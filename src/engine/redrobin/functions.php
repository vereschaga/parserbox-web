<?php

use AwardWallet\Engine\ProxyList;

require_once __DIR__ . '/../pieology/functions.php';

class TAccountCheckerRedrobinPucnh extends TAccountCheckerPieologyPunchhDotCom
{
    public $code = "redrobin";
    public $reCaptcha = true;

    public function parseExtendedProperties()
    {
        $this->logger->notice(__METHOD__);
    }
}

class TAccountCheckerRedrobin extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private $headers = [
        'Accept'       => '*/*',
        'Content-Type' => 'application/json',
    ];

    private $rewards = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

        $headers = $this->headers + ['Authorization' => $this->State['Authorization']];
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://nomnom-prod-api.redrobin.com/punchhv2/user', $headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->user->first_name)) {
            $this->headers = $headers;

            return true;
        }

        unset($this->State['Authorization'], $this->headers['Authorization']);
        unset($this->State['authorization'], $this->headers['authorization']);

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        return $this->selenium();

        $this->http->GetURL('https://www.redrobin.com/account/login');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $data = [
            "user" => [
                "email"    => $this->AccountFields['Login'],
                "password" => $this->AccountFields['Pass'],
            ],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://nomnom-prod-api.redrobin.com/punchhv2/login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // login successful
        if (isset($response->access_token->token)) {
            $this->State['Authorization'] = $this->headers['Authorization'] = "Bearer " . $response->access_token->token;

            if (isset($response->user->first_name)) {
                return true;
            }
        }

        $message =
            $response->errors->base[0]
            ?? $this->http->FindSingleNode('//span[contains(@class, "error")]')
        ;

        if ($message) {
            $this->logger->error("[Error]: '{$message}'");

            if (
                $message == 'Sorry, we don\'t recognize that password. If it\'s been a while since you updated it, please select \'Forgot Password\' to reset it. Or, try again.'
            ) {
                throw new CheckException("Your password may not be recognized because we added new features to make ordering easier! Just click \"Forgot Password\" to reset it and Yummm will be restored.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'Sorry there was a log in issue. Please try again later.'
                || $message == 'Sorry, you\'ve reached the limit on login attempts. Try again later.'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $this->SetProperty('Name', beautifulName("{$response->user->first_name} {$response->user->last_name}"));

        $this->http->GetURL("https://www.redrobin.com/api/punchh/api2/mobile/users/balance", $this->headers);
        $response = $this->http->JsonLog($this->rewards, 5);
        // Balance - points
        $this->SetBalance($response->account_balance->points_balance);
        // ... POINTS AWAY FROM YOUR $10 REWARD!
        $this->SetProperty('ItemsNextReward', (100 - $response->account_balance->points_balance));
        // Lifetime Points
        $this->SetProperty('LifetimePoints', $response->account_balance->lifetime_points);

        $this->logger->debug("Total " . count($response->rewards) . " rewards were found");

        foreach ($response->rewards as $reward) {
            $displayName = $reward->name;
            $exp = strtotime($reward->expiring_at);
            $this->AddSubAccount([
                'Code'           => 'Reward' . md5($displayName . $exp),
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => $exp,
            ]);
        }
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

//            $selenium->useFirefoxPlaywright();
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
//            $selenium->setProxyMount();
//            $selenium->setKeepProfile(true);
//            $selenium->disableImages();
//            $selenium->useCache();
//            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
//            $selenium->seleniumOptions->addHideSeleniumExtension = false;

//            $selenium->setKeepProfile(true);
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            try {
                $selenium->http->GetURL('https://www.redrobin.com/account/login');
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'email']"), 10);
            $this->savePageToLogs($selenium);

            try {
                if (!$login && $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Checking your browser before accessing')]"), 0)) {
                    // save page to logs
                    $this->savePageToLogs($selenium);
                    $selenium->http->GetURL('https://www.redrobin.com/account/login');
                    $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'email']"), 10);
                }
            } catch (StaleElementReferenceException | UnexpectedJavascriptException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }

            if (!$login) {
                return false;
            }

            $password = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;

            $this->logger->debug("login");
            $mover->sendKeys($login, $this->AccountFields['Login'], 5);
            $this->logger->debug("pass");
            $mover->sendKeys($password, $this->AccountFields['Pass'], 5);
//            $login->sendKeys($this->AccountFields['Login']);
//            $password->sendKeys($this->AccountFields['Pass']);

            $signIn = $selenium->waitForElement(WebDriverBy::xpath('//div[@class = "c-sign-in__submit"]/button[not(@disabled)]'), 1);

            if (!$signIn) {
                $this->savePageToLogs($selenium);

                return false;
            }

            $signIn->click();

            sleep(5);
            $selenium->waitForElement(WebDriverBy::xpath("//*[contains(@class, 'rewards__challenge ')] | //input[@value = \"Verify you are human\"] | //div[@id = \"turnstile-wrapper\"]//iframe"), 25);

            if ($this->cloudFlareworkaround($selenium)) {
                $this->savePageToLogs($selenium);

                $login = $selenium->waitForElement(WebDriverBy::id("email"), 10);
                $this->savePageToLogs($selenium);

                try {
                    if (!$login && $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Checking your browser before accessing')]"), 0)) {
                        // save page to logs
                        $this->savePageToLogs($selenium);
                        $selenium->http->GetURL('https://www.redrobin.com/account/login');
                        $selenium->waitForElement(WebDriverBy::id("email"), 10);
                    }
                } catch (StaleElementReferenceException | UnexpectedJavascriptException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                }

                if (!$login) {
                    return false;
                }

                $password = $selenium->waitForElement(WebDriverBy::id('password'), 0);

                $login->sendKeys($this->AccountFields['Login']);
                $password->sendKeys($this->AccountFields['Pass']);

                $signIn = $selenium->waitForElement(WebDriverBy::xpath('//div[@class = "c-sign-in__submit"]/button[not(@disabled)]'), 1);

                if (!$signIn) {
                    $this->savePageToLogs($selenium);

                    return false;
                }

                $signIn->click();

                $selenium->waitForElement(WebDriverBy::xpath("//*[contains(@class, 'rewards__challenge ')]"), 25);
                $this->savePageToLogs($selenium);
            }

            $this->injectJQ($selenium);
            // save page to logs
            $this->savePageToLogs($selenium);

//            $seleniumDriver = $selenium->http->driver;
//            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
            $responseData = null;
//
//            foreach ($requests as $n => $xhr) {
//                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
//
//                if ($xhr->request->getUri() == 'https://www.redrobin.com/punchhv2/login') {
//                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
//                    $responseData = json_encode($xhr->response->getBody());
//
//                    break;
//                }
//
//                if ($xhr->request->getUri() == 'https://www.redrobin.com/punchhv2/balance') {
//                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
//                    $this->rewards = json_encode($xhr->response->getBody());
//
//                    break;
//                }
//            }

            $reduxStore = $this->http->JsonLog($selenium->driver->executeScript("return localStorage.getItem('reduxStore');"), 1);
            $first_name = $last_name = $token = null;

            foreach ($reduxStore as $data) {
                if (isset($data->firstname, $data->lastname)) {
                    $first_name = $reduxStore[$data->firstname];
                    $last_name = $reduxStore[$data->lastname];
                }

                if (isset($data->access_token)) {
                    $token = $reduxStore[$data->access_token];
                }
            }

            $responseData = json_encode([
                "user"         => [
                    "first_name" => $first_name,
                    "last_name"  => $last_name,
                ],
                "access_token" => [
                    "token" => $token,
                ],
            ]);

            if ($token) {
                $this->http->SetBody($responseData);

                if (!$this->rewards) {
                    $selenium->driver->executeScript('
                        $.ajax({
                            type: \'GET\',
                            url: \'https://nomnom-prod-api.redrobin.com/punchhv2/balance\',
                            async: false,
                            beforeSend: function(request) {
                                request.setRequestHeader(\'Accept\', \'*/*\');
                                request.setRequestHeader(\'Authorization\', `Bearer 62d68bef44b23f2d6ddc7b8ea1e76ad15a7c810a6a4a0a0cf9ee53f318857c92`);
                                request.setRequestHeader(\'Content-Type\', \'application/json\');
                            },
                            dataType: \'json\',
                            cache: false,
                            success: function (response) {
                                console.log(\'success\');
                                localStorage.setItem(\'responseData\', JSON.stringify(response));
                            },
                            error: function (response) {
                                console.log(`fail: profile data status = ${response.status}`);
                            }
                        });
                    ');

                    $this->rewards = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
                    $this->logger->info("[Form responseData]: " . $responseData);
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } catch (NoSuchDriverException | WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return $currentUrl;
    }

    private function injectJQ($engine)
    {
        $this->logger->notice(__METHOD__);
        $engine->driver->executeScript("
            var jq = document.createElement('script');
            jq.src = \"https://ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.js\";
            document.getElementsByTagName('head')[0].appendChild(jq);
        ");
    }

    /* @deprecated */
    private function cloudFlareworkaround($selenium)
    {
        $this->logger->notice(__METHOD__);

        $res = false;

        if ($verify = $selenium->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human']"), 0)) {
            $verify->click();
            $res = true;
        }

        if ($iframe = $selenium->waitForElement(WebDriverBy::xpath("//div[@id = 'turnstile-wrapper']//iframe"), 5)) {
            $this->savePageToLogs($selenium);
            $selenium->driver->switchTo()->frame($iframe);
            $this->savePageToLogs($selenium);

            if ($captcha = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'ctp-checkbox-container']/label"), 10)) {
                $this->savePageToLogs($selenium);
                $captcha->click();
                $this->logger->debug("delay -> 15 sec");
                $this->savePageToLogs($selenium);
                sleep(15);

                $selenium->driver->switchTo()->defaultContent();
                $this->savePageToLogs($selenium);
                $res = true;
            }
        }

        return $res;
    }
}
