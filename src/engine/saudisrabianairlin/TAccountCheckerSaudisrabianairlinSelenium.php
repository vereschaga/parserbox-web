<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSaudisrabianairlinSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const WAIT_TIMEOUT = 10;
    private const CONFIGS = [
        /*
        'firefox-100' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_100,
        ],
        'chrome-100' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_100,
        ],
        'chromium-80' => [
            'agent'           => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROMIUM,
            'browser-version' => SeleniumFinderRequest::CHROMIUM_80,
        ],
        'chrome-84' => [
            'agent'           => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_84,
        ],
        */
        'chrome-95' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_95,
        ],
        /*
        'puppeteer-103' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER,
            'browser-version' => SeleniumFinderRequest::CHROME_PUPPETEER_103,
        ],
        'firefox-84' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_84,
        ],
        'chrome-94-mac' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_94,
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

    private $config;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();

        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->FilterHTML = false;

        $this->setConfig();
        /*
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
        */

        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->seleniumRequest->request(
            self::CONFIGS[$this->config]['browser-family'],
            self::CONFIGS[$this->config]['browser-version']
        );

        if ($this->attempt !== 0) {
            $this->http->setProxy($this->proxyUK());
        }

        $this->usePacFile(false);
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.saudia.com/en-US/loyalty/overview', [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $this->http->GetURL('https://www.saudia.com');
        $this->waitForElement(WebDriverBy::xpath('//button[@id="login-btn"] | //iframe[@id="main-iframe"]'), self::WAIT_TIMEOUT * 2);

        $this->saveResponse();

        /*
        if ($this->http->FindSingleNode('//iframe[@id="main-iframe"]')) {
            $this->markConfigAsBadOrSuccess(false);
        } else {
            $this->sendNotification('refs #24888 saudianairlin - success config was found // IZ');
            $this->markConfigAsBadOrSuccess(true);
        }
        */

        $this->incapsulaWorkaround(true);

        $loginButton = $this->waitForElement(WebDriverBy::xpath('//button[@id="login-btn"]'), 0);

        if (!$loginButton) {
            return $this->checkErrors();
        }

        $loginButton->click();

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="alfursanId"]'), self::WAIT_TIMEOUT);
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password"]'), 0);
        $submit = $this->waitForElement(WebDriverBy::xpath('//button[@type="submit"]'), 0);

        if (!$login || !$password || !$submit) {
            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);
        $submit->click();

        return true;
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->processQuestion()) {
            return false;
        }

        if (
            $message = $this->http->FindSingleNode('//span[@class="warning-text"]')
        ) {
            $this->logger->error($message);

            if (strstr($message, 'Incorrect Login ID or password')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.saudia.com/en-US/loyalty/overview');
        $this->waitForElement(WebDriverBy::xpath('//span[@class="member-id" and text() and not(text()="")]'), self::WAIT_TIMEOUT * 6);
        $this->saveResponse();
        $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[@class="member-id-wrapper"]/span[text() and not(text()="")]')));

        // ALFURSAN Number
        $this->SetProperty("Number", $this->http->FindSingleNode('//span[@class="member-id" and text() and not(text()="")]'));

        // Tier Miles / Tier Credits
        $this->SetProperty("TierMiles", $this->http->FindSingleNode('//div[contains(@class, "tier-miles-credits")]/h3/span[not(@class) and text() and not(text()="")]'));

        $memberSince = $this->http->FindSingleNode('//div[@class="member-since" and span[contains(text(), "Member since")]]/span[@class="member-text"]');

        if ($memberSince) {
            // Member Since
            $date = DateTime::createFromFormat("m/y", $memberSince);
            $this->SetProperty('MemberSince', $date->getTimestamp());
        }

        $tierExpirationDate = $this->http->FindSingleNode('//div[@class="member-since" and span[contains(text(), "Tier expiry date")]]/span[@class="member-text"]');

        if ($memberSince) {
            // Tier expiry date
            $date = DateTime::createFromFormat("d M, Y", $tierExpirationDate);
            $this->SetProperty('TierExpiryDate', $date->getTimestamp());
        }

        $this->logger->debug('clicking to flights tab element');
        $this->driver->executeScript("document.getElementById('mat-tab-label-1-1').click();");
        sleep(1);
        $this->saveResponse();
        $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));

        // Expriting Balance
        $this->SetProperty('PointsToExpire', PriceHelper::parse($this->http->FindSingleNode('//span[contains(text, "Miles") and not(contains(text(), "balance")]', null, true, '/[\d,]+/i')));
        $expDateRaw = $this->http->FindSingleNode('//span[contains(text(), "Will expire on")]/span');
        $date = DateTime::createFromFormat("d M, Y", $expDateRaw);
        // Expiration date
        $this->SetExpirationDate($date->getTimestamp());

        // Counters / Flights
        $this->SetProperty('Counters', $this->http->FindSingleNode('//div[contains(@class, "tier-miles-credits")]/h3/span[not(@class) and text() and not(text()="")]'));

        // Status
        $this->SetProperty("MemberType", beautifulName($this->http->FindSingleNode('//div[@class="tier"]//span[@class="member-text" and text() and not(text()="")]')));

        // Balance - Reward Miles Balance
        $this->SetBalance(PriceHelper::parse($this->http->FindSingleNode('//div[@class="miles-card-title"]/h3[text() and not(text()="")]', null, true, '/[\d,]+/i')));

        $this->http->GetURL('https://www.saudia.com/en-US/loyalty/family-program');
        $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "no-data-link-text")]'), self::WAIT_TIMEOUT * 6);
        $this->saveResponse();
        $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));

        if (!$this->http->FindSingleNode('//span[contains(@class, "no-data-link-text")]')) {
            $this->sendNotification('refs #24888 saudisrabianairlin - need to check family program // IZ');
        }
    }

    public function ProcessStep($step)
    {
        return $this->processQuestion();
    }

    public function processQuestion()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "To keep your account secure, a verification code will be sent whenever you log in from an unknown device.")]'), 0)
        ) {
            $this->waitForElement(WebDriverBy::xpath('//button[@type="submit"]'), 0)->click();
            $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "phone")]/../span[contains(@class, "ng-star-inserted")]'), self::WAIT_TIMEOUT);
        }

        $this->saveResponse();
        $question = $this->http->FindSingleNode('//span[contains(@class, "phone")]/../span[contains(@class, "ng-star-inserted")]');

        if (!$question) {
            $this->logger->notice("question not found");

            return false;
        }

        $questionInput = $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="authenticationOTP"]'), 0);

        if (!$questionInput) {
            $this->logger->error("question input not found");

            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return true;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $questionInput->clear();
        $questionInput->sendKeys($answer);
        $this->logger->debug("ready to click");
        $this->saveResponse();
        $aceptar2fa = $this->waitForElement(WebDriverBy::xpath('//button[@type="submit"]'), 0);
        $this->saveResponse();

        if (!$aceptar2fa) {
            $this->logger->error("btn not found");

            return false;
        }

        $this->logger->debug("clicking next");
        $aceptar2fa->click();

        sleep(5);
        $this->saveResponse();

        if ($error = $this->waitForElement(WebDriverBy::xpath('//div[@class="alfursan-login-body"]//div[contains(@class, "error-msg")]'), 5)) {
            $message = $error->getText();
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Wrong code entered')) {
                $this->holdSession();
                $this->AskQuestion($question, $message, "Question");
            }

            $this->DebugInfo = $message;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    private function markConfigAsBadOrSuccess($success = true): void
    {
        if ($success) {
            $this->logger->info("marking config {$this->config} as successful");
            Cache::getInstance()->set('saudisrabianairlin_config_' . $this->config, 1, 60 * 60);
        } else {
            $this->logger->info("marking config {$this->config} as bad");
            Cache::getInstance()->set('saudisrabianairlin_config_' . $this->config, 0, 60 * 60);
        }
    }

    private function setConfig()
    {
        $configs = self::CONFIGS;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            unset($configs['chrome-94-mac']);
        }

        /*
        $successfulConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('saudisrabianairlin_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('saudisrabianairlin_config_' . $key) !== 0;
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
        */

        $this->config = array_rand($configs);

        $this->logger->info("selected config $this->config");
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $el = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Sign Out"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($el) {
            return true;
        }

        /*
        if ($this->http->FindSingleNode('(//button[@aria-label="Sign Out"])[1]')) {
            return true;
        }
        */

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//span[contains(text(), "Request Blocked")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function incapsulaWorkaround($retry = false)
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")
            || $this->http->FindPreg("/<head>\s*<META NAME=\"robots\" CONTENT=\"noindex,nofollow\">\s*<script src=\"\/_Incapsula_Resource\?SWJIYLWA=[^\"]+\">\s*<\/script>\s*<body>/")
        ) {
            if ($retry) {
                throw new CheckRetryNeededException(3, 1, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
    }
}
