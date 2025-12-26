<?php

class TAccountCheckerWynnredcard extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://profile.wynnresorts.com/Profile/';

    public static function FormatBalance($fields, $properties)
    {
        if (
            isset($properties['SubAccountCode'], $properties['Currency'])
            && $properties['Currency'] == "USD"
        ) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }
        $this->selenium();
        return true;
        //$this->http->GetURL(self::REWARDS_PAGE_URL);

        if (!$this->http->ParseForm(null, '//form[contains(@class,"_form-login-id")]')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('webauthn-platform-available', 'false');
        $this->http->SetInputValue('webauthn-available', 'false');
        $this->http->SetInputValue('is-brave ', 'false');

        $this->http->SetInputValue('js-available', 'false');


        if ($key = $this->http->FindSingleNode('//div[@data-captcha-sitekey]/@data-captcha-sitekey')) {
            $captcha = $this->parseReCaptcha($key);

            if ($captcha !== false) {
                $this->http->SetInputValue('g-recaptcha-response', $captcha);
                $this->http->SetInputValue('captcha', $captcha);
            }
        }

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if (!$this->http->ParseForm(null, '//form[contains(@class,"_form-login-password")]')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV2TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            //"websiteURL"   => "https://www.garuda-indonesia.com/oc/en/login",
            "websiteKey"   => $key,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "version"   => "v2",
            "enterprise"   => true,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[@id="prompt-alert"]/p')) {
            $this->logger->error("[Error]: {$message}");
            // We have detected a potential security issue with this account. To protect your account, we have prevented this login. Please reset your password to proceed
            if (
                stristr($message, 'We have detected a potential security issue with this account')
            ) {
                throw new CheckException("We have detected a potential security issue with this account. To protect your account, we have prevented this login. Please reset your password to proceed", ACCOUNT_INVALID_PASSWORD);
            }
            //$this->DebugInfo = $message;
            return false;
        }


        return false;
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        if ($this->http->ParseForm(null, '//form[contains(@class,"_form-detect-browser-capabilities")]')) {
            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }
        }
        if ($this->http->ParseForm(null, '//button[contains(text(),"Remind me later")]/ancestor::form')) {
            $this->http->SetInputValue('action', 'snooze-enrollment');

            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }
            /*$headers = [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];
            $data = [
                'state' => '',
                'action' => 'snooze-enrollment',

            ];
            $this->http->PostURL("https://auth.wynnresorts.com/u/mfa-webauthn-platform-enrollment?state=", $data, $headers);*/
        }


        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[contains(@class,"ulp-input-error-message")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'The email address or password entered is incorrect. Please try again.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[@class="rc-user-info-wrap"]/p[1]')));
        // Number
        $Number = $this->http->FindSingleNode('//div[@class="rc-user-info-wrap"]/p[2]');
        $this->SetProperty('Number', $Number);
        // Balance - points
        $this->SetBalance($this->http->FindSingleNode('//p[contains(@aria-label, "Slot Point Balance is")]', null, false, "/[^A-z\s]+/"));
        // Tier level
        $this->SetProperty('TierLevel', $this->http->FindSingleNode('//h2[contains(text(), "Tier Level")]', null, false, '/Tier Level: (.*)/'));
        // Points to next level
        $this->SetProperty('PointsToNextLevel', $this->http->FindSingleNode('//span[contains(text(), "Tier Credits needed by")]/@aria-label', null, false, '/[^A-z\s]+/'));

        // SubAccounts
        // Freecredit Las Vegas, Boston Or WinBet
        $WRCFCLVBWB = $this->http->FindSingleNode('//p[contains(@aria-label, "Las Vegas, Boston, or WynnBET Reward")]', null, false, '/[^$A-z\s]+/');
        // Freecredit Las Vegas only
        $WRCFCLV = $this->http->FindSingleNode('//p[contains(@aria-label, "Las Vegas only")]', null, false, '/[^$A-z\s]+/');
        // Compdollars
        $WRCCD = $this->http->FindSingleNode('//p[contains(@aria-label, "comp dollars gathered ")]', null, false, '/[^$A-z\s]+/');
        // Tier Credits
        $WRCTC = $this->http->FindSingleNode('//p[contains(@aria-label, "tier credits")]', null, false, '/[^A-z\s]+/');

        if (isset($WRCFCLVBWB)) {
            $this->AddSubAccount([
                "Code"        => "FreecreditLasVegasBostonOrWinBet{$Number}",
                "DisplayName" => "Freecredit Las Vegas, Boston Or WinBet",
                "Balance"     => $WRCFCLVBWB,
                "Currency"    => "USD",
            ]);
        }

        if (isset($WRCFCLV)) {
            $this->AddSubAccount([
                "Code"        => "FreecreditLasVegasOnly{$Number}",
                "DisplayName" => "Freecredit Las Vegas only",
                "Balance"     => $WRCFCLV,
                "Currency"    => "USD",
            ]);
        }

        if (isset($WRCCD)) {
            $this->AddSubAccount([
                "Code"        => "Compdollars{$Number}",
                "DisplayName" => "Compdollars",
                "Balance"     => $WRCCD,
                "Currency"    => "USD",
            ]);
        }

        if (isset($WRCTC)) {
            $this->AddSubAccount([
                "Code"        => "TierCredits{$Number}",
                "DisplayName" => "Tier Credits",
                "Balance"     => $WRCTC,
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[text()="Sign Out"]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            $selenium->disableImages();
//            $selenium->http->setUserAgent($this->http->userAgent);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL(self::REWARDS_PAGE_URL);
            $selenium->waitForElement(WebDriverBy::xpath('//form'), 15);

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 15);
            $loginBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@name="action" and contains(@class,"_button-login-id")]'), 0);

            if (!$loginInput && !$loginBtn) {
                $this->savePageToLogs($selenium);
                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $this->savePageToLogs($selenium);

            if ($key = $this->http->FindSingleNode('//div[@data-captcha-sitekey]/@data-captcha-sitekey')) {
                $captcha = $this->parseReCaptcha($key);

                if ($captcha !== false) {
                    $selenium->driver->executeScript("document.getElementsByName('g-recaptcha-response').value = '{$captcha}';");
                    $selenium->driver->executeScript("document.querySelector('input[name=captcha]').value = '{$captcha}';");
                }
            }

            $loginBtn->click();
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 10);
            $loginBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@name="action" and contains(@class,"_button-login-password")]'), 0);

            if (!$passwordInput && !$loginBtn) {
                $this->savePageToLogs($selenium);

                return false;
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $loginBtn->click();

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return false;
    }
}
