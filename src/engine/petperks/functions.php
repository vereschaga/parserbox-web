<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPetperks extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.petsmart.com/treats-rewards.html';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $selenium = false;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerPetperksSelenium.php";

        return new TAccountCheckerPetperksSelenium();
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'petperksRewards')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
//        $arg['CookieURL'] = 'https://petperks.petsmart.com/index.aspx';
        $arg['SuccessURL'] = 'https://www.petsmart.com/account/treats-offers/';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->setHttp2(true);

        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 1) {
            $this->http->setRandomUserAgent(7);
            $agent = $this->http->getDefaultHeader("User-Agent");

            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }
        } else {
            $this->http->setUserAgent($this->State[$userAgentKey]);
        }
        $this->selenium = true;

        if ($this->attempt == 1) {
//            $this->http->SetProxy($this->proxyReCaptchaIt7());
            $this->setProxyNetNut();
        } else {
            $this->setProxyBrightData();
        }
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
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
        // Please enter valid email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        if ($this->selenium == true) {
            $this->selenium();

            return true;
        }

        /*
        // prevent 403
        $this->http->setCookie("i10c.bdddb", "c2-f0103ZLNqAeI3BH6yYOfG7TZlRtCrMwzKDQfPMtvESnCuVjBtyWlLudjtWrMwuswxFolPHdkESNCtx04RiOfGqfIlRULts1qPnlkPolfJSiIRsomwzSdJqeKlRtT3jxvKEOjKMDfJSvUoxo6uXWaGVZkqoAhloxqQlofPwYkJwyCtxjCRxOfqqek06i3aQsvP8rINHdKESne3jgwkuTfBwCllRTCqoKAx3VfPMYlrVlIz4jBTuTfa3ZkqMupojxVKDq5iHdkETLKrsolozT11EQIRMtHlpVtKDQfPM2M9NnHoyM6yYOfGLo96oKCqoswx8qkOHdNINnHoxO6tzjzUqeklSRCqovqPqofPMYktNnHCD9TozTaHTZkqPoHTmsvP8vLKMDfJS63RYQmozTaHTdfq1oHqE7qPDllxHdkEVMGwys6tZSiLuZkQQxFvjxVOHroKMDjNWlCtXnF05OfqujkvMtrpt0xKDQjUSgfJ2mNs1jEUuTfBvEfqRLXloxqQlpfPwYkJtc9jojBtuUDEqeKlRtRZUXdKDqfQubfJ2iHtMy6tzOgotZpR", ".petsmart.com", "/", null, true);
        */

        /*
        $this->http->setCookie('_ALGOLIA', 'anonymous-41c470ff-e05b-41ad-a83f-c95cd90132a9', 'www.petsmart.com', '/', null, false);
        */

        /*$headers = [
            'Accept' => 'application/json, text/javascript, * / *; q=0.01',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $this->http->GetURL("https://www.petsmart.com/on/demandware.store/Sites-PetSmart-Site/default/AccountController-CheckLoginEmail?email={$this->AccountFields["Login"]}", $headers);
        $response = $this->http->JsonLog();*/

        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if (!$this->http->ParseForm("signInForm")) {
            if ($this->http->Response['code'] == 403) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3);
            }

            return $this->checkErrors();
        }

        $this->markProxySuccessful();

        $this->http->FormURL = 'https://www.petsmart.com/on/demandware.store/Sites-PetSmart-Site/default/AccountController-ProcessLogin';
        $this->http->SetInputValue('username', $this->AccountFields["Login"]);
        $this->http->SetInputValue('password', $this->AccountFields["Pass"]);
        $this->http->SetInputValue('redirectUrl', '/account/about-you/');
        $this->http->unsetInputValue('');

        if ($key = $this->http->FindPreg("/\"ENABLE_ENTERPRISE_RECAPTCHA\":true,\"ENTERPRISE_RECAPTCHA_KEY\":\"(6Le7ws4ZAAAAAKbfnETJrEWtmpRx9vU8lFFmi8Cb)\"/")) {
            $this->http->FormURL = 'https://www.petsmart.com/on/demandware.store/Sites-PetSmart-Site/default/AccountController-ProcessCaptchaLogin?redirectUrl=https://www.petsmart.com/account/about-you/';
            $key = "6Le7ws4ZAAAAAKbfnETJrEWtmpRx9vU8lFFmi8Cb";
//            $key = "6Lc8c1MaAAAAAI_EIxnsAY13LiMRKPw2Nh34BUSw";
            $captcha = $this->parseCaptcha($key);

            if ($captcha === false) {
                return false;
            }

            $this->http->SetInputValue('g-recaptcha-key', $key);
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->SetInputValue('hiddenrecaptcha', "");
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//h1[text()='Transmission Problems']")) {
            throw new CheckException('The request couldn\'t be processed correctly. Please try again soon.', ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "We\'re sorry for the inconvenience. We\'ll be up and running soon.")]
                | //p[contains(text(), "Our furry friends are working on upgrades.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->selenium == false) {
            if (!$this->http->PostForm(["X-Requested-With" => "XMLHttpRequest", "Accept" => "*/*", "Referer" => self::REWARDS_PAGE_URL])) {
                return $this->checkErrors();
            }
        }

        $response = $this->http->JsonLog();

        if (isset($response->success) && $response->success) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            return true;
        }

        if (!empty($response->redirectUrl)) {
            $redirectUrl = $response->redirectUrl;
            $this->http->NormalizeURL($redirectUrl);
            $this->http->GetURL($redirectUrl);
        }// if (!empty($response->redirectUrl))

        if ($this->loginSuccessful()) {
            return true;
        }

        $message =
            $response->error
            ?? $this->http->FindSingleNode('//div[@id = "account-login"]//div[contains(@class, "login-errors") and not(@style = "display:none;")]')
//            ?? $this->http->FindSingleNode('//span[contains(@class, "gtm-error-msg")]')
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");
            // Your email and/or password are incorrect. Please try again.
            if (
                $message == 'invalid_credentials'
                || strstr($message, 'Your email and/or password are incorrect. Please try again.')
            ) {
//                throw new CheckException('Your email and/or password are incorrect. Please try again.', ACCOUNT_INVALID_PASSWORD);

                throw new CheckRetryNeededException(2, 7, 'Your email and/or password are incorrect. Please try again.', ACCOUNT_INVALID_PASSWORD);
            }
            // An unknown error has occurred. Please try again later.
            if (
                $message == 'unknown_error'
                || strstr($message, 'An unknown error has occurred. Please try again later.')
            ) {
                throw new CheckRetryNeededException(2, 0, 'An unknown error has occurred. Please try again later.', ACCOUNT_PROVIDER_ERROR);

                throw new CheckException('An unknown error has occurred. Please try again later.', ACCOUNT_PROVIDER_ERROR);
            }
            // Service currently not available. Please try again later.
            if (
                $message == 'service_unavailable'
                || $message == 'Service currently not available. Please try again later.'
            ) {
                throw new CheckException('Service currently not available. Please try again later.', ACCOUNT_PROVIDER_ERROR);
            }
        }// if (isset($response->error))

        // block workaround
        if (isset($this->http->Response['code']) && $this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(2, 0);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//input[@id = "dwfrm_profile_customer_firstname"]/@value') . " " . $this->http->FindSingleNode('//input[@id = "dwfrm_profile_customer_lastname"]/@value')));

        $this->http->GetURL("https://www.petsmart.com/on/demandware.store/Sites-PetSmart-Site/default/LoyaltyController-GetLoyaltyMemberPointsBFF");
        $response = $this->http->JsonLog();
        // Balance - points
        $this->SetBalance($response->api->availablePoints ?? null);

        if (isset($response->api->availableDollars)) {
            $this->AddSubAccount([
                "Code"        => "petperksRewards",
                "DisplayName" => 'Rewards',
                "Balance"     => $response->api->availableDollars,
            ]);
        }
        // Status
        $this->SetProperty("Status", beautifulName($response->api->currentTierLevel ?? null));
        // NextLevel
        $this->SetProperty("NextLevel", beautifulName($response->api->nextTierLevel ?? null));
        // Spend until the next level - Spend $364 to become a Bestie!
        if (isset($response->api->dollarsToNextTier)) {
            $this->SetProperty("SpendUntilTheNextLevel", floor($response->api->dollarsToNextTier));
        }
        // Spent this year - You've spent $136 this year.**
        if (isset($response->api->currentTierDollarsSpent)) {
            $this->SetProperty("SpentThisYear", floor($response->api->currentTierDollarsSpent));
        }
        // pts. until next treat
        if (isset($response->api->pointsToNextTier)) {
            $this->SetProperty("UntilNextTreat", floor($response->api->pointsToNextTier));
        }

        // Total Pets
        $this->http->GetURL("https://www.petsmart.com/on/demandware.store/Sites-PetSmart-Site/default/Pet-CustomerPet?includeCheckoutPets=false");
        $response = $this->http->JsonLog(null, 0, true);
        $this->SetProperty("TotalPets", count(ArrayVal($response, 'petModelArray', [])));

        $this->http->GetURL('https://www.petsmart.com/account/treats-offers/');
        $expDates = array_map('strtotime', $this->http->FindNodes('//ul[@id = "expire-points-container"]/li[not(contains(@class, "heading"))]', null, '#(\d{1,2}/\d{1,2}/\d{4})#'));
        $expPoints = $this->http->FindNodes('//ul[@id = "expire-points-container"]//span');

        if (count($expDates) != count($expPoints)
            || count($expDates) == 0
        ) {
            return;
        }

        foreach (array_combine($expDates, $expPoints) as $time => $points) {
            if ($points > 0) {
                $this->SetExpirationDate($time);
                $this->SetProperty('ExpiringBalance', $points);

                break;
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//a[contains(@href, "Login-Logout")]/@href')) {
            return true;
        }

        return false;
    }

    private function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.9,
            "pageAction"   => "submit", //"login",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "version"   => "enterprise",
            "invisible" => 1,
            "action"    => "submit", //"login",
            "min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
//            $resolutions = [
//                [1152, 864],
//                [1280, 720],
//                [1280, 768],
//                [1280, 800],
//                [1360, 768],
//                [1366, 768],
//            ];
//            $resolution = $resolutions[array_rand($resolutions)];
//            $selenium->setScreenResolution($resolution);
            /*$selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumRequest->setOs("mac");
            $selenium->http->setUserAgent(null);*/
            //$selenium->useFirefox();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);

            $selenium->setKeepProfile(true);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            //$selenium->disableImages();

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();

            try {
                $selenium->http->GetURL("https://www.petsmart.com/account/about-you/");
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath("//form[@id = 'signInForm']//input[@name = 'username']"), 5, false);
            $pass = $selenium->waitForElement(WebDriverBy::xpath("//form[@id = 'signInForm']//input[@name = 'password']"), 0, false);
            $signInButton = $selenium->waitForElement(WebDriverBy::xpath("//form[@id = 'signInForm']//button[@id = 'login']"), 0, false);
            $this->savePageToLogs($selenium);

            if ($login && $pass && $signInButton) {
                $selenium->driver->executeScript("
                    document.forms['signInFormModal'].name = 'not_this_form';
                    let rememberMe = document.forms['signInForm'].capture_signIn_rememberMe;
                    if (rememberMe) {
                        rememberMe.checked = true;
                    }
                    let login = document.forms['signInForm'].username;
                    let pwd = document.forms['signInForm'].password;
                    login.value = '{$this->AccountFields['Login']}';
                    login.dispatchEvent(new Event('input'));
                    login.dispatchEvent(new Event('change'));
                    login.dispatchEvent(new Event('blur'));
                    pwd.value = '" . str_replace("'", "\'", $this->AccountFields['Pass']) . "';
                    pwd.dispatchEvent(new Event('input'));
                    pwd.dispatchEvent(new Event('change'));
                    pwd.dispatchEvent(new Event('blur'));                               
                ");

                $this->savePageToLogs($selenium);
                $selenium->driver->executeScript("document.forms['signInForm'].login.click();");

                $res = $selenium->waitForElement(WebDriverBy::xpath('
                    //span[contains(text(), "hi, ")]
                    | //div[@id = "account-login"]//div[contains(@class, "login-errors") and not(@style = "display:none;")]
                    | //span[contains(@class, "gtm-error-msg")]
                '), 15);

                try {
                    $this->savePageToLogs($selenium);
                } catch (TimeOutException $e) {
                    $this->logger->debug("TimeoutException: " . $e->getMessage());
                    $selenium->driver->executeScript('window.stop();');
                }

                // provider bug fix (empty page workaround)
                if (!$res && $this->http->FindPreg("/'loginState':\s*true,/")) {
                    $selenium->http->GetURL("https://www.petsmart.com/account/about-you/");
                    $res = $selenium->waitForElement(WebDriverBy::xpath('
                        //span[contains(text(), "hi, ")]
                        | //div[@id = "account-login"]//div[contains(@class, "login-errors") and not(@style = "display:none;")]
                        | //span[contains(@class, "gtm-error-msg")]
                    '), 20);
                    $this->savePageToLogs($selenium);
                }
            } elseif ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                $retry = true;
                $this->markProxyAsInvalid();
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

            // Complete your profile
            if (strstr($currentUrl, 'https://www.petsmart.com/on/demandware.store/Sites-PetSmart-Site/default/LoyaltyController-CompleteProfile')) {
                $this->markProxySuccessful();
                $this->http->GetURL(self::REWARDS_PAGE_URL);
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }
}
