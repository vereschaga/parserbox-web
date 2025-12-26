<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerVitamin extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], "vitaminAward")) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->FilterHTML = false;

        $this->UseSelenium();

        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        $request = FingerprintRequest::firefox();
        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN - 5;
        $request->platform = 'Linux x86_64';
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        $this->setProxyGoProxies();

        if ($fingerprint !== null) {
            $this->http->setUserAgent($fingerprint->getUseragent());
        }

        $this->setKeepProfile(true);

        // refs #22003
        /*
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
        $this->seleniumOptions->addHideSeleniumExtension = false;

        $this->http->SetProxy($this->proxyReCaptcha());
        $this->seleniumOptions->addAntiCaptchaExtension = true;
        $this->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();
        */

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;

        try {
            $this->http->GetURL("https://www.vitaminshoppe.com/s/myAccount/login.jsp");
        } catch (UnexpectedAlertOpenException | Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->dismiss();
                $this->logger->debug("alert, dismiss");
            } catch (NoAlertOpenException | UnexpectedAlertOpenException $e) {
                $this->logger->error("UnexpectedAlertOpenException -> exception: " . $e->getMessage());
            } finally {
                $this->logger->debug("UnexpectedAlertOpenException -> finally");
                sleep(2);
            }
        }

        $this->http->RetryCount = 2;

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'vs_registerLoginEmailAddress']"), 5);

        if (!$login) {
            $this->solveDatadomeCaptcha();
            $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'vs_registerLoginEmailAddress']"), 5);
        }

        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'vs_registerLoginPassword']"), 0);
        $this->saveResponse();

        if (empty($pass)) {
            $this->logger->error('something went wrong');

            if (
                $this->http->FindPreg('#<iframe src="https://geo.captcha-delivery.com/captcha/\?initialCid#')
                || $this->http->FindSingleNode("//h1[contains(text(), 'The proxy server is refusing connections')]")
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(2, 0);
            }

            return $this->checkErrors();
        }

        try {
            $this->logger->debug("close popups");
            $this->driver->executeScript("
                document.querySelector('.modal-backdrop').style.display = 'none';
                document.querySelector('#vs-modal-supper-supplement-modal-id').style.display = 'none';
            ");
            $this->saveResponse();
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage());
        }

//        $mover = new MouseMover($this->driver);
//        $mover->logger = $this->logger;
//        $mover->duration = rand(100000, 120000);
//        $mover->steps = rand(50, 70);

        if (!empty($login)) {
            $this->logger->debug("login");
            $login->click();
            $login->sendKeys($this->AccountFields['Login']);

//            $mover->moveToElement($login);
//            $mover->click();
//            $mover->sendKeys($login, $this->AccountFields['Login'], 10);
        }

        $this->logger->debug("pass");
        $pass->click();
        $pass->sendKeys($this->AccountFields['Pass']);

//        $mover->moveToElement($pass);
//        $mover->click();
//        $mover->sendKeys($pass, $this->AccountFields['Pass'], 10);

        $this->logger->debug("click");

        try {
            $this->driver->executeScript("
                document.querySelector('#atg_store_loginButton').style.display = 'block';
                document.querySelector('#recaptchaSubmit').style.display = 'none';
            ");
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage());
        }
        sleep(1);
        $btn = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'atg_store_loginButton']"), 0);
        $this->saveResponse();

        if (empty($btn)) {
            $this->logger->error('something went wrong');

            return $this->checkErrors();
        }

        $this->driver->executeScript("
            document.querySelector('#atg_store_loginButton').click();
        ");
//        $btn->click();

        return true;

        // prevent 403
        /*
        if ($this->http->Response['code'] == 403) {
            $this->http->SetProxy($this->proxyDOP());
            $this->http->GetURL("https://www.vitaminshoppe.com/s/myAccount/login.jsp");
            $this->parseGeetestCaptcha();
        }// if ($this->http->Response['code'] == 403)
        */

        if (!$this->http->ParseForm('vs_registerLoginForm')) {
            return $this->checkErrors();
        }

        /*
        $formUrl = $this->http->FormURL;
        $form = $this->http->Form;

        $this->http->RetryCount = 0;
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data url not found");

            return false;
        }

        $this->http->NormalizeURL($sensorDataUrl);

        $data = [
            "sensor_data" => "7a74G7m23Vrp0o5c9263491.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.106 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399601,2640340,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.07809803139,812041320170,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,2900,520,0;1,0,0,0,2553,2553,0;-1,2,-94,-102,0,0,0,0,2900,520,0;1,0,0,0,2553,2553,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.vitaminshoppe.com/s/myAccount/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1624082640340,-999999,17373,0,0,2895,0,0,6,0,0,B2BE5E8611B178EEF7756412A31AC326~-1~YAAQFmAZuFwmrRl6AQAAIWHeIgbgL2BDuXEXi5WgCDxa2YQAfTvz6bFdEqQwdmJgkpyj7G5s0IU2qWrd2SqlnIbyLQ9gVkGLUQJNswBmBNKtQ/xUcIoXjJvYQYTlDjJAGAokm7ZdFB7NMTqEUSLThgvlFkq9rFN1XHPk1njvIsVMTg8s7kzza3Stdp0BzRBsF07PJFe/hj/sZOC/oGJzbB+/WPA+PnZBJpM7GHuLkkcQjUNI+ybuVRjhyKHlxZ1tHMmBRB3VzBwGuenA4Rl/30PCIxbJZl2HX45UekVCpvzpZcx/tu3eRJ178xRj7dcfliR2qDOKwZV7fabsDN3sgeWrhjHB3O8Fi382+SdDOMCdWaSD4fkioD8AlXOmgVymh8hK/aVr7FSXIaRm2FYkqVI=~-1~-1~-1,37860,-1,-1,30261693,PiZtE,62307,70,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,198025440-1,2,-94,-118,90435-1,2,-94,-129,-1,2,-94,-121,;9;-1;0",
        ];
        $headers = [
            "Accept"       => "*
        /*",
            "Content-type" => "application/json",
        ];
        sleep(1);
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);

        $data = [
            "sensor_data" => "7a74G7m23Vrp0o5c9263491.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.106 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399601,2640340,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.0176967508,812041320170,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,2900,520,0;1,0,0,0,2553,2553,0;-1,2,-94,-102,0,0,0,0,1365,1044,0;0,0,0,0,1813,630,0;0,0,0,0,1813,-1,0;0,0,0,0,2900,520,0;1,0,0,0,2553,2553,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.vitaminshoppe.com/s/myAccount/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,1671,0,1624082640340,28,17373,0,0,2895,0,0,1672,0,0,B2BE5E8611B178EEF7756412A31AC326~0~YAAQFmAZuKsmrRl6AQAABHDeIgbyoMQ1vcAZaLC9GHkiXFl+wf1f2VNyYx97ROJEtiD6k3uk7y3wZg5lvZfMAC3XlVld99KkbwfI0bfuv4UT7ebOGdrN2mqFUeTbJBT5V1mgFgWBgQhgTbklYgbdX/yT0RsuYU8vnfVDrcmQY/GKsukGhIRVcBNrhedYAUnmO2nji9hJdS+sqqPwZcCAL2LB12Oxk4udRo+jAeCugNFzJbrxHsef/jijQwk9y+HZNI0gVHK/9Z1j/uDxAH/yA9dHrGKkVl3x0ay5SI6UQZsVmDaltPmZz519nx8MWsc47X8I3YzYmdylU81fsO06Ggnms1NVp24rtTFeHaUSUq/Q/yYLMMvRFIsK7yDW0+2kmF7oW7Uefcss6mMFeT4Rph1UWIii6cQTILJafhL/6g==~-1~||1-WtUXUENvMP-1-10-1000-2||~-1,41485,306,-809149378,30261693,PiZtE,77754,72,0,-1-1,2,-94,-106,8,1-1,2,-94,-119,40,40,40,40,60,60,40,40,40,0,0,0,20,240,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.d52e2da29a1db,0.5c9db986fc106,0.bb7573226dad1,0.662322e9e3584,0.5ebdd81dd781d,0.4ddd874627f86,0.f84eddf69ebca,0.cbcc348cd267e,0.d6b9457c0283,0.b3c4abb7882c9;0,3,0,0,2,5,7,1,1,4;0,7,4,0,1,21,15,3,4,5;B2BE5E8611B178EEF7756412A31AC326,1624082640340,WtUXUENvMP,B2BE5E8611B178EEF7756412A31AC3261624082640340WtUXUENvMP,1,1,0.d52e2da29a1db,B2BE5E8611B178EEF7756412A31AC3261624082640340WtUXUENvMP10.d52e2da29a1db,222,123,174,63,118,139,72,131,202,152,19,201,103,210,67,165,11,199,205,227,156,11,230,2,57,122,137,160,229,247,202,157,447,0,1624082642011;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,198025440-1,2,-94,-118,132634-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0f7edf978cda71a82a5af4b78a8df770924ac922ce0e334cc52acd51016343e3,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;4;12;0",
        ];
        sleep(1);
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;

        $this->http->FormURL = $formUrl;
        $this->http->Form = $form;
        */

        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('vs_registerLoginPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('/atg/userprofiling/ProfileFormHandler.login', "Continue");
        $this->http->SetInputValue('r1', "on");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Scheduled maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'website is currently offline for scheduled maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The Vitamin Shoppe website is currently offline for scheduled maintenance.
        if ($message = $this->http->FindPreg("/(The Vitamin Shoppe website is currently offline for scheduled maintenance\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // An error occurred while processing your request.
        if ($message = $this->http->FindPreg("/An error occurred while processing your request\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry our site is being a bit moody right now.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry our site is being a bit moody right now')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry our site is being a bit moody right now
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Sorry our site is being a bit')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // maintenance
        if ($message = $this->http->FindSingleNode("//a[contains(text(), 'Our site needs some SELF CARE so we can come back stronger than ever in')]")) {
            throw new CheckException("Our site needs some SELF CARE so we can come back stronger than ever in a few hours.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        sleep(5);
        $this->solveDatadomeCaptcha();

        $this->waitForElement(WebDriverBy::xpath("//div[@class = 'error-master']/div[@class = 'error-master']"), 20);
        $this->saveResponse();
        /*
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $currentUrl = $this->http->currentUrl();

        if ($this->http->FindPreg('/http:/', false, $currentUrl)) {
            $currentUrl = preg_replace('/http:/', 'https:', $currentUrl);
            $this->http->GetURL($currentUrl);
        }
        */
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//div[@class = 'error-master']/div[@class = 'error-master']")) {
            $this->markProxySuccessful();

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        try {
            // Login successful
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

            if ($this->http->FindPreg("/^https:\/\/www\.vitaminshoppe\.com\/s\/myAccount\?_requestid=\d+$/i", false, $this->http->currentUrl())) {
                $this->markProxySuccessful();

                return true;
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.vitaminshoppe.com/rest/model/core/rest/customer/actor/VSICustomerActor/getUserInfoForDataLayer");
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));

        if (!isset($response->userInfoForDL->crmCustNumber)) {
            // Sorry, we are unable to process your request at this time. Please try again later.
            if (
                in_array($this->AccountFields['Login'], ['jasonkir@gmail.com', '2012@cory.fryling.name', 'vitaminshoppe@kokopop.net'])
                && $this->http->FindPreg('/"crmCustNumber": null,/', false, $this->http->Response['body'])) {
                throw new CheckException('Sorry, we are unable to process your request at this time. Please try again later.', ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }
        $customerNumber = $response->userInfoForDL->crmCustNumber;
        // Name
        if (isset($response->userInfoForDL->firstName, $response->userInfoForDL->lastName)) {
            $this->SetProperty("Name", beautifulName($response->userInfoForDL->firstName . " " . $response->userInfoForDL->lastName));
        }

        $this->http->GetURL("https://www.vitaminshoppe.com/rest/model/core/rest/navigation/actor/VSINavigationActor/userSummary");
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
        $this->SetProperty("Status", beautifulName($response->profile->status));

        $this->http->GetURL("https://www.vitaminshoppe.com/rest/model/vitaminshoppe/ca/actor/VSIRewardsActor/webHealthyAwardsBalance?customerNumber={$customerNumber}");
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
        $pointsDetails = $response->HealthyAwardsBalanceResponse->pointsDetails ?? null;
        // Balance - You earned ... points in the previous quarter.
        $this->SetBalance($pointsDetails->totalAvailablePoints ?? null);
        // Earn ... more points to get to the next level, for a $5 reward
        $this->SetProperty("PointsForNextReward", $pointsDetails->pointsToGo ?? null);

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $message = $response->HealthyAwardsBalanceResponse->errorDisplayMessage ?? null;

            if ($message == "<span>Sorry, we are unable to process your request at this time. Please try again later.</span>") {
                $this->SetWarning('Sorry, we are unable to process your request at this time. Please try again later.');
            }
        }

        $this->http->GetURL("https://www.vitaminshoppe.com/rest/model/core/rest/customer/actor/VSICustomerActor/getRewardsForLoyalty?customerNumber={$customerNumber}");
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
        // Total Awards available
        $this->SetProperty("AwardsAvailable", '$' . $this->http->FindPreg('/([^\/]+)/', false, $response->getRewardsForLoyalty->totalDollarValue));

        if (!empty($response->getRewardsForLoyalty->activeCoupons)) {
            foreach ($response->getRewardsForLoyalty->activeCoupons as $coupon) {
                $this->AddSubAccount([
                    'Code'           => 'vitaminAward' . $coupon->code,
                    'DisplayName'    => "Available award #" . $coupon->code,
                    'Balance'        => $coupon->value,
                    'ExpirationDate' => strtotime($coupon->expiryDate, false),
                ], true);
            }
        }
    }

    private function parseGeetestCaptcha()
    {
        $this->logger->notice(__METHOD__);

        $currentUrl = $this->http->currentUrl();

        // Parsing captcha config
        $config = $this->http->FindPreg('/var dd=([^}]+})/');
        $config = $this->http->JsonLog(str_replace("'", '"', $config));
        $config->initialCid = $config->cid ?? null;
        $config->cid = $this->http->getCookieByName("datadome", ".vitaminshoppe.com");

        if (!isset($config->host, $config->e, $config->s, $config->hsh, $config->initialCid, $config->cid)) {
            $this->logger->error('Cannot parse captcha configuration');

            return false;
        }

        $params = http_build_query([
            'initialCid' => $config->initialCid,
            'hash'       => $config->hsh,
            'cid'        => $config->cid,
            't'          => 'fe',
            'referer'    => 'https://www.vitaminshoppe.com/s/myAccount/login.jsp',
            's'          => $config->s,
            'e'          => $config->e,
        ]);
        $this->http->GetURL("https://{$config->host}/captcha/?{$params}");

        $gt = $this->http->FindPreg("/gt:\s*'(.+?)'/");
        $apiServer = $this->http->FindPreg("/api_server:\s*'(.+?)'/");
        $challenge = $this->http->FindPreg("/challenge:\s*'(.+?)'/");
        $userEnv = $this->http->FindPreg("/userEnv=' \+ encodeURIComponent\( '([^\']+)/");

        if (
            !$gt
            || !$apiServer
            || !$challenge
            || !$userEnv
        ) {
            $this->logger->notice('Not a geetest captcha');

            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $this->http->currentUrl(),
            //            "proxy"      => $this->http->GetProxy(),
            'api_server' => $apiServer,
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
        $request = $this->http->JsonLog($captcha, 3, true);

        if (empty($request)) {
            $this->logger->info('Retrying parsing geetest captcha');
            $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
            $request = $this->http->JsonLog($captcha, 3, true);
        }

        if (empty($request)) {
            $this->logger->error("geetest failed = true");

            return false;
        }

        $this->http->RetryCount = 0;
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "application/x-www-form-urlencoded; charset=UTF-8",
        ];
        $params = http_build_query([
            'cid'                        => $config->cid,
            'icid'                       => $config->icid,
            'ccid'                       => '',
            'userEnv'                    => $userEnv,
            'geetest-response-challenge' => $request['geetest_challenge'],
            'geetest-response-validate'  => $request['geetest_validate'],
            'geetest-response-seccode'   => $request['geetest_seccode'],
            'hash'                       => $config->hsh,
            'ua'                         => $this->http->userAgent,
            'referer'                    => 'https://www.vitaminshoppe.com/s/myAccount/login.jsp',
            'parent_url'                 => 'https://www.vitaminshoppe.com/',
            'x-forwarded-for'            => '',
            'captchaChallenge'           => '17189974' . random_int(1, 9),
            's'                          => $config->s,
        ]);
        $this->http->GetURL("https://geo.captcha-delivery.com/captcha/check?{$params}", $headers);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();

        $cookie = $this->http->FindPreg("/datadome=([^\;]+)/");

        if (!$cookie) {
            return false;
        }

        $this->http->setCookie("datadome", $cookie, ".vitaminshoppe.com", "/", null, true);
        $this->http->GetURL($currentUrl);

        return true;
    }

    private function solveDatadomeCaptcha(): bool
    {
        $this->logger->notice(__METHOD__);
        $captchaFrame = $this->waitForElement(WebDriverBy::xpath('//iframe[starts-with(@src, "https://geo.captcha-delivery.com/captcha") and @width="100%" and @height="100%"]'), 5);

        if (!$captchaFrame) {
            $this->logger->info('captcha not found');
            $this->saveResponse();

            return true;
        }
        $this->driver->switchTo()->frame($captchaFrame);
        $slider = $this->waitForElement(WebDriverBy::cssSelector('.slider'), 5);
        $this->saveResponse();

        if (!$slider) {
            $this->logger->error('captcha not found');
            $this->driver->switchTo()->defaultContent();
            $this->saveResponse();

            return false;
        }

        // loading images to Imagick
        [$puzzleEncoded, $imgEncoded] = $this->driver->executeScript('
            const baseImageCanvas = document.querySelector("#captcha__puzzle > canvas:first-child");
            const puzzleCanvas = document.querySelector("#captcha__puzzle > canvas:nth-child(2)");
            if (!baseImageCanvas || !puzzleCanvas) return [false, false];
            return [puzzleCanvas.toDataURL(), baseImageCanvas.toDataURL()];
        ');

        if (!$puzzleEncoded || !$imgEncoded) {
            $this->logger->error('captcha image not found');

            return false;
        }

        if (!extension_loaded('imagick')) {
            $this->DebugInfo = "imagick not loaded";
            $this->logger->error("imagick not loaded");

            return false;
        }

        // getting puzzle size and initial location on image
        $puzzle = new Imagick();
        $puzzle->setBackgroundColor(new ImagickPixel('transparent'));
        $puzzle->readImageBlob(base64_decode(substr($puzzleEncoded, 22))); // trimming "data:image/png;base64," part
        $puzzle->trimImage(0);
        $puzzle->clear();
        $puzzle->destroy();

        // saving captcha image
        $img = new Imagick();
        $img->setBackgroundColor(new ImagickPixel('transparent'));
        $img->readImageBlob(base64_decode(substr($imgEncoded, 22)));
        $path = '/tmp/seleniumPageScreenshot-' . getmypid() . '-' . microtime(true) . '.jpeg';
        $img->writeImage($path);
        $img->clear();
        $img->destroy();

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 60;
        $params = [
            'coordinatescaptcha' => '1',
            'textinstructions'   => 'Click on the most left edge of the dark puzzle / Кликните по самому левому краю темного паззла',
        ];
        $targetCoordsText = '';

        try {
            $targetCoordsText = $this->recognizer->recognizeFile($path, $params);
        } catch (CaptchaException $e) {
            $this->logger->error("CaptchaException: {$e->getMessage()}");

            if ($e->getMessage() === 'server returned error: ERROR_CAPTCHA_UNSOLVABLE') {
                $this->captchaReporting($this->recognizer, false); // it is solvable

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            if ($e->getMessage() === 'timelimit (60) hit') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }
        } finally {
            unlink($path);
        }

        $targetCoords = $this->parseCoordinates($targetCoordsText);
        $targetCoords = end($targetCoords);

        if (!is_numeric($targetCoords['x'] ?? null)) {
            $this->logger->error("something went wrong");

            return false;
        }

        $m = new MouseMover($this->driver);
        $distance = $targetCoords['x'] /* - $puzzleInitialLocationAndSize['x'] */;
        $stepLength = floor($distance / $m->steps);
        $pauseBetweenSteps = $m->duration / $m->steps;
        $m->enableCursor();
        $this->saveResponse();
//        $m->moveToElement($slider);
        $m = $this->driver->getMouse()->mouseDown($slider->getCoordinates());
        $distanceTraveled = 0;

        for ($stepsLeft = 50; $stepsLeft > 0; $stepsLeft--) {
            $m->mouseMove(null, $stepLength, 0);
            $distanceTraveled += $stepLength;
            usleep(round($pauseBetweenSteps * rand(80, 120) / 100));
        }
        $lastStep = round($distance - $distanceTraveled);

        if ($lastStep > 0) {
            $m->mouseMove(null, $lastStep, 0);
        }
        $this->saveResponse();
        $m->mouseUp();

        $this->logger->debug('switch to defaultContent');
        $this->driver->switchTo()->defaultContent();
        $this->saveResponse();
        $this->logger->debug('waiting for page loading captcha result');

        return true;
    }
}
