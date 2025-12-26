<?php

// refs #7202

use AwardWallet\Engine\ProxyList;

class TAccountCheckerMarketexplorer extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.worldmarket.com/account';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->setProxyGoProxies(null, 'uk');

        /*
        if ($this->attempt == 1) {
            $this->http->SetProxy($this->proxyReCaptcha());
        } else {
            $this->setProxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA);
        }
        */
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // js
        $this->logger->debug("strlen pass: " . strlen($this->AccountFields['Pass']));

        if (strlen($this->AccountFields['Pass']) <= 6) {
            throw new CheckException("Password cannot be less than 6 characters.", ACCOUNT_INVALID_PASSWORD);
        }

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address with "@" and "."', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://www.worldmarket.com/");

        if (!$this->http->ParseForm(null, '//form[@name="login-form" or @name="dwfrm_coRegisteredCustomer"]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("dwfrm_coRegisteredCustomer_email", $this->AccountFields['Login']);
        $this->http->SetInputValue("dwfrm_coRegisteredCustomer_password", $this->AccountFields['Pass']);
        $this->http->SetInputValue('wishlistProductId', '');
        $this->http->SetInputValue('captchaAction', 'Login');

        $captcha = $this->parseCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('gRecaptchaResponse', $captcha);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $success = $response->success ?? null;
        // Access is allowed
        if ($success === true && $this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        $message =
            $response->error[0]
            ?? $response->fields->dwfrm_coRegisteredCustomer_password
            ?? null
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Login unsuccessful. Please try to login again.') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG);
            }

            if (
                strstr($message, 'Your password has expired,')
                || $message == 'Sorry, but the information entered does not match our records. Please try again.'
                || $message == 'This field needs 8 to 255 characters'
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException(strip_tags($message), ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Access to this page has been denied because we believe you are using automation tools to browse the website.")]')) {
            throw new CheckRetryNeededException(2, 0);
        }

        if ($message = $this->http->FindSingleNode('//h4[contains(@class, "error-message")]/text()')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "For technical reasons, your request could not be handled properly at this time. We apologize for any inconvenience")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//p[span[contains(text(), "Member Since:")]]/preceding-sibling::p')));
        // Member ID
        $this->SetProperty("MemberId", $this->http->FindSingleNode('//p[span[contains(text(), "Member Since:")]]/text()[last()]', null, true, "/\|\s*(.+)/"));
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode('//p[span[contains(text(), "Member Since:")]]/text()[last()]', null, true, "/([^\|]+)/"));
        // Points to your next reward
        $this->SetProperty("PointsUntilReward", $this->http->FindSingleNode('//div[@id = "maincontent"]//div[span and contains(., "to your next reward")]/span'));
        // Balance - Current Points
        $this->SetBalance($this->http->FindSingleNode('//div[@id = "maincontent"]//div[contains(@class, "reward-bar-fill")]/div'));
        // Your Rewards and Coupons
        $nodes = $this->http->XPath->query('//div[@id = "maincontent"]//div[contains(@class, "row available-rewards-card")]/div');
        $this->logger->debug("Total coupons found: " . $nodes->length);
        // Rewards Available
        $this->SetProperty("RewardsAvailable", $nodes->length);
        $this->SetProperty("CombineSubAccounts", false);

        for ($i = 0; $i < $nodes->length; $i++) {
            $displayName = $this->http->FindSingleNode('.//p[contains(@class, "reward-title")]', $nodes->item($i));
            $exp = $this->http->FindSingleNode('.//p[contains(@class, "reward-expiration")]', $nodes->item($i));

            $this->AddSubAccount([
                'Code'           => 'marketexplorerCoupon' . $i,
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => strtotime($exp),
            ]);
        }// for ($i = 0; $i < $nodes->length; $i++)
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//input[@id = "recaptchaSiteKey"]/@value');

        if (!$key) {
            return false;
        }

        $postData = [
            "type"        => "RecaptchaV3TaskProxyless",
            "websiteURL"  => $this->http->currentUrl(),
            "websiteKey"  => $key,
            "isInvisible" => true,
            "pageAction"  => "Login",
            "minScore"    => 0.7,
            "proxy"       => $this->http->GetProxy(),
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            //            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "invisible" => 1,
            //            "action"    => "Login",
            "min_score" => 0.7,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL(self:: REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode('//a[contains(@href, "Login-Logout")]')) {
            return true;
        }

        return false;
    }

    private function getCookiesFromSelenium($retry = false)
    {
        $this->logger->notice(__METHOD__);

        $cache = Cache::getInstance();
        $cacheKey = "sensor_data_walgreens";
        $result = Cache::getInstance()->get($cacheKey);

        if (!empty($result) && $this->attempt == 0) {
            $this->logger->debug("set _abck from cache: {$result}");

            $this->http->setCookie("_abck", $result, ".walgreens.com");

            return null;
        }

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL("https://www.worldmarket.com/login");
            sleep(3);
            $login = $selenium->waitForElement(WebDriverBy::id('login-form-email'), 5);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (TimeOutException | SessionNotCreatedException | UnexpectedAlertOpenException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "Exception";
            // retries
            $retry = true;
        }// catch (TimeOutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return 1000;
    }
}
