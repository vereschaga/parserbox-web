<?php

// refs #2013
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerRegal extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://experience.regmovies.com/api/Member";

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $newAuth = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;

        $this->UseSelenium();
        $this->setProxyBrightData();

        $this->useChromePuppeteer();
//        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        /*
        $this->setKeepProfile(true);
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        */

        $this->http->saveScreenshots = true;
        $this->newAuth = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;

        try {
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException | UnexpectedAlertOpenException $e) {
                $this->logger->error("IsLoggedIn -> Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            } finally {
                $this->logger->debug("LoadLoginForm -> finally");
            }
        }// catch (UnexpectedAlertOpenException $e)
        catch (NoAlertOpenException $e) {
            $this->logger->debug("no alert, skip");
        }

        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            $this->newAuth = true;

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        try {
            $this->http->GetURL('https://experience.regmovies.com/login');
        } catch (
            UnknownServerException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\UnrecognizedExceptionException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            sleep(2);
            $this->saveResponse();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(2, 10);
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 5);
        }
        sleep(2); // time for turnstile wrapper to load

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 5);
        $this->saveResponse();

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 5);
            $this->saveResponse();
        }

        if (!$login) {
            $this->saveResponse();
            $this->driver->executeScript("let username = document.querySelector('input[name = \"username\"]'); if (username) username.style.zIndex = '100003';");
            $this->driver->executeScript("let pass = document.querySelector('input[name = \"password\"]'); if (pass) pass.style.zIndex = '100003';");
            $this->driver->executeScript("let btn = document.querySelector('button[type = \"submit\"]'); if (btn) btn.style.zIndex = '100003';");

            $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 0);
        }

        $pwd = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@type = "submit"]'), 0);
        $this->saveResponse();

        if (!isset($login, $pwd, $btn)) {
            if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Access denied") or contains(text(), "Sorry, you have been blocked")]')) {
                $this->DebugInfo = $message;
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 5);
            }

            return false;
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pwd->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $btn->click();

        $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Logout")] | //p[contains(@class, "error_wrapper")]'), 25);
        $this->saveResponse();

        return true;

        $this->http->GetURL('https://www.regmovies.com/crown-club');

        if ($this->http->currentUrl() == 'https://experience.regmovies.com/login') {
            $this->newAuth = true;

            return $this->selenium();
        }

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'login') or @class = 'login']") && !$this->newAuth) {
            return $this->checkErrors();
        }

        $key = null;

        if ($this->newAuth) {
            $this->http->GetURL("https://experience.regmovies.com/api/CaptchaEnabled");
            $response = $this->http->JsonLog();
            $captchaEnabled = $response->enabled ?? null;
            $captcha = "";

            if ($captchaEnabled === true) {
                $key = '6LcpbW0UAAAAAPYXglvWC_3-cIrWu-eJlLRzLzH-';
                $captcha = $this->parseCaptcha($key);

                if ($captcha === false) {
                    return false;
                }
            }
        } else {
            $key = $this->http->FindPreg('/sitekey: "(.+?)",/');
            $captcha = $this->parseCaptcha($key);

            if ($captcha === false) {
                return false;
            }
        }

        $this->http->RetryCount = 0;
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json",
            //            "X-Requested-With" => "XMLHttpRequest",
        ];

        if ($this->newAuth) {
            $data = [
                "credential1"       => $this->AccountFields['Login'],
                "credential2"       => $this->AccountFields['Pass'],
                "isWebBookingLogin" => false,
                "captcha"           => $captcha,
            ];
            $this->http->PostURL("https://experience.regmovies.com/api/login", json_encode($data), $headers);
        } else {
            $data = [
                "identity"                 => $this->AccountFields['Login'],
                "password"                 => $this->AccountFields['Pass'],
                "isThirdPartyAuthRequired" => "false",
                "captchaToken"             => $captcha,
            ];
            $this->http->PostURL("https://www.regmovies.com/login", json_encode($data), $headers);
        }
        $this->http->RetryCount = 2;

        // retries
        if ($this->http->Response['code'] == 0) {
            throw new CheckRetryNeededException(2, 10);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[
                contains(text(), "Server Error")
                or contains(text(), "502 Bad Gateway")
            ]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We apologize for your inconvenience. We expect to be back up shortly.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We apologize for your inconvenience')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'));

        if ($this->newAuth) {
            if ($this->http->FindSingleNode('//button[contains(., "Logout")]')) {
                $this->markProxySuccessful();

                try {
                    $this->http->GetURL(self::REWARDS_PAGE_URL);
                } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                }
                $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 0);
            }

            if ($this->loginSuccessful()) {
                $this->captchaReporting($this->recognizer);

                return true;
            }

            $userMessage =
                $this->http->FindSingleNode('//p[contains(@class, "error_wrapper")]')
                ?? $response->userMessage
                ?? $response->message
                ?? null
            ;

            if ($userMessage) {
                $this->logger->error("[Error]: {$userMessage}");

                if (
                    $userMessage == 'Username or Password is incorrect.'
                    || $userMessage == 'Sorry, the credential combination provided is not valid.'
                    || trim($userMessage) == 'Bad user name or password.'
                ) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($userMessage, ACCOUNT_INVALID_PASSWORD);
                }

                if ($userMessage == 'Failed to verify that you are not a robot.') {
                    $this->captchaReporting($this->recognizer, false);

                    throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
                }

                if (strstr($userMessage, 'The request could not be understood by the server due to malformed syntax')) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckRetryNeededException(3, 0);
                }

                if (
                    $userMessage == 'Internal server error'
                    || $userMessage == 'The system is currently unavailable.'
                    || $userMessage == 'The error response was unsuccessfully initialized.'
                    || $userMessage == 'Login failed for unknown reason. Please try again later.'
                ) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($userMessage, ACCOUNT_PROVIDER_ERROR);
                }
            }// if ($userMessage)

            $userMessage = $response->Message ?? null;
            $this->logger->error("[Error 2]: {$userMessage}");

            if ($userMessage == "An error has occurred.") {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($userMessage, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                isset($this->http->Response['code'])
                && $this->http->Response['code'] == 500
                && ($message = $this->http->FindSingleNode('//div[contains(text(), "An unexpected error has occured.")]'))
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $userMessage;

            if ($this->waitForElement(WebDriverBy::xpath('//iframe[@title="recaptcha challenge expires in two minutes"]'), 0)) {
                $this->markProxyAsInvalid();
                $this->DebugInfo = "recaptcha issue";

                throw new CheckRetryNeededException(3, 0);
            }

            $this->saveResponse();

            return $this->checkErrors();
        }

        /*
        if (isset($response->redirectUrl)) {
            $this->logger->debug("json redirect");
            $redirectUrl = $response->redirectUrl;
            $this->http->NormalizeURL($redirectUrl);
            $this->http->GetURL($redirectUrl);

            if ($this->http->FindSingleNode('//strong[normalize-space(text()) = "Continue"]')) {
                $this->http->GetURL($redirectUrl);
            }
        }// if (isset($response->redirectUrl))

        // Access is allowed
        if ($this->http->FindSingleNode("//button[contains(text(), 'MY ACCOUNT')]")) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        // Username or Password is incorrect.
        if ($message = $this->http->FindPreg('/^(Username or Password is incorrect\.)$/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, the credential combination provided is not valid.
        if ($message = $this->http->FindPreg('/^(Sorry, the credential combination provided is not valid\.)$/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, the email password combination didn't match a membership.
        if ($message = $this->http->FindPreg('/^(Sorry, the email password combination didn\'t match a membership\.)$/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Card Number cannot be used as a login credential.  Please provide email address and password.
        if ($message = $this->http->FindPreg('/^(Card Number cannot be used as a login credential\.\s*Please provide email address and password\.)$/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        /*
         * Whoops, we couldn't find an account with that information. Please try again.
         * If you're still having trouble, try using Forgot Password to reset.
         * /
        if ($message = $this->http->FindPreg('/(Whoops, we couldn\'t find an account with that information\. Please try again\. If you\'re still having trouble, try using Forgot Password to reset\.)/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        /*
         * Womp womp. That request didn't go through.
         * We're sounding the alarms to let our team know. Please try again.
         * /
        if ($message = $this->http->FindPreg('/(Womp womp. That request didn\'t go through. We\'re sounding the alarms to let our team know\. Please try again\.)/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We were unable to process your request due to a service error. Please try again shortly.
        if ($message = $this->http->FindSingleNode("
                //div[
                    contains(text(), 'We were unable to process your request due to a service error. Please try again shortly.')
                    or contains(text(), 'An unexpected error has occured.')
                ]
            ")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] == 400 && $message = $this->http->FindPreg('/Looks like that request didn\'t go through. Sorry about that, go ahead and try again/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] == 500 && $message = $this->http->FindPreg('/(The system is currently unavailable\.)/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Looks like that request didn't go through. Sorry about that, go ahead and try again.
        if (isset($response->message) && $this->http->FindPreg("/Looks like that request didn't go through/", false, $response->message)) {
            $this->captchaReporting($this->recognizer);

            throw new CheckRetryNeededException(2, 10);
        }
        // captcha issues
        if ($this->http->Response['code'] == 400 && $message = $this->http->FindPreg('/Please verify you are not a robot./')) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }
        */

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->newAuth) {
            $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 0);
            // Balance - Credit Balance
            $this->SetBalance($response->CurrentCreditBalance ?? null);
            // Name
            if (isset($response->FirstName, $response->LastName)) {
                $this->SetProperty("Name", beautifulName($response->FirstName . " " . $response->LastName));
            }
            // Regal Card Number
            $this->SetProperty("Number", $response->CardNumber ?? null);
            // Visits needed until next level
            $this->SetProperty("PointsNeeded", $response->VisitsTillNextGemLevel ?? null);
            // Current Status
            $status = $response->GemLevel ?? null;

            if ($status == 'RCC Membership') {
                $status = "Member";
            }

            $this->SetProperty("Status", $status);
            // Status Expiration
            if ($status != "Member" && ($expiringStatus = $response->GemLevelExpiration ?? null)) {
                $this->SetProperty("StatusExpiration", date("M d, Y", strtotime($expiringStatus)));
            }
            // Rewards
            $this->logger->info('Rewards ', ['Header' => 3]);
            $sessionToken = $response->SessionToken ?? null;
            $this->http->GetURL("https://experience.regmovies.com/api/Recognitions?sessionToken=" . $sessionToken);
            $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 2);

            if (!empty($response)) {
                $this->SetProperty("CombineSubAccounts", false);

                foreach ($response as $reward) {
                    $rewardExpiryDate = strtotime($reward->Expiry_Date, false);
                    $displayName = trim($reward->DisplayName);

                    if ($rewardExpiryDate < time()) {
                        $this->logger->notice("[Skip reward]: {$displayName} / {$reward->Expiry_Date}");

                        continue;
                    }

                    $subAcc = [
                        'Code'           => 'regalReward' . str_replace([' ', '%', ':', ',', "'"], '', $displayName) . $rewardExpiryDate,
                        'DisplayName'    => $displayName,
                        'Balance'        => $reward->Qty,
                    ];

                    // Never expier
                    if ($reward->Expiry_Date != '2099-01-01T00:00:00') {
                        $subAcc['ExpirationDate'] = $rewardExpiryDate;
                    }

                    $this->AddSubAccount($subAcc);
                }// foreach ($response as $reward)
            }

            if ($this->Balance <= 0) {
                return;
            }

            $this->logger->info('Expiration date', ['Header' => 3]);
            $this->http->GetURL("https://experience.regmovies.com/api/CreditExpiration?sessionToken=" . $sessionToken);
            $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'));

            if (!empty($response)) {
                foreach ($response as $transaction) {
                    if (!isset($transaction->ExpiringCredits, $transaction->ExpiryDate)) {
                        $this->logger->error("something went wrnng");

                        continue;
                    }

                    $creditsExpiring = $transaction->ExpiringCredits;
                    $creditsExpiryDate = $transaction->ExpiryDate;

                    if ($creditsExpiring == 0) {
                        $this->logger->notice("there is nothing to expire");

                        continue;
                    }

                    $this->logger->debug("CreditsExpiryDate: {$creditsExpiryDate} - {$creditsExpiring}");
                    $expiryDate = strtotime($creditsExpiryDate);

                    if (!$expiryDate || $expiryDate < time()) {
                        $this->logger->notice("skip old ExpiryDate");

                        continue;
                    }

                    if ($creditsExpiring > $this->Balance) {
                        $this->sendNotification("regal. Need to check exp date");

                        break;
                    }

                    $this->SetProperty('ExpiringBalance', $creditsExpiring);
                    $this->SetExpirationDate($expiryDate);

                    break;
                }// foreach ($transactions as $transaction)
            }// if (!empty($response) && is_array($response))

            return;
        }

        $this->http->GetURL('https://www.regmovies.com/crown-club');
        // Balance - Credit Balance
        $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'Available Credits:')]", null, true, "/:\s*([\-\d,.]+)/"));
        // Visits needed until next level
        $this->SetProperty("PointsNeeded", $this->http->FindSingleNode("//div[contains(@class, 'visible-md-block')]//div[@class = 'progress-description']/text()[1]"));
        // Expiration date and contains(text(), 'credits expiring on')
        if ($this->Balance > 0 && ($expiring = $this->http->FindSingleNode("//div[contains(text(), 'Available Credits:')]/following-sibling::div[contains(text(), 'credits expiring on')]"))) {
            $this->SetProperty('ExpiringBalance', $this->http->FindPreg('/([\d,.]+) credits expiring/', false, $expiring));

            if ($exp = strtotime($this->http->FindPreg('/credits expiring on (.+?)\./', false, $expiring), false)) {
                $this->SetExpirationDate($exp);
            }
        }

        // Current Status
        if ($status = $this->http->FindSingleNode("//div[contains(@class, 'visible-md-block')]//a[@class = 'current-status-color']")) {
            $this->SetProperty("Status", $status);
        } elseif ($this->http->FindNodes("//div[@class='progress-description' and contains(., 'visits to go to reach') and contains(.,'Emerald Status')]")) {
            $this->SetProperty("Status", "Member");
        }

        // Status Expiration
        if ($expiringStatus = $this->http->FindSingleNode("//div[contains(@class, 'visible-md-block')]//div[contains(text(), 'Expires')]", null, true, "/Expires\s*(.+)/")) {
            $this->SetProperty("StatusExpiration", $expiringStatus);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            /*
             * Congrats! Check your email to confirm your account.
             *
             * After confirming your email, you can go to your Regal Crown Club page and see all of your member benefits!
             */
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Congrats! Check your email to confirm your account.')]")) {
                $this->throwProfileUpdateMessageException();
            }
            // Looks like that request didn't go through. Sorry about that, go ahead and try again. (AccountID: 3734125)
            if ($message = $this->http->FindPreg('/class="alert modal fade" data-csm="\{&quot;category&quot;:&quot;UnknownAlert&quot;,&quot;action&quot;:&quot;PageView&quot;\}"/')) {
                throw new CheckException("Looks like that request didn't go through. Sorry about that, go ahead and try again.", ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        $this->http->GetURL('https://www.regmovies.com/settings/profile');
        // Name
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//label[contains(@class, 'active')]//input[@name = 'firstName']/@value")
            . ' ' . $this->http->FindSingleNode("//label[contains(@class, 'active')]//input[@name = 'lastName']/@value"));
        $this->SetProperty("Name", beautifulName($name));
        // Regal Card Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//label[contains(@class, 'active')]//input[@placeholder = 'Regal Card Number']/@value"));
    }

    private function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);
        //$key = $this->http->FindPreg('/sitekey: "(.+?)",/');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => 'https://experience.regmovies.com/login',
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    /*
    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice('Running Selenium...');
            $selenium->UseSelenium();

//            if ($this->attempt == 0) {
//            $selenium->useFirefox();
//            $selenium->setKeepProfile(true);
//            } else {
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
//            }

            $selenium->http->saveScreenshots = true;
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://experience.regmovies.com/login');

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 5);
            $pwd = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@type = "submit"]'), 0);
            $this->savePageToLogs($selenium);

            if (!isset($login, $pwd, $btn)) {
                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);
            $pwd->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            $btn->click();

            $selenium->waitForElement(WebDriverBy::xpath('//button[contains(., "Logout")]'), 10);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->savePageToLogs($selenium);
        } finally {
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }
    */

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'));

        if (isset($response->SessionToken)) {
            return true;
        }

        return false;
    }
}
