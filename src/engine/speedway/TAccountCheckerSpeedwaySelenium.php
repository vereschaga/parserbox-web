<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSpeedwaySelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    private const REWARDS_PAGE_URL = 'https://www.speedway.com/account/profile';
    private const XPATH_OF_RESULT = "
        //li[contains(@class, 'account-summary__list__card')]
        | //li[position() > 1]/button[@aria-controls = 'menu-account-desktop']//span[contains(@class, 'header__account-summary__points')]
        | //div[@id = 'validationSummary']/ul/li
        | //p[contains(text(), 'CARD #')]
        | //iframe[contains(@src, '/_Incapsula_Resource?')]
        | //p[contains(text(), 'Please enter the 7-digit verification code sent via')]
        | //p[contains(text(), 'Enter the one time code we sent to')]
        | //li[@class = 'u-hide-for-mobile-nav']//span[@class = 'header__account-summary__points']
        | //div[@class= 'header__account']//span[contains(@class, 'header__account-summary__points')]
    ";
    private const CONFIGS = [
        /*
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
        */
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
        */
        /*
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
    private $curlDrive;
    private $headers;
    private $configsWithOs = [];
    private $config;

    public function InitBrowserOld()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->UseSelenium();
        $this->setConfig();
        /*
        $this->setProxyGoProxies();
        */
        $this->selectProxy($this);

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

        $this->seleniumRequest->request(
            $this->configsWithOs[$this->config]['browser-family'],
            $this->configsWithOs[$this->config]['browser-version']
        );
        /*
        $this->seleniumRequest->setOs($this->configsWithOs[$this->config]['os']);
        */

        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->http->saveScreenshots = true;
        $this->usePacFile(false);
    }

    /**
     * Renamed the method to avoid creating comments, of which there are already many. Only for debug purposes.
     *
     * @return void
     */
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->KeepState = true;
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
        $chosenResolution = $resolutions[array_rand($resolutions)];
        $this->logger->info('chosenResolution:');
        $this->logger->info(var_export($chosenResolution, true));
        $this->setScreenResolution($chosenResolution);
        */
        $this->useChromium();
        /*
        if ($this->attempt > 1) {
            $this->setProxyBrightData(null, 'static', 'us');
        } else {
            $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
        }
        */
        /*
        $this->setProxyMount();
        */
        /*
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        */

        /*
        $this->setProxyBrightData(null, 'static', 'us');
        */
        /*
        $this->setProxyNetNut();
        */
        $this->selectProxy($this);
        /*
        $this->useFirefoxPlaywright();
        */
        /*
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        */
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->http->saveScreenshots = true;
        $this->disableImages();
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token'])) {
            return false;
        }

        $this->openCurlDrive();

        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'Authorization'   => 'Bearer ' . $this->State['token'],
            'Origin'          => 'https://www.speedway.com',
            'Referer'         => 'https://www.speedway.com/',
            'X-SEI-PLATFORM'  => 'web_spw',
            'X-SEI-DEVICE-ID' => 'dbd9109f-036f-4bdf-b889-2bf85a2b57c8',
            'X-SEI-VERSION'   => '7.0',
        ];

        $this->curlDrive->RetryCount = 0;
        $this->curlDrive->GetURL('https://apis.7-eleven.com/v5/accounts?profile=full', $headers);
        $this->curlDrive->RetryCount = 2;
        $userInfo = $this->curlDrive->JsonLog();

        if (
            isset($userInfo->mobile_number)
            && $userInfo->mobile_number == $this->AccountFields['Login']
        ) {
            $this->headers = $headers;

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // regexp from attribute 'data-val-regex-pattern'
        if (!$this->http->FindPreg('/^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}/', false, $this->AccountFields['Login'])) {
            throw new CheckException("Please enter a valid mobile phone number.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
//        $this->http->GetURL(self::REWARDS_PAGE_URL);
        $this->http->GetURL("https://www.speedway.com/speedy-rewards");

        if ($navLogin = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Log In')]"), 10)) {
            $this->driver->executeScript('var windowOpen = window.open; window.open = function(url){windowOpen(url, \'_self\');}');
            $navLogin->click();
        }

        $login = $this->waitForElement(WebDriverBy::xpath("//input[contains(@id, 'form_phone-')]"), 10);

        if (empty($login)) {
            $this->logger->error('something went wrong');
            $this->saveResponse();

            if ($this->http->FindSingleNode("//iframe[@id='px-captcha-modal']")) {
                /*
                $this->sendNotification('refs #24875 - need to check "press and hold" captcha // IZ');
                */
            }

            // incapsula workaround
            if ($this->http->FindSingleNode("
                    //iframe[contains(@src, '/_Incapsula_Resource?')]/@src
                    | //span[contains(text(), 'This site can’t be reached')]
                    | //iframe[@id='px-captcha-modal']
                ")
            ) {
                throw new CheckRetryNeededException(3, 3);
            }

            return $this->checkErrors();
        }

        $login->clear();
        $login->sendKeys($this->AccountFields['Login']);

        if ($submit = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'submit']"), 5)) {
            $submit->click();
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//span[@class="PageText" and contains(text(), "experiencing difficulties")]', null, false)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error has occurred on the server
        if ($message = $this->http->FindPreg("/(An error has occurred on the server\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Something's gone wrong on our end.
        if ($message = $this->http->FindPreg("/(Something\'s gone wrong on our end\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Speedway.com Maintenance
        if ($message = $this->http->FindSingleNode("//title[contains(text(), 'Speedway.com Maintenance')]")) {
            throw new CheckException("Speedway.com is under maintenance. Please try to check again later.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Your login request failed. Contact customer support for more information")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->pressAndHoldWorkAround($this);

        return false;
    }

    public function Login()
    {
        $status = $this->waitForElement(WebDriverBy::xpath(self::XPATH_OF_RESULT . " | //button[@id = 'btn-close-tutorial']"), 30);
        $this->saveResponse();
        $this->pressAndHoldWorkAround($this);

        if ($status && ($message = $this->http->FindPreg('/(The server could not process your request\.|An unexpected error has occurred\. Please check your internet connection and try again\.)/', false, $status->getText()))) {
            throw new CheckRetryNeededException(3, 3, $message);
        }

        if ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Please enter the 7-digit verification code sent via')] | //p[contains(text(), 'Enter the one time code we sent to')]"), 0)) {
            if ($this->parseQuestion()) {
                return false;
            }
        }

        if ($twoFa = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'btn-close-tutorial']"), 0)) {
            $twoFa->click();
            $this->waitForElement(WebDriverBy::xpath("//input[@id = 'device-verificationcode']"), 5);
            $this->saveResponse();

            if ($this->parseQuestion()) {
                return false;
            }
        }

        if ($this->loginSuccessful()) {
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

            if ($this->http->currentUrl() == "https://www.speedway.com/") {
                $this->http->GetURL(self::REWARDS_PAGE_URL);
                $this->waitForElement(WebDriverBy::xpath("//li[contains(@class, 'account-summary__list__card')] | //div[contains(text(), 'Your Speedy Rewards card #') and contains(text(), ' has been disabled.')]"), 20);
                $this->saveResponse();
            }

            return true;
        }

        if (
            $this->waitForElement(WebDriverBy::xpath("//div[@id = 'loading']"), 0)
            || $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")
        ) {
            throw new CheckRetryNeededException(4, 0);
        }

        if ($status) {
            $message = $status->getText();
            $this->logger->error("[Error]: {}");

            if (strstr($message, 'Please enter a valid 10-digit Mobile Phone Number.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'An error occurred sending your verification code.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        //# Speedy Rewards card has been reported as lost/stolen
        if ($message = $this->http->FindPreg("/Your Speedy Rewards card # \d* has been reported as lost\/stolen/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Login services are currently down. Please try again later.
        if ($message = $this->http->FindPreg("/(Login services are currently down\.\s*Please try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // PIN must be a 4-8 digit number.
        /*
        if (!is_numeric($this->AccountFields['Pass']) && !$this->http->FindSingleNode("//span[contains(@class, 'card_number')]", null, true, "/Card\s*#\s*([^<]+)/ims")) {
            throw new CheckException("PIN must be a 4-8 digit number.", ACCOUNT_INVALID_PASSWORD);
        }
        */
        // Login failed. Please check your email and passcode and try again.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Login failed. Please check your email and passcode and try again.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been locked out as a result of multiple failed login attempts.
        if ($message = $this->http->FindSingleNode("
                //li[contains(text(), 'Your account has been locked out as a result of multiple failed login attempts')]
                | //div[@id = 'panLoginError' and contains(text(), 'Your account has been locked out as a result of multiple failed login attempts.')]
                | //p[contains(text(), 'Your account has been locked ')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // hard code (error is empty on provider website)
        if (in_array($this->AccountFields['Login'], ['dbookbinder@gmail.com', 'liangjy10@gmail.com'])
            && strlen($this->AccountFields['Pass']) == 4
            && !$this->http->FindSingleNode("//span[contains(@class, 'card_number')]")) {
            throw new CheckException("PIN must be a 4-8 digit number.", ACCOUNT_INVALID_PASSWORD);
        }

        // hard code - error box does not contains any text (AccountID: 2318469)
        if ($this->AccountFields['Login'] == 'corey.bregman@yahoo.com'
            && !$this->http->FindSingleNode("//span[contains(@class, 'card_number')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // login successful
        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        //p[contains(text(), 'Enter the one time code we sent to')]
        $question = $this->http->FindSingleNode('//p[
            contains(text(), "We don’t recognize your browser. Please enter the")
            or contains(text(), "Please enter the 7-digit verification code sent via")
            or contains(text(), "nter the one time code we sent to")
        ]', null, true, "/(?:Please enter the 7-digit verification code.+|Enter the one time code we sent to.+)/i");

        if (!$question) {
            return false;
        }

        $this->holdSession();
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->selectProxy($this);

        if (strstr($this->http->currentUrl(), 'data:text/plain;charset=utf-8;text,hsbc+%7C+')) {
            if ($this->isNewSession()) {
                $this->logger->notice("new session");
            }

            return $this->LoadLoginForm() && $this->Login();
        }

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $answerInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'device-verificationcode'] | //input[@id = 'VerificationCode']"), 5);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//pre[contains(text(), "speedway") and contains(text(), "awardwallet")]')) {
            throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($answerInput) {
            $answerInput->clear();
            $answerInput->sendKeys($answer);
        } else {
            $answerInputs = $this->driver->findElements(WebDriverBy::xpath('//input[contains(@id, "verification_pin-")]'));

            if (!$answerInputs) {
                return false;
            }

            $this->logger->debug("entering answer...");

            foreach ($answerInputs as $i => $element) {
                $this->logger->debug("#{$i}: {$answer[$i]}");
                $answerInputs[$i]->clear();
                $answerInputs[$i]->sendKeys($answer[$i]);
                $this->saveResponse();
                sleep(rand(0, 2));
            }
        }

        sleep(1);
        $btn = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Verify') and not(@disabled)] | //input[@aria-label=\"Continue\"]"), 10);
        $this->saveResponse();

        $this->pressAndHoldWorkAround($this);

        sleep(1);
        $btn = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Verify') and not(@disabled)] | //input[@aria-label=\"Continue\"]"), 10);
        $this->saveResponse();

        if (!$btn) {
            return false;
        }

        $this->injectCatchingScript();
        $btn->click();

        if ($this->waitForElement(WebDriverBy::xpath("//p[@id='toast-text' and @aria-label='Login Successful!']"), 10)) {
            sleep(5);
            $this->saveResponse();

            /*
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
            $this->waitForElement(WebDriverBy::xpath(self::XPATH_OF_RESULT), 30);
            $this->saveResponse();
            */

            if ($this->loginSuccessful()) {
                return true;
            }
        }
        $this->saveResponse();

        try {
            $result = $this->waitForElement(WebDriverBy::xpath(self::XPATH_OF_RESULT . " | //button[@id = 'auth-device-continue'] | //input[@name= 'Email'] | //p[@id='unabletoverifyaccount'] | //p[contains(text(), 'Personalize your experience')]"), 15);
            $this->saveResponse();

            if ($message = $this->http->FindSingleNode('//p[@id="unabletoverifyaccount"]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode('//div[@id="error_verification_pin"]')) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $message, "Question");
            }

            if ($message = $this->http->FindSingleNode('//p[contains(text(), "Personalize your experience")]')) {
                $this->throwProfileUpdateMessageException();
            }

            // refs #22894
            if (
                ($email = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'Email']"), 0))
                && ($nextButton = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Next')]"), 0))
            ) {
                // for field 'Last Name'
                if (empty($this->AccountFields['Login2']) || empty($this->AccountFields['Pass'])) {
                    throw new CheckException("To update this Speedway (Speedy Rewards) account you need to fill in the 'Email' and 'Passcode' fields. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR); /*review*/
                }

                $email->sendKeys($this->AccountFields['Login2']);
                $nextButton->click();

                $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'Passcode']"), 10);
                $nextPassButton = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Next')]"), 0);
                $this->saveResponse();

                if (!$pass || !$nextPassButton) {
                    return false;
                }

                $pass->sendKeys($this->AccountFields['Pass']);
                $nextPassButton->click();

                $result = $this->waitForElement(WebDriverBy::xpath(self::XPATH_OF_RESULT . " | //button[@id = 'auth-device-continue']"), 15);
                $this->saveResponse();
            }

            if (
                $result
                && in_array($result->getText(), [
                    'The verification code you entered is incorrect.',
                    'The verification code you entered has expired.',
                ])
            ) {
                $error = $result->getText();
                $this->holdSession();
                //            $answer->clear();
                $this->AskQuestion($this->Question, $error, "Question");

                return false;
            } elseif ($result && $result->getText() == 'An unexpected error has occurred. Please check your internet connection and try again.') {
                throw new CheckException($result->getText(), ACCOUNT_PROVIDER_ERROR);
            } elseif ($btn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'auth-device-continue']"), 0)) {
                $this->injectCatchingScript();
                $btn->click();
                $this->waitForElement(WebDriverBy::xpath(self::XPATH_OF_RESULT), 30);

                $this->saveResponse();
            }
        } catch (WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->DebugInfo = "WebDriverCurlException";

            throw new CheckRetryNeededException(2, 0); // "Curl error thrown for http GET to /session" workaround
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "For additional verification")]') && $this->parseQuestion()) { // additional verification via email
            return false;
        }

        /*
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->waitForElement(WebDriverBy::xpath(self::XPATH_OF_RESULT), 30);
        $this->saveResponse();
        */

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $userInfo = $this->curlDrive->JsonLog();

        // Balance - Points
        $this->SetBalance($userInfo->rewards_points);
        // Name
        $this->SetProperty("Name", beautifulName($userInfo->first_name . ' ' . $userInfo->last_name));
        // Card #
        $this->SetProperty('Number', $this->http->FindPreg('/<LoyAcct>(\d+)<\/LoyAcct>/', false, base64_decode($userInfo->barcode)));

        $this->curlDrive->GetURL('https://apis.7-eleven.com/v5/transactions', $this->headers);
        $transactionsInfo = $this->curlDrive->JsonLog();
        $nodes = $transactionsInfo->data ?? [];

        if (count($nodes) > 0) {
            $this->sendNotification('refs #24889 speedway - need to check transactions');
        }

        return;

        // Replace Card
        // Your Speedy Rewards card #... has been disabled.
        // AccountID: 4044189
        if ($this->http->FindSingleNode("//p[contains(text(), 'CARD #')] | //div[@class = 'toast-message' and contains(text(), 'Your Speedy Rewards card #') and contains(text(), ' has been disabled.')]")) {//todo
            // Balance - Points
            $this->SetBalance($this->http->FindSingleNode("//div[@class= 'header__account']//span[contains(@class, 'header__account-summary__points')]"));
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class= 'header__account']//span[contains(@class, 'header__account-summary__name')]")));

            return;
        }

        // Card #
        $this->SetProperty("Number", $this->http->FindSingleNode("//p[contains(text(), 'CARD #')]", null, true, "/Card\s*#\s*([^<]+)/ims"));
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "account-dashboard__member")]/p/strong'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[span[contains(text(), "Name")]]/following-sibling::div[1]/span')));

        // Expiration Date   // refs #4416
        /*
        $this->http->GetURL("https://www.speedway.com/MyAccount/Transactions");
        */
        $this->http->GetURL('https://www.speedway.com/account/transactions');

        try {
            $this->waitForElement(WebDriverBy::xpath("//table[contains(@class, 'table-striped')]//tr[td]"), 5);
            $this->saveResponse();
        } catch (WebDriverCurlException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
        }
        $nodes = $this->http->XPath->query("//div[@id = 'transactionList']/ul/li[not(contains(@class, 'header'))]");
        $this->logger->debug("Total {$nodes->length} nodes found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $date = $this->http->FindSingleNode('span[1]', $nodes->item($i));
            $points = $this->http->FindSingleNode('span[4]/text()', $nodes->item($i));

            if (($exp = strtotime($date)) && $points > 0) {
                $this->logger->debug("Node # " . $i);
                // Expiration Date
                $this->SetExpirationDate(strtotime("+9 month", $exp));
                // Last Activity
                $this->SetProperty("LastActivity", $date);

                break;
            }// if ($exp = strtotime($exp))
        }// for ($i = 0; $i < $nodes->length; $i++)
    }

    protected function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    protected function pressAndHoldWorkAround(
        $selenium,
        $captchaFrameXpath = '//iframe[@id="px-captcha-modal" or @title="Human verification challenge" and not(@style="display: none;")]',
        $captchaElemXpath = '//div[@id = "px-captcha"] | //p[contains(text(), "Press")]',
        $xOffset = 300,
        $yOffset = 40
    ) {
        $this->logger->notice(__METHOD__);

        $this->saveResponse();

        if ($this->http->FindSingleNode($captchaFrameXpath) === null) {
            $this->logger->debug('captcha frame not found');
            $this->markConfigAsSuccess();

            return false;
        }

        $this->logger->debug("xOffset: {$xOffset} / yOffset: {$yOffset}");

        $captchaFrame = $selenium->waitForElement(WebDriverBy::xpath($captchaFrameXpath), 5);
        $selenium->driver->switchTo()->frame($captchaFrame);
        $this->savePageToLogs($selenium);

        $captchaElem = $selenium->waitForElement(WebDriverBy::xpath($captchaElemXpath), 0);
        $this->savePageToLogs($selenium);

        if (!$captchaElem) {
            $this->logger->debug('captcha element not found');
            $this->markConfigAsSuccess();

            return false;
        }

        $this->markConfigAsBad();

        throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
    }

    private function markConfigAsSuccess(): void
    {
        $this->logger->info("marking config {$this->config} as success");
        Cache::getInstance()->set('speedway_success_config_' . $this->config, 1, 60 * 60);
        /*
        $this->sendNotification('refs #24875 - success config was found // IZ');
        */
    }

    private function markConfigAsBad(): void
    {
        $this->logger->info("marking config {$this->config} as bad");
        Cache::getInstance()->set('speedway_bad_config_' . $this->config, 1, 60 * 10);
    }

    private function markConfigAsUnstable(): void
    {
        $this->logger->info("marking config {$this->config} as unstable");
        Cache::getInstance()->set('speedway_unstable_config_' . $this->config, 1, 60 * 10);
    }

    private function setConfig()
    {
        $oses = [
            SeleniumFinderRequest::OS_MAC,
            /*
            SeleniumFinderRequest::OS_MAC_M1,
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
            return Cache::getInstance()->get('speedway_success_config_' . $key) === 1;
        });

        $badConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('speedway_bad_config_' . $key) === 1;
        });

        $unstableConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('speedway_unstable_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys($configs), function (string $key) use ($successConfigs, $badConfigs, $unstableConfigs) {
            return !in_array($key, array_merge($successConfigs, $badConfigs, $unstableConfigs));
        });

        foreach (array_keys($badConfigs) as $key) {
            if (isset($successConfigs[$key])) {
                unset($successConfigs[$key]);
            }
        }

        foreach (array_keys($unstableConfigs) as $key) {
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

        foreach ($this->configsWithOs as $configName => $config) {
            $this->logger->debug($configName);
        }

        if (count($successConfigs) > 0) {
            $this->logger->debug('[SUCCESS CONFIGS]');

            foreach ($successConfigs as $config) {
                $this->logger->debug($config);
            }
        }

        if (count($badConfigs) > 0) {
            $this->logger->debug('[BAD CONFIGS]');

            foreach ($badConfigs as $config) {
                $this->logger->debug($config);
            }
        }

        if (count($unstableConfigs) > 0) {
            $this->logger->debug('[UNSTABLE CONFIGS]');

            foreach ($unstableConfigs as $config) {
                $this->logger->debug($config);
            }
        }

        if (count($neutralConfigs) > 0) {
            $this->logger->debug('[NEUTRAL CONFIGS]');

            foreach ($neutralConfigs as $config) {
                $this->logger->debug($config);
            }
        }
    }

    private function injectCatchingScript(): void
    {
        $this->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (/api\/token/g.exec(url)) {
                        localStorage.setItem("tokenData", this.responseText);
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
                            if(response.url.indexOf("/api/token") > -1) {
                                response
                                    .clone()
                                    .json()
                                    .then(body => localStorage.setItem("tokenData", JSON.stringify(body)));
                            }
                            resolve(response);
                        })
                        .catch((error) => {
                            reject(error);
                        })
                });
            }
        ');
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $tokenData = $this->driver->executeScript("return localStorage.getItem('tokenData');");
        $this->logger->info("[token data]: " . $tokenData);
        $tokenDataDecoded = $this->http->JsonLog($tokenData);

        if (!isset($tokenDataDecoded->access_token)) {
            return false;
        }

        $token = $tokenDataDecoded->access_token;
        $this->logger->info("[token]: " . $token);
        $this->openCurlDrive();

        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'Authorization'   => 'Bearer ' . $token,
            'Origin'          => 'https://www.speedway.com',
            'Referer'         => 'https://www.speedway.com/',
            'X-SEI-PLATFORM'  => 'web_spw',
            'X-SEI-DEVICE-ID' => 'dbd9109f-036f-4bdf-b889-2bf85a2b57c8',
            'X-SEI-VERSION'   => '7.0',
        ];

        $this->curlDrive->GetURL('https://apis.7-eleven.com/v5/accounts?profile=full', $headers);
        $userInfo = $this->curlDrive->JsonLog();

        if (
            isset($userInfo->mobile_number)
            && $userInfo->mobile_number == $this->AccountFields['Login']
        ) {
            $this->headers = $headers;

            return true;
        }

        return false;
    }

    private function openCurlDrive()
    {
        $this->logger->notice(__METHOD__);
        $this->curlDrive = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->curlDrive);
        $this->curlDrive->setHttp2(true);
        $this->curlDrive->setProxyParams($this->http->getProxyParams());
    }

    private function copySeleniumCookies($selenium, $curl)
    {
        $this->logger->notice(__METHOD__);

        $cookies = $selenium->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $curl->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
    }

    private function selectProxy(TAccountChecker $selenium)
    {
        return;
        /*
        $proxyConfig = rand(0, 4);
        */
        $proxyConfig = 4;
        $this->logger->debug('proxy config: ' . $proxyConfig);

        switch ($proxyConfig) {
            case 0:
                $selenium->setProxyDOP();

                break;

            case 1:
                $selenium->setProxyBrightData(null, "static");

                break;

            case 2:
                $selenium->setProxyMount();

                break;

            case 3:
                $selenium->setProxyGoProxies();

                break;

            case 4:
                $selenium->setProxyNetNut(true);

                break;
        }
    }
}
