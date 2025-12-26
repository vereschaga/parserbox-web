<?php

//  ProviderID: 1237

use AwardWallet\Common\Parsing\JsExecutor;

class TAccountCheckerVistara extends TAccountChecker
{
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $headers = [
        'Accept'           => '*/*',
        'Accept-Encoding'  => 'gzip, deflate, br',
        'Content-Type'     => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
        "Service-Type"     => "loyaltyApi",
        "AWS-Endpoint"     => "myProfileDetails",
        "isSecured"        => "true",
    ];

    private $userId = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
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

        $this->checkCookies();
//        return true;

//        $this->http->GetURL("https://www.airvistara.com/in/en");
        $this->http->GetURL("https://www.airvistara.com/in/en/club-vistara/login/login-page");

        if (!$this->http->ParseForm(null, "//form[@class = 'validate-sign-up']")) {
            return false;
        }

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->AccountFields['Pass'] = substr($this->AccountFields['Pass'], 0, 12);

        $jsExecutor = $this->services->get(JsExecutor::class);
        $script = "
        var enFirstParam = CryptoJS.enc.Latin1.parse('AEE0715D0778A4E4');
        var enSecondParam = CryptoJS.enc.Latin1.parse('secredemptionKey');
        function changedLoginData(encryptValue, enFirstParam, enSecondParam) {
            const givenString =
                typeof encryptValue === 'string'
                    ? encryptValue.slice()
                    : JSON.stringify(encryptValue);
            const encrypted = CryptoJS.AES.encrypt(givenString, enSecondParam, {
                iv: enFirstParam,
                mode: CryptoJS.mode.CBC,
                padding: CryptoJS.pad.Pkcs7,
            });
            return encrypted.toString();
        }
        
        sendResponseToPhp(changedLoginData(" . json_encode($this->AccountFields['Pass']) . ", enFirstParam, enSecondParam));
        ";
        $password = $jsExecutor->executeString($script, 5, ["https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.9-1/crypto-js.min.js"]);

        $data = [
            'customer' => [
                'authenticate' => [
                    'captchaResponse' => $captcha,
                    'userId'          => $this->AccountFields['Login'],
                    'password'        => $password,
                ],
                'channel'      => 'UK_WEB',
            ],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.airvistara.com/bin/airvistara/userlogin', json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The server is temporarily unable to service your')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('
                //h1[contains(text(), "502 Bad Gateway")]
                | //h2[contains(text(), "The request could not be satisfied.")]
            ')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] == 500) {
            $this->http->GetURL('https://www.airvistara.com/in/en/club-vistara/my-account');

            if ($this->http->Response['code'] == 500) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $isSuccess = $response->isSuccess ?? null;

        if ($this->http->FindSingleNode('//span[contains(text(), "CV Points balance")]')) {
            return true;
        }

        if ($isSuccess === true) {
            $this->captchaReporting($this->recognizer);
            $userInfo = $this->http->JsonLog(base64_decode($this->http->getCookieByName("userInfo")));
            $emailId = $userInfo->customer->profile->emailId ?? null;
            $this->userId = $userInfo->customer->userId ?? null;

            if (!$emailId) {
                /*
                    Dear Member,

                    We are incorporating changes and upgrades to our systems to give you an enhanced user experience.
                    We request you to update your password to access your Club Vistara account.
                    Request your cooperation and apologise for the inconvenience.

                    Thanks for your understanding.
                 */
                if (isset($userInfo->customer->isMigratedPassword) && $userInfo->customer->isMigratedPassword === true) {
                    $this->throwProfileUpdateMessageException();
                }

                $this->logger->error("something went wrong");

                return false;
            }

            // Mobile Number Verification
            if (!isset($userInfo->customer->accessToken)) {
                $data = [
                    "customer" => [
                        "otp"     => [
                            "appId" => "28",
                            "email" => false,
                            "sms"   => true,
                        ],
                        "channel" => "UK_WEB",
                        "userId"  => $this->userId,
                    ],
                ];
                $this->headers['AWS-Endpoint'] = 'generateOtp';

                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }

                $this->http->RetryCount = 0;
                $this->http->PostURL('https://www.airvistara.com/bin/airvistara/awsServiceJsonResponse', json_encode($data), $this->headers);
                $this->http->RetryCount = 2;
                $response = $this->http->JsonLog();
                // Please enter the One Time Password (OTP) that has been sent to your Mobile Number ******... .
                if (!isset($response->phoneNumber)) {
                    return false;
                }
                $this->State['userId'] = $this->userId;
                $this->State['uniqueId'] = $response->uniqueId;
                $question = "Please enter the One Time Password (OTP) that has been sent to your Mobile Number {$response->phoneNumber}";
                $this->AskQuestion($question, null, "Question");

                return false;
            }// if (!isset($userInfo->customer->accessToken))

            $data = [
                "customer" => [
                    "channel" => "UK_WEB",
                    "userId"  => $this->userId,
                    "profile" => [
                        "emailId" => $emailId,
                    ],
                ],
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.airvistara.com/bin/airvistara/awsServiceJsonResponse', json_encode($data), $this->headers);
            $this->http->RetryCount = 2;

            $response = $this->http->JsonLog();

            if (!empty($response->customer->userId)) {
                $this->State['userId'] = $response->customer->userId;

                return $this->loginSuccessful();
            } else {
                return $this->checkErrors();
            }
        }// if ($isSuccess === true)

        $err = $this->http->Response['headers']['errormessage'] ?? null;
        $this->logger->error("[errorMessage]: {$err}");

        if ($err) {
            $this->captchaReporting($this->recognizer);

            switch ($err) {
                // Authentication failed. Please check your Club Vistara ID and password.
                case 'Authentication failed due to incorrect Password.':
                    throw new CheckException("Authentication failed. Please check your Club Vistara ID and password.", ACCOUNT_INVALID_PASSWORD);

                    break;

                case 'This email ID is not registered with us':
                    throw new CheckException("User doesn't exist", ACCOUNT_INVALID_PASSWORD);

                    break;

                case 'Email address format is invalid.':
                    throw new CheckException("Please enter Email/Club Vistara ID", ACCOUNT_INVALID_PASSWORD);

                    break;

                case 'Email Address not found.':
                case 'Customer account status is Suspended':
                    throw new CheckException("Something went wrong, please connect to CV helpdesk.", ACCOUNT_INVALID_PASSWORD);

                    break;

                case 'Password is expired.':
                    throw new CheckException($err, ACCOUNT_INVALID_PASSWORD);

                case strstr($err, 'Incorrect password. Account will be locked after '):
                    if ($message = $this->http->FindPreg("/(Incorrect password. Account will be locked after \d+ unsuccessful attempts?\.)/", false, $err)) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    break;

                case strstr($err, 'Your account is locked due to 5 unsuccessful attempts. Please write to us at unlockaccount@clubvistara.com from your registered email address or contact Vistara Customer Service Centre on '):
                case 'Your account is locked due to 5 unsuccessful attempts with an invalid Password.':
                    throw new CheckException("Your account is locked due to 5 unsuccessful attempts with an invalid Password.", ACCOUNT_LOCKOUT);

                    break;
                // CREATE STRONG PASSWORD
                case 'Password length should be between 8 and 12.':
                // Your password was reset and you are currently using a temporary password. You will be redirected to set up a new password before accessing your Club Vistara account.
                case 'This user is using reset password':
                case 'you have not accessed your account for past one year ,kindly change your password':
                    $this->throwProfileUpdateMessageException();

                    break;

                case 'Sorry for inconvenience!!! We are not able to process this service now. Please try again later':
                case 'Sorry!!! Service got Timeout. We could not process now. Please try again later':
                case 'Sorry, we are unable to process your request right now ! Please try again later or contact Vistara Customer Service Centre on +91 9289228888 for quick assistance.':
                case 'Account Status not eligible for this activity':
                case strstr($err, 'Your account is under review in accordance with the terms and conditions of the Club Vistara program.'):
                case strstr($err, 'Account activation is not yet completed.'):
                case strstr($err, 'Due to the ongoing process of integration between Air India and Vistara, this service is currently unavailable.'):
                    throw new CheckException($err, ACCOUNT_PROVIDER_ERROR);

                    break;

                case 'Account is locked due to 5 unsuccessful attempts. Please contact Customer Service Centre.':
                    throw new CheckException($err, ACCOUNT_LOCKOUT);

                    break;

                case '20755':
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

                    break;

                default:
                    $this->logger->error("Unknown error");
                    $this->DebugInfo = $err;

                    break;
            }// switch ($err)
        }// if ($err)

        if ($this->http->Response['code'] === 504
            && $this->http->FindPreg("/CloudFront attempted to establish a connection with the origin, but either the attempt failed or the\s*origin closed the connection\./")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // AN EXCEPTION HAS OCCURRED
        if ($this->http->Response['code'] === 500 && $this->http->currentUrl() == 'https://www.airvistara.com/api/customer/loginDetails') {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("AN EXCEPTION HAS OCCURRED", ACCOUNT_PROVIDER_ERROR);
        }

        // Mobile Number Verification
        if (isset($response->customer->mobileVerified) && $response->customer->mobileVerified === false) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }

        // Account Security and Verification
        if (isset($response->isSuccess, $response->message) && $response->isSuccess === false && $response->message == 'Something went wrong!') {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->sendNotification("OTP was entered // RR");
        $this->userId = $this->State['userId'];
        $data = [
            "customer" => [
                "userId"   => $this->userId,
                "loggedIn" => false,
                "otp"      => [
                    "id"    => $this->State['uniqueId'],
                    "value" => $this->Answers[$this->Question],
                ],
            ],
        ];
        unset($this->State['userId']);
        unset($this->State['uniqueId']);
        unset($this->Answers[$this->Question]);
        $this->http->RetryCount = 0;
        $this->headers['AWS-Endpoint'] = 'authenticatePhoneNumber';
        $this->http->PostURL('https://www.airvistara.com/bin/airvistara/awsServiceJsonResponse', json_encode($data), $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (
            !empty($response->customer->userId)
            || !empty($response->customer->contact->email->emailId)
        ) {
            $this->State['userId'] = $response->customer->userId;

            return $this->loginSuccessful();
        }// if (!empty($response->customer->userId))

        // Entered OTP is wrong.Please enter OTP again
        $err = $this->http->Response['headers']['errormessage'] ?? null;
        $this->logger->error("[errorMessage]: {$err}");

        if ($err) {
            switch ($err) {
                case 'Entered OTP is wrong.Please enter OTP again':
                case 'OTP expired':
                    $this->AskQuestion($this->Question, $err, "Question");

                    return false;

                default:
                    $this->logger->error("Unknown error");

                    break;
            }// switch ($err)
        }// if ($err)

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', $this->http->FindSingleNode('//span[contains(text(), "CV Points balance")]/preceding-sibling::span'));
        // CV ID
        $this->SetProperty('CardNumber', $this->http->FindSingleNode('//span[contains(text(), "CV ID")]/following-sibling::span', null, true, "/\:(.+)/"));
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//span[contains(text(), "Member Since")]/following-sibling::span', null, true, "/\:(.+)/"));
        // Tier
        $this->SetProperty('Status', $this->http->FindSingleNode('//div[contains(@class, "dashboard-card")]//div[contains(@class, "dashboard-title") and not(contains(., "Points"))]'));
        // Tier Points required for CV ... Level
        $this->SetProperty('TierPointsForNextLevel', number_format($this->http->FindSingleNode('//span[contains(text(), "Tier Points required for")]/following-sibling::span')));
        // Flights required for CV ... Level
        $this->SetProperty('FlightsForNextLevel', $this->http->FindSingleNode('//span[contains(text(), "Flights required for")]/following-sibling::span', null, true, "/\d+/"));
        // Status expiration
        $this->SetProperty('StatusExpiration', $this->http->FindSingleNode('//p[contains(text(), "Valid till")]', null, true, "/till\s*(.+)/"));
        // Balance - CV Points balance
        $this->SetBalance($this->http->FindSingleNode('//span[contains(text(), "CV Points balance")]/span'));
        // Current Tier Points
        $this->SetProperty('PointTier', number_format($this->http->FindSingleNode('//span[contains(text(), "Current Tier Points")]/preceding-sibling::span')));
        // CV Points Earned
        $this->SetProperty('PointsEarned', number_format($this->http->FindSingleNode('//span[contains(text(), "CV Points Earned")]/preceding-sibling::span')));
        // Total Points Redeemed
        $this->SetProperty('PointRedeemed', number_format($this->http->FindSingleNode('//span[contains(text(), "CV Points Redeemed")]/preceding-sibling::span')));
        // Expiring Balance
        $this->SetProperty('ExpiringBalance', number_format($this->http->FindSingleNode('//div[span[contains(text(), "Expiring in")]]/preceding-sibling::div[1]/span')));
        // Expiring in
        $exp = $this->http->FindSingleNode('//span[contains(text(), "Expiring in")]/following-sibling::span', null, true, "/\:(.+)/");
        $this->logger->debug("Expiring in: {$exp}");
        $exp = str_replace(',', '', $exp);
        $this->logger->debug("Expiring in: {$exp}");

        if ($exp) {
            $this->SetExpirationDate(strtotime("last day of " . $exp));
        }

        if ($this->Balance <= 0) {
            return;
        }

        // refs #23846
        $this->logger->info('Expiration date', ['Header' => 3]);

        $data = [
            "customer" => [
                "channel"      => "UK_WEB",
                "userId"       => $this->State['userId'],
                "expiryMonths" => "3",
            ],
        ];
        $this->headers['AWS-Endpoint'] = 'expiryPoints';
        $this->headers['Service-Type'] = 'lmsCommonApi';
        $this->headers['AccessToken'] = $this->http->getCookieByName("accessToken", "www.airvistara.com", "/", true);
        $this->http->PostURL('https://www.airvistara.com/bin/airvistara/awsServiceJsonResponse', json_encode($data), $this->headers);
        $response = $this->http->JsonLog(null, 3, false, 'expirePoints');
        $expiringPointDetails = $response->expiryPointsDetail->expiringPointDetails ?? [];
        unset($exp);

        foreach ($expiringPointDetails as $expiringPointDetail) {
            if (isset($exp) && $exp > strtotime($expiringPointDetail->expirePoints)) {
                continue;
            }

            // Expiring Balance
            $this->SetProperty('ExpiringBalance', $expiringPointDetail->expirePoints);

            $exp = strtotime($expiringPointDetail->expireDate);

            if ($exp) {
                $this->SetExpirationDate($exp);
            }
        }// foreach ($expiringPointDetails as $expiringPointDetail)
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindPreg('/recaptcha\/api\.js\?render=([^\"]+)/')
            ?? $this->http->FindPreg('/\/api2\/anchor\?ar=1&amp;k=([^\"&]+)/')
        ;

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.airvistara.com/in/en/club-vistara/my-account");

        if ($this->http->FindSingleNode('//div[contains(text(), "Internal Server Error")]')) {
            sleep(5);
            $this->http->GetURL("https://www.airvistara.com/in/en/club-vistara/my-account");
        }

        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode('//span[contains(text(), "CV ID")]/following-sibling::span', null, true, "/\:(.+)/")) {
            return true;
        }

        return false;
    }

    private function checkCookies()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

            $selenium->useChromium();
//            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
//            $selenium->seleniumOptions->addHideSeleniumExtension = false;
//            $selenium->seleniumOptions->userAgent = null;

//            $selenium->setProxyBrightData();
//            $selenium->seleniumOptions->addAntiCaptchaExtension = true;
//            $selenium->seleniumOptions->antiCaptchaProxyParams = $selenium->getCaptchaProxy();

            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL("https://www.airvistara.com/in/en");
            sleep(rand(1, 5));
            $selenium->http->GetURL("https://www.airvistara.com/in/en/club-vistara/login/login-page");

            $login = $selenium->waitForElement(WebDriverBy::id('flyerid'), 15);
            /*
            $pass = $selenium->waitForElement(WebDriverBy::id('password'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "content-container"]//button[contains(text(), "Log In")]'), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$pass || !$btn) {
                $this->logger->error("something went wrong");

                return false;
            }

            $this->logger->debug("enter Login");
            $login->sendKeys($this->AccountFields['Login']);
            $this->logger->debug("enter password");
            $pass->sendKeys($this->AccountFields['Pass']);

//            $selenium->waitFor(function () use ($selenium) {
//                return is_null($selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0));
//            }, 120);
//            $this->savePageToLogs($selenium);
//            $captcha = $this->parseCaptcha();
//
//            if ($captcha === false) {
//                return false;
//            }
//
//            $selenium->driver->executeScript("$('#g-recaptcha-response').val(\"".$captcha."\");");

            $this->logger->debug("click");
            $this->savePageToLogs($selenium);
            $btn->click();

            $this->savePageToLogs($selenium);

            $res = $selenium->waitForElement(WebDriverBy::xpath('
                //span[contains(text(), "CV Points balance")]
            '), 15);
            */

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $selenium->driver->executeScript('window.stop();');
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }
}
