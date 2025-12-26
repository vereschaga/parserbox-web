<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;
use AwardWallet\ItineraryArrays\Hotel;

class TAccountCheckerMarriott extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    use PriceTools;

    public const LOGOUT_XPATH = '//span[contains(text(), "Hello, ")] | //a[contains(text(), "Sign Out") or contains(text(), "Logout")]';
    public const HISTORY_PAGE_URL = "https://www.marriott.com/loyalty/myAccount/activity.mi?activityType=types&monthsFilter=24";
    public const ERROR_URL = 'https://www.marriott.com/sign-in-error.mi?transaction=login';

    protected $endHistory = false;
    protected $accountActivity = [];

    private $seleniumURL = null;
    private ?string $customerId;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if (in_array($this->AccountFields['UserID'] ?? null, [2110])) {
            $this->logger->debug("remove cookies, test mode");
            $this->http->removeCookies();
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.marriott.com/loyalty/myAccount/default.mi", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);
        //		$this->http->removeCookies();
        $this->http->FilterHTML = false;
        /*
        $this->http->setCookie("EUCookieShowOnce", "true", "www.marriott.com");
        */
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.marriott.com/sign-in.mi");

        // prevent "Unable to Sign In"
        if (isset($this->State['removeCookies'])) {
            $this->http->removeCookies();
            unset($this->State['removeCookies']);
        }
        // prevent 503
        if ($this->http->FindPreg("/The server is temporarily unable to service your request\.\s*Please try again\s*later\./")) {
            $this->http->removeCookies();
            $this->http->GetURL("https://www.marriott.com/sign-in.mi");
        }

        if (!$this->http->ParseForm('signInForm') && !$this->http->ParseForm(null, "//div[@data-testid=\"account-SignIn\" or contains(@class, 'StyledSignInContainerDiv')]/form")) {
            return $this->checkErrors();
        }

        $this->AccountFields['Login'] = preg_replace('/\s*/', '', $this->AccountFields['Login']);

        if (!$this->seleniumAuth()) {
            return false;
        }

//        $this->http->RetryCount = 2;
        //		$this->http->SetInputValue('j_username', "rewardsWebService@" . preg_replace('/\s*/', '', $this->AccountFields['Login']));
        //		$this->http->Form["userNamePrefix"] = "rewardsWebService@";
        //		$this->http->SetInputValue('visibleUserName', preg_replace('/\s*/', '', $this->AccountFields['Login']));
        //		$this->http->SetInputValue('j_password', $this->AccountFields['Pass']);
        //		$this->http->Form["remember_me"] = "on";

        return true;
    }

    /**
     * @var TAccountCheckerMarriott
     */
    public function innerLogin($selenium, &$restart, $firefox = false, $proxySwitchAttempts = 0)
    {
        $this->logger->notice(__METHOD__);

        /** @var SeleniumDriver $seleniumDriver */
        $seleniumDriver = $selenium->http->driver;
        $attempt = 0;
        $currentProxy = $selenium->http->getProxyAddress();
        $proxies = [];

        if ($currentProxy !== null && $proxySwitchAttempts > 0) {
            $currentProxy .= ":" . $selenium->http->getProxyPort();
            $proxies = $this->getDoProxies(Settings::DATACENTERS_NORTH_AMERICA);
            $pos = array_search($currentProxy, $proxies);

            if ($pos !== false) {
                array_splice($proxies, $pos, 1);
            }
        }

        try {
            if (in_array($this->AccountFields['UserID'] ?? null, [2110])) {
                $selenium->http->GetURL("https://ipinfo.io/json");
            }
            $selenium->http->GetURL("https://www.marriott.com/sign-in.mi?returnTo=%2Floyalty%2FmyAccount%2Fdefault.mi");
        } catch (\Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("Exception 1: " . $e->getMessage(), ['HtmlEncode' => true]);
            $selenium->driver->executeScript('window.stop();');

            if (
                strstr($e->getMessage(), 'TimedPromise timed out after')
                || strstr($e->getMessage(), 'Timed out receiving message from renderer:')
            ) {
                throw new CheckRetryNeededException(4, 0);
            }
        } catch (Facebook\WebDriver\Exception\UnknownErrorException | UnknownErrorException $e) {
            $this->logger->error("Exception 2: " . $e->getMessage(), ['HtmlEncode' => true]);

            if (strstr($e->getMessage(), 'ERR_TUNNEL_CONNECTION_FAILED')) {
                throw new CheckRetryNeededException(4, 0);
            }

            $this->savePageToLogs($selenium);
        } catch (UnexpectedAlertOpenException | Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage(), ['HtmlEncode' => true]);

            try {
                $error = $selenium->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $selenium->driver->switchTo()->alert()->dismiss();
                $this->logger->debug("alert, dismiss");
            } catch (NoAlertOpenException | UnexpectedAlertOpenException $e) {
                $this->logger->error("UnexpectedAlertOpenException -> exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            } finally {
                $this->logger->debug("UnexpectedAlertOpenException -> finally");
                sleep(2);

                try {
                    $error = $selenium->driver->switchTo()->alert()->getText();
                    $this->logger->debug("alert -> {$error}");
                    $selenium->driver->switchTo()->alert()->dismiss();
                    $this->logger->debug("alert, dismiss");
                } catch (NoAlertOpenException | UnexpectedAlertOpenException $e) {
                    $this->logger->error("UnexpectedAlertOpenException -> exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                } finally {
                    $this->logger->debug("UnexpectedAlertOpenException -> finally 2");
                    sleep(2);
                }

                $this->savePageToLogs($selenium);
            }
        }// catch (UnexpectedAlertOpenException $e)
        catch (NoAlertOpenException $e) {
            $this->logger->debug("no alert, skip");
        }

        do {
            $retry = false;

            if ($selenium->http->FindPreg("#get your browser updated#ims")) {
                return false;
            }

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->enableCursor();

            $headerForm = true;

            $resXpath = '//input[@id = "field-user-id"] | //span[contains(text(), "This site can’t be reached")] | //span[contains(text(), "This page isn’t working")] | //h1[contains(text(), "Access Denied")]';

            if (
                $this->attempt % 2
                && $headerForm
            ) {
                $selenium->waitForElement(WebDriverBy::xpath('
                    //a[contains(@href, "signInOverlay") or @data-modal-target = "/signInOverlay.mi"]
                    | //span[contains(text(), "This page isn’t working")]
                    | //h1[contains(text(), "Access Denied")]
                '), 9);

                $header = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@href, "signInOverlay") or @data-modal-target = "/signInOverlay.mi"]'), 0);
                // save page to logs
                $this->savePageToLogs($selenium);

                if (!$header) {
                    $this->logger->error("header not found");
                    /*
                    $this->callRetries($restart);

                    return false;
                    */
                }

                if ($header) {
                    $header->click();
                }

                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "user-id"]'), 10);

                if (!$loginInput) {
                    $headerForm = false;
                    $selenium->waitForElement(WebDriverBy::xpath($resXpath), 10);
                    $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "field-user-id" or @id = "user-id" or contains(@id, "-email")]'), 0);
                }
            } else {
                $selenium->waitForElement(WebDriverBy::xpath($resXpath), 10);
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "field-user-id" or contains(@id, "-email")]'), 3);
            }

            $selenium->driver->executeScript('var c = document.getElementById("onetrust-accept-btn-handler"); if (c) c.click();');
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput) {
                $this->logger->error("Login not found");
                $this->callRetries($restart);

                return $this->checkErrors();
            }

            if ($firefox == false) {
                $mover->duration = rand(90000, 120000);
                $mover->steps = rand(50, 70);
//                $mover->moveToElement($loginInput);
//                $mover->click();
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            // password
            if (
                $this->attempt % 2
                && $headerForm
            ) {
                $selenium->driver->executeScript("document.querySelector('#password').style.zIndex = '100000';");
                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 5);
                $buttonXpath = "//form[@name = 'directLoginForm']//button[contains(text(), 'Sign In')]";
            } else {
                $selenium->driver->executeScript("try { document.querySelector('#field-password').style.zIndex = '100000'; } catch (e) {}");
                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "field-password"] | //div[@data-testid="account-SignIn" or contains(@class, "StyledSignInContainerDiv")]//input[@id = "field-password" or contains(@id , "-password")]'), 5);
                $buttonXpath = "//form[@name = 'signInForm']//button[contains(text(), 'Sign In')] | //div[@data-testid=\"account-SignIn\" or contains(@class, 'StyledSignInContainerDiv')]//button[contains(text(), 'Sign In')]";
            }

            if (!$passwordInput) {
                $this->logger->error("password not found");

                return false;
            }

//            if ($firefox == false) {
//                $mover->moveToElement($passwordInput);
//            }
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            // Sign In
            $button = $selenium->waitForElement(WebDriverBy::xpath($buttonXpath), 0);

            if (!$button) {
                $this->savePageToLogs($selenium);
                $this->logger->error("button not found");

                return $this->checkErrors();
            }

            if ($firefox == false) {
//                $mover->moveToElement($button);
            }
            usleep(rand(100000, 500000));
            $this->logger->debug("click by 'Sign In' button");
            $this->savePageToLogs($selenium);

            try {
                $button->click();
            } catch (StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
                sleep(1);
                $this->savePageToLogs($selenium);
                $button = $selenium->waitForElement(WebDriverBy::xpath($buttonXpath), 0);
                $button->click();
            }

            $signOut = false;
            $this->waitFor(function () use (&$signOut, $selenium) {
                $this->seleniumURL = $selenium->http->currentUrl();
                $this->logger->debug("[Current URL]: {$this->seleniumURL}");

                if (in_array($this->seleniumURL, [
                        self::ERROR_URL,
                        'https://www.marriott.com/sign-in-error.mi?returnTo=/sign-in.mi?returnTo=%2Floyalty%2FmyAccount%2Fdefault.mi',
                        'https://www.marriott.com/loyalty/myAccount/force-change-password.mi',
                    ])
                    || strstr($this->seleniumURL, 'https://www.marriott.com/loyalty/myAccount/send-otp-challenge.mi')
                ) {
                    $selenium->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Confirm your identity')] | //h2[contains(text(), 'Confirm Your Identity')]"), 20);

                    if ($sendCodeChoice =
                        $selenium->waitForElement(WebDriverBy::xpath("//label[contains(text(), 'Send code via email to')] | //span[contains(text(), 'Email code to')]"), 3)
                        ?? $selenium->waitForElement(WebDriverBy::xpath("//label[contains(text(), 'Send code via text')] | //span[contains(text(), 'Email code to')]"), 0)
                    ) {
                        $this->savePageToLogs($selenium);
                        $sendCodeChoice->click();
                        $sendCode = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'send-otp']"), 0);
                        $sendCode->click();

                        $question = $selenium->waitForElement(WebDriverBy::xpath("
                            //label[
                                contains(text(), 'Enter the code from the email:')
                                or contains(text(), 'Enter the code from the SMS/text:')
                            ]
                            | //p[contains(text(), 'We sent a verification code via ')]
                            | //div[contains(text(), 'We'll send a verification code')]
                        "), 10);
                        $this->savePageToLogs($selenium);

                        if (!$this->http->ParseForm("otpActionForm") || !isset($question)) {
                            if ($message = $this->http->FindSingleNode('//div[contains(text(), "We\'re sorry, the server is unable to complete the verification process at this time. Please try again later.")]')) {
                                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                            }

                            if ($message = $this->http->FindSingleNode('//div[contains(text(), "There is no valid contact method on your account to send a verification code.")]')) {
                                throw new CheckException($message." For assistance, contact the customer support center.", ACCOUNT_PROVIDER_ERROR);
                            }

                            return true;
                        }

                        $this->Question = Html::cleanXMLValue($question->getText());
                        $this->ErrorCode = ACCOUNT_QUESTION;
                        $this->Step = "Question";

                        return true;
                    }

                    if ($selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "skeleton-loader")]'), 0)) {
                        $this->logger->error("site to long loading");
                        return false;
                    }
                }

                $signOut = $selenium->waitForElement(WebDriverBy::xpath(self::LOGOUT_XPATH), 0, false);

                return $signOut !== null;
            }, 15);

            // save page to logs
            $this->savePageToLogs($selenium);

            if (
                $seleniumDriver->browserCommunicator !== null
                && $seleniumDriver->browserCommunicator->canSwitchProxy()
                && $attempt < $proxySwitchAttempts
                && count($proxies) > 0
                && $this->seleniumURL === self::ERROR_URL
            ) {
                $attempt++;
                $newProxy = array_shift($proxies);
                $this->logger->debug("retrying on another proxy, attempt $attempt, old: {$selenium->http->getProxyAddress()}, new: {$newProxy}");
                $parts = explode(":", $newProxy);
                $selenium->markProxyAsInvalid();
                $seleniumDriver->browserCommunicator->switchProxy($parts[0], $parts[1]);
                $selenium->http->setProxyParams(["proxyAddress" => $parts[0], "proxyPort" => $parts[1]]);
                $retry = true;
            }
        } while ($retry);

        if ($signOut) {
            $selenium->markProxySuccessful();
        }

        return $signOut ? true : false;
    }

    public function seleniumAuth()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $proxy = "direct";
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");

            $selenium->UseSelenium();

            if (!isset($this->State["Resolution"])) {
                $resolutions = [
                    [1152, 864],
                    [1280, 800],
                    [1366, 768],
                    [1440, 900],
                    [1504, 1003],
                    [1920, 1080],
                    [2195, 1235],
                    [3414, 1440],
                ];
                $this->State["Resolution"] = $resolutions[array_rand($resolutions)];
            }
            $selenium->setScreenResolution($this->State["Resolution"]);
            // refs #14848
            $firefox = false;

            if ($this->attempt == 2) {
                $configs = [0, /*5,*/ 6/*8*/];
            } elseif ($this->attempt >= 1) {
                $configs = [/*1, */ 2, 3, 6, /*8,*/ 9, 10, 11];
            } else {
                $configs = [/*1,*/ 2, 7];
            }

            $config = $configs[array_rand($configs)];

            $proxySelected = false;
            $proxySwitchAttempts = 0;

            switch ($config) {
                case 0:
                    $selenium->useChromium();

                    /*
                    $request = FingerprintRequest::chrome();
                    $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
                    $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                    if ($fingerprint !== null) {
                        $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                        $selenium->http->setUserAgent($fingerprint->getUseragent());
                    }
                    */

                    break;

                case 1:
                    $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
                    $selenium->seleniumOptions->userAgent = null;

                    break;

                case 2:
//                    $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);
                    $firefox = true;
                    // this is chrome on macBook, NOT server Puppeteer
                    $selenium->useChromePuppeteer(SeleniumFinderRequest::CHROME_PUPPETEER_103);
                    $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;

                    /*
                    $request = FingerprintRequest::chrome();
                    $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
                    $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                    if ($fingerprint !== null) {
                        $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                        $selenium->http->setUserAgent($fingerprint->getUseragent());
                    }
                    */
                    $request = FingerprintRequest::chrome();
                    $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 5;
                    $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
                    $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                    /*
                    if (isset($fingerprint)) {
                        $selenium->http->setUserAgent($fingerprint->getUseragent());
                    }
                    */

                    break;

                case 3:
                    $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
                    $selenium->setKeepProfile(true);
                    $firefox = true;
                    $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
//                        $selenium->http->setRandomUserAgent(2, false, false, true);

                    break;

                case 4:
                    $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);

                    break;

                case 5:
                    // do not use 95 crhome, after auth in curl 502 on all pages
                    $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);

                    $request = FingerprintRequest::chrome();
                    $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                    $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                    if ($fingerprint !== null) {
                        $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                        $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
                        $selenium->http->setUserAgent($fingerprint->getUseragent());

                        // todo: debug
                        if (
                            $fingerprint->getScreenWidth() == '1280' && $fingerprint->getScreenHeight() == '768'
                            || $fingerprint->getScreenWidth() == '1280' && $fingerprint->getScreenHeight() == '800'
                        ) {
                            $selenium->setScreenResolution($this->State["Resolution"]);
                        }
                    }

                    break;

                case 6:
                    $this->useChromePuppeteer();

                    $request = FingerprintRequest::chrome();
                    $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                    $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                    if ($fingerprint !== null) {
                        $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                        $this->seleniumOptions->setResolution([
                            $fingerprint->getScreenWidth(),
                            $fingerprint->getScreenHeight()
                        ]);
                        $this->http->setUserAgent($fingerprint->getUseragent());
                    }

                    break;

                case 7:
                    $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
                    $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
                    $selenium->setKeepProfile(true);
                    $selenium->disableImages();
                    $firefox = true;

                    break;

                case 8:
                    $selenium->useChromePuppeteer(SeleniumFinderRequest::CHROME_PUPPETEER_100);
                    $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
                    $selenium->seleniumOptions->userAgent = null;

                    break;

                case 9:
                    $selenium->useChromePuppeteer();
                    $selenium->seleniumOptions->userAgent = null;

                    break;

                case 10:
                    $selenium->useFirefoxPlaywright();
//                    $selenium->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
//                    $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
                    $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['linux']];
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
                    $selenium->seleniumOptions->userAgent = null;

                    break;

                case 11:
                    $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
                    $selenium->seleniumOptions->userAgent = null;

                    break;
            }

            if (!$proxySelected) {
                $proxy = $this->selectProxy($selenium);
            }

            $this->http->setProxyParams($selenium->http->getProxyParams());

//            $selenium->disableImages();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $result = $this->innerLogin($selenium, $retry, $firefox, $proxySwitchAttempts);
            $this->logger->debug("[seleniumURL]: {$this->seleniumURL}");
            /*
             * [Current selenium URL]: https://www.marriott.com/aries-auth/loginWithCredentials.comp
             * {"status":"LEGACY_SUCCESS","sessionToken":"52993AE8-8314-5A72-B410-F10E64776381","redirect":false,"componentIdentifier":"submit.login"}
             */
            if (!$result && $this->http->FindPreg("/\{\"status\":\"LEGACY_SUCCESS\",\"sessionToken\":\"/")
                && !stristr($this->seleniumURL, 'https://www.marriott.com/aries-auth/loginWithCredentials.comp')) {
                $this->logger->notice("provider bug fix");
                $this->sendNotification("loginWithCredentials debug");
                $selenium->http->removeCookies();
                $selenium->http->GetURL("https://www.marriott.com/signIn.mi");
                $result = $this->innerLogin($selenium, $retry, $firefox);
                $this->logger->debug("[seleniumURL]: {$this->seleniumURL}");
            }

            if (
                (
                    in_array($this->seleniumURL, [
                        self::ERROR_URL,
                        'https://www.marriott.com/sign-in-error.mi?returnTo=/sign-in.mi?returnTo=%2Floyalty%2FmyAccount%2Fdefault.mi',
                        'https://www.marriott.com/loyalty/myAccount/force-change-password.mi',
                    ])
                    || strstr($this->seleniumURL, 'https://www.marriott.com/loyalty/myAccount/send-otp-challenge.mi')
                )
                && $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "skeleton-loader")]'), 0)
            ) {
                $this->logger->error("site to long loading");
                throw new CheckRetryNeededException(3, 0);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $aCookie) {
                $this->http->setCookie($aCookie['name'], $aCookie['value'], $aCookie['domain'], $aCookie['path'], $aCookie['expiry'] ?? null);
            }

            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current selenium URL]: {$this->seleniumURL}");

            // refs #19994
            if ($this->seleniumURL == 'https://www.marriott.com/default.mi') {
                $this->DebugInfo = "https://www.marriott.com/default.mi";

                try {
                    $selenium->http->GetURL('https://www.marriott.com/loyalty/myAccount/default.mi');
                } catch (Facebook\WebDriver\Exception\UnknownErrorException | Facebook\WebDriver\Exception\WebDriverException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                }
                $selenium->waitForElement(WebDriverBy::xpath(self::LOGOUT_XPATH), 5, false);
                // save page to logs
                $this->savePageToLogs($selenium);
            }

            $this->logger->debug("[Current selenium URL]: {$this->seleniumURL}");
            // 404 Not Found
            if (stristr($this->seleniumURL, 'https://www.marriott.com/aries-auth/loginWithCredentials.comp')
                && $selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "404 Not Found")]'), 0)) {
                throw new CheckRetryNeededException(4, 10, self::PROVIDER_ERROR_MSG);
            }
            // no auth, no errors, request has been blocked
            if (
                (stristr($this->seleniumURL, 'https://www.marriott.com/sign-in-error.mi?transaction=login')
                || stristr($this->seleniumURL, 'https://www.marriott.com/sign-in-error.mi?returnTo='))
                || $this->http->FindSingleNode('//button[@data-testid="sign-in-btn-submit"]/div[@data-testid="loading-spinner"]/@data-testid')
                // refs #25232
                || $this->http->FindSingleNode('//div[contains(@class, "m-message-content")]/div[@role]/p[contains(., "We can’t process your request")]')
                && !$this->checkLoginErrors()
            ) {
                $retry = true;
                $this->DebugInfo = 'request has been blocked';
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
            }

            // auth not thru passed, spg accounts bug fix
            $spgXpath = '//h2[contains(text(), "It\'s time to switch to your new 9-digit member number:")]';

            if (!$result && $this->seleniumURL == 'https://www.marriott.com/sign-in.mi'
                && ($selenium->waitForElement(WebDriverBy::xpath($spgXpath), 3) || $this->http->FindSingleNode($spgXpath))) {
                $retry = true;
            }

            return !$retry;
        } catch (ScriptTimeoutException | TimeOutException | Facebook\WebDriver\Exception\TimeoutException | WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')
                || strstr($e->getMessage(), 'timeout')
                || strstr($e->getMessage(), 'JSON decoding of remote response failed')
                || strstr($e->getMessage(), 'page.goto: Timeout ')
                || strstr($e->getMessage(), 'ERR_TUNNEL_CONNECTION_FAILED')
                || strstr($e->getMessage(), 'NS_ERROR_UNKNOWN_HOST')
            ) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (SessionNotCreatedException $e) {
            $this->logger->error("SessionNotCreatedException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if (
                $this->http->FindPreg('/session\s*not\s*created\s*exception\s*from\s*unknown\s*error/ims', false, $e->getMessage())
                || strstr($e->getMessage(), 'Unable to create session from org.openqa.selenium.remote.NewSessionPayload')
            ) {
                $retry = true;
            }
        }// catch (SessionNotCreatedException $e)
        catch (UnrecognizedExceptionException $e) {
            $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            $retry = true;
        }// catch (ScriptTimeoutException $e)
        catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            $retry = true;
        }// catch (ScriptTimeoutException $e)
        catch (
            NoSuchDriverException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\ElementNotInteractableException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\WebDriverException
            | UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception 3: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            StatLogger::getInstance()->info("marriott login attempt", [
                "success"        => !$retry,
                "proxy"          => $proxy,
                "browser"        => $selenium->seleniumRequest->getBrowser() . ":" . $selenium->seleniumRequest->getVersion(),
                "userAgentStr"   => $selenium->http->userAgent,
                "resolution"     => $selenium->seleniumOptions->resolution[0] . "x" . $selenium->seleniumOptions->resolution[1],
                "attempt"        => $this->attempt,
                "isWindows"      => stripos($this->http->userAgent, 'windows') !== false,
                "config"         => $config,
            ]);
            $selenium->http->cleanup(); //todo

            if ($retry) {
                $selenium->markProxyAsInvalid();
            }

            if (
                $retry
                && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            ) {
                // hard code: no errors, no auth
                // very strange AccountID: 5350092
                if (in_array($this->AccountFields['Login'], [
                    '014113377',
                    'ashnovotny@gmail.com',
                    'Jsmith@massmentors.org',
                    '194101887',
                    'salmansju@gmail.com',
                    '262100824',
                    '171170884',
                    'douglasdaigle@gmail.com',
                    'nikiandmike2715@gmail.com',
                    'shaurav.datta@gmail.com',
                ])
                ) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if ($this->attempt == 2) {
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;
                }

                throw new CheckRetryNeededException(3, 7);
            }
        }

        return false;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Marriott.com is currently unavailable.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Marriott.com is currently unavailable.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We’re temporarily unable to display the information you requested.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We’re temporarily unable to display the information you requested.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // This service is temporarily unavailable. But don't worry, we're on it. Please try again later.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "This service is temporarily unavailable. But don\'t worry, we\'re on it. Please try again later.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Technical difficulties
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'We are experiencing technical difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, we can't find the page you requested
        if ($message = $this->http->FindSingleNode('
                //h1[
                    contains(text(), "Sorry, we can\'t find the page you requested")
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable - Zero size object
        if ($message = $this->http->FindPreg("/The server is temporarily unable to service your request\.\s*Please try again\s*later\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            // Error 404
            $this->http->FindPreg("/(Error 404)/ims")
            // An error occurred while processing your request.
            || $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")
            || ($this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]") && $this->http->Response['code'] == 500)
            || ($this->http->FindSingleNode("//h1[contains(text(), 'Application is not available')]") && $this->http->Response['code'] == 503)
            // Error 500: java.lang.Error: Error: could not match input
            || ($this->http->FindPreg("/Error 500: java.lang.Error: Error: could not match input/") && $this->http->Response['code'] == 500)
            || $this->http->FindSingleNode("//h1[contains(text(), 'SRVE0232E: Internal Server Error.')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '503 Service Temporarily Unavailable')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '504 Gateway Time-out')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '503 Service Unavailable')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->logger->debug("[URL]: " . $this->http->currentUrl());
        $this->logger->debug("[CODE]: " . $this->http->Response['code']);
        // Change Password
        if ($this->http->FindPreg("/Change Password/") && $this->http->currentUrl() == 'https://www.marriott.com/rewards/myAccount/forceChangePassword.mi') {
            throw new CheckException("Marriott Rewards website is asking you to change your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if (
            in_array($this->http->Response['code'], [0, 403])
            || empty($this->http->Response['body'])
            || $this->http->FindSingleNode('//*[self::h1 or self::span][contains(text(), "This site can’t be reached")]')
        ) {
            if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
                return false;
            }

            // do not use banned proxy
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }
        // retries
        if ($this->http->FindPreg("/^<pre[^>]+><\/pre>$/") || $this->http->FindPreg("/^<head><\/head><body><pre[^>]+><\/pre><\/body>$/")) {
            throw new CheckRetryNeededException(4, 7);
        }

        $this->http->GetURL('https://www.marriott.com/loyalty/myAccount/default.mi');
        // site maintenance
        if ($this->http->FindSingleNode('//td[contains(text(), "unable to display your account information")]')) {
            throw new CheckException('We\'re sorry. We are unable to display your account information online at this time. Please try again later. Thank you for your patience.', ACCOUNT_PROVIDER_ERROR);
        }
        // This service is temporarily unavailable. But don't worry, we're on it. Please try again later.
        if ($this->http->FindPreg('/>\s*This service is temporarily unavailable\. But don\&apos;t worry, we\&apos;re on it\. Please try again later\.\s*<a/')) {
            throw new CheckException('This service is temporarily unavailable. But don\'t worry, we\'re on it. Please try again later.', ACCOUNT_PROVIDER_ERROR);
        }

        // refs #13993 retries
        $this->logger->debug("[URL]: " . $this->http->currentUrl());
        $this->logger->debug("[CODE]: " . $this->http->Response['code']);

        // debug
        if (stristr($this->http->currentUrl(), 'https://www.marriott.com/sign-in.mi')) {
            $this->logger->debug("url matched");
        }

        if ($this->http->ParseForm('signInForm') || $this->http->ParseForm(null, "//div[contains(@class, 'StyledSignInContainerDiv')]/form")) {
            $this->logger->debug("form matched");
        }

        if (empty($this->http->Response['body'])) {
            $this->logger->debug("empty body");
        }
        $this->logger->debug("body: " . strlen($this->http->Response['body']));
        // debug
        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            && (stristr($this->http->currentUrl(), 'https://www.marriott.com/sign-in.mi') || stristr($this->http->currentUrl(), 'https://www.marriott.com/signIn.mi'))
            && $this->http->Response['code'] == 200
            && (
                $this->http->ParseForm('signInForm')
                || $this->http->ParseForm(null, "//div[contains(@class, 'StyledSignInContainerDiv')]/form")
            )
        ) {
            // hard code: no errors, no auth
            // very strange AccountID: 3184765, 582667
            if (in_array($this->AccountFields['Login'], [
                'neerajdani@gmail.com',
                '114897549',
                'lucas@crandall.ch',
                'nrdani1',
                '756330635',
                '014113377',
                'gregory.joseph.brown@gmail.com',
                'dwieringa@gmail.com',
                '037360476',
                'senkup@gmail.com',
                'Davidamar1@hotmail.com',
                'dmasciorini@gmail.com',
                'trkhillyer@gmail.com',
                'spatrizcc1@gmail.com',
                '983201617',
                '193394295',
                '100381792',
                '254472683',
                'MR.BILL.KEENE@GMAIL.COM',
                'gmpalowitch@gmail.com',
                '869962654',
                '610221566',
                '864029947',
                'lakeview611@yahoo.com',
                '581444085',
                'ashnovotny@gmail.com',
                'Jsmith@massmentors.org',
                '194101887',
                'salmansju@gmail.com',
                'vlksmrtprst@yahoo.com',
                'ambersterr@gmail.com',
                '197888300',
                'shaurav.datta@gmail.com',
                '940563174',
            ])) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            throw new CheckRetryNeededException(4, 10);
        }

        // 502 Bad Gateway
        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            && stristr($this->http->currentUrl(), 'https://www.marriott.com/sign-in.mi') && $this->http->Response['code'] == 502
            && ($this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]') || empty($this->http->Response['body']))) {
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
        }

        // retries, no errors on the page
        if ($this->seleniumURL == 'https://www.marriott.com/sign-in-error.mi?transaction=login') {
            if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
                return false;
            }

            throw new CheckRetryNeededException(3);
        }

        $this->DebugInfo = "attempt {$this->attempt}";

        return false;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        //		if (!$this->http->PostForm())
        //			return $this->checkErrors();

        if ($this->loginSuccessful()) {
            $this->markProxySuccessful();

            return true;
        }

        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'Confirm your identity')] | //h2[contains(text(), 'Confirm Your Identity')]")
            && $this->parseQuestion()
        ) {
            return false;
        }

        if (
            stristr($this->seleniumURL, 'otp')
            || stristr($this->http->currentUrl(), 'otp')
        ) {
            $this->logger->debug("2fa");

            if ($message = $this->http->FindSingleNode('//div[contains(text(), "We\'re sorry, the server is unable to complete the verification process at this time.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode('//div[contains(text(), "There is no valid contact method on your account to send a verification code.")]')) {
                throw new CheckException($message." For assistance, contact the customer support center.", ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        if ($this->checkLoginErrors()) {
            return false;
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $email =
            $this->http->FindSingleNode('//p[@id = "email-icon-text"]')
        ;
        $phone =
            $this->http->FindSingleNode('//input[@id = "phoneButtonClickedMT"]/following-sibling::label[@for="phoneButtonClickedMT"]')
            ?? $this->http->FindSingleNode('//input[@id = "phoneButtonClickedHT"]/following-sibling::label[@for = "phoneButtonClickedHT"]')
            ?? $this->http->FindSingleNode('//input[@id = "transTypeMT"]/following-sibling::p')
            ?? $this->http->FindSingleNode('//input[@id = "transTypeHT"]/following-sibling::p')
            ?? $this->http->FindSingleNode('//input[@id = "transTypeBT"]/following-sibling::p')
        ;
        $phoneValue =
            $this->http->FindSingleNode('//input[@id = "phoneButtonClickedMT"]/@value')
            ?? $this->http->FindSingleNode('//input[@id = "phoneButtonClickedHT"]/@value')
            ?? $this->http->FindSingleNode('//input[@id = "transTypeMT"]/@data-value')
            ?? $this->http->FindSingleNode('//input[@id = "transTypeHT"]/@data-value')
            ?? $this->http->FindSingleNode('//input[@id = "transTypeBT"]/@data-value')
        ;

        if (!$this->http->ParseForm("otpActionForm") || (!isset($email) && !isset($phone))) {
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "Your passcode cannot be sent since you recently changed the email address and phone number(s) associated with this account.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $email = $this->http->FindSingleNode('//span[contains(text(), "Email code to")]', null, true, "/code\s*to\s*([^<]+)/ims");
            $phone =
                $this->http->FindSingleNode('//input[@id = "number_MT"]/following-sibling::label/span', null, true, "/code\s*to\s*([^<]+)/ims")
                ?? $this->http->FindSingleNode('//input[@id = "number_BT"]/following-sibling::label/span', null, true, "/code\s*to\s*([^<]+)/ims")
                ?? $this->http->FindSingleNode('//input[@id = "number_HT"]/following-sibling::label/span', null, true, "/code\s*to\s*([^<]+)/ims")
            ;
            $phoneValue =
                $this->http->FindSingleNode('//input[@id = "number_MT"]/@value', null, true, "/number_(.+)/")
                ?? $this->http->FindSingleNode('//input[@id = "number_BT"]/@value', null, true, "/number_(.+)/")
                ?? $this->http->FindSingleNode('//input[@id = "number_HT"]/@value', null, true, "/number_(.+)/")
            ;
            $this->logger->debug("phoneValue: {$phoneValue}");

            if (!isset($email) && !isset($phone)) {
                return false;
            }

            // prevent code spam    // refs #6042
            if (
                $this->isBackgroundCheck()
                && (
                    !$this->getWaitForOtc()
                    || ($this->getWaitForOtc() && !$email)
                )
            ) {
                $this->Cancel();
            }

            if ($email) {
                $data = [
                    "mfaOption"       => "OTP_EMAIL",
                    "resendOtp"       => false,
                    "emailAddress"    => $email,
                    "phoneNumber"     => "",
                    "phoneNumberType" => "",
                    "returnUrl"       => "/loyalty/myAccount/default.mi",
                ];
            } elseif ($phone) {
                $data = [
                    "mfaOption"       => "OOB_SMS",
                    "resendOtp"       => false,
                    "emailAddress"    => "",
                    "phoneNumber"     => $phone,
                    "phoneNumberType" => $phoneValue ?? "MT",
                    "returnUrl"       => "/loyalty/myAccount/default.mi",
                ];
            }
            $headers = [
                "Origin"       => "https://www.marriott.com",
                "Referer"      => "https://www.marriott.com/loyalty/myAccount/send-otp-challenge.mi",
                "Accept"       => "application/json, text/plain, */*",
                "Content-Type" => "application/json",
            ];
            $this->http->PostURL("https://www.marriott.com/mi/phoenix-account-auth/v1/generateOneTimePassword", json_encode($data), $headers);
            $response = $this->http->JsonLog();

            if (!isset($response->status) || $response->status != 'SUCCESS') {
                return false;
            }

            if ($email) {
                $question = "Please enter the code that has been sent to your registered email ({$email})";
            } elseif ($phone) {
                $question = "Please enter the code that has been sent to your registered phone number ending in: {$phone}"; /*review*/ // 'Please enter the code that has been sent to your registered phone number ending in: 57801'
            }
            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question_v2";

            return false;
        }

        // prevent code spam    // refs #6042
        if (
            $this->isBackgroundCheck()
            && (
                !$this->getWaitForOtc()
                || ($this->getWaitForOtc() && !$email)
            )
        ) {
            $this->Cancel();
        }

        if ($email) {
            $this->http->SetInputValue("transType", "OOB_EMAIL");
        } elseif ($phone) {
            $this->http->SetInputValue("transType", "OOB_SMS");
            $this->http->SetInputValue("phoneButtonClicked", $phoneValue ?? "MT");
        }
        $headers = [
            "Origin"  => "https://www.marriott.com",
            "Referer" => "https://www.marriott.com/loyalty/myAccount/send-otp-challenge.mi",
        ];
        $this->http->PostForm($headers);

        // it helps sometimes
        if ($this->http->Response['code'] === 502) {
            throw new CheckRetryNeededException(4, 0);
        }

        $question = $this->http->FindSingleNode("
            //label[
                contains(text(), 'Enter the code from the email:')
                or contains(text(), 'Enter the code from the SMS/text:')
            ]
            | //p[contains(text(), 'We sent a verification code via ')]
        ");

        if (!$this->http->ParseForm("otpActionForm") || !isset($question)) {
            // AccountID: 1506286
            if ($this->http->currentUrl() == 'https://www.marriott.com/profile/systemMaintenance.mi?type=AccountAccess') {
                if ($message = $this->http->FindSingleNode('//div[contains(text(), "Account Sign In is temporarily unavailable. We apologize for the inconvenience. Thank you for your patience.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
            }
            // AccountID: 6867948
            if ($this->http->currentUrl() == 'https://www.marriott.com/profile/systemMaintenance.mi?type=OTP') {
                if ($message = $this->http->FindSingleNode('//div[contains(text(), "We\'re sorry, the server is unable to complete the verification process at this time. Please try again later.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
            }

            return true;
        }

        if ($email) {
            $question = "Please enter the code that has been sent to your registered email ({$email})";
        } elseif ($phone) {
            $question = "Please enter the code that has been sent to your registered phone number ending in: {$phone}"; /*review*/ // 'Please enter the code that has been sent to your registered phone number ending in: 57801'
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        if ($step == "Question_v2") {
            $data = [
                "otpValue"   => $answer,
                "returnUrl"  => "/loyalty/myAccount/default.mi",
                "errorUrl"   => "",
                "rememberMe" => "true",
            ];
            $headers = [
                "Origin"       => "https://www.marriott.com",
                "Referer"      => "https://www.marriott.com/loyalty/myAccount/send-otp-challenge.mi",
                "Accept"       => "application/json, text/plain, */*",
                "Content-Type" => "application/json",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.marriott.com/mi/phoenix-account-auth/v1/validateOneTimePassword", json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (!isset($response->status) || $response->status != 'SUCCESS') {
                if (isset($response->message) && $response->message == 'verificationIncorrectErrorMessage') {
                    $this->AskQuestion($this->Question, "Incorrect verification code.", $step);
                }

                return false;
            }// if (!isset($response->status) || $response->status != 'SUCCESS')

            $this->http->GetURL('https://www.marriott.com/loyalty/myAccount/default.mi');

            return true;
        }

        $this->http->SetInputValue("otpcode", $answer);
        $headers = [
            "Origin"  => "https://www.marriott.com",
            "Referer" => "https://www.marriott.com/loyalty/myAccount/gen-otp.mi",
        ];

        if (!$this->http->PostForm($headers)) {
            return false;
        }
        // Incorrect temporary code
        if ($error = $this->http->FindSingleNode("//li[contains(text(), 'Incorrect temporary code')]")) {
            $this->AskQuestion($this->Question, $error, 'Question');

            return false;
        }

        // Change Password
        if ($this->http->FindSingleNode("//h1[contains(., 'Change Password')] | //p[contains(text(), 'In a continuing effort to safeguard your account, we are requiring you to change your password.')]")
            && stristr($this->http->currentUrl(), 'force-change-password.mi')) {
            throw new CheckException("Marriott Rewards website is asking you to change your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        // refs #16418
        if ($this->http->currentUrl() == 'https://www.marriott.com/sign-in-error.mi') {
            if (
                $this->http->FindSingleNode('//div[not(contains(@class, "l-display-none"))]/span[contains(text(), "Email/member number and/or password")]')
                // refs #19779
                || in_array($this->AccountFields['Login'], [
                    "27565477",
                ])
            ) {
                throw new CheckException("Unfortunately, we receive error messages from the Marriott website, please verify that you are able to login to your account directly and that the two-factor authentication works fine for you.", ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }
        // refs #19779
        if ($this->http->currentUrl() == 'https://www.marriott.com/error.mi') {
            if ($this->http->FindSingleNode('//h1[contains(text(), "Something happened")]')) {
                throw new CheckException("Unfortunately, the Marriott website returns an error message after entering verification code, so we are unable to access your information and update the account.", ACCOUNT_PROVIDER_ERROR);
            }

            $this->sendNotification("refs #19779 check sq // RR");

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $this->logger->debug("[Selenium URL]: {$this->seleniumURL}");

        // 2fa wrong redirect workaround
        if ($this->http->currentUrl() == 'https://www.marriott.com/default.mi') {
            $this->http->GetURL("https://www.marriott.com/loyalty/myAccount/default.mi");
        }
        $sessionToken = $this->http->FindPreg("/\"sessionId\":\s*\"([^\"]+)/");

        // Balance
        if (
            $this->ErrorMessage != "Your old member number and username will be deactivated in early 2019."
            // refs #18508
            && !$this->http->FindSingleNode('//div[contains(@id, "NightDetails")]//p[contains(text(), "We’re temporarily unable to display the information you requested.")]')
            && !$this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'm-rewards-member-points') and not(contains(text(), 'Point'))]"))
        ) {
            $this->SetBalance($this->http->FindPreg("/\"mr_prof_points_balance\":\s*\"([^\"]+)/"));
        }
        // Qualification Period
        $this->SetProperty("YearBegins", strtotime("1 JAN"));
        // Nights this year
        $this->SetProperty("Nights", $this->http->FindSingleNode("//div[contains(text(), 'NIGHTS THIS YEAR')]/preceding-sibling::div[1] | //div[@data-testid=\"nightsdetail\"]//div[a[contains(text(), \"Nights This Year\")]]/preceding-sibling::div", null, true, '/([0-9]+)/ims'));

        if (!isset($this->Properties['Nights'])) {
            $this->SetProperty("Nights", $this->http->FindPreg("/\"mr_prof_nights_booked_this_year\":\s*\"([^\"]+)/"));
        }
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//p[contains(text(), 'Member since')]", null, true, '/since\s*([^<]+)/ims'));

        if (!isset($this->Properties['MemberSince'])) {
            $this->SetProperty("MemberSince", $this->http->FindPreg("/\"mr_prof_join_date\":\s*\"([^\-\"]+)/"));
        }
        // Rewards #
        $this->SetProperty("Number",
            $this->http->FindPreg("/\"mr_id\":\s*\"([^\"]+)/")
            ?? $this->http->FindPreg("/\"rewardsId\":\s*\"([^\"]+)/")
        );
        // Name
        if ($name = $this->http->FindPreg("/\"mr_prof_name_full\":\s*\"([^\"]+)/")) {
            $this->SetProperty("Name", beautifulName($name));
        }

        // refs #16853
        // Ambassador Qualifying Dollars
        $ambassadorQualifyingDollars = $this->http->FindSingleNode('//div[contains(@class, "elite_main_guage")]//p[contains(text(), "this year")]', null, true, "/\+\s*(.+)\s+USD this year/ims");
        $this->SetProperty("AmbassadorQualifyingDollars", isset($ambassadorQualifyingDollars) ? '$' . $ambassadorQualifyingDollars : '');
        // Ambassador Qualifying Nights
        $this->SetProperty("AmbassadorQualifyingNights", $this->http->FindSingleNode('//div[contains(@class, "elite_main_guage")]//p[contains(text(), "this year")]', null, true, "/^\s*(\d+)\s*Night/ims"));

        /**
         * We’re temporarily unable to display the information you requested.
         * Please try again later.
         */
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($message = $this->http->FindSingleNode('//div[contains(@id, "NightDetails")]//p[contains(text(), "We’re temporarily unable to display the information you requested.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Something happened
            if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Something happened")]')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // Lifetime Membership  // refs #16853, refs #16853, https://redmine.awardwallet.com/issues/16930#note-15
        $this->SetProperty("LifetimeMembership", $this->http->FindSingleNode("//*[self::span or self::h2][contains(text(), 'You are Lifetime') or contains(text(), 'You’ve earned Lifetime')]", null, true, '/Lifetime\s*([^<]+)/ims'));

        if (isset($this->Properties['LifetimeMembership'])) {
            $this->Properties['LifetimeMembership'] = str_replace(' status', '', $this->Properties['LifetimeMembership']);
            $this->logger->info('Lifetime properties', ['Header' => 3]);
            // Your lifetime statistics
            $this->http->GetURL("https://www.marriott.com/loyalty/myAccount/lifeTimeNightDetails.mi?_=" . date("UB"));
            // Nights (Lifetime Nights)
            $this->SetProperty("LifetimeNights", $this->http->FindSingleNode("//p[contains(text(), 'Nights:')]", null, true, '/:\s*(\d+)/ims'));
            // Years as Silver, Gold or Platinum
            $this->SetProperty("YearsAsSilver", $this->http->FindSingleNode("//p[contains(text(), 'Years as Silver, Gold or Platinum:')]", null, true, '/:\s*(\d+)/ims'));
            // Years as Gold or Platinum
            $this->SetProperty("YearsAsGold", $this->http->FindSingleNode("//p[contains(text(), 'Years as Gold or Platinum:')]", null, true, '/:\s*(\d+)/ims'));
            // Years as Platinum
            $this->SetProperty("YearsAsPlatinum", $this->http->FindSingleNode("//p[contains(text(), 'Years as Platinum:')]", null, true, '/:\s*(\d+)/ims'));
        }

        /*
        // refs #14648
        $this->logger->info('Zip Code', ['Header' => 3]);
        $this->logger->debug('ZipCodeParseDate: ' . ($this->State['ZipCodeParseDate'] ?? null));
        $this->logger->debug('Time: ' . strtotime("-1 month"));

        if (
            !isset($this->State['ZipCodeParseDate'])
            || $this->State['ZipCodeParseDate'] < strtotime("-1 month")
        ) {
            $headers = [
                "X-Requested-With" => "XMLHttpRequest",
                "Accept"           => "text/html, *
        /*; q=0.01",
                "Referer"          => "https://www.marriott.com/loyalty/myAccount/profile.mi",
            ];
            $this->http->GetURL("https://www.marriott.com/loyalty/myAccount/editPersonalInformation.mi?_=" . date("UB"), $headers);
            $zip = $this->http->FindSingleNode("//input[@id = 'postal-code']/@value");
            $country = $this->http->FindSingleNode('//select[@id = "country"]//option[@selected]/@value');

            if ($country == 'US' && strlen($zip) == 9) {
                $zipCode = substr($zip, 0, 5) . " " . substr($zip, 5);
            } else {
                $zipCode = $zip;
            }
            $this->SetProperty("ZipCode", $zipCode);
            $street = $this->http->FindSingleNode("//input[@id = 'street1']/@value");

            if ($zipCode && $street) {
                $this->SetProperty("ParsedAddress", preg_replace(
                    '/(, ){2,}/',
                    ', ',
                    $street
                    . ", " . $this->http->FindSingleNode("//input[@id = 'city']/@value")
                    . ", " . $this->http->FindPreg("/\"mr_prof_address_state_abbr\":\s*\"([^\"]+)/")
                    . ", " . $zipCode
                    . ", " . $country
                ));
            }// if ($zipCode)
            $this->State['ZipCodeParseDate'] = time();
        }// if (!isset($this->State['ZipCodeParseDate']) || $this->State['ZipCodeParseDate'] > strtotime("-1 month"))
        */

        $this->http->GetURL(self::HISTORY_PAGE_URL);

        // refs #22044, it helps
        if ($this->http->Response['code'] == 502) {
            sleep(5);
            $this->http->GetURL(self::HISTORY_PAGE_URL);
        }

        // may be 95 chrome / 502 and 403 issue
        if ($this->http->Response['code'] != 200) {
            throw new CheckRetryNeededException(4, 0);
        }

        // session was lost
        if ($this->http->FindSingleNode("//form[@name='signInForm']")) {
            $this->logger->notice("session was lost");

            throw new CheckRetryNeededException(3, 5);
        }

        $this->logger->info('Expiration date', ['Header' => 3]);
        // Expiration date      // refs #10278
        $this->SetProperty("LastActivity", $this->http->FindSingleNode("//div[contains(@class, 't-activity-heading') and contains(., 'last qualifying activity')]", null, true, "/was\s*([^\.]+)/"));
        // Your account will remain active and points won’t expire as long as you stay or use some of your points by ...
        // or
        // Don't let your points expire. Stay with us or use some of your points by ... to keep your account active.
        $exp = $this->http->FindSingleNode('(//p[contains(text(), "expire as long as you stay") or contains(text(), "Don\'t let your points expire.")])[1]', null, true, "/oints\s*by\s*([^\.\s]+)/");
        $this->logger->debug("Exp date: {$exp}");

        if ($exp = strtotime($exp)) {
            $this->SetExpirationDate($exp);
        }
        // refs #16930, https://redmine.awardwallet.com/issues/16930#note-4
        else {
            if (
                !isset($this->Properties['LastActivity'])
                && ($lastActivity = $this->http->FindSingleNode("//div[contains(@class, \"tile-activity-grid\")]//div[contains(@class, \"l-row\") and not(contains(@class, \"headers\")) and
                    (
                    div[*[contains(@class, 'l-description')]
                           and not(contains(., 'POINTS TRANSFERRED'))
                           and not(contains(., 'POINTS ADDED VIA TRANSFER'))
                           and not(contains(., 'POINT TRANSFER WITH OTHER ACCOUNT'))
                           and not(contains(., 'MRPoints'))
                           and not(contains(., 'MarriottBonvoyPoints'))
                           and not(contains(., 'Points Reinstatement'))
                           and not(contains(., 'Points Expiration'))
                           and not(contains(., 'Cancelled 0 Rewards'))
                       ]
                    or p[contains(@class, 'l-description')
                           and not(contains(., 'POINTS TRANSFERRED'))
                           and not(contains(., 'POINTS ADDED VIA TRANSFER'))
                           and not(contains(., 'POINT TRANSFER WITH OTHER ACCOUNT'))
                           and not(contains(., 'MRPoints'))
                           and not(contains(., 'MarriottBonvoyPoints'))
                           and not(contains(., 'Points Reinstatement'))
                           and not(contains(., 'Points Expiration'))
                           and not(contains(., 'Cancelled 0 Rewards'))
                       ]
                   )
                ][1]/p[contains(@class, 'post-date')]"))
            ) {// div[p[contains(@class, 'l-description')]] | div[a[contains(@class, 'l-description')]]
                // p[contains(@class, 'l-description')]
                $this->SetProperty("LastActivity", $lastActivity);
            }
            // refs #16853, https://redmine.awardwallet.com/issues/16930#note-15
            if (!empty($this->Properties['LifetimeMembership'])) {
                $this->SetExpirationDateNever();
                $this->ClearExpirationDate();
                $this->SetProperty("AccountExpirationWarning", "do not expire with elite status");
            }
            // refs #16930, https://redmine.awardwallet.com/issues/16930#note-41
            elseif ($this->http->FindSingleNode("//div[contains(@class, 't-activity-heading') and contains(., 'last qualifying activity')]", null, true, "/was\s*([^\.]+)/")) {
                $lastActivity = $this->http->FindSingleNode("//div[contains(@class, 't-activity-heading') and contains(., 'last qualifying activity')]", null, true, "/was\s*([^\.]+)/");
                $exp = strtotime($lastActivity);
                // https://redmine.awardwallet.com/issues/16930#note-21
                $exp = strtotime("+24 month", $exp);

                // TODO: refs #16930, https://help.marriott.com/s/article/Article-24119
                $this->SetProperty("AccountExpirationWarning", 'The balance on this award program due to expire on ' . date("m/d/Y", $exp) . '
<br />
<br />
Marriott Rewards on their website state that <a href="https://www.marriott.com/loyalty/terms/default.mi" target="_blank">&quot;Members must remain active in the Loyalty Program to retain Points they accumulate. If a Member Account is inactive for twenty-four (24) consecutive months, that Member Account will forfeit all accumulated Points. Members can remain active in the Loyalty Program and retain accumulated Points by earning Points or Miles in the Loyalty Program or redeeming Points in the Loyalty Program at least once every twenty-four (24) months, subject to the exceptions described below&quot;</a>.
<br />
<br />
i. Not all Points activities help maintain active status in the Loyalty Program. The following activities do not count toward maintaining an active status in the Loyalty Program:
<br />
A. Gifting or transferring Points; however, converting Points to Miles or Miles to Points does count toward maintaining an active status;<br />
B. Receiving Points as a gift or transfer; or
<br />
C. Earning Points through social media programs, such as #MarriottBonvoyPoints.
<br />
<br />
We determined that your latest valid activity date was on ' . $lastActivity . ', so the expiration date for your account balance was calculated by adding 24 months to this date.');
                $this->SetExpirationDate($exp);
            }
        }




        // refs #23625
        if ($consumerID = $this->http->FindPreg("/consumerID\":\"([^\"]+)/")) {
            $this->customerId = $consumerID;

            $headers = [
                "Accept"                       => "*/*",
                "Accept-Language"              => "en-US",
                "Accept-Encoding"              => "gzip, deflate, br",
                "Content-Type"                 => "application/json",
            ];
            $data = '{"sessionToken":"'.$sessionToken.'","context":{"context":{"localeKey":"en_GB"}}}';
            $this->http->PostURL("https://www.marriott.com/hybrid-presentation/api/v1/getUserDetails", $data, $headers);
            $userDetails = $this->http->JsonLog();
            // Name
            $this->SetProperty("Name", beautifulName($userDetails->headerSubtext->consumerName ?? ''));
            // Level
            $this->SetProperty("Level", beautifulName($userDetails->userProfileSummary->level ?? ''));
            // Lifetime Titanium Elite
            $this->SetProperty("LifetimeMembership", beautifulName(str_replace('Lifetime ', '',
                $userDetails->userProfileSummary->eliteLifetimelevelDescription ?? '')));
            // Balance
            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                $this->logger->notice("set balance from json");
                $this->SetBalance($userDetails->userProfileSummary->currentPoints);
            }

            $headers = [
                "Accept"                       => "*/*",
                "Accept-Language"              => "en-US",
                "Accept-Encoding"              => "gzip, deflate, br",
                "Content-Type"                 => "application/json",
                "apollographql-client-name"    => "phoenix_account",
                "apollographql-client-version" => "v1",
                "x-request-id"                 => "phoenix_account-d3acd6cf-7441-46d4-b683-50e5878c255e",
                "application-name"             => "account",
                "graphql-require-safelisting"  => "true",
                "graphql-operation-signature"  => "bdf6686a7fc0fbf318133a1a286d992d96470e80605aae3d1154d6e827aa3ecb",
            ];
            $this->http->PostURL("https://www.marriott.com/mi/query/phoenixAccountGetMyActivityTable", '{"operationName":"phoenixAccountGetMyActivityTable","variables":{"customerId":"' . $consumerID . '","numberOfMonths":25,"types":"all","limit":1000,"offset":0,"filter":null},"query":"query phoenixAccountGetMyActivityTable($customerId: ID!, $numberOfMonths: Int, $limit: Int, $sort: String, $offset: Int, $types: String, $filter: AccountActivityFilterInput) {\n  customer(id: $customerId) {\n    loyaltyInformation {\n      accountActivity(\n        numberOfMonths: $numberOfMonths\n        limit: $limit\n        sort: $sort\n        offset: $offset\n        types: $types\n        filter: $filter\n      ) {\n        total\n        edges {\n          node {\n            postDate\n            ... on LoyaltyAccountActivity {\n              totalEarning\n              baseEarning\n              eliteEarning\n              extraEarning\n              isQualifyingActivity\n              actions {\n                actionDate\n                totalEarning\n                type {\n                  code\n                  description\n                  __typename\n                }\n                __typename\n              }\n              currency {\n                code\n                __typename\n              }\n              partner {\n                account\n                type {\n                  code\n                  description\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            type {\n              code\n              description\n              __typename\n            }\n            description\n            ... on LoyaltyAccountAwardActivity {\n              awardType {\n                code\n                __typename\n              }\n              __typename\n            }\n            startDate\n            endDate\n            properties {\n              id\n              policies {\n                daysFolioAvailableOnline\n                __typename\n              }\n              basicInformation {\n                bookable\n                brand {\n                  id\n                  __typename\n                }\n                name\n                nameInDefaultLanguage\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        pageInfo {\n          hasNextPage\n          hasPreviousPage\n          previousOffset\n          currentOffset\n          nextOffset\n          __typename\n        }\n        __typename\n      }\n      rewards {\n        lastQualifyingActivityDate\n        datePointsExpire\n        vistanaPointsExpired\n        number\n        isExempt\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}', $headers);
            $response = $this->http->JsonLog();
            $this->accountActivity = $response->data->customer->loyaltyInformation->accountActivity->edges ?? [];

            // Expiration date      // refs #10278
            $this->SetProperty("LastActivity", $response->data->customer->loyaltyInformation->rewards->lastQualifyingActivityDate ?? null);
            // Your account will remain active and points won’t expire as long as you stay or use some of your points by ...
            // or
            // Don't let your points expire. Stay with us or use some of your points by ... to keep your account active.
            $exp = $response->data->customer->loyaltyInformation->rewards->datePointsExpire ?? null;
            $this->logger->debug("Exp date: {$exp}");

            if ($exp = strtotime($exp)) {
                $this->SetExpirationDate($exp);
            }

            // refs #16853, https://redmine.awardwallet.com/issues/16930#note-15
            if (!empty($this->Properties['LifetimeMembership'])) {
                $this->SetExpirationDateNever();
                $this->ClearExpirationDate();
                $this->SetProperty("AccountExpirationWarning", "do not expire with elite status");
            }

            // subAccounts - Unused Rewards Certificates
            $this->logger->info('Unused Rewards Certificates', ['Header' => 3]);

            $headers = [
                "Accept"                       => "*/*",
                "Accept-Language"              => "en-US",
                "Accept-Encoding"              => "gzip, deflate, br",
                "Content-Type"                 => "application/json",
                "apollographql-client-name"    => "phoenix_account",
                "apollographql-client-version" => "v1",
                "x-request-id"                 => "phoenix_account-146bd539-dba6-45f4-8580-3e1f4995b005",
                "application-name"             => "account",
                "graphql-require-safelisting"  => "true",
                "graphql-operation-signature"  => "6972f5f1f0e93b306f14c3c7d5cb5a6f14b3f567eece51a6b0d18db2cc8e3d7c",
            ];
            $this->http->PostURL("https://www.marriott.com/mi/query/phoenixAccountGetMyActivityRewardsEarned", '{"operationName":"phoenixAccountGetMyActivityRewardsEarned","variables":{"customerId":"' . $consumerID . '"},"query":"query phoenixAccountGetMyActivityRewardsEarned($customerId: ID!) {\n  customer(id: $customerId) {\n    loyaltyInformation {\n      suiteNightAwards {\n        available {\n          count\n          details {\n            issueDate\n            expirationDate\n            count\n            __typename\n          }\n          __typename\n        }\n        expired {\n          count\n          details {\n            issueDate\n            expirationDate\n            count\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      certificates {\n        total\n        edges {\n          node {\n            awardType {\n              code\n              label\n              description\n              enumCode\n              __typename\n            }\n            expirationDate\n            isCancellable\n            issueDate\n            numberOfNights\n            points\n            __typename\n          }\n          __typename\n        }\n        status {\n          ... on ResponseStatus {\n            __typename\n            code\n            httpStatus\n            messages {\n              user {\n                type\n                id\n                field\n                message\n                details\n                __typename\n              }\n              ops {\n                type\n                id\n                field\n                message\n                details\n                __typename\n              }\n              dev {\n                type\n                id\n                field\n                message\n                details\n                __typename\n              }\n              __typename\n            }\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}', $headers);
            $response = $this->http->JsonLog();
            $edges = $response->data->customer->loyaltyInformation->certificates->edges ?? [];
            $this->logger->debug("Total " . count($edges) . " Unused Rewards Certificates were found");

            $subAccounts = [];
            $this->SetProperty("CombineSubAccounts", false);

            foreach ($edges as $edge) {
                $subAcc = [];
                $displayName = $edge->node->awardType->description;
                $subAcc['ExpirationDate'] = strtotime($edge->node->expirationDate);
                $code = "marriott" . md5(str_replace(' ', '', $displayName)) . $subAcc['ExpirationDate'];
                $subAcc['Code'] = $code;
                $subAcc['DisplayName'] = "Certificate: " . $displayName;
                $subAcc['Balance'] = null;
                $subAccounts = $this->groupCertificates($subAccounts, $subAcc);
            }// foreach ($edges as $edge)

            $this->logger->info('Nightly Upgrade Awards', ['Header' => 3]);

            $suiteNightAwards = $response->data->customer->loyaltyInformation->suiteNightAwards->available->details ?? [];
            $this->logger->debug("Total " . count($suiteNightAwards) . " Nightly Upgrade Awards were found");

            foreach ($suiteNightAwards as $suiteNightAward) {
                $subAcc = [];
                $displayName = "Nightly Upgrade Awards - {$suiteNightAward->count} ";
                $subAcc['ExpirationDate'] = strtotime($suiteNightAward->expirationDate);
                $code = "marriottNightlyUpgradeAwards" . md5(str_replace(' ', '', $displayName)) . $subAcc['ExpirationDate'];
                $subAcc['Code'] = $code;
                $subAcc['DisplayName'] = $displayName . ($suiteNightAward->count == 1 ? "Night" : "Nights");
                $subAcc['Balance'] = null;
                $subAccounts = $this->groupCertificates($subAccounts, $subAcc);
            }// foreach ($suiteNightAwards as $suiteNightAward)

            foreach ($subAccounts as $subAccount) {
                $this->AddSubAccount($subAccount, true);
            }


            // refs #23909
            $this->http->GetURL('https://www.marriott.com/loyalty/myAccount/nights.mi');
            // Continue as Silver Elite: Stay 10 more nights by December 31.
            $this->SetProperty("NightsNeededToRetainTier",
                $this->http->FindSingleNode('//span[contains(text(),"Continue as ")]/following-sibling::span',
                    null, false, '/Stay (\d+) more nights/'));
            // Unlock more benefits: Stay 25 more nights by December 31 to reach Gold Elite.
            $this->SetProperty("NightsUntilNextTier",
                $this->http->FindSingleNode('//span[contains(text(),"Unlock more benefits:")]/following-sibling::span',
                    null, false, '/Stay (\d+) more nights/'));
            // 250 Total Nights + 5 years as Silver Elite or higher
            $totalNightsReach = $this->http->FindSingleNode('//p[contains(text(),"Total Nights +")]', null, false, '/(\d+) Total Nights \+/');
            $totalNights = $this->http->FindSingleNode('//p[contains(text(),"Total Nights:")]/span', null, false, '/^(\d+)$/');
            if (isset($totalNightsReach, $totalNights)) {
                $this->SetProperty("NightsUntilNextLifetimeTier", $totalNightsReach - $totalNights);
            }

            // 250 Total Nights + 5 years as Silver Elite or higher
            $years = $this->http->FindSingleNode('//p[contains(text(),"Total Nights +")]', null, false, '/\+ (\d+) years as/');
            $yearsSilver = $this->http->FindSingleNode('//p[contains(text(),"Years as Silver Elite:")]/span', null, false, '/^(\d+)$/');
            $yearsGold = $this->http->FindSingleNode('//p[contains(text(),"Years as Gold Elite:")]/span', null, false, '/^(\d+)$/');
            $yearsPlatinum = $this->http->FindSingleNode('//p[contains(text(),"Years as Platinum Elite:")]/span', null, false, '/^(\d+)$/');
            if (isset($years, $yearsSilver, $yearsGold, $yearsPlatinum)) {
                $sumYears = $years - $yearsSilver - $yearsGold - $yearsPlatinum;
                if ($sumYears > 0) {
                    $this->SetProperty("StatusYearsUntilNextLifetimeTier", $sumYears);
                }
            }


            // refs#24688
            $headers['x-request-id'] = 'phoenix_account-be00d1d4-d238-4ad8-a3fb-568cbbb63500';
            $headers['graphql-operation-signature'] = '5f22916f33ab30251e999ff2957d31dd9245416d695292631a83076e5dc1496d';
            $this->http->PostURL("https://www.marriott.com/mi/query/phoenixAccountGetMemberStatusDetails", '{"operationName":"phoenixAccountGetMemberStatusDetails","variables":{"customerId":"' . $consumerID . '","startYear":2024},"query":"query phoenixAccountGetMemberStatusDetails($customerId: ID!, $startYear: Int) {\n  customer(id: $customerId) {\n    profile {\n      name {\n        givenName\n        __typename\n      }\n      __typename\n    }\n    contactInformation {\n      emails {\n        address\n        primary\n        __typename\n      }\n      phones {\n        country {\n          code\n          __typename\n        }\n        number\n        __typename\n      }\n      __typename\n    }\n    loyaltyInformation {\n      rewards {\n        currentPointBalance\n        eliteLifetimeNights\n        eliteLifetimeLevel {\n          code\n          __typename\n        }\n        level {\n          code\n          __typename\n        }\n        levelType {\n          code\n          __typename\n        }\n        nextLevelType {\n          code\n          __typename\n        }\n        nextLevel {\n          code\n          __typename\n        }\n        number\n        eliteNightsToAchieveNext\n        eliteNightsToRenewCurrent\n        datePointsExpire\n        __typename\n      }\n      rewardsSummary {\n        yearly(startYear: $startYear) {\n          totalNights\n          stayNights\n          year\n          totalRevenue\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}', $headers);
            $response = $this->http->JsonLog();

            foreach ($response->data->customer->loyaltyInformation->rewardsSummary->yearly ?? [] as $item) {
                if ($item->__typename === 'RewardYearlySummary') {
                    // Annual Qualifying Spend: 142 of $23,000 USD
                    $this->SetProperty("AnnualQualifyingSpend", $item->totalRevenue);
                    break;
                }
            }

            return;
        }

        $nodes = $this->http->XPath->query('//div[contains(@class, "tile-unused-certificates")]//li[p[starts-with(normalize-space(text()), "Expires")] or p[span[starts-with(normalize-space(text()), "Expires")]]]');
        $this->logger->debug("Total {$nodes->length} Unused Rewards Certificates were found");
        $subAccounts = [];

        if ($nodes->length > 0) {
            $this->SetProperty("CombineSubAccounts", false);

            for ($i = 0; $i < $nodes->length; $i++) {
                $subAcc = [];
                $displayName =
                    $this->http->FindSingleNode("p[contains(@class, 'l-display-block')]/span/span", $nodes->item($i))
                    ?? $this->http->FindSingleNode("p[contains(@class, 'l-display-block')]/span", $nodes->item($i))
                    ?? $this->http->FindSingleNode("p/span[contains(@class, 'l-display-block')]", $nodes->item($i))
                ;
                $subAcc['ExpirationDate'] = strtotime($this->http->FindSingleNode("p[starts-with(normalize-space(text()), 'Expires')] | p/span[starts-with(normalize-space(text()), 'Expires')]", $nodes->item($i), false, '/Expires:?\s+(.+)/'));
                $code = "marriott" . md5(str_replace(' ', '', $displayName)) . $subAcc['ExpirationDate'];
                $subAcc['Code'] = $code;
                $subAcc['DisplayName'] = "Certificate: " . $displayName;
                $subAcc['Balance'] = null;
            }// for ($i = 0; $i < $nodes->length; $i++)

            foreach ($subAccounts as $subAccount) {
                $this->AddSubAccount($subAccount, true);
            }
        }// if ($nodes->length > 0)
    }

    public function groupCertificates($subAccounts, $subAcc)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $duplicate = false;
        $this->logger->debug("Adding Certificate...");
        $this->logger->debug(var_export($subAcc, true), ['pre' => true]);

        if (empty($subAccounts)) {
            $subAccounts[] = $subAcc;
        } else {
            foreach ($subAccounts as $subAccount) {
                if (isset($subAccount['Code']) && $subAccount['Code'] == $subAcc['Code']) {
                    $duplicate = true;

                    if ($subAccount['Balance'] == null) {
                        $subAccount['Balance'] = 2;
                    } else {
                        $subAccount['Balance']++;
                    }
                }// if (isset($subAccount['Code']) && $subAccount['Code'] == $subAcc['Code'])
                $result[] = $subAccount;
            }// foreach($this->Properties['DetectedCards'] as $subAccount)
            $subAccounts = $result;

            if (!$duplicate) {
                $subAccounts[] = $subAcc;
            }
        }
        $this->logger->debug("Certificates:");
        $this->logger->debug(var_export($subAccounts, true), ['pre' => true]);

        return $subAccounts;
    }

    public function ParseItineraries()
    {

        $result = [];
        $headers = [
            "Accept"                       => "*/*",
            "Accept-Language"              => "en-US",
            "Accept-Encoding"              => "gzip, deflate, br",
            "Content-Type"                 => "application/json",
            "apollographql-client-name"    => "phoenix_account",
            "apollographql-client-version" => "v1",
            "x-request-id"                 => "phoenix_account-d3acd6cf-7441-46d4-b683-50e5878c255e",
            "application-name"             => "account",
            "graphql-require-safelisting"  => "true",
            "graphql-operation-signature"  => "21eb6e0c22a6c2b5fc1b5c34c6ac2e335cb02e9c5a47ff22e8445ccc7b6ba9f2",
        ];
        $offset = 0;

        do {
            $this->logger->debug("[Offset: $offset]");
            $this->http->PostURL("https://www.marriott.com/mi/query/phoenixAccountGetUpcomingTripsOfCustomer", '{"operationName":"phoenixAccountGetUpcomingTripsOfCustomer","variables":{"customerId":"'.$this->customerId.'","status":"ACTIVE","limit":10,"offset":'.$offset.'},"query":"query phoenixAccountGetUpcomingTripsOfCustomer($customerId: ID!, $status: OrderStatus, $limit: Int, $offset: Int) {\n  customer(id: $customerId) {\n    orders(status: $status, limit: $limit, offset: $offset) {\n      edges {\n        node {\n          id\n          items {\n            basicInformation {\n              startDate\n              endDate\n              confirmationNumber\n              __typename\n            }\n            property {\n              id\n              __typename\n            }\n            awardRequests {\n              status {\n                code\n                __typename\n              }\n              type {\n                code\n                __typename\n              }\n              __typename\n            }\n            stay {\n              status {\n                code\n                description\n                enumCode\n                label\n                __typename\n              }\n              stayStatus {\n                code\n                description\n                __typename\n              }\n              __typename\n            }\n            id\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      total\n      __typename\n    }\n    __typename\n  }\n}\n"}', $headers);
            $edges = $this->http->JsonLog()->data->customer->orders->edges ?? [];
            $this->logger->info(sprintf('Found %s itineraries', count($edges)));
            if ($offset == 0 && count($edges) == 0 && $this->http->FindPreg('/,"edges":\[\],"total":0/')) {
                $this->itinerariesMaster->setNoItineraries(true);
                return [];
            }
            $offset += 10;
            foreach ($edges as $edge) {
                $item = $edge->node->items[0];
                $confirmationNumber = $item->basicInformation->confirmationNumber;
                $propertyId = $item->property->id;
                $this->http->GetURL("https://www.marriott.com/reservation/findReservationDetail.mi?confirmationNumber={$confirmationNumber}&tripId={$edge->node->id}&propertyId={$confirmationNumber}");
                $this->http->GetURL("https://www.marriott.com/mi/phoenix-book-preprocessor/v1/findReservationDetail?confirmationNumber={$confirmationNumber}&tripId={$confirmationNumber}&propertyId={$propertyId}");
                $this->getItinerary($confirmationNumber, $propertyId);
            }
            if ($offset >= 10) {
                // refs #24949
                if (in_array($this->AccountFields['Login'], ['116231932'])) {
                    $this->logger->debug('special case of account 116231932; increaseTimeLimit -> 100');
                    $this->increaseTimeLimit(100);
                } else {
                    $this->logger->debug('increaseTimeLimit -> 60');
                    $this->increaseTimeLimit(60);    
                }
            }
        } while ($offset < 100 && count($edges) > 0);

        return $result;
    }

    private function getItinerary($confirmationNumber, $propertyId) {
        $this->logger->notice(__METHOD__);

        $miDataLayer = $this->http->FindSingleNode("//script[@id='miDataLayer']", null, false,
            '/var dataLayer = (.+?); var mvpOffers =/');
        $miDataLayer = $this->http->JsonLog($miDataLayer, 1);

        $headers = [
            "Accept" => "*/*",
            "Content-Type" => "application/json",
            "Accept-Language" => "en-US",
            "Accept-Encoding" => "gzip, deflate, br",
            "apollographql-client-name" => "phoenix_book",
            "apollographql-client-version" => "1",
            "graphql-operation-name" => "PhoenixBookHotelHeaderData",
            "graphql-require-safelisting" => "true",
            "graphql-operation-signature" => "018b971a06886d6d2ca3f77747bf9f9c2cc8d49638bf25a1c3429277c4627c9f",
        ];
        $this->http->PostURL("https://www.marriott.com/mi/query/PhoenixBookHotelHeaderData",
            '{"operationName":"PhoenixBookHotelHeaderData","variables":{"propertyId":"' . $propertyId . '"},"query":"query PhoenixBookHotelHeaderData($propertyId: ID!) {\n  property(id: $propertyId) {\n    id\n    basicInformation {\n      latitude\n      longitude\n      name\n      brand {\n        id\n        __typename\n      }\n      __typename\n    }\n    reviews {\n      numberOfReviews {\n        count\n        description\n        __typename\n      }\n      stars {\n        count\n        description\n        __typename\n      }\n      __typename\n    }\n    contactInformation {\n      contactNumbers {\n        number\n        type {\n          description\n          code\n          __typename\n        }\n        __typename\n      }\n      address {\n        line1\n        line2\n        line3\n        city\n        stateProvince {\n          description\n          __typename\n        }\n        country {\n          description\n          code\n          __typename\n        }\n        postalCode\n        __typename\n      }\n      __typename\n    }\n    ... on Hotel {\n      seoNickname\n      __typename\n    }\n    media {\n      primaryImage {\n        edges {\n          node {\n            imageUrls {\n              wideHorizontal\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}',
            $headers);
        $hotel = $this->http->JsonLog(null, 1, false, 'property');

        $headers = [
            "Accept" => "*/*",
            "Content-Type" => "application/json",
            "Accept-Language" => "en-US",
            "Accept-Encoding" => "gzip, deflate, br",
            "apollographql-client-name" => "phoenix_book",
            "apollographql-client-version" => "1",
            "graphql-operation-name" => "PhoenixBookAuthRoomOverviewDetails",
            "graphql-require-safelisting" => "true",
            "graphql-operation-signature" => "3f603341dcff4d9757011a1c67c09e42dfd174b0c94a98b86e561ac94709b277",
        ];
        $this->http->PostURL("https://www.marriott.com/mi/query/PhoenixBookAuthRoomOverviewDetails",
            '{"operationName":"PhoenixBookAuthRoomOverviewDetails","variables":{"orderId":"' . $confirmationNumber . '","customerId":"' . $this->customerId . '"},"query":"query PhoenixBookAuthRoomOverviewDetails($orderId: ID!, $customerId: ID!) {\n  order(id: $orderId) {\n    items {\n      stay {\n        estimatedTimeOfArrival\n        __typename\n      }\n      comments {\n        comment\n        __typename\n      }\n      addOns {\n        category {\n          code\n          description\n          label\n          __typename\n        }\n        ordinal\n        code: id\n        description\n        addOnStatus: status {\n          code\n          description\n          label\n          __typename\n        }\n        __typename\n      }\n      basicInformation {\n        product {\n          ... on HotelRoom {\n            rates {\n              name\n              localizedName {\n                translatedText\n                __typename\n              }\n              __typename\n            }\n            roomAttributes {\n              attributes {\n                description\n                category {\n                  code\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        Id\n        confirmationNumber\n        isCancellable\n        isModifiable\n        inHouse\n        isOTA\n        isRedemption\n        lengthOfStay\n        oldRates\n        creationDate\n        __typename\n      }\n      totalPricing {\n        childAges\n        quantity\n        numberInParty\n        numberOfAdults\n        numberOfChildren\n        rateAmountsByMode {\n          pointsPerQuantity {\n            points\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      product {\n        id\n        ... on HotelRoom {\n          media {\n            photoTour {\n              images {\n                captions {\n                  title\n                  __typename\n                }\n                metadata {\n                  imageFile\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          availableAddOns {\n            addOns {\n              numberOfRequestSlots\n              id\n              startDate\n              endDate\n              description\n              category {\n                code\n                label\n                description\n                __typename\n              }\n              status {\n                code\n                label\n                description\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          basicInformation {\n            name\n            oldRates\n            startDate\n            endDate\n            type\n            description\n            __typename\n          }\n          termsAndConditions {\n            rules {\n              descriptions\n              type {\n                code\n                description\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          rateDetails {\n            id\n            ratePlanType {\n              code\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      property {\n        ... on Hotel {\n          policies {\n            smokefree\n            petsPolicyDescription\n            localizedPetsPolicyDescription {\n              translatedText\n              __typename\n            }\n            petsAllowed\n            petsPolicyDetails {\n              additionalPetFee\n              additionalPetFeeType\n              refundableFee\n              refundableFeeType\n              nonRefundableFee\n              nonRefundableFeeType\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        basicInformation {\n          currency\n          isMarshaProperty\n          gmtOffset\n          __typename\n        }\n        transportation {\n          type {\n            code\n            __typename\n          }\n          name\n          __typename\n        }\n        __typename\n      }\n      id\n      guests {\n        primaryGuest {\n          name {\n            givenName\n            surname\n            title {\n              code\n              label\n              description\n              __typename\n            }\n            __typename\n          }\n          emails {\n            address\n            primary\n            __typename\n          }\n          phones {\n            number\n            type {\n              code\n              label\n              description\n              __typename\n            }\n            __typename\n          }\n          addresses {\n            type {\n              code\n              label\n              description\n              __typename\n            }\n            primary\n            line1\n            line2\n            city\n            stateProvince\n            postalCode\n            country {\n              code\n              label\n              description\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        rewardsNumber\n        __typename\n      }\n      awardRequests {\n        type {\n          code\n          description\n          __typename\n        }\n        status {\n          code\n          description\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    id\n    __typename\n  }\n  customer(id: $customerId) {\n    revisionToken\n    loyaltyInformation {\n      rewards {\n        number\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}',
            $headers);
        $room = $this->http->JsonLog(null, 1, false, 'items');

        $headers = [
            "Accept" => "*/*",
            "Content-Type" => "application/json",
            "Accept-Language" => "en-US",
            "Accept-Encoding" => "gzip, deflate, br",
            "apollographql-client-name" => "phoenix_book",
            "apollographql-client-version" => "1",
            "graphql-operation-name" => "PhoenixBookSummaryOfChargesAuth",
            "graphql-require-safelisting" => "true",
            "graphql-operation-signature" => "afc0a549aac2274802eb139547eafa359702049c236b7480708a8bf182caf612",
        ];
        $this->http->PostURL("https://www.marriott.com/mi/query/PhoenixBookSummaryOfChargesAuth",
            '{"operationName":"PhoenixBookSummaryOfChargesAuth","variables":{"orderId":"' . $confirmationNumber . '","customerId":"' . $this->customerId . '","propertyId":"phxps"},"query":"query PhoenixBookSummaryOfChargesAuth($orderId: ID!, $customerId: ID!, $propertyId: ID!) {\n  order(id: $orderId) {\n    items {\n      product {\n        ... on HotelRoom {\n          rateDetails {\n            segment\n            startDate\n            lengthOfStay\n            rateAmounts {\n              amount {\n                origin {\n                  value: amount\n                  valueDecimalPoint\n                  __typename\n                }\n                __typename\n              }\n              freeNights\n              freeNightsPoints\n              points\n              rateMode {\n                code\n                __typename\n              }\n              __typename\n            }\n            name\n            isFreeNight\n            ratePlanType {\n              code\n              __typename\n            }\n            isGuestViewable\n            isRedemption\n            fnaTopOffPoints\n            certificateNumber\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      totalPricing {\n        quantity\n        rateAmounts {\n          amount {\n            origin {\n              value: amount\n              valueDecimalPoint\n              __typename\n            }\n            __typename\n          }\n          rateMode {\n            code\n            __typename\n          }\n          points\n          __typename\n        }\n        fees {\n          rateAmounts {\n            rateUnit {\n              code\n              description\n              __typename\n            }\n            amount {\n              origin {\n                value: amount\n                valueDecimalPoint\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          costType {\n            description\n            __typename\n          }\n          __typename\n        }\n        isGuestViewable\n        __typename\n      }\n      basicInformation {\n        confirmationNumber\n        lengthOfStay\n        isRedemption\n        __typename\n      }\n      property {\n        basicInformation {\n          currency\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    rateAmounts {\n      amount {\n        origin {\n          value: amount\n          valueDecimalPoint\n          currency\n          __typename\n        }\n        __typename\n      }\n      points\n      __typename\n    }\n    isGuestViewable\n    __typename\n  }\n  customer(id: $customerId) {\n    loyaltyInformation {\n      rewards {\n        currentPointBalance\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  property(id: $propertyId) {\n    transportation {\n      type {\n        code\n        __typename\n      }\n      name\n      __typename\n    }\n    __typename\n  }\n}\n"}',
            $headers);
        $summary = $this->http->JsonLog(null, 1, false, 'items');
        $this->parseItinerary($miDataLayer, $hotel, $room, $summary);
    }

    private function getItineraryForConfNo($confirmationNumber, $propertyId, $tripsXRequestedByHeader) {
        $this->logger->notice(__METHOD__);

        $miDataLayer = $this->http->FindSingleNode("//script[@id='miDataLayer']", null, false,
            '/var dataLayer = (.+?); var mvpOffers =/');
        $miDataLayer = $this->http->JsonLog($miDataLayer, 1);

        $headers = [
            "Accept" => "*/*",
            "Content-Type" => "application/json",
            "Accept-Language" => "en-US",
            "Accept-Encoding" => "gzip, deflate, br",
            "apollographql-client-name" => "phoenix_book",
            "apollographql-client-version" => "1",
            "graphql-operation-name" => "PhoenixBookHotelHeaderData",
            "graphql-require-safelisting" => "true",
            "graphql-operation-signature" => "018b971a06886d6d2ca3f77747bf9f9c2cc8d49638bf25a1c3429277c4627c9f",
            "X-Requested-By" => $tripsXRequestedByHeader
        ];
        $this->http->PostURL("https://www.marriott.com/mi/query/PhoenixBookHotelHeaderData",
            '{"operationName":"PhoenixBookHotelHeaderData","variables":{"propertyId":"' . $propertyId . '"},"query":"query PhoenixBookHotelHeaderData($propertyId: ID!) {\n  property(id: $propertyId) {\n    id\n    basicInformation {\n      latitude\n      longitude\n      name\n      brand {\n        id\n        __typename\n      }\n      __typename\n    }\n    reviews {\n      numberOfReviews {\n        count\n        description\n        __typename\n      }\n      stars {\n        count\n        description\n        __typename\n      }\n      __typename\n    }\n    contactInformation {\n      contactNumbers {\n        number\n        type {\n          description\n          code\n          __typename\n        }\n        __typename\n      }\n      address {\n        line1\n        line2\n        line3\n        city\n        stateProvince {\n          description\n          __typename\n        }\n        country {\n          description\n          code\n          __typename\n        }\n        postalCode\n        __typename\n      }\n      __typename\n    }\n    ... on Hotel {\n      seoNickname\n      __typename\n    }\n    media {\n      primaryImage {\n        edges {\n          node {\n            imageUrls {\n              wideHorizontal\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}',
            $headers);
        $hotel = $this->http->JsonLog(null, 1, false, 'property');

        $headers = [
            "Accept" => "*/*",
            "Content-Type" => "application/json",
            "Accept-Language" => "en-US",
            "Accept-Encoding" => "gzip, deflate, br",
            "apollographql-client-name" => "phoenix_book",
            "apollographql-client-version" => "1",
            "graphql-operation-name" => "PhoenixBookRoomOverviewDetails",
            "graphql-require-safelisting" => "true",
            "graphql-operation-signature" => "93bfcebc36dbfae94cacb783b235cf94be65e05159d45a3cf92698a1a90e0084",
            "X-Requested-By" => $tripsXRequestedByHeader
        ];
        $this->http->PostURL("https://www.marriott.com/mi/query/PhoenixBookRoomOverviewDetails",
            '{"operationName":"PhoenixBookRoomOverviewDetails","variables":{"orderId":"'.$confirmationNumber.'"},"query":"query PhoenixBookRoomOverviewDetails($orderId: ID!) {\n  order(id: $orderId) {\n    items {\n      stay {\n        estimatedTimeOfArrival\n        __typename\n      }\n      comments {\n        comment\n        __typename\n      }\n      notifications {\n        deliveryMethods {\n          recipient\n          deliveryMethod {\n            code\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      addOns {\n        category {\n          code\n          description\n          label\n          __typename\n        }\n        ordinal\n        code: id\n        description\n        addOnStatus: status {\n          code\n          description\n          label\n          __typename\n        }\n        __typename\n      }\n      basicInformation {\n        product {\n          ... on HotelRoom {\n            rates {\n              name\n              localizedName {\n                translatedText\n                __typename\n              }\n              __typename\n            }\n            roomAttributes {\n              attributes {\n                description\n                category {\n                  code\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        Id\n        confirmationNumber\n        isCancellable\n        isModifiable\n        inHouse\n        isOTA\n        isRedemption\n        lengthOfStay\n        oldRates\n        creationDate\n        __typename\n      }\n      totalPricing {\n        childAges\n        quantity\n        numberInParty\n        numberOfAdults\n        numberOfChildren\n        rateAmountsByMode {\n          pointsPerQuantity {\n            points\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      product {\n        id\n        ... on HotelRoom {\n          media {\n            photoTour {\n              images {\n                captions {\n                  title\n                  __typename\n                }\n                metadata {\n                  imageFile\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          availableAddOns {\n            addOns {\n              numberOfRequestSlots\n              id\n              startDate\n              endDate\n              description\n              category {\n                code\n                label\n                description\n                __typename\n              }\n              status {\n                code\n                label\n                description\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          basicInformation {\n            name\n            oldRates\n            startDate\n            endDate\n            type\n            description\n            freeCancellationUntil\n            __typename\n          }\n          termsAndConditions {\n            rules {\n              descriptions\n              type {\n                code\n                description\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          rateDetails {\n            id\n            ratePlanType {\n              code\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      property {\n        ... on Hotel {\n          policies {\n            smokefree\n            petsPolicyDescription\n            localizedPetsPolicyDescription {\n              translatedText\n              __typename\n            }\n            petsAllowed\n            petsPolicyDetails {\n              additionalPetFee\n              additionalPetFeeType\n              refundableFee\n              refundableFeeType\n              nonRefundableFee\n              nonRefundableFeeType\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        basicInformation {\n          currency\n          isMarshaProperty\n          gmtOffset\n          __typename\n        }\n        transportation {\n          type {\n            code\n            __typename\n          }\n          name\n          __typename\n        }\n        __typename\n      }\n      id\n      guests {\n        primaryGuest {\n          name {\n            givenName\n            surname\n            title {\n              code\n              label\n              description\n              __typename\n            }\n            __typename\n          }\n          emails {\n            address\n            primary\n            __typename\n          }\n          phones {\n            number\n            type {\n              code\n              label\n              description\n              __typename\n            }\n            __typename\n          }\n          addresses {\n            type {\n              code\n              label\n              description\n              __typename\n            }\n            primary\n            line1\n            line2\n            city\n            stateProvince\n            postalCode\n            country {\n              code\n              label\n              description\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        rewardsNumber\n        __typename\n      }\n      awardRequests {\n        type {\n          code\n          description\n          __typename\n        }\n        status {\n          code\n          description\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    id\n    __typename\n  }\n}\n"}',
            $headers);
        $room = $this->http->JsonLog(null, 1, false, 'items');

        $headers = [
            "Accept" => "*/*",
            "Content-Type" => "application/json",
            "Accept-Language" => "en-US",
            "Accept-Encoding" => "gzip, deflate, br",
            "apollographql-client-name" => "phoenix_book",
            "apollographql-client-version" => "1",
            "graphql-operation-name" => "PhoenixBookSummaryOfChargesAuth",
            "graphql-require-safelisting" => "true",
            "graphql-operation-signature" => "614c83ced6f29395e64999b8debe6e608d83a96a38df640bdb00c6673b5aa8dc",
            "X-Requested-By" => $tripsXRequestedByHeader
        ];
        $this->http->PostURL("https://www.marriott.com/mi/query/PhoenixBookSummaryOfChargesUnAuth",
            '{"operationName":"PhoenixBookSummaryOfChargesUnAuth","variables":{"orderId":"'.$confirmationNumber.'","propertyId":"'.$propertyId.'"},"query":"query PhoenixBookSummaryOfChargesUnAuth($orderId: ID!, $propertyId: ID!) {\n  order(id: $orderId) {\n    items {\n      product {\n        ... on HotelRoom {\n          rateDetails {\n            segment\n            startDate\n            lengthOfStay\n            rateAmounts {\n              amount {\n                origin {\n                  value: amount\n                  valueDecimalPoint\n                  __typename\n                }\n                __typename\n              }\n              freeNights\n              freeNightsPoints\n              points\n              rateMode {\n                code\n                __typename\n              }\n              __typename\n            }\n            name\n            isFreeNight\n            ratePlanType {\n              code\n              __typename\n            }\n            isGuestViewable\n            isRedemption\n            fnaTopOffPoints\n            certificateNumber\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      totalPricing {\n        quantity\n        rateAmounts {\n          amount {\n            origin {\n              value: amount\n              valueDecimalPoint\n              __typename\n            }\n            __typename\n          }\n          rateMode {\n            code\n            __typename\n          }\n          points\n          __typename\n        }\n        fees {\n          rateAmounts {\n            rateUnit {\n              code\n              description\n              __typename\n            }\n            amount {\n              origin {\n                value: amount\n                valueDecimalPoint\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          costType {\n            description\n            __typename\n          }\n          __typename\n        }\n        isGuestViewable\n        __typename\n      }\n      basicInformation {\n        confirmationNumber\n        lengthOfStay\n        isRedemption\n        __typename\n      }\n      property {\n        basicInformation {\n          currency\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    rateAmounts {\n      amount {\n        origin {\n          value: amount\n          valueDecimalPoint\n          currency\n          __typename\n        }\n        __typename\n      }\n      points\n      __typename\n    }\n    isGuestViewable\n    __typename\n  }\n  property(id: $propertyId) {\n    transportation {\n      type {\n        code\n        __typename\n      }\n      name\n      __typename\n    }\n    __typename\n  }\n}\n"}',
            $headers);
        $summary = $this->http->JsonLog(null, 1, false, 'items');
        $this->parseItinerary($miDataLayer, $hotel, $room, $summary);
    }


    public function parseItinerary($miDataLayer, $hotel, $room, $summary)
    {
        $this->logger->notice(__METHOD__);

        $hotel = $hotel->data->property ?? null;
        $items = $room->data->order->items ?? [];

        if (empty($items)) {
            $this->logger->error("checkin info not found");

            return;
        }
        $h = $this->itinerariesMaster->add()->hotel();

        $confNo = [];
        foreach ($items as $room) {
            if (isset($room->product)) {
                if (!empty($room->product->basicInformation->name)) {
                    $r = $h->addRoom();
                    $r->setDescription("{$room->product->basicInformation->name} {$room->product->basicInformation->description}");
                }
                $h->general()->confirmation($room->basicInformation->confirmationNumber, "Confirmation Number");
                $confNo[] = $room->basicInformation->confirmationNumber;
            }
        }
        $this->logger->info('Parse Itinerary #' . join(', ', $confNo), ['Header' => 3]);

        $h->booked()->guests($miDataLayer->numberOfAdults ?? null, false, true);
        //$h->general()->confirmation($confNo, "Confirmation Number", true);

        $guests = $items[0]->guests ?? [];

        foreach ($guests as $guest) {
            $h->general()->traveller(beautifulName("{$guest->primaryGuest->name->givenName} {$guest->primaryGuest->name->surname}"));
        }

        // Address
        $address = $hotel->contactInformation->address->line1 ?? null;
        if (isset($hotel->contactInformation->address->city)) {
            $address .= ", {$hotel->contactInformation->address->city}";
        }
        if (isset($hotel->contactInformation->address->postalCode)) {
            $address .= ", {$hotel->contactInformation->address->postalCode}";
        }
        if (isset($hotel->contactInformation->address->country->description)) {
            $address .= ", {$hotel->contactInformation->address->country->description}";
        }
        $h->hotel()
            ->name($hotel->basicInformation->name ?? null)
            ->address($address);

        $contactNumbers = $hotel->contactInformation->contactNumbers ?? [];

        foreach ($contactNumbers as $phone) {
            if ($phone->type->code == 'phone') {
                $h->hotel()->phone($phone->number);
            }
            if ($phone->type->code == 'fax') {
                $h->hotel()->fax($phone->number);
            }
        }

        $h->booked()
            ->checkIn2($items[0]->product->basicInformation->startDate ?? $items[1]->product->basicInformation->startDate)
            ->checkOut2($items[0]->product->basicInformation->endDate ?? $items[1]->product->basicInformation->endDate);

        $currency = $tax = $total = $valueDecimalPoint = null;
        if (isset($summary->data)) {
            $items = $summary->data->order->items ?? [];
            foreach ($items as $item) {
                $currency = $item->property->basicInformation->currency;
                foreach ($item->totalPricing->rateAmounts as $rateAmount) {
                    if ($rateAmount->rateMode->code == 'advance-purchase-amount') {
                        $total += $rateAmount->amount->origin->value;
                    }
                    if ($rateAmount->rateMode->code == 'total-taxes-per-quantity') {
                        $tax += $rateAmount->amount->origin->value;
                        $valueDecimalPoint = $rateAmount->amount->origin->valueDecimalPoint;
                    }
                }
            }
        }

        if (isset($currency, $valueDecimalPoint)) {
            $h->price()->currency($currency);
            $precision = pow(10, $valueDecimalPoint);
            $h->price()->total(round($total / $precision, 2));
            $h->price()->tax(round($tax / $precision, 2));
        }


        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
            "CheckInDate" => [
                "Type"     => "date",
                "Required" => true,
                "Size"     => 40,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.marriott.com/reservation/lookupReservation.mi";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $sessionData = $this->CheckConfirmationNumberInternalSelenium($arFields);
        $sessionData = $this->http->JsonLog($sessionData);

        if ($error = $this->http->FindSingleNode('//div[contains(., "Correction needed")]/span')) {
            return $error;
        }

        if ($error = $this->http->FindSingleNode("//div[@id = 'form-action-messages' and starts-with(normalize-space(),'Reservation not')]")) {
            return $error;
        }

        if ($error = $this->http->FindSingleNode('//div[contains(., "We cannot locate your reservation")]/span')) {
            return strip_tags($error);
        }

        $propertyId = $this->http->FindPreg('/"propertyId":\s*"(.+?)",/');
        $this->getItineraryForConfNo($arFields['ConfNo'], $propertyId, $sessionData->cacheData->data->AriesReservation->tripsXRequestedByHeader ?? null);

        return null;
    }




    public function GetHistoryColumns()
    {
        return [
            "Type"                => "Info",
            "Date Posted"         => "PostingDate",
            "Description"         => "Description",
            "Points earned"       => "Miles",
            "Bonus points earned" => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->http->FilterHTML = true;
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $this->http->GetURL(self::HISTORY_PAGE_URL);

        $dataFromJson = $this->http->FindSingleNode('//script[contains(text(), "window.MI_S2_RESOURCE_BASE_URL")]', null, true, "/window.makenComponents\s*=\s*(.+)\/\/\]\]>$/");

        $page = 0;
        //		do {
        $page++;
        $this->logger->debug("[Page: {$page}]");
        //			if ($page > 1) {
        //				$this->http->NormalizeURL($url);
        //				$this->http->GetURL($url);
        //			}
        $startIndex = sizeof($result);
        $resultFromHTML = array_merge($result, $this->ParseHistoryPage($startIndex, $startDate));

        if ($dataFromJson) {
            $result = array_merge($result, $this->ParseHistoryJSON($dataFromJson, $startIndex, $startDate));
        }

        // refs #18481 - if history to small, only several transactions
        if (!$dataFromJson || (count($resultFromHTML) > count($result))) {
            $this->logger->notice("grab info from html");
            $result = $resultFromHTML;
        }

        // refs #23625
        if (!$dataFromJson && !empty($this->accountActivity)) {
            $this->logger->debug("Total " . count($this->accountActivity) . " history items were found");

            foreach ($this->accountActivity as $activity) {
                $dateStr = $activity->node->startDate;
                $postDate = strtotime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");
                    $this->endHistory = true;

                    continue;
                }
                $result[$startIndex]['Date Posted'] = $postDate;
                $result[$startIndex]['Type'] = $activity->node->type->description ?? null;
                $result[$startIndex]['Description'] = $activity->node->description ?? null;

                $points = $activity->node->totalEarning ?? null;

                if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Type'])) {
                    $result[$startIndex]['Bonus points earned'] = $points;
                } else {
                    $result[$startIndex]['Points earned'] = $points;
                }

                $startIndex++;
            }
        }

        $this->getTime($startTimer);

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    public function ParseHistoryJSON($dataFromJson, $startIndex, $startDate = null)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
//        $this->logger->debug($dataFromJson);
        $dataFromJson = $this->http->JsonLog($dataFromJson, false);
        $activityFilters = $dataFromJson->resolvedComponentContexts->activityFilters ?? [];
//        $this->logger->debug(var_export($dataFromJson->resolvedComponentContexts->activityFilters ?? [], true), ['pre' => true]);
        foreach ($activityFilters as $key => $value) {
//            $this->logger->debug(var_export($key, true), ['pre' => true]);
//            $this->logger->debug(var_export($value, true), ['pre' => true]);
            break;
        }

        if (!isset($value)) {
            $this->logger->error("activityFilters not found");

            return [];
        }

        $this->sendNotification("refs #23625 -  outdated code"); // TODO

        $data = $this->http->JsonLog(json_encode($value), false, true);
        $recordsPerPage = $this->http->FindSingleNode('//select[@id = "viewPerPage"]/option[contains(text(), "All")]/@value');

        if (!$recordsPerPage) {
            $this->logger->error("recordsPerPage not found");

            return [];
        }
        $data["context"]["recordsPerPage"] = $recordsPerPage;
        $data['sourceURI'] = "/loyalty/myAccount/activity.mi";
        $data['sessionToken'] = $this->http->FindPreg("/\"sessionId\":\s*\"([^\"]+)/");
        $this->logger->debug(var_export($data, true), ['pre' => true]);
//        $data = [
//            "context"      => [
//                "localeKey"                      => "en_US",
//                "programFlag"                    => "",
//                "siteName"                       => "marriott.com",
//                "channel"                        => "marriott",
//                "pageContent"                    => [
//                    [
//                        "contentName" => "/RAM Experience/Metadata/Rewards/Activity",
//                        "type"        => "meta",
//                    ],
//                ],
//                "levelCode"                      => "GAR",
//                "level"                          => "G",
//                "programCode"                    => "HGA",
//                "pageURI"                        => "/loyalty/myAccount/activity",
//                "Referer"                        => "https://www.marriott.com/loyalty/myAccount/activity.mi",
//                "monthsFilter"                   => "24",
//                "queryString"                    => "activityType%3Dtypes%26monthsFilter%3D24",
//                "absolutePageURL"                => "/loyalty/myAccount/activity.mi?activityType=types&monthsFilter=24",
//                "applicationName"                => "AriesRewards",
//                "template"                       => "V2",
//                "recordsPerPage"                 => \d,
//                "textColor"                      => "",
//                "error.message.key"              => "API_CM_general-errors_v2|tile.failure.text1",
//                "error.message.description.keys" => [
//                    "API_CM_general-errors_v2|tile.failure.text2",
//                ],
//                "pageNumber"                     => 1,
//            ],
//            "sessionToken" => "________-____-____-____-____________",
//            "sourceURI"    => "/loyalty/myAccount/activity.mi",
//            "variation"    => "0.1",
//        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/json",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->PostURL("https://www.marriott.com/aries-rewards/v1/activityList.comp", json_encode($data), $headers);
        $response = $this->http->JsonLog(null, false);
        $activitiesList = $response->component->data->activitiesList ?? [];
        $this->logger->debug("Total " . count($activitiesList) . " history items were found");

        foreach ($activitiesList as $activity) {
            $dateStr = $activity->postDate;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }
            $result[$startIndex]['Date Posted'] = $postDate;
            $result[$startIndex]['Type'] = $this->http->FindPreg("/([^*]+)/", false, $activity->activityType);
            $result[$startIndex]['Description'] = $activity->description ?? null;

            if (isset($activity->descNextLine)) {
                $result[$startIndex]['Description'] = trim($result[$startIndex]['Description'] . ' ' . $activity->descNextLine);
            }
            $points = $this->http->FindPreg(self::BALANCE_REGEXP, false, $activity->points);

            if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Type'])) {
                $result[$startIndex]['Bonus points earned'] = $points;
            } else {
                $result[$startIndex]['Points earned'] = $points;
            }
//            $result[$startIndex]['Actions'] = $activity->actionLabel ?? null;

            $startIndex++;
        }

        return $result;
    }

    public function ParseHistoryPage($startIndex, $startDate = null)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $nodes = $this->http->XPath->query('//div[contains(@class, "tile-activity-grid")]//div[contains(@class, "l-row") and not(contains(@class, "headers"))]');
        $this->logger->debug("Total {$nodes->length} history items were found");

        if ($nodes->length) {
            $this->sendNotification("refs #23625 -  outdated code"); // TODO
        }

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $dateStr = $this->http->FindSingleNode("p[contains(@class, 'post-date')]", $nodes->item($i));
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }
            $result[$startIndex]['Date Posted'] = $postDate;
            $result[$startIndex]['Type'] = $this->http->FindSingleNode("p[contains(@class, 'activity-type')]", $node, true, "/([^*]+)/");
            $result[$startIndex]['Description'] = $this->http->FindSingleNode("div[p[contains(@class, 'l-description')]] | div[a[contains(@class, 'l-description')]]", $node);

            if (!$result[$startIndex]['Description']) {
                $result[$startIndex]['Description'] = $this->http->FindSingleNode("p[contains(@class, 'l-description')]", $node);
            }
            $points = $this->http->FindSingleNode(".//p[contains(@class, 'l-points')]", $node, true, self::BALANCE_REGEXP);

            if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Type'])) {
                $result[$startIndex]['Bonus points earned'] = $points;
            } else {
                $result[$startIndex]['Points earned'] = $points;
            }
//            $result[$startIndex]['Actions'] = $this->http->FindSingleNode(".//a[contains(@class, 'l-action-label')]", $node);

            $startIndex++;
        }

        return $result;
    }

    private function checkLoginErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "m-message-box-error l-clear")]/span | //div[contains(@class, "m-message-content")]/div[@role]/p')) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'No profile exists for Customer Id'
                || strstr($message, 'This account has already been combined with another account.')
                || strstr($message, 'There is more than one account for the email address you entered. Please use your Rewards number to sign in')
                || strstr($message, 'You have one attempt remaining to enter the correct username/password')
                || strstr($message, 'Please correct the following and try again: Email/member number and/or password')
                || strstr($message, 'There was an issue with the email or member number you entered.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            // refs #25232
            if (stristr($message, 'We can’t process your request Our apologies – sign-in is temporarily not available.')) {
                if ($this->attempt == 2) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            if (
                stristr($message, 'The Account is under Audit')
                || stristr($message, 'Your account is under audit')
                || stristr($message, 'We are unable to display your account information online at this time.')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (stristr($message, 'You are temporarily locked out of your account. Please wait 30 minutes before entering your information again')) {
                throw new CheckException("You are temporarily locked out of your account. Please wait 30 minutes before entering your information again or using the Forgot Password link.", ACCOUNT_LOCKOUT);
            }

            if (
                stristr($message, 've detected suspicious activity on your account. To resolve, please call our Customer Engagement Center at 1-800-535-4028.')
                || stristr($message, 'We\'ve detected suspicious activity on your account. For assistance,')
                || stristr($message, 'Your account has been temporarily frozen.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            /*
             * Your account has been temporarily frozen
             * Please call Member Support at +1 800-228-2100 toll free in the US and Canada, or +800 2282 1000 outside of the US and Canada.
             */
            if (stristr($message, 'Please call Member Support at')) {
                throw new CheckException($this->http->FindSingleNode('//div[contains(@class, "m-message-box-error l-clear")]/text()[1]'), ACCOUNT_LOCKOUT);
            }

            // Email, member number and/or password is incorrect.
            if (
                $message == 'Email, member number and/or password is incorrect.'
                || $message == 'Email/member number and/or password'
            ) {
                throw new CheckRetryNeededException(2, 7, $message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return true;
        }

        // Email address or Rewards number and/or password is invalid.
        if ($message = $this->http->FindSingleNode('//*[contains(text(), "Email address or Rewards number and/or password is invalid")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // There is more than one account for the email address you entered
        if ($message = $this->http->FindSingleNode('//*[contains(text(), "There is more than one account for the email address you entered")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // User ID and/or password is incorrect
        if ($message = $this->http->FindSingleNode('//div[not(contains(@class, "l-display-none"))]/span[contains(text(), "User ID and/or password is incorrect")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Account id is invalid
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Account id is invalid")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Your account has been temporarily frozen.")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // The Account has been transfered to another Account
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "The Account has been transfered to another Account")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Incorrect email address, Rewards number and/or password.
        if ($message = $this->http->FindSingleNode('//*[contains(text(), "Incorrect email address, Rewards number and/or password.")]')) {
            //			throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            throw new CheckRetryNeededException(2, 7, $message, ACCOUNT_INVALID_PASSWORD);
        }
        // Die Anmeldung bei Ihrem Konto ist derzeit nicht möglich. Rufen Sie bitte den Kundenservice an, wenn Sie Hilfe benötigen.
        if ($message = $this->http->FindSingleNode('//*[contains(text(), "Die Anmeldung bei Ihrem Konto ist derzeit nicht möglich. Rufen Sie bitte den Kundenservice an, wenn Sie Hilfe benötigen.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are unable to process your request at this time due to an unprocessable condition. Please try again later.
        if ($message = $this->http->FindSingleNode('
            //*[contains(text(), "We are unable to process your request at this time due to an unprocessable condition. Please try again later.")
                or contains(text(), "We are unable to process your request at this time, we apologize for the inconvenience.")]
        ')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Change Password
        if ($this->http->FindSingleNode("//h1[contains(., 'Change Password')] | //p[contains(text(), 'In a continuing effort to safeguard your account, we are requiring you to change your password.')]")
            && (stristr($this->http->currentUrl(), 'force-change-password.mi') || stristr($this->seleniumURL, 'force-change-password.mi'))) {
            throw new CheckException("Marriott Rewards website is asking you to change your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }
        // Your immediate action is required.
        // In a continuing effort to safeguard your account, we are requiring you to change your password.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "In a continuing effort to safeguard your account, we are requiring you to change your password.")]')) {
            $this->throwProfileUpdateMessageException();
        }

        // prevent "Unable to Sign In"
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Account Sign In is temporarily unavailable. We apologize for the inconvenience. Thank you for your patience.")]')) {
            $this->State['removeCookies'] = true;

            throw new CheckRetryNeededException(2, 0, $message, ACCOUNT_PROVIDER_ERROR); // AccountID: 3093846
        }

        /*
         * General Error
         *
         * We are experiencing technical difficulties. Please try again.
         */
        // AccountID: 4442590
        if ($this->seleniumURL == 'https://www.marriott.com/error.mi') {
            $technicalXpath = '//div[contains(text(), "We are experiencing technical difficulties. Please try again.")]';

            if ($this->http->FindSingleNode($technicalXpath) && $this->http->FindPreg("/\"memState\": \"unauthenticated\",/")) {
                throw new CheckRetryNeededException(3, 10/*, "We are experiencing technical difficulties. Please try again."*/);
            }
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (($this->http->FindNodes(self::LOGOUT_XPATH) || $this->http->FindPreg("/\"mr_id\":\s*\"([^\"]+)/"))
            && !strstr($this->seleniumURL, 'https://www.marriott.com/rewards/myAccount/forceChangePassword.mi')) {
            return true;
        }

        return false;
    }

    private function ParseCancelledReservations()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice("Parse Cancelled Reservations...");
        $result = [];

        if ($this->http->FindSingleNode("//ul[contains(@class, 'cancellation-details-confirmation')][count(./li/p)=4][contains(.,'Confirmation Number')]//p[2][contains(.,'Status')]")) {//retrieve get it
            $nodes = $this->http->XPath->query("//ul[contains(@class, 'cancellation-details-confirmation')][count(./li/p)=4][not(contains(.,'Confirmation Number'))]");
            $this->logger->debug("Total {$nodes->length} cancelled reservations are found");
            // search|check positions
            $posConfNo = count($this->http->FindNodes("//ul[contains(@class, 'cancellation-details-confirmation')][count(./li/p)=4][contains(.,'Confirmation Number')]//p[contains(.,'Confirmation Number')]/preceding-sibling::p")) + 1;
            $posCancNo = count($this->http->FindNodes("//ul[contains(@class, 'cancellation-details-confirmation')][count(./li/p)=4][contains(.,'Confirmation Number')]//p[contains(.,'Cancellation Number')]/preceding-sibling::p")) + 1;
            $posStatus = count($this->http->FindNodes("//ul[contains(@class, 'cancellation-details-confirmation')][count(./li/p)=4][contains(.,'Confirmation Number')]//p[contains(.,'Status')]/preceding-sibling::p")) + 1;

            if ($posConfNo === 1 || $posCancNo === 1 || $posStatus === 1) {
                $this->itinerariesMaster->add()->hotel(); // for broke
                $this->sendNotification("other format Cancelled Reservations");

                return $result;
            }
            $this->logger->debug("column position: Status - {$posStatus}, Confirmation - {$posConfNo}, Cancellation - {$posCancNo}");

            $hotelName = $this->http->FindSingleNode('//h1[contains(@class, "hotel-name")]/a');
            $address = $this->http->FindSingleNode('//h1[contains(@class, "hotel-name")]/following::div[1]/address');
            $address = preg_replace('/ ·/', ',', $address);
            $hotelPhone = $this->http->FindSingleNode('//div[contains(@class, "tile-hotel-header")]//span[contains(@class, "phone-number") and @itemprop="telephone"]');

            foreach ($nodes as $node) {
                $h = $this->itinerariesMaster->add()->hotel();
                $confNo = $this->http->FindSingleNode(".//p[{$posConfNo}]", $node);
                $cancNo = $this->http->FindSingleNode(".//p[{$posCancNo}]", $node);
                $status = $this->http->FindSingleNode(".//p[{$posStatus}]", $node);
                $this->logger->info('Parse Cancelled Itinerary #' . $confNo, ['Header' => 3]);

                if (strcasecmp($status, 'cancelled') !== 0) {
                    $this->sendNotification("looks like Cancelled Reservation - has no status Cancelled");

                    return $result;
                }
                $h->general()
                    ->confirmation($confNo)
                    ->status($status)
                    ->cancellationNumber($cancNo)
                    ->cancelled();

                if (!empty($address) && !empty($hotelName)) {
                    $h->hotel()
                        ->name($hotelName)
                        ->address($address)
                        ->phone($hotelPhone, true);
                }

                if (!empty($cntRooms = $this->http->FindSingleNode(".//p[1]", $node, false,
                    "/^Room (\d+)$/"))
                ) {
                    $h->booked()->rooms($cntRooms);
                }
                $this->logger->debug('Parsed itineraries:');
                $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
            }
        } else {
            $nodes = $this->http->XPath->query('//div[contains(@class, "tile-cancelled-trips")]/div[contains(@class, "rows")]');
            $this->logger->debug("Total {$nodes->length} cancelled reservations are found");

            foreach ($nodes as $node) {
                $h = $this->itinerariesMaster->add()->hotel();
                $confNo = $this->http->FindSingleNode(".//p[contains(text(), 'Confirmation number')]/following-sibling::h3",
                    $node);
                $h->general()
                    ->confirmation($confNo)
                    ->cancelled();
                $this->logger->debug('Parsed itineraries:');
                $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
            }
        }

        return $result;
    }

    private function CheckConfirmationNumberInternalSelenium($arFields)
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $sessionData = null;
        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL($this->ConfirmationNumberURL($arFields));
            $selenium->waitForElement(WebDriverBy::id('lookup-submit-btn'), 5);

            $confInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@name='confirmationNumber']"), 5);
            $firstNameInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@name='firstName']"), 0);
            $lastNameInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@name='lastName']"), 0);

            if (!$confInput || !$firstNameInput || !$lastNameInput) {
                $this->logger->error('not found input fields');

                return null;
            }
            $confInput->sendKeys($arFields['ConfNo']);
            $firstNameInput->sendKeys($arFields['FirstName']);
            $lastNameInput->sendKeys($arFields['LastName']);
            $dateIn = date('Y-m-d', strtotime($arFields['CheckInDate'], false));
            $selenium->driver->executeScript("document.querySelector('input[name=\"checkInDate\"]').value='{$dateIn}'");
            $this->savePageToLogs($selenium);

            //$findButton = $selenium->waitForElement(WebDriverBy::xpath("//button[@name='submit']"), 0);
            $findButton = $selenium->waitForElement(WebDriverBy::id('lookup-submit-btn'), 0);

            if (!$findButton) {
                $this->logger->error('not found button submit');

                return null;
            }
            $findButton->click();
            $selenium->waitForElement(WebDriverBy::xpath("
            //span[contains(text(),'Upcoming Reservation')] 
            | //h1[contains(text(),'Cancellation Confirmation')] 
            | //div[contains(., 'Correction needed')]/span
            | //div[@id = 'form-action-messages' and starts-with(normalize-space(),'Reservation not')]
            | //div[contains(., 'We cannot locate your reservation')]/span"), 20);
            $this->savePageToLogs($selenium);
            $sessionData = $selenium->driver->executeScript("return sessionStorage.getItem('sessionData');");
            $this->logger->debug("[sessionData]: " . $sessionData);
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return $sessionData;
    }

    private function selectProxy(TAccountChecker $selenium)
    {
        // we want to change proxy provider every attempt
        // so, we fill available providers into the state on attempt 0, and then will pick one on every try
        $allowedProxyConfigs = range(2, 6);

        if ($this->attempt == 0) {
            $this->logger->debug("fixed config, test mode");
            $allowedProxyConfigs = range(0, 2);
        }

        if (
            $this->attempt === 0
            || !isset($this->State["ProxyConfigs"])
            || count($this->State["ProxyConfigs"]) === 0
            || count(array_diff($this->State["ProxyConfigs"], $allowedProxyConfigs)) > 0
        ) {
            $this->State["ProxyConfigs"] = $allowedProxyConfigs;
        }

        $proxyConfigKey = array_rand($this->State["ProxyConfigs"]);
        $proxyConfig = $this->State["ProxyConfigs"][$proxyConfigKey];
        unset($this->State["ProxyConfigs"][$proxyConfigKey]);

        switch ($proxyConfig) {
            case 0:
                $proxy = "direct:" . $selenium->http->getProxyAddress();

                break;

            case 1:
                $selenium->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_EU));
                $proxy = "dop-eu:" . $selenium->http->getProxyAddress();

                break;

            case 2:
                $selenium->setProxyBrightData(null, "static");
                $proxy = "lumunati-us:" . $selenium->http->getProxyAddress();

                break;

            case 3:
                $selenium->setProxyBrightData(null, "static", "gb");
                $proxy = "lumunati-gb:" . $selenium->http->getProxyAddress();
//                $selenium->setProxyGoProxies(null, "gb");
//                $proxy = "go-gb:" . $selenium->http->getProxyAddress();

                break;

            case 4:
                $selenium->setProxyMount();
                $proxy = "mount:" . $selenium->http->getProxyAddress();

                break;

            case 5:
                $selenium->setProxyBrightData(null, "static", "au");
                $proxy = "lumunati-au:" . $selenium->http->getProxyAddress();
//                $selenium->setProxyGoProxies(null, "au");
//                $proxy = "go-au:" . $selenium->http->getProxyAddress();

                break;

            case 6:
                $selenium->setProxyBrightData(null, "static", "fi");
                $proxy = "lumunati-fi:" . $selenium->http->getProxyAddress();
//                $selenium->setProxyGoProxies(null, "fi");
//                $proxy = "go-fi:" . $selenium->http->getProxyAddress();

                break;
        }

        return $proxy;
    }

    private function callRetries(&$restart)
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindPreg('/page isn’t working/ims')
            || $this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")] | //h1[contains(text(), "Access Denied")]')
            || ($this->http->FindPreg('/^<head><link[^>]+><\/head><body><\w+[^>]*><\/\w+><\w+[^>]+><\/\w+><\w+[^>]+><\/\w+><img[^>]+><\/body>$/') && $this->http->FindSingleNode('//img[@id = "selenium-mouse"]/@id'))
            || $this->http->FindSingleNode("//pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                | //title[contains(text(), 'http://localhost:4448/start?text={$this->AccountFields['ProviderCode']}+%7C+{$this->AccountFields['Login']}+%7C+')]
            ")
        ) {
            $this->markProxyAsInvalid();
            $restart = true;

            if ($this->attempt == 2) {
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
            }

            throw new CheckRetryNeededException(3, 0);
        }
    }
}
