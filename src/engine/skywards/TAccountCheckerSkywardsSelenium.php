<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSkywardsSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    /** @var HttpBrowser */
    public $browser;

    public $selenium = true;

    private $businessAccount = false;
    private $parseMainSite = false;

    private $profilePage = 'https://www.emirates.com/account/us/english/manage-account/manage-account.aspx';

    public function delay()
    {
        $delay = rand(1, 10);
        $this->logger->debug("Delay -> {$delay}");
        sleep($delay);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->selectExperimentalSettings();
    }

    public function LoadLoginForm()
    {
        if (!$this->isExperimental()) {
            try {
                $this->http->removeCookies();
            } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("UnknownErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(2, 1);
            }
        }
        // catch alert -> "Start up service failed."
        try {
            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

            $this->driver->manage()->window()->maximize();

            if (
                $currentUrl != $this->profilePage
                && $currentUrl != 'https://www.emirates.com/account/english/login/login.aspx?refUrl=%2faccount%2fenglish%2fmanage-account%2fmanage-account.aspx'
                && $currentUrl != 'https://www.emirates.com/account/english/login/login.aspx?refUrl=%2faccount%2fenglish%2fmanage-account%2fmy-statement%2findex.aspx'
            ) {
                /*
                $this->http->GetURL($this->profilePage);
                */

                if ($this->loginViaPartnerLink()) {
                    $this->usePacFile(false);
                    $this->http->GetURL("https://www.emirates.com/account/ae/english/partner-login/login.aspx?target=FZ&prscode=AFHP7&returnurl=https://www.emirates.com/account/english/manage-account/skywards-skysurfers.aspx");
                } else {
                    $this->http->GetURL('https://www.emirates.com/us/english/');
                    $loginPage = $this->waitForElement(WebDriverBy::xpath('//a[@id= "login-nav-link"]'), 5);

                    if (!$loginPage) {
                        // retries
                        $this->checkBadProxy();

                        return false;
                    }
                    $this->hideOverlay();
                    $loginPage->click();
                    $profileButton = $this->waitForElement(WebDriverBy::cssSelector('a.view-skywards-button'), 3);
                    $this->saveResponse();

                    if ($profileButton) {
                        $this->logger->info("already logged in");
                        $profileButton->click();
                        $this->waitForElement(WebDriverBy::cssSelector("div.membershipSkywardsMiles"), 20);

                        return true;
                    }
                }
            }
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage(), ['HtmlEncode' => true]);

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException $e) {
                $this->logger->debug("no alert, skip");
            }
        } catch (NoAlertOpenException $e) {
            $this->logger->debug("no alert, skip");
        } catch (WebDriverCurlException | Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(2, 1);
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnknownErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(2, 1);
        }

        return $this->authorization();
    }

    /*
    function parseCaptchaNew() {
        $this->logger->debug("parseCaptcha");
        $captcha = $this->driver->executeScript("
        var captchaDiv = document.createElement('div');
        captchaDiv.id = 'captchaDiv';
        document.body.appendChild(captchaDiv);

        var canvas = document.createElement('CANVAS'),
            ctx = canvas.getContext('2d'),
            img = document.getElementById('c_english_login_login_maincontent_ctl00_botdetectcaptcha_CaptchaImage');

        canvas.height = img.height;
        canvas.width = img.width;
        ctx.drawImage(img, 0, 0);
        dataURL = canvas.toDataURL('image/png');

        return dataURL;
        ");
        $this->logger->debug("captcha: ".$captcha);
        $marker = "data:image/png;base64,";
        if(strpos($captcha, $marker) !== 0) {
            $this->logger->debug("no marker");
            return false;
        }
        $captcha = substr($captcha, strlen($marker));
        $file = tempnam(sys_get_temp_dir(), "captcha").".png";
        $this->logger->debug("captcha file: " . $file);
        file_put_contents($file, base64_decode($captcha));

        $recognizer = $this->getCaptchaRecognizer();
        $code = $recognizer->recognizeFile($file);
        unlink($file);

        return $code;
    }

    function parseCaptchaOld() {
        $forValidationCodeButton = $this->waitForElement(WebDriverBy::id("visualCaptcha-img-5"), 30);
        $text = $this->waitForElement(WebDriverBy::className("visualCaptcha-explanation"), 0);
//        $text = $this->http->FindSingleNode("//div[@class = 'visualCaptcha-explanation']", null, true, "/\.\s*([^\-]+)/i");
        $this->saveResponse();
        if (!$forValidationCodeButton || !$text) {
            $this->logger->error('Failed to find captcha img');
            return false;
        }

        sleep(3);
        $captcha = $this->driver->executeScript("

        var captchaDiv = document.createElement('div');
        captchaDiv.id = 'captchaDiv';
        document.body.appendChild(captchaDiv);

        var canvas = document.createElement('CANVAS'),
            ctx = canvas.getContext('2d'),
            img = document.getElementById('visualCaptcha-img-0');

        canvas.height = img.height * 3;
        canvas.width = img.width * 6;

        ctx.font = '16px Verdana';
        ctx.fillText('".$text->getText()."', 0, img.height * 3 - Math.round(img.height / 5));

        for (n = 0; n < 6; n++) {
            img = document.getElementById('visualCaptcha-img-' + n);
            ctx.drawImage(img, img.width * n, 0);
            ctx.font = '14px Verdana';
            ctx.fillText(n+1, img.width * n + Math.round(img.height / 2), img.height * 2);
        }

        dataURL = canvas.toDataURL('image/png');

        return dataURL;

        ");

        $this->logger->debug("captcha: ".$captcha);
        $marker = "data:image/png;base64,";
        if (strpos($captcha, $marker) !== 0) {
            $this->logger->error("no marker");
            return false;
        }
        $captcha = substr($captcha, strlen($marker));
        $file = tempnam(sys_get_temp_dir(), "captcha").".png";
        $this->logger->debug("captcha file: " . $file);
        file_put_contents($file, base64_decode($captcha));

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        // https://rucaptcha.com/support/faq
        $code = $this->recognizeCaptcha($recognizer, $file, ["id_constructor" => '40']);
        unlink($file);

        $code = CleanXMLValue(str_replace("Select:", "", $code));
        $this->logger->debug("Code: $code");
        // wrong response from rucaptcha
        if ($code === "" || (is_numeric($code) && strlen(trim($code)) > 1) || (!is_numeric($code) && strlen(trim($code)) > 1)
            || (!is_numeric($code) && $this->http->FindPreg("/[a-zа-яA-ZА-ЯёЁ]/u", false, $code)) ) {
            $recognizer->reportIncorrectlySolvedCAPTCHA();
            throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
        }// if ($code === "" || (is_numeric($code) && strlen(trim($code)) > 1) || (!is_numeric($code) && strlen(trim($code)) > 1))

        return $code;
    }
    */

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        //# An error occurred while processing your request
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, the service you are trying to access is currently unavailable.
        if ($message = $this->http->FindSingleNode('
                //span[contains(text(), "Sorry, the service you are trying to access is currently unavailable.")]
                | //p[contains(text(), "Sorry, there\'s a problem with our system and we\'re temporarily unable to log you in to your account. Please try again later.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            // Internal Server Error
            || $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Proxy Error')]")
            || $this->http->FindSingleNode("//h2[contains(text(), '500 - Internal server error.')]")
            || $this->http->FindSingleNode("//p[contains(text(), 'HTTP Error 503. The service is unavailable.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, parts of emirates.com are currently unavailable due to a technical problem.
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Sorry, parts of emirates.com are currently unavailable due to a technical problem')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our website isn't available at the moment.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Our website isn\'t available at the moment.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->waitForElement(WebDriverBy::xpath('//button[@id = "login-button" and contains(., "Loading")]'), 0)) {
            /*
                        $this->logger->info("detected loading on button, will wait 10 secs while it disappears");
                        $loaded = self::waitFor(function(){
                            return null === $this->waitForElement(WebDriverBy::xpath('//button[@id = "login-button" and contains(., "Loading")]'), 20);
                        });
                        if (!$loaded) {
                        */
            $this->logger->info("not loaded yet, but try to load profile anyway, sometimes it works");
            /*
            }
            */

            $this->logger->info('Loading', ['Header' => 3]);

            try {
                $this->logger->info('Try to load profile', ['Header' => 3]);
                $this->http->GetURL($this->profilePage);
            } catch (TimeOutException | ScriptTimeoutException $e) {// works
                $this->logger->error("Exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
            }
            $logout = $this->detectingLogoutLink(1);

            if ($logout) {
//                $this->sendNotification("[0] second auth success {$this->attempt} // RR");
                return true;
            } else {
                $this->logger->info('Second attempt', ['Header' => 3]);
                $loginInput = $this->waitForElement(WebDriverBy::id('sso-email'), 10);
                $passwordInput = $this->waitForElement(WebDriverBy::id('sso-password'), 0);
                $btnLogIn = $this->waitForElement(WebDriverBy::id('login-button'), 0);
                $this->saveResponse();

                $this->closeCookiePopup();

                if (!$loginInput || !$passwordInput || !$btnLogIn) {
                    $this->logger->error('something went wrong');

                    return $this->checkErrors();
                }// if (!$loginInput || !$passwordInput)
                // refs #14450
                $loginInput->clear();
                $loginInput->sendKeys($this->AccountFields['Login']);
                $passwordInput->clear();
                $passwordInput->sendKeys($this->AccountFields['Pass']);

                if ($this->selenium) {
                    $btnLogIn->click();
                    $logout = $this->detectingLogoutLink(25);

                    if ($logout) {
//                        $this->sendNotification("[3] second auth success {$this->attempt} // RR");
                        return true;
                    }
                }

                try {
                    $this->logger->info('Try to load profile', ['Header' => 3]);
                    $this->http->GetURL($this->profilePage);
                } catch (TimeOutException | ScriptTimeoutException $e) {// works
                    $this->logger->error("Exception: " . $e->getMessage());
                    $this->driver->executeScript('window.stop();');
                }
                $logout = $this->detectingLogoutLink(1);

                if ($logout) {
//                    $this->sendNotification("[4] second auth success {$this->attempt} // RR");
                    return true;
                }
            }

            throw new CheckRetryNeededException(2, 1);
        }

        return $this->detectingLogoutLink(1);

        return false;
    }

    public function detectingLogoutLink($sleep = 10, $selenium = false)
    {
        $this->logger->notice(__METHOD__);
        $doNotSavePage = false;

        if ($this->selenium || $selenium) {
            $startTime = time();

            while ((time() - $startTime) < $sleep) {
                $currentTime = time() - $startTime;
                $this->logger->debug("(time() - \$startTime) = {$currentTime} < {$sleep}");
                $logout = $this->waitForElement(WebDriverBy::xpath("
                        //div[contains(@class, 'membershipName')]
                        | //div[@class = 'membershipSkywardsMiles']/div[@class= 'milesCount']
                        | //span[contains(@class, \"icon-profile-account\")]/following-sibling::span[not(normalize-space(text()) = 'Log in')]
                        | //div[@class = 'welcome-message']/span
                        | //div[@class = 'form-container']//input[@value = 'Log in']
                        | //h1[contains(text(), 'Business Rewards Dashboard')]
                "), 0);

                if (!$logout) {
                    $logout = $this->waitForElement(WebDriverBy::xpath("//span[@id = 'tlmembershipnumber']"), 0, false);
                }
                $error = $this->waitForElement(WebDriverBy::xpath('
                    //div[@id = "validationSummary"]
                    | //div[@id = "MainContent_SSLogin_pnlErrorMessage"]
                    | //div[contains(@class, "login-error")]
                    | //div[contains(@class, "focus-alert alert alert-danger")][count(./ancestor::div[contains(@class,"noshow")])=0]
                '), 0)
                    ?? $this->http->FindSingleNode('//div[@id="validationSummary" and @class="errorPanel"]/ul/li')
                ;

                if ($error) {
                    $this->logger->debug("error found");
                    $doNotSavePage = true;
                    $this->saveResponse();
                }

                if ($this->waitForElement(WebDriverBy::xpath('//p[
                        contains(text(), "An email with a 6-digit passcode has been sent to")
                        or contains(text(), "Please choose how you want to receive your passcode.")
                    ]'), 0)
                ) {
                    $this->saveResponse();

                    return false;
                }

                try {
                    if ($logout || $doNotSavePage == false) {
                        $this->saveResponse();
                    }
                } catch (WebDriverCurlException | NoSuchDriverException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                }

                if ($logout && !$error) {
                    try {
                        $this->logger->debug("[Current URl]: {$this->http->currentUrl()}");
                    } catch (NoSuchDriverException $e) {
                        $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());
                    }
                    $this->browser = $this->http;

                    return true;
                } elseif ($error && $currentTime > 10) {
                    return false;
                }

                try {
                    $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
                } catch (NoSuchDriverException $e) {
                    $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());

                    throw new CheckRetryNeededException(3, 0);
                }

                if ($msg = $this->http->FindPreg('/accessrestricted/', false, $this->http->currentUrl())) {
                    $this->DebugInfo = $msg;
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;
                    $this->markProxyAsInvalid();

                    if ($this->attempt == 0) {
                        Cache::getInstance()->set('skywards_fail', "true", 60 * 60 * 0.5);
                    }

                    throw new CheckRetryNeededException(3, 0);
                } elseif ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                    $this->DebugInfo = $msg;
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;

                    $this->checkBadProxy();
                } else {
                    $this->DebugInfo = null;
                }

                if (!$error && !$this->http->FindNodes('//div[@id="validationSummary" and @class="errorPanel"]/ul/li')) {
                    $this->checkBadProxy();
                }

                if ($this->http->currentUrl() == 'https://skywards.flydubai.com/en/login') {
                    throw new CheckRetryNeededException(3, 0);
                }
            }// while ((time() - $startTime) < $sleep)
        } elseif ($this->browser->FindNodes("//a[contains(@href, 'logout')]")) {
            return true;
        }

        return false;
    }

    public function twoStepVerification()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Two-step verification', ['Header' => 3]);

        if (
            $this->http->FindSingleNode('//p[contains(text(), "Please choose how you want to receive your passcode.")]')
            && ($email = $this->waitForElement(WebDriverBy::xpath("//div[label[@for = 'radio-button-email']]"), 0))
        ) {
            $email->click();
            $sendOTP = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'send-OTP-button']"), 0);
            $sendOTP->click();

            $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "An email with a 6-digit passcode has been sent to")]'), 5);
            $this->saveResponse();
            $this->holdSession();
        }

        $question = $this->http->FindSingleNode('//p[contains(text(), "An email with a 6-digit passcode has been sent to")]');

        if (!$question) {
            $this->logger->error("something went wrong");

            return false;
        }
        $answerInputs = $this->driver->findElements(WebDriverBy::xpath("//input[contains(@class, 'otp-input-field__input')]"));
        $this->saveResponse();
        $this->logger->debug("count answer inputs: " . count($answerInputs));

        if (!$question || empty($answerInputs)) {
            $this->logger->error("something went wrong");

            return false;
        }
        $this->saveResponse();

        if (!isset($this->Answers[$question])) {
            $this->AskQuestion($question, null, 'Question');

            return false;
        }
        $this->logger->debug("entering answer...");
        $answer = $this->Answers[$question];

        foreach ($answerInputs as $i => $answerInput) {
            if (!isset($answer[$i])) {
                $this->logger->error("wrong answer");

                break;
            }
            $answerInput->sendKeys($answer[$i]);
            $this->saveResponse();
        }// foreach ($elements as $key => $element)
        unset($this->Answers[$question]);
        $this->saveResponse();

        $this->logger->debug("wait errors...");
        $errorXpath = "//p[
                contains(text(), 'The one-time passcode you have entered is incorrect')
                or contains(text(), ' incorrect attempts to enter your passcode. You have ')
                or contains(text(), 'Sorry, you have exceeded the allowed number of attempts to enter your passcode. Your account is temporarily locked for 30 minutes.')
        ]";
        $error = $this->waitForElement(WebDriverBy::xpath($errorXpath), 5);
        $this->saveResponse();

        if (!$error && $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Loading")]'), 0)) {
            $error = $this->waitForElement(WebDriverBy::xpath($errorXpath), 10);
            $this->saveResponse();
        }

        if ($error) {
            $message = $error->getText();

            if (
                strstr($message, 'The one-time passcode you have entered is incorrect')
                || strstr($message, ' incorrect attempts to enter your passcode. You have ')
            ) {
                $this->logger->notice("resetting answers");
                $this->AskQuestion($question, $message, 'Question');
                $this->holdSession();

                return false;
            } elseif (
                strstr($message, 'Sorry, you have exceeded the allowed number of attempts to enter your passcode. Your account is temporarily locked for 30 minutes.')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($error)
        $this->logger->debug("success");
        $this->logger->debug("[CurrentURL]: {$this->http->currentUrl()}");

        $this->parseMainSite = true;

        $this->http->GetURL('https://www.emirates.com/account/english/manage-account/upcoming-flights/upcoming-flights.aspx');
        $this->detectingLogoutLink(5);

        $this->browser = $this->http;

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if (
            $this->isNewSession()
            || $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Your session has expired')]"), 0)
        ) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == "Question") {
            return $this->twoStepVerification();
        }

        return true;
    }

    public function Login()
    {
        $logout = $this->detectingLogoutLink(15);

        if ($this->loginViaPartnerLink()) {
            // refs #20992
            if ($message = $this->http->FindSingleNode("//*[self::li or self::div][
                    contains(text(), 'Sorry, the email address, Emirates Skywards number or password you entered is incorrect. Please check and try again.')
                    or contains(text(), 'Sorry, there are multiple accounts active for this email address. If you')
                ]")
            ) {
                $this->State['isBusiness'] = true;

                throw new CheckRetryNeededException(3, 0, $message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        if ($this->http->FindSingleNode('//p[
                contains(text(), "An email with a 6-digit passcode has been sent to")
                or contains(text(), "Please choose how you want to receive your passcode.")
            ]')
        ) {
            return $this->twoStepVerification();
        }

        // Your account has been proactively locked as a security precaution.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Your account has been proactively locked as a security precaution.')]")) {
            $this->markProxyAsInvalid();

            throw new CheckException("Your account has been proactively locked as a security precaution.", ACCOUNT_LOCKOUT);
        }
        //# Invalid credentials
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Membership Number or Password you entered is incorrect')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, the email address, Emirates Skywards number or password you entered is incorrect
        if ($message = $this->http->FindSingleNode("//p[
                (
                    contains(text(), 'Sorry, the email address, Emirates Skywards number or password you entered is incorrect')
                    or contains(text(), 'Email address or Emirates Skywards number: This is a required field; please check and try again.')
                    or contains(text(), 'Sorry, the email address, Emirates Skywards number, or password you entered is incorrect. Please check and try again')
                )
                and not(contains(@class, 'hide'))
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Sorry, there are multiple accounts active for this email address. If you\'re an Emirates Skywards") and not(contains(@class, "hide"))]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Sorry, we encountered a problem when submitting this request
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, we encountered a problem when submitting this request') and not(contains(@class, 'hide'))]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//*[self::p or self::div][
                (
                    contains(text(), "Sorry, this account isn\'t accessible at the moment due to a routine review.")
                    or contains(text(), "Sorry there\'s a problem with our system and we\'re temporarily unable to log you in to your account.")
                    or contains(text(), "Sorry, this account has been deactivated.")
                    or contains(text(), "Sorry, there’s a problem with our system and we’re temporarily unable to log you in to your account.")
                )
                and not(contains(@class, "hide"))
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[
                (
                    contains(text(), "Sorry, your account has been locked. If you need urgent access to your account, please talk to our representatives ")
                    or contains(text(), "Your account has been locked as a security precaution. To regain access to your account")
                )
                and not(contains(@class, "hide"))
            ]')
        ) {
            $this->markProxyAsInvalid();

            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindSingleNode('//span[
                contains(text(), "Sorry, this account has been cancelled.")
                or contains(text(), "Sorry, this account has been canceled.")]')
        ) {
            throw new CheckException("Sorry, this account has been cancelled.", ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, your account is temporarily unavailable.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, your account is temporarily unavailable.')]", null, true, '/(Sorry, your account is temporarily unavailable\.)/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        //# Sorry, The Skywards account is not available for use. Please call an Emirates Contact Centre for assistance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, The Skywards account is not available for use')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Sorry, a Skysurfers member is not eligible to log in to book online
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Sorry, a Skysurfers member is not eligible to log in to book online')] | //div[contains(text(), 'Sorry, a Skysurfers member is unable to log in to emirates.com.')] | //div[contains(text(), 'Sorry, Skysurfers are not eligible to log in to emirates.com.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Sorry, the skywards login functionality is currently unavailable
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Sorry, the skywards login functionality is currently unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, some information is missing from your account.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, some information is missing from your account.')]", null, true, '/(Sorry, some information is missing from your account\.)/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, this membership number belongs to a merged account
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Sorry, this membership number belongs to a merged account.") and not(contains(@class, "hide"))]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[span[contains(text(), "Sorry, your account is temporarily unavailable.")] and not(contains(@class, "hide"))]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, your account is not accessible at the moment due to a routine review
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, your account is not accessible at the moment due to a routine review')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, we have a technical problem at the moment. Please try again later.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, we have a technical problem at the moment. Please try again later.')]")) {
            throw new CheckRetryNeededException(2, 1, $message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, a Skysurfers member is unable to log in to emirates.com
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Sorry, a Skysurfers member is unable to log in to emirates.com')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, Emirates Skywards Family Bonus members are unable to log in to emirates.com
//        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Sorry, Emirates Skywards Family Bonus members are unable to log in to emirates.com')]"))
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        // Sorry, we encountered a problem when submitting this request.
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Sorry, we encountered a problem when submitting this request.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The email address that you are using for your account is also linked to another Emirates Skywards member
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'The email address that you are using for your account is also linked to another Emirates Skywards member')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//li[contains(text(), "Sorry, you can\'t log in to this site using your Emirates Business Rewards account.")]')) {
            $this->State['isBusiness'] = true;

            throw new CheckRetryNeededException(3, 0);
        }

        // provider bug - Please log in to your account.
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
//        if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Please log in to your account.')]"), 0))
//            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

        // Activate Your Online Access
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Activate Your Online Access')]")
            && strstr($this->http->currentUrl(), 'activate-membership.aspx')) {
            throw new CheckException("Emirates (Skywards) website is asking you to confirm your email address and set a password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        // Please complete the CAPTCHA instruction correctly to prove you are human. This is mandatory
        if ($this->selenium) {
            if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG && ($this->waitForElement(WebDriverBy::xpath("//li[contains(text(), 'Please complete the CAPTCHA')]"), 0)
                || $this->waitForElement(WebDriverBy::xpath("//li[contains(text(), 'Please complete an audio or visual verification method to continue.') and not(contains(@class, 'hide'))]"), 0)
                // selenium bug?
                || $this->waitForElement(WebDriverBy::xpath("//li[contains(text(), 'Password : This is a mandatory field, please check and try again.') and not(contains(@class, 'hide'))]"), 0))) {
                $this->saveResponse();

                throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
            }
        }// if ($this->selenium)
        else {
            if (/*$this->browser->FindSingleNode("//li[contains(text(), 'Please complete the CAPTCHA')]")
                || */ $this->browser->FindSingleNode("//li[contains(text(), 'Please complete an audio or visual verification method to continue.') and not(contains(@class, 'hide'))]")
                // selenium bug?
                || $this->browser->FindSingleNode("//li[contains(text(), 'Password : This is a mandatory field, please check and try again.') and not(contains(@class, 'hide'))]")) {
                $this->saveResponse();

                if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                    $retries = 3;
                } else {
                    $retries = 1;
                }

                throw new CheckRetryNeededException($retries, 7, self::CAPTCHA_ERROR_MSG);
            }
        }

        // todo: debug
        try {
            if (
                $this->loginViaPartnerLink()
                && strstr($this->http->currentUrl(), 'https://www.emirates.com/account/ae/english/partner-login/login.aspx?target=FZ&prscode=FZSE&returnurl=')
            ) {
                $logout = $this->detectingLogoutLink(25);
                $this->saveResponse();
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        if (
            $this->loginViaPartnerLink()
            && $this->http->currentUrl() == 'https://skywards.flydubai.com/en/session'
        ) {
            // provider bug fix (selenium bug fix)
            $this->http->GetURL('https://www.emirates.com/account/system/aspx/ExternalTransfer.aspx?target=FZ&returnurl=https://www.emirates.com/account/english/manage-account/skywards-skysurfers.aspx');
            $this->saveResponse();

            sleep(3);
            $this->http->GetURL('https://www.emirates.com/account/english/manage-account/skywards-skysurfers.aspx');

            try {
                $logout = $this->detectingLogoutLink(5);
            } catch (NoSuchDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage());

                throw new CheckRetryNeededException(3, 0);
            }

            if (strstr($this->http->currentUrl(), 'https://accounts.emirates.com/english/sso/login?clientId=')) {
                throw new CheckRetryNeededException(3, 5);
            }
        }
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        // Sorry, we've encountered a problem. Please try again using your Emirates Skywards number or call an Emirates Contact Centre for assistance.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 've encountered a problem. Please try again using your Emirates Skywards number or call an Emirates Contact Centre for assistance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, this account isn't accessible at the moment due to a routine review.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Sorry, this account isn\'t accessible at the moment due to a routine review.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->ParseForm("aspnetForm") && !$this->http->FindSingleNode("//div[@id = 'validationSummary']")) {
            $this->DebugInfo = "Login page";
            $this->logger->debug("[URL]: {$this->http->currentUrl()}");

            if ($this->http->InputExists('txtMembershipNo') && $this->http->InputExists('txtPassword')
                && $this->http->Form['txtMembershipNo'] == '' && $this->http->Form['txtPassword'] == '') {
                $this->logger->debug("[txtMembershipNo]: {$this->http->Form['txtMembershipNo']}");
                $this->logger->debug("[txtPassword]: {$this->http->Form['txtPassword']}");

                throw new CheckRetryNeededException(3, 5);
            }
        }// if ($this->http->ParseForm("aspnetForm") && !$this->http->FindSingleNode("//div[@id = 'validationSummary']"))

        return $this->checkErrors();
    }

    // ===============================
    // Parse
    // ===============================

    public function parseViaPartnerLink()
    {
        $this->logger->notice(__METHOD__);
        //# Balance - Skywards Miles
        $this->SetBalance($this->browser->FindSingleNode("(//div[@class = 'membershipSkywardsMiles']/div[@class= 'milesCount'])[1]", null, true, '/([\d\.\,]+)/ims'));
        //# Account Number
        $this->SetProperty("SkywardsNo", $this->browser->FindSingleNode("(//div[@class = 'userWelcome']/div[@class = 'membershipNumber'])[1]", null, true, '/([\w\s]+)/ims'));
        //# Name
        $this->SetProperty("Name", $this->browser->FindSingleNode("(//div[@class = 'userWelcome']/div[@class = 'membershipName']/text())[1]"));
        //# Tier
        $this->SetProperty("CurrentTier", $this->browser->FindSingleNode("//span[@id = 'loginControl_spnMemberTier']"));
        // refs #12080
        if (isset($this->Properties['CurrentTier']) && $this->Properties['CurrentTier'] == 'SKYWARDS') {
            $this->ArchiveLogs = true;
            $this->sendNotification("skywards. Showing wrong status - SKYWARDS, refs #12080");
        }
        //# Tier Miles
        $this->SetProperty("TierMiles", $this->browser->FindSingleNode("//span[contains(@id, '_lblSkywardsTierMiles')]", null, true, '/([\d\.\,]+)/ims'));
        // Skywards Miles Expiring
        $date = $this->browser->FindSingleNode("//div[@id = 'skywardSection']//div[contains(@class, 'desktop')]/span[contains(text(), 'expire on')]", null, true, "/expire\s*on\s*([^<]+)/");
        $quantity = $this->browser->FindSingleNode("//div[@id = 'skywardSection']//div[contains(@class, 'desktop')]/span[contains(text(), 'expire on')]", null, true, "/([\d\.\,\s]+)\s+mile/ims");
        $this->logger->debug("Date: {$date} (" . strtotime($date) . ") / {$quantity}");

        if (strtotime($date)) {
            $this->SetExpirationDate(strtotime($date));
            // Miles to Expire
            $this->SetProperty("MilesToExpire", $quantity);
        }// if (strtotime($date))

        $this->ParseSubaccountMyFamily();

        // "My Account" > "Skywards Skysurfer"
        $skysurferMembers = $this->browser->XPath->query("//div[@id = 'MainContent_ctl00_linkedSkysurferMembers']//div[@class = 'sky-surfers-user-box-container']");
        $this->logger->debug("Total {$skysurferMembers->length} skysurfers members were found");

        foreach ($skysurferMembers as $skysurferMember) {
            $name = beautifulName($this->browser->FindSingleNode(".//h3[contains(@class, 'skysurfer-name')]", $skysurferMember));
            // Account Number
            $skywardsNo = $this->browser->FindSingleNode(".//span[contains(@class, 'skywards-num')]", $skysurferMember);
            $subAccount = [
                'Code'        => 'skywardsSkysurfer' . str_replace(' ', '', $skywardsNo),
                'DisplayName' => "Skywards Skysurfer: " . $name . " ({$skywardsNo})",
                'Balance'     => $this->browser->FindSingleNode(".//span[contains(text(), 'Skywards Miles')]/following-sibling::span[contains(@class, 'skywards-miles-earned')]", $skysurferMember),
                // Name
                'Name'        => $name,
                // Account Number
                'SkywardsNo'  => $skywardsNo,
                // Tier
                'CurrentTier' => $this->browser->FindSingleNode(".//span[@id = 'skysurfer-tier']", $skysurferMember),
                // Tier Miles
                'TierMiles'   => $this->browser->FindSingleNode(".//span[contains(text(), 'Tier Miles')]/following-sibling::span[contains(@class, 'skywards-miles-earned')]", $skysurferMember),
            ];
            // Expiration Date
            $exp = $this->browser->FindSingleNode(".//span[contains(text(), 'expire on')]/following-sibling::span[1]", $skysurferMember);
            // ... Skywards Miles are due to expire on ...
            $milesToExpire = $this->browser->FindSingleNode(".//span[contains(text(), 'expire on')]/preceding-sibling::span[1]", $skysurferMember);
            $subAccount['MilesToExpire'] = $milesToExpire;

            if ($milesToExpire && ($exp = strtotime($exp))) {
                $subAccount['ExpirationDate'] = $exp;
            }
            // add subAccount
            $this->AddSubAccount($subAccount, true);
        }// foreach ($skysurferMembers as $skysurferMember)

        // Emirates (Business Rewards) SubAccounts // refs #14150
        $organisation = $this->browser->FindSingleNode("//div[@id = 'brsrSection']//span[@id = 'loginControl_spnBROrganisation']");

        if ($organisation) {
            $this->logger->info("Business account", ['Header' => 3]);
            $date = $this->browser->FindSingleNode("//div[@id = 'brsrSection']//span[@id = 'loginControl_spnPoints']/span", null, true, "/expire on\s*([^\)]+)/");
            $quantity = $this->browser->FindSingleNode("//div[@id = 'brsrSection']//span[@id = 'loginControl_spnPoints']/span", null, true, "/\(([^\(]+)\s+(?:Mile|point)/");
            $this->logger->debug("Exp date: {$date} (" . strtotime($date) . ") / {$quantity}");
            $properties = [
                // Emirates Business Rewards
                'SkywardsNo'       => $this->browser->FindSingleNode("//div[@id = 'brsrSection']//span[contains(@class, 'number')]"),
                // Organisation name
                'OrganisationName' => $organisation,
                // Name
                'Name'             => $this->browser->FindSingleNode("//div[@id = 'brsrSection']//span[@id = 'loginControl_spnBRSRMobMembersName']"),
                // Expiring balance
                'MilesToExpire'    => $quantity,
                // Balance - Points balance
                'Balance'          => $this->browser->FindSingleNode("//div[@id = 'brsrSection']//span[@id = 'loginControl_spnPoints']/text()[1]"),
                // You have saved ... since ...
                //            'Saved'            => $this->parseBusinessPropSaved(),
            ];

            if (strtotime($date) && $quantity > 0) {
                $properties['ExpirationDate'] = strtotime($date);
            }

            $properties = array_merge([
                'Code'        => 'skywardsBusinessRewards' . str_replace(' ', '', $properties['SkywardsNo']),
                'DisplayName' => "Business Rewards",
            ], $properties);

            // add subAccount
            $this->AddSubAccount($properties, true);
        }
    }

    public function Parse()
    {
        $this->closeCookiePopup();

        if ($this->loginViaPartnerLink()) {
            $this->parseViaPartnerLink();

            return;
        }

//        $this->logger->debug("[CurrentURL]: {$this->browser->currentUrl()}");
        // prevent the re-loading of the page
//        if ($this->http->currentUrl() != 'https://www.emirates.com/account/english/manage-account/my-statement/' &&
//            $this->http->currentUrl() != 'https://www.emirates.com/account/english/manage-account/my-statement/index.aspx')
//            $this->browser->GetURL("https://www.emirates.com/account/english/manage-account/my-statement/");

        $this->logger->debug("[CurrentURL]: {$this->browser->currentUrl()}");

        // Emirates (Business Rewards) / Refs #14150
        if (strstr($this->http->currentUrl(), 'business-rewards')) {
            $this->parseBusiness();

            return;
        }

        $this->parseProperties();

        // hard code
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->browser->currentUrl() == 'https://www.emirates.com/account/english/login/login.aspx?refUrl=%2Faccount%2Fenglish%2Fmanage-account%2Fmy-statement%2Findex.aspx%3Fbsp%3Dwww.emirates.com') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->ParseSubaccountMyFamily();

        // "My Account" > "Skywards Skysurfer"
        $this->logger->info('Skywards Skysurfer', ['Header' => 3]);

        try {
            $this->delay();
            $this->browser->GetURL("https://www.emirates.com/account/english/manage-account/skywards-skysurfers.aspx");
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            try {
                $this->driver->executeScript('window.stop();');
            } catch (TimeOutException | ScriptTimeoutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }
        } catch (UnknownServerException | WebDriverCurlException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
        }

        // provider bug fix
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->parseProperties();
        }

        $skysurferMembers = $this->browser->XPath->query("//div[@id = 'MainContent_ctl00_linkedSkysurferMembers']//div[@class = 'sky-surfers-user-box-container']");
        $this->logger->debug("Total {$skysurferMembers->length} skysurfers members were found");

        foreach ($skysurferMembers as $skysurferMember) {
            $name = beautifulName($this->browser->FindSingleNode(".//h3[contains(@class, 'skysurfer-name')]", $skysurferMember));
            // Account Number
            $skywardsNo = $this->browser->FindSingleNode(".//span[contains(@class, 'skywards-num')]", $skysurferMember);
            $subAccount = [
                'Code'        => 'skywardsSkysurfer' . str_replace(' ', '', $skywardsNo),
                'DisplayName' => "Skywards Skysurfer: " . $name . " ({$skywardsNo})",
                'Balance'     => $this->browser->FindSingleNode(".//span[contains(text(), 'Skywards Miles')]/following-sibling::span[contains(@class, 'skywards-miles-earned')]", $skysurferMember),
                // Name
                'Name'        => $name,
                // Account Number
                'SkywardsNo'  => $skywardsNo,
                // Tier
                'CurrentTier' => $this->browser->FindSingleNode(".//span[@id = 'skysurfer-tier']", $skysurferMember),
                // Tier Miles
                'TierMiles'   => $this->browser->FindSingleNode(".//span[contains(text(), 'Tier Miles')]/following-sibling::span[contains(@class, 'skywards-miles-earned')]", $skysurferMember),
            ];
            // Expiration Date
            $exp = $this->browser->FindSingleNode(".//span[contains(text(), 'expire on')]/following-sibling::span[1]", $skysurferMember);
            // ... Skywards Miles are due to expire on ...
            $milesToExpire = $this->browser->FindSingleNode(".//span[contains(text(), 'expire on')]/preceding-sibling::span[1]", $skysurferMember);
            $subAccount['MilesToExpire'] = $milesToExpire;

            if ($milesToExpire && ($exp = strtotime($exp))) {
                $subAccount['ExpirationDate'] = $exp;
            }
            // add subAccount
            $this->AddSubAccount($subAccount, true);
        }// foreach ($skysurferMembers as $skysurferMember)

        // Emirates (Business Rewards) SubAccounts // refs #14150
        if ($this->browser->FindSingleNode("//a[@id = 'loginControl_aBRAccount']")) {
            $this->browser->RetryCount = 0;

            try {
                $this->browser->GetURL('https://www.emirates.com/account/english/business-rewards/');
            } catch (TimeOutException | ScriptTimeoutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
            }
            $this->browser->RetryCount = 2;

            if ($this->browser->Response['code'] == 200 && strstr($this->browser->currentUrl(), 'business-rewards')) {
                $asSubAccount = true;

                if (
                    $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                    && $this->http->FindSingleNode('//h1[contains(text(), "Business Rewards Dashboard")]')) {
                    $asSubAccount = false;
                }
                $this->parseBusiness($asSubAccount);
            }
        }// if ($this->browser->FindSingleNode("//*[@id='loginControl_linkBusinessRewards' and @aria-label != '']"))

        $this->SetProperty("CombineSubAccounts", false);
    }

    // ===============================
    // Itineraries
    // ===============================

    public function ParseItinerariesBusiness()
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $this->delay();
        $this->browser->GetURL('https://www.emirates.com/account/SessionHandler.aspx?pageurl=/MYB.aspx&brtm=Y&j=f&pub=/english&section=MYB');
//        $data = '{"PageNumber":1,"SearchText":"","BookingType":"upcoming","IsPaging":false,"IsFilter":false,"IsSearch":false,"Currency":"none","SkipCount":0}';
//        $header = [
//            "Accept" => "application/json, text/javascript, */*; q=0.01",
//            "Content-Type" => "application/json; charset=utf-8",
//            "X-Requested-With" => "XMLHttpRequest"
//        ];
//        $bookingURL = "/MAB/SME/BRDashboard.aspx/GetPNRSummary";
//        $this->browser->NormalizeURL($bookingURL);
//        $this->browser->PostURL($bookingURL, $data, $header);
//        // provider bug fix
//        if ($this->browser->FindPreg("/\{\"d\":\"\"\}/"))
//            $this->browser->PostURL('https://fly4.emirates.com/MAB/SME/BRDashboard.aspx/GetPNRSummary', $data, $header);
//
//        $response = $this->browser->JsonLog(null, false, true);
//        $bookings = $this->browser->FindPregAll("/onclick=\"fnManageBooking\([^;]+;([^\,]+)&#39;,/", ArrayVal($response, 'd'), PREG_PATTERN_ORDER, true);
        $this->waitForElement(WebDriverBy::xpath("//a[contains(@id, 'btnManage')]"), 5);
        $this->saveResponse();
        $bookings = $this->browser->FindPregAll("/onclick=\"fnManageBooking\(\'([^\']+)/", $this->http->Response['body'], PREG_PATTERN_ORDER, true);
        $this->logger->debug("Total " . count($bookings) . " bookings were found");

        foreach ($bookings as $booking) {
            $this->browser->FilterHTML = false;
            $this->browser->GetURL($booking);
            $this->browser->FilterHTML = true;
            $result[] = $this->ParseBusinessItinerary();
        }// foreach ($bookings as $booking)

        return $result;
    }

    public function ParseItinerariesManage()
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $this->delay();

        try {
            if ($this->loginViaPartnerLink()) {
                try {
                    $this->browser->GetURL('https://www.emirates.com/account/ae/english/partner-login/login.aspx?target=FZ&prscode=FZSE&returnurl=https://www.emirates.com/account/english/manage-account/upcoming-flights/upcoming-flights.aspx');
                    $this->authorization(false);
                } catch (UnknownServerException $e) {
                    $this->logger->error('UnknownServerException - ' . $e->getMessage());

                    return [];
                }

                $this->detectingLogoutLink(5);

                if (
                    $this->loginViaPartnerLink()
                    && $this->http->currentUrl() == 'https://skywards.flydubai.com/en/session'
                ) {
                    $this->http->GetURL('https://www.emirates.com/account/english/manage-account/upcoming-flights/upcoming-flights.aspx');
                    $this->detectingLogoutLink(5);
                }
                $this->saveResponse();
            } else {
                $this->browser->GetURL('https://www.emirates.com/account/english/manage-account/upcoming-flights/upcoming-flights.aspx');
            }
        } catch (WebDriverCurlException $e) {
            $this->increaseTimeLimit();
            $this->logger->error("Exception: " . $e->getMessage());
            $this->browser->GetURL('https://www.emirates.com/account/english/manage-account/upcoming-flights/upcoming-flights.aspx');
        } catch (Exception $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            return [];
        } catch (NoSuchWindowException $e) {
            $this->logger->error('SeleniumDriver::Exception - ' . $e->getMessage());
            $this->browser->GetURL('https://www.emirates.com/account/english/manage-account/upcoming-flights/upcoming-flights.aspx');
        }
        $this->increaseTimeLimit(120);
        $this->waitForElement(WebDriverBy::xpath('//div[@class = "flight-box"] | //div[@class = "flight-box closed"]'), 5);
        $this->increaseTimeLimit(120);
        $this->saveResponse();
        // You have no upcoming trips.
        if ($this->waitForElement(WebDriverBy::xpath("//data[contains(text(), \"You don't have any upcoming travel at the moment.\")]"), 0)) {
            return $this->noItinerariesArr();
        }
        $itineraryNodes = $this->browser->XPath->query('//div[@class = "flight-box"] | //div[@class = "flight-box closed"]');
        $this->logger->debug('Found ' . $itineraryNodes->length . ' itineraries');
        $i = 1;

        foreach ($itineraryNodes as $node) {
            $this->logger->debug("Parsing itinerary #$i");

            if ($res = $this->ParseItinerary($node)) {
                $result[] = $res;
            }
            $i++;
        }

        return $result;
    }

    public function ParseItinerariesFly10()
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $this->delay();

        try {
            $this->increaseTimeLimit();
            $this->browser->GetURL('https://www.emirates.com/SessionHandler.aspx?pageurl=/MYB.aspx&pub=/english&section=MYB&j=f');
            $this->waitForElement(WebDriverBy::xpath("//a[contains(@id, 'btnManage')]"), 5);
            $this->saveResponse();
        } catch (WebDriverCurlException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->increaseTimeLimit();
            $this->browser->GetURL('https://www.emirates.com/SessionHandler.aspx?pageurl=/MYB.aspx&pub=/english&section=MYB&j=f');
            $this->waitForElement(WebDriverBy::xpath("//a[contains(@id, 'btnManage')]"), 5);
            $this->saveResponse();
        } catch (TimeOutException | ScriptTimeoutException $e) {// works
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (NoSuchWindowException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }
        // You have no upcoming trips.
        if ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'You have no upcoming trips.')]"), 0)) {
            return $this->noItinerariesArr();
        }
//        $bookings = $this->browser->FindPregAll("/onclick=\"fnManageBooking\([^;]+;([^\,]+)&#39;,/");
        $bookings = $this->browser->FindPregAll("/onclick=\"fnManageBooking\(\'([^\']+)/", $this->http->Response['body'], PREG_PATTERN_ORDER, true);
        $this->logger->debug("Total " . count($bookings) . " bookings were found");

        foreach ($bookings as $booking) {
            $this->browser->FilterHTML = false;
            $this->browser->GetURL($booking);
            $this->browser->FilterHTML = true;
            $result[] = $this->ParseBusinessItinerary();
        }// foreach ($bookings as $booking)

        return $result;
    }

    public function ParseItineraries()
    {
        if ($this->businessAccount) {
            return $this->ParseItinerariesBusiness();
        }

        if ($this->loginViaPartnerLink()) {
            $result = $this->ParseItinerariesManage();
            $result = uniteAirSegments($result);

            return $result;
        }

        $result = $this->ParseItinerariesFly10();

        if (empty($result) || $result == $this->noItinerariesArr()) {
            $result = $this->ParseItinerariesManage();
            $result = uniteAirSegments($result);
        }// if (empty($result) || $result == $this->noItinerariesArr())

        return $result;
    }

    public function ParseBusinessItinerary()
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => "T"];
        // RecordLocator
        $result['RecordLocator'] = $this->browser->FindSingleNode("//span[@id = 'ctl00_c_ucPnrInfo_lblPnr']");

        if (!$result['RecordLocator']) {
            $result['RecordLocator'] = $this->browser->FindSingleNode("//strong[@data-ek-id = 'ek-booking-no']");
        }
        $this->logger->info('Parse Itinerary #' . $result['RecordLocator'], ['Header' => 3]);
        // Passengers
        $result['Passengers'] = array_map(function ($elem) {
            return beautifulName($elem);
        }, $this->browser->FindNodes("//h3[contains(@class, 'ts-name')]/span"));
        // AccountNumbers
        $result['AccountNumbers'] = $this->browser->FindNodes("//input[contains(@id, 'txtSkyWardsNo')]/@txt-val", null, "/\d+/");

        $segments = $this->browser->XPath->query("//div[@class= 'ts-trip-details']/div");
        $this->logger->debug("Total {$segments->length} legs were found");
        $withStops = false;
        $secContrip = $this->browser->XPath->query("//div[@class= 'ts-trip-details']/div/section[contains(@id, 'secContrip')]");
        $this->logger->debug("Total {$secContrip->length} segments were found with stops");

        if ($secContrip->length > 0) {
            $segments = $this->browser->XPath->query("//div[@class= 'ts-trip-details']/div/section");
            $this->logger->debug("Total {$segments->length} segments were found (include stops)");
            $withStops = true;
        }// if ($secContrip->length > 0)

        for ($i = 0; $i < $segments->length; $i++) {
            $segment = [];
            $seg = $segments->item($i);
            // FlightNumber
            $segment['FlightNumber'] = $this->browser->FindSingleNode(".//span[contains(@id, 'spFlightNo')]", $seg, true, '/^\s*[A-Z]*([\d]+)/');

            // Status
            $segment['Status'] = $this->browser->FindSingleNode(".//strong[@class = 'name' and img[contains(@id, 'imgFlightIcon')]]", $seg);
            // AirlineName
            $segment['AirlineName'] = $this->browser->FindSingleNode(".//strong[@class = 'name' and img[contains(@id, 'imgFlightIcon')]]/img/@alt", $seg, true, "/\s\-\s*(.+)/");
            // Class
            $segment['Cabin'] = trim($this->browser->FindSingleNode(".//strong[contains(@id, 'stClassBrand')]", $seg) . " " . $this->browser->FindSingleNode(".//strong[contains(@id, 'stClassBrand')]/following-sibling::span[@class = 'code']", $seg));
            // Aircraft
            $segment['Aircraft'] = $this->browser->FindSingleNode(".//strong[contains(@id, 'spAircraftType')]", $seg);
            // Duration
            $segment['Duration'] = $this->browser->FindSingleNode(".//span[contains(@id, 'spDuration')]", $seg);
            // DepName, DepCode
            $segment['DepName'] = $segment['DepCode'] = implode('', $this->browser->FindNodes(".//span[contains(@data-ek-id, 'ek-fromairport-code')]/text()", $seg));
            // DepartureTerminal
            $segment['DepartureTerminal'] = $this->browser->FindSingleNode(".//p[contains(@data-ek-id, 'ek-from-terminal')]", $seg);

            $date = $this->browser->FindSingleNode(".//time[@id = 'tmFltTime' or @id = 'ts-review-changes-scroll-target']", $seg, true, "/\,\s*(.+)/");
            $this->logger->debug("Date: {$date}");

            if ($withStops && $i > 0) {
                $this->logger->debug("Correcting Date");

                if ($date2 = $this->browser->FindSingleNode(".//time[@data-ek-id = 'ek-tripdate' and not(@id)]", $seg, true, "/\,\s*(.+)/")) {
                    $date = $date2;
                }
                $this->logger->debug("Date: {$date}");
                $this->logger->debug("Correcting Duration");
                // Duration
                $segment['Duration'] = str_replace('Duration ', '', $this->browser->FindSingleNode(".//time[@data-ek-id = 'ek-flight-duration' and not(@id)]", $seg));
            }// if ($withStops && $i > 0)

            $departTime = $this->browser->FindSingleNode(".//time[contains(@id, 'tDepartTime')]/text()[last()]", $seg);
            $depDate = $date . ' ' . $departTime;
            $this->logger->debug("DepDate: {$depDate} / " . strtotime($depDate));
            $depDate = strtotime($depDate);

            if ($date && $depDate) {
                $segment['DepDate'] = $depDate;
            }
            // ArrName, ArrCode
            $segment['ArrName'] = $segment['ArrCode'] = implode('', $this->browser->FindNodes(".//span[contains(@data-ek-id, 'ek-toairport-code')]/span[2]", $seg));
            // ArrivalTerminal
            $segment['ArrivalTerminal'] = $this->browser->FindSingleNode(".//p[contains(@id, 'Terminal')]", $seg);

            $arrivalTime = $this->browser->FindSingleNode(".//div[contains(@id, 'dvArrivalTime')]", $seg);
            $dayDiff = $this->browser->FindSingleNode(".//sup[contains(@id, 'supDayDiff')]", $seg, true, "/\+(\d+)/");
            $arrDate = $date . ' ' . $arrivalTime;
            $this->logger->debug("ArrDate: {$arrDate} / " . strtotime($arrDate));
            $arrDate = strtotime($arrDate);

            if ($date && $arrDate) {
                if ($dayDiff) {
                    $this->logger->debug("+{$dayDiff} day");
                    $arrDate = strtotime("+ {$dayDiff} day", $arrDate);
                }
                $segment['ArrDate'] = $arrDate;
            }// if ($arrDate)

            if ($withStops && $i == 0) {
                $this->logger->debug("Correcting ArrCode");
                $segment['ArrName'] = $segment['ArrCode'] = implode('', $this->browser->FindNodes(".//span[contains(@data-ek-id, 'ek-fromairport-code')]/text()", $segments->item(1)));

                $this->logger->debug("Correcting ArrDate");
                // ArrDate
                $arrivalTime = $this->browser->FindSingleNode(".//div[contains(@id, 'dvArrivalTime')]/@data-expanded", $seg);
                $dayDiff = $this->browser->FindSingleNode(".//sup[contains(@id, 'supDayDiff')]/@data-expanded", $seg);
                $arrDate = $date . ' ' . $arrivalTime;
                $this->logger->debug("ArrDate: {$arrDate} / " . strtotime($arrivalTime));
                $arrDate = strtotime($arrDate);

                if ($arrDate) {
                    if ($dayDiff) {
                        $this->logger->debug("{$dayDiff}");
                        $arrDate = strtotime("{$dayDiff}", $arrDate);
                    }// if ($dayDiff)
                    $segment['ArrDate'] = $arrDate;
                }// if ($arrDate)
                $this->logger->debug("Correcting Duration");
                // Duration
                $segment['Duration'] = $this->browser->FindSingleNode(".//span[contains(@id, 'spDuration')]/@data-expanded", $seg);
                // ArrivalTerminal
                $segment['ArrivalTerminal'] = $this->browser->FindSingleNode(".//p[contains(@id, 'Terminal')]/@data-expanded", $seg);
            }// if ($withStops && $i == 0)

            // Seats
            $segment['Seats'] = implode(', ', $this->browser->FindNodes("//th[contains(., '{$segment['FlightNumber']}')]/following-sibling::td[contains(@class, 'ts-seats')]/div[contains(@class, 'ts-row-2') and not(contains(@class, 'applicable'))]", null, '/^\s*(\w+)/'));

            if (trim($segment['Seats']) == ',') {
                unset($segment['Seats']);
            }
            // Meals
            $segment['Meal'] = implode(', ', $this->browser->FindNodes("//th[contains(., '{$segment['FlightNumber']}')]/following-sibling::td[contains(@class, 'ts-meals')]/div[contains(@class, 'ts-oneline')]"));

            if (trim($segment['Meal']) == ',') {
                unset($segment['Meal']);
            }

            $result['TripSegments'][] = $segment;
        }// foreach ($segments as $seg)

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    public function ParseItinerary($itineraryNode)
    {
        $this->logger->notice(__METHOD__);
        $itinerary = [];
        $segment = [];

        $itinerary['Kind'] = 'T';

        $xpath = './/div[@class = "review-flight-table-mg"]//text()[contains(normalize-space(.), "Booking Reference")]/ancestor::td[1]';
        $itinerary['RecordLocator'] = $this->browser->FindSingleNode($xpath, $itineraryNode, true, '/Reference\s*(\w{6})/i');

        if (empty($itinerary['RecordLocator'])) {
            $itinerary['RecordLocator'] = $this->browser->FindSingleNode(".//a[contains(@href,'bookref')]/@href",
                $itineraryNode, true, '/bookref[^=]*=(\w{6})&/i');
        }

        $xpath = './/div[@class = "review-flight-table-mg"]//text()[normalize-space(.) = "Flight"]/ancestor::td[1]';
        $flightNumber = $this->browser->FindSingleNode($xpath, $itineraryNode);

        if (preg_match('#(\w{2})(\d+)#i', $flightNumber, $m)) {
            $segment['AirlineName'] = $m[1];
            $segment['FlightNumber'] = $m[2];
        }

        $segment['Duration'] = $duration = $this->browser->FindSingleNode('.//div[@class = "review-flight-table-mg"]//text()[contains(., "Duration")]/ancestor::td[1]', $itineraryNode, true, '#\d+hr\s+\d+min#i');

        $aircraft = $this->browser->FindSingleNode('.//div[@class = "review-flight-table-mg"]//text()[contains(., "Aircraft")]/ancestor::td[1]', $itineraryNode, true, '#Aircraft\s*(.*)#i');

        if (!stristr($aircraft, 'No data available')) {
            $segment['Aircraft'] = $aircraft;
        }

        foreach (['Dep' => ['Depart', 1], 'Arr' => ['Arrive', 2]] as $key => $value) {
            $xpath = './/div[@class = "review-flight-table-mg"]//div[normalize-space(.) = "' . $value[0] . '"]/following-sibling::div[1]';
            $segment[$key . 'Name'] = $this->browser->FindSingleNode($xpath . "/span[2]", $itineraryNode);
            $segment[$key . 'Code'] = $this->browser->FindSingleNode($xpath . "/span[1]", $itineraryNode);

            $xpath = './/div[@class = "review-flight-table-mg"]//div[normalize-space(.) = "' . $value[0] . '"]/ancestor::tr[1]/following-sibling::tr[1]/td[' . $value[1] . ']';
            $dateStr = $this->browser->FindSingleNode($xpath, $itineraryNode);
            $this->logger->debug("Itinerary segment $key date str: $dateStr");

            if (preg_match('#^(\d+:\d+)\s*\w+?\s*(\d+\s+\w+\s+\d+)#i', $dateStr, $m)) {
                // For correct datetime format like "11:10 Saturday 27 February 2016"
                $segment[$key . 'Date'] = strtotime($m[2] . ', ' . $m[1]);
            } elseif (preg_match('#^:\s*\w+?\s*(\d+\s+\w+\s+000\d)#i', $dateStr, $m)) {
                // For broken datetime format like " : Monday 1 January 0001" which sometimes is shown on provider site
                $segment[$key . 'Date'] = MISSING_DATE;
            }
        }

        if ($segment['DepCode'] == 'HDQ' && $this->http->FindPreg('/\d+:\d+ \w+/', false, $segment['DepName'])) {
            $this->logger->error('Skip: HDQ itinerary');

            return [];
        }

        $itinerary['TripSegments'][] = $segment;

        $this->logger->debug('Parsed itinerary segment');
        $this->logger->debug(print_r($itinerary, true));

        return $itinerary;
    }

    // ===============================
    // History
    // ===============================

    public function GetHistoryColumns()
    {
        return [
            "Date"           => "PostingDate",
            /*
            "Partner"        => "Info",
            */
            "Transaction"    => "Description",
            "Skywards Miles" => "Miles",
            "Tier miles"     => "Info",
            "Bonus Miles"    => "Bonus", // refs #4843
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $result = [];
        $startTimer = microtime(true);
        $this->increaseTimeLimit();

        if ($this->loginViaPartnerLink()) {
            $headers = [
                "Accept"           => "application/json, text/javascript, */*; q=0.01",
                "X-Requested-With" => "XMLHttpRequest",
                "Referer"          => "https://skywards.flydubai.com/en/miles/transactions",
            ];

            try {
                try {
                    $this->browser->GetURL("https://skywards.flydubai.com/en/");
                } catch (Facebook\WebDriver\Exception\ScriptTimeoutException | ScriptTimeoutException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                    $this->driver->executeScript('window.stop();');
                }
                $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Welcome back,')]"), 5);
                $this->saveResponse();

                if ($error = $this->http->FindSingleNode("//span[contains(text(), 'This page isn’t working')]")) {
                    $this->logger->error("[Error]: {$error}");

                    return [];
                }

                // load jq
                $this->driver->executeScript("
                    var jq = document.createElement('script');
                    jq.src = \"https://ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.js\";
                    document.getElementsByTagName('head')[0].appendChild(jq); 
                ");
                sleep(1);
            } catch (
                UnknownServerException
                | WebDriverException
                | UnknownServerException
                | WebDriverCurlException
                | Facebook\WebDriver\Exception\WebDriverCurlException
                | Facebook\WebDriver\Exception\UnknownServerException
                | Facebook\WebDriver\Exception\WebDriverException
                $e
            ) {
                $this->logger->error('Exception - ' . $e->getMessage());

                return [];
            } catch (Facebook\WebDriver\Exception\ScriptTimeoutException | ScriptTimeoutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
            }

            try {
//            $this->browser->GetURL("https://skywards.flydubai.com/en/comp/Activity/ListingSkyward?block=100000&offset=0&mode=desktop&_=".time(), $headers);
                $this->driver->executeScript("
                $.ajax({
                    url: \"https://skywards.flydubai.com/en/comp/Activity/ListingSkyward?block=100000&offset=0&mode=desktop&_=" . date("UB") . "\",
                    async: false,
                    dataType: 'json',
                    success: function (data) {
                        console.log(\"---------------- success data ----------------\");
                        console.log(JSON.stringify(data));
                        localStorage.setItem('response', JSON.stringify(data));
                        console.log(\"---------------- success data ----------------\");
                    },
                    error: function (data) {
                        data = $(data);
                        console.log(\"---------------- fail data ----------------\");
                        console.log(JSON.stringify(data));
                        console.log(\"---------------- fail data ----------------\");
                        localStorage.setItem('response', JSON.stringify(data));
                    }
                });
            ");
            } catch (UnknownServerException | WebDriverException | UnknownServerException | WebDriverCurlException $e) {
                $this->logger->error('Exception - ' . $e->getMessage());

                return [];
            } catch (Facebook\WebDriver\Exception\ScriptTimeoutException | ScriptTimeoutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
            }

            sleep(2);
            $response = $this->driver->executeScript("return localStorage.getItem('response');");
//            $this->logger->info("[Form response]: " . $response);
            $response = $this->browser->JsonLog($response, 0);

            if (isset($response->DesktopView)) {
                $this->http->SetBody($response->DesktopView, true);
            }

            $startIndex = sizeof($result);
            // refs #19074
            $result = $this->ParsePageFlydubaiHistory($startIndex, $startDate);

            return $result;
        }

        try {
            $headers = [
                "Accept"           => "application/json, text/javascript, */*; q=0.01",
                "X-Requested-With" => "XMLHttpRequest",
                "Referer"          => "https://www.emirates.com/account/english/manage-account/my-statement/",
            ];
            // AccountID: 2160646
            if (in_array($this->AccountFields['Login'], [
                '107230395',
                '162996002',
                'oliver.ruschhaupt@gmail.com',
                '00115310974',
                '105111646',
            ])
            ) {
                $this->browser->GetURL("https://www.emirates.com/account/english/manage-account/my-statement/?mode=JSON&dateRange=twelve_months", $headers);
            } else {
                try {
                    $this->browser->GetURL("https://www.emirates.com/account/english/manage-account/my-statement/?mode=JSON&dateRange=all", $headers);
                } catch (WebDriverCurlException | UnknownServerException $e) {
                    $this->increaseTimeLimit();
                    $this->logger->error("Exception: " . $e->getMessage());
                    $this->browser->GetURL("https://www.emirates.com/account/english/manage-account/my-statement/?mode=JSON&dateRange=twelve_months", $headers);
                } catch (TimeOutException | ScriptTimeoutException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                    $this->driver->executeScript('window.stop();');
                }
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());
        }

        $response = json_decode($this->browser->Response['body']);
        $this->browser->Log("json: <pre>" . var_export($response, true) . "</pre>", false);

        if (isset($response->rows)) {
            $startIndex = sizeof($result);
            $result = $this->ParsePageHistory($startIndex, $startDate, $response->rows);
        }

        $this->logger->debug("[Time parsing: " . (microtime(true) - $startTimer) . "]");

        return $result;
    }

    public function ParsePageFlydubaiHistory($startIndex, $startDate)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $rows = $this->http->XPath->query('//tr[contains(@class, "activity-item")]');
        $this->logger->debug("Total history {$rows->length} rows were found");

        foreach ($rows as $row) {
            $dateStr = $this->http->FindSingleNode('td[2]', $row);
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Transaction'] = $this->http->FindSingleNode('td[1]', $row);
            $totalSkywards = $this->http->FindSingleNode('td[3]/span/span[1]', $row);
            $ticker = 'Skywards Miles';

            if ($this->http->FindPreg("/Bonus/ims", false, $result[$startIndex]['Transaction'])) {
                $ticker = 'Bonus Miles';
            }
            $result[$startIndex][$ticker] = $totalSkywards;
            $result[$startIndex]['Tier miles'] = $this->http->FindSingleNode('td[4]/span/span[1]', $row);
            $startIndex++;
        }

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate, $rows)
    {
        $result = [];

        foreach ($rows as $row) {
            if (isset($row->date) && strtotime($row->date)) {
                $dateStr = $row->date;
                $postDate = strtotime($dateStr);
            }

            if (isset($startDate, $postDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }

            if (!isset($postDate)) {
                $postDate = '';
            }

            $result[$startIndex]['Date'] = $postDate;

            if (isset($row->partner)) {
                $result[$startIndex]['Partner'] = $row->partner;
            }

            if (isset($row->transaction)) {
                $result[$startIndex]['Transaction'] = $row->transaction;
            }

            if (!empty($row->transactionTypecode)) {
                $result[$startIndex]['Transaction'] = $row->transactionTypecode . " ({$result[$startIndex]['Transaction']})";
            }

            if (isset($row->transaction, $row->totalSkywards) && $this->http->FindPreg("/Bonus/ims", false, $result[$startIndex]['Transaction'])) {
                $result[$startIndex]['Bonus Miles'] = $row->totalSkywards;
            } elseif (isset($row->totalSkywards)) {
                $result[$startIndex]['Skywards Miles'] = $row->totalSkywards;
            }

            if (isset($row->totalTier)) {
                $result[$startIndex]['Tier miles'] = $row->totalTier;
            }
            // ----------------------------------- Details ------------------------------------ #
            /*if (!empty($row->innerRows))
                foreach ($row->innerRows as $innerRows ) {
                    $startIndex++;
                    $result[$startIndex]['Date'] = $postDate;
                    if (isset($innerRows->partner))
                        $result[$startIndex]['Partner'] = $innerRows->partner;
                    if (isset($innerRows->transaction))
                        $result[$startIndex]['Transaction'] = $innerRows->transaction;
                    if (!empty($innerRows->transactionTypecode))
                        $result[$startIndex]['Transaction'] = $innerRows->transactionTypecode." ({$result[$startIndex]['Transaction']})";
                    if (isset($innerRows->totalSkywards, $innerRows->transaction) && preg_match("/Bonus/ims", $result[$startIndex]['Transaction']))
                        $result[$startIndex]['Bonus Miles'] = $innerRows->totalSkywards;
                    elseif (isset($innerRows->totalSkywards))
                        $result[$startIndex]['Skywards Miles'] = $innerRows->totalSkywards;
                    if (isset($innerRows->totalTier))
                        $result[$startIndex]['Tier miles'] = $innerRows->totalTier;
                }*/
            $startIndex++;
        }

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    private function ParseSubaccountMyFamily()
    {
        $this->logger->notice(__METHOD__);
        // refs #16645, #23694
        $this->logger->info('My Family', ['Header' => 3]);
        $loginPage = $this->waitForElement(WebDriverBy::xpath('//a[@id= "login-nav-link"]'), 0);

        if (!$loginPage) {
            return;
        }
        $loginPage->click();
        sleep(3);
        $this->saveResponse();
        $myFamilyStr = $this->http->FindSingleNode("//span[@id='loginControl_spnGroupMiles']");
        $this->logger->debug("My Family: $myFamilyStr");
        // 28,350 (5,250 Skywards Miles to expire on 30 Jun 2026)
        if (preg_match('/^([\d.,]+) \(([\d.,]+) Skywards Miles to expire on (.+?)\)/', $myFamilyStr, $m)) {
            $this->AddSubAccount([
                'Code'        => 'skywardsMyFamily',
                'DisplayName' => "My Family",
                'Balance'     => $m[1],
                // Account Number
                //                'SkywardsNo' => $this->http->FindSingleNode("//div[@id = 'familyGroupSection']//span[contains(@class, 'number')]"),
                // Skywards Miles Expiring
                'MilesToExpire'  => $m[2],
                'ExpirationDate' => strtotime($m[3]),
            ]);
        } else {
            $this->logger->debug("My Family Not Found");
        }
    }

    // https://redmine.awardwallet.com/issues/19074?#note-25
    private function loginViaPartnerLink(): bool
    {
        $this->logger->notice(__METHOD__);
        // business accounts
        if (in_array($this->AccountFields['Login'], [
            'danilov.uu@gmail.com',
            'guillaume@eyfari.com',
            'l.altieri@gfvr.ch',
            'executivepoints@gmail.com',
            'briangeller@gmail.com',
            'razr7@yahoo.com', // not business, not working via partner site
            'cox.lana@yahoo.com', // not business, not working via partner site
            'edgemonteducation@gmail.com',
            '108889141',
            'luigi@altieri.one',
            'aydinbrcu@gmail.com',
            'EK676492670',
            '661 902 474',
            '676 772 202',
            'emirates@finnegan.fr',
            'stephen.w.ho@gmail.com',
        ])
            || (isset($this->State['isBusiness']) && $this->State['isBusiness'] === true)
            || $this->attempt == 2
            || $this->parseMainSite === true
        ) {
            return false;
        }

        return true;
    }

    private function isExperimental(): bool
    {
        $this->logger->notice(__METHOD__);

        return true;
        //return in_array($this->AccountFields['UserID'] ?? null, [7, 2110, 61266]);
    }

    private function selectExperimentalSettings()
    {
        $this->logger->notice(__METHOD__);

        $chromium = false;

        if ($this->attempt > 1) {
            $chromium = true;
        }

        if ($chromium === true) {
            $this->useFirefox(SeleniumFinderRequest::FIREFOX_59);
            $this->setKeepProfile(true);
        } else {
            $chromium = true;

            if ($this->loginViaPartnerLink() === true) {
//                $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
                $this->setProxyGoProxies();
//                $this->seleniumOptions->addHideSeleniumExtension = false;
//                $this->seleniumOptions->userAgent = null;

                $this->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
                $request = FingerprintRequest::chrome();
                $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                if ($fingerprint !== null) {
                    $this->logger->info("selected fingerprint {$fingerprint->getId()}, {{$fingerprint->getBrowserFamily()}}:{{$fingerprint->getBrowserVersion()}}, {{$fingerprint->getPlatform()}}, {$fingerprint->getUseragent()}");
                    $this->State['Fingerprint'] = $fingerprint->getFingerprint();
                    $this->State['UserAgent'] = $fingerprint->getUseragent();
                    $this->State['Resolution'] = [$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()];
                }

                if (isset($this->State['Fingerprint'])) {
                    $this->logger->debug("set fingerprint");
                    $this->seleniumOptions->fingerprint = $this->State['Fingerprint'];
                }

                if (isset($this->State['UserAgent'])) {
                    $this->logger->debug("set userAgent");
                    $this->http->setUserAgent($this->State['UserAgent']);
                }

                if (isset($this->State['Resolution'])) {
                    $this->logger->debug("set resolution");
                    $this->seleniumOptions->setResolution($this->State['Resolution']);
                }

                $this->usePacFile(false);

                return;
            }
        }

        if ($this->loginViaPartnerLink() === false) {
            $this->useFirefox();
            $this->setKeepProfile(true);
            $chromium = false;

            $this->usePacFile(false);

            unset($this->State['Fingerprint']);
            unset($this->State['UserAgent']);
            unset($this->State['Resolution']);
        }

        if (in_array($this->AccountFields['Login'], [//todo: timeout on first page
            '570267180',
            'guillaume@eyfari.com',
        ])
        ) {
            $this->setProxyGoProxies();

            return;
        }

        $ipLogic = 5;

        if (($this->State['ip-selection-logic'] ?? 0) !== $ipLogic) {
            unset($this->State['illuminati-ip']);
        }
        $this->State['ip-selection-logic'] = $ipLogic;
        $this->setProxyBrightData(null, "static", 'us' /*function (array $ipInfo) {
            return
                $ipInfo['country'] === 'us'
                && strpos($ipInfo['org'], 'kvchosting') !== false
                && $ipInfo['region'] !== 'oklahoma'
                && !$this->isProxyInvalid($ipInfo['ip'])
            ;
        }*/ /*, true*/);

        if (
            !isset($this->State['Fingerprint'])
            || $this->attempt > 0
            || ($chromium === true && !isset($this->State['UserAgent']))
            || ($chromium === true && stristr($this->State['UserAgent'], 'Gecko'))
        ) {
            $this->logger->notice("set new Fingerprint");

            if ($chromium === true) {
                $request = FingerprintRequest::chrome();
            } else {
                $request = FingerprintRequest::firefox();
            }

            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            $fp = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fp !== null) {
                $this->logger->info("selected fingerprint {$fp->getId()}, {{$fp->getBrowserFamily()}}:{{$fp->getBrowserVersion()}}, {{$fp->getPlatform()}}, {$fp->getUseragent()}");
                $this->State['Fingerprint'] = $fp->getFingerprint();
                $this->State['UserAgent'] = $fp->getUseragent();
                $this->State['Resolution'] = [$fp->getScreenWidth(), $fp->getScreenHeight()];
            }
        }

        if (isset($this->State['Fingerprint'])) {
            $this->logger->debug("set fingerprint");
            $this->seleniumOptions->fingerprint = $this->State['Fingerprint'];
        }
    }

    private function hideOverlay()
    {
        $this->logger->notice(__METHOD__);
        $this->driver->executeScript('var overlay = document.getElementById(\'onetrust-consent-sdk\'); if (overlay) overlay.style.display = "none";');
    }

    private function authorization($throwErrors = true)
    {
        $this->logger->notice(__METHOD__);
        $delay = 0;

        if ($this->loginViaPartnerLink()) {
            $delay = 5;
        }
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "sso-email" or @id = "txtMembershipNo"]'), $delay);

        if (!$loginInput) {
            $this->hideOverlay();
            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "sso-email" or @id = "txtMembershipNo"]'), 10);
        }
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "sso-password" or @id = "reauth-password" or @id = "txtPassword"]'), 0);
//        $captchaCodeTextBox = $this->waitForElement(WebDriverBy::id('CaptchaCodeTextBox'), 0);
        $this->saveResponse();

        if (!$loginInput && $this->http->FindPreg("#window.location.href = 'https://www.emirates.com/account/english/manage-account/skywards-skysurfers.aspx';#")) {
            $this->http->GetURL('https://www.emirates.com/account/english/manage-account/skywards-skysurfers.aspx');
        }

        try {
            $mover = new MouseMover($this->driver);
        } catch (NoSuchElementException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        $mover->logger = $this->logger;
//        $mover->enableCursor();

        if (!$loginInput && $passwordInput) {
            $this->saveResponse();

            if (!$this->isExperimental()) {
                $mover->moveToElement($passwordInput);
                $mover->click();
            }
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 10);

            $btnLogIn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "reauth-login-btn"]'), 0);
            $this->saveResponse();

            if (!$btnLogIn) {
                $this->logger->error('something went wrong');

                if ($throwErrors === false) {
                    return false;
                }

                return $this->checkErrors();
            }

            $btnLogIn->click();

            return true;
        }

        $this->hideOverlay();
        $btnLogIn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "login-button"] | //input[@id = "btnLogin_LoginWidget"]'), 0);

        if (!$loginInput || !$passwordInput || !$btnLogIn) {
            $this->logger->error('something went wrong');

            // retries
            $this->checkBadProxy();

            if ($throwErrors === false) {
                return false;
            }

            return $this->checkErrors();
        }// if (!$loginInput || !$passwordInput)
        // refs #14450
        $mover->duration = 100000;
        $mover->steps = 50;

        if (!$this->isExperimental()) {
            $mover->moveToElement($loginInput);
            $mover->click();
        }
        $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);
        /*
        $loginInput->clear();
        $loginInput->sendKeys($this->AccountFields['Login']);
        $this->delay();
        */
        /*
        $passwordInput->clear();
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        */
        if (!$this->isExperimental()) {
            $mover->moveToElement($passwordInput);
            $mover->click();
        }
        $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 10);

        $this->driver->executeScript("var remember = document.getElementById('chkRememberMe'); if (remember) remember.checked = true;");

        if ($this->loginViaPartnerLink()) {
            $this->closeCookiePopup();
        }

        /*
        // Remember me on this computer
//        if ($chkRememberMe = $this->waitForElement(WebDriverBy::xpath("//label[@for = 'chkRememberMe']"), 0))
//            $chkRememberMe->click();

        // TODO:
        try {
            $captchaVersion = $this->waitForElement(WebDriverBy::id('c_english_login_login_maincontent_ctl00_botdetectcaptcha_CaptchaImage'), 10);
            $captcha = $captchaVersion ? $this->parseCaptchaNew() : $this->parseCaptchaOld();
            if ($captchaVersion)
                $captchaCodeTextBox->click();
        } catch (UnexpectedJavascriptException $e) {
            // Audio Captcha
            $this->DebugInfo = 'Captcha not load';
            $this->logger->debug($e->getMessage());
            throw new CheckRetryNeededException(3, 1);
        }

        if ($captcha === false) {
            $this->logger->error('Failed to parse captcha');
            return false;
        }

        if (isset($captchaVersion)) {
            $captchaCodeTextBox->sendKeys($captcha);
        }
        else {
            $img = $this->waitForElement(WebDriverBy::id('visualCaptcha-img-' . ($captcha - 1)), 5);
            if (!$img)
                return false;
            $this->driver->executeScript('setTimeout(function(){
                delete document.$cdc_asdjflasutopfhvcZLmcfl_;
                delete document.$cdc_asdjflasutopfhvcZLawlt_;
                $(\'img[id = "visualCaptcha-img-' . ($captcha - 1) . '"]\').click();
            }, 500)');
            sleep(2);
            $img->click();
        }
        // refs #12848
//      $mouse = $this->driver->getMouse();
//      $mouse->click($img->getCoordinates());
        */
        usleep(rand(400000, 1300000));
        $this->hideOverlay();
        $btnLogIn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "login-button" or @id = "reauth-login-btn"] | //input[@id = "btnLogin_LoginWidget"]'), 0);
        $this->saveResponse();

        if (!$btnLogIn) {
            $this->logger->error('something went wrong');

            if ($throwErrors === false) {
                return false;
            }

            return $this->checkErrors();
        }

        if ($this->selenium) {
            $this->logger->debug('click');
            $btnLogIn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "login-button" or @id = "reauth-login-btn"] | //input[@id = "btnLogin_LoginWidget"]'), 0);

            if (!$btnLogIn) {
                return false;
            }

            $btnLogIn->click();
        } else {
//            $this->driver->executeScript("
//                    $('#btnLogin_LoginWidget').click();window.stop();
//                ");
            $formInputs = [];

            $this->logger->debug('wait results');

            if ($this->waitForElement(WebDriverBy::xpath('//form[@id = "aspnetForm"]//input'), 5, false)) {
                foreach ($this->driver->findElements(WebDriverBy::xpath('//form[@id = "aspnetForm"]//input', 0, false)) as $index => $xKey) {
                    $formInputs[] = [
                        'name'  => $xKey->getAttribute("name"),
                        'value' => $xKey->getAttribute("value"),
                    ];
                }
//                $this->logger->debug(var_export($formInputs, true), ["pre" => true]);
                $this->browser->Form = [];

                foreach ($formInputs as $input) {
                    $this->browser->Form[$input['name']] = $input['value'];
                }
                $this->browser->FormURL = "https://www.emirates.com/account/english/login/login.aspx?mode=ssl";
                $this->browser->PostForm(["Content-Type" => "application/x-www-form-urlencoded", "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"]);

                $this->http->SetBody($this->browser->Response['body']);
            } else {
                return false;
            }
        }
        sleep(5);

        return true;
    }

    private function closeCookiePopup()
    {
        $this->logger->notice(__METHOD__);

        $btnCookies = $this->waitForElement(WebDriverBy::xpath('//button[@id = "onetrust-accept-btn-handler"]'), 0);

        if ($btnCookies) {
            $btnCookies->click();
            sleep(1);
            $this->saveResponse();
        }
    }

    private function checkBadProxy()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("
                    //h1[contains(text(), 'This site can’t be reached')]
                    | //span[contains(text(), 'This site can’t be reached')]
                    | //h1[contains(text(), 'Access Denied')]
                    | //span[contains(text(), 'Your connection was interrupted')]
                    | //body[contains(text(), 'Bad Request')]
                ")
            || $this->http->FindSingleNode("
                    //p[contains(text(), 'Health check')]
                    | //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                    | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                    | //title[contains(text(), 'http://localhost:4448/start?text={$this->AccountFields['ProviderCode']}+%7C+{$this->AccountFields['Login']}+%7C+')]
                ")
            || $this->http->FindPreg('/page isn’t working/ims')
            || $this->waitForElement(WebDriverBy::xpath("
                //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                | //title[contains(text(), 'http://localhost:4448/start?text={$this->AccountFields['ProviderCode']}+%7C+{$this->AccountFields['Login']}+%7C+')]
                | //p[contains(text(), 'Health check')]
                | //span[contains(text(), 'Your connection was interrupted')]
                | //body[contains(text(), 'Bad Request')]
            "), 0)
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }
    }

    private function parseProperties()
    {
        $this->logger->notice(__METHOD__);
        // Balance - Skywards Miles
        $this->SetBalance($this->browser->FindSingleNode("(//div[@class = 'membershipSkywardsMiles']/div[@class= 'milesCount'])[1]", null, true, '/([\d\.\,]+)/ims'));
        // Account Number
        $this->SetProperty("SkywardsNo", $this->browser->FindSingleNode("(//div[@class = 'userWelcome']/div[@class = 'membershipNumber'])[1]", null, true, '/([\w\s]+)/ims'));
        // Name
        $this->SetProperty("Name", $this->browser->FindSingleNode("(//div[@class = 'userWelcome']/div[@class = 'membershipName']/text())[1]"));
        // Tier
        $this->SetProperty("CurrentTier", $this->browser->FindSingleNode("//span[@id = 'loginControl_spnMemberTier']"));
        // refs #12080
        if (isset($this->Properties['CurrentTier']) && $this->Properties['CurrentTier'] == 'SKYWARDS') {
            $this->ArchiveLogs = true;
            $this->sendNotification("skywards. Showing wrong status - SKYWARDS, refs #12080");
        }
        // Tier Miles
        $this->SetProperty("TierMiles", $this->browser->FindSingleNode("//span[contains(@id, '_lblSkywardsTierMiles')]", null, true, '/([\d\.\,]+)/ims'));
        // Skywards Miles Expiring
        $date = $this->browser->FindSingleNode("//div[@id = 'skywardSection']//div[contains(@class, 'desktop')]/span[contains(text(), 'expire on')]", null, true, "/expire\s*on\s*([^<]+)/");
        $quantity = $this->browser->FindSingleNode("//div[@id = 'skywardSection']//div[contains(@class, 'desktop')]/span[contains(text(), 'expire on')]", null, true, "/([\d\.\,\s]+)\s+mile/ims");
        $this->logger->debug("Date: {$date} (" . strtotime($date) . ") / {$quantity}");

        if (strtotime($date)) {
            $this->SetExpirationDate(strtotime($date));
            // Miles to Expire
            $this->SetProperty("MilesToExpire", $quantity);
        }// if (strtotime($date))
        /*## Skywards Miles Expiring
        $nodes = $this->browser->XPath->query("//tr[th[contains(text(), 'Expiry Date')]]/following-sibling::tr");
        $this->logger->debug("Total {$nodes->length} exp date nodes were found");
        for ($i = 0; $i < $nodes->length; $i++) {
            $date = $this->browser->FindSingleNode("td[1]/span[contains(@class, 'hidden-control')]", $nodes->item($i));
            $quantity = $this->browser->FindSingleNode("td[2]", $nodes->item($i));
            $this->logger->debug("Date: {$date} (".strtotime($date).") / {$quantity}");
            if (strtotime($date)) {
                $this->SetExpirationDate(strtotime($date));
                // Miles to Expire
                $this->SetProperty("MilesToExpire", $quantity);
                break;
            }// if (strtotime($date))
        }// for ($i = 0; $i < $nodes->length; $i++)*/
    }

    private function parseBusiness($subAccount = false)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Business account [subAccount: {$subAccount}]", ['Header' => 3]);

        $date = $this->browser->FindSingleNode("//input[@id = 'hdnExpireDate']/@value");
        $quantity = $this->browser->FindSingleNode("//input[@id = 'hdnExpirePoint']/@value");
        $this->logger->debug("Exp date: {$date} (" . strtotime($date) . ") / {$quantity}");
        $properties = [
            // Emirates Business Rewards
            'SkywardsNo' => $this->browser->FindSingleNode("//span[@class = 'profile-links__code']"),
            // Organisation name
            'OrganisationName' => $this->browser->FindSingleNode("//a[@class='company']"),
            // Name
            'Name' => $this->browser->FindSingleNode("//div[@class = 'name-container']/div[@class = 'name']"),
            // Expiring balance
            'MilesToExpire' => $quantity,
            // Balance - Points balance
            'Balance' => $this->browser->FindPreg("/'pointsBalanceBRAccount'\s*:\s*'([^\']+)/"),
            // You have saved ... since ...
            'Saved' => $this->parseBusinessPropSaved(),
        ];

        if (strtotime($date) && $quantity > 0) {
            $properties['ExpirationDate'] = strtotime($date);
        }

        $this->delay();
        $this->browser->GetURL("https://www.emirates.com/account/english/business-rewards/account.aspx");
        // Organisation name
        $properties["OrganisationName"] = $this->browser->FindSingleNode("//a[@class='company']");
        // Trade licence number
        $properties["TradeLicenceNumber"] = $this->browser->FindSingleNode("//div[contains(text(), 'Trade licence')]/following-sibling::div[1]");

        if ($subAccount) {
            $subAccount = array_merge([
                'Code'        => 'skywardsBusinessRewards' . $properties['SkywardsNo'],
                'DisplayName' => "Business Rewards",
            ], $properties);
            $this->AddSubAccount($subAccount, true);
        }// if ($subAccount)
        else {
            $this->businessAccount = true;

            foreach ($properties as $key => $value) {
                if ($key == 'ExpirationDate') {
                    $this->SetExpirationDate($value);
                } elseif ($key == 'Balance') {
                    $this->SetBalance($value);
                } else {
                    $this->SetProperty($key, $value);
                }
            }// foreach ($properties as $key => $value)
        }
    }

    private function parseBusinessPropSaved()
    {
        $this->logger->notice(__METHOD__);

        if ($saved = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'You have saved')]/strong"), 5)) {
            return $saved->getText();
        } else {
            return false;
        }

//        $since = $this->browser->FindSingleNode("//input[@id = 'hdnOrgJoindate']/@value");
        $brActivityFromDate = $this->browser->FindSingleNode("//input[@id = 'hdnOrgjoingdate']/@value");
        $businessRewardsNumber = $this->browser->FindSingleNode("//input[@id = 'hdnBRSRno']/@value");
        $hdnjwtToken = $this->browser->FindSingleNode("//input[@id = 'hdnjwtToken']/@value");

        if ($brActivityFromDate && $businessRewardsNumber/* && $since*/) {
            $data = [
                "businessRewardsNumber" => $businessRewardsNumber,
                "pageNumber"            => 1,
                'brActivityFromDate'    => str_replace(' ', '', $brActivityFromDate),
                'brActivityToDate'      => date('dMY'),
            ];
            $headers = [
                "Accept"        => "application/json, text/plain, */*",
                "Content-Type"  => "application/json;charset=utf-8",
                "Authorization" => "BRSR {$hdnjwtToken}",
            ];
            $this->browser->PostURL("https://www.emirates.com/api/brsr/dashboard/activitiessummary/bractivitiesrequest", json_encode($data), $headers);
            $response = $this->browser->JsonLog();
            // You have saved ... since ...
            if (isset($response->totalCashSaved->currencyCode, $response->totalCashSaved->value)) {
                return $response->totalCashSaved->currencyCode . " " . number_format($response->totalCashSaved->value, 2)/*." since ".$since*/;
            } else {
                $this->logger->notice("totalCashSaved not found");
            }
        }

        return false;
    }
}
