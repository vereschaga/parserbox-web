<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerKellogg extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $postAuthParams = [];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->http->saveScreenshots = true;
        $this->KeepState = true;
//        $this->useFirefox();
//        $this->setKeepProfile(true);
        $this->useChromium();
        /*
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
        */
        //$this->disableImages();
        $this->usePacFile(false);

        // we do not have astra on wsdl yet
        /*
        if (defined('ASTRA_API_KEY')) {
            // astra, residential, us
            curlRequest('http://node-ru-16.astroproxy.com:11241/api/changeIP?apiToken=' . ASTRA_API_KEY);
            $this->http->SetProxy("lpm.awardwallet.com:24003");
        }
        else {
        /*
        */
//        $this->setProxyBrightData(null, "us_residential", "us"/*, true*/);
//        $this->http->SetProxy($this->proxyReCaptchaIt7());
//        $this->http->setUserAgent(null);
        /*
        }
        */

//        $this->http->setRandomUserAgent(5, true, false, true, true, false);
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL("https://www.kelloggsfamilyrewards.com/en_US/your-account/dashboard.html");
        } catch (WebDriverCurlException | NoSuchWindowException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }

        if ($this->waitForElement(WebDriverBy::xpath("//p[starts-with(text(), 'Your account number:')]"), 3)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->removeCookies();

            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

            if (!strstr($this->http->currentUrl(), 'login.html')) {
                try {
                    $this->http->GetURL("https://www.kelloggsfamilyrewards.com/en_US/login.html");
                } catch (TimeOutException $e) {
                    $this->logger->error("TimeOutException exception: " . $e->getMessage());
                    $this->driver->executeScript('window.stop();');
                    $this->saveResponse();

                    if ($this->http->FindSingleNode("//h1[contains(text(), 'The proxy server is refusing connections')]")) {
                        $this->markProxyAsInvalid();

                        throw new CheckRetryNeededException(3, 0);
                    }
                } catch (NoSuchWindowException | NoSuchDriverException $e) {
                    $this->logger->error("NoSuchWindowException: " . $e->getMessage());

                    throw new CheckRetryNeededException(3, 0);
                }
            }

            if (!$this->waitForElement(WebDriverBy::xpath('
                    //button[@class="btn_responsive"]
                    | //input[@id = "submit_btn"]
                    | //input[contains(@class, "loginbtn")]
                    | //input[contains(@value, "Sign in")]
                    | //h1[contains(text(), "Pardon Our Interruption..")]
                '), 15)
            ) {
                // provider bug fix
                try {
                    $this->http->GetURL("https://www.kelloggsfamilyrewards.com/en_US/login.html");
                } catch (NoSuchWindowException | NoSuchDriverException $e) {
                    $this->logger->error("NoSuchWindowException: " . $e->getMessage());

                    throw new CheckRetryNeededException(3, 0);
                }
            }

            if ($this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Pardon Our Interruption..")]'), 0)) {
                if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                    return false;
                }

                throw new CheckRetryNeededException(3, 10);
            }
        } catch (WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException exception: " . $e->getMessage());
            // retries
            if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG && strstr($e->getMessage(), 'Curl error thrown for http POST to /session')) {
                throw new CheckRetryNeededException(3, 10);
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage());
        }

        $buttonInput = $this->waitForElement(WebDriverBy::xpath('
            //button[@class="btn_responsive"]
            | //input[@id = "submit_btn"]
            | //input[contains(@class, "loginbtn")]
            | //input[contains(@value, "Sign in")]
        '), 15);

        if (!$buttonInput) {
            $buttonInput = $this->waitForElement(WebDriverBy::xpath('//input[contains(@class, "loginbtn")]'), 0, false);
        }
        $this->saveResponse();
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name="UserName"] | //input[@name = "email"]'), 0);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name="Password"] | //input[@name = "pwd"]'), 0);

        $newAuth = false;

        if (!$loginInput) {
            $newAuth = true;
            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 0, false);
            $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0, false);
        }

        if (!$loginInput || !$passwordInput || !$buttonInput) {
            $this->logger->error('something went wrong');
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::xpath("//a[text()='Logout']"), 0)) {
                return true;
            }

            if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG && $this->waitForElement(WebDriverBy::xpath('//div[@login-form]//div[@ng-show="loading"]'), 0)) {
                $this->sendStatistic(false);

                throw new CheckRetryNeededException(3, 7);
            }

            return $this->checkErrors();
        }

        if ($newAuth == true) {
            $this->driver->executeScript("$('input[name = \"username\"]').val('" . $this->AccountFields["Login"] . "')");
            $this->driver->executeScript("$('input[name = \"password\"]').val('" . $this->AccountFields["Pass"] . "')");
            $this->driver->executeScript("$('input.loginbtn, input.gigya-input-submit').click()");

            return true;
        }

        $loginInput->click();
        usleep(rand(100000, 500000));
        $loginInput->clear();
        usleep(rand(200000, 500000));
        $loginInput->sendKeys($this->AccountFields['Login']);

        $this->logger->debug('move to Password field');
        $passwordInput->click();
        usleep(rand(100000, 500000));
        $passwordInput->clear();
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        usleep(rand(100000, 500000));
        $this->logger->debug('move to Button');
        sleep(rand(1, 5));

        if ($this->waitForElement(WebDriverBy::xpath('//div[@id = "KSTL-Registration-recaptcha-Login"]'), 0)) {
            $this->saveResponse();
            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                throw new CheckRetryNeededException(3, 3);

                return false;
            }
            $this->logger->debug('setting captcha: ' . $captcha);
//            $this->driver->executeScript('___grecaptcha_cfg.clients[0].cl.J.callback("'.$captcha.'");');
            $this->driver->executeScript('
                var findCb = (object) => {
                    if (!!object["callback"] && !!object["sitekey"]) {
                        return object["callback"]
                    } else {
                        for (let key in object) {
                            if (typeof object[key] == "object") {
                                return findCb(object[key])
                            } else {
                                return null
                            }
                        }
                    }
                }
                findCb(___grecaptcha_cfg.clients[0])("' . $captcha . '")
            ');
            sleep(1);
        }
        $buttonInput->click();

        return true;
    }

    public function sendStatistic($success = false)
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("kellogg login attempt", [
            "success"        => !$success,
            "proxy"          => "luminati:" . $this->http->getProxyAddress(),
            "browser"        => $this->seleniumRequest->getBrowser() . ":" . $this->seleniumRequest->getVersion(),
            "userAgentStr"   => $this->http->userAgent,
            //            "resolution"     => $this->seleniumOptions->resolution[0] . "x" . $this->seleniumOptions->resolution[1],
            "attempt"        => $this->attempt,
            "isWindows"      => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }

    public function getTokens()
    {
        $this->logger->notice(__METHOD__);
        // from js
        $moduleKey = $this->http->FindPreg("/moduleKey: '([^\']+)/");

        if (!isset($moduleKey)) {
            $this->logger->error("moduleKey not found");

            return null;
        }
        // get Token
        $referer = "https://registration.kglobalservices.com/Proxy?ModuleKey={$moduleKey}";
        $this->http->GetURL($referer);
        $token = $this->http->FindPreg("/var token = '([^\']+)/");

        if (!isset($token)) {
            $this->logger->error("token not found");

            return null;
        }

        return ["ModuleKey" => $moduleKey, "Token" => $token, "Referer" => $referer];
    }

    public function Login()
    {
        $sleep = 40;
        sleep(5);
        $startTime = time();

        try {
            while ((time() - $startTime) < $sleep) {
                $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
                // success
                if ($this->waitForElement(WebDriverBy::xpath("//a[text()='Logout']"), 0)) {
                    $this->sendStatistic(true);
                    $this->captchaReporting($this->recognizer);

                    return true;
                }

                if ($this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "PIN Verification")] | //div[contains(text(), "A verification code has been sent to:")]'), 0)) {
                    $this->sendStatistic(true);

                    return $this->processTwoFactor();
                }
                // Oops! It looks like you entered the wrong email and/or password. Please try again.
                // Your email is expired. Please Contact Us
                if ($message = $this->waitForElement(WebDriverBy::xpath("
                    //div[contains(normalize-space(),'Oops! It looks like you entered the wrong email and/or password. Please try again.') and contains(@class,'gigya-error-msg-active')]
                    | //span[contains(text(), 'Your email is expired.')]
                    | //span[contains(text(), 'Please provide a valid email address.')]
                    | //p[contains(text(), 'WARNING! Your account will be locked after one more incorrect email and/or password entry.')]
                    | //div[contains(text(), \"It seems like you're trying to log in with a password that was changed. If you don't remember the new one, reset your password.\")]
                "), 0)) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
                }
                // We’re sorry, but your account has been locked because your email and/or password was entered incorrectly too many times. We’ve emailed you instructions for unlocking it.
                if ($message = $this->waitForElement(WebDriverBy::xpath("
                    //h2[text() = 'Account Locked']
                    | //p[contains(text(), 'We’re sorry, but your account has been locked because your email and/or password was entered incorrectly too many times.')]
                "), 0)
            ) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
                }
                // New Terms & Conditions
                if ($this->waitForElement(WebDriverBy::xpath("//label[contains(normalize-space(.),'I agree to') and a[text()='Terms & Conditions']]"), 0)) {
                    $this->captchaReporting($this->recognizer);
                    $this->throwAcceptTermsMessageException();
                }

                if ($this->waitForElement(WebDriverBy::xpath("
                    //h2[contains(text(), 'Update Your Password')]
                    | //h2[contains(text(), 'Password Reset Required')]
                    | //h2[contains(text(), 'Your Email is Out of Date')]
                "), 0)
            ) {
                    $this->captchaReporting($this->recognizer);
                    $this->throwProfileUpdateMessageException();
                }

                // Your Email is Out of Date
                $remindLater = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Remind me later')]"), 0);

                if ($remindLater && $this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Your Email is Out of Date')]"), 0)) {
                    $remindLater->click();
                    sleep(2);

                    if ($this->waitForElement(WebDriverBy::xpath("//a[text()='Logout']"), 0)) {
                        $this->captchaReporting($this->recognizer);
                        $this->sendStatistic(true);

                        return true;
                    }
                }

                $this->saveResponse();
            }// while ((time() - $startTime) < $sleep)
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException exception: " . $e->getMessage());

            try {
                $this->logger->debug($this->driver->switchTo()->alert()->getText());
                $message = $this->driver->switchTo()->alert()->getText();

                if (strstr($message, 'Login is currently unavailable. Please try again later.')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                $this->driver->switchTo()->alert()->accept();
                $this->logger->notice("alert, accept");
            } catch (NoAlertOpenException | UnexpectedAlertOpenException $e) {
                $this->logger->error("Login -> exception: " . $e->getMessage());

                throw new CheckException("Login is currently unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
            } finally {
                $this->logger->debug("Login -> finally");
                $this->saveResponse();
            }
        }

        // we apologize,but brands is currently unavailable.You may continue to log in,browse the catalog and redeem points.Our team is currently working to resolve the issue,please check back later.Thank you for your patience
        // Sorry, please try again.
        if ($message = $this->waitForElement(WebDriverBy::xpath("
                //a[contains(text(), 'we apologize,but brands is currently unavailable.')]
                | //p[contains(text(), 'Sorry, please try again.')]
                | //a[contains(text(), 'We apologise, but Login and Registration are undergoing maintenance and are currently unavailable.')]
            "), 0)
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->waitForElement(WebDriverBy::xpath('
                (//span[@class = "ai-circled ai-indicator ai-grey-spin"])[2]/@class | //button[@class = "btn_responsive processing"]/@class
                | //label[input[@id = "submit_btn"]]/i[contains(@class, "fa-spinner") and @style="display: block;"]
            '), 0)
            || $this->http->FindSingleNode('//button[@class=\'btn_responsive processing\']/@class | //label[input[@id = "submit_btn"]]/i[contains(@class, "fa-spinner") and @style="display: block;"]')
        ) {
            if ($this->AccountFields['Login'] == 'kurtklein62@gmail.com') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->sendStatistic(false);

            $this->saveResponse();

            if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                return false;
            }

            throw new CheckRetryNeededException(3, 3);
        }

        if ($this->waitForElement(WebDriverBy::xpath("//a[text()='Logout']"), 0)) {
            $this->sendStatistic(true);
            $this->captchaReporting($this->recognizer);

            return true;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        switch ($step) {
            case "processTwoFactor":
                return $this->processTwoFactor();

                break;
        }

        return false;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Internal Server Error
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Unresolved compilation problems:')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('
                //a[contains(text(), "Our website is currently undergoing scheduled maintenance.")]
                | //a[contains(text(), "Our website is currently undergoing scheduled maintenance beginning ")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Sorry, please try again.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // maintenance
        if ($this->http->FindPreg("/^File not found\.\"$/")) {
            throw new CheckException("We apologize, but Login and Registration are currently unavailable. You may continue to browse the rewards catalog, recipes, and promotions. Our team is currently working to resolve the issue, please check back later. Thank you for your patience!", ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if (
            isset($this->http->Response['code']) && $this->http->Response['code'] == 416
            || $this->http->FindPreg("/^<head><\/head><body><\/body>$/")
        ) {
            throw new CheckRetryNeededException(3, 10);
        }

        return false;
    }

    public function Parse()
    {
        try {
            $this->http->GetURL("https://www.kelloggsfamilyrewards.com/en_US/rewards.html");
            $this->saveResponse();
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";

            $this->http->GetURL("https://www.kelloggsfamilyrewards.com/en_US/rewards.html");
            $this->saveResponse();
        }
        // Balance - Your sweeps entries
        $points = $this->waitForElement(WebDriverBy::xpath("//span[@id = 'current-month-sweeps-entries']"), 10);
        $this->saveResponse();
        // Balance - Your sweeps entries
        $points = $this->waitForElement(WebDriverBy::xpath("//span[@id = 'current-month-sweeps-entries']"), 10);
        $this->saveResponse();

        if ($points) {
            $this->SetBalance($this->http->FindPreg(self::BALANCE_REGEXP_EXTENDED, false, $points->getText()));
        }
        // Drawing on
        $this->SetProperty('DrawingOn', $this->http->FindSingleNode('//p[contains(text(), "Drawing on")]', null, true, '/Drawing on (.+)/'));

        $this->http->GetURL("https://www.kelloggsfamilyrewards.com/en_US/my-account/profile.html");
        $name = $this->waitForElement(WebDriverBy::xpath('//label[@for = "KSTL-Registration-FirstName"]/following-sibling::span'), 10);
        $this->saveResponse();

        if ($name) {
            $this->SetProperty('Name', beautifulName($name->getText() . " " . $this->waitForElement(WebDriverBy::xpath('//label[@for = "KSTL-Registration-LastName"]/following-sibling::span'), 0)->getText()));
        }
        // MemberSince - Member since: 2015
        if ($memberSince = $this->waitForElement(WebDriverBy::xpath("//p[contains(., 'Member since')]"), 0)) {
            $this->SetProperty('MemberSince', $this->http->FindPreg('/:\s*(.+)/', false, $memberSince->getText()));
        }
        // Number - Account number
        if ($number = $this->waitForElement(WebDriverBy::xpath("//p[contains(., 'Account number')]"), 0)) {
            $this->SetProperty('Number', $this->http->FindPreg('/:\s*(\d+)/', false, $number->getText()));
        }
        $this->saveResponse();

        // provider bug fix
        if ($this->ErrorCode !== ACCOUNT_ENGINE_ERROR) {
            return;
        }

        try {
            $this->http->GetURL("https://www.kelloggsfamilyrewards.com/en_US/rewards.html");
            $this->saveResponse();
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";

            $this->http->GetURL("https://www.kelloggsfamilyrewards.com/en_US/rewards.html");
            $this->saveResponse();
        }
        // Balance - Your sweeps entries
        $points = $this->waitForElement(WebDriverBy::xpath("//span[@id = 'current-month-sweeps-entries']"), 10);
        $this->saveResponse();

        if ($points) {
            $this->SetBalance($this->http->FindPreg(self::BALANCE_REGEXP_EXTENDED, false, $points->getText()));
        }
        // Drawing on
        $this->SetProperty('DrawingOn', $this->http->FindSingleNode('//p[contains(text(), "Drawing on")]', null, true, '/Drawing on (.+)/'));
    }

    protected function distil()
    {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        $distilLink = $this->http->FindPreg("/meta http-equiv=\"refresh\" content=\"\d+;\s*url=([^\"]+)/ims");

        if (isset($distilLink)) {
            sleep(2);
            $this->http->NormalizeURL($distilLink);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($distilLink);
            $this->http->RetryCount = 2;
        }// if (isset($distil))

        if (!$this->http->ParseForm('distilCaptchaForm')) {
            return false;
        }
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;
        $captcha = $this->parseCaptcha($form);

        if ($captcha === false) {
            return false;
        }
        $this->http->FormURL = $formURL;
        $this->http->Form = $form;
        $this->http->SetInputValue('recaptcha_response_field', str_replace(' ', '', $captcha));
        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->FilterHTML = true;

        return true;
    }

    protected function parseCaptcha(&$form)
    {
        $this->logger->notice(__METHOD__);
        $script = $this->http->FindSingleNode("//iframe[contains(@src, 'https://www.google.com/recaptcha/api/noscript?k')]/@src");

        if (!$script) {
            return false;
        }
        $this->http->GetURL($script);
        $recaptcha_challenge_field = $this->http->FindSingleNode("//input[@name = 'recaptcha_challenge_field']/@value");
        $captcha = $this->http->FindSingleNode("//img[contains(@src, 'image?c=')]/@src");

        if (!$captcha || !$recaptcha_challenge_field) {
            return false;
        }
        $form['recaptcha_challenge_field'] = $recaptcha_challenge_field;
        $file = $this->http->DownloadFile("https://www.google.com/recaptcha/api/" . $captcha, "png");
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($recognizer, $file);
        unlink($file);

        return $captcha;
    }

    protected function processTwoFactor()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $this->saveResponse();
        $questionLabel = $this->waitForElement(WebDriverBy::xpath('
            //p[contains(text(), "We\'ve emailed you a PIN to verify your account. Please enter it below.")]
            | //div[contains(text(), "A verification code has been sent to:")]
        '), 0);
        $codeInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'KSTL-Registration-Pin' or contains(@class, 'gig-tfa-code-textbox')]"), 0);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[contains(@class, 'btn_responsive')] | //div[contains(text(), 'Submit')]"), 0);

        if (!$codeInput || !$questionLabel || !$button) {
            $this->logger->error("something went wrong");

            return false;
        }

        $this->saveResponse();
        $this->driver->executeScript("document.querySelector('input.gig-tfa-code-remember-checkbox').checked = true;");

        $question = $questionLabel->getText();

        if (strstr($question, 'A verification code has been sent to')) {
            $question .= " " . $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "A verification code has been sent to:")]/following-sibling::div[1]'), 0)->getText();
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "processTwoFactor");

            return false;
        }// if (!isset($this->Answers[$question]))

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);
        $codeInput->clear();
        $codeInput->sendKeys($answer);
        $this->logger->debug("click 'Sign In'");
        $button->click();

        sleep(5);

        $result = $this->waitForElement(WebDriverBy::xpath('
            //p[contains(text(), "Your account has been verified. You will be redirected to your destination in a moment.")]
            | //h1[@class = "points"]
            | //div[@class = "gig-tfa-error"]
            | //span[contains(text(), "I agree to Kellogg\'s Family Rewards")]
            | //a[contains(text(), "Browse rewards")]
        '), 20);
        $this->saveResponse();

        $error = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'gig-tfa-error']"), 0);
        unset($this->Answers[$question]);

        if (!empty($error)) {
            $error = $error->getText();
            $this->logger->notice("error: " . $error);
            $this->holdSession();
            $this->AskQuestion($question, $error, "processTwoFactor");

            return false;
        }// if (!empty($error))
        elseif ($this->http->FindSingleNode('//span[contains(text(), "I agree to Kellogg\'s Family Rewards")]')) {
            $this->throwAcceptTermsMessageException();
        } elseif (!$result) {
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, there was a problem. Please reload the page and try again.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->waitForElement(WebDriverBy::xpath('//p[@ng-bind-html = "error.Description" and @class = "ng-binding" and normalize-space(text()) = "[object Object]"]'), 0)) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if (!empty($error))

        $this->saveResponse();

        return true;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//div[@id = "KSTL-Registration-recaptcha-Login"]//iframe/@src', null, true, "/k=([^\&]+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    /*function LoadLoginFormOld() {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.kelloggsfamilyrewards.com/en_US/login.html");
        sleep(1);
        $this->distil();
        $tokens = $this->getTokens();
        if (empty($tokens))
            return $this->checkErrors();
        // get form fields
        sleep(2);
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
        $this->http->setDefaultHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
        $this->http->setDefaultHeader("KReg-Proxied-Origin", "https://www.kelloggsfamilyrewards.com");
        $this->http->setDefaultHeader("Accept-Language", "en-US,en;q=0.5");
        $this->http->setDefaultHeader("X-Distil-Ajax", "tvdqervaaxrwfazrqvtwfrrwq");
        $this->http->PostURL("https://registration.kglobalservices.com/Login/Get", array(
            "ModuleKey" => $tokens["ModuleKey"],
            "Token" => $tokens["Token"],
        ));
        $response = $this->http->JsonLog(null, false);
        if (!isset($response->Token, $response->Payload->CaptchaSignature))
            return $this->checkErrors();

        // posting login form
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://registration.kglobalservices.com/Login/Submit", array(
            "LoginPurpose"      => "general",
            "CaptchaSignature"  => isset($response->Payload->CaptchaSignature) ? $response->Payload->CaptchaSignature : '',
            "CaptchaLanguage"   => isset($response->Payload->CaptchaLanguage) ? $response->Payload->CaptchaLanguage : 'en',
            "ShouldShowCaptcha" => "false",
            "ReferringSource"   => "",
            "UserName"          => $this->AccountFields['Login'],
            "Password"          => $this->AccountFields['Pass'],
            "email_h"           => "",
            "ModuleKey"         => $tokens["ModuleKey"],
            "Token"             => isset($response->Token) ? $response->Token : '',
        ), array("Referer" => $tokens["Referer"]));
        $this->http->RetryCount = 2;

        return true;
    }

     function LoginOld() {
        $response = $this->http->JsonLog();
        // Update Your Password
        if (isset($response->Payload->ModuleNextAction) && $response->Payload->ModuleNextAction == "/UpdatePassword/Get")
            throw new CheckException("Kellogg (Family Rewards) is asking you to update your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        // Update email address
        if (isset($response->Payload->ModuleNextAction) && $response->Payload->ModuleNextAction == "/InvalidEmail/GetNotice")
            throw new CheckException("Kellogg (Family Rewards) is asking you to update your email address, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);

        if (isset($response->Payload->ModuleNextAction, $response->Payload->PostAuthParams->AccessToken)
            && $response->Payload->ModuleNextAction == "/NextURL") {
            // set Cookies
            $this->postAuthParams = array(
                'AccessToken'            => $response->Payload->PostAuthParams->AccessToken,
                'RefreshToken'           => $response->Payload->PostAuthParams->RefreshToken,
                'ProfileID'              => $response->Payload->PostAuthParams->ProfileID,
                'FirstName'              => $response->Payload->PostAuthParams->FirstName,
                'LastName'               => (isset($response->Payload->PostAuthParams->LastName)) ? $response->Payload->PostAuthParams->LastName: null,
                'UsernameForServiceAuth' => $response->Payload->PostAuthParams->UsernameForServiceAuth,
            );
            $this->http->setCookie("profileID", $this->postAuthParams['ProfileID'], ".kelloggsfamilyrewards.com");
            $this->http->setCookie("profileId", $this->postAuthParams['ProfileID'], ".kelloggsfamilyrewards.com");
            $this->http->setCookie("firstname", $this->postAuthParams['FirstName'], ".kelloggsfamilyrewards.com");
            $this->http->setCookie("currentUserToken", "true", ".kelloggsfamilyrewards.com");
            $this->http->setCookie("accessToken", $this->postAuthParams['AccessToken'], ".kelloggsfamilyrewards.com");
            $this->http->setCookie("refreshToken", $this->postAuthParams['RefreshToken'], ".kelloggsfamilyrewards.com");
            $this->http->setCookie("usernameForServiceAuth", $this->postAuthParams['UsernameForServiceAuth'], ".kelloggsfamilyrewards.com");

            return true;
        }
        // When you sign up for Kellogg's Family Rewards®...
        if (isset($response->Payload->ModuleNextAction, $response->Payload->PartialAuthParams->AccessToken)
            && $response->Payload->ModuleNextAction == '/Profile/Get')
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        // Oops! It looks like you entered the wrong email and/or password. Please try again.
        if (isset($response->Errors[0]->Code, $response->Errors[0]->Description) && $response->Errors[0]->Code == '2200')
            throw new CheckException($response->Errors[0]->Description, ACCOUNT_INVALID_PASSWORD);
        // Sorry, please try again.
        if (isset($response->Errors[0]->Code, $response->Errors[0]->Description) && in_array($response->Errors[0]->Code, array('609', '603')))
            throw new CheckException($response->Errors[0]->Description, ACCOUNT_PROVIDER_ERROR);
        // Account Locked
        if (isset($response->Payload->ModuleNextAction) && $response->Payload->ModuleNextAction == '/AccountLocked/Get')
            throw new CheckException("We’re sorry, but your account has been locked because your email and/or password was entered incorrectly too many times. We’ve emailed you instructions for unlocking it.", ACCOUNT_LOCKOUT);
        // maintenance
        if (isset($response->Errors[0]->Code, $response->Errors[0]->Description) && $response->Errors[0]->Code == '0'
            && $response->Errors[0]->Description == "We're sorry, there was a problem. Please reload the page and try again.")
            throw new CheckException("Alert Image Our website is currently undergoing scheduled maintenance. During this time Registration, Log In, Receipt Submission, Code Entry, catalog redemption and other associated tools will be unavailable. We apologize for any inconvenience and appreciate your participation in our program.", ACCOUNT_PROVIDER_ERROR);

        return $this->checkErrors();
    }

    function ParseOld() {
        // Name
        //$this->SetProperty('Name', beautifulName($this->postAuthParams['FirstName']." ".$this->postAuthParams['LastName']));

        $headers = [
            "Accept"           => "application/json, text/javascript, *
    /*; q=0.01",
            "X-Requested-With" => "XMLHttpRequest",
            "Referer"          => "https://app.kelloggsfamilyrewards.com/proxy.html"
        ];
        // Get Balance info
        $this->http->PostURL("https://app.kelloggsfamilyrewards.com/Registration/LogIn", array(
            "locale"       => "en_US",
            "source"       => "desktop",
            "site"         => "KFR",
            "accessToken"  => $this->postAuthParams['AccessToken'],
            "username"     => $this->AccountFields['Login'],
            "refreshToken" => $this->postAuthParams['RefreshToken'],
            "profileId"    => $this->postAuthParams['ProfileID'],
        ), $headers);
        $response = $this->http->JsonLog();
        if (isset($response->currencyPoints))
            foreach ($response->currencyPoints as $currencyPoints) {
                if (isset($currencyPoints->name) && $currencyPoints->name == 'Base') {
                    // Balance - Points Available
                    $this->SetBalance($currencyPoints->balance);
                    break;
                }// if (isset($currencyPoints->name) && $currencyPoints->name == 'Base')
            }// foreach ($response->currencyPoints as $currencyPoints)

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Back-end system seems to be down at the moment.
            if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Back-end system seems to be down at the moment.')]"))
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            // Your e-mail was not found.
            if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Your e-mail was not found.')]"))
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

            if (!empty($this->Properties['Name']))
                throw new CheckRetryNeededException(3, 7, self::PROVIDER_ERROR_MSG);
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // get Card info
        $this->http->PostURL("https://app.kelloggsfamilyrewards.com/Loyalty/GetCards", array(
            "locale"       => "en_US",
            "source"       => "desktop",
            "site"         => "KFR",
        ), $headers);
        $response = $this->http->JsonLog();
        if (isset($response->cards))
            foreach ($response->cards as $card) {
                if (isset($card->status) && $card->status == 'VALID') {
                    // Your Current Card and Memberships
                    $this->SetProperty("Number", $card->value);
                    break;
                }// if (isset($card->status) && $card->status == 'VALID')
            }// foreach ($response->cards as $card)
    }*/
}
