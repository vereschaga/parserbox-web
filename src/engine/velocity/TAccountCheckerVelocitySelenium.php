<?php


use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerVelocitySelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    use PriceTools;

    /**
     * @var HttpBrowser
     */
    public $browser;
    private $currentItin = 0;
    private $config;
    private const CONFIGS = [
        /*'firefox-100' => [
            'agent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family' => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_100,
        ],
        'chrome-100' => [
            'agent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family' => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_100,
        ],
        'chromium-80' => [
            'agent' => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36",
            'browser-family' => SeleniumFinderRequest::BROWSER_CHROMIUM,
            'browser-version' => SeleniumFinderRequest::CHROMIUM_80,
        ],
        'chrome-84' => [
            'agent' => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36",
            'browser-family' => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_84,
        ],
        'chrome-95' => [
            'agent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family' => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_95,
        ],
        'puppeteer-103' => [
            'agent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family' => SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER,
            'browser-version' => SeleniumFinderRequest::CHROME_PUPPETEER_103,
        ],
        'firefox-84' => [
            'agent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family' => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_84,
        ],*/
        'chrome-84' => [
            'agent' => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36",
            'browser-family' => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_84,
        ],
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();

        /*if ($this->attempt == 0) {
            $this->setProxyBrightData();
        } else {
            $array = ['us', 'ca', 'br', 'cl', 'mx'];
            $country = $array[random_int(0, count($array) - 1)];
            $this->setProxyGoProxies(null, $country);
        }*/

        //$this->http->setRandomUserAgent();
        $this->setConfig();
        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($resolution);

        if (self::CONFIGS[$this->config]['browser-family'] === SeleniumFinderRequest::BROWSER_FIREFOX
            || self::CONFIGS[$this->config]['browser-family'] === SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT) {
            $request = FingerprintRequest::firefox();
        } else {
            $request = FingerprintRequest::chrome();
        }
        $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
        $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if (isset($fingerprint)
            /*&& self::CONFIGS[$this->config]['browser-version'] !== SeleniumFinderRequest::CHROME_94
            && self::CONFIGS[$this->config]['browser-version'] !== SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101*/
        ) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
            $this->http->setUserAgent($fingerprint->getUseragent());
            $this->seleniumOptions->userAgent = $fingerprint->getUseragent();
        } else {
            $this->seleniumOptions->addHideSeleniumExtension = false;
            $this->seleniumOptions->userAgent = null;
        }

        $this->seleniumRequest->request(
            self::CONFIGS[$this->config]['browser-family'],
            self::CONFIGS[$this->config]['browser-version']
        );

        //$selenium->useCache();
        $this->usePacFile(false);
        $this->http->saveScreenshots = true;
    }

    private function setConfig()
    {
        $configs = self::CONFIGS;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            unset($configs['chrome-94-mac']);
        }

        $successfulConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('velocity_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('velocity_config_' . $key) !== 0;
        });

        if (count($successfulConfigs) > 0) {
            $this->config = $successfulConfigs[array_rand($successfulConfigs)];
            $this->logger->info("found " . count($successfulConfigs) . " successful configs");
        } elseif (count($neutralConfigs) > 0) {
            $this->config = $neutralConfigs[array_rand($neutralConfigs)];
            $this->logger->info("found " . count($neutralConfigs) . " neutral configs");
        } else {
            $this->config = array_rand($configs);
        }

        $this->logger->info("selected config $this->config");
    }

    private function markConfigAsBadOrSuccess($success = true): void
    {
        if ($success) {
            $this->logger->info("marking config {$this->config} as successful");
            Cache::getInstance()->set('velocity_config_' . $this->config, 1, 60 * 60);
        } else {
            $this->logger->info("marking config {$this->config} as bad");
            Cache::getInstance()->set('velocity_config_' . $this->config, 0, 60 * 60);
        }
    }

    public function IsLoggedIn()
    {

        return false;
    }



    public function LoadLoginForm()
    {
        try {
            $this->http->GetURL('https://www.velocityfrequentflyer.com/');
            sleep(random_int(1, 4));
            $login = $this->waitForElement(WebDriverBy::xpath('//a[@id="joinNowLink"]/following-sibling::button[@aria-label="Log in"]'),
                15);

            if (!$login) {
                if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                return false;
            }
            $login->click();
            sleep(5);
//                $this->savePageToLogs($selenium);
//                $selenium->http->GetURL('https://auth.velocityfrequentflyer.com/as/authorization.oauth2?client_id=vff_web&redirect_uri=https%3A%2F%2Fwww.velocityfrequentflyer.com%2Fmy-velocity%2Factivity%3Fexplicit_login%3Dweb&response_type=code&scope=openid+vff_web_group_scopes&state=2cda103d695e4eca96e62289f88660f7&code_challenge=aENgrk8P8F81pTF7eySwdnHjkR9F7sLODgrdez28i6o&code_challenge_method=S256&response_mode=query&prompt=login&cctp=velocity%3Ahome%3AvffLoginCta');

        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
        }
        $this->saveResponse();

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name="username"] | //input[@id="username"]'),
            15);
        $pass = $this->waitForElement(WebDriverBy::xpath('//input[@name="password"] | //input[@id="password"]'), 0);
        $remember = $this->waitForElement(WebDriverBy::xpath('//input[@id="rememberUsername"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@name="login"]'), 0);

        if (!$login || !$pass || !$btn || !$remember) {
            return false;
        }

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $login->clear();
        $mover->sendKeys($login, $this->AccountFields['Login'], 30);
        $pass->clear();
        $mover->sendKeys($pass, $this->AccountFields['Pass'], 30);
        $remember->click();
        $this->savePageToLogs($this);
        $btn->click();

        $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(),"Welcome back,")] | //a[@title="Activity"] | //div[contains(@class, "alert-error")]/span[contains(@class, "kc-feedback-text")]'),
            25);
        $loginSuccess = $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(),"Welcome back,")] | //a[@title="Activity"]'),
            0);
        $this->savePageToLogs($this);

        if (!$loginSuccess) {

            if ($this->http->FindSingleNode('//div[contains(text(),"Enter the 6 digit verification code we sent to")]')) {
                return $this->processSecurityCheckpoint();
            }

            if ($message = $this->http->FindSingleNode('
                    //div[contains(@class, "form-error-block")]/span[contains(@class, "kc-feedback-text")] 
                    | //div[contains(@class, "styled-input__error") and contains(@style,"flex;")]')
            ) {
                $this->logger->error("[Error]: {$message}");

                if (
                    stristr($message, "Your Velocity number or password is incorrect.")
                    || stristr($message,
                        "The Velocity Membership number you have entered is associated with a closed account.")
                    || stristr($message, "Your account has been closed because a duplicate account exists")
                    || stristr($message, "Velocity number must be 10 digits long")
                    || stristr($message, "Velocity numbers only contain digits (0–9)")
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    stristr($message,
                        "The Velocity Membership number you have entered is associated with an inactive, suspended or closed account.")
                    || stristr($message, "Your account has been locked.")
                ) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                if (stristr($message, "Error while committing the transaction")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                return false;
            }

            // The details entered do not match
            if ($message = $this->http->FindSingleNode("//div[contains(text(), 'The details entered do not match') or contains(text(), 'The membership number entered is inactive, suspended or closed.')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message = $this->http->FindSingleNode("//span[contains(normalize-space(text()), 'INACTIVE, SUSPENDED OR CLOSED ACCOUNT')]")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            // Unfortunately this request could not be processed
            if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Unfortunately this request could not be processed')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindSingleNode('//p[contains(text(), "Sorry, we\'re having issues with our system.")]')) {
                throw new CheckRetryNeededException(3, 0);
            }

            return false;
        }

        $this->saveResponse();

        if ($this->ParseIts) {
            $this->setProxyMount();
            //$selenium->http->GetURL(' https://accounts.velocityfrequentflyer.com/auth/realms/velocity/protocol/saml/clients/ibe?RelayState=https%3A%2F%2Fbook.virginaustralia.com%2Fdx%2FVADX%2F%23%2Fdashboard%3Fcctp%3Dvald_velocity%3Amy-velocity%26channel%3Dvff-mybookings-browser');
            // https://accounts.velocityfrequentflyer.com/auth/realms/velocity/protocol/saml/clients/ibe?RelayState=https%3A%2F%2Fbook.virginaustralia.com%2Fdx%2FVADX%2F%23%2Fdashboard%3Fchannel%3Dvff-mybookings-browser

            $closeButton = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class,"mv__src-components-Modal-Modal_closeButton")]'), 0);
            if ($closeButton) {
                $closeButton->click();
            }
            $menu = $this->waitForElement(WebDriverBy::xpath('//button[@id="control-login-module-dropdown-id" or @id="pointsButton"]'), 0);
            if ($menu) {
                $menu->click();
                $this->saveResponse();
                $trips = $this->waitForElement(WebDriverBy::xpath('(//a[contains(@href,"auth/realms/velocity/protocol/saml/clients/ibe")])[1]'), 2);
                if ($trips) {
                    $trips->click();
                    sleep(10);
                    $this->saveResponse();
                }
            }
            $cookies = $this->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
        }

        sleep(random_int(3, 5));
        $this->saveResponse();
         return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $loginSuccess = $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(),"Welcome back,")] | //a[@title="Activity"]'),
            10);
        if ($loginSuccess) {
            return true;
        }

        return $this->checkErrors();
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);

        $q = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(),"Enter the 6 digit verification code we sent to")]'), 0);

        if ($q) {
            $question = $q->getText();
        }

        $codeInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "confirmation_code"] | //input[@id = "sms_code"]'), 0);
        $answerInputs = $this->waitForElement(WebDriverBy::xpath('//div[@class="otp-field"]//input[@data-index]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id="sign-on"]'), 0);
        $this->saveResponse();

        if (!isset($question) || (!$codeInput && !$answerInputs) || !$button) {
            return false;
        }
        $this->holdSession();
        if ($question && !isset($this->Answers[$question])) {
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);
        $answerInputs = $this->driver->findElements(WebDriverBy::xpath('//div[@class="otp-field"]//input[@data-index]'));

        $this->logger->debug("entering answer...");
        foreach ($answerInputs as $i => $element) {
            $this->logger->debug("#{$i}: {$answer[$i]}");
            $element->clear();
            $element->sendKeys($answer[$i]);
            $this->saveResponse();
        }
        $this->logger->debug("click button...");
        $button->click();

        $error = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Invalid code. Please try again.')]"), 5);
        $this->saveResponse();
        if ($error) {
            $this->logger->notice("resetting answers");
            $this->AskQuestion($question, $error->getText(), "Question");
            return false;
        }
        $this->logger->debug("success");
        $loginSuccess = $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(),"Welcome back,")] | //a[@title="Activity"]'), 10);
        $this->saveResponse();

        if ($clientSecret = $this->http->FindPreg('/"clientSecret":"(.+?)",/')) {
            $this->logger->debug("clientSecret: $clientSecret");
        }

         return $this->loginSuccessful();
    }

    private function loginSuccessful()
    {
        $data = $this->driver->executeScript("return localStorage.getItem('oidc.user:https://auth.velocityfrequentflyer.com:vff_web');");
        $this->logger->debug("Locale Storage: $data");
        $data = $this->http->JsonLog($data);
        if (isset($data->access_token)) {
            $this->velocity = $this->getVelocity();
            return $this->velocity->loginSuccessful($data->access_token);
        }
        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());


        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == "Question") {
            return $this->processSecurityCheckpoint();
        }

        return false;
    }

    private $velocity;

    protected function getVelocity()
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->velocity)) {
            $this->velocity = new TAccountCheckerVelocity();
            $this->velocity->http = new HttpBrowser("none", new CurlDriver());
            $this->velocity->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->velocity->http);
            $this->velocity->AccountFields = $this->AccountFields;
            $this->velocity->itinerariesMaster = $this->itinerariesMaster;
            $this->velocity->HistoryStartDate = $this->HistoryStartDate;
            $this->velocity->historyStartDates = $this->historyStartDates;
            $this->velocity->http->LogHeaders = $this->http->LogHeaders;
            $this->velocity->ParseIts = $this->ParseIts;
            $this->velocity->ParsePastIts = $this->ParsePastIts;
            $this->velocity->WantHistory = $this->WantHistory;
            $this->velocity->WantFiles = $this->WantFiles;
            $this->velocity->strictHistoryStartDate = $this->strictHistoryStartDate;

            $this->velocity->globalLogger = $this->globalLogger;
            $this->velocity->logger = $this->logger;
            $this->velocity->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->velocity->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->velocity;
    }

    public function Parse()
    {
        $this->velocity = $this->getVelocity();
        $this->velocity->Parse();
        $this->SetBalance($this->velocity->Balance);
        $this->Properties = $this->velocity->Properties;
        $this->ErrorCode = $this->velocity->ErrorCode;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $this->velocity->ErrorMessage;
            $this->DebugInfo = $this->velocity->DebugInfo;
        }

    }

    public function ParseItineraries()
    {
        $this->velocity = $this->getVelocity();
        $this->velocity->ParseItineraries();
        return [];
    }


}
