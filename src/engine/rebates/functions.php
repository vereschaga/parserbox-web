<?php

// refs #1771

use AwardWallet\Engine\ProxyList;

class TAccountCheckerRebates extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.mrrebates.com/account/account_summary.asp';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

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

        if ($this->http->FindSingleNode('//div[contains(@class, "top-bar")]/a[@href="/logout.asp"]')) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        return $this->selenium();
        $this->http->GetURL("https://www.mrrebates.com/account/account_summary.asp");

        $this->http->setCookie('CookieScriptConsent', '{"action":"accept"}');

        if (!$this->http->ParseForm("theForm")) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://www.mrrebates.com/login.asp';
        $this->http->SetInputValue("t_email_address", $this->AccountFields['Login']);
        $this->http->SetInputValue("t_password", $this->AccountFields['Pass']);

        $captcha = $this->parseCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('g-recaptcha-response', "$captcha");
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg("/An internal system error has occurred/ims")) {
            throw new CheckException("Site is temporarily down. Please try to access it later.", ACCOUNT_PROVIDER_ERROR);
        }
        // Mr. Rebates is Temporarily Unavailable due to Technology Upgrades
        if ($message = $this->http->FindPreg("/(Mr\. Rebates is\s*Temporarily Unavailable due to Technology Upgrades)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Database Maintenance
        if ($message = $this->http->FindSingleNode("
            //font[contains(text(), 'Database Maintenace')]
            | //p[contains(., 'Temporarily Unavailable due to Technology Upgrades')]
        ")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# under construction
        if ($message = $this->http->FindSingleNode("//div[@id='under-construction']")) {
            throw new CheckException("Please pardon the dust. We're working hard to bring you an even better shopping experience. We should be back up shortly. Thank you for your patience.", ACCOUNT_PROVIDER_ERROR);
        }
        //# 502 Bad Gateway
        if ($this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")
            //# Service Temporarily Unavailable
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        return true;
        $headers = [
            "Accept"          => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
            "User-Agent"      => HttpBrowser::PROXY_USER_AGENT,
            "Accept-Encoding" => "gzip, deflate, br",
        ];

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }

        if ($this->http->FindPreg("/You are logged in as/")) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($this->http->FindNodes('//a[@href = "/logout.asp"]')) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($message = $this->http->FindPreg("/Forgot Password?/ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("The Email Address or Password is incorrect.", ACCOUNT_INVALID_PASSWORD);
        }
        // Account Locked
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'Account Locked')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'Account Disabled')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Please note that the Google Recaptcha system has identified this device as a possible \"bot\" or some type of other technical issue.')]")) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(2, 1);
        }

        if ($message = $this->http->FindSingleNode('//p[font[contains(text(), "Whoops!") and contains(., "An issue has occurred upon login.")]]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->RetryCount = 1;
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 40);
        }
        //# Account Disabled
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'Account Disabled')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Account Locked
        if (($message = $this->http->FindSingleNode("//font[contains(text(), 'Account Locked')]"))
            && strstr($this->http->currentUrl(), 'errors/account_locked.asp')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // retry
        if (
            $this->http->Response['code'] == 400
            || $this->http->Response['code'] == 0
            || strstr($this->http->currentUrl(), 'https://www.mrrebates.com/login.asp?url=/account/account_summary.asp')
        ) {
            throw new CheckRetryNeededException(3, 7);
        }

        // Pending Rebates
        $pending = str_replace(',', '', $this->http->FindSingleNode('//a[@data-open = "PendingCashBack"]/following-sibling::h6[1]', null, true, self::BALANCE_REGEXP_EXTENDED));
        $this->AddSubAccount([
            "Code"              => "rebatesPending",
            "DisplayName"       => "Pending",
            "Balance"           => $pending,
            "BalanceInTotalSum" => true,
        ]);
        // Available Rebates
        $available = str_replace(',', '', $this->http->FindSingleNode('//a[@data-open = "AvailableCashBack"]/following-sibling::h6[1]', null, true, self::BALANCE_REGEXP_EXTENDED));
        $this->AddSubAccount([
            "Code"              => "rebatesAvailable",
            "DisplayName"       => "Available",
            "Balance"           => $available,
            "BalanceInTotalSum" => true,
        ]);

        // Balance - Pending Rebates + Available Rebates
        if (isset($pending, $available)) {
            $this->SetBalance($pending + $available);
        }

        $this->http->GetURL('http://www.mrrebates.com/account/lifetime_balance.asp');
        // # of Rebates
        $this->SetProperty('NumRebates', $this->http->FindSingleNode('//div[contains(text(), "Cash Back Rebates:")]/following-sibling::div[1]'));
        // Referral Rebates
        $this->SetProperty('ReferralRebates', $this->http->FindSingleNode('//div[contains(text(), "Referral Cash Back:")]/following-sibling::div[1]'));
        // # of Referral Rebates
        $this->SetProperty('NumReferralRebates', $this->http->FindSingleNode('//div[contains(text(), "# of Referral Rebates:")]/following-sibling::div[1]'));
        // Payment Deductions
        // $this->SetProperty('PaymentDeductions', $this->http->FindSingleNode('//div[normalize-space(text()) = "Payments:"]/following-sibling::div[1]'));
        // # of Payment Deductions
        // $this->SetProperty('NumPaymentDeductions', $this->http->FindSingleNode('//div[contains(text(), "# of Payments:")]/following-sibling::div[1]'));

        $this->http->getURL('https://www.mrrebates.com/account/change_account.asp');
        $firstname = $this->http->FindSingleNode('//input[@name="t_first_name"]/@value');
        $lastname = $this->http->FindSingleNode('//input[@name="t_last_name"]/@value');
        $this->SetProperty("Name", beautifulName($firstname . ' ' . $lastname));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@name = 'theForm']//div[contains(@class, 'g-recaptcha')]/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

//        $postData = [
//            "type"         => "RecaptchaV2TaskProxyless",
//            "websiteURL"   => 'https://www.mrrebates.com/login.asp?url=/account/account_summary.asp',
//            "websiteKey"   => $key,
//            "isInvisible" => true
//        ];
//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
//        $this->recognizer->RecognizeTimeout = 120;
//
//        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => 'https://www.mrrebates.com/login.asp?url=/account/account_summary.asp',
            "proxy"     => $this->http->GetProxy(),
            //            "invisible" => 1,
            //"version"   => "v3",
            //"action"    => "submit",
            // "min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
//            $selenium->useChromium();
            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->disableImages();
            $selenium->http->SetProxy($this->proxyDOP());

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.mrrebates.com/account/account_summary.asp");
            } catch (Facebook\WebDriver\Exception\TimeoutException | TimeOutException | ScriptTimeoutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
                $this->savePageToLogs($selenium);
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 't_email_address']"), 7);
            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 't_password']"), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Log In')]"), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$login || !$pass || !$btn) {
                $this->logger->error("something went wrong");

                return $this->checkErrors();
            }

            $this->logger->notice("set login");
            $login->sendKeys($this->AccountFields['Login']);
            $this->logger->notice("set pass");
            $pass->sendKeys($this->AccountFields['Pass']);

            $captcha = $this->parseCaptcha();

            if ($captcha !== false) {
                $this->logger->notice("Remove iframe");
                $selenium->driver->executeScript("$('div.g-recaptcha iframe').remove();");
                $selenium->driver->executeScript("$('#g-recaptcha-response').val(\"" . $captcha . "\");");
            }

            $this->logger->notice("btn click");
            $btn->click();
            $err = $selenium->waitForElement(WebDriverBy::xpath("//font[contains(text(), 'The Email Address or Password is incorrect.')]"), 6);
            $this->savePageToLogs($selenium);

            if ($err) {
                throw new CheckException($err->getText(), ACCOUNT_INVALID_PASSWORD);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        }// catch (ScriptTimeoutException $e)
        catch (UnknownServerException|SessionNotCreatedException| Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if (isset($retry) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(5, 0);
            }
        }

        return true;
    }
}
