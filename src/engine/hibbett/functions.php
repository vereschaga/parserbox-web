<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerHibbett extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.hibbett.com/account';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'hibbettAward')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        /*
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
//        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->setKeepProfile(true);
//        $this->setProxyGoProxies();
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        */

        $this->setProxyMount();
//        $this->useFirefoxPlaywright();
        $this->useChromePuppeteer();
//        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->usePacFile(false);
        $this->http->saveScreenshots = true;

        $this->http->FilterHTML = false;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;

        try {
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException $e) {
                $this->logger->debug("no alert, skip");
            }
        }

        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->GetURL("https://www.hibbett.com/");
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "dwfrm_login_username"]'), 15); // fake

        if ($this->clickPressAndHoldByMouse($this)) {
            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "dwfrm_login_username"]'), 15); // fake
        }

        // save page to logs
        $this->saveResponse();
        $this->driver->executeScript("$('a:contains(\"Sign In\")').get(0).click()");

//        $this->http->GetURL(self::REWARDS_PAGE_URL);

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "dwfrm_login_username"]'), 5);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "dwfrm_login_password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "account-login-button"]'), 0);
        // save page to logs
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Please verify you are a human")]')) {
                $this->DebugInfo = $message;
            }

            return $this->checkErrors();
        }

        $this->driver->executeScript("let remMe = document.querySelector('input[name=dwfrm_login_rememberme]'); if (remMe) remMe.checked = true;");
        $loginInput->clear();
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->driver->executeScript("$('#g-recaptcha-response').val(\"" . $captcha . "\"); recaptchaFormSubmit();");
        /*
        $button->click();
        */

        $this->waitForElement(WebDriverBy::xpath('
            //div[@class = "error-form"]
            | //span[@class = "error"]
            | //div[@id = "primary"]//strong[contains(text(), "Rewards#")]/following-sibling::span
            | //div[@class = "status" and contains(., "Member since")]
        '), 10);
        $this->saveResponse();

        /*
        $xpathBlock = "
            //title[contains(text(), 'Access to this page has been denied')]
            | //p[contains(text(), 'We seem to have misplaced this page. Please try the search function below to help you find what you were looking for.')]
            | //p[contains(text(), 'This website is using a security service to protect itself from online attacks.')]
        ";

        if ($this->http->FindSingleNode($xpathBlock)) {
            $this->http->removeCookies();
            $this->http->SetProxy($this->proxyReCaptcha());
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            if ($this->http->FindSingleNode($xpathBlock)) {
                $this->http->removeCookies();
                $this->http->SetProxy($this->proxyReCaptchaIt7());
                $this->http->GetURL(self::REWARDS_PAGE_URL);

                if ($this->http->FindPreg("/<form class = \"refreshButton\">/")) {
                    sleep(1);
                    $this->http->GetURL(self::REWARDS_PAGE_URL);
                }
            }
        }

        if (!$this->http->ParseForm("dwfrm_login")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('dwfrm_login_username', $this->AccountFields['Login']);
        $this->http->SetInputValue('dwfrm_login_password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('dwfrm_login_login', "Login");
        $this->http->SetInputValue('dwfrm_login_rememberme', "true");
        */

        return true;
    }

    protected function clickPressAndHoldByMouse(
        $selenium,
        $captchaElemXpath = '//div[@class = "px-captcha-container"]',
        $xOffset = 160,
        $yOffset = 50
    ) {
        $this->logger->notice(__METHOD__);
        $isSeleniumMainEngine = true;

        if (!$selenium) {
            $selenium = $this;
            $isSeleniumMainEngine = false;
        }

        $this->logger->debug("xOffset: {$xOffset} / yOffset: {$yOffset}");

        $captchaElem = $selenium->waitForElement(WebDriverBy::xpath($captchaElemXpath), 0);
        $this->saveLogs($isSeleniumMainEngine);

        if (!$captchaElem) {
            return false;
        }

        $mover = new MouseMover($selenium->driver);
        $mover->logger = $selenium->logger;
        $mouse = $selenium->driver->getMouse();
        $mover->enableCursor();

        $mouse->mouseMove($captchaElem->getCoordinates());
        $this->saveLogs($isSeleniumMainEngine);

        if ($this->driver instanceof RemoteWebDriver) {
            // unsupported in new versions of webdriver
            $captchaCoords = $captchaElem->getCoordinates()->inViewPort();
        } else {
            $captchaCoords = $captchaElem->getLocation();
        }

        $this->logger->info(var_export([
            'x' => $captchaCoords->getX(),
            'y' => $captchaCoords->getY(),
        ], true), ['pre' => true]);

        $x = intval($captchaCoords->getX() + $xOffset);
        $y = intval($captchaCoords->getY() + $yOffset);
        $mover->moveToCoordinates(['x' => $x, 'y' => $y], ['x' => 0, 'y' => 0]);
        $this->saveLogs($isSeleniumMainEngine);
        $this->logger->debug("clicking Verify you are human button");
        $mouse->mouseDown();

        sleep(3);
        $this->saveLogs($isSeleniumMainEngine);
        $mouse->mouseDown();
        $this->saveLogs($isSeleniumMainEngine);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode('
                //marquee[@id = "dock-maintenance"]
                | //h1[contains(text(), "Our site is temporary offline for maintenance.")]
                | //img[@alt = "Our Site is Down for Maintenance"]/@alt
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        /*
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($redirect = $this->http->FindPreg("/redirect\":\"([^\"]+)\"/ims")) {
            $redirect = stripslashes($redirect);
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);
        }// if ($redirect = $this->http->FindPreg("/redirect\":\"([^\"]+)\"/ims"))
        */

        // login successful
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        // The username or password you entered is invalid. Please try again.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Sorry, this does not match our records.')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Please Call Customer Service at 1-844-362-4422.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Please Call Customer Service at 1-844-362-4422.')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//div[@class = "error-form"] | //span[@class = "error"]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Your email address or password is incorrect. ')) {// todo: false/positive error
                throw new CheckRetryNeededException(2, 3, $message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Invalid password. This account will be locked after one more invalid login')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Your account is temporarily locked to prevent unauthorized use')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if ($this->http->currentUrl() == 'https://www.hibbett.com/') {
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            $this->waitForElement(WebDriverBy::xpath('//div[@id = "primary"]//strong[contains(text(), "Rewards#")]/following-sibling::span'), 5);
            $this->saveResponse();
        }

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[@id = "primary"]//div[@class = "customer-name"]', null, true, "/Hi\s+(.+), Welcome Back/")));
        // Account #
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode('//div[@id = "primary"]//strong[contains(text(), "Rewards#")]/following-sibling::span'));
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode('//div[@id = "primary"]//div[@class = "account-member"]', null, true, "/since\s*([^<]+)/"));
        // Balance - My Points
        $this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "reward-points")]//div[@class = "value "]'));
        // Points needed to next Certificate
        $this->SetProperty("PointsNeededToNextCertificate", $this->http->FindSingleNode('//div[@id = "primary"]//div[@class = "points-details"]/strong', null, true, self::BALANCE_REGEXP));

        // My Awards
        $this->http->GetURL("https://www.hibbett.com/mvp-awards");

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "No Awards or Offers available at this time.")]')) {
            $this->logger->notice($message);
        }

        $awards = $this->http->XPath->query("//div[@class = 'mvp-offer-single-outer']");
        $this->logger->debug("Total {$awards->length} awards were found");

        foreach ($awards as $award) {
            $code = $this->http->FindSingleNode("div[@class = 'award-number']", $award);
            $balance = $this->http->FindSingleNode("div[contains(@class, 'amount')]/span[contains(@class, 'value')]", $award);
            $exp = $this->http->FindSingleNode("div[contains(@class, 'expiration')]/span[contains(@class, 'value')]", $award);
            $subAccount = [
                "Code"        => 'hibbettAward' . $code,
                "DisplayName" => "Offer {$code}",
                "Balance"     => $balance,
            ];

            if ($exp && ($exp = strtotime($exp))) {
                $subAccount['ExpirationDate'] = $exp;
            }
            $this->AddSubAccount($subAccount, true);
        }// foreach ($awards as $award)
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'dwfrm_login']//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('
            //div[@id = "primary"]//strong[contains(text(), "Rewards#")]/following-sibling::span
            | //div[@class = "status" and contains(., "Member since")]
        ')) {
            return true;
        }

        return false;
    }
}
