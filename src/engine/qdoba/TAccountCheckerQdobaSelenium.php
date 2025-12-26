<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerQdobaSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
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
        ],*/
        'firefox-84' => [
            'agent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family' => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_84,
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
        /*$resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($resolution);*/

        if (self::CONFIGS[$this->config]['browser-family'] === SeleniumFinderRequest::BROWSER_FIREFOX
            || self::CONFIGS[$this->config]['browser-family'] === SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT) {
            $request = FingerprintRequest::firefox();
            $this->setKeepProfile(true);
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
        $this->KeepState = true;
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
            return Cache::getInstance()->get('qdoba_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('qdoba_config_' . $key) !== 0;
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
            Cache::getInstance()->set('qdoba_config_' . $this->config, 1, 60 * 60);
        } else {
            $this->logger->info("marking config {$this->config} as bad");
            Cache::getInstance()->set('qdoba_config_' . $this->config, 0, 60 * 60);
        }
    }

    /*
    public function IsLoggedIn()
    {
        $this->http->GetURL('https://order.qdoba.com/order/rewards');

        $isLoggedIn = $this->waitForElement(WebDriverBy::xpath("//h1[contains(@class,'recent__header__greeting')]"),
            10);
        $this->saveResponse();
        if ($isLoggedIn) {
            return true;
        }
        return false;
    }
    */

    public function LoadLoginForm()
    {

        $this->http->GetURL('https://order.qdoba.com/order/rewards');

        $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] 
            | //div[@id = 'turnstile-wrapper']//iframe 
            | //div[contains(@class, 'cf-turnstile-wrapper')] 
            | //button[span[contains(text(),'Log In')]]
            | //div[@class='px-captcha-error-message']"), 10);
        $this->saveResponse();

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            sleep(5);
        }
        $this->saveResponse();

        if ($this->http->FindSingleNode("//span[contains(text(), 'Your connection was interrupted')]")) {
            throw new CheckRetryNeededException(3, 0);
        }

        $this->http->GetURL('https://nomnom-prod-migration.qdoba.com/api/profiles/login?redirectUri=https://order.qdoba.com/oauth/callback');
        $this->waitForElement(WebDriverBy::xpath("//form[contains(@method,'POST')]//input[@id = 'username']"), 10);

        $loginInput = $this->waitForElement(WebDriverBy::xpath("//input[@id='username']"), 0);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath("//input[@id='password']"), 0);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[@type='submit']"), 0);

        if ($agreeBtn = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'I Agree') or contains(text(), 'OK, I understand')]"),
            0)) {
            $agreeBtn->click();
        }
        $this->saveResponse();
        if (!$loginInput || !$passwordInput || !$button) {
            return false;
        }
        $loginInput->sendKeys($this->AccountFields['Login']);

        // canes
        if (empty($this->AccountFields['Pass'])) {
            throw new CheckException("The username could not be found or the password you entered was incorrect. Please try again.",
                ACCOUNT_PROVIDER_ERROR);
        }

        $passwordInput->sendKeys($this->AccountFields['Pass']);

        $this->logger->debug("click by btn");
        $button->click();
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
        $this->waitForElement(WebDriverBy::xpath("
        //h1[contains(@class,'recent__header__greeting')]
        | //span[contains(@class,'ulp-input-error-message') and normalize-space()!='']"),
            10);
        $this->saveResponse();

        $isLoggedIn = $this->waitForElement(WebDriverBy::xpath("//h1[contains(@class,'recent__header__greeting')]"),
            0);
        $this->saveResponse();
        if ($isLoggedIn) {
            $this->markConfigAsBadOrSuccess(true);
            return true;
        }

        $error = $this->waitForElement(WebDriverBy::xpath("//span[contains(@class,'ulp-input-error-message') and normalize-space()!='']"),
            0);
        if ($error) {
            if (stristr($error->getText(), 'Invalid credentials provided.')
                || stristr($error->getText(), 'Your email address and/or password are incorrect. Please try again.')) {
                throw new CheckException($error->getText(), ACCOUNT_INVALID_PASSWORD);
            }
        }
        $error = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(),'ve sent an email with your code to')]"),
            0);
        if ($error) {
            if ($this->processSecurityCheckpoint()) {
                return false;
            }
        }
        return $this->checkErrors();
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);

        $q = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(),'ve sent an email with your code to')]"), 0);

        if (!$q) {
            $this->logger->error("Question not found");

            return false;
        }

        $question = $q->getText();

        if (!isset($this->Answers[$question])) {
            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            $this->sendNotification('check 2fa // MI');
            $this->holdSession();
            $this->AskQuestion($question, null, 'Question');

            return true;
        }
        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $codeInput = $this->waitForElement(WebDriverBy::xpath("//input[@name='code']"), 0);
        $this->saveResponse();

        if (!isset($codeInput)) {
            $this->saveResponse();

            return false;
        }

        $codeInput->clear();
        $codeInput->sendKeys($answer);

        $btn = $this->waitForElement(WebDriverBy::xpath("//button[@name='action']"), 0);

        if (!$btn) {
            return false;
        }

        $btn->click();
        sleep(1);

        $this->waitForElement(WebDriverBy::xpath("
            //h1[contains(@class,'recent__header__greeting')]
            | //span[contains(@class,'ulp-input-error-message') and normalize-space()!='']
        "), 7);
        $this->saveResponse();
        $error = $this->waitForElement(WebDriverBy::xpath("//span[contains(@class,'ulp-input-error-message') and normalize-space()!='']"),
            0);
        if ($error) {
            if (stristr($error->getText(), 'The code you entered is invalid')) {
                $this->logger->error("[Error]: {$error->getText()}");
                $this->holdSession();

                $this->AskQuestion($question, $error->getText(), 'Question');

                return false;
            }
        }

        return true;
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


    public function Parse()
    {
        /*$headers = [
            'Accept' => 'application/json, text/plain, * / *',
            'Origin' => 'https://qdoba-prod.us.auth0.com',
            'priority' => 'u=0, i',
        ];
        $data = [];
        $this->http->PostURL("https://qdoba-prod.us.auth0.com/u/login?state=$state", $data, $headers);*/

        // 65/125 points earned toward your next free entrée!
        $balance = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class,'recent__header-point-details')]/p/span"), 0);
        $this->saveResponse();
        $this->SetBalance($this->http->FindPreg('#(\d+)/\d+ points#', false, $balance->getText()));

        // Status
        $status = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class,'recent__header-personalized')]/h1/following-sibling::div/p"), 0);
        if ($status) {
            $this->SetProperty("Tier", $status->getText());
        } elseif ($this->http->FindSingleNode('//p[contains(text(), "toward Gold Status level")]')) {
            $this->SetProperty("Tier", "Foodie");
        }

        // 0/12 visits toward 'Chef' level
        $visits = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class,'recent__header-point-details')]/p[contains(text(),'visits toward')]"),
            0);
        if ($visits) {
            $this->SetProperty("AnnualVisits", $this->http->FindPreg('#(\d+)/\d+ visits#', false, $visits->getText()));
        }


        $this->http->GetURL('https://order.qdoba.com/account/settings');
        $name = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class,'settings__contact-info-filled')]/div/p[@class='name']"),
            10);
        $this->SetProperty("Name", beautifulName($name->getText()));
    }



}
