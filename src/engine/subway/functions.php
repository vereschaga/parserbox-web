<?php

use AwardWallet\Common\Parsing\JsExecutor;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerSubway extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $loginUrl;
    private $selenium = false;
    private $seleniumUrl = null;

    private $headers = [
        'Accept'     => 'application/json, text/plain, */*',
        'modulecode' => 'SUB_STORMBORN',
    ];

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'subwayUSA')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyBrightData();

        $this->http->setHttp2(true);
    }

    public static function isClientCheck(AwardWallet\MainBundle\Entity\Account $account, $isMobile)
    {
        if ($isMobile) {
            return false;
        }

        return null;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // Your email and/or password were entered incorrectly.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("The email address or password is incorrect. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://order.subway.com/en-US/");
//        $this->http->GetURL("https://order.subway.com/Profile/Profile/Login");
//        $this->http->GetURL("https://order.subway.com/en-US/profile/rewards-activity");
//        $this->http->GetURL("https://www.subway.com/en-us/signin?url=/en-us/profile/rewards-activity");
        /*
        $csrf = $this->http->FindPreg("/\"csrf\"\s*:\s*\"([^\"]+)/");
        $tenant = $this->http->FindPreg("/\"tenant\"\s*:\s*\"([^\"]+)/");
        $policy = $this->http->FindPreg("/\"policy\"\s*:\s*\"([^\"]+)/");
        $transId = $this->http->FindPreg("/\"transId\"\s*:\s*\"([^\"]+)/");
        $remoteResource = $this->http->FindPreg("/\"remoteResource\"\s*:\s*\"([^\"]+)/");
        $pageViewId = $this->http->FindPreg("/\"pageViewId\"\s*:\s*\"([^\"]+)/");

        if (!$csrf || !$tenant || !$policy || !$transId || !$remoteResource || !$pageViewId) {
            return $this->checkErrors();
        }
        $this->loginUrl = $this->http->currentUrl();
        */

        if ($this->http->Response['code'] !== 200) {
            if (
                $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
                && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 7);
            }

            return $this->checkErrors();
        }

        $this->selenium = true;

        return $this->seleniumUSA();

        // Sensor Data
        if ($url = $this->http->FindSingleNode("//script[@type='text/javascript' and contains(@src,'/static/')]/@src")) {
            if ($sensorData = $this->getStaticSensorData()) {
                $this->sendSensorData($url, $sensorData);

                if ($sensorData = $this->getStaticSensorDataTwo()) {
                    $this->sendSensorData($url, $sensorData);
                }
            }
        }

        $this->http->GetURL($remoteResource);
        $captcha = $this->parseCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('g-recaptcha-response-toms', $captcha);
        }

        $this->http->FormURL = "https://id.subway.com{$tenant}/SelfAsserted?tx={$transId}&p={$policy}";
        $this->http->SetInputValue('signInName', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('request_type', "RESPONSE");
        $date = date('D M j Y H:i:s'); // Wed May 22 2019 11:27:22
        $this->http->SetInputValue('adobeAnalyticsTag', '{"storeCountry": "US","storeLanguage": "en","mid": "47491867804234323743639198858381520592","timestamp": "' . $date . '", "uxRendered": "desktop", "page.name": "account:log in", "event": "event10"}');

        if (!$this->http->PostForm([
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "X-CSRF-TOKEN"     => $csrf,
            "X-Requested-With" => "XMLHttpRequest",
            "Referer"          => $this->loginUrl, ])) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog(null, 3, true);

        if (!$this->http->FindPreg('/\{"status":"200"\}/ims')) {
            $message = ArrayVal($response, 'message');
            $this->logger->error("[Error]: {$message}");
            // Your password is incorrect
            if (in_array($message, [
                'We have enhanced our account security. Please reset your password to continue using your Subway account.',
                'The email address or password is incorrect. Please try again.',
                'Your password is incorrect',
                "We can't seem to find your account",
            ])) {
                throw new CheckException("The email address or password is incorrect. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }
            // We are sorry, your account could not be verified at this time.
            if (in_array(trim($message), ['We are sorry, your account could not be verified at this time.'])) {
                throw new CheckException(trim($message), ACCOUNT_PROVIDER_ERROR);
            }
            // Your account is temporarily locked to prevent unauthorized use. Try again later.
            if (in_array(trim($message), ['Your account is temporarily locked to prevent unauthorized use. Try again later.'])) {
                throw new CheckException(trim($message), ACCOUNT_PROVIDER_ERROR);
            }
            /**
             * passes after a while.
             */
            // Oops. Something is not right. Please try again with the correct account information. If the issue persists, Please call Guest Care at (833)-778-2929 and Press 2 for the Mobile App Team. Make note of the reference ID 18.16efdd17.1554437151.aeb4ba7 to help track your issue
            if ($this->http->FindPreg('/Oops. Something is not right. Please try again with the correct account information./', false, $message)) {
                $this->DebugInfo = 'Need to update sensor_data';

                return false;
            }

            return false;
        }// if (!$this->http->FindPreg('/\{"status":"200"\}/ims'))
        $this->logger->notice("Logging in...");
        $headers = [
            "Accept"  => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
            "Referer" => $this->loginUrl,
        ];
//                $this->http->GetURL("https://id.subway.com{$tenant}/api/CombinedSigninAndSignup/confirmed?csrf_token={$csrf}&tx={$transId}&metrics=v1.0.1;{$remoteResource};&p={$policy}", $headers);
        $param = [
            'csrf_token' => $csrf,
            'tx'         => $transId,
            'p'          => $policy,
            'diags'      => '{"pageViewId":"' . $pageViewId . '","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1584861671,"acD":1},{"ac":"T021 - URL:' . $remoteResource . '","acST":1584861671,"acD":539},{"ac":"T004","acST":1584861672,"acD":2},{"ac":"T019","acST":1584861672,"acD":6},{"ac":"T003","acST":1584861672,"acD":1},{"ac":"T002","acST":0,"acD":0}]}',
        ];
        $this->http->GetURL("https://id.subway.com{$tenant}/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param), $headers);

        if (!$this->http->ParseForm("auto")) {
            // New Terms & Conditions.
            // Wait! We made some changes 'round here.
            if (
                $this->http->FindPreg("/\"DISP\": \"I agree to Subway&#174; Terms and Conditions\",/")
                || $this->http->FindPreg('#"remoteResource":\s*".+?content/global/terms-and-conditions/termsandconditions_global#')
                || $this->http->FindPreg('#"remoteResource":\s*".+?content/global/terms-and-conditions/[a-z]{2}-[A-Z]{2}/termsandconditions_global#')
            ) {
                $this->throwAcceptTermsMessageException();
            }

            return $this->checkErrors();
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindPreg("/(Card site is currently under scheduled maintenance)/ims")) {
            throw new CheckException("My Subway® Card site is currently under scheduled maintenance", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h5[contains(text(), 'Online ordering is temporarily unavailable.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Server Error in '/' Application.
        if ($message = $this->http->FindPreg("/(Server Error in \'\/\' Application\.)/ims")
            //# Error 404
            || $this->http->FindSingleNode("//h2[contains(text(), '404 - File or directory not found')]")
            // 502 Proxy Error
            || $this->http->FindSingleNode("//title[contains(text(), '502 Proxy Error')]")
            // Service Temporarily Unavailable
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->selenium && !$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;
        // Our apologies... Our web site just ran out to get a delicious Subway® sandwich!
        $currentUrl = $this->seleniumUrl ?? $this->http->currentUrl() ?? null;

        if (
            ($message = $this->http->FindSingleNode('//h1[@id = "ErrorTitle"]/following-sibling::h2[@id = "ErrorText" and contains(text(), "Our web site just ran out to get a delicious Subway")]'))
            && (
                strstr($currentUrl, "https://order.subway.com/Areas/WebMain/ErrorPage/Error.html?message=An")
                || strstr($currentUrl, "https://order.subway.com/Areas/WebMain/ErrorPage/Error.html?aspxerrorpath=/")
                || strstr($currentUrl, "https://order.subway.com/Areas/WebMain/ErrorPage/Error.html?message=server_error")
            )
        ) {
            throw new CheckException("Our apologies... " . $message, ACCOUNT_PROVIDER_ERROR);
        }

        // Access is allowed
        if ($this->http->getCookieByName(".ASPXAUTH", "www.subway.com", "/", true)
            || strstr($this->http->getCookieByName("UserProfileCacheKey", ".subway.com", "/", true), '"isLoggedIn":true,')
        ) {
            $this->markProxySuccessful();

            return true;
        }

        // The user name or password is incorrect
        if ($this->http->FindPreg('/"token":""/ims') || $this->http->FindSingleNode('//p[@class = "error-block" and (@style="display: block;" or @style = "visibility: visible;") and contains(text(), "The email address or password is incorrect. Please try again.")]')) {
            throw new CheckException("The Email or Password is incorrect. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode('//p[@class = "error-block" and (@style="display: block;" or @style = "visibility: visible;") and contains(text(), "Oops. Something is not right. Please try again with the correct account information.")]')) {
            throw new CheckException("Oops. Something is not right. Please try again with the correct account information.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//p[@class = "error-block" and @style="visibility: visible;" and contains(text(), "Oops. Something is not right, please try again. If the issue persists, please visit")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // The user name or password is incorrect
        if ($message = $this->http->FindPreg("/(The Username or Password is incorrect\.\s*Please try again\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been locked due to invalid login attempts.
        if ($message = $this->http->FindPreg("/(Your account has been locked due to invalid login attempts\.)/ims")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Registration is not complete
        if ($message = $this->http->FindPreg("/(In order to proceed with your login\, we\'ll need you to update your profile so we can verify that you have given us a valid email address\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Something went wrong')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//div[@id = 'optInText' and contains(text(), 'I agree to these Terms & Conditions.')] |  //h1[@id = 'pageTitle' and contains(text(), 'Reset Password')]")) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindPreg('/^null$/ims')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (in_array($this->AccountFields['Login'], [
            'cupps14@yahoo.com',
            'dr_robert_schmidt@yahoo.de',
        ])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $this->sendNotification('check parse // MI');

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@id='dtmUserName']/@value")));
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode('//h2[@class = "rewards-content--points"]', null, true, "/(.+)\s+Point/ims"));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode('//h2[@class = "rewards-content--recruit"]'));
        // Spend until next Status
        $this->SetProperty("SpendUntilNextStatus", $this->http->FindSingleNode('//h2[contains(text(), "to unlock")]', null, true, "/Spend (.+) to unlock/"));

        // Certificates
        $this->logger->info('Certificates', ['Header' => 3]);
        $certificates = $this->http->XPath->query('//h1[contains(text(), "My Rewards")]/following-sibling::div[1]//div[@class = "card__details"]');
        $this->logger->debug("Total {$certificates->length} rewards were found");

        foreach ($certificates as $certificate) {
            $displayName = $this->http->FindSingleNode('.//h2[contains(@class, "card__title")]', $certificate);
            $exp = $this->http->FindSingleNode('.//p[contains(@class, "card__description")]', $certificate, true, "/Expires\s*([^<]+)/");
            $this->logger->debug("[$displayName]: {$exp}");

            if (!$displayName || !$exp) {
                continue;
            }

            $this->AddSubAccount([
                'Code'           => 'subwayUSA' . md5($displayName),
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => strtotime($exp),
            ]);
        }// foreach ($certificates as $certificate)

//        if ($this->http->FindSingleNode('//button[@data-testid="rewards-model"]'))

        return;

        $this->http->setDefaultHeader("Origin", "https://www.subway.com");
        $this->http->setDefaultHeader("Content-Type", "application/json");
        $this->http->setDefaultHeader("Accept", "application/json, text/javascript, */*");

        $data = [
            "datasourceId" => "DB43215A-B694-4CC6-BECC-B56F37F69407",
            "siteName"     => "remoteorderen-US",
        ];
        $headers = [
            //            "User-Agent"   => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/604.5.6 (KHTML, like Gecko) Version/11.0.3 Safari/604.5.6",
            "Referer"      => "https://www.subway.com/en-US/",
            "Content-Type" => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.subway.com/RemoteOrder/Rewards/GetRewardsInformation", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // Balance - Points
        $this->SetBalance($response->TotalPoints ?? null);
        // Status
        $this->SetProperty("Status", $response->CurrentTierTitle ?? null);
        // Name
        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindPreg("/([^!]+)/", false, $response->FirstName)));
        }

        $data = [
            "siteName" => "remoteorderen-US",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.subway.com/RemoteOrder/Rewards/GetRewardsData", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $rewardsResponse = $this->http->JsonLog(null, 3, false, 'certificateBalance');
        // Spend until next Status
        $this->SetProperty("SpendUntilNextStatus", $this->http->FindPreg("/Spend (.+) to unlock/", false, $rewardsResponse->tierDescription ?? null));
        // My Rewards
        if (isset($rewardsResponse->certificateBalance)) {
            $this->SetProperty("MyRewards", "$" . $rewardsResponse->certificateBalance);
        }

        // Certificates
        $this->logger->info('Certificates', ['Header' => 3]);
        // TODO: outdated code, need to update
        if (!empty($response->successResult->certificates->certificatesList) && is_array($response->successResult->certificates->certificatesList)) {
            foreach ($response->successResult->certificates->certificatesList as $certificate) {
                $this->AddSubAccount([
                    'Code'           => 'subwayUSA' . $certificate->serialNumber,
                    'DisplayName'    => "Reward",
                    'Balance'        => $certificate->amount,
                    'ExpirationDate' => strtotime($certificate->expirationDate),
                ]);
            }// foreach ($response->certificates->certificatesList as $certificate)
        }// if (!empty($response->certificates->certificatesList) && is_array($response->certificates->certificatesList))

        // Expiration Date refs #18787
        $this->logger->info('Expiration date', ['Header' => 3]);
        $rewardsActivityList = $rewardsResponse->rewardsActivityList ?? [];

        foreach ($rewardsActivityList as $activity) {
            if (stristr($activity->rewardType, 'Cash') || stristr($activity->rewardType, 'redemption')) {
                $this->sendNotification("refs #18787 - {$activity->rewardType} // RR");
            }
            /*
            $rewardAmount = $this->http->FindPreg('/\+([\d\,\,]+) Point/ims', false, $activity->rewardAmount);

            if ($rewardAmount == 0) {
                continue;
            }

            $lastActivity = $activity->date;
            break;
            */
        }// foreach ($rewardsActivityList as $activity)

        if (
            isset($lastActivity)
            && ($lastActivityDate = strtotime($lastActivity))
        ) {
            $this->SetProperty('LastActivity', $lastActivity);
            $this->SetExpirationDate(strtotime('+6 months', $lastActivityDate));
            $this->SetProperty('AccountExpirationWarning', 'Subway state the following on their website: <a href="https://www.subway.com/en-us/legal/subway-mvp-rewards">"Points earned through Subway® MVP Rewards Member Account transactions expire after six (6) months if there has been no activity on the Member Account in that six (6) month period. As used herein, “activity” is defined as any positive change to your Member Account balance, the earning of any Points, or any Subway® Cash or other Reward redemption. If your account shows any activity within that period of six (6) months, the expiration date of all your Points will be reset."</a><br><br> We determined that last time you had account activity with Subway on [LastActivity], so the expiration date was calculated by adding 6 months to this date.');
        }

        if (
            isset($rewardsActivityList->loyaltyHisErrorMessage->message)
            && $rewardsActivityList->loyaltyHisErrorMessage->message == "We can't retrieve your Rewards information right now, please try again later"
        ) {
            throw new CheckException($rewardsActivityList->loyaltyHisErrorMessage->message, ACCOUNT_PROVIDER_ERROR);
        }
    }

    public function seleniumUSA()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

//            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
            $selenium->useFirefoxPlaywright();
            $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
            /*$selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;*/
            $selenium->usePacFile(false);
            $selenium->setProxyMount();
            /*
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            $selenium->useFirefoxPlaywright();
            $selenium->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            */

            /*
//            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }
            */

            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();
            $selenium->http->GetURL("https://order.subway.com/en-US");
//            $selenium->http->GetURL("https://www.subway.com/en-us/signin");
//            $selenium->http->GetURL("https://order.subway.com/en-US/profile/rewards-activity");

            $selenium->waitForElement(WebDriverBy::xpath('//a[@data-title="Profile"] | //button[@data-title="Profile"] | //h1[contains(text(), "Online Ordering is temporarily unavailable.")] | //h1[contains(text(), "Access Denied")]'), 10);
            $this->savePageToLogs($selenium);
            $selenium->driver->executeScript('try { document.getElementById("onetrust-accept-btn-handler").click(); } catch (e) {}');
            sleep(1);
            $loginBtn = $selenium->waitForElement(WebDriverBy::xpath('//a[@data-title="Profile"] | //button[@data-title="Profile"]'), 0);
            $backToPaymentBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "BackToPaymentBtn"]'), 0);

            if ($backToPaymentBtn) {
                $this->savePageToLogs($selenium);

                try {
                    $backToPaymentBtn->click();
                    sleep(1);
                } catch (WebDriverException $e) {
                    $this->logger->error("WebDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
                }
            }

            $this->savePageToLogs($selenium);

            if (!$loginBtn) {
                if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Online Ordering is temporarily unavailable.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                return false;
            }

            try {
                $loginBtn->click();
            } catch (WebDriverException $e) {
                $this->logger->error("WebDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
            }
            /*
            $selenium->http->GetURL("https://www.subway.com/en-us/profile/rewards-activity");
            */

            $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "signInName"] | //h1[contains(text(), "Access Denied")]'), 80);
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "signInName"]'), 5);

            if (!$loginInput) {
                $selenium->driver->executeScript("let login = document.querySelector('input[id = \"signInName\"]'); if (login) login.style.zIndex = '100003';");
                $selenium->driver->executeScript("let pass = document.querySelector('input[id = \"password\"]'); if (pass) pass.style.zIndex = '100003';");
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'signInName']"), 5);
            }

            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            $this->savePageToLogs($selenium);

            $this->loginUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$this->loginUrl}");

            if (!$loginInput || !$passwordInput) {
                $this->logger->error("something went wrong");

                if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                return false;
            }
            $loginInput->clear();

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 5);
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 5);

             //$loginInput->sendKeys($this->AccountFields['Login']);
//            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "next" and not(@disabled)]'), 10);

            if (!$button) {
                $this->logger->error("something went wrong");

                return false;
            }
            $this->savePageToLogs($selenium);

            $button->click();

            sleep(5);
            $this->savePageToLogs($selenium);

            $selenium->waitForElement(WebDriverBy::xpath("//div[@id =  'rewards-inner-circle'] | //p[@class = 'error-block'] | //h1[@id = 'pageTitle' and contains(text(), 'Reset Password')] | //h1[contains(text(), 'Something went wrong')] | //button[id = 'continue']"), 40);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);

            $this->seleniumUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current selenium URL]: {$this->seleniumUrl}");

            if ($dismissButton = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(., "Dismiss")]'), 0)) {
                $dismissButton->click();
                sleep(3);
                $this->savePageToLogs($selenium);
            }

            if ($button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "rewards") and contains(text(), "Rewards")]'), 0)) {
                $selenium->driver->executeScript('try { document.getElementById("onetrust-accept-btn-handler").click(); } catch (e) {}');
                sleep(1);
                $this->savePageToLogs($selenium);

                $button->click();

                sleep(5);
                $this->savePageToLogs($selenium);
                $selenium->http->GetURL("https://www.subway.com/en-us/profile/rewards-activity");

                $selenium->waitForElement(WebDriverBy::xpath('//h2[@class = "rewards-content--recruit"]'), 15);
                $this->savePageToLogs($selenium);
            }

            return true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')
                || strstr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (SessionNotCreatedException $e) {
            $this->logger->error("SessionNotCreatedException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if ($this->http->FindPreg('/session\s*not\s*created\s*exception\s*from\s*unknown\s*error/ims', false, $e->getMessage())) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            $retry = true;
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(4, 7);
            }
        }

        return false;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey");

        if (!$key) {
            $key = $this->http->FindPreg("/'sitekey'\s*:\s*'([^\']+)/ims");
        }

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;

        if ($this->AccountFields['Login2'] == 'USA') {
            $url = $this->loginUrl;
        } else {
            $url = $this->http->currentUrl();
        }
        $this->logger->debug("pageurl: {$url}");
        $parameters = [
            "pageurl" => $url,
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, 1);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://rewards.subway.co.uk/tx-sub/members", $this->State['headers']);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (!empty($email) && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function sendSensorData($url, $sensor_data)
    {
        $this->http->RetryCount = 0;
        $data = [
            "sensor_data" => $sensor_data,
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => 'text/plain;charset=UTF-8',
            "Referer"      => $this->loginUrl,
        ];
        $this->http->PostURL('https://id.subway.com' . $url, json_encode($data), $headers);
        $this->http->JsonLog();

        $this->http->RetryCount = 2;
        sleep(1);
    }

    private function getStaticSensorData()
    {
        $sensor = [
            "7a74G7m23Vrp0o5c9045191.45-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,389123,7444256,1536,880,1536,960,1536,469,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8974,0.02901578814,790748722127.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://id.subway.com/02d64b66-5494-461d-8e0d-5c72dc1efa7f/oauth2/v2.0/authorize?p=b2c_1a_signup_signin-r2&ui_locales=en-US&client_id=f9129eff-3d7f-4699-a727-896ff258deb5&redirect_uri=https%3a%2f%2forder.subway.com&response_mode=form_post&response_type=code+id_token&scope=openid+offline_access&state=OpenIdConnect.AuthenticationProperties%3d6lpqA6-TLakkLEgoCaJhXzpb-6hmkGKn88hBhJ3BWOyDXgtNqOLJCNqwNtYpXHXH8cNSE5d_KM3-65E0CG262lv3ERXsg6eHjmVUOKKHsuGnkQUxXxa4AqAnolhbuXQTiRgdYDNuDR8tRzCqh4EIIm6tq2tSNVZmFB7ljwFgnAlG2pm2sN9ZX1-sVwYNi6b20z8Gp1DVzRlAcGUvP4a4s_kpBniSjWihPc7P7D8pMGHte4LLg4CPIUeCh3vD8tH2&nonce=637170942425936344.ZmQwNTNiMzktYjRmNS00NTU5LWE1MTUtOTc0NDE1MDg5MmRlYjI5NDliYzAtNDIxYy00MzFiLWI4NzQtYTZmM2NkZjEwMjQ4-1,2,-94,-115,1,1,0,0,0,0,0,1,0,1581497444255,-999999,16918,0,0,2819,0,0,6,0,0,48D948A59FC54ECFBBC3D1DC5A4AEC55~-1~YAAQbaw4F+ralgtwAQAAV+aXOAN8dmbsj9HjjM4gWG+myxU4s3cel1ohxQXZy/AmIaUEZK5MGgclZAuWuQbqIMNpzmK1I1aut9QXxm9AShLCe4RV1xLYTx91aqL8CbBkfExqYxxSAV9egGEI/qyy8dEg+dfLWOHGcqYvLZKwpJmZ1XK+ZquNi8lhyYaKlgGHjoLLbI7IHWDIR8RahGGcOxyNzrOwyf0U+V84R2Xffw6qu95HOWzSTucT7+jcD4KfbZ4VzstMc10vI1Y7haMZxcsimtX0L15sHcoKEzVuIesBhD84n+Uo2yj0~-1~-1~-1,29971,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-125,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,186106300-1,2,-94,-118,133887-1,2,-94,-121,;6;-1;0",
        ];

        return $sensor[array_rand($sensor)];
    }

    private function getStaticSensorDataTwo()
    {
        $sensor = [
            "7a74G7m23Vrp0o5c9045191.45-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,389123,7444256,1536,880,1536,960,1536,469,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8974,0.347867825173,790748722127.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://id.subway.com/02d64b66-5494-461d-8e0d-5c72dc1efa7f/oauth2/v2.0/authorize?p=b2c_1a_signup_signin-r2&ui_locales=en-US&client_id=f9129eff-3d7f-4699-a727-896ff258deb5&redirect_uri=https%3a%2f%2forder.subway.com&response_mode=form_post&response_type=code+id_token&scope=openid+offline_access&state=OpenIdConnect.AuthenticationProperties%3d6lpqA6-TLakkLEgoCaJhXzpb-6hmkGKn88hBhJ3BWOyDXgtNqOLJCNqwNtYpXHXH8cNSE5d_KM3-65E0CG262lv3ERXsg6eHjmVUOKKHsuGnkQUxXxa4AqAnolhbuXQTiRgdYDNuDR8tRzCqh4EIIm6tq2tSNVZmFB7ljwFgnAlG2pm2sN9ZX1-sVwYNi6b20z8Gp1DVzRlAcGUvP4a4s_kpBniSjWihPc7P7D8pMGHte4LLg4CPIUeCh3vD8tH2&nonce=637170942425936344.ZmQwNTNiMzktYjRmNS00NTU5LWE1MTUtOTc0NDE1MDg5MmRlYjI5NDliYzAtNDIxYy00MzFiLWI4NzQtYTZmM2NkZjEwMjQ4-1,2,-94,-115,1,1,0,0,0,0,0,190,0,1581497444255,58,16918,0,0,2819,0,0,190,0,0,48D948A59FC54ECFBBC3D1DC5A4AEC55~-1~YAAQbaw4F+ralgtwAQAAV+aXOAN8dmbsj9HjjM4gWG+myxU4s3cel1ohxQXZy/AmIaUEZK5MGgclZAuWuQbqIMNpzmK1I1aut9QXxm9AShLCe4RV1xLYTx91aqL8CbBkfExqYxxSAV9egGEI/qyy8dEg+dfLWOHGcqYvLZKwpJmZ1XK+ZquNi8lhyYaKlgGHjoLLbI7IHWDIR8RahGGcOxyNzrOwyf0U+V84R2Xffw6qu95HOWzSTucT7+jcD4KfbZ4VzstMc10vI1Y7haMZxcsimtX0L15sHcoKEzVuIesBhD84n+Uo2yj0~-1~-1~-1,29971,552,-915346280,30261693-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-125,-1,2,-94,-70,-36060876;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,4945-1,2,-94,-116,186106300-1,2,-94,-118,134371-1,2,-94,-121,;1;8;0",
        ];

        return $sensor[array_rand($sensor)];
    }
}
