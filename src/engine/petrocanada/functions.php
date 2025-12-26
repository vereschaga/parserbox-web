<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPetrocanada extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const WAIT_TIMEOUT = 10;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->setProxyGoProxies();
        $this->setKeepProfile(true);
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->usePacFile(false);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.petro-canada.ca/en/personal/my-petro-points", [], 20);
        $this->http->RetryCount = 2;

        $this->waitForElement(WebDriverBy::xpath('//span[(@class="user-info__card-number")] | //input[@id="email"]'), self::WAIT_TIMEOUT);

        $this->saveResponse();

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        if (!strstr($this->http->currentUrl(), "/en/personal/login"))
            $this->http->GetURL("https://www.petro-canada.ca/en/personal/login?returnUrl=%2Fen%2Fpersonal%2Fmy-petro-points");

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="email"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($login) {
            $this->http->GetURL("https://www.petro-canada.ca/en/personal/login?returnUrl=%2Fen%2Fpersonal%2Fmy-petro-points");
            $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="email"]'), self::WAIT_TIMEOUT);
            $this->saveResponse();
        }

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="email"]'), self::WAIT_TIMEOUT);
            $this->saveResponse();
        }

        // $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="email"]'), self::WAIT_TIMEOUT);
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@id="password"]'), 0);

        if (!$login || !$password) {
            $this->logger->error("Failed to find form fields");
            $this->saveResponse();

            if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Sorry, you have been blocked")]')) {
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
                $this->DebugInfo = $message;

                return false;
            }

            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);

        $this->saveResponse();

        /*

        $captcha = $this->parseCaptcha();

        $this->driver->executeScript('document.getElementsByName("g-recaptcha-response")[0].value = "' . $captcha . '";');

        $this->saveResponse();


        $this->logger->notice("Executing captcha callback");

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

        $this->saveResponse();
        */

        sleep(2);
        $submit = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "sign-in") and contains(@class, "button") and not(contains(@class, "button--disabled")) and not(@disabled)]'), self::WAIT_TIMEOUT);

        if (!$submit) {
            $this->logger->error("Failed to find form fields");

            return $this->checkErrors();
        }

        $submit->click();

        sleep(7);

        $this->saveResponse();

        $submit = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "sign-in") and contains(@class, "button")]'), 0);
        $this->saveResponse();

        if ($submit) {
            $this->logger->notice("Retry click");

            try {
                $submit->click();
            } catch (StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
            } catch (UnrecognizedExceptionException $e) {
                $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $this->driver->executeScript("document.querySelector('.sign-in__button').click();");
            }
        }

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "form-error") and contains(@class, "visible")] | //span[(@class="user-info__card-number")]'), self::WAIT_TIMEOUT * 3);

        $this->saveResponse();

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "form-error") and contains(@class, "visible") and @data-form-error]/text()')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "The email or password you entered is not correct. Please try again. Passwords must have 8-16 characters and at least one number, one lowercase letter, one uppercase letter and one special character.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            // The email or password you entered is not correct. Please try again. Passwords must have 8-16 characters and at least one number, one lowercase letter, one uppercase letter and one special character (from @$!%*#?& ).
            if (strstr($message, 'invalid.password')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Sorry, but your account has been temporarily locked-out. Please try again in 30 minutes, or contact our customer service team for help.
            if (
                strstr($message, 'profile.soft.locked')
                || strstr($message, 'Sorry, the login function has been disabled for 180 minutes.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, 'invalid.recaptcha')) {
                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }

            // Sorry, an error occurred on our servers. Our tech team has been notified.
            if (strstr($message, 'internal.error')) {
                throw new CheckRetryNeededException(3, 7, $message);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[@class = 'account-well__name'] | //div[@class = 'user-info__full-name']")));
        // Account #
        $this->SetProperty("Number", $this->http->FindSingleNode("//span[(@class = 'user-info__card-number')]"));
        // Balance - Petro-Points
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'You have')]/following-sibling::strong[1]"));
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Web site is experiencing a technical glitch
        if ($message = $this->http->FindSingleNode('//p[contains(text(),"possible that our Web site is experiencing a technical glitch")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Petro-Points™ login is temporarily unavailable due to scheduled maintenance.
        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "Petro-Points™ login is temporarily unavailable due to scheduled maintenance.")]
                | //div[contains(@class, "content") and contains(., "Petro-Points login will be unavailable due to scheduled maintenance on")]
                | //h1[contains(text(), "We\'re working on our site right now.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            // Service Unavailable
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '504 Gateway Time-out')]")
            || $this->http->FindPreg("/(Service Unavailable)/ims")
            || $this->http->FindPreg("/(The service is unavailable\.)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);

        $key = $this->http->FindSingleNode('//div[@id="recaptcha"]/@data-sitekey');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;

        $parameters = [
            "pageurl"     => $this->http->currentUrl(),
            "proxy"       => $this->http->GetProxy(),
            'type'        => 'ReCaptchaV2TaskProxyLess',
            "isInvisible" => true,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//button[contains(text(), 'Sign out')]")) {
            if ($this->http->FindPreg('/<h1 class="content__headline">It\'s time to create a new password.<\/h1>/')) {
                throw new CheckException("{$this->AccountFields['DisplayName']} website is asking you to create a new password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }

            return true;
        }

        return false;
    }
}
