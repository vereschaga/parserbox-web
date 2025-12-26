<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerRydges extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $headers = [
        "Accept"        => "application/json, text/plain, */*",
        "Referer"       => "https://www.evtstays.com/account/",
        "X-NewRelic-ID" => "VgQGVVNQGwIBVVRTAAIGVFw=",
        "newrelic"      => "eyJ2IjpbMCwxXSwiZCI6eyJ0eSI6IkJyb3dzZXIiLCJhYyI6IjIyMTQ1MyIsImFwIjoiMTM4NjE3NTg2OSIsImlkIjoiNTQ1MGFkMjRmMjczNzI0YyIsInRyIjoiYmEyN2IwOTFjN2M5ZGEzY2I2OWRhYmI0NWE4NDIzNDgiLCJ0aSI6MTcyNzI2Mzg5MTA3Nn19",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_EU));

//        $this->UseSelenium();
//        $this->http->saveScreenshots = true;
//
//        $this->useChromePuppeteer();
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
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Email is not valid.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.evtstays.com/account/#/");
//        $this->challengeReCaptchaForm();

        if ($this->http->FindPreg("/(?:error code: 1020|form id=\"challenge-form\"|You are unable to access)/")) {
            $this->seleniumAuth();

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                if ($message = $this->http->FindSingleNode('//span[@data-error-code="wrong-email-credentials"]')) {
                    $this->logger->error("[Error]: {$message}");

                    if (strstr($message, 'Wrong email or password')) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;
                }
            }

            return false;
        }

//        if (!$this->http->ParseForm("PGRPLoginForm")) {
//        if ($this->http->Response['code'] != 200) {
//            return $this->checkErrors();
//        }
//        $this->http->Form = [];
//        $this->http->FormURL = 'https://bookings.priorityguestrewards.com/plugin/loginTrans';
//        $this->http->SetInputValue("userID", $this->AccountFields['Login']);
//        $this->http->SetInputValue("loginpass", $this->AccountFields['Pass']);

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.priorityguestrewards.com/';
        $arg['SuccessURL'] = 'https://www.priorityguestrewards.com/account/';

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            // 500 - Internal server error
            $this->http->FindSingleNode("//h2[contains(text(), 'server error')]")
            // Error establishing a database connection
            || $this->http->FindSingleNode("//h1[contains(text(), 'Error establishing a database connection')]")
            || $this->http->FindSingleNode('//h1[contains(text(), "Server Error in \'/\' Application.")]')
            || $this->http->FindPreg("/<H1>Server Error in '\/' Application\./")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        // retries
        if ($this->http->currentUrl() == 'https://www.priorityguestrewards.com.au/login/?refer=https%3A%2F%2Fwww.priorityguestrewards.com.au%2Faccount%2Facc%2Fdashboard.php') {
            throw new CheckRetryNeededException(3, 7);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm($this->headers) && $this->http->Response['code'] != 401) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'Data');

        if (
            ArrayVal($response, 'Success') == 'true'
            && $data != 'SYSTEM ERROR'
            && $this->loginSuccessful()
        ) {
            return true;
        }
        // INVALID MEMBERSHIP ACCOUNT OR PASSWORD.
        if ($data == "Invalid Email address.") {
            throw new CheckException("INVALID MEMBERSHIP ACCOUNT OR PASSWORD.", ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid membership account or password.
        if ($data == "Invalid membership account or password.") {
            throw new CheckException("Invalid membership account or password.", ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid Membership Id.
        if ($data == "Invalid Membership Id.") {
            throw new CheckException("INVALID MEMBERSHIP ID.", ACCOUNT_INVALID_PASSWORD);
        }
        // Error
        if ($data == "SYSTEM ERROR") {
            throw new CheckException($data, ACCOUNT_PROVIDER_ERROR);
        }

        if ($data == "Error") {
            throw new CheckException("We couldn't find that account. Please check your details and try again, or click \"Contact Us\" at the bottom of this page to get in touch with our team", ACCOUNT_PROVIDER_ERROR);
        }

        if ($data == "Error 1002") {
            throw new CheckException('Invalid membership account or password. Please check your details and try again, or click "Contact Us" at the bottom of this page to get in touch with our team', ACCOUNT_INVALID_PASSWORD);
        }

        /*
         * ERROR REACTIVATE ACCOUNT
         *
         * Please select one account to continue:
         *
         * Mem. No.	Pts	Last Used
         * xxxxx	0	[date]
         */
        if (strstr($data, "Error Reactivate Account:")) {
            $this->logger->info("Parse", ['Header' => 2]);
            [$number, $date, $balance, $name] = explode("/", $this->http->FindPreg("/Error Reactivate Account:\s*([^<]+)/", false, $data));
            // Name
            $this->SetProperty("Name", beautifulName($name));
            // Number
            $this->SetProperty("Number", $number);
            // Balance - Personal Points
            $this->SetBalance($balance);
        }// if (strstr($data, "Error Reactivate Account:"))
        /*
         * Error Multiple Ids
         *
         * Please select one account to continue:
         *
         * Mem. No.	Pts	Last Used
         * xxxxx	0	[date]
         */
        if (strstr($data, "Error Multiple Ids:")) {
            $this->logger->info("Parse", ['Header' => 2]);
            $accounts = explode(",", $this->http->FindPreg("/Error Multiple Ids:\s*([^<]+)/", false, $data));

            foreach ($accounts as $account) {
                [$number, $date, $balance, $name] = explode("/", $account);

                if (isset($name, $number, $balance)) {
                    $subAccounts[] = [
                        'Code'        => 'rydges' . $number,
                        'DisplayName' => "Account # {$number}",
                        // Balance - Personal Points
                        'Balance'     => $balance,
                        // Name
                        'Name'        => beautifulName($name),
                        // Number
                        'Number'      => $number,
                    ];
                }// if (isset($name, $number, $balance))
            }// foreach ($accounts as $account)

            if (isset($subAccounts)) {
                // Set Sub Accounts
                $this->SetProperty("CombineSubAccounts", false);
                $this->logger->debug("Total subAccounts: " . count($subAccounts));
                // Set SubAccounts Properties
                $this->SetProperty("SubAccounts", $subAccounts);
                $this->SetBalanceNA();
            }// if(isset($subAccounts))
        }// if (strstr($data, "Error Reactivate Account:"))

        $message = ArrayVal($response, 'Message');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == "Object reference not set to an instance of an object.") {
                throw new CheckException('Invalid membership account or password. Please check your details and try again, or click "Contact Us" at the bottom of this page to get in touch with our team', ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'), 4);
        // Name
        $this->SetProperty("Name", beautifulName(
            ($response->metadata->membership->profilePerson->givenName ?? null)
            ." ". $response->metadata->membership->profilePerson->familyName ?? null)
        );
        // Number
        $this->SetProperty("Number", $response->metadata->membership->profileMembership->memberId ?? null);
        // Balance - Total Points
        $this->SetBalance($response->metadata->membership->profileMembership->accountPoints ?? null);

        $this->http->RetryCount = 1;
        $this->http->GetURL("https://www.evtstays.com/wp-json/wp-evtstays-membership/v1/member-dashboard", $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
        // Eligible Nights
        $this->SetProperty("Nights", $response->totalNights ?? null);
        // Eligible Stays
        $this->SetProperty("Stays", $response->totalStays ?? null);
        // Level
        $this->SetProperty("Status", beautifulName($response->membershipDetailsSummary->curr->value ?? null));
        $this->SetProperty("StatusExpiration", $response->eligibleRetainDateExpiry ?? null);

        // refs#24631
        // Nights to retain status
        $this->SetProperty("NightsToRetainStatus", $response->summaryDetails->retain->nights->neededMoreNights ?? null);
        // Stays to retain status
        $this->SetProperty("StaysToRetainStatus", $response->summaryDetails->retain->stays->neededMoreStays ?? null);

    }

    protected function parseCaptcha($key = null, $method = 'userrecaptcha')
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key && $method == 'hcaptcha') {
            $key = '33f96e6a-38cd-421b-bb68-7806e1764460';
        }

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => $method,
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function loginSuccessful($selenium = null)
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.evtstays.com/wp-json/wp-evtstays-membership/v1/get-user", $this->headers);
        $this->http->RetryCount = 2;

        if ($selenium) {
            $this->savePageToLogs($selenium);
        }

        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'), 4);
        $email = $response->metadata->membership->profilePerson->emailAddress ?? null;//$this->http->FindPreg("/\"emailAddress\":\"([^\"]+)/")
        $this->logger->debug("[Email]: {$email}");

        if (strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function challengeReCaptchaForm()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->ParseForm("challenge-form")) {
            return false;
        }
        $key = $this->http->FindSingleNode("//form[@id = 'challenge-form']//script/@data-sitekey");
        $method = 'userrecaptcha';

        if ($this->http->FindSingleNode("//form[@id = 'challenge-form']//input[@name = 'cf_captcha_kind' and @value = 'h']/@value")) {
            $method = "hcaptcha";
        }
        $captcha = $this->parseCaptcha($key, $method);

        if ($captcha == false) {
            return false;
        }

        if ($method == "hcaptcha") {
            $this->http->SetInputValue("g-recaptcha-response", $captcha);
            $this->http->SetInputValue("h-captcha-response", $captcha);
            $this->http->PostForm();
        } else {
            $this->http->SetInputValue("g-recaptcha-response", $captcha);
            $this->http->PostForm();
        }

        return true;
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

            $selenium->useFirefox();//\SeleniumFinderRequest::FIREFOX_84
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->setKeepProfile(true);
            $selenium->disableImages();

            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL("https://www.evtstays.com/wp-json/wp-evtstays-membership/v1/login?v=1.0.2&returnTo=aHR0cHM6Ly93d3cuZXZ0c3RheXMuY29tLw%3D%3D");

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'username']"), 10);
            $button = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'CONTINUE')]"), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$button) {
                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $button->click();

            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 10);
            $button = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Continue')]"), 0);
            $this->savePageToLogs($selenium);

            if (!$passwordInput || !$button) {
                return false;
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            $this->logger->debug("click by btn");
            $button->click();

            $logout = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(., "Logout")]'), 10, false);

            if ($button = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Continue')]"), 0)) {
                $this->savePageToLogs($selenium);
                $button->click();
                $logout = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(., "Logout")]'), 10, false);
            }

            $this->savePageToLogs($selenium);

            if ($logout && $selenium->loginSuccessful()) {
                $selenium->Parse();
                $this->Balance = $selenium->Balance;
                $this->ErrorCode = $selenium->ErrorCode;
                $this->ErrorMessage = $selenium->ErrorMessage;
                $this->Properties = $selenium->Properties;
            }

            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
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
