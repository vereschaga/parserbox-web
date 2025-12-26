<?php

use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerHoneygold extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.joinhoney.com/paypalrewards/activity';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        if ($this->attempt == 0) {
            $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
//                $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
                $this->setProxyBrightData();
                $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $this->http->setUserAgent($fingerprint->getUseragent());
            }

            $wrappedProxy = $this->services->get(WrappedProxyClient::class);
            $proxy = $wrappedProxy->createPort($this->http->getProxyParams());
            $this->seleniumOptions->antiCaptchaProxyParams = $proxy;
//        } elseif ($this->attempt == 0) {
//            $this->useFirefox();
//            $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
//            $this->setKeepProfile(true);
//            $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        } else {
            $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
//            $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
            $this->setProxyBrightData();

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $this->http->setUserAgent($fingerprint->getUseragent());
            }
            /*
            $this->useGoogleChrome();
            //        $this->http->SetProxy($this->proxyReCaptcha());
            $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));

            $this->seleniumOptions->addHideSeleniumExtension = false;
            $this->seleniumOptions->userAgent = null;

            //        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
            //        $this->http->setDefaultHeader('Origin', 'https://www.joinhoney.com');

            */
            $this->seleniumOptions->addAntiCaptchaExtension = true;

            $wrappedProxy = $this->services->get(WrappedProxyClient::class);
            $proxy = $wrappedProxy->createPort($this->http->getProxyParams());
            $this->seleniumOptions->antiCaptchaProxyParams = $proxy;
        }

        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        return false;

        if (!isset($this->State['token'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $success = $this->loginSuccessful($this->State['token']);
        $this->http->RetryCount = 2;

        if ($success) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter a valid email.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
//        $this->http->GetURL("https://www.joinhoney.com/features/honeygold");
        $this->http->GetURL('https://www.joinhoney.com/features/paypalrewards');
        $mouse = $this->driver->getMouse();

        $showFormBtn = $this->waitForElement(WebDriverBy::id('header-log-in'), 2);

        if (!$showFormBtn) {
            $this->saveResponse();

            if ($this->loginSuccessfulSelenium()) {
                return true;
            }

            return $this->checkErrors();
        }
        // $showFormBtn->click();
        $mouse->mouseMove($showFormBtn->getCoordinates());
        $mouse->click();

        $loginWithEmailBtn = $this->waitForElement(WebDriverBy::id('auth-login-modal'), 2);

        if (!$loginWithEmailBtn) {
            $this->saveResponse();
            $this->logger->error($this->DebugInfo = 'Button to login with email not found');

            return $this->checkErrors();
        }
        $loginWithEmailBtn->click();
//        $mouse->mouseMove($loginWithEmailBtn->getCoordinates());
//        $mouse->click();

        $login = $this->waitForElement(WebDriverBy::id('email-auth-modal'), 2);
        $pwd = $this->waitForElement(WebDriverBy::id('pwd-auth-modal'), 0);

        if (!isset($login, $pwd)) {
            $this->saveResponse();
            $this->logger->error($this->DebugInfo = 'Form elements not found');

            return $this->checkErrors();
        }

        $mouse->mouseMove($login->getCoordinates());
        $mouse->click();
        $login->sendKeys($this->AccountFields['Login']);
        $mouse->mouseMove($pwd->getCoordinates());
        $mouse->click();
        $pwd->sendKeys($this->AccountFields['Pass']);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label = "Log in with Email" and not(@disabled)]'), 2);
        $this->saveResponse();

        if (!$btn) {
            return $this->checkErrors();
        }

        $mouse->mouseMove($btn->getCoordinates());
        $btn->click();
//        $mouse->click();

        return true;

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        // for parseCaptcha
        $bundle = $this->http->FindSingleNode('//script[contains(@src, "/main.bundle.js")]/@src');

        if (!$bundle) {
            return false;
        }
        $this->http->NormalizeURL($bundle);
        $this->http->GetURL($bundle);

        if (
//            !$this->http->FindPreg("/var .e=\"(6L[^\"]+)/")// 6LchU3oUAAAAAC80RDNMHlVFEukwEPpNq1R6IiM-
//            !$this->http->FindPreg("/\"3DE441E6-1235-4127-962B-429861CC9CE1\"/")// 6LchU3oUAAAAAC80RDNMHlVFEukwEPpNq1R6IiM-
            !$this->http->FindPreg("/\"6Lf9utoUAAAAADaxmV6tW2ZRyVem_W5WT8qJ3aIP\"/")// 6Lf9utoUAAAAADaxmV6tW2ZRyVem_W5WT8qJ3aIP
        ) {
            return false;
        }

        /*
        $captcha = $this->parseCaptcha();
        $captcha = $this->parseFunCaptcha();

        if ($captcha === false) {
            return false;
        }
        */
        $captchaCorr = $this->parseCaptcha(true);

        if ($captchaCorr === false) {
            return false;
        }

        // csrf
        $this->http->PostURL("https://d.joinhoney.com/v3/csrf/generate", "");
        $csrf = $this->http->Response['headers']['csrf-token'] ?? null;

        if (!$csrf) {
            $this->logger->error("csrf not found");

            return false;
        }

        $data = [
            "operationName" => "users_logUserInEmail",
            "variables"     => [
                "audience" => "d.joinhoney.com",
                "email"    => $this->AccountFields['Login'],
                "password" => $this->AccountFields['Pass'],
            ],
        ];
        // set headers
        $headers = [
            "Content-Type"               => 'application/json',
            "Accept"                     => '*/*',
            "Referer"                    => 'https://www.joinhoney.com/',
            "honey-session-validation-e" => $captchaCorr,
            /*
            "honey-session-validation-a" => $captcha,
            "honey-session-validation"      => $captcha,
            "honey-session-validation-corr" => $captchaCorr,
            */
            "Service-Name"               => "honey-website",
            "Service-Version"            => "40.4.1",
            "csrf-token"                 => $csrf,
        ];
        // send post
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://d.joinhoney.com/v3', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $el = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "userProfile") and @role="button"] | //div[starts-with(@class, "notificationCopy") and contains(@class, " alert")] | //h2[contains(text(), "Sorry, something went wrong. We\'re working on it!")]'), 52);
        $this->saveResponse();

        if ($this->loginSuccessfulSelenium()) {
            return true;
        }

        $error = $this->http->FindSingleNode('//div[starts-with(@class, "notificationCopy") and contains(@class, " alert")]')
            ?? $this->http->FindSingleNode('//div[starts-with(@class, "inputHintText") and @aria-invalid = "true"]/span');

        if ($error) {
            $this->logger->error("[Error]: $error");

            if (str_contains($error, 'Incorrect email and/or password')) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            // captcha issue
            if ($error == "There's been an unexpected error. We're on it so check back soon. (eml-U1)") {
                $this->DebugInfo = $error;
                $this->saveResponse();

                sleep(3);
                $this->saveResponse();

                $this->waitFor(function () {
                    return is_null($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0));
                }, 120);

                $btn = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label = "Log in with Email" and not(@disabled)]'), 2);
                $this->saveResponse();

                if (!$btn) {
                    return $this->checkErrors();
                }

                $btn->click();

                $el = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "userProfile") and @role="button"] | //a[contains(text(), "Recaptcha server reported that site key is invalid")]'), 12);
                $this->saveResponse();

                if (!$el) {
                    $btn = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label = "Log in with Email" and not(@disabled)]'), 2);
                    $this->saveResponse();

                    if (!$btn) {
                        return $this->checkErrors();
                    }

                    $btn->click();

                    $el = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "userProfile") and @role="button"] | //a[contains(text(), "Recaptcha server reported that site key is invalid")]'), 12);
                    $this->saveResponse();

                    if (
                        ($error = $this->http->FindSingleNode('//div[starts-with(@class, "notificationCopy") and contains(@class, " alert")]')
                        ?? $this->http->FindSingleNode('//div[starts-with(@class, "inputHintText") and @aria-invalid = "true"]/span'))
                        && $error == "There's been an unexpected error. We're on it so check back soon. (eml-U1)"
                    ) {
                        throw new CheckRetryNeededException(2, 0, $error);
                    }
                }

                if ($this->loginSuccessfulSelenium()) {
                    return true;
                }

                if ($this->http->FindSingleNode('//a[contains(text(), "Recaptcha server reported that site key is invalid")]')) {
                    throw new CheckRetryNeededException(3, 0);
                }

                $this->DebugInfo = "Request blocked / captcha issue";

                return false;
            }

            if (str_contains($error, "You've reached the max number of tries. Check back later")) {
                $this->DebugInfo = "Reached the max number of tries";

                return false;
            }

            $this->DebugInfo = "[Error]: $error";

            return false;
        }

        if ($this->http->FindSingleNode('//h2[contains(text(), "Sorry, something went wrong. We\'re working on it!")]')) {
            throw new CheckRetryNeededException(3, 0);
        }

        return $this->checkErrors();

        $response = $this->http->JsonLog();
        $token = $response->data->logUserInEmail->accessToken ?? null;

        if ($token && $this->loginSuccessful($token)) {
            $this->captchaReporting($this->recognizer);
            $this->State['token'] = $token;

            return true;
        }

        $message =
            $response->errors[0]->message
            ?? $response->message
            ?? null
        ;

        if ($message) {
            $this->logger->error($message);

            if (
                strstr($message, 'Invalid email and/or password.')
                || $message == 'invalid EMAIL credentials'
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException('Invalid email and/or password.', ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'rate limited (B)')
                || strstr($message, 'rate limited (M)')
                || strstr($message, 'session validation')
            ) {
                $this->DebugInfo = $message;

                throw new CheckRetryNeededException();
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $tokenAfterLogin = str_replace('"', '', $this->driver->executeScript("return localStorage.getItem('hckey');"));
        $this->logger->info("[tokenAfterLogin]: " . $tokenAfterLogin);

        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
            $this->waitForElement(WebDriverBy::xpath('//div[starts-with(@class, "availablePoints")]'), 5);
            $this->saveResponse();
        }

        /*
        // Balance - (available_points)
        $this->SetBalance($this->http->FindSingleNode('//div[starts-with(@class, "availablePoints")]'));
        // Pending
        $pending = $this->http->FindSingleNode('//em[contains(., "Pending")]/span', null, false, self::BALANCE_REGEXP);
        $this->SetProperty('Pending', str_replace(',', '', $pending));
        // Total
        $this->SetProperty('Total', $this->http->FindSingleNode('//div[starts-with(@class, "statsBalanceAmt") and contains(text(), "redemption value")]', null, false, '/(\$\d+\.\d+)/'));

        if (!isset($this->Properties['Total']) && $totalEl = $this->waitForElement(WebDriverBy::xpath('//div[text() = "Lifetime Points"]/following-sibling::div/div[2]'), 0)) {
            $this->SetProperty('Total', $totalEl->getText());
        }
        $this->http->GetURL('https://www.joinhoney.com/settings');
        // Name
        $this->waitForElement(WebDriverBy::id('Settings:Profile:FirstName:Input'), 5);
        $this->saveResponse();

        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//input[@id = "Settings:Profile:FirstName:Input"]/@value') . " " . $this->http->FindSingleNode('//input[@id = "Settings:Profile:LastName:Input"]/@value')));
        */

        $token = str_replace('"', '', $this->driver->executeScript("return localStorage.getItem('hckey');"));
        $this->logger->info("[token]: " . $token);

        $localStorage = $this->driver->executeScript("return localStorage;");
        $this->logger->debug(var_export($localStorage, true));

        if (!$token) {
            $this->logger->error("token not found");

            return;
        }

        $this->driver->executeScript('
            await fetch("https://d.joinhoney.com/v3?operationName=web_getUserById", {
                "headers": {
                "Accept": "*/*",
                "Content-Type": "application/json",
                "Service-Name": "honey-website",
                "Service-Version": "40.4.1",
                "Csrf-Token": "' . $token . '",
            },
            mode: "cors",
            cache: "no-cache",
            credentials: "include",
            referrerPolicy: "strict-origin",
            }).then((response) => {
                    response
                    .clone()
                    .json()
                    .then(body => localStorage.setItem("profileData", JSON.stringify(body)));
            })
        ');
        sleep(2);
        $profileData = $this->driver->executeScript("return localStorage.getItem('profileData');");
        $this->logger->info("[Form profileData]: " . $profileData);

        $response = $this->http->JsonLog($profileData);
        // Balance - (available_points)
        $this->SetBalance($response->data->getUserById->points->pointsAvailable ?? null);
        // Name
        $FirstName = $response->data->getUserById->firstName ?? null;
        $LastName = $response->data->getUserById->lastName ?? null;
        $this->SetProperty('Name', beautifulName($FirstName . ' ' . $LastName));
        // Lifetime PayPal Honey Savings
        if ($response->data->getUserById->lifetimeSaving === null) {
            $this->SetProperty('LifetimePayPal', '$0.00');
        } elseif (isset($response->data->getUserById->lifetimeSaving->lifetimeSavingInUSD)) {
            $this->SetProperty('LifetimePayPal', '$' . $response->data->getUserById->lifetimeSaving->lifetimeSavingInUSD);
        }
        // Pending
        $this->SetProperty('Pending', $response->data->getUserById->points->pointsPendingDeposit ?? null);
        // Lifetime Points
        $total = $response->data->getUserById->points->pointsAvailable + $response->data->getUserById->points->pointsPendingDeposit;

        if (isset($response->data->getUserById->points->pointsRedeemed)) {
            $total += $response->data->getUserById->points->pointsRedeemed;
        }

        $this->SetProperty('Total', '$' . ($total / 100));
        // Lifetime Points
        $this->SetProperty('LifetimePoints', $total);
        // ReferralEarned
        $this->SetProperty('ReferralEarned', $response->data->getUserById->onboarding->referralPoints ?? null);

        // AccountID: 5338185
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindPreg("/,\"points\":null,\"onboarding\":\{\"demoComplete\":0,\"demoPoints\":0,\"referralComplete\":0,\"referralPoints\":0,/")
        ) {
            $this->SetBalance(0);
        }
    }

    protected function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg('/"([^\"]+)","\/\/\w+-api\.arkoselabs\.com/');

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }

    protected function parseCaptcha($isV3 = false)
    {
        $this->logger->notice(__METHOD__);
        // 6LfJKtsUAAAAAAUvdDNxsEK4i2Fn2H0Azjx1-idp - v2
        /*
        $key = $this->http->FindPreg("/sitekey:\"([^<]+)\",callback:r}/");// honey-session-validation
        */
        $key = $this->http->FindPreg("/6LfJKtsUAAAAAAUvdDNxsEK4i2Fn2H0Azjx1-idp/"); // honey-session-validation
        // 6LchU3oUAAAAAC80RDNMHlVFEukwEPpNq1R6IiM- - enterprise
        if ($isV3 == true) {
            /*
            $key = $this->http->FindPreg("/enterprise.execute\(\"([^\"]+)/");// honey-session-validation-corr
            */
            // AXIprJExPvmjMcwsUdVpBdiMWuqSBNIGHRgnSlFw5nizm4XWR3Hv1QeVIbGRkgiUbHG2wD_k6IOmMzNb",te="6LchU3oUAAAAAC80RDNMHlVFEukwEPpNq1R6IiM-",re="com.joinhoney.honey-web",ne="apple-signin-state"
//            $key = $this->http->FindPreg("/var .e=\"(6L[^\"]+)/"); // honey-session-validation-corr
            $key = $this->http->FindPreg("/siteKey=\"([^\"]+)/"); // honey-session-validation-e - 6Lf9utoUAAAAADaxmV6tW2ZRyVem_W5WT8qJ3aIP
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        /*
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        */
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => "https://www.joinhoney.com/features/paypalrewards",
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        $postData = [
            "type"         => "NoCaptchaTaskProxyless",
            "websiteURL"   => "https://www.joinhoney.com/features/paypalrewards",
            "websiteKey"   => $key,
        ];

        if ($isV3 === true) {
            $parameters += [
                "version"   => "enterprise",
                //                "version"   => "v3",
                "action"    => "login",
                "min_score" => 0.7,
                "invisible" => "1",
            ];

            $postData["type"] = "RecaptchaV3TaskProxyless";
            $postData += [
                "minScore"     => 0.3,
                //                "pageAction"   => "recaptchaScore",
                "pageAction"   => "login",
                "isEnterprise" => true,
            ];
        }

        return $this->recognizeAntiCaptcha($this->recognizer, $postData, false);

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessfulSelenium()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//div[contains(@class, "userProfile") and @role="button"]/@class')) {
            return true;
        }

        return false;
    }

    private function loginSuccessful($token)
    {
        $this->logger->notice(__METHOD__);

        $header = [
            'authorization' => 'honey ' . $token,
            'Content-Type'  => 'application/json',
        ];
        $this->http->GetURL('https://d.joinhoney.com/v3?operationName=web_getUserById', $header);
        $response = $this->http->JsonLog();

        if (isset($response->data->getUserById->userId)) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        return false;
    }
}
