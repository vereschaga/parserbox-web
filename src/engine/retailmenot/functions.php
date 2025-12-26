<?php

class TAccountCheckerRetailmenot extends TAccountChecker
{
    use AwardWallet\Engine\ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://secure.retailmenot.com/my-rewards';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $selenium = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if ($this->attempt > 0) {
            $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
        } else {
            $this->http->SetProxy($this->proxyReCaptcha(), false);
            //$this->setProxyBrightData();
        }

        //$this->http->setHttp2(true);
//        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->disableOriginHeader(); // prevent error code: 1020
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
        // Login must be correct email
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Email and/or password is incorrect. If you need to verify your account, please check your email.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        //$this->http->GetURL('https://secure.retailmenot.com/accounts/login');

        //if ($this->http->Response['code'] == 403) {
        // if ($this->attempt == 0) {
        $this->selenium = true;
        $this->seleniumAuth();

        return true;
        // }

        $this->markProxyAsInvalid();

        throw new CheckRetryNeededException(3, 1);
        //}

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            if ($this->http->FindPreg("#(?:cpo.src = \"/cdn-cgi/challenge-platform/\w/\w/orchestrate/([^/]+)/v1\"|cType:\s*\"interactive\")#s")) {
                throw new CheckRetryNeededException(2, 1);
            }

            return false;
        }
        $data = [
            'identifier'             => $this->AccountFields['Login'],
            'password'               => $this->AccountFields['Pass'],
            'passwordHash'           => hash('sha256', $this->AccountFields['Pass']),
            'passwordMeetsMinLength' => true,
        ];
        $headers = [
            'Accept'            => '*/*',
            'Content-Type'      => 'application/json',
            'x-recaptcha-token' => $captcha,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://secure.retailmenot.com/accounts/api/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $result = $this->http->JsonLog(null, 3);
        $success = $result->success ?? null;

        if (!$success && $this->selenium === false) {
            if ($this->http->FindPreg("#(?:cpo.src = \"/cdn-cgi/challenge-platform/\w/\w/orchestrate/([^/]+)/v1\"|cType: \"non-interactive\",)#s")) {
                throw new CheckRetryNeededException(2, 1);
            }

            if (strstr($this->http->Error, 'Network error 28 - Operation timed out after ')) {
                throw new CheckRetryNeededException(3, 1);
            }

            if ($success === false && $result->error->message == 'Unauthorized') {
                throw new CheckRetryNeededException(2, 10, 'Email and/or password is incorrect. If you need to verify your account, please check your email.', ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($success === true || $this->http->FindSingleNode('//a[contains(text(), "Log Out")]')) {
            $this->captchaReporting($this->recognizer);
//            $this->http->GetURL(self::REWARDS_PAGE_URL);

            return true;
        }

        if ($message = $this->http->FindSingleNode('//li[contains(@class, "is-active")] | //p[span[contains(@class, "anchor-icon")]]/text()[last()]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "Email and/or password is incorrect.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "An unexpected error occurred. Please try again later.")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // AccountID: 5901273
        if (strlen($this->AccountFields['Pass']) == 7) {
            throw new CheckException("Email and/or password is incorrect.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $headers = [
            "Accept"       => "*/*",
            "Content-type" => "application/json",
            "Origin"       => "https://www.retailmenot.com",
            "Referer"      => "https://www.retailmenot.com/",
        ];

//        $this->http->GetURL("https://secure.retailmenot.com/my-rewards", [
//            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9'
//        ]);
        $response = $this->http->JsonLog($this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json">(.+?)</script>#'), 2);

        if (!$response) {
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "Before continuing, please update your password to meet our new security standards.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }

        $sent = $response->props->apolloState->{'$ROOT_QUERY.member.rewards.cashBackTotals'}->sent ?? null;
        // Your lifetime rewards
        $this->SetProperty('LifetimeRewards', $sent);

        // Approved Cash Back Rewards
//        $this->http->GetURL("https://secure.retailmenot.com/my-rewards");
        // pending
        $pending = $this->http->FindSingleNode('//div[contains(text(), "Pending Rewards")]/preceding-sibling::div[contains(text(), "$")]', null, true, "/\\$(.+)/")
            // https://secure.retailmenot.com/my-account
            ?? $this->http->FindSingleNode('//div[contains(text(), "Pending Rewards")]/following-sibling::div[contains(text(), "$")]', null, true, "/\\$(.+)/");
        $approved = $this->http->FindSingleNode('//div[contains(text(), "Approved Rewards")]/preceding-sibling::div[contains(text(), "$")]', null, true, "/\\$(.+)/")
            // https://secure.retailmenot.com/my-account
            ?? $this->http->FindSingleNode('//div[contains(text(), "Approved Rewards")]/following-sibling::div[contains(text(), "$")]', null, true, "/\\$(.+)/");

        if (!isset($approved) || !isset($pending)) {
            // AccountID: 7082003
            if (!is_null($approved)) {
                // Balance
                $this->SetBalance($approved);
                // Name
                $firstName = $response->props->apolloState->{'$ROOT_QUERY.member.profile'}->firstName ?? '';
                $lastName = $response->props->apolloState->{'$ROOT_QUERY.member.profile'}->lastName ?? '';
                $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));
            }

            return;
        }

        $this->AddSubAccount([
            "Code"              => "retailmenotCashBackRewards",
            "DisplayName"       => "Approved",
            "Balance"           => $approved,
            "BalanceInTotalSum" => true,
        ]);
        $this->AddSubAccount([
            "Code"              => "retailmenotPending",
            "DisplayName"       => "Pending",
            "Balance"           => $pending,
            "BalanceInTotalSum" => true,
        ]);
        // Balance
        $this->SetBalance(str_replace(',', '', $approved) + str_replace(',', '', $pending));
        // Name
//        $this->http->GetURL('https://secure.retailmenot.com/my-profile');
        $firstName = $response->props->apolloState->{'$ROOT_QUERY.member.profile'}->firstName ?? '';
        $lastName = $response->props->apolloState->{'$ROOT_QUERY.member.profile'}->lastName ?? '';
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//div[contains(text(), 'My Rewards')]")) {
            return true;
        }

        return false;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg('/"siteKey":"([^\"]+)/');

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => 'https://secure.retailmenot.com/accounts/login',
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function seleniumAuth()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
//            $selenium->useGoogleChrome();

            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
            $selenium->http->setUserAgent($this->http->userAgent);

            //$selenium->setKeepProfile(true);
            //$selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
//            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();

            try {
                $selenium->http->GetURL("https://secure.retailmenot.com/accounts/login");
            } catch (NoSuchWindowException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
                // save page to logs
                $this->savePageToLogs($selenium);
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "identifier" or @id = "email"]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput) {
                return false;
            }

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->duration = rand(100000, 120000);
            $mover->steps = rand(50, 70);

            try {
                $mover->moveToElement($loginInput);
                $mover->click();
            } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException $e) {
                $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
            }

            $loginInput->click();
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);

            try {
                $mover->moveToElement($passwordInput);
                $mover->click();
            } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException $e) {
                $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
            }

            $passwordInput->click();
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 10);

            /*
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            */
            $this->savePageToLogs($selenium);

            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Log In")]'), 5);

            if (!$button) {
                return false;
            }

            $captcha = $this->parseReCaptcha();

            if ($captcha !== false) {
                $this->logger->debug("set captcha g-recaptcha-response");
                $selenium->driver->executeScript("document.querySelector('[name = \"g-recaptcha-response\"]').value = '{$captcha}';");
                sleep(1);
            }

            $this->logger->debug("click by btn");

            try {
                $mover->moveToElement($button);
                $mover->click();
            } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException $e) {
                $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
            }
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('//li[contains(@class, "is-active")] | //button[contains(@class, "drop-down-button")] | //p[span[contains(@class, "anchor-icon")]]'), 10);
            $this->savePageToLogs($selenium);

            if (!$selenium->waitForElement(WebDriverBy::xpath('//li[contains(@class, "is-active")] | //p[span[contains(@class, "anchor-icon")]]'), 0)) {
                $selenium->http->GetURL('https://secure.retailmenot.com/my-rewards');
                $selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'My Rewards')]"), 5);

                // AccountID: 7082003
                if ($selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'An error 500 occurred on server')]"), 0)) {
                    $selenium->http->GetURL('https://secure.retailmenot.com/my-account');
                    $selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'My Rewards')]"), 5);
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (NoSuchDriverException | UnknownServerException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
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
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }
}
