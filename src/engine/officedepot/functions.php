<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerOfficedepot extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = "https://www.officedepot.com/account/accountSummaryDisplay.do";

    /** @var CaptchaRecognizer */
    private $recognizer;

    private $responseDataRewards = null;

    /*
        static function FormatBalance($fields, $properties)
        {
            if (isset($properties['SubAccountCode'])
                && (strstr($properties['SubAccountCode'], "officedepotCertificate"))) {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
            }

            return parent::FormatBalance($fields, $properties);
        }
    */

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptcha(), false);
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
        $this->http->GetURL("https://www.officedepot.com/account/loginAccountSet.do");
//        if (!$this->http->ParseForm(null, '//form[@class = "login-form"]')) {
        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Right now, we’re taking care of some routine site maintenance to make your online shopping experience even better.') or contains(text(), 'Right now, we are taking care of some routine site maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->getCookiesFromSelenium();

        if ($this->responseDataRewards) {
            return true;
        }
//        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><#");
//
//        if (!$sensorDataUrl) {
//            $this->logger->error("sensor_data URL not found");
//
//            return false;
//        }
//        $retry = false;
//        $this->http->NormalizeURL($sensorDataUrl);
//        $key = $this->sendSensorData($sensorDataUrl);
//
        $captcha = $this->parseReCaptcha("6LdmZrMZAAAAAGzLpSIU6hjZp6hl10YlbHRI2EwI");

        if ($captcha === false) {
            $this->logger->error("failed to recognize captcha");

            return false;
        }

        $data = [
            "loginName"              => $this->AccountFields['Login'],
            "password"               => $this->AccountFields['Pass'],
            "autoLogin"              => "true",
            "googleRecaptchaV3Token" => $captcha,
            //            "googleRecaptchaV3Token" => "03AGdBq26yOnXAfBSo4gC8PL_BuPQeK8f6lZW9GMQX8A9TRbrodfQGg_IyL9BpWxwJ5Ilj8bFnfufVjS9hqu_tiItSnHAPwxms4eywbPCbAHQKKqFUM4_M6xdHA7LCVRhveEfOmh6ASxbBm5ytJLbC8RrG0DlftNCnADBo_Zw5flv303vCpeajS5D9nXR4bAy2DIqc3P6POqXNwq8u5EK7xQQwqEp80zS-uB-Dlul7pffYw7MAhS_sIHeYzqraKIjCrvhgkvzOYfQQ1NwsH4Y0C5CatA5vCDaBv1VPbOYV4DnfU1vB-jqgVT3N4Qno9c7r2KMA1bXGNYNdLXzBvgtCZqSvFXEZ6u8AbDnpN8L85Toxu5Bwnr0qz40KEgELBmkyrn9yLTui4TQzK8TDMfmMEKzldsC0ZW7RI2q9k7ZyGyEt-Tgm4g9Rk2yutW5pXCH3hbolmU7iHFC5Y51xFnh0UHt4nWSQ4FjP4jT1c2Zb8IC_N1xfbRWl3DVrPGIZJ…ywO8BkEwlM0dzomT3NFXu5vStN2E8Sepg-c1jFhT9GVPeqeQFtHvI707h16T9t29eRdPj3-ZjsZe_bnsrMBVbPVn4RM334f5xBnQX2nUhfB32MZ_JD-_LQLhYG4CK2pTHDr4GxhtOGrEmylBGHnH-EGZqStvEpABBJXScTQm5SQ-_Lur4OFxY45Gmjnzjjbgg-wwIF07WQyoevzu7gHVqHYn-K5uiV7j43g2mV9GvjlVRvqAXT_C-ufFwuAoHBZnyH9p41ewKZz-mhNbgFxbHDLzJ0PVMrXHAPRJjg1iMW9co62UuFcRsvV7AOSTjGZCnWuTdoM_KpZN5RSpJGsJYhbRY-Zls99JNxRxE04rr0oMqsfMOfuc6esL7O_ZS68byZmpHMCMY1AvmipeI9DRzPlQSNuwLxm592pOMSziVqr_4BXOsCsPSKpJXzulgxtmEi2fyoBWKrhhndkuOB8b8mLpactN55pgiWlIDu_vxAqZcPty166qDw7Ck47tN4",
        ];
//        $this->http->SetInputValue("username", $this->AccountFields['Login']);
//        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
//        $this->http->SetInputValue("autoLogin", "true");
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "application/json",
            "Referer"      => "https://www.officedepot.com/account/loginAccountDisplay.do?promo_name=od_rewards",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.officedepot.com/json/loginAccount.do", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//p[contains(normalize-space(), "Rest assured we are working diligently to resolve this issue. If you would like to place an order by phone or speak with one of our Customer Service representatives please contact us")]')
            && $this->http->Response['code'] == 403
        ) {
            throw new CheckRetryNeededException(2, 1);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "We\'ll Be Back Online Soon!")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        $message =
            $response->errorMessages[0]
            ?? $this->DebugInfo
            ?? null
        ;

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        // 2fa
        $success = $response->success ?? null;
        $twoFa = $response->twoFactorAttributes->account2FAEnabled ?? null;

        if ($success === false && $twoFa === true && empty($this->DebugInfo)) {
            $this->captchaReporting($this->recognizer);
            $this->parseQuestion($response);

            return false;
        }

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'We were unable to validate your credentials. Please verify and try again.') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);

            if (
                strstr($message, 'Your login name or Password is incorrect ')
                || strstr($message, 'You have entered invalid information (passwords are case sensitive). The next incorrect login will deactivate your login ID.')
                || strstr($message, 'Due to security reasons, your login ID is no longer active. Please contact Customer Service at ')
                || $message == 'The login name or Password is incorrect (passwords are case sensitive). Please try again.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'The features of your contract account are supported at business.officedepot.com')
                || $message == 'Sorry we could not log you in at this time. Please try again later.'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $message == "A backend is not available."
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($message, 'Due to security reasons, your password has been deactivated')
                || strstr($message, 'Due to multiple failed login attempts, your account has been locked.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // broken account, no errors, no auth
        if (in_array($this->AccountFields['Login'], [
            'Djfeliciano35@yahoo.com',
        ])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function parseQuestion($response)
    {
        $this->logger->notice(__METHOD__);

        $authId = $response->twoFactorAttributes->authId ?? null;

        if (!$authId) {
            return false;
        }
        // jwt.do
        $authToken = $this->getAuthToken($authId);

        if (!$authToken) {
            return false;
        }
        $this->State['authId'] = $authId;

        $maskedEmail = $response->twoFactorAttributes->maskedEmail ?? null;
        $maskedPhone = $response->twoFactorAttributes->maskedPhone ?? null;

        if ($maskedEmail) {
            $masked = $maskedEmail;
            $method = "EMAIL";
        } elseif ($maskedPhone) {
            $masked = $maskedPhone;
            $method = "SMS";
        } else {
            return false;
        }

        // prevent code spam    // refs #6042
        if ($this->getWaitForOtc()) {
            $this->sendNotification("account with mailbox // RR");
        }

        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        // send code
        $data = [
            "verificationMethodType" => $method,
            "purpose"                => "BASL",
            "source"                 => "WWW",
            "twoFactorAuthId"        => $authId,
        ];

        $headers = [
            "Content-Type"    => "application/json",
            "Authorization"   => "Bearer " . $authToken,
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "Origin"          => "https://www.officedepot.com",
            "Referer"         => "https://www.officedepot.com/account/accountSummaryDisplay.do",
        ];

        $this->http->PostURL("https://api.officedepot.io/services/two-factor-authentication/external/sendTwoFactorAuthCode", json_encode($data), $headers);
        $response = $this->http->JsonLog();
        $twoFactorAuthId = $response->responseObject->twoFactorAuthId ?? null;

        if (!$twoFactorAuthId) {
            return false;
        }

        // jwt.do
        $authToken = $this->getAuthToken($twoFactorAuthId);

        if (!$authToken) {
            return false;
        }
        $this->State['authToken'] = $authToken;

        $this->Question = "A validation code was sent to {$masked}. Enter the six digit validation code";
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        if (empty($this->State['authId']) || empty($this->State['authToken'])) {
            return false;
        }
        // verify
        $data = [
            "purpose"         => "BASL",
            "source"          => "WWW",
            "code"            => $this->Answers[$this->Question],
            "twoFactorAuthId" => $this->State['authId'],
        ];
        $headers = [
            "Content-Type"  => "application/json",
            "Authorization" => "Bearer " . $this->State['authToken'],
            "Referer"       => "https://www.officedepot.com/account/loginAccountDisplay.do",
            "Origin"        => "https://www.officedepot.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.officedepot.io/services/two-factor-authentication/external/verify", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        unset($this->State['authToken']);
        unset($this->State['authId']);
        unset($this->Answers[$this->Question]);

        $success = $response->responseObject->success ?? null;
        $twoFactorToken = $response->responseObject->token ?? null;

        if ($success == false && !$twoFactorToken) {
            $this->AskQuestion($this->Question, "Invalid Code", 'Question');

            return false;
        }
        // loginAccountSet
        $data = [
            "twoFactorToken" => $twoFactorToken,
        ];
        $headers = [
            "Accept"          => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-Type"    => "application/x-www-form-urlencoded",
            "Referer"         => "https://www.officedepot.com/account/loginAccountDisplay.do",
            "Origin"          => "https://www.officedepot.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.officedepot.com/account/authorized/loginAccountSet.do", $data, $headers);
        $this->http->RetryCount = 2;

        if ($this->http->currentUrl() == 'https://www.officedepot.com/account/authorized/loginAccountSet.do') {
            return false;
        }

        return true;
    }

    public function Parse()
    {
        $this->markProxySuccessful();

        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("(//div[contains(@class, 'contactName')]/span)[1]")));

        // get Rewards
        if (!empty($this->responseDataRewards)) {
            $response = $this->http->JsonLog($this->responseDataRewards);
            $this->sendNotification('rewards data parsed from xhr // BS');
        } else {
            $jsessionid = $this->http->getCookieByName("JSESSIONID");
            $this->http->GetURL("https://www.officedepot.com/mobile/loyalty/getRewards.do;jsessionid={$jsessionid}?_=" . time() . date("B"), [
                'X-Requested-With' => 'XMLHttpRequest',
            ]);
            $response = $this->http->JsonLog();

            // retries
            if ($this->http->Error == 'Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)') {
                throw new CheckRetryNeededException(2, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }
        // Not a member
        if (
            isset($response->wlrActiveLoyaltyID, $response->showPoints)
            && $response->wlrActiveLoyaltyID == false && $response->showPoints == false
        ) {
            $this->SetWarning(self::NOT_MEMBER_MSG);

            return;
        }

        // Balance - Available Rewards:
        $this->SetBalance($response->totalAvailableRewards ?? null);
        // Member Number:
        $this->SetProperty("Number", $response->loyaltyID ?? null);
        $vipTierEndDate = $response->vipTierEndDate ?? null;
        $vip = $response->vip ?? null;

        if (
            $vip == true
            && $vipTierEndDate
        ) {
            // VIP status expires
            $this->SetProperty("Status", "VIP");
            $this->SetProperty("StatusExpiration", $vipTierEndDate);
        }

        if (
            $vip == false
            && isset($response->vipTierNeedToSpend, $response->pilotTotalSpent)
        ) {
            // Spend to Next Level
            $this->SetProperty("SpendNextLevel", "$" . $response->vipTierNeedToSpend);
            // Spent YTD
            $this->SetProperty("SpentYTD", "$" . $response->pilotTotalSpent);
        }
        $loyaltyPoints = $response->loyaltyPoints ?? null;
        $totalPendingRewards = $response->totalPendingRewards ?? null;

        // refs #19977
        if (
            !empty($loyaltyPoints)
            || !empty($totalPendingRewards)
        ) {
            $this->sendNotification('Properties not empty, check on site, perhaps need to collect. - refs #19977');
        }

        // Reward Certificates
        if (!empty($response->loyaltyRewards) && is_array($response->loyaltyRewards)) {
            $this->SetProperty("CombineSubAccounts", false);

            foreach ($response->loyaltyRewards as $reward) {
                // Reward Certificate
                $code = $reward->fullRewardNumber;
                // Expiration Date
                $exp = $reward->expirationDate;
                // Balance
                $balance = $reward->balance;

                if (strtotime($exp) && isset($code)) {
                    $this->AddSubAccount([
                        'Code'           => 'officedepotCertificate' . str_replace('************', '', $code),
                        'DisplayName'    => "Reward Certificate #" . $code,
                        'Balance'        => $balance,
                        'Number'         => $code,
                        'ExpirationDate' => strtotime($exp),
                        'Pin'            => $reward->pin,
                    ]);
                }// if (strtotime($exp) && isset($displayName, $code))
            }// for ($i = 0; $i < $nodes->length; $i++)
        }// if (!empty($response->loyaltyRewards))

//        $this->http->GetURL("http://www.officedepot.com/loyaltyRedirect.do?page=accountSummary");
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindPreg('/"isLoggedIn":true/')
            || $this->http->FindPreg('/"errorCode":"","success":true,/')
        ) {
            return true;
        }

        return false;
    }

    private function getAuthToken($authId)
    {
        $data = [
            "source"          => "WWW",
            "twoFactorAuthId" => $authId,
        ];
        $headers = [
            "Content-Type" => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.officedepot.com/json/2fa/jwt.do", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $success = $response->success ?? null;
        $token = $response->token ?? null;

        if (!$success || !$token) {
            return false;
        }

        return $token;
    }

//    protected function parseReCaptcha($key)
//    {
//        $this->logger->notice(__METHOD__);
//
//        if (!$key) {
//            return false;
//        }
//
//        $postData = [
//            "type"         => "RecaptchaV3TaskProxyless",
//            "websiteURL"   => $this->http->currentUrl(),
//            "websiteKey"   => $key,
//            "minScore"     => 0.9,
//            "pageAction"   => "login_form",
//            "isEnterprise" => true,
//        ];
//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
//        $this->recognizer->RecognizeTimeout = 120;
//
//        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
//
//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
//        $this->recognizer->RecognizeTimeout = 120;
//        $parameters = [
//            "pageurl"   => $this->http->currentUrl(),
//            "proxy"     => $this->http->GetProxy(),
//            "version"   => "enterprise",
//            "invisible" => 1,
//            "action"    => "login_form",
//            "min_score" => 0.9,
//        ];
//
//        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
//    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        $key = rand(0, 3);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            if ($this->attempt == 2) {
                /*
                $selenium->useChromePuppeteer();
                */
                $selenium->useFirefoxPlaywright();
            } elseif ($this->attempt == 0) {
                $selenium->useGoogleChrome();
            } else {
                $selenium->useGoogleChrome();
                $request = FingerprintRequest::chrome();
                $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                $request->platform = 'Win32';
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                if ($fingerprint !== null) {
                    $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                    $selenium->http->setUserAgent($fingerprint->getUseragent());
                }
            }

            $selenium->usePacFile(false);
//            $selenium->disableImages();
//            $selenium->useCache();

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();

            try {
                $selenium->http->GetURL('https://www.officedepot.com/a/loyalty-programs/office-depot-rewards/');
            } catch (TimeOutException $e) {
                $this->logger->error("TimeoutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage());
                sleep(2);
                $this->savePageToLogs($selenium);
            }

            $linkToForm = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Log In")]'), 5);

            if (!$linkToForm) {
                if ($selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "We are sorry, but Office Depot is currently not available in your country")] | //h1[contains(text(), "Access Denied")] | //span[contains(text(), "This site can&rsquo;t be reached")]'), 0)) {
                    $this->markProxyAsInvalid();
                    $this->DebugInfo = 'request blocked';
                    $this->logger->error($this->DebugInfo);
                    $retry = true;
                }

                $this->DebugInfo = 'Not found first button';
                $this->logger->error($this->DebugInfo);

                $this->savePageToLogs($selenium);

                return false;
            }

            $this->savePageToLogs($selenium);
            $this->closePopup($selenium);
            $this->logger->debug("click Login btn");
//            $linkToForm->click();// traces in PW
            $selenium->driver->executeScript("try { document.querySelector('[data-auid=\"OdContentPublishPage_OdButton_text\"]').click() } catch (e) {}");
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 5);

            if (!$loginInput) {
                $selenium->driver->executeScript("let login = document.querySelector('input[name = \"username\"]'); if (login) login.style.zIndex = '100003';");
                $this->savePageToLogs($selenium);
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 5);
            }

            $this->savePageToLogs($selenium);

            if (!$loginInput) {
                return false;
            }

            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-auid="LoginPage_OdButton_LoginBtn"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$passwordInput || !$btn) {
                $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-auid="LoginPage_OdButton_CheckoutContinueLoginBtn"]'), 0);

                if (!$btn) {
                    return false;
                }

                $mover = new MouseMover($selenium->driver);
                $mover->logger = $this->logger;
                $mover->sendKeys($loginInput, $this->AccountFields['Login'], 5);
//                $loginInput->sendKeys($this->AccountFields['Login']);
                $btn->click();

                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 5);
                $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-auid="LoginPage_OdButton_CheckoutLoginBtn"]'), 0);
                $this->savePageToLogs($selenium);

                if (!$passwordInput || !$btn) {
                    return false;
                }
            } else {
                $loginInput->sendKeys($this->AccountFields['Login']);
            }

            $selenium->driver->executeScript("document.querySelector('input[name=\"keep_me_logged_in\"]').checked = true;");
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $selenium->driver->executeScript("$('#login-checkbox').prop('checked', 'checked');");

            $this->savePageToLogs($selenium);

            $selenium->driver->executeScript('
                const constantMock = window.fetch;
                window.fetch = function() {
                    console.log(arguments);
                    return new Promise((resolve, reject) => {
                        constantMock.apply(this, arguments)
                        .then((response) => {
                            if (response.url.includes("loginAccount.do")) {
                                response
                                .clone()
                                .json()
                                .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
                            }
                            if (response.url.includes("getRewards.do")) {
                                response
                                .clone()
                                .json()
                                .then(body => localStorage.setItem("responseDataRewards", JSON.stringify(body)));
                            }
                            resolve(response);
                        })
                    .catch((error) => {
                            reject(response);
                        })
                    });
                };
                document.querySelector("button[data-auid=LoginPage_OdButton_LoginBtn], button[data-auid=LoginPage_OdButton_CheckoutLoginBtn]").click();
            ');

            $res = $this->waitResult($selenium);

            if (empty($res) && !$this->loginSuccessful()) {
                $selenium->http->GetURL('https://www.officedepot.com/a/loyalty-programs/office-depot-rewards/');
                $linkToForm = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Log In")]'), 5);

                if (!$linkToForm) {
                    $this->savePageToLogs($selenium);

                    return false;
                }

                $this->savePageToLogs($selenium);
                $this->closePopup($selenium);
                $this->logger->debug("click Login btn");
//            $linkToForm->click();// traces in PW
                $selenium->driver->executeScript("try { document.querySelector('[data-auid=\"OdContentPublishPage_OdButton_text\"]').click() } catch (e) {}");
//                $linkToForm->click();
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 5);

                if (!$loginInput) {
                    $selenium->driver->executeScript("let login = document.querySelector('input[name = \"username\"]'); if (login) login.style.zIndex = '100003';");
                    $this->savePageToLogs($selenium);
                    $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 5);
                }

                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 2);
                $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-auid = "LoginPage_OdButton_LoginBtn"]'), 1);
                $this->savePageToLogs($selenium);

                if (!$loginInput || !$passwordInput || !$btn) {
                    return false;
                }

                $selenium->driver->executeScript("document.querySelector('input[name=\"keep_me_logged_in\"]').checked = true;");

                $mover = new MouseMover($selenium->driver);
                $mover->logger = $this->logger;
                $mover->sendKeys($loginInput, $this->AccountFields['Login'], 5);
//                $loginInput->sendKeys($this->AccountFields['Login']);
                $passwordInput->sendKeys($this->AccountFields['Pass']);
                $selenium->driver->executeScript("$('#login-checkbox').prop('checked', 'checked');");
                $this->savePageToLogs($selenium);
                $selenium->driver->executeScript('
                    const constantMock = window.fetch;
                    window.fetch = function() {
                        console.log(arguments);
                        return new Promise((resolve, reject) => {
                            constantMock.apply(this, arguments)
                            .then((response) => {
                                if (response.url.includes("json/loginAccount.do")) {
                                    response
                                    .clone()
                                    .json()
                                    .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
                                }
                                if (response.url.includes("getRewards.do")) {
                                    response
                                    .clone()
                                    .json()
                                    .then(body => localStorage.setItem("responseDataRewards", JSON.stringify(body)));
                                }
                                resolve(response);
                            })
                        .catch((error) => {
                                reject(response);
                            })
                        });
                    };
                ');
                $btn->click();
                //label[@for="email"]
                //button[@data-auid="UserAuthentication_OdButton_SendCodeBtn"]
                $res = $this->waitResult($selenium);
            }

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);
            $this->responseDataRewards = $selenium->driver->executeScript("return localStorage.getItem('responseDataRewards');");
            $this->logger->info("[responseDataRewards]: " . $this->responseDataRewards);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            if (!empty($responseData) && strstr($responseData, '"account2FAEnabled":true')) {
                $this->http->SetBody($responseData);
            } elseif (!empty($res) && !$this->loginSuccessful()) {
                $this->DebugInfo = $res->getText();

                if ($this->DebugInfo == 'Something went wrong, please refresh and try again.') {
                    $this->DebugInfo = 'recpatcha issue';
                    $retry = true;
                }
            }// } elseif (!empty($res) && !$this->loginSuccessful())

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (
            UnknownServerException
            | SessionNotCreatedException
            | WebDriverCurlException
            | TimeOutException
            | NoSuchWindowException
            | Facebook\WebDriver\Exception\WebDriverException
            $e
        ) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                if ($this->DebugInfo == 'recpatcha issue') {
                    throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
                }

                throw new CheckRetryNeededException(3);
            }
        }

        return $key;
    }

    private function closePopup($selenium)
    {
        $this->logger->notice(__METHOD__);
        $selenium->driver->executeScript("try { document.querySelector('a.mt-close').click() } catch (e) {}");
    }

    private function waitResult($selenium)
    {
        $this->logger->notice(__METHOD__);
        $results = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "login-error")]//strong/p | //div[starts-with(text(), "Welcome")] | //h2[contains(text(), "Available Rewards:")] | //h2[contains(text(), "Two Step Verification")]'), 20, false);
        $this->savePageToLogs($selenium);

        return $results;
    }

    private function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => "https://www.officedepot.com/account/loginAccountDisplay.do", //$this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
