<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerWaterstones extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public const PROFILE_URL = "https://www.waterstones.com/account/contactdetails";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->http->SetProxy($this->proxyDOP());
        $this->UseSelenium();
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
//        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
        $this->setKeepProfile(true);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::PROFILE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.waterstones.com/signin');

        $this->waitForElement(WebDriverBy::xpath("//input[@id = 'login_form_email'] | //div[@id = 'turnstile-wrapper']//iframe"), 10);
        $this->saveResponse();

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->saveResponse();
        }

        $this->waitForElement(WebDriverBy::id("login_form_email"), 10);

        try {
            $this->removePopup();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 0);
        }

        $login = $this->waitForElement(WebDriverBy::id("login_form_email"), 0);
        $pass = $this->waitForElement(WebDriverBy::id("login_form_password"), 0);

        // save page to logs
        $this->saveResponse();

        if (!$login || !$pass) {
            $this->logger->error("something went wrong");

            if ($this->http->FindSingleNode('//h1[contains(text(), "An error has occurred connecting to Waterstones")]')) {
                throw new CheckRetryNeededException(3, 0);
            }

            return false;
        }

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = rand(10000, 60000);
        $mover->steps = rand(30, 60);

        $mover->sendKeys($login, $this->AccountFields['Login'], 6);
        $mover->sendKeys($pass, $this->AccountFields['Pass'], 6);
        $this->saveResponse();
        $this->logger->debug("click");

        try {
            $btn = $this->waitForElement(WebDriverBy::xpath("//button[@name = 'Login']"), 0);
            $btn->click();
        } catch (TimeOutException $e) {
            $this->increaseTimeLimit(100);
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }

        /*
        $this->http->GetURL("https://www.waterstones.com/signin");

        if (!$this->http->ParseForm('loginForm')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("keepLoggedIn", "on");
        $this->http->SetInputValue("Login", "1");
        $captcha = $this->parseReCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We've had to close Waterstones.com for a little while, due to some essential maintenance work.
        if ($message = $this->http->FindPreg('/We\'ve had to close Waterstones.com for a little while, due to some essential maintenance work\.\&nbsp;We\'ll be back up and running soon\./ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('
                //strong[contains(text(), "Waterstones.com is very busy right now.")]
                | //p[contains(text(), "Waterstones.com is undertaking some planned maintenance.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
//        if (!$this->http->PostForm()) {
//            return $this->checkErrors();
//        }

        // We've been unable to sign you in to your account. Please try again, by typing your email address and password below.
        // We've been unable to sign you in to your account. Please re-enter your email address and password to try again.
        $wrongErrorXpath = '//p[contains(text(), "ve been unable to sign you in to your account. Please")]';

        $this->logger->debug("wait result");

        $this->waitForElement(WebDriverBy::xpath("
            //a[contains(text(), 'Sign out')]
            | {$wrongErrorXpath}
        "), 15);
        $this->saveResponse();

        // Your login details are invalid. Please try again.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Your login details are invalid. Please try again.")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (
            (
                $this->http->FindSingleNode($wrongErrorXpath)
                || $this->http->FindSingleNode("//form[@id = 'loginForm']//div[contains(@class, 'g-recaptcha')]/@data-sitekey")
            )
            && $this->http->ParseForm('loginForm')
        ) {
            $this->DebugInfo = "need to recognize recaptcha";

            $login = $this->waitForElement(WebDriverBy::id("login_form_email"), 10);
            $pass = $this->waitForElement(WebDriverBy::id("login_form_password"), 0);
            $btn = $this->waitForElement(WebDriverBy::xpath("//button[@name = 'Login']"), 0);
            // save page to logs
            $this->saveResponse();

            if (!$login || !$pass || !$btn) {
                $this->logger->error("something went wrong");

                return false;
            }

            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);
            $pass->click();
            $pass->clear();
            $pass->sendKeys($this->AccountFields['Pass']);

            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->logger->debug('setting captcha: ' . $captcha);
            $this->driver->executeScript('
                $(\'#g-recaptcha-response\').val(\'' . $captcha . '\');
                loginForm.submit();
            ');
            sleep(1);

            $this->waitForElement(WebDriverBy::xpath("
                //a[contains(text(), 'Sign out')]
            "), 10);
            $this->saveResponse();
            /*
            $this->http->SetInputValue("email", $this->AccountFields['Login']);
            $this->http->SetInputValue("password", $this->AccountFields['Pass']);
            $this->http->SetInputValue("keepLoggedIn", "on");
            $this->http->SetInputValue("secure", "1");
            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue('g-recaptcha-response', $captcha);

            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }
            */
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($message = $this->http->FindPreg('/Your login details are invalid.[^.]+./ims')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // WELCOME TO OUR NEW WEBSITE
        if ($this->http->FindPreg('/<p>As you\&rsquo;re logging in for the first time you\&rsquo;ll need to re-set your password\./')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Waterstones (Waterstones Card) website is asking you to re-set your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        // Please complete the captcha.
        if (
            $this->http->FindSingleNode('//p[contains(text(), "Please complete the captcha.")]')
            || $this->http->FindSingleNode($wrongErrorXpath)
        ) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 1);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(@class, "error")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'We regularly review and enhance our IT security measures and we request that you create a new and improved password.')) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::PROFILE_URL) {
            try {
                $this->http->GetURL(self::PROFILE_URL);
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
                $this->saveResponse();
            }
        }
        // Name
        $firstName = $this->http->FindSingleNode("//input[contains(@name,'user[first_name]')]/@value");
        $surName = $this->http->FindSingleNode("//input[contains(@name,'user[last_name]')]/@value");
        $this->SetProperty("Name", beautifulName($firstName . ' ' . $surName));

        // Available Waterstones Card balance
        try {
            $this->http->GetURL("https://www.waterstones.com/account/waterstonescard");
        } catch (TimeOutException $e) {
            $this->increaseTimeLimit(100);
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }
        $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'Available ') and contains(., 'balance:')]/following-sibling::div[1]/strong", null, true, self::BALANCE_REGEXP));
        // Waterstones Card no.
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//div[@class = 'plus-cardno']"));
        // Current stamps balance
        $this->SetProperty("CurrentStamps", $this->http->FindSingleNode('//div[contains(text(), "Current stamps balance:")]/following-sibling::div/strong', null, true, "/^(\d+)/"));
        // Pending stamp cards
        $this->SetProperty("PendingStamp", $this->http->FindSingleNode('//div[contains(text(), "Pending stamp cards:")]/following-sibling::div/strong', null, true, "/^(\d+)/"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindPreg("/You don't currently have a Waterstones Card associated with your account\./")) {
                $this->SetBalanceNA();
            }

            if ($this->http->FindPreg('#.com/plus#', false, $this->http->currentUrl()) && $this->http->FindSingleNode("//div[contains(text(),'Register for your') and contains(.,'Plus card online')]")) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // An error has occurred in the Waterstones Card system, please try again later.
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'An error has occurred in the Waterstones Card system, please try again later.')] | //h2[contains(., 'rewards are currently down')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Thank you for choosing to upgrade to Waterstones Plus, please take a moment to confirm your details below. By joining our hugely popular email programme, you'll be all set to enjoy our complete suite of Waterstones Plus rewards.
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'please take a moment to confirm your details below.')]")) {
                $this->throwProfileUpdateMessageException();
            }

            // provider error
            if (
                $this->http->Response['code'] == 500
                && !empty($this->Properties['Name'])
                && $this->http->FindSingleNode("//div[contains(text(), 'Current') and contains(., 'Balance:')]/strong") == '£'
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // Available gift card balance
        try {
            $this->http->GetURL("https://www.waterstones.com/account/giftcards");
        } catch (UnknownServerException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }
        $this->SetProperty("GiftCardBalance", $this->http->FindSingleNode("//div[h2[contains(text(),'card balance:')]]/child::h2", null, true, '/£[\d\.]+/ims'));
    }

    protected function parseReCaptcha($isV3 = false)
    {
        $this->logger->notice(__METHOD__);

        if ($isV3 === true) {
            $key = $this->http->FindSingleNode("//form[@id = 'loginForm']//script[contains(text(), 'enableRecaptchaV3')]", null, true, "/enableRecaptchaV3\(\"([^\"]+)/");
        } else {
            $key =
                $this->http->FindSingleNode("//form[@id = 'loginForm']//div[contains(@class, 'g-recaptcha')]/@data-sitekey")
                ?? $this->http->FindSingleNode("//div[@id='g-recaptcha-modal']/@data-sitekey")
            ;
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        if ($isV3) {
            $parameters += [
                "version"   => "v3",
                "action"    => "login",
                "min_score" => 0.3,
            ];
        }

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function removePopup()
    {
        $this->logger->notice(__METHOD__);
        sleep(5);
        $this->saveResponse();
        $this->driver->executeScript('var c = document.getElementById("onetrust-accept-btn-handler"); if (c) c.click();');
        $this->driver->executeScript("let trust = document.getElementById('onetrust-consent-sdk'); if (trust) trust.style.display = 'none';");
        $this->saveResponse();

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(text(), 'Sign out')]")) {
            return true;
        }

        return false;
    }
}
