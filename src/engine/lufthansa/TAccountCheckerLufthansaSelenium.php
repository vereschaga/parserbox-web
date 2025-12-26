<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use Facebook\WebDriver\Exception\UnknownErrorException;


class TAccountCheckerLufthansaSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use PriceTools;
    use ProxyList;

    private HttpBrowser $browser;
    private $mainData = null;
    private $endHistory = false;
    private $currentItin = 0;
    private $parsedLocators = [];
    private TAccountCheckerLufthansa $checker;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);

        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->useChromePuppeteer();
        $this->usePacFile(false);
        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
//        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        $this->setProxyGoProxies();
        $this->seleniumOptions->addHideSeleniumExtension = false;

        /*
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::chrome()]);

        if ($fingerprint !== null) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
        }
        */

        /*
        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1920, 1080],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->DebugInfo = "Resolution: " . implode("x", $resolution);

        $this->useGoogleChrome();
        $this->setProxyBrightData();
        $this->seleniumOptions->userAgent = null;

        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN - 3;
        /*
        $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if (isset($fingerprint)) {
            $this->http->setUserAgent($fingerprint->getUseragent());
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
        }
        */

//        $this->seleniumOptions->recordRequests = false;
        $this->KeepState = true;

    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL("https://api.travelid.lufthansa.com/v1/user/me/loginstatus");
            sleep(3);
            $this->http->saveResponse();
            if ($this->http->FindPreg('/"loginstatus":\{"custNo":/')) {
                $this->http->GetURL('https://www.lufthansa.com/us/en/homepage', [], 20);
                sleep(5);
                $data = $this->getApi('https://www.lufthansa.com/service/secured/api/core/user/profile');
                $data = $this->http->JsonLog($data);
                if (isset($data->authenticationLevel) && $data->authenticationLevel == 'AUTHENTICATED') {
                    return true;
                }
            }
        } catch (Exception $e) {
            $this->logger->error('Exception: ' . $e->getMessage(), ['HtmlEncode' => true]);

            return false;
        }

        return false;
    }

    public function LoadLoginForm()
    {
//        return $this->LoadLoginFormMAM();
        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
        /*
//        $this->http->GetURL('https://www.lufthansa.com/us/en/account-statement');
        */
        $this->http->GetURL('https://www.lufthansa.com/');

        $acceptAll = $this->waitForElement(WebDriverBy::xpath('//button[contains(@id, "cm-acceptAll")]'), 4);
        $this->saveResponse();

        if ($acceptAll) {
            $acceptAll->click();
            sleep(1);
            $this->saveResponse();
        }

        $btnLogin = $this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "btn-login")]'), 2);
        $this->saveResponse();

        if ($btnLogin) {
            $btnLogin->click();
            sleep(3);
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "loginStepOne"]'), 10);
        $this->saveResponse();

        if (!$loginInput) {
            $this->logger->error("something went wrong");

            return false;
        }

        $loginInput->sendKeys(str_replace(' ', '', $this->AccountFields['Login']));
        sleep(rand(1,2));

        try {
            $this->logger->debug("login__continueButton click");
            $this->driver->executeScript('$(".travelid-login__continueButton").click();');
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage(), ['HtmlEncode' => true]);
        }

        if ($error = $this->waitForElement(WebDriverBy::xpath('//form[not(@hidden)]//p[contains(@class, "travelid-form__elementValidationMessage")]/span[not(@hidden)] | //p[@class = "travelid-form__errorBoxContentItemText"]'), 5)) {
            $this->saveResponse();
            $message = $error->getText();
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'A technical error has occurred.')) {
                $this->markProxyAsInvalid();
                throw new CheckRetryNeededException(3, 5);
            }

            if (
                strstr($message, 'Your service card number consists of 15 digits.')
                || $message == 'The service card number begins with 9999, 9920, 9922, 2220 or 3330.'
                || strstr($message, 'Your servicecard number consists of 15 digits.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//form[not(@hidden)]//div[not(@hidden) and contains(@class,"travelid-form__elementWrapper")]//input[@name = "emailLoginStepTwo" or @name = "mamLoginStepTwoPassword" or @name = "mamLoginStepTwoPin" or @name = "loginStepTwoPassword"]'), 0, false);
        $this->saveResponse();

        if (!$passwordInput) {
            $this->logger->error("something went wrong");

            return false;
        }

        $passwordInput->click();
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $this->logger->debug("click 'Sign In'");
        sleep(rand(1,2));

        $button = $this->waitForElement(WebDriverBy::xpath('//form[contains(@class, "travelid-login__form ")]//button[contains(@class, "travelid-login__loginButton")]'), 2);

        if (!$button) {
            return false;
        }

        $button->click();
        /*
        $this->driver->executeScript('
            var loginBtn = $(\'.travelid-login__loginButton:not(:hidden)\');
            loginBtn.click();
        ');
        */

        return true;
    }

    public function Login()
    {
        //return $this->LoginMAM();
        $sleep = 40;
        $startTime = time();
        $time = time() - $startTime;

        $login = false;
        while (($time < $sleep)) {
            $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");
            $logout = $this->waitForElement(WebDriverBy::xpath("//span[@id = 'lh-loginModule-name']"), 0);
            if ($logout) {
                $login = true;
                $this->markProxySuccessful();
                $this->increaseTimeLimit(100);
                break;
            }
            // Everything under one roof: the new Travel ID
            if ($this->waitForElement(WebDriverBy::xpath('//button[@name = "welcome_startMigration"]'), 0)) {
                break;
            }
            if ($error = $this->waitForElement(WebDriverBy::xpath('//form[not(@hidden)]//p[contains(@class, "travelid-form__elementValidationMessage")]/span[not(@hidden)] | //p[@class = "travelid-form__errorBoxContentItemText"]'), 0)) {
                $message = trim($error->getText());
                $this->saveResponse();
                $this->logger->error("[Error]: {$message}");

                $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "emailLoginStepTwo" or @name = "mamLoginStepTwoPassword" or @name = "mamLoginStepTwoPin"]'), 0);
                $this->saveResponse();
                if (
                    $message == 'Please enter your password.' && $passwordInput
                ) {
                    $this->logger->debug("Try to enter password via selenium methods");
                    $passwordInput->sendKeys($this->AccountFields['Login']);
                    sleep(1);
                    $this->driver->executeScript('
                        var loginBtn = $(\'.travelid-login__loginButton:not(:hidden)\');
                        loginBtn.click();
                    ');
                    sleep(2);

                    continue;
                }

                if (
                    // It is not possible to log in. Please contact your local Miles & More Service Team.
                    strstr($message, 'It is not possible to log in.')
                    || $message == 'Wrong username or password'
                    || $message == 'Only numbers (0-9) are permitted.'
                    || $message == 'Your password has expired. Please change your password.'
                    || $message == 'Your service card number consists of 15 digits.'
                    || $message == 'Your account is not currently active. Please contact your local Miles & More Service Team to reactivate your account.'
                    || $message == 'Please check your login data. Login with Lufthansa iD, austrian.com profile and swiss.com profile is no longer possible. Register for Travel ID.'
                    || $message == 'Please check your login data.'
                    || $message == 'Only digits (0-9) are permitted.'
                    || $message == 'Validation has failed: {0}'
                    || $message == 'At least 8 characters'
                    || $message == 'Your PIN consists of exactly five digits.'
                    || $message == 'Please check your login data.'
                    || $message == 'Please check your login data or request a new password/PIN.'
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $message == 'Access Denied'
                    || $message == 'The connection has timed out'
                ) {
                    $this->markProxyAsInvalid();
                    throw new CheckRetryNeededException(3, 5);
                }

                if (
                    strstr($message, 'A technical error has occurred.')
                    || $message == 'An error occurred'
                    || $message == 'A technical problem has occurred.'
                    || $message == 'Es ist ein technischer Fehler aufgetreten.'
                    || $message == 'Currently a lot of users are trying to use this process. Please re-try at a later time'
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                break;
            }

            if ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'We apologise for the interruption. We detected unusual behaviour from your browser, which resembles that of a bot.')]"), 0)) {
                $this->saveResponse();
                $this->markProxyAsInvalid();
                throw new CheckRetryNeededException(3, 5);
            }

            if ($this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Two-factor authentication')]"), 0)) {
                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                    $this->Cancel();
                }
                $this->saveResponse();

                $this->sendNotification('2fa 1// MI');
                return $this->processSecurityCheckpoint();
            }

            $this->saveResponse();
            $time = time() - $startTime;
        }

        if (!$login) {
            try {
                $this->http->GetURL('https://www.lufthansa.com/us/en/homepage');
            } catch (Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
                $this->logger->error('InvalidSessionIdException: ' . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(3, 0);
            }

            $logout = $this->waitForElement(WebDriverBy::xpath("//span[@id = 'lh-loginModule-name']"), 10);
            $this->saveResponse();
            if ($logout) {
                $login = true;
                $this->markProxySuccessful();
                $this->increaseTimeLimit(100);
            }
            // TODO
            else {
                $acceptAll = $this->waitForElement(WebDriverBy::xpath('//button[contains(@id, "cm-acceptAll")]'), 4);
                $this->saveResponse();

                if ($acceptAll) {
                    $acceptAll->click();
                    sleep(1);
                    $this->saveResponse();
                }

                $btnLogin = $this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "btn-login")]'), 10);
                $this->saveResponse();

                if ($btnLogin) {
                    $btnLogin->click();
                    sleep(3);
                }

                $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "loginStepOne"]'), 10);
                $this->saveResponse();

                if (!$loginInput) {
                    $this->logger->error("something went wrong");

                    return false;
                }

                $loginInput->sendKeys(str_replace(' ', '', $this->AccountFields['Login']));
                sleep(rand(1,2));
                $this->driver->executeScript('$(".travelid-login__continueButton").click();');
                if ($error = $this->waitForElement(WebDriverBy::xpath('//form[not(@hidden)]//p[contains(@class, "travelid-form__elementValidationMessage")]/span[not(@hidden)] | //p[@class = "travelid-form__errorBoxContentItemText"]'), 5)) {
                    $this->saveResponse();
                    $message = $error->getText();
                    $this->logger->error("[Error]: {$message}");

                    if (strstr($message, 'A technical error has occurred.')) {
                        $this->markProxyAsInvalid();
                        throw new CheckRetryNeededException(3, 5);
                    }

                    if (
                        strstr($message, 'Your service card number consists of 15 digits.')
                        || $message == 'The service card number begins with 9999, 9920, 9922, 2220 or 3330.'
                        || strstr($message, 'Your servicecard number consists of 15 digits.')
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                $passwordInput = $this->waitForElement(WebDriverBy::xpath('//form[not(@hidden)]//div[not(@hidden) and contains(@class,"travelid-form__elementWrapper")]//input[@name = "emailLoginStepTwo" or @name = "mamLoginStepTwoPassword" or @name = "mamLoginStepTwoPin" or @name = "loginStepTwoPassword"]'), 0, false);
                $this->saveResponse();

                if (!$passwordInput) {
                    $this->logger->error("something went wrong");

                    return false;
                }

                $passwordInput->click();
                $passwordInput->sendKeys($this->AccountFields['Pass']);
                $this->saveResponse();
                $this->logger->debug("click 'Sign In'");
                sleep(rand(1,2));

                $button = $this->waitForElement(WebDriverBy::xpath('//form[contains(@class, "travelid-login__form ")]//button[contains(@class, "travelid-login__loginButton")]'), 2);

                if (!$button) {
                    return false;
                }

                $button->click();

                $logout = $this->waitForElement(WebDriverBy::xpath("//span[@id = 'lh-loginModule-name']"), 10);
                $this->saveResponse();

                if ($logout) {
                    $login = true;
                    $this->markProxySuccessful();
                    $this->increaseTimeLimit(100);
                }
            }
        }

        return $login;
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $q = $this->waitForElement(WebDriverBy::xpath("
            //div[contains(text(), 'Open your authentication app, which will show you a code. Enter it here:')]
        "), 5);
        $this->saveResponse();

        if (!$q) {
            return false;
        }

        $question = Html::cleanXMLValue($q->getText());
        $this->holdSession();

        $this->logger->debug($question);
        $this->logger->debug(var_export($this->Answers, true));
        $this->logger->debug(!isset($this->Answers[$question]) ? 'not Answer': $this->Answers[$question]);

        if (!isset($this->Answers[$question])) {
            $this->AskQuestion($question, null, 'authApp');

            return false;
        }
        $this->sendNotification('2fa 2 // MI');

        $securityAnswer = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'mfaVerificationCode']"), 0);
        $securityAnswer->clear();
        $securityAnswer->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);

        if ($askMe = $this->waitForElement(WebDriverBy::xpath("//label[contains(., 'Do not ask me again for the code in this browser')]"), 0)) {
            $this->saveResponse();
            $askMe->click();
        }

        $btn = $this->waitForElement(WebDriverBy::xpath("//button[contains(@class, 'mfa__submitBtn')]"), 0);
        $btn->click();

        // OTP entered is incorrect
        $error = $this->waitForElement(WebDriverBy::xpath("
            //p[contains(@class,'travelid-form__errorBoxContentItemText')]
        "), 10);
        $this->saveResponse();

        if ($error) {
            $this->logger->error("resetting answers");
            $this->AskQuestion($question, $error->getText(), 'authApp');

            return false;
        }

        sleep(10);
        $this->logger->debug("success");
        $this->saveResponse();

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'authApp') {
            return $this->processSecurityCheckpoint();
        }

        return false;
    }


    private function LoadLoginFormMAM()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.miles-and-more.com/');

        $acceptAll = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "AcceptAll")]'), 5);
        if ($acceptAll) {
            $this->saveResponse();
            $acceptAll->click();
        } else {
            $this->driver->executeScript('
                try { $(\'[class *= "buttonAcceptAll"]:visible\').click() } catch (e) {}
            ');
        }
        sleep(1);

        $btnLogin = $this->waitForElement(WebDriverBy::xpath('//a[contains(@class, "mainnavigation__login")]'), 2);
        $this->saveResponse();

        if (!$btnLogin) {
            $this->logger->error("something went wrong");

            return false;
        }

        $btnLogin->click();
        sleep(3);

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "loginStepOne"]'), 10);

        if (!$loginInput) {
            $this->saveResponse();
            $this->logger->error("something went wrong");
            return false;
        }

        $loginInput->sendKeys(str_replace(' ', '', $this->AccountFields['Login']));
        $this->driver->executeScript('$(".travelid-login__continueButton").click();');

        if ($error = $this->waitForElement(WebDriverBy::xpath('//form[not(@hidden)]//p[contains(@class, "travelid-form__elementValidationMessage")]/span[not(@hidden)] | //p[@class = "travelid-form__errorBoxContentItemText"]'), 5)) {
            $this->saveResponse();
            $message = Html::cleanxmlvalue($error->getText());
            $this->logger->error("[Error]: {$message}");
            if (strstr($message, 'A technical error has occurred.')) {
                $this->markProxyAsInvalid();
                throw new CheckRetryNeededException(3, 5);
            }

            if (
                strstr($message, 'Your service card number consists of 15 digits.')
                || strstr($message, 'Your servicecard number consists of 15 digits.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;
        }

        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//form[not(@hidden)]//div[not(@hidden) and contains(@class,"travelid-form__elementWrapper")]//input[@name = "emailLoginStepTwo" or @name = "mamLoginStepTwoPassword" or @name = "mamLoginStepTwoPin" or @name = "loginStepTwoPassword"]'), 0, false);
        $this->saveResponse();

        if (!$passwordInput) {
            $this->logger->error("something went wrong");

            return false;
        }

        /*
        $this->logger->debug("set pass via js");
        $this->driver->executeScript('
                $(\'[name = "emailLoginStepTwo"],[name = "mamLoginStepTwoPassword"],[name = "mamLoginStepTwoPin"]\').val(\'' . str_replace(['\\', "'"], ['\\\\', "\'"], $this->AccountFields['Pass']) . '\');
            ');
        */
        $passwordInput->click();
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $this->logger->debug("click 'Sign In'");
//        $button->click();
        sleep(1);
        $this->driver->executeScript('
            var loginBtn = $(\'.travelid-login__loginButton:not(:hidden)\');
            loginBtn.click();
        ');

        return true;
    }

    private function LoginMAM()
    {
        $sleep = 60;
        $startTime = time();
        $time = time() - $startTime;

        $login = false;
        while (($time < $sleep)) {
            $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");
            $logout = $this->waitForElement(WebDriverBy::xpath("//span[@id = 'lh-loginModule-name']"), 0);
            if ($logout) {
                $login = true;
                $this->markProxySuccessful();
                $this->increaseTimeLimit(100);
                break;
            }
            // Everything under one roof: the new Travel ID
            if ($this->waitForElement(WebDriverBy::xpath('//button[@name = "welcome_startMigration"]'), 0)) {
                break;
            }
            if ($error = $this->waitForElement(WebDriverBy::xpath('//form[not(@hidden)]//p[contains(@class, "travelid-form__elementValidationMessage")]/span[not(@hidden)] | //p[@class = "travelid-form__errorBoxContentItemText"]'), 0)) {
                $message = $error->getText();
                $this->saveResponse();
                $this->logger->error("[Error]: {$message}");

                if (strstr($message, 'A technical error has occurred.')) {
                    $this->markProxyAsInvalid();
                    //unset($this->State['ProxyCountry']);
                    //throw new CheckRetryNeededException(3, 5, $message, ACCOUNT_PROVIDER_ERROR);
                }

                $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "emailLoginStepTwo" or @name = "mamLoginStepTwoPassword" or @name = "mamLoginStepTwoPin"]'), 0);
                $this->saveResponse();
                if (
                    $message == 'Please enter your password.' && $passwordInput
                ) {
                    $this->logger->debug("Try to enter password via selenium methods");
                    $passwordInput->sendKeys($this->AccountFields['Login']);
                    sleep(1);
                    $this->driver->executeScript('
                        var loginBtn = $(\'.travelid-login__loginButton:not(:hidden)\');
                        loginBtn.click();
                    ');
                    sleep(2);

                    continue;
                }

                if (
                    // It is not possible to log in. Please contact your local Miles & More Service Team.
                    strstr($message, 'It is not possible to log in.')
                    || $message == 'Wrong username or password'
                    || $message == 'Only numbers (0-9) are permitted.'
                    || $message == 'Your password has expired. Please change your password.'
                    || $message == 'Your service card number consists of 15 digits.'
                    || $message == 'Your account is not currently active. Please contact your local Miles & More Service Team to reactivate your account.'
                    || $message == 'Please check your login data. Login with Lufthansa iD, austrian.com profile and swiss.com profile is no longer possible. Register for Travel ID.'
                    || $message == 'Please check your login data.'
                    || $message == 'Only digits (0-9) are permitted.'
                    || $message == 'Validation has failed: {0}'
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $message == 'Access Denied'
                    || $message == 'The connection has timed out'
                ) {
                    $this->markProxyAsInvalid();
                    throw new CheckRetryNeededException(3, 5);
                }

                if (
                    strstr($message, 'A technical error has occurred.')
                    || $message == 'An error occurred'
                    || $message == 'A technical problem has occurred.'
                    || $message == 'Currently a lot of users are trying to use this process. Please re-try at a later time'
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                break;
            }

            if ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'We apologise for the interruption. We detected unusual behaviour from your browser, which resembles that of a bot.')]"), 0)) {
                $this->markProxyAsInvalid();
                throw new CheckRetryNeededException(3, 5);
            }

            $this->saveResponse();
            $time = time() - $startTime;
        }

        if (!$login) {
            try {
                $this->http->GetURL('https://www.lufthansa.com/us/en/homepage');
            } catch (Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
                $this->logger->error('InvalidSessionIdException: ' . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(3, 0);
            }
            $logout = $this->waitForElement(WebDriverBy::xpath("//span[@id = 'lh-loginModule-name']"), 30);
            $this->saveResponse();
            if ($logout) {
                $login = true;
                $this->markProxySuccessful();
                $this->increaseTimeLimit(100);
            }
        }

        return $login;
    }

    public function Parse()
    {
        $profileResp = $this->getApi('https://www.lufthansa.com/service/secured/api/core/user/profile');
        $profile = $this->http->JsonLog($profileResp);

        // Balance
        $currenciesLongList = $profile->profile->bloomStatus->display->currenciesLongList ?? [];

        foreach ($currenciesLongList as $item) {
            if ($item->label == 'Miles') {
                $this->SetBalance($item->amount);
            } elseif ($item->label == 'Miles (Pool)') {
                $this->SetProperty("PoolBalance", $item->amount);
            } elseif ($item->label == 'Points') {
                // Points
                $this->SetProperty('Points', $item->amount);
            } elseif ($item->label == 'Qualifying Points') {
                // Qualifying Points
                $this->SetProperty('QualifyingPoints', $item->amount);
            } elseif ($item->label == 'HON Circle Points') {
                // HON Circle Points
                $this->SetProperty('HONCirclePoints', $item->amount);
            }
        }

        foreach ($profile->profile->bloomStatus->display->teaserStatus->detailView->infoTexts ?? [] as $infoText) {
            if (preg_match('/You still require ([\d.,]+) Points and ([\d.,]+) Qualifying Points this calendar year to achieve/', $infoText, $m)) {
                // Points needed to next level
                $this->SetProperty('PointsNeededToNextLevel', $m[1]);
                // Qualifying Points needed to next level
                $this->SetProperty('QualifyingPointsNeededToNextLevel', $m[2]);
            }
            if (preg_match('/You still require ([\d.,]+) HON Circle Points this calendar year to achieve/', $infoText, $m)) {
                // HON Circle Points needed to the next level
                $this->SetProperty('HONCirclePointsNeededToTheNextLevel', $m[1]);
            }
        }

        // Name
        $this->SetProperty("Name", $profile->profile->generalDetails->firstName . " " . $profile->profile->generalDetails->lastName);

        // Status
        $status =
            $profile->profile->loyaltyDetails->programCard->statusCode->value
            ?? null
        ;
        $this->logger->debug("[Status]: {$status}");

        switch ($status) {
            case 'BASE':
            case 'INST':
                $status = 'Miles & More member';

                break;

            case 'FTL':
                $status = 'Frequent Traveller';

                break;

            case 'HON':
                $status = 'HON Circle';

                break;

            case 'SEN':
                $status = 'Senator';

                break;

            default:
                if (!empty($status)) {
                    $this->sendNotification("unknown tier was found {$status}");
                }
        }// case ($mainInfo->maminfo->status)

        if ($status) {
            $this->SetProperty("Status", $status);
        }
        // eVoucher
        $this->SetProperty("EVouchers",
            $profile->profile->loyaltyDetails->remainingEVoucher
            ?? null
        );
        // Card number
        $this->SetProperty("Number",
            $profile->profile->loyaltyDetails->programCard->cardNumber
            ?? null
        );

        $dataAccResp = $this->getApi('https://www.lufthansa.com/service/secured/api/core/user/accountstatement?showHistory=false');
        if ($this->http->FindPreg('/\{"error":"(No MAM User|No account statement found)"\}/', false, $dataAccResp))
            $dataAccResp = $this->getApi('https://www.lufthansa.com/service/secured/api/core/user/accountstatement?showHistory=false');
        $dataAcc = $this->http->JsonLog($dataAccResp);

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (!empty($this->Properties['Name'])
                && $this->http->FindPreg('/\{"error":"(No MAM User|No account statement found)"\}/', false, $dataAccResp)
            ) {
                $this->logger->notice('Non Miles & More member');
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }

        // Account number - Miles & More customer number
        $this->SetProperty("CustomerNumber", $dataAcc->maminfo->accountNumber ?? $dataAcc->milesInfo->accountNumber ?? null);
        // Status valid until
        $statusValidUntil = preg_replace('/(\d{4})(\d{2})(\d{2})/', '$1/$2/$3', $dataAcc->maminfo->currentStatusExpiryDate ?? $dataAcc->milesInfo->currentStatusExpiryDate ?? null);
        $this->logger->debug("Status valid until: {$statusValidUntil} / " . strtotime($statusValidUntil));

        if ($statusValidUntil && strtotime($statusValidUntil) < strtotime("+5 year")) {
            $this->SetProperty("Statusvalidityuntil", date("F Y", strtotime($statusValidUntil)));
        }
        // Status frequencies
        $visible = $dataAcc->maminfo->statusFrequencies->visible ?? $dataAcc->milesInfo->statusFrequencies->visible ?? false;

        if ($visible) {
            $this->SetProperty("FlightSegments", $dataAcc->maminfo->statusFrequencies->value ?? $dataAcc->milesInfo->statusFrequencies->value ?? null);
        }



        $expires = $dataAcc->maminfo->statusMessages ?? $dataAcc->milesInfo->statusMessages ?? [];
        $value = 0;
        $date = 0;

        foreach ($expires as $expire) {
            if (preg_match("/([\d,]+) award miles expire on ([\d\-\/.]+)\./ims", $expire, $matches)) {
                if (isset($matches[2])) {
                    $this->logger->debug(var_export($matches, true), ['pre' => true]);

                    if (strstr($matches[2], '/')) {
                        $exp = explode('/', $matches[2]);
                        $matches[2] = $exp[0] . "-" . $exp[2] . "-" . $exp[1];
                    }
                }// if (isset($matches[2]))
            }// if (preg_match("/([\d\,]+) award miles expire on ([\d\-\/.]+)\./ims"
            // Deutsch
            elseif (preg_match("/([\d,]+) .+ verfallen zum ([\d\-\/.]+)\./ims", $expire, $matches)) {
                $this->logger->debug(var_export($matches, true), ['pre' => true]);
            } elseif (empty($value)) {
                $this->logger->notice(">>> Expiration date is not found");
                /**
                 * TODO: without any checks exclusively for Lufthansa // refs #17670
                 * We delete the expiration date because it’s not displayed on the provider’s website, herefore, it might be outdated.
                 */
                $this->ClearExpirationDate();
            }
            // Filter dates
            if (isset($matches[2])) {
                $expTime = strtotime($matches[2]);
                $this->logger->debug("expTime: $expTime ");
                $this->logger->debug("date: $date ");

                if ($expTime && ($expTime < $date || $date == 0)) {
                    $date = $expTime;

                    if (isset($matches[1])) {
                        $value = $matches[1];
                    }
                }// if ($expTime && ($expTime < $date || $date == 0))
            }// if (isset($matches[2]))

            if ($value != 0 && $date != 0) {
                $this->SetProperty("MilesToExpire", $value);
                $this->SetExpirationDate($date);
            }// if ($value != 0 && $date != 0)
        }// foreach ($expires as $expire)
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"                   => "PostingDate",
            "Description"            => "Description",
            "Award miles"            => "Miles",
            "Status miles"           => "Info",
            "Executive Bonus"        => "Bonus",
            "Status&HONCircle Miles" => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $result = [];
        $startTimer = $this->getTime();

        // current statement
        $startIndex = sizeof($result);
        $response = $this->getApi('https://www.lufthansa.com/service/secured/api/core/user/accountstatement?showHistory=false');
        if ($this->http->FindPreg('#"message":"Forbidden", "url":#', false, $response)) {
            $this->sendNotification("history forbidden // MI");
        } else {
            $this->sendNotification("history success // MI");
        }
        $response = $this->http->JsonLog($response);
        $result = array_merge($result, $this->ParsePageHistoryV2($response, $startIndex, $startDate));
        // account history
        if (!$this->endHistory) {
            $response = $this->getApi('https://www.lufthansa.com/service/secured/api/core/user/accountstatement?showHistory=true');
            $response = $this->http->JsonLog($response);
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistoryV2($response, $startIndex, $startDate));
        }

        $this->getTime($startTimer);

        return $result;
    }

    private function ParsePageHistory($response, $startIndex, $startDate)
    {
        $result = [];
        $activities = $response->maminfo->activities ?? $response->milesInfo->activities ?? [];
        $this->logger->debug("Total " . count($activities) . " history items were found");

        foreach ($activities as $activity) {
            $dateStr = $activity->activityDate ?? null;

            if ($dateStr === null && isset($result[$startIndex - 1]['Date'])) {
                $postDate = $result[$startIndex - 1]['Date'];
            } else {
                $postDate = strtotime($dateStr);
            }

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                break;
            }// if (isset($startDate) && $postDate < $startDate)
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Description'] = $activity->activityDescription;

            if (!empty($activity->promotions)) {
                foreach ($activity->promotions as $promotion) {
                    $result[$startIndex]['Description'] .= ' | ' . $promotion->activityDescription;

                    $activityDescription = $promotion->activityDescription;

                    switch ($activityDescription) {
                        case 'Executive Bonus':
                            if (isset($promotion->statusMilesSign, $promotion->statusMiles)) {
                                $result[$startIndex]['Executive Bonus'] = $promotion->statusMilesSign . $promotion->statusMiles;
                            }

                            break;

                        case 'Status&HONCircle Miles Promotion':
                            if (isset($promotion->statusMilesSign, $promotion->statusMiles)) {
                                $result[$startIndex]['Status&HONCircle Miles'] = $promotion->statusMilesSign . $promotion->statusMiles;
                            }

                            break;
//                        case 'over 3 years old':

                        default:
                            $this->logger->notice("Unknown activity: {$activityDescription}");

                            if (!empty($promotion->amount)) {
                                $this->sendNotification("need to check history // RR");
                            }

                            break;
                    }
                }
            }

            if (isset($activity->marketingPartnerCode, $activity->marketingFlightNumber)) {
                $result[$startIndex]['Description'] .= " | $activity->marketingPartnerCode $activity->marketingFlightNumber";

                if (isset($activity->operatingPartnerCode, $activity->operatingFlightNumber)) {
                    $result[$startIndex]['Description'] .= "/{$activity->operatingPartnerCode} {$activity->operatingFlightNumber}";
                }

                if (isset($activity->serviceClass)) {
                    $result[$startIndex]['Description'] .= " | {$activity->serviceClass}";
                }
            }// if (isset($activity->marketingPartnerCode, $activity->marketingFlightNumber))
            elseif (isset($activity->marketingPartnerCode)) {
                $result[$startIndex]['Description'] .= ' | ' . $activity->marketingPartnerCode;
            }

            foreach ($activity->amounts as $amount) {
                switch ($amount->currency) {
                    case 'AWD':
                        $result[$startIndex]['Award miles'] = $amount->amount;

                        break;

                    case 'STA':
                        $result[$startIndex]['Status miles'] = $amount->amount;
                }
            }

            $startIndex++;
        }// foreach ($activities as $activity)

        return $result;
    }

    private function ParsePageHistoryV2($response, $startIndex, $startDate)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $activities = $response->bloomInfo->records ?? [];
        $this->logger->debug("Total " . count($activities) . " history items were found");
//        $this->sendNotification('check history // MI');
//        if (count($activities)) {
//            $this->sendNotification('check history 2 // MI');
//        }
        foreach ($activities as $activity) {
            $dateStr = $activity->date ?? null;

            if ($dateStr === null && isset($result[$startIndex - 1]['Date'])) {
                $postDate = $result[$startIndex - 1]['Date'];
            } else {
                $postDate = strtotime($dateStr);
            }

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                break;
            }// if (isset($startDate) && $postDate < $startDate)
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Description'] = $activity->description->default ?? $activity->retroDescription->retroFlightInfoText ?? '';
            if (isset($activity->description->flightInfo)) {
                $result[$startIndex]['Description'] .= ' | ' . $activity->description->flightInfo;
            } elseif(isset($activity->retroDescription->retroFlightInfoText)) {
                $result[$startIndex]['Description'] .= ' | ' . $activity->retroDescription->retroFlightInfoText;
            }

            if (!empty($result[$startIndex]['Description']))
                $result[$startIndex]['Description'] = trim($result[$startIndex]['Description'], "| ");


            if (isset($activity->currencyAmounts)) {
                foreach ($activity->currencyAmounts as $amount) {
                    switch ($amount->currency) {
                        case 'AWD':
                            $result[$startIndex]['Award miles'] = $amount->amount;

                            break;

                        case 'STA':
                            $result[$startIndex]['Status miles'] = $amount->amount;
                    }
                }
            }

            $startIndex++;
        }// foreach ($activities as $activity)

        return $result;
    }

    public function ParseItineraries()
    {
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();
        $response = $this->sendApiV3('https://api.travelid.lufthansa.com/flights/v3/me/upcoming-trips?lang=en-US&tenant=LH');
        $noItineraries = $this->http->FindPreg('/"content"\s*:\s*\[\s*]/', false, $response);
        $data = $this->http->JsonLog($response);
        if (empty($data))
            return [];
        $i = 0;
        $failedBookingList = [];
        $checker = $this->getLufthansa();
        $bookings = $data->content ?? [];

        foreach ($bookings as $booking) {
            if ($i > 35) {
                $this->logger->error('Maximum limit reached');
                break;
            }
            $i++;
            if (isset($booking->deeplinks->manageBookingDeeplink->body)) {
                $deeplink = $this->http->JsonLog($booking->deeplinks->manageBookingDeeplink->body);
                if (isset($deeplink->recLoc)) {
                    $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $deeplink->recLoc),
                        ['Header' => 3]);
                    $arFields = [
                        'ConfNo' => $deeplink->recLoc,
                        'LastName' => $deeplink->lastName,
                    ];
                    if ($i % 3 == 0) {
                        $this->logger->notice('Increase time limit: 300');
                        $this->increaseTimeLimit(300);
                    }
                    $message = $this->retrieveSeleniumForm($arFields);

                    if (is_string($message)) {
                        $this->logger->error($message);
                        continue;
                    } elseif ($message === false) {
                        $failedBookingList[] = $booking;
                        continue;
                    }
                    $response = $this->http->JsonLog();
                    $checker->parseItinerariesJsonNew($response);
                }
            }
        }

        $this->logger->notice('Failed booking: ' . count($failedBookingList));
//        if (count($failedBookingList) > 0)
//            $this->sendNotification('check failed it // MI');
        $i = 0;
        foreach ($failedBookingList as $booking) {
            if ($i > 10) {
                $this->logger->error('Maximum limit reached');
                break;
            }
            $i++;
            if (isset($booking->filekey) && isset($booking->name)) {
                $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $booking->filekey), ['Header' => 3]);
                $arFields = [
                    'ConfNo'    => $booking->filekey,
                    'LastName'  => $data->lastName,
                ];
                $this->logger->debug('Parsed Locators:');
                $this->logger->debug(var_export($this->parsedLocators, true));

                if (!in_array($booking->filekey, $this->parsedLocators)) {
                    if ($i % 3 == 0) {
                        $this->logger->notice('Increase time limit: 300');
                        $this->increaseTimeLimit(300);
                    }
                    $message = $this->retrieveSeleniumForm($arFields);

                    if (is_string($message)) {
                        $this->logger->error($message);
                        continue;
                    } elseif ($message === false) {
                        $failedBookingList[] = $booking;
                        continue;
                    }
                    $response = $this->http->JsonLog();
                    $checker->parseItinerariesJsonNew($response);
                }
            }
        }

        if (empty($this->itinerariesMaster->getItineraries()) && $noItineraries) {
            $this->itinerariesMaster->setNoItineraries(true);
        }
        $this->getTime($startTimer);

        return [];
    }

    private function retrieveSeleniumForm($arFields)
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $this->http->GetURL("https://shop.lufthansa.com/booking/manage-booking/retrieve");
        sleep(3);
        $this->waitFor(function () {
            return is_null($this->waitForElement(WebDriverBy::xpath("//div[contains(@class,'loading-container')]/img"), 0));
        }, 20);
        $cookie = $this->waitForElement(WebDriverBy::id("cm-acceptAll"), 3);
        if ($cookie) {
            $cookie->click();
            sleep(1);
        }
        $bookingCode = $this->waitForElement(WebDriverBy::xpath("//input[contains(@id,'dentificationorderId')]"), 0);
        $lastname = $this->waitForElement(WebDriverBy::xpath("//input[contains(@id,'dentificationlastName')]"), 0);
        $this->saveResponse();
        if (!$bookingCode || !$lastname) {
            return false;
        }

        $arFields['LastName'] = str_replace('-', '', $arFields['LastName']);
        $this->logger->debug(var_export($arFields, true));
        $bookingCode->sendKeys($arFields['ConfNo']);
        $lastname->sendKeys($arFields['LastName']);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[@type='submit' and contains(.,'Continue')]"), 0);
        $this->saveResponse();
        if (!$button) {
            return false;
        }

        $button->click();
        $this->waitForElement(WebDriverBy::xpath("//h1/div[contains(text(),'Manage Booking')] | //span[contains(@class,'message-title ng-star-inserted')]"), 20);
        $this->saveResponse();
        try {
            $data = $this->driver->executeScript("return sessionStorage.getItem('order');");
            $this->http->SetBody($data);
        } catch (Exception $e) {
            $this->logger->error($e);
            $this->sendNotification('check retry exception // MI');
            $this->waitForElement(WebDriverBy::xpath("//h1/div[contains(text(),'Manage Booking')] | //span[contains(@class,'message-title ng-star-inserted')]"), 10);
            $data = $this->driver->executeScript("return sessionStorage.getItem('order');");
            $this->http->SetBody($data);
        }

        if ($this->http->FindPreg('/"ids":\[\],"entities":\{\}/')) {
            $error = $this->waitForElement(WebDriverBy::xpath("//span[contains(@class,'message-title ng-star-inserted')]"), 0);
            if ($error) {
                $this->logger->error("error: " . $error->getText());
                return $error->getText();
            }
            return false;
        }

        return true;
    }

    private function getApi($url, $isMaM = false) {
        $this->logger->notice(__METHOD__);
        $response = $this->sendApi($url, $isMaM);
        if ($this->http->FindPreg('#"message":"Forbidden", "url":#', false, $response) || empty($response)) {
            sleep(random_int(1, 3));
            $response = $this->sendApi($url, $isMaM);
        }
        return $response;
    }

    private function sendApi($url, $isMaM = false)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[URL]: $url");
        try {
            if ($isMaM) {
                $headers = "
                xhr.withCredentials = true;
                ";
            } else {
                $headers = "
                xhr.setRequestHeader('Accept', 'application/json, text/plain, */*');
                xhr.setRequestHeader('X-Envoy-Upstream-Service-Time', 154);
                xhr.setRequestHeader('X-Portal', 'LH');
                xhr.setRequestHeader('X-Portal-CountryId', '');
                xhr.setRequestHeader('X-Portal-Language', 'en');
                xhr.setRequestHeader('X-Portal-Site', 'US');
                xhr.setRequestHeader('X-Portal-Taxonomy', '');
                xhr.setRequestHeader('X-Vhost', 'publish');
                xhr.setRequestHeader('line', 'g');
                ";
            }
            $this->driver->executeScript("
                var xhr = new XMLHttpRequest();
                xhr.open('GET', '$url');
                $headers
    
                xhr.onreadystatechange = function() {
                    if (this.readyState != 4) {
                        return;
                    }
                    if (this.status != 200) {
                        localStorage.setItem('statusText', this.statusText);
                        localStorage.setItem('responseText', this.responseText);
                        return;
                    }
                    localStorage.setItem('statusText', this.statusText);
                    localStorage.setItem('responseText', this.responseText);
                }
                xhr.send();     
            ");
            sleep(3);
            $response = $this->driver->executeScript("return localStorage.getItem('responseText')");
            $statusText = $this->driver->executeScript("return localStorage.getItem('statusText')");
            if (empty($response)) {
                sleep(3);
                $response = $this->driver->executeScript("return localStorage.getItem('responseText')");
                $statusText = $this->driver->executeScript("return localStorage.getItem('statusText')");
                if (empty($response)) {
                    sleep(3);
                    $response = $this->driver->executeScript("return localStorage.getItem('responseText')");
                    $statusText = $this->driver->executeScript("return localStorage.getItem('statusText')");
                    if (empty($response)) {
                        sleep(3);
                        $response = $this->driver->executeScript("return localStorage.getItem('responseText')");
                        $statusText = $this->driver->executeScript("return localStorage.getItem('statusText')");
                    }
                }
            }
            $this->driver->executeScript("localStorage.removeItem('responseText'); localStorage.removeItem('statusText');");
            $this->logger->info("[Form statusText]: $statusText");
        } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $response = null;
        }
        catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            throw new CheckRetryNeededException(3, 0);
        }

        return $response;
    }

    private function sendApiV3($url)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[URL]: $url");
        try {

            $this->driver->executeScript("
                var xhr = new XMLHttpRequest();
                xhr.open('GET', '$url');
                xhr.withCredentials = true;
                xhr.setRequestHeader('Accept', '*/*');
    
                xhr.onreadystatechange = function() {
                    if (this.readyState != 4) {
                        return;
                    }
                    /*if (this.status != 200) {
                        localStorage.setItem('statusText', this.statusText);
                        localStorage.setItem('responseText', this.responseText);
                        return;
                    }*/
                    localStorage.setItem('responseText', this.responseText);
                }
                xhr.send();     
            ");
            sleep(3);
            $response = $this->driver->executeScript("return localStorage.getItem('responseText')");
            if (empty($response)) {
                sleep(3);
                $response = $this->driver->executeScript("return localStorage.getItem('responseText')");
                if (empty($response)) {
                    sleep(3);
                    $response = $this->driver->executeScript("return localStorage.getItem('responseText')");
                    if (empty($response)) {
                        sleep(3);
                        $response = $this->driver->executeScript("return localStorage.getItem('responseText')");
                    }
                }
            }
            $this->driver->executeScript("localStorage.removeItem('responseText')");
            //$this->logger->info("[Form response]: $response");
        } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $response = null;
        }
        catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            throw new CheckRetryNeededException(3, 0);
        }

        return $response;
    }

    private function savePageToBody()
    {
        $this->logger->notice(__METHOD__);
        $this->http->SaveResponse();
        try {
            $this->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML'));
        } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\JavascriptErrorException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }
    }

    protected function getLufthansa()
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->checker)) {
            $this->checker = new TAccountCheckerLufthansa();
            $this->checker->http = new HttpBrowser("none", new CurlDriver());
            $this->checker->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->checker->http);
            $this->checker->AccountFields = $this->AccountFields;
            $this->checker->itinerariesMaster = $this->itinerariesMaster;
            $this->checker->HistoryStartDate = $this->HistoryStartDate;
            $this->checker->historyStartDates = $this->historyStartDates;
            $this->checker->http->LogHeaders = $this->http->LogHeaders;
            $this->checker->ParseIts = $this->ParseIts;
            $this->checker->ParsePastIts = $this->ParsePastIts;
            $this->checker->WantHistory = $this->WantHistory;
            $this->checker->WantFiles = $this->WantFiles;
            $this->checker->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->checker->http->setDefaultHeader($header, $value);
            }

            $this->checker->globalLogger = $this->globalLogger;
            $this->checker->logger = $this->logger;
            $this->checker->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->checker->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->checker;
    }
}
