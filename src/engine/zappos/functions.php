<?php

class TAccountCheckerZappos extends TAccountChecker
{
    use SeleniumCheckerHelper;
    private const REWARDS_PAGE_URL = 'https://www.zappos.com/account';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
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

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function getMetadataKey()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $xKeys = null;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://www.zappos.com/zap/preAuth/signin?openid.return_to=/c/zappos-homepage");

            $loginInput = $selenium->waitForElement(WebDriverBy::id('ap_email'), 10, false);
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('ap_password'), 0, false);

            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if (!$loginInput || !$passwordInput) {
                return $this->checkErrors();
            }
            /*
            $selenium->driver->executeScript("
                        $('form[name = \"signIn\"]').find('input[name = \"email\"]').val('{$this->AccountFields['Login']}');
                        $('form[name = \"signIn\"]').find('input[name = \"password\"]').val('".addcslashes($this->AccountFields['Pass'], "'\\")."');
                    ");
            */
            // Sign In
            $selenium->driver->executeScript("
                document.getElementById('signInSubmit').click();
                window.stop();
            ");

            // uniqueStateKey
            if ($xKey = $selenium->waitForElement(WebDriverBy::xpath('//form[@name = "signIn"]//input[contains(@name, "metadata1")]'), 5, false)) {
                $xKeys = $xKey->getAttribute("value");
                $this->logger->debug(var_export($xKeys, true), ["pre" => true]);
            }
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 7);
            }
        }

        return $xKeys;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.zappos.com/zap/preAuth/signin?openid.return_to=/c/zappos-homepage');

        if (!$this->http->ParseForm('signIn')) {
            return $this->checkErrors();
        }
        $xKeys = $this->getMetadataKey();

        if (!$this->http->ParseForm('signIn') || empty($xKeys)) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://auth.zappos.com/ap/signin';
        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('metadata1', $xKeys);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm(['Origin' => 'https://auth.zappos.com'])) {
            return $this->checkErrors();
        }

        $this->http->MultiValuedForms = true;

        if (
            $this->http->FindSingleNode("//*[contains(text(), 'To better protect your account, please re-enter your password')]")
            && $this->http->ParseForm("signIn")
        ) {
            // parse captcha
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue('email', $this->AccountFields['Login']);
            $this->http->SetInputValue('password', $this->AccountFields['Pass']);
            $this->http->SetInputValue('guess', str_replace(' ', '', $captcha));

            $this->http->PostForm();
        }

        if (
            $this->http->FindSingleNode("//*[contains(text(), 'Solve this puzzle to protect your account')]")
            && $this->http->ParseForm(null, '//form[@action="verify"]')
        ) {
            // parse captcha
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->http->SetInputValue('cvf_captcha_input', str_replace(' ', '', $captcha));

            $this->http->PostForm();
        }

        $this->http->MultiValuedForms = false;

        // check auth
        if ($this->loginSuccessful()) {
            return true;
        }

        // We cannot find an account with that email address
        if ($message = $this->http->FindSingleNode("//li/span[contains(text(),'We cannot find an account with that email address')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//li/span[contains(text(),'Your password is incorrect')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode("//div[@id = 'auth-error-message-box']/descendant::span[contains(text(), 'Enter the characters as they are given in the challenge.')]")) {
            throw new CheckRetryNeededException(2, 1, self::CAPTCHA_ERROR_MSG);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() !== self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Join Zappos VIP")]')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $headers = [
            'X-Mafia-Session-Token'     => str_replace('"', '', $this->http->getCookieByName('session-token')),
            'X-Mafia-Auth-Requested'    => true,
            'X-Mafia-Recognized-Token'  => str_replace('"', '', $this->http->getCookieByName('x-main')),
            'X-Mafia-Session-Id'        => '134-8158164-5020661',
            'X-Mafia-Session-Requested' => true,
            'X-Mafia-Ubid-Main'         => '132-0476581-3639262',
        ];

        $this->http->GetURL('https://amazon.zappos.com/mobileapi/akita/slotz/recognized/v1/customers', $headers + ["x-api-key" => "82d48292-c2b6-493d-82f7-6beb65300958"]);
        $response = $this->http->JsonLog();
        // Redeemable Points
        $this->SetBalance($response->data->spend_points);
        // Dollar Amount Redeemable
        $this->SetProperty("DollarAmount", "$" . $response->data->spend_points_dollar_value);

        $this->http->GetURL('https://amazon.zappos.com/mobileapi/v1/customerInfo', $headers);
        $response = $this->http->JsonLog();
        $this->SetProperty("Name", beautifulName($response->customerInfo->name ?? null));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $link = $this->http->FindSingleNode("//div[contains(@id, 'captcha') or contains(@class, 'cvf-captcha-img')]/img/@src");
        $this->logger->debug("Download Image by URL");
        $recognizer = $this->getCaptchaRecognizer();

        return $this->recognizeCaptchaByURL($recognizer, $link, 'jpg');
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//a[contains(@href,'/logout')]")
            || $this->http->getCookieByName("holmes", "zappos.com", "/", true)
        ) {
            return true;
        }

        return false;
    }
}
