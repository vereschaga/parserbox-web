<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerJcp extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private $account_id = null;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], "jcpReward")) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Enter a valid email address', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.jcpenney.com/signin');
        // parsing form on the page
        if (!$this->http->FindSingleNode('//input[@name = "email"]/@name') && $this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        return $this->getCookiesFromSelenium();

        if (!$this->sendSensorData()) {
            return false;
        }

        $data = [
            "email"      => $this->AccountFields['Login'],
            "grant_type" => "password",
            "password"   => $this->AccountFields['Pass'],
            //            "rememberMe" => true,
        ];
        $headers = [
            "Content-Type"     => "application/json;charset=utf-8",
            "Accept"           => "*/*",
            "X-KEEP-ME-SIGNED" => "true",
        ];
        $this->http->PostURL("https://account-api.jcpenney.com/v5/oauth2/token", json_encode($data), $headers);

        return true;
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            return false;
        }

        $data = [
            "sensor_data" => "7a74G7m23Vrp0o5c9207881.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,403534,6102647,1536,871,1536,960,1536,479,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8974,0.530807628265,820033051323.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,1331,310,0;0,0,0,1,1025,520,0;1,-1,0,0,331,331,0;-1,2,-94,-102,0,0,0,0,1331,310,0;0,0,0,1,1025,520,0;1,-1,0,0,331,331,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.jcpenney.com/signin-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1640066102647,-999999,17544,0,0,2924,0,0,3,0,0,790ACC6067EDA19326399254006281E9~-1~YAAQHdxgaAvj6dh9AQAARLCO2wcQzMETgLPWKqhkGCESMsqOu2iVA72UuJ10Nl4zZb3AGU3FVZBql+tPhbwmwxlUCv6M6+sJ+13dIHIxZM1+G6AAzaHF4tL0cz2Mb+k+WYskac00bSyT4t2JBUwYgmA61+f1YsTyIXojX/zyQ2BlfC2TphQtlf/LhEJo6vTleyLiYxNGzs/uVys45Gggy8xLmfIEc4AMtBs7jlWWbDhPWPb7lyIBgD+1iDf7ejMnFXK/S8CQWKhz4411ImGV5VzI/rj9pifWiNuxiinGFbEc/EXqyonPZhaFoOTb69pvk9nkRzuUNx/8BaLz9HjFYpGYz3wXezL7ZXuKo+7r31tWWvXLsFicarUvrS45jJ/sD18EltEiJvkij6v+~-1~-1~1640069638,37448,-1,-1,30261693,PiZtE,53806,79-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,91539555-1,2,-94,-118,89742-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
        ];
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"       => "*/*",
            "Content-type" => "application/json",
        ];
        $this->http->PostURL("https://www.jcpenney.com{$sensorDataUrl}", json_encode($data), $headers);
        $this->http->JsonLog();

        sleep(1);
        $data = [
            "sensor_data" => "7a74G7m23Vrp0o5c9207881.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,403534,6102647,1536,871,1536,960,1536,479,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8974,0.741596264370,820033051323.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,1331,310,0;0,0,0,1,1025,520,0;1,-1,0,0,331,331,0;-1,2,-94,-102,0,0,0,0,1331,310,0;0,0,0,1,1025,520,0;1,-1,0,0,331,331,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.jcpenney.com/signin-1,2,-94,-115,1,32,32,0,0,0,0,584,0,1640066102647,77,17544,0,0,2924,0,0,584,0,0,790ACC6067EDA19326399254006281E9~-1~YAAQHdxgaAvj6dh9AQAARLCO2wcQzMETgLPWKqhkGCESMsqOu2iVA72UuJ10Nl4zZb3AGU3FVZBql+tPhbwmwxlUCv6M6+sJ+13dIHIxZM1+G6AAzaHF4tL0cz2Mb+k+WYskac00bSyT4t2JBUwYgmA61+f1YsTyIXojX/zyQ2BlfC2TphQtlf/LhEJo6vTleyLiYxNGzs/uVys45Gggy8xLmfIEc4AMtBs7jlWWbDhPWPb7lyIBgD+1iDf7ejMnFXK/S8CQWKhz4411ImGV5VzI/rj9pifWiNuxiinGFbEc/EXqyonPZhaFoOTb69pvk9nkRzuUNx/8BaLz9HjFYpGYz3wXezL7ZXuKo+7r31tWWvXLsFicarUvrS45jJ/sD18EltEiJvkij6v+~-1~-1~1640069638,37448,942,705104919,30261693,PiZtE,17516,68-1,2,-94,-106,9,1-1,2,-94,-119,20,40,200,20,40,40,20,0,0,0,0,0,20,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5475-1,2,-94,-116,91539555-1,2,-94,-118,92861-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;19;7;0",
        ];

        $this->http->PostURL("https://www.jcpenney.com{$sensorDataUrl}", json_encode($data), $headers);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
        sleep(1);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        // retries
        if ($this->http->currentUrl() == 'https://www.jcpenney.com/signin' && $this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(3);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, 3, true);
        $access_token = ArrayVal($response, 'access_token', null) ?? ArrayVal($response, 'Access_Token', null);
        $account_id = ArrayVal($response, 'account_id', null) ?? ArrayVal($response, 'ACCOUNT_ID', null);

        if (isset($access_token, $account_id)) {
            $this->account_id = $account_id;
//            $data = [
//                "email" => $this->AccountFields['Login'],
//                "password" => $this->AccountFields['Pass'],
//                "captchaToken" => "",
//                "host" => "www.jcpenney.com"
//            ];
//            $this->http->RetryCount = 0;
//            $this->http->PostURL("https://www.jcpenney.com/v2/session", json_encode($data));
//            $this->http->RetryCount = 2;
//            $this->http->GetURL("https://www.jcpenney.com/cam/jsp/profile/secure/myAccount.jsp");
            $this->http->setDefaultHeader("Authorization", "Bearer {$access_token}");

            return true;
        }// if (isset($access_token, $account_id))
        else {
            $errorMessage =
                ArrayVal($response, 'errorMessage', null)
                ?? $this->http->FindSingleNode('
                    //div[@data-automation-id="signin_error"]/div/p[normalize-space() != ""]
                    | //div[@data-automation-id="signin_error"]/div/div/span
                ')
            ;
            $this->logger->error("[Error]: {$errorMessage}");

            // The email address or password you entered was not found in our records. Remember, your password is case sensitive. Please try again.
            if (strstr($errorMessage, 'The email address or password you entered was not found in our records.')
                // The email address or password you entered was not found. Please try again.
                || strstr($errorMessage, 'The email address or password you entered was not found.')
                // You have only one more attempt before this account is locked.
                || strstr($errorMessage, 'You have only one more attempt before this account is locked.')
                // The username or password you entered was not found. Please try again.
                || strstr($errorMessage, 'The username or password you entered was not found. Please try again.')
                // The customer's password reset is required
                || strstr($errorMessage, 'The customer\'s password reset is required')
                || $errorMessage == 'Please use forgot password to reset your password'
            ) {
                throw new CheckException($errorMessage, ACCOUNT_INVALID_PASSWORD);
            }
            // Oops. That was last unsuccessful sign in attempt. For your protection this jcp.com account is now locked. You will receive an email from JCPenney with a link to reactivate your account and reset your password.
            if (strstr($errorMessage, 'Oops. That was last unsuccessful sign in attempt. For your protection this jcp.com account is now locked.')
                // You have only one more attempt to login before the account gets locked.
                || strstr($errorMessage, 'Too many unsuccessful login attempts.')
                // Your account has been locked. Check your email for instructions on how to unlock it or request a change of password
                || strstr($errorMessage, 'Your account has been locked.')) {
                throw new CheckException($errorMessage, ACCOUNT_LOCKOUT);
            }
            // There was an error while processing your request. Please try after some time.
            if (
                strstr($errorMessage, 'There was an error while processing your request. Please try after some time.')
                || $errorMessage == 'Oops! Something went wrong.'
            ) {
                throw new CheckException($errorMessage, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://account-api.jcpenney.com/v5/accounts/{$this->account_id}?expand=addresses%2CpaymentMethods");
        $response = $this->http->JsonLog(null, 3, true);
        // Name
        $this->SetProperty('Name', beautifulName(ArrayVal($response, 'firstName') . " " . ArrayVal($response, 'lastName')));

        $this->http->GetURL("https://account-api.jcpenney.com/v5/accounts/{$this->account_id}/rewards-profile?expand=points%2Ccertificates%2Crecentactivity");
        $response = $this->http->JsonLog();

        // not a member
        if (isset($response->status) && ($response->status == 'NotEnrolled' || $response->status == 'Pending')) {
            $this->SetWarning(self::NOT_MEMBER_MSG);

            return;
        }// if (isset($response->status) && $response->status == 'NotEnrolled')

        // Balance - Current points balance
        if (isset($response->points->earnedPoints)) {
            $this->SetBalance($response->points->earnedPoints);
        } elseif (isset($response->errorMessage)) {
            if ($response->errorMessage == 'Unfortunately the JCPenney Rewards system is down right now. Try again later.') {
                throw new CheckException($response->errorMessage, ACCOUNT_PROVIDER_ERROR);
            }
            // AccountID: 4208651
            if ($response->errorMessage == "The customer's session is invalid.") {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($response->errorMessage == "get-rewards short-circuited and fallback failed.") {
                throw new CheckException("Uh-oh! It looks like we’re having some trouble with our rewards system right now. Rest assured, we’ll have things up and running again in no time. In the mean time, feel free to refresh the page or learn more about our rewards program below!", ACCOUNT_PROVIDER_ERROR);
            }
        }// elseif (isset($response->errorMessage))
        // ... points away from your next $10 reward
        if (isset($response->points->pointsAway)) {
            $this->SetProperty('NeededToNextReward', $response->points->pointsAway);
        }
        // exp date
        if (isset($response->points->expiryDate) && strtotime($response->points->expiryDate)) {
            $this->SetExpirationDate(strtotime($response->points->expiryDate));
        }
        // Rewards this Month
        if (isset($response->rewards)) {
            $this->SetProperty('RewardsThisMonth', count($response->rewards));
        }
        // My Status
        if (isset($response->tier->name)) {
            $status = str_replace('Credit Cardmember', '', $response->tier->name);
            $this->SetProperty('MyStatus', $status);
        }// if (isset($response->tier->name))

        if (empty($response->rewards)) {
            return;
        }
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($response->rewards as $reward) {
            $this->AddSubAccount([
                'Code'           => 'jcpReward' . $reward->barcode,
                'DisplayName'    => "JCPenney Reward ($reward->barcode)",
                'Balance'        => $reward->amount,
                'ExpirationDate' => strtotime($reward->expires),
                'BarCode'        => $reward->barcode,
                "BarCodeType"    => BAR_CODE_EAN_13,
                // Serial Number
                'SerialNumber' => $reward->serialNumber,
                // Reward code
                'RewardCode' => $reward->code,
            ], true);
        }// foreach ($response->rewards as $reward)
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox();
//            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
            $request = \AwardWallet\Common\Selenium\FingerprintRequest::firefox();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN - 5;
            $request->platform = 'Linux x86_64';
            $fingerprint = $this->services->get(\AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([$request]);

            if (isset($fingerprint)) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            try {
                $selenium->http->GetURL("https://www.jcpenney.com");
                $selenium->http->GetURL("https://www.jcpenney.com/signin");
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }
            $this->savePageToLogs($selenium);

            $loginInput = $selenium->waitForElement(WebDriverBy::id('loginEmail'), 8);
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('signin-password'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-automation-id = "signin_button"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$button || $selenium->driver->executeScript('return document.getElementById("loginEmail") == null || document.getElementById("signin-password") == null')) {
                $this->logger->error('form elements not found');

                return $this->checkErrors();
            }

            $this->logger->notice('remember Me');
            $selenium->driver->executeScript("let remMe = document.getElementById('keepMeLogged'); if (remMe) remMe.checked = true;");
            $this->logger->notice('login');
            /*
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            */
            $selenium->driver->executeScript(/** @lang JavaScript */ "
                function triggerInput(selector, enteredValue) {
                    let input = document.querySelector(selector);
                    if (!input) return;
                    input.dispatchEvent(new Event('focus', { bubbles: true }));
                    input.dispatchEvent(new Event('click', { bubbles: true }));
                    input.dispatchEvent(new KeyboardEvent('keydown',{'key':'a'}));
                    input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));
                    let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                    nativeInputValueSetter.call(input, enteredValue);
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    input.dispatchEvent(new Event('blur', { bubbles: true }));
                }
                triggerInput('#loginEmail', '{$this->AccountFields['Login']}');
                triggerInput('#signin-password', '{$this->AccountFields['Pass']}');
            ");

            $selenium->driver->executeScript(/** @lang JavaScript */
                '
            const constantMock = window.fetch;
            window.fetch = function() {
                console.log(arguments);
                return new Promise((resolve, reject) => {
                    constantMock.apply(this, arguments)
                        .then((response) => {                
                            if(response.url.indexOf("/oauth2/token") > -1) {
                                response
                                 .clone()
                                 .json()
                                 .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
                            }
                            resolve(response);
                        })
                        .catch((error) => {
                            reject(response);
                        })
                });
            }
            ');

            $this->savePageToLogs($selenium);
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('
                //div[contains(text(), "Sign Out")]
                | //div[@data-automation-id="signin_error"]/div/p
            '), 10);
            $this->savePageToLogs($selenium);

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('__yoda');");
            $this->logger->info("[Form responseData]: " . $responseData);

            if (!empty($responseData) && !$this->http->FindSingleNode('
                    //div[@data-automation-id="signin_error"]/div/p[normalize-space() != ""]
                    | //div[@data-automation-id="signin_error"]/div/div/span
            ')) {
                $this->logger->debug("save responseData to body");
                $this->http->SetBody($responseData);

                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }
        } catch (UnknownServerException | SessionNotCreatedException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }
}
