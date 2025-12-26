<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerLowesOld extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private string $xpathResult = '
        //span[contains(text(), "Enter a valid password with at least 8 characters.")]
        | //span[contains(text(), "Enter a valid password with a max of 12 characters.")]
        | //span[contains(text(), "At least 1 letter and 1 number with no spaces.")]
        | //span[contains(text(), "Please leave this window open while you retrieve your code.")]
        | //p[contains(text(), "We wanted to let you know your LowesForPros.com account is moving to Lowes.com.")]
        | //button[contains(text(), "Agree and continue")]
        | //p[contains(text(), "We\'ve sent a code to")]
        | //span[@id = "account-name"]
        | //label[contains(text(), "Email one-time passcode to")]
        | //p[span[contains(text(), "To help protect your account.")] and contains(., "We\'ve sent a one-time passcode")]
        | //h2[contains(text(), "Complete Your Profile")]
    ';
    private string $errorXpath = '//div[@role="alert"]';

    private const CONFIGS = [
        'firefox-100-mac' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_100,
        ],
        'firefox-59-mac' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_59,
        ],
        'firefox-59-win' => [
            'agent'           => "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_59,
        ],
        'firefox-84-mac' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_84,
        ],
        'firefox-84-win' => [
            'agent'           => "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_84,
        ],
        'chromium-80-linux' => [
            'agent'           => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROMIUM,
            'browser-version' => \SeleniumFinderRequest::CHROMIUM_80,
        ],
        'chromium-80-mac' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROMIUM,
            'browser-version' => \SeleniumFinderRequest::CHROMIUM_80,
        ],
        'chrome-84-linux' => [
            'agent'           => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_84,
        ],
        'chrome-84-mac' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_84,
        ],
        /*'chrome-95-mac' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_95,
        ],*/
    ];
    /**
     * @var array|int|mixed|string
     */
    private $config;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->UseSelenium();
        //$this->http->SetProxy($this->proxyReCaptcha());

        /*
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        $request = FingerprintRequest::firefox();
        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if ($fingerprint !== null) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
        }
        */

        $this->setProxyMount();
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->saveScreenshots = true;

        // $this->setConfig();
    }

    private function setConfig()
    {
        $configs = self::CONFIGS;

        $successfulConfigs = array_filter(array_keys($configs), function (string $key) {
            return \Cache::getInstance()->get('lowes_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys($configs), function (string $key) {
            return \Cache::getInstance()->get('lowes_config_' . $key) !== 0;
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

        $fingerprint = null;
        switch (self::CONFIGS[$this->config]['browser-family']) {
            case \SeleniumFinderRequest::BROWSER_CHROMIUM:
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::chrome()]);

                break;

            case \SeleniumFinderRequest::BROWSER_CHROME:
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::chrome()]);

                break;

            case \SeleniumFinderRequest::BROWSER_FIREFOX:
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::firefox()]);

                break;
        }

        if ($fingerprint !== null) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
        }else {
            $this->http->setUserAgent(self::CONFIGS[$this->config]['agent']);
        }

        $this->seleniumRequest->request(self::CONFIGS[$this->config]['browser-family'], self::CONFIGS[$this->config]['browser-version']);

        if (
            empty($this->State['chosenResolution'])
            || $this->attempt > 1
        ) {
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $this->State['chosenResolution'] = $resolutions[array_rand($resolutions)];
            $this->setScreenResolution($this->State['chosenResolution']);
        }
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        /*
        $this->http->GetURL('https://www.lowes.com/u/login');

        $email = $this->waitForElement(WebDriverBy::xpath('//input[@id="email"]'), 10);
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@id="user-password"]'), 0);
        $submit = $this->waitForElement(WebDriverBy::xpath('//button[@type="submit"]'), 0);

        if(!$email || !$password || !$submit) {
            $this->logger->debug('Failed to find form fields');
            $this->saveResponse();
            return false;
        }

        $email->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);
        $submit->click();

        // $sendCodeTo = $this->waitForElement(WebDriverBy::xpath("//strong[text()=\"{$this->AccountFields['Login']}\"]"), 1);
        // $submit = $this->waitForElement(WebDriverBy::xpath('//button[text()="Continue"]'), 1);

        // if(!$sendCodeTo || !$submit) {
        //     $this->logger->debug('Failed to send otp code');
        //     $this->saveResponse();
        //     return false;
        // }
        // $submit->click();

        for($i = 0; $i < 3; $i++) {
            sleep(12);
            $error = $this->waitForElement(WebDriverBy::xpath('//span[text()="Something went wrong please try again."]'), 1);
            if(!$error) {
                break;
            }
            $submit = $this->waitForElement(WebDriverBy::xpath('//button[@type="submit"]'), 0);
            $submit->click();
        }

        return false;
        */

        try {
//            $this->http->GetURL("https://www.lowes.com/mylowes/profile/mylowescard");
            $this->http->GetURL("https://www.lowes.com/mylowes/profile");
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;

            return false;
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "email"]'), 10);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "user-password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In")]'), 0);
        // save page to logs
        $this->saveResponse();

        if (!$passwordInput || !$button) {
            if ($this->http->FindSingleNode('//span[@id = "account-name"]')) {
                $this->loginSuccessful();

                return true;
            }

            if ($this->http->FindSingleNode('//p[contains(text(), "Health check")]')) {
                $this->markProxyAsInvalid();
                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        // remember me issue
        if ($loginInput) {
            //$loginInput->sendKeys($this->AccountFields['Login']);
            try {
                $mover->moveToElement($loginInput);
                $mover->click();
                $mover->sendKeys($loginInput, $this->AccountFields['Login'], 30);
            } catch (NoSuchDriverException $e) {
                $this->logger->error("exception: " . $e->getMessage());

                throw new CheckRetryNeededException(3, 5);
            }
            /*
            catch (Exception $e) {
                $this->logger->debug($e->getMessage());
                $loginInput->clear();
                $mouse = $this->driver->getMouse();
                $mouse->mouseMove($loginInput->getCoordinates());
                $mouse->click();
                $loginInput->sendKeys($this->AccountFields['Login']);
            }
            */
        }

        //$passwordInput->sendKeys($this->AccountFields['Pass']);
        try {
            $mover->moveToElement($passwordInput);
            $mover->click();
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 20);
        } catch (NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 5);
        }
        /*
        catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            $passwordInput->clear();
            $mouse = $this->driver->getMouse();
            $mouse->mouseMove($passwordInput->getCoordinates());
            $mouse->click();
            $passwordInput->sendKeys($this->AccountFields['Pass']);
        }
        */
        $this->saveResponse();

        $this->catchLoginRequest();

        $button->click();
        $this->logger->debug("wait results");
        sleep(5);

        $res = $this->waitForElement(WebDriverBy::xpath($this->xpathResult), 17);
        $this->saveResponse();

        $sec = $this->waitForElement(WebDriverBy::id('sec-text-container'), 0);
        // sec-text-container workaround
        if ($sec) {
            $this->logger->notice("sec-text-container workaround");
            $delay = 0;

            $this->waitFor(function () {
                return !$this->waitForElement(WebDriverBy::id('sec-text-if'), 0);
            }, 50);
            $this->saveResponse();

            if ($button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In")]'), 0)) {
                $this->catchLoginRequest();
                $button->click();
                $this->logger->debug("wait results");
                sleep(5);

                $delay = 17;
            }

            $res = $this->waitForElement(WebDriverBy::xpath($this->xpathResult), $delay);
            $this->saveResponse();
        }

        try {
            $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 5);
        }
        $this->logger->info("[Form responseData]: " . $responseData);

        if ($res && $this->http->FindPreg("/(Please leave this window open while you retrieve your code\.|Your account has multi-factor authentication enabled\.)/", false, $res->getText())) {
            $contBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue")]'), 0);
            $contBtn->click();

            $this->waitForElement(WebDriverBy::xpath('
                //input[@id = "verificationCode"]
            '), 5);
            $this->saveResponse();

            return true;
        }
        $this->saveResponse();
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "email"]'), 0);
        $contBtn = $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "submit-btn")]/button'), 0);

        if (!$res && $loginInput && $contBtn) {
            $this->logger->notice('>>> Retry click');

            $this->catchLoginRequest();

            $contBtn->click();
            sleep(5);
            $this->logger->debug("wait results");
            $res = $this->waitForElement(WebDriverBy::xpath($this->xpathResult), 10);
            $this->saveResponse();

            try {
                $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
            } catch (NoSuchDriverException $e) {
                $this->logger->error("exception: " . $e->getMessage());

                throw new CheckRetryNeededException(3, 5);
            }

            $this->logger->info("[Form responseData]: " . $responseData);
        }

        $contBtn = $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "submit-btn")]/button'), 10);
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "email"]'), 0);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "user-password"]'), 0);

        if (
            !$res
            && !$loginInput
            && $contBtn
            && $passwordInput
        ) {
            $this->logger->notice('>>> Retry');
            // todo: not working
            //$passwordInput->clear();
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            /*try {
                $mover->moveToElement($passwordInput);
                $mover->click();
                $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 5);
            } catch (Exception $e) {
                $this->logger->debug($e->getMessage());
                $passwordInput->clear();
                $mouse = $this->driver->getMouse();
                $mouse->mouseMove($passwordInput->getCoordinates());
                $mouse->click();
                $passwordInput->sendKeys($this->AccountFields['Pass']);
            }*/
            $this->saveResponse();

            $this->catchLoginRequest();
            $contBtn->click();
            sleep(5);
            $this->logger->debug("wait results");

            $res = $this->waitForElement(WebDriverBy::xpath($this->xpathResult), 10);
            $this->saveResponse();

            $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            if ($res && $this->http->FindPreg("/(Please leave this window open while you retrieve your code\.|Your account has multi-factor authentication enabled\.|Email one-time passcode to)/", false, $res->getText())) {
                $contBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue")]'), 0);
                $contBtn->click();

                $this->waitForElement(WebDriverBy::xpath('
                    //input[@id = "verificationCode"]
                '), 5);
                $this->saveResponse();

                return true;
            }
        }

        $this->logger->debug("try to find sq");
        $res = $this->waitForElement(WebDriverBy::xpath($this->xpathResult), 0);
        $this->saveResponse();

        if (
            (
                !$res
                || $res->getText() == 'Your account has multi-factor authentication enabled.'
                || $res->getText() == 'Please leave this window open while you retrieve your code. Closing this window will end your session.'
                || strstr($res->getText(), 'Email one-time passcode to')
            )
            && ($contBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue")]'), 0))
        ) {
            $res = null;
            $contBtn->click();
            $lowesBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue to Lowes.com")]'), 3);
            $this->saveResponse();

            if ($lowesBtn) {
                $lowesBtn->click();
                $label = $this->waitForElement(WebDriverBy::xpath('//label[contains(text(), "Lowe\'s Account")]'), 3);
                $this->saveResponse();
                $label->click();

                sleep(1);
                $lowesBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue to Lowes.com")]'), 1);
                $lowesBtn->click();

                $this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Profile & Preferences")]'), 3);
                $this->saveResponse();
            }
        }

        if (!empty($responseData) && !$this->http->FindPreg('#/_sec/cp_challenge/crypto_message#') && !$this->http->FindSingleNode('//div[@role="alert"] | //span[contains(text(), "Enter a valid ")] | //p[span[contains(text(), "To help protect your account.")] and contains(., "We\'ve sent a one-time passcode")]')) {
            $this->http->SetBody($responseData, false);
        } elseif ($res) {
            $message = $res->getText();
            $this->logger->error("[res text]: {$message}");

            if (strstr($message, 'Something went wrong please try again.')) {
                $this->logger->info("marking config {$this->config} as bad");
                \Cache::getInstance()->set('lowes_config_' . $this->config, 0);
                $this->DebugInfo = $message;
                throw new CheckRetryNeededException(3, 0);
            }

            if (
                strstr($message, "We wanted to let you know your LowesForPros.com account is moving to Lowes.com.")
                || strstr($message, "Complete Your Profile")
            ) {
                $this->throwProfileUpdateMessageException();
            }

            if (
                strstr($message, 'Enter a valid password with at least 8 characters.')
                || strstr($message, 'At least 1 letter and 1 number with no spaces.')
                || strstr($message, 'Enter a valid password with a max of 12 characters.')
                || strstr($message, 'The email address or password you entered doesn')
                || strstr($message, 'Your credentials do not match our records.')
                || strstr($message, 'Looks like you’re having trouble. You may want to try resetting your password')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Your account has been locked due to too many unsuccessful attempts.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (
                strstr($message, "For your added security, please update your password by clicking the 'Forget Password?'")
            ) {
                throw new CheckException("It looks like you haven't logged in in a while. For your added security, please update your password by clicking the 'Forget Password?'.", ACCOUNT_INVALID_PASSWORD);
            }

        }

        return true;

        $this->http->removeCookies();
        $this->http->setMaxRedirects(15);
        $this->http->GetURL("https://www.lowes.com/u/login");

        // retries
        if ($this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(2, 7);
        }

        if ($redirectUrl = $this->http->FindPreg("/oAuth2preAuthorize\":\{\"redirectUrl\":\"([^\"]+)/")) {
            $this->http->NormalizeURL($redirectUrl);
            $this->http->GetURL($redirectUrl);
        }

        if (
            !$this->http->ParseForm("login-form")
            && !$this->http->ParseForm(null, '//section[@id = "main"]/div/div/form', false)
        ) {
            return $this->checkErrors();
        }
//        $this->http->SetInputValue("email", $this->AccountFields['Login']);
//        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

//        if (!$sensorPostUrl) {
//            return false;
//        }
//
//        $this->http->NormalizeURL($sensorPostUrl);

        $this->http->removeCookies();

        return $this->selenium();
//        $this->sendSensorData($sensorPostUrl);

        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Authorization"    => "Basic QWRvYmU6ZW9pdWV3ZjA5ZmV3bw==",
            "Content-Type"     => "application/json",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $data = [
            "email"         => $this->AccountFields['Login'],
            "user-password" => $this->AccountFields['Pass'],
            "rememberMe"    => true,
        ];
        $this->http->PostURL("https://www.lowes.com/u/login", json_encode($data), $headers);

        return true;
    }

    private function catchLoginRequest()
    {
        $this->logger->notice(__METHOD__);
        $this->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (
                        /u\/login/g.exec(url)
                        && /(?:access_token|errorMessage|message)/g.exec(this.responseText)
                    ) {
                        localStorage.setItem("responseData", this.responseText);
                    }
                });
                return oldXHROpen.apply(this, arguments);
            };
        ');
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The site is currently offline and will be available within the next hour.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The site is currently offline and will be available within the next hour.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(2, 0);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $access_token = $response->access_token ?? null;
        $emailAddress = $response->emailAddress ?? null;

        if ($this->parseQuestion()) {
            return false;
        }

        if (
            !empty($access_token)
            && !empty($emailAddress)
            && strtolower($emailAddress) == strtolower($this->AccountFields['Login'])
        ) {
            if (isset($response->preferredStoreId) || $this->http->FindPreg("/preferredStoreId/")) {
                $this->State['preferredStoreId'] = $response->preferredStoreId;
            }

            return $this->loginSuccessful();
        }

        // AccountID: 4510237
        $email = $response->email ?? null;
        $isCrossShopper = $response->isCrossShopper ?? null;

        if (
            !empty($email)
            && $isCrossShopper === true
            && strtolower($email) == strtolower($this->AccountFields['Login'])
        ) {
            if (isset($response->preferredStoreId) || $this->http->FindPreg("/preferredStoreId/")) {
                $this->State['preferredStoreId'] = $response->preferredStoreId;
            }

            return $this->loginSuccessful();
        }

        if ($this->http->FindSingleNode('//span[@id = "account-name"]')) {
            $this->loginSuccessful();

            return true;
        }

        if ($this->http->FindSingleNode('//h4[contains(text(), "We have updated our Terms")] | //h2[contains(text(), "Complete Your Profile")]')) {
            $this->throwAcceptTermsMessageException();
        }

        if (is_array($response) && isset($response[0]->errorMessage)) {
            $message = $response[0]->errorMessage ?? null;
            $header = $response[0]->errorHeading ?? null;
            $this->logger->error("[Error message]: {$message}");

            if (
                $message == "The email address or password you entered doesn't match our records. Please give it another try."
                || stripos($message, 'Looks like you’re having trouble. You may want to try resetting  your password.') !== false
                || stripos($message, 'Please be aware another failed attempts will lock the account.') !== false
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (stripos($message, 'For your added security, please update your password') !== false) {
                throw new CheckException("For your added security, please update your password by clicking the 'Forget Password?' link", ACCOUNT_INVALID_PASSWORD);
            }

            if (isset($response[0]->errorKey) && $response[0]->errorKey == "SERVER_ERROR" && $message == "Something went wrong please try again.") {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($header == "Your MyLowe's account credentials may have been compromised." && $message == "Please select the Forgot Password link below to update your password.") {
                throw new CheckException($header, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, "Your account has been locked due to too many unsuccessful attempts.")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if ($header == "Updated terms not accepted." && $message == "Please accept the updated terms and conditions.") {
                $this->throwAcceptTermsMessageException();
            }

            $this->DebugInfo = $message;

            return false;
        }// if (isset($response[0]->errorMessage))

        $errorMessage = $response->{"Error message"} ?? null;

        if ($errorMessage == 'CWXFR0260E: The following exception occurred in method com.ibm.commerce.browseradapter.CrossSiteScriptingHelper.prohibitedCharactersExist(CrossSiteScriptingHelper.java:352): java.lang.NullPointerException.') {
            throw new CheckException("We're sorry. We're unable to access your account at this time. Please try again. If the problem persists, please call Customer Care at 1-866-678-2760.", ACCOUNT_PROVIDER_ERROR);
        }

        $message = $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error message]: {$message}");

            if ($message == "An internal server error occurred") {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        if ($message = $this->http->FindSingleNode($this->errorXpath)) {
            $message = preg_replace("/^Login Failed/i", "", $message);
            $this->logger->error("[Error]: {$message}");

            if (
                $message == "The email address or password you entered doesn't match our records. Please give it another try."
                || $message == "Something went wrong please try again."
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == "Your credentials do not match our records. Please try again or reset your password.") {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "It looks like you haven't logged in in a while.For your added security, please update your password by")) {
                throw new CheckException("It looks like you haven't logged in in a while.For your added security, please update your password.", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "Your account has been locked due to too many unsuccessful attempts. Please click on 'Forgot Password?'")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        /*if ($this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException();
        }*/

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        // We've sent a code to ******... . It will expire in 10 minutes
        // We've sent a one-time passcode to alexgmull@gmail.com. It will expire in 10 minutes.
        $q = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We\'ve sent a code to")] | //div[contains(text(), "We\'ve sent a one-time passcode to")] | //p[span[contains(text(), "To help protect your account.")] and contains(., "We\'ve sent a one-time passcode")]'), 0);
        $this->saveResponse();

        if (!$q) {
            return false;
        }

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $this->holdSession();
        // Please verify your device by clicking on Verify Device link sent to your email
        $question = $q->getText();

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        /*$contBtn = $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "submit-btn")]/button'), 0);
        $this->saveResponse();
        if ( $contBtn) {
            $contBtn->click();
            sleep(3);
        }*/

        $verificationCode = $this->waitForElement(WebDriverBy::xpath($xpath = '(//input[@id = "verificationCode"] | //label[contains(text(), "One-Time Passcode")]/following-sibling::div/input)'), 5);

        $contBtn = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class,"ModalDialog")]//button[contains(text(), "Verify & Continue")]'), 0);
        if (!$contBtn) {
            $contBtn = $this->waitForElement(WebDriverBy::xpath("$xpath/following::button[contains(.,'Sign In')][1]"), 0);
        } else
        $this->sendNotification('detect button // MI');


        $this->saveResponse();

        if (!$verificationCode || !$contBtn) {
            return false;
        }


        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        // remember me issue
        //$loginInput->sendKeys($this->AccountFields['Login']);
        try {
            $mover->moveToElement($verificationCode);
            $mover->click();
            $verificationCode->clear();
            $mover->sendKeys($verificationCode, $answer, 30);
        } catch (NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }

        $this->saveResponse();
        $this->catchLoginRequest();
        $this->saveResponse();
        $contBtn->click();
        $this->saveResponse();
        $this->logger->debug("wait results");
        sleep(5);
        $this->waitForElement(WebDriverBy::xpath('
            //div[@role="alert"]
            | //span[contains(text(), "Enter a valid password with at least 8 characters.")]
            | //span[contains(text(), "Enter a valid password with a max of 12 characters.")]
            | //span[contains(text(), "At least 1 letter and 1 number with no spaces.")]
            | //button[contains(text(), "Agree and continue")]
            | //h1[contains(text(), "Enter your verification code.")]
            | //span[@id = "account-name"]
        '), 5);
        $this->saveResponse();


        if ($this->http->FindSingleNode('//span[@id = "account-name"]')) {
            $this->loginSuccessful();

            return true;
        }

        $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
        $this->logger->info("[Form responseData]: " . $responseData);

        if (!empty($responseData)) {
            $this->http->SetBody($responseData, false);
        }

        $response = $this->http->JsonLog();
        if (isset($response->message) && $response->message=='Invalid OTP') {
            $this->holdSession();
            $this->AskQuestion($this->Question, 'This code is invalid. Please try again. You have a maximum of 5 attempts.', $step);
        }
        $message = $response->payload->message ?? null;
        $access_token = $response->token ?? null;
        //$emailAddress = $response->emailAddress ?? null;

        if (
            $message == 'success'
            && !empty($access_token)
            /*&& strtolower($emailAddress) == strtolower($this->AccountFields['Login'])*/
        ) {
            if (isset($response->preferredStoreId) || $this->http->FindPreg("/preferredStoreId/")) {
                $this->State['preferredStoreId'] = $response->preferredStoreId;
            }

            return $this->loginSuccessful();
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $script = $this->http->FindSingleNode('//script[contains(text(), "__PRELOADED_STATE__")]', null, true, "/__PRELOADED_STATE__\'\]\s*=\s*(.+)\]\]\>$/");
        $this->logger->debug("[$script]");
        $data = $this->http->JsonLog($script, 3, false, 'created');
        $userInfo = $data->accountDashboardApi->userInfo
            ?? $data->profileDataContent
            ?? null
        ;
        $firstname = $userInfo->firstName ?? null;
        $lastname = $userInfo->lastName ?? null;

        if (isset($firstname, $lastname)) {
            $this->SetProperty("Name", beautifulName($firstname . " " . $lastname));
        }

        $this->http->GetURL("https://www.lowes.com/loyalty/mylowesrewards");

        return;

        $this->http->RetryCount = 0;

        if (
            !isset($this->State['preferredStoreId'])
            && (isset($userInfo->preferredStoreId) || $this->http->FindPreg("/preferredStoreId/"))
        ) {
            $this->State['preferredStoreId'] = $userInfo->preferredStoreId;
            $this->logger->debug("[preferredStoreId]: {$this->State['preferredStoreId']}");
        }

//        $this->http->GetURL("https://www.lowes.com/mylowes/profile/mylowescard");
        // Card Number
        $this->SetProperty("CardNumber", $data->accountDashboardApi->myLowesCards->data[0]->id ?? null);

        // Store Phone #
        if (isset($this->State['preferredStoreId'])) {
            $this->http->GetURL("https://www.lowes.com/store/api/search?maxResults=1&responseGroup=large&searchTerm={$this->State['preferredStoreId']}");
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));

            if (is_array($response->stores ?? null)) {
                $this->SetProperty("StorePhone", $response->stores[0]->store->phone ?? null);
            }
        } else {
            $this->http->GetURL("https://www.lowes.com/account/organization");
            $this->SetProperty("StorePhone", $this->http->FindSingleNode('//div[p[contains(text(), "Organization address:")]]/following-sibling::div/p[contains(text(), "-")]'));
        }

//        $this->http->GetURL("https://www.lowes.com/account/loyalty-benefits");

        if (
            (
                isset($this->Properties['StorePhone'])
                || isset($this->Properties['CardNumber'])
                || in_array($this->AccountFields['Login'], [
                    'lindsey1911@gmail.com',
                    'andrewx80@gmail.com',
                    'corriemelton@yahoo.com',
                    'kitty.gang.cml@gmail.com',
                ])
                || $this->http->FindNodes('//div[p[contains(text(), "Organization address:")]]/following-sibling::div/p')
                || (array_key_exists('preferredStoreId', $this->State) && $this->State['preferredStoreId'] === null)
                || (isset($data->accountDashboardApi->myLowesCards->payload->data) && $data->accountDashboardApi->myLowesCards->payload->data == [])
            )
            && !empty($this->Properties['Name'])
        ) {
            $this->SetBalanceNA();
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.lowes.com/mylowes/profile");
//        $this->http->GetURL("https://www.lowes.com/mylowes/profile/mylowescard");
        $this->http->RetryCount = 2;
        $this->saveResponse();

        if (
            $this->http->FindSingleNode('//h1[contains(normalize-space(), "MyLowe\'s Card")]')
        ) {
            $this->logger->info("marking config {$this->config} as successful");
            \Cache::getInstance()->set('lowes_config_' . $this->config, 1);
            return true;
        }

        return false;
    }

    private function sendSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);

        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9299621.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,402811,8636221,1536,871,1536,960,1536,434,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8939,0.999602599499,818564318110.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,520,520,0;1,-1,0,0,1375,1375,0;-1,2,-94,-102,0,-1,0,0,520,520,0;1,-1,0,0,1375,1375,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.lowes.com/u/login-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1637128636221,-999999,17513,0,0,2918,0,0,3,0,0,22D16A4E41343E0CE1266ED87355F71D~-1~YAAQkGjcF8y5mSN9AQAA7IB4LAbb569EdEA1dEiHGkaSE4rQkKku8ZvpvpWPigAJ0GNKTi+wh5UKk9OGm0+O3fgLmPW05DZI7yy/mUxXJnrSLZo8fc3BH4PBZiRGFy6yd9kUMqio2qIMv9ueuwAhG7+xx8zyPbYsv3B60ByEX0IHf6uUhDke08b+g8bXRHG1o/k4hgaW/rs8A+IjEjq9W76dYHGkrt17pDndedVr/npJ52g+RmB+AiKAK5E4pwCBMCWnz6nMxBh2MOeoO3LKlaARZwhqdp9IqhZvD4lfYGxNJI+YAwsiBgbqtdxOT4Yd/m0UDY+R2xr/S6cC1fFrofiPxlkG279zyzt6aOejmKVw5yHvAGsgtAYdJ0EjJQ7yfxXW5rrei/4D~-1~-1~-1,36279,-1,-1,30261693,PiZtE,108464,63,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,25908618-1,2,-94,-118,86971-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 1
            "7a74G7m23Vrp0o5c9299621.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,402811,8754718,1536,871,1536,960,1536,434,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8939,0.221542248110,818564377358.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,520,520,0;1,-1,0,0,1375,1375,0;-1,2,-94,-102,0,-1,0,0,520,520,0;1,-1,0,0,1375,1375,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.lowes.com/u/login-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1637128754717,-999999,17513,0,0,2918,0,0,4,0,0,6E423961DB9CEFA9F4245FCFFC2F2197~-1~YAAQlGjcF/IcgPJ8AQAAcFV6LAZrDWqPsv1cxwzloNtU1EvPV/XL8Cn1kFhbo4/DyKhOHUbtg82ACp7SmxRUs56ObtZ/kg+rXlvj3v4/2r7iz9jzIPyCqF7TBw3C8A9H3vXA+OLsDxH2pTfCHjdlHFX3gFcN5/BsdqhMDPliX08lX8bp/AiZ21cqDRAKTP6EfErSO/nSoIDQ1g6rHW6gY7jbxrgoON/hriCPRJyTjCyPqYUsoAieom+0FeuNYY0d0FIJtrqTg0tWyjch9oRBAEQQGCgJqVXZ9JQurXu6D+K/NuT/A1USgNmXnkOGii3MePtH73tTafBkO0k3gdirJR+hDE2f7u0gszud0/n3mdZqpP5Nj1pryOUauDHrJKe74RL4lWkeP6DBn1mSJsXp2xcPs41Oc/25V4pqzow=~-1~-1~-1,38821,-1,-1,30261693,PiZtE,104077,52,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,1181886858-1,2,-94,-118,89498-1,2,-94,-129,-1,2,-94,-121,;6;-1;0",
        ];
        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9299621.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,402811,8636221,1536,871,1536,960,1536,434,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8939,0.253482870126,818564318110.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,520,520,0;1,-1,0,0,1375,1375,0;-1,2,-94,-102,0,-1,0,0,520,520,0;1,-1,0,0,1375,1375,0;-1,2,-94,-108,-1,2,-94,-110,0,1,92,271,428;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.lowes.com/u/login-1,2,-94,-115,1,824,32,0,0,0,792,593,0,1637128636221,35,17513,0,1,2918,0,0,594,92,0,22D16A4E41343E0CE1266ED87355F71D~-1~YAAQkGjcF/+6mSN9AQAAoYh4LAZ5crczc/axwQUQZwceoKjZ/n4k0OnSpfCoyVWve+ERaQSNBab21SA1ZzuHa4DuaP1lgm3+/ewNRACY0fz71X9Wuw+8gKSzo1u75TzhMG8/GQauGLM1Kig50FLTPT7dlXmxjZ4ETs26lGw+8kxaJ60biZadmJEmG3j9OaEZgBd7ill3pJpeEboQPRwQ4hMO1uf9VxnlI+N98bzEf9P2nP87POE+MxwMsQ5DuZNK5YVVkFbm1iIHF+C/yKl15ytEMP2C5pgGy9rEpXNcXBh0Jvty3Tzyi6aHFXbqtXaQ+CHZ2/ygIrJBBHj2yXfguZY2OOOhShkwQ06wp8qZQQnKkBkezpg0whWOGAdIUD0KDsaQer2IeNLkwfAp/xhKNQ5sJdHIZRS+KOlcDaE=~-1~-1~-1,38606,378,892612484,30261693,PiZtE,63455,88,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,40,20,40,20,40,20,20,0,20,0,0,1320,1260,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5475-1,2,-94,-116,25908618-1,2,-94,-118,93505-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;24;7;0",
            // 1
            "7a74G7m23Vrp0o5c9299621.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,402811,8754718,1536,871,1536,960,1536,434,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8939,0.778144642389,818564377358.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,520,520,0;1,-1,0,0,1375,1375,0;-1,2,-94,-102,0,-1,0,0,520,520,0;1,-1,0,0,1375,1375,0;-1,2,-94,-108,-1,2,-94,-110,0,1,440,358,111;1,1,443,357,118;2,1,454,355,127;3,1,486,348,163;4,1,514,345,188;5,1,520,344,196;6,1,525,343,204;7,1,540,342,211;8,1,540,342,218;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.lowes.com/u/login-1,2,-94,-115,1,9209,32,0,0,0,9177,564,0,1637128754717,23,17513,0,9,2918,0,0,564,4462,0,6E423961DB9CEFA9F4245FCFFC2F2197~-1~YAAQlGjcFzEdgPJ8AQAAjld6LAZJJwxXkW1/e0A6izYjCJBfr3v+WovzjeqSYDaivwQ0h/i75Yekxu+CD6Bt5JY4avtZTAGOyHRYkjXq9Dm2udvttXcFxv8hy0eU6eo+sIWu9pY3D5kFU+pVLkAaIvyIFNA66O6yskW6NmoxdQalS3xiRXT2rp1DOTbcO7Cz6Od1X3uxO5urveUojjkxLkfu1e+rqIv/8uuGBefi+gYzGjCQlywLey7oCsr4prTrRQ8nDQEtuh3FWSxAlULpth0uPmGoPAIl/VpOk8dyehWJtYShVjpKPZqoWmLL369rsAgjRqm/M2obl3s+qCcTgIPgdUWZLGrRM34+iRiNuoHK3a3vdRvLufYWEn3HqwF8oVRJT0gsYp1B19pAyfY50ucldKoBnDOzlr2QGXw=~-1~-1~-1,40156,822,-1723173792,30261693,PiZtE,80288,50,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,40,20,40,40,40,60,40,20,0,0,0,1200,1180,240,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5475-1,2,-94,-116,1181886858-1,2,-94,-118,101856-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;24;9;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $data = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($data), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        /*
        $data = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($data), $sensorDataHeaders);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
        sleep(1);
        */

        return $key;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $retry = false;

        try {
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }
//            $selenium->disableImages();
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.lowes.com/mylowes/profile/mylowescard");
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $retry = true;

                return false;
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "email"]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "user-password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In")]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return $this->checkErrors();
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);

            $this->catchLoginRequest();

            $button->click();

            sleep(5);
            $this->logger->debug("wait results");

            $res = $selenium->waitForElement(WebDriverBy::xpath('
                //div[@role="alert"]
                | //span[contains(text(), "Enter a valid password with at least 8 characters.")]
                | //span[contains(text(), "Enter a valid password with a max of 12 characters.")]
                | //span[contains(text(), "At least 1 letter and 1 number with no spaces.")]
                | //button[contains(text(), "Agree and continue")]
                | //h1[contains(text(), "Enter your verification code.")]
                | //span[@id = "account-name"]
            '), 5);

            $this->savePageToLogs($selenium);

            if ($res && $this->http->FindPreg("/Please leave this window open while you retrieve your code\./", false, $res->getText())) {
                $this->savePageToLogs($selenium);
                $contBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue")]'), 0);
                $contBtn->click();

                $selenium->waitForElement(WebDriverBy::xpath('
                    //span[contains(text(), "Enter a ")]
                '), 5);
                $this->savePageToLogs($selenium);
            }

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            $contBtn = $selenium->waitForElement(WebDriverBy::xpath('//span[contains(@class, "submit-btn")]/button'), 0);

            if (
                !$res
                && $contBtn
                && ($passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "user-password"]'), 0))
            ) {
                $passwordInput->sendKeys($this->AccountFields['Pass']);
                $this->savePageToLogs($selenium);

                $this->catchLoginRequest();

                $contBtn->click();
                sleep(5);
                $this->logger->debug("wait results");

                $res = $selenium->waitForElement(WebDriverBy::xpath('
                    //div[@role="alert"]
                    | //span[contains(text(), "Enter a valid password with at least 8 characters.")]
                    | //span[contains(text(), "Enter a valid password with a max of 12 characters.")]
                    | //span[contains(text(), "At least 1 letter and 1 number with no spaces.")]
                    | //button[contains(text(), "Agree and continue")]
                    | //h1[contains(text(), "Enter your verification code.")]
                    | //span[@id = "account-name"]
                '), 5);

                if ($res && $this->http->FindPreg("/Please leave this window open while you retrieve your code\./", false, $res->getText())) {
                    $this->savePageToLogs($selenium);
                    $contBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue")]'), 0);
                    $contBtn->click();

                    $selenium->waitForElement(WebDriverBy::xpath('
                        //span[contains(text(), "Enter a ")]
                    '), 5);
                    $this->savePageToLogs($selenium);
                }

                $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
                $this->logger->info("[Form responseData]: " . $responseData);

                $this->savePageToLogs($selenium);
            }

            if (
                (
                    !$res
                    || $res->getText() == 'Your account has multi-factor authentication enabled.'
                    || strstr($res->getText(), 'Email one-time passcode to')
                )
                && $contBtn
            ) {
                $res = null;
                $contBtn->click();
                $lowesBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue to Lowes.com")]'), 3);
                $this->savePageToLogs($selenium);

                if ($lowesBtn) {
                    $lowesBtn->click();
                    $label = $selenium->waitForElement(WebDriverBy::xpath('//label[contains(text(), "Lowe\'s Account")]'), 3);
                    $this->savePageToLogs($selenium);
                    $label->click();

                    sleep(1);
                    $lowesBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue to Lowes.com")]'), 1);
                    $lowesBtn->click();

                    $selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Profile & Preferences")]'), 3);
                    $this->savePageToLogs($selenium);
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!empty($responseData)) {
                $this->http->SetBody($responseData, false);

                return true;
            } elseif ($res) {
                $message = $res->getText();
                $this->logger->error("[res text]: '{$message}'");

                if (
                    strstr($message, 'Enter a valid password with at least 8 characters.')
                    || strstr($message, 'At least 1 letter and 1 number with no spaces.')
                    || strstr($message, 'Enter a valid password with a max of 12 characters.')
                    || strstr($message, 'Your credentials do not match our records.')
                    || strstr($message, 'Looks like you’re having trouble. You may want to try resetting your password')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    strstr($message, "For your added security, please update your password by clicking the 'Forget Password?'")
                ) {
                    throw new CheckException("It looks like you haven't logged in in a while. For your added security, please update your password by clicking the 'Forget Password?'.", ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    strstr($message, 'Your account has been locked due to too many unsuccessful attempts.')
                ) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                if ($message == "Login Failed Something went wrong please try again.") {
                    $this->DebugInfo = 'captcha issue';
                    $retry = true;
                } else {
                    $this->DebugInfo = $message;
                }
            }

            $result = true;
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(2, 0);
            }
        }

        return $result;
    }
}
