<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerGolfnow extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public const REWARDS_PAGE_URL = "https://www.golfnow.com/my/profile#tabRewards";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $client_id = null;
    private $state = null;
    private $scope = null;

//    public static function GetAccountChecker($accountInfo)
//    {
//        require_once __DIR__."/TAccountCheckerGolfnowSelenium.php";
//        return new TAccountCheckerGolfnowSelenium();
//    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], "golfnowReward")) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["SuccessURL"] = "https://www.golfnow.com/long-island/my-account/frequent-golfer";

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
//        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.golfnow.com/customer/login");

        if ($this->http->FindPreg('/window\["bobcmn"\]\s*=/')) {
            $this->selenium();
        }

        // new auth
        if ($iframe = $this->http->FindPreg("/iframeRawUrl:\s*'([^\']+)/")) {
            $this->http->GetURL($iframe);

            $this->client_id = $this->http->FindPreg("/client_id=([^&]+)/", false, $iframe);
            $this->state = $this->http->FindPreg("/state=([^&]+)/", false, $iframe);
            $this->scope = $this->http->FindPreg("/scope=([^&]+)/", false, $iframe);
            $gidRecaptchaKe = $this->http->FindPreg("/gidRecaptchaKey:\s*'([^\']+)/");

            if (!$this->client_id || !$this->state || !$this->scope) {
                return $this->checkErrors();
            }

            $captcha = $this->parseCaptcha($gidRecaptchaKe);

            if ($captcha === false) {
                return false;
            }

            $data = [
                "username"   => $this->AccountFields["Login"],
                "recaptcha"  => $captcha,
                "password"   => $this->AccountFields["Pass"],
                "parameters" => [
                    "response_type" => "code",
                    "client_id"     => $this->client_id,
                    "redirect_uri"  => "https://www.golfnow.com/golfid/oauth-callback",
                    "scope"         => $this->scope,
                    "state"         => $this->state,
                ],
            ];
            $headers = [
                "content-type" => "application/json",
                "accept"       => "*/*",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://api.golfid.io/api/v1/sso/oauth/user/authenticate?clientId={$this->client_id}", json_encode($data), $headers);
            $this->http->RetryCount = 2;

            return true;
        }

        if (!$this->http->ParseForm("fmlogin")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("UserName", $this->AccountFields["Login"]);
        $this->http->SetInputValue("Password", $this->AccountFields["Pass"]);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# We are currently undergoing system maintenance
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'We are currently undergoing system maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The page you requested is in the water hazard.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'The page you requested is in the water hazard.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->client_id) {
            $response = $this->http->JsonLog();
            $token = $response->token ?? null;

            if (!$token) {
                // Invalid credentials
                if ($this->http->Response['body'] == '{"statusCode":401,"error":"Unauthorized","message":"Unauthorized"}') {
                    throw new CheckException("We couldn't find a user with that address, or the password didn't match. Please check your credentials and try again.", ACCOUNT_INVALID_PASSWORD);
                }

                if ($this->http->Response['body'] == '{"statusCode":423,"error":"Locked","message":"Locked"}') {
                    throw new CheckException("This account has been temporary locked for too many failed login attempts. Please try again in a while, or contact customer support for immediate assistance.", ACCOUNT_LOCKOUT);
                }

                return false;
            }
            $headers = [
                "authorization" => $token,
            ];
            $this->http->GetURL("https://api.golfid.io/api/v1/sso/oauth/application/token?response_type=code&client_id={$this->client_id}&scope={$this->scope}&state={$this->state}&redirect_uri=https://www.golfnow.com/golfid/oauth-callback",
                $headers);
            $response = $this->http->JsonLog();
            $code = $response->code ?? null;

            if (!$code) {
                return false;
            }
            $this->http->GetURL("https://www.golfnow.com/golfid/oauth-callback?code={$code}&state={$this->state}");

            if ($this->http->FindPreg('/authSuccessCheckEmailOptin/')) {
                $this->selenium("https://www.golfnow.com/golfid/oauth-callback?code={$code}&state={$this->state}");

                $firstName = $this->http->FindPreg("/\"firstName\":\"([^\"]+)\",\"lastName\":\"[^\"]+\",\"status\":/");
                $lastName = $this->http->FindPreg("/\",\"lastName\":\"([^\"]+)\",\"status\":/");
                $this->SetProperty("Name", beautifulName("$firstName $lastName"));

                return $this->loginSuccessful();
            }

            if ($this->http->FindPreg("/auth success/")) {
                $this->captchaReporting($this->recognizer);

                if ($this->loginSuccessful()) {
                    return true;
                }

                // AccountID: 4789726
                if (
                    $this->AccountFields["Login"] == 'jwa1780@gmail.com'
                    && $this->http->FindPreg("/\{\"message\":\"Authorization has been denied for this request.\"\}/")
                ) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            if ($this->http->FindPreg("/auth failed/")) {
                throw new CheckRetryNeededException(2, 10, "There was an issue logging into your account.");
            }

            return $this->checkErrors();
        }

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // login successful
        if ($this->http->FindSingleNode("//a[contains(text(), 'Log out')]")) {
            return $this->loginSuccessful();
        }
        /*
        if ($this->loginSuccessful()) {
            return true;
        }
        */
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("
                //small[contains(text(), 'The credentials provided are incorrect.')]
                | //small[contains(text(), 'The Email address field is not a valid e-mail address.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The account associated with this email address has been temporarily locked.
        if ($message = $this->http->FindSingleNode("//small[contains(text(), 'The account associated with this email address has been temporarily locked.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance - Points Earned
        $this->SetBalance($response->rewardsPoints ?? null);
        // Points Until Next Reward
        $this->SetProperty("PointsUntilNextReward", $response->pointsToNextReward ?? null);
        // Total Reservations
        $this->SetProperty("RoundsBooked", $response->roundsBooked ?? null);
        // Rewards Available
        $this->SetProperty("RewardsAvailable", $response->rewardsAvailable ?? null);
        // Lifetime Points Earned
        $this->SetProperty("LifetimePointsEarned", $response->lifetimePoints ?? null);

        // Rewards
        $rewards = $response->rewards ?? [];
        $this->logger->debug("Total " . count($rewards) . " available rewards were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($rewards as $reward) {
            $wasUsed = $reward->wasUsed ?? null;
            // Promo Code
            $code = $reward->rewardCode ?? null;

            if ($wasUsed == true) {
                $this->logger->notice("skip used reward: {$code}");

                continue;
            }
            // Reward Earned
            $displayName = $reward->rewardName ?? null;
            // Expires On
            $exp = $reward->expirationDate ?? null;
            $exp = strtotime($exp);

            if (!$exp || $exp < time()) {
                $this->logger->notice("skip old reward: {$code} / " . ($reward->expirationDate ?? null));

                continue;
            }
            $this->AddSubAccount([
                'Code'           => 'golfnowReward' . $code,
                'DisplayName'    => $displayName,
                'Balance'        => $reward->value,
                // Promo Code
                'PromoCode'      => $code,
                'ExpirationDate' => $exp,
            ], true);
        }// foreach ($rewards as $reward)

        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // set Name
        $firstName = $this->http->FindPreg("/\"firstName\":\"([^\"]+)\",\"lastName\":\"[^\"]+\",\"status\":/");
        $lastName = $this->http->FindPreg("/\",\"lastName\":\"([^\"]+)\",\"status\":/");

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName("$firstName $lastName"));
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode("//div[contains(text(), 'You have not yet accrued any loyalty rewards')]")) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    protected function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.3,
            "pageAction"   => "submit",
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $this->logger->notice("currentUrl: " . $this->http->currentUrl());
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
            "version"   => "v3",
            "action"    => "submit",
            "min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "X-Requested-With" => "XMLHttpRequest",
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Accept-Encoding"  => "gzip, deflate, br",
            "Referer"          => "https://www.golfnow.com/my/profile",
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.golfnow.com/api/account/rewards-profile", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->rewardsPoints) && !is_null($response->rewardsPoints)) {
            return true;
        }
        /*
        if ($this->http->FindSingleNode("//a[contains(text(), 'Log out')]")) {
            return true;
        }
        */

        return false;
    }

    private function selenium($url = "https://www.golfnow.com/customer/login")
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->useChromium(SeleniumFinderRequest::CHROMIUM_80);
            $selenium->http->setUserAgent($this->http->userAgent);
            $selenium->disableImages();
            $selenium->useCache();

            $selenium->http->start();
            $selenium->Start();

            if ($url == self::REWARDS_PAGE_URL) {
                $selenium->http->GetURL('https://www.golfnow.com/dsfsf');
                // get cookies from curl
                $allCookies = array_merge($this->http->GetCookies(".golfnow.com"), $this->http->GetCookies(".golfnow.com", "/", true));
                $allCookies = array_merge($allCookies, $this->http->GetCookies("www.golfnow.com"), $this->http->GetCookies("www.golfnow.com", "/", true));

                foreach ($allCookies as $key => $value) {
                    $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".hyatt.com"]);
                }
            }

            $selenium->http->GetURL($url);

            if (strstr($url, 'https://www.golfnow.com/golfid/oauth-callback?code')) {
                $selenium->http->GetURL(self::REWARDS_PAGE_URL);
            }

            $login = $selenium->waitForElement(WebDriverBy::id('username'), 3); // save page to logs
//            $pass = $selenium->waitForElement(WebDriverBy::id('password'), 10);// save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup();
        }

        return $result;
    }
}
