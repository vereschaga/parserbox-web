<?php
use AwardWallet\Engine\ProxyList;
class TAccountCheckerChoiceSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    private const WAIT_TIMEOUT = 10;
    private const CONFIGS = [
        'chrome-84' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_84,
        ],
        'chrome-94' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_94,
        ],
        'chrome-95' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_95,
        ],
        'chrome-100' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_100,
        ],
        'puppeteer-103' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER,
            'browser-version' => SeleniumFinderRequest::CHROME_PUPPETEER_103,
        ],
        'firefox-53' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_53,
        ],
        'firefox-84' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_84,
        ],
        /*
        'firefox-100' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_100,
        ],
        'firefox-playwright-100' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
            'browser-version' => SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_100,
        ],
        'firefox-playwright-102' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
            'browser-version' => SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_102,
        ],
        'firefox-playwright-101' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
            'browser-version' => SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101,
        ],
        */
    ];
    private $configsWithOs = [];
    private $config;
    private $choice;
    
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->UseSelenium();
        $this->setConfig();
        $this->setProxyGoProxies();

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
        $this->setScreenResolution($resolution);
        
        $this->seleniumRequest->request(
            $this->configsWithOs[$this->config]['browser-family'],
            $this->configsWithOs[$this->config]['browser-version']
        );
        $this->seleniumRequest->setOs($this->configsWithOs[$this->config]['os']);

        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->http->saveScreenshots = true;
        $this->usePacFile(false);
    }

    public function IsLoggedIn()
    {
        return $this->getChoice(true)->IsLoggedIn();
    }

    public function LoadLoginForm(): bool
    {
        $this->http->removeCookies();

        try {
            $this->http->GetURL("https://www.choicehotels.com/choice-privileges/account");
        } catch (TimeOutException | ScriptTimeoutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="cpSignInUsername"]'), self::WAIT_TIMEOUT);

        try {
            $mover = new MouseMover($this->driver);
            $mover->logger = $this->logger;
            $mover->duration = rand(50000, 90000);
            $mover->steps = rand(40, 80);
        } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException | ErrorException $e) {
            $this->logger->error('[MoveTargetOutOfBoundsException | ErrorException]: ' . $e->getMessage());
        }
    
        if (!$login && !$loginButton = $this->waitForElement(WebDriverBy::xpath('//button[@id="header-sign-in-button"]'), self::WAIT_TIMEOUT)) {
            return $this->checkErrors();
        } else if (!$login && $loginButton) {
            try {
                $mover->moveToElement($loginButton);
                $mover->click();
            } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException | ErrorException $e) {
                $this->logger->error('[MoveTargetOutOfBoundsException | ErrorException]: ' . $e->getMessage());
                $loginButton->click();
            }
        }

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="cpSignInUsername"]'), self::WAIT_TIMEOUT);
        $pass = $this->waitForElement(WebDriverBy::xpath('//input[@id="cpSignInPassword"]'), 0);
        $this->saveResponse();

        if (!$login || !$pass) {
            return $this->checkErrors();
        }

        try {
            $mover->moveToElement($login);
            $mover->click();
            $mover->sendKeys($login, $this->AccountFields['Login'], 10);
        } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException | ErrorException $e) {
            $this->logger->error('[MoveTargetOutOfBoundsException | ErrorException]: ' . $e->getMessage());
            $login->click();
            $login->sendKeys($this->AccountFields['Login']);
        }

        try {
            $mover->moveToElement($pass);
            $mover->click();
            $mover->sendKeys($pass, $this->AccountFields['Pass'], 10);
        } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException | ErrorException $e) {
            $this->logger->error('[MoveTargetOutOfBoundsException | ErrorException]: ' . $e->getMessage());
            $pass->click();
            $pass->sendKeys($this->AccountFields['Pass']);
        }

        $this->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (/webapi\/user-account\/login/g.exec(url)) {
                        localStorage.setItem("responseData", this.responseText);
                    }
                });
                return oldXHROpen.apply(this, arguments);
            };
        ');

        $this->driver->executeScript('
            const constantMock = window.fetch;
            window.fetch = function() {
                console.log(arguments);
                return new Promise((resolve, reject) => {
                    constantMock.apply(this, arguments)
                        .then((response) => {                
                            if(response.url.indexOf("/webapi/user-account/login") > -1) {
                                response
                                    .clone()
                                    .json()
                                    .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
                            }
                            resolve(response);
                        })
                        .catch((error) => {
                            reject(error);
                        })
                });
            }
        ');

        $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "btn-login")]'), self::WAIT_TIMEOUT);
        $this->saveResponse();
        
        if (!$btn) {
            return $this->checkErrors();
        }

        try {
            $mover->moveToElement($btn);
            $mover->click();
        } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException | ErrorException $e) {
            $this->logger->error('[MoveTargetOutOfBoundsException | ErrorException]: ' . $e->getMessage());
            $btn->click();
        }

        $this->saveResponse();
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "cp-user-points")] | //label[@for="EMAIL"] | //button[@type="submit" and span[contains(text(), "Send Code")]]'), self::WAIT_TIMEOUT);
        $this->saveResponse();
        $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
        $this->logger->info("[Form responseData]: " . $responseData);

        if (!empty($responseData)) {
            $this->http->SetBody($responseData, false);
            return true;
        }

        $this->saveResponse();

        return $this->checkErrors();
    }

    public function Login()
    {
        if (
            $this->http->FindSingleNode('//div[@class="error-message-body"]/p/text()')
        ) {
            $this->markConfigAsSuccess();
        }

        $choice = $this->getChoice(true);
        $choice->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));
        
        if ($choice->loginSuccessful()) {
            return true;
        }

        if ($this->http->FindPreg("/\"INVALID_LOYALTY_MEMBER_CREDENTIALS_PERMANENT_LOCKOUT\":\"The supplied loyalty account has been permanently locked, please call the support center.\"/ims")) {
            throw new CheckException("Sign in failed", ACCOUNT_INVALID_PASSWORD);
        }
        if ($this->http->FindPreg("/\"INVALID_LOYALTY_MEMBER_CREDENTIALS_TEMPORARY_LOCKOUT\":\"The supplied loyalty account has been temporarily locked.\"/ims")) {
            throw new CheckException("The username or password is incorrect.", ACCOUNT_INVALID_PASSWORD);
        }
        if ($this->http->FindPreg("/\"INVALID_LOYALTY_O2A_MEMBER_CREDENTIALS\":\"Please enter a valid loyalty account\.\"/ims")) {
            throw new CheckException("The username or password is incorrect.", ACCOUNT_INVALID_PASSWORD);
        }
        if ($this->http->FindPreg("/outputErrors\":\{\"UNEXPECTED_TECHNICAL_FAILURE\":\"We’re currently experiencing a technical issue. Please try signing in later.\"/ims")) {
            throw new CheckException("We’re currently experiencing a technical issue. Please try signing in later.", ACCOUNT_PROVIDER_ERROR);
        }
        if ($this->http->FindPreg("/outputErrors\":\{\"(?:UNEXPECTED_TECHNICAL_FAILURE|UNEXPECTED_O2A_TECHNICAL_FAILURE)\":\"We're sorry, an unexpected error occurred. Please try signing in later.\s*\"/ims")) {
            throw new CheckException("We're sorry, an unexpected error occurred. Please try signing in later.", ACCOUNT_PROVIDER_ERROR);
        }
        if ($this->http->FindPreg("/\{\"status\":\"ERROR\",\"outputInfo\":\{\"NONEXISTENT_PPC_DATA_FOR_LOYALTY_PROGRAM\":\"Points plus cash is not available for the selected loyalty program.\"\},\"isEmailTaken\":false,\"isMFAVerifiedInSession\":false,/ims")) {
            throw new CheckException("We’re currently experiencing a technical issue. Please try signing in later.", ACCOUNT_PROVIDER_ERROR);
        }

        // broken account
        if (in_array($this->http->Response['code'], [403, 500]) && in_array($this->AccountFields['Login'], [
            'ChoiceJSH',
        ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        
        // Sign in failed
        if ($this->http->FindPreg("/\"(?:INVALID_LOYALTY_MEMBER_CREDENTIALS|INVALID_LOYALTY_O2A_MEMBER_CREDENTIALS)\":\"Please enter a valid loyalty account.\"/ims")) {
            throw new CheckException("The username or password is incorrect.", ACCOUNT_INVALID_PASSWORD);
        }
        if ($this->http->FindPreg("/\"status\":\"ERROR\",\"outputErrors\":\{\"INVALID_LOYALTY_MEMBER_CREDENTIALS_PERMANENT_LOCKOUT\":\"The supplied loyalty account has been permanently locked, please call the support center.\"},/ims")) {
            throw new CheckException("Your account is locked. To protect your account, it's been locked after too many sign-in attempts. Call 1-888-770-6800 to unlock your account.", ACCOUNT_LOCKOUT);
        }
        // Sign in failed - it looks like invalid credentials, but on valid accounts
        if (
            $this->http->FindPreg("/\"UNEXPECTED_FAILURE_GET_PROFILE\":\"We're sorry, an unexpected error occurred\.\"/ims")
            || $this->http->FindPreg("/\"UNEXPECTED_FAILURE\":\"We're sorry, an unexpected error occurred\.\"/ims")
        ) {
            throw new CheckException("Sign in failed", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/\"outputErrors\":\{\"UNAVAILABLE_GET_PROFILE\":\"We’re currently experiencing a technical issue. Please try signing in later.\"/ims")) {
            throw new CheckRetryNeededException(3, 2, "We’re currently experiencing a technical issue. Please try signing in later.", ACCOUNT_PROVIDER_ERROR);
        }
        if ($this->processQuestion()) {
            return false;
        }

        if (
            $message = $this->http->FindSingleNode('//div[@class="error-message-body"]/p/text()')
        ) {
            $this->markConfigAsSuccess();

            if (strstr($message, "The username or password is incorrect")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function processQuestion()
    {
        $this->logger->notice(__METHOD__);

        if($button = $this->waitForElement(WebDriverBy::xpath('//label[@for="EMAIL"]'), self::WAIT_TIMEOUT)) {
            $this->saveResponse();

            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            $button->click();
            $submit = $this->waitForElement(WebDriverBy::xpath('//button[@type="submit" and span[contains(text(), "Send Code")]]'), self::WAIT_TIMEOUT);
            $submit->click();
        }

        $this->saveResponse();

        $questionElement = $this->waitForElement(WebDriverBy::xpath('//p[@class="mfa-explanation" and p]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if (!$questionElement) {
            return $this->checkErrors();
        }

        $question = $questionElement->getText();
        $code = $this->waitForElement(WebDriverBy::xpath('//input[@id="mfa-verify-code"]'), 0);
        $submit = $this->waitForElement(WebDriverBy::xpath('//button[@data-track-id="VerifyChallengeSubmitBtn"]'), 0);

        if (!$submit || !$question || !$code) {
            return $this->checkErrors();
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, 'question');

            return true;
        }

        $code->clear();
        $code->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);

        $this->logger->debug("Submit question");
        $submit->click();

        sleep(5);

        $this->saveResponse();

        $error = $this->http->FindSingleNode('//p[@class="choice-alert-banner-text"]/text()');

        if ($error) {
            $this->logger->error("[Error]: {$error}");
            $this->holdSession();
            $this->AskQuestion($question, $error, );
            $this->DebugInfo = $error;
        }

        return $this->getChoice(true)->IsLoggedIn();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        return $this->processQuestion();
    }

    public function Parse()
    {
        $this->markConfigAsSuccess();
        $choice = $this->getChoice();
        $choice->Parse();
        $this->SetBalance($choice->Balance ?? $this->Balance);
        $this->Properties = $choice->Properties;
        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorCode = $choice->ErrorCode;
            $this->ErrorMessage = $choice->ErrorMessage;
            $this->DebugInfo = $choice->DebugInfo;
        }
    }
    public function ParseItineraries()
    {
        return $this->getChoice()->ParseItineraries();
    }
    protected function getChoice($forwardCookies = false)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->choice)) {
            $this->choice = new TAccountCheckerChoice();
            $this->choice->http = new HttpBrowser("none", new CurlDriver());
            $this->choice->http->setProxyParams($this->http->getProxyParams());
            $this->http->brotherBrowser($this->choice->http);
            $this->choice->State = $this->State;
            $this->choice->AccountFields = $this->AccountFields;
            $this->choice->itinerariesMaster = $this->itinerariesMaster;
            $this->choice->HistoryStartDate = $this->HistoryStartDate;
            $this->choice->historyStartDates = $this->historyStartDates;
            $this->choice->http->LogHeaders = $this->http->LogHeaders;
            $this->choice->ParseIts = $this->ParseIts;
            $this->choice->ParsePastIts = $this->ParsePastIts;
            $this->choice->WantHistory = $this->WantHistory;
            $this->choice->WantFiles = $this->WantFiles;
            $this->choice->strictHistoryStartDate = $this->strictHistoryStartDate;
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();
            foreach ($defaultHeaders as $header => $value) {
                $this->choice->http->setDefaultHeader($header, $value);
            }
            $this->choice->globalLogger = $this->globalLogger;
            $this->choice->logger = $this->logger;
            $this->choice->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        if ($forwardCookies) {
            $this->choice->http->removeCookies();
            $cookies = $this->driver->manage()->getCookies();
            $this->logger->debug("set cookies");
            foreach ($cookies as $cookie) {
                $this->choice->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        }

        return $this->choice;
    }

    private function markConfigAsSuccess(): void
    {
        $this->logger->info("marking config {$this->config} as success");
        Cache::getInstance()->set('choice_success_config_' . $this->config, 1, 60 * 60);
    }

    private function markConfigAsBad(): void
    {
        $this->logger->info("marking config {$this->config} as bad");
        Cache::getInstance()->set('choice_bad_config_' . $this->config, 1, 60 * 10);
    }

    private function markConfigAsUnstable(): void
    {
        $this->logger->info("marking config {$this->config} as unstable");
        Cache::getInstance()->set('choice_unstable_config_' . $this->config, 1, 60 * 10);
    }

    private function setConfig()
    {
        $oses = [
            /*
            SeleniumFinderRequest::OS_MAC_M1,
            */
            SeleniumFinderRequest::OS_MAC,
            /*
            SeleniumFinderRequest::OS_WINDOWS,
            SeleniumFinderRequest::OS_LINUX
            */
        ];

        foreach ($oses as $os) {
            foreach (self::CONFIGS as $configName => $config) {
                $configNameFull = $configName . '-' . $os;
                $this->configsWithOs[$configNameFull] = [
                    'browser-family'  => self::CONFIGS[$configName]['browser-family'],
                    'browser-version' => self::CONFIGS[$configName]['browser-version'],
                    'os'              => $os,
                ];
            }
        }
        
        $configs = $this->configsWithOs;

        $successConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('choice_success_config_' . $key) === 1;
        });

        $badConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('choice_bad_config_' . $key) === 1;
        });

        $unstableConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('choice_unstable_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys($configs), function (string $key) use ($successConfigs, $badConfigs, $unstableConfigs) {
            return !in_array($key, array_merge($successConfigs, $badConfigs, $unstableConfigs));
        });

        foreach(array_keys($badConfigs) as $key) {
            if (isset($successConfigs[$key])) {
                unset($successConfigs[$key]);
            }
        }
        
        foreach(array_keys($unstableConfigs) as $key) {
            if (isset($successConfigs[$key])) {
                unset($successConfigs[$key]);
            }
        }
        
        $this->logger->info("found " . count($successConfigs) . " success configs");
        $this->logger->info("found " . count($badConfigs) . " bad configs");
        $this->logger->info("found " . count($unstableConfigs) . " unstable configs");
        $this->logger->info("found " . count($neutralConfigs) . " neutral configs");

        if (count($successConfigs) > 0) {
            $this->logger->info('selecting config from success configs');
            $this->config = $successConfigs[array_rand($successConfigs)];
        } elseif (count($neutralConfigs) > 0) {
            $this->logger->info('selecting config from neutral configs');
            $this->config = $neutralConfigs[array_rand($neutralConfigs)];
        } elseif (count($unstableConfigs) > 0) {
            $this->logger->info('selecting config from unstable configs');
            $this->config = $unstableConfigs[array_rand($unstableConfigs)];
        } else {
            $this->logger->info('selecting config from all configs');
            $keys = array_keys($configs);
            $this->config = $keys[array_rand($keys)];
        }

        $this->logger->info('selected config ' . $this->config);
        $this->logger->debug('[ALL CONFIGS]');
        foreach($this->configsWithOs as $configName => $config) {
            $this->logger->debug($configName);
        }

        if (count($successConfigs) > 0) {
            $this->logger->debug('[SUCCESS CONFIGS]');
            foreach($successConfigs as $config) {
                $this->logger->debug($config);
            }
        }

        if (count($badConfigs) > 0) {
            $this->logger->debug('[BAD CONFIGS]');
            foreach($badConfigs as $config) {
                $this->logger->debug($config);
            }    
        }

        if (count($unstableConfigs) > 0) {
            $this->logger->debug('[UNSTABLE CONFIGS]');
            foreach($unstableConfigs as $config) {
                $this->logger->debug($config);
            }    
        }

        if (count($neutralConfigs) > 0) {
            $this->logger->debug('[NEUTRAL CONFIGS]');
            foreach($neutralConfigs as $config) {
                $this->logger->debug($config);
            }    
        }
    }
    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        
        $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));

        // retries
        if (
            ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            && $this->http->FindPreg("/(?:<html>Banned: Detecting too many failed attempts from your IP\. Access is denied until the ban expires\.<\/html>|<H1>Access Denied<\/H1>)/")
        ) {
            /*
            $this->markConfigAsBad();
            */
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // It's not you - it's us! choicehotels.com is temporarily unavailable.
        if ($this->http->FindSingleNode('//p[contains(text(), "It\'s not you - it\'s us! choicehotels.com is temporarily unavailable.")]')) {
            throw new CheckException("It's not you - it's us! choicehotels.com is temporarily unavailable. Please try again in a few minutes. Thank you, and our apologies for the delay.", ACCOUNT_PROVIDER_ERROR);
        }

        // Service Unavailable - Zero size object
        if ($this->http->FindSingleNode("
                //h1[contains(text(), 'Service Unavailable - Zero size object')]
                | //h1[contains(text(), 'Internal Server Error - Read')]
                | //h1[contains(text(), '504 Gateway Time-out')]
                | //title[contains(text(), '502 Bad Gateway')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
        ) {
            $this->markConfigAsBad();
            throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            (
                $this->http->FindSingleNode('//button[contains(@class, "btn-login")]')
                || $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')    
            ) && !(
                $this->http->FindSingleNode('//div[@class="error-message-body"]/p')
                || $this->http->FindSingleNode('//button[@type="submit" and span[contains(text(), "Send Code")]]')
                || $this->http->FindSingleNode('//p[@class="mfa-explanation" and p]')
            )
        ) {
            $this->logger->debug('looks like a block');
            $this->markConfigAsBad();
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindPreg('/var proxied = window.XMLHttpRequest.prototype.send;/')
            || (
                !$this->http->FindSingleNode('//button[contains(@class, "btn-login")]')
                && $this->http->FindSingleNode('(//button[@id="header-sign-in-button"])[1]')
            )
        ) {
            $this->logger->debug('looks like a site hang');
            $this->markConfigAsUnstable();
            throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}