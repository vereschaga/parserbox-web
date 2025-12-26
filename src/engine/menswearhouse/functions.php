<?php

class TAccountCheckerMenswearhouse extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private $selenium = false;
    private array $headers;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setUserAgent("AwardWallet Service. Contact us at awardwallet.com/contact"); // "Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)" workaroud
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    private function setHeaders() {
        $this->State['access_token'] = $this->State['access_token'] ?? urldecode($this->http->getCookieByName('access_token'));
        $this->State['guest_user_id'] = $this->State['guest_user_id'] ?? $this->http->getCookieByName('guest_user_id');
        $this->State['WC_PERSISTENT'] = $this->State['WC_PERSISTENT'] ?? $this->http->getCookieByName('WC_PERSISTENT');
        $this->http->setDefaultHeader('Referer', 'https://www.menswearhouse.com/');
        $this->http->setDefaultHeader('Origin', 'https://www.menswearhouse.com');
        $this->headers = [
            'Accept'              => 'application/json, text/plain, */*',
            'access_token'        => $this->State['access_token'],
            'Content-Type'        => 'application/json',
            'domainorigin'        => 'TMW',
            'guest_user_id'       => $this->State['guest_user_id'],
            'isloggedin'          => '',
            'login_id'            => $this->AccountFields['Login'],
            'store_id'            => '12751',
            'uniquesessioncookie' => '',
            'wc_persistent_token' => $this->State['WC_PERSISTENT'],
        ];
    }

    public function LoadLoginForm()
    {
        unset($this->State);
        $this->http->removeCookies();
        if (!$this->selenium()) {
            return false;
        }
        $this->setHeaders();
        $captcha = $this->parseReCaptcha('6Lf2OGcaAAAAAFnLbQzeFINYx8jQBofyMBYmyMXE');

        $data = [
            "body" => [
                "g-recaptcha-response" => $captcha,
            ],
            "user_id" => $this->AccountFields['Login'],
            "password" => $this->encrypt(),
            "rememberMe" => true,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.menswearhouse.com/api/v2/secure/login/user", json_encode($data),
            $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    private function encrypt( )
    {
        $this->logger->notice(__METHOD__);
        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
        $encrypted = $jsExecutor->executeString("
        let encryptedMessage = CryptoJS.AES.encrypt(
            '".str_replace("'", "\'", $this->AccountFields['Pass'])."',
            'TB122021',
            {
                mode: CryptoJS.mode.CBC,
                padding: CryptoJS.pad.Pkcs7
            }
        );
        sendResponseToPhp(encryptedMessage.toString());
        ", 5, ['https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.9-1/crypto-js.min.js']);

        return $encrypted;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.menswearhouse.com/webapp/wcs/stores/servlet/AjaxLogonForm?storeId=12751&catalogId=12004&langId=-1";

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg("/An error occurred while processing your request\./")
            || $this->http->Response['code'] == 503) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // maintenance
        if ($this->http->FindSingleNode("//img[@src = 'http://images.menswearhouse.com/is/image/TMW/31412_sitedown_withtux2?scl=1']/@src")) {
            throw new CheckException("Our site is currently undergoing alterations. Thank you for your patience. Please visit us again soon.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        if (isset($response->status, $response->data->access_token) && $response->status == 'success') {
            $this->State['access_token'] = $response->data->access_token;

            return $this->loginSuccessful();
        }
        if ($this->http->FindPreg('/"error_message":"Email address or password is incorrect"/')) {
            throw new CheckException("Email id and password did not match. Please check and try again.",
                ACCOUNT_INVALID_PASSWORD);
        }

        return false;


        if ($this->selenium == false && !$this->http->PostForm() && $this->http->Response['code'] != 404) {
            return $this->checkErrors();
        }

        if (
            ($redirecturl = $this->http->FindPreg("/\"redirecturl\": \"([^<\"]+)/"))
            && $redirecturl != 'LogonForm'
        ) {
            $this->http->GetURL($redirecturl);
        }

        if (
            $this->http->FindSingleNode("//div[@class='error-msg' and contains(text(),'Please verify Captcha to continue')]")
            || $this->http->FindSingleNode("//textarea[@id='g-recaptcha-response']/@id")
        ) {
            if (!$this->http->ParseForm("logonForm2")) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue("logonId", $this->AccountFields['Login']);
            $this->http->SetInputValue("logonPassword", $this->AccountFields['Pass']);

            $captcha = $this->parseCaptcha();

            if ($captcha !== false) {
                $this->http->SetInputValue('g-recaptcha-response', $captcha);
            }

            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Error Page Exception')]")
            || stristr($this->http->currentUrl(), 'https://www.menswearhouse.com/OrderItemMove?langId=-1&catalogId=12004&storeId=12751')) {
            $this->http->GetURL("https://www.menswearhouse.com/RewardsView?catalogId=12004&langId=-1&storeId=12751");
        }

        if ($this->loginSuccessful()) {
            if ($this->selenium == true) {
                return false;
            }

            return true;
        }
        // Log in failed. Please Try Again.
        if ($message = $this->http->FindSingleNode("//form[@id = 'logonForm2']//div[contains(@class, 'error-msg')]")) {
            $this->logger->error($message);

            if (strstr($message, 'Log in failed. Please Try Again.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // The reset password URL has expired. Please request password reset one more time.
            if (strstr($message, 'The reset password URL has expired. Please request password reset one more time.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            // captcha issues
            if (strstr($message, 'Please verify Captcha to continue')) {
                throw new CheckRetryNeededException(3, 1);
            }
        }// if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error-msg')]"))

        if ($errorCode = $this->http->FindPreg("/\"ErrorCode\":\s*\"([^\"]+)/")) {
            $this->logger->error("[ErrorCode]: {$errorCode}");

            if ($errorCode == '2030') {
                throw new CheckException("Log in failed. Please Try Again.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($errorCode == 'RESET_PASSWORD_URL_EXPIRED') {
                throw new CheckException("The reset password URL has expired. Please request password reset one more time.", ACCOUNT_INVALID_PASSWORD);
            }

            // captcha issues
            if ($errorCode == 'RECAPCHA_NOT_VERIFIED') {
                throw new CheckRetryNeededException(3, 1);
            }

            $this->DebugInfo = "[ErrorCode]: {$errorCode}";

            return false;
        }

        // Join Perfect Fit Rewards
        if ($this->http->FindSingleNode("//h3[contains(text(), 'Join Perfect Fit Rewards')]")
            && $this->http->FindSingleNode("//a[contains(text(), 'Join now for free')]")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance - Point Balance
        $this->SetBalance($response->data->pf_points);
        // Perfect Fit ID
        $this->SetProperty("Account", $response->data->rewardsId);
        // You are 72 points away from your next $50 Perfect Fit Rewards Certificate!
        $this->SetProperty("PointsToCertificate", $response->data->remaining_points);
        // Name
        $this->SetProperty("Name", beautifulName($response->data->name));
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => "1",
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->setHeaders();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.menswearhouse.com/api/v1/secure/users/pfrdetails", $this->headers, 20);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        if (isset($response->status) && $response->status == 'success') {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_100);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.menswearhouse.com/myAccount#fitReward');
            $login = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@id, 'sign-in')]//input[@name = 'email']"), 7);
            $pass = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@id, 'sign-in')]//input[@name = 'password']"), 0);
            // save page to logs
            $this->saveToLogs($selenium);

            if (!$login || !$pass) {
                $this->logger->error("something went wrong");

                return false;
            }

            $cookies = $selenium->driver->manage()->getCookies();
            $this->saveToLogs($selenium);

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";
            // retries
            if (strstr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (TimeOutException $e)
        finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 5);
            }
        }

        return true;
    }

    private function saveToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
