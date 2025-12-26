<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerOdeonSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();

        /*
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
        $this->keepCookies(false);
        */

        /*
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);

        $this->setKeepProfile(true);
        */
//        $this->disableImages();
        $this->usePacFile(false);
        $this->http->saveScreenshots = true;

//        $this->setProxyBrightData(null, 'static', 'uk');
//        $this->http->SetProxy($this->proxyDOP(['lon1']));
//        $this->http->SetProxy($this->proxyUK());
        $this->setProxyGoProxies();

        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);
        /*
        $this->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);
        */
    }

    /*
    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL("https://www.odeon.co.uk/my-account/details/");
        $this->http->RetryCount = 2;

        // Accept All Cookies
        $this->removePopup();

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }
    */

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();

        if (!$this->getToken()) {
            if ($this->http->FindSingleNode('//h1[contains(text(), "The proxy server is refusing connections") or contains(text(), "Sorry, you have been blocked")]')) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }

        $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe | //input[@name = 'email'] | //div[@id and @style=\"display: grid;\"] | //div[@id=\"ulp-auth0-v2-captcha\"] | //div[@id=\"cf-turnstile\"] "), 5);

        // Accept All Cookies
        try {
            $this->removePopup();

            $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe | //input[@name = 'email']"), 5);
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe | //input[@name = 'email']"), 10);
            $this->saveResponse();
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "email"]'), 0);

        if (!$loginInput && $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Forgot your password?")]'), 0)) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }

        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Sign in")]'), 0);
        $rememberMe = $this->waitForElement(WebDriverBy::xpath('//label[contains(., "Keep me signed in")]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button || !$rememberMe) {
            if ($this->loginSuccessful()) {
                return true;
            }

            return $this->checkErrors();
        }

        $this->logger->debug("set login");
        $loginInput->sendKeys($this->AccountFields['Login']);
        $this->logger->debug("set password");
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->logger->debug("set remember me");

        $this->removePopup();

        $rememberMe->click();

        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "v-captcha")]'), 0)) {
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->logger->debug('setting captcha: ' . $captcha);

            try {
                $this->driver->executeScript('
                    var findCb = (object) => {
                        if (!!object["callback"] && !!object["sitekey"]) {
                            return object["callback"]
                        } else {
                            for (let key in object) {
                                if (typeof object[key] == "object") {
                                    return findCb(object[key])
                                } else {
                                    return null
                                }
                            }
                        }
                    }
                    findCb(___grecaptcha_cfg.clients[0])("' . $captcha . '")
                ');
            } catch (NoSuchDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage());

                throw new CheckRetryNeededException(2, 5);
            }
            sleep(1);
        }

        $button->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//a[contains(., "Sign out")] | //div[contains(@class, "style-error")]'), 30);
        $this->saveResponse();

        $message = $this->http->FindSingleNode('(//div[contains(@class, "style-error")])[last()]');

        $success = false;

        try {
            $cookies = $this->driver->manage()->getCookies();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        foreach ($cookies as $cookie) {
            if ($cookie['name'] == "vista-loyalty-member-authentication-token") {
                $success = true;
            }
        }

        if ($success && !$message) {
            $this->logger->notice("try 2");
            $this->captchaReporting($this->recognizer);

            return $this->loginSuccessful();
        }

        $this->saveResponse();

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Details not recognised.')) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Your account has been locked for 15 minutes due to too many failed sign in attempts. ')) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (
                strstr($message, 'Sign in failed, please try again.')
                || $message == 'Sorry something went wrong, please try again.'
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

//            if ($detail == 'Failed CAPTCHA validation') {
//                throw new CheckRetryNeededException(2, 1, self::CAPTCHA_ERROR_MSG);
//            }

            $this->DebugInfo = $message;

            return false;
        }
        // Sign in failed, please try again.
        if (
            $this->http->FindSingleNode('//h1[contains(text(), "403 Forbidden")]')
            && $this->http->FindSingleNode('//center[contains(text(), "Microsoft-Azure-Application-Gateway/v2")]')
            && strstr($this->AccountFields['Pass'], '^')// AccountID: 3855715
        ) {
            throw new CheckException("Sign in failed, please try again.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        /*
        $this->waitForElement(WebDriverBy::xpath('//input[@name = "givenName"]/@value'), 5);
        $this->saveResponse();
        // Name
        $firstName = $this->http->FindSingleNode('//input[@name = "givenName"]/@value');
        $lastName = $this->http->FindSingleNode('//input[@name = "familyName"]/@value');
        $this->SetProperty('Name', beautifulName("$firstName $lastName"));
        */

        try {
            $this->http->GetURL("https://www.odeon.co.uk/my-account/membership/");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: {$e->getMessage()}");

            throw new CheckRetryNeededException(3, 0);
        }
        $this->waitForElement(WebDriverBy::xpath('//li[contains(text(), "Member Since")]/following-sibling::li | //div[contains(text(), "THE CLUB SINCE:")]/following-sibling::div'), 5);
        $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "MEMBERSHIP NUMBER")]/following-sibling::div'), 5);
        $this->saveResponse();
        // Name
        $this->SetProperty('Name', $this->http->FindSingleNode('//div[contains(text(), "Name")]/following-sibling::div'));
        // Membership Number
        $this->SetProperty('Number', $this->http->FindSingleNode('//div[contains(text(), "MEMBERSHIP NUMBER")]/following-sibling::div'));
        // Member since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//li[contains(text(), "Member Since")]/following-sibling::li | //div[contains(text(), "THE CLUB SINCE:")]/following-sibling::div'));
        // Type of membership
        $this->SetProperty('TypeOfMembership', $this->http->FindSingleNode('//li[normalize-space() = "Type"]/following-sibling::li'));

        if (
            !empty($this->Properties['Name'])
            && !empty($this->Properties['Number'])
            && !empty($this->Properties['MemberSince'])
//            && !empty($this->Properties['TypeOfMembership'])
        ) {
            $this->SetBalanceNA();
        }
    }

    public function getToken()
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->http->GetURL('https://www.odeon.co.uk/sign-in');
        } catch (UnexpectedAlertOpenException | Facebook\WebDriver\Exception\UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException | Facebook\WebDriver\Exception\NoSuchAlertException $e) {
                $this->logger->debug("no alert, skip");
            }
        }

        sleep(5);
        $this->saveResponse();

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe | //input[@name = 'email']"), 10);
            $this->saveResponse();
        }

        $authToken = $this->http->FindPreg("/\"authToken\":\"([^\"]+)/");

        if (!$authToken) {
            return false;
        }
        $this->headers = [
            "authorization" => "Bearer {$authToken}",
        ];

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->waitForElement(WebDriverBy::xpath('//a[contains(., "Sign out")]'), 3)) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We are sorry for the inconvenience, but our site is")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);

        $key = $this->http->FindPreg("/\"captcha\":\{\"siteKey\":\"([^\"]+)/");

        if (!$key) {
            return false;
        }

        /*
        $postData = array_merge(
            [
                "type"       => "NoCaptchaTaskProxyless",
                "websiteURL" => $this->http->currentUrl(),
                "websiteKey" => $key,
                "userAgent"  => $this->http->getDefaultHeader("User-Agent"),
            ],
            []
        );
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        */

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function removePopup()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        $this->driver->executeScript('var c = document.getElementById("onetrust-accept-btn-handler"); if (c) c.click();');
        $this->driver->executeScript('let trust = document.getElementById(\'onetrust-consent-sdk\'); if (trust) trust.style.display = \'none\';');
        $this->saveResponse();

        return true;
    }
}
