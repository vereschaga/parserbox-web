<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSaudisrabianairlinSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();

        /*
        $this->http->SetProxy($this->proxyUK());
        */

        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);

        // It breaks everything
        $this->usePacFile(false);
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (empty($this->AccountFields['Login2'])) {
            throw new CheckException("To update this Saudi Arabian Airlines (Alfursan) account you need to fill in the 'Country / Region' field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR); /*review*/
        }

        if (!is_numeric($this->AccountFields['Login'])) {
            throw new CheckException('Invalid ALFURSAN ID or Password', ACCOUNT_INVALID_PASSWORD);
        }

        $this->driver->manage()->window()->maximize();

        try {
            $retry = false;
            $this->http->GetURL("https://alfursan.saudia.com/en");
//            sleep(5);
            $loginBtn = $this->waitForElement(WebDriverBy::xpath('//button[@data-test="login-btn"]'));
            $this->saveResponse();

            if (!$loginBtn) {
                if ($this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")) {
                    throw new CheckRetryNeededException(3, 0);
                }

                return $this->checkErrors();
            }

            // waiting for loading icon to disappear
            if ($this->waitForElement(WebDriverBy::xpath('//div[@id = "cdk-overlay-1"]'), 0)) {
                $this->logger->notice("loader still visible, waiting");
                $this->waitFor(function () {
                    try {
                        return !$this->waitForElement(WebDriverBy::xpath('//div[@id = "cdk-overlay-1"]'), 0);
                    } catch (NoSuchElementException $e) {
                        $this->logger->error("NoSuchElementException: " . $e->getMessage());

                        return true;
                    }
                }, 20);
                $this->saveResponse();
            }

            $loginBtn->click();

            $login = $this->waitForElement(WebDriverBy::xpath("//form[@id = 'gigya-login-form']//input[@name = 'username']"), 10);

            if (!$login) {
                return $this->checkErrors();
            }
            $this->driver->executeScript("document.getElementById('gigya-screen-dialog-page-overlay').style.display = 'none';");

            $mouse = $this->driver->getMouse();
            $mouse->mouseMove($login->getCoordinates());
            $mouse->click();

            $login->click();
            $this->logger->debug("set login");
            $login->sendKeys($this->AccountFields['Login']);
            $this->saveResponse();
            $this->driver->executeScript('document.querySelector(\'input[id="gigya-checkbox-remember"]\').checked = true;');
            // remove overlay
            $this->driver->executeScript('document.querySelector(\'#gigya-screen-dialog-page-overlay\').style = \'display:none\';');
            $pass = $this->waitForElement(WebDriverBy::xpath("//form[@id = 'gigya-login-form']//input[@name = 'password']"), 0);

            if (!$pass) {
                return $this->checkErrors();
            }

            $mouse->mouseMove($pass->getCoordinates());
            $mouse->click();
            $this->logger->debug("set pass");
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->saveResponse();

            // captcha
            $captcha = $this->parseRecaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->driver->executeScript('document.getElementsByName("g-recaptcha-response").value = "' . $captcha . '";');

            $this->saveResponse();

            $this->logger->notice("Executing captcha callback");

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

            $this->saveResponse();

            $loginButton = $this->waitForElement(WebDriverBy::xpath('//form[@id = "gigya-login-form"]//input[@value="log in "]'), 0);

            if (!$loginButton) {
                $this->logger->error('Failed to find login button');

                return false;
            }

            $mouse->mouseMove($loginButton->getCoordinates());
            $mouse->click();
//            $this->driver->executeScript('submitForm()');
//            $loginButton->click();
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";
            // retries
            if (strstr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (TimeOutException $e)
        catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        } finally {
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($this->http->FindPreg('/pear error\: Malformed response/ims')) {
            throw new CheckException("Site is temporarily down. Please try to access it later.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('(//p[contains(text(), "Starting from December 3 until December 5, 2021 our system will be under renovation,")])[1]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Maintenance
        if ($this->http->FindPreg("/(UNDER\s*<br>\s*MAINTENANCE!)/ims")
            || $this->http->FindPreg("/background-image\: url\(\.\/images\/Maintenance\.jpg\)\;/ims")) {
            throw new CheckException("Alfursan Website is currently under maintenance! Sorry for any inconveniences!", ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached') or contains(text(), 'Secure Connection Failed')]") || $this->http->FindPreg('/page isn’t working/ims')) {
            throw new CheckRetryNeededException(3);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //div[contains(text(), "Loyalty Signup - Additional Info")]
            | //div[contains(@class, "gigya-error-msg")]
            | //div[contains(text(), "Securing Your Account")]
            | //div[contains(text(), "Verifying Your Account")]
        '), 10);
        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Verifying Your Account")]'), 0)) {
            return $this->askOTP();
        }

        if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Securing Your Account")]'), 0)) {
            return $this->processOTP();
        }

        // Login is successful
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Loyalty Signup - Additional Info")]')) {
            $this->throwAcceptTermsMessageException();
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "gigya-error-msg") and contains(@class, "active")]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Invalid login or password'
                || $message == 'Invalid ALFURSAN ID or Password'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'There are errors in your form, please try again') {
                $this->DebugInfo = "something went wrong";
                sleep(60);
                $this->saveResponse();
                $this->driver->executeScript('submitForm()');
                $this->saveResponse();
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        if ($step == "AskPhone") {
            return $this->processOTP();
        }

        if ($step == "Question") {
            return $this->enteringOTP();
        }

        return false;
    }

    public function Parse()
    {
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $this->waitForElement(WebDriverBy::xpath('//span[@class = "balance-points"]'), 10);
        $this->saveResponse();
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[@class="name"]')));
        // ALFURSAN Number
        $this->SetProperty("Number", $this->http->FindSingleNode('//div[@class="points-container"]//span'));
        // Counters / Flights
        $flights = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "progress-tracker-item-name") and (normalize-space() = "Counters" or normalize-space() = "Flights")]/following-sibling::mat-card-content[1]//div[@class = "progress-tracker-item-text-value"]'), 0);
        $this->SetProperty("Counters", $flights
            ? $this->http->FindPreg("/([^\/]+)/", false, $flights->getText())
            : $this->http->FindSingleNode('//div[contains(@class, "progress-tracker-item-name") and (normalize-space() = "Counters" or normalize-space() = "Flights")]/following-sibling::mat-card-content[1]//div[@class = "progress-tracker-item-text-value"]', null, true, "/([^\/]+)/")
        );
        // Tier Miles / Tier Credits
        $tierCredits = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "progress-tracker-item-name") and (normalize-space() = "Tier Miles" or normalize-space() = "Tier Credits")]/following-sibling::mat-card-content[1]//div[@class = "progress-tracker-item-text-value"]'), 0);
        $this->SetProperty("TierMiles", $tierCredits
            ? $this->http->FindPreg("/([^\/]+)/", false, $tierCredits->getText())
            : $this->http->FindSingleNode('//div[contains(@class, "progress-tracker-item-name") and (normalize-space() = "Tier Miles" or normalize-space() = "Tier Credits")]/following-sibling::mat-card-content[1]//div[@class = "progress-tracker-item-text-value"]', null, true, "/([^\/]+)/")
        );
        // Status points
        $this->SetProperty("MemberType", $this->http->FindSingleNode('//span[@class="status-name"]'));
        // Balance - Reward Miles Balance
        $this->SetBalance($this->http->FindSingleNode('//span[@class = "balance-points"]'));

        // Family Program Miles
        $this->http->GetURL("https://alfursan.saudia.com/en/household");
        $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'hh-points-amount')]/span[1] | //div[contains(text(), 'There are no Members in your Family Program')]"), 10);
        $this->saveResponse();
        $this->SetProperty("FamilyBalance", $this->http->FindSingleNode("//div[contains(@class, 'hh-points-amount')]/span[1]"));

        // Get expiration date information
        if ($this->Balance <= 0) {
            return;
        }

        try {
            $this->http->GetURL("https://alfursan.saudia.com/en/points");

            $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'balance-description') and (contains(., 'Expiring') or contains(., 'will not expire this month with our new expiry policy'))]"), 10);
            $this->saveResponse();
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            sleep(2);
            $this->sendNotification("UnexpectedJavascriptException // RR");
            $this->saveResponse();
        }
        $expirationDate = $this->http->FindSingleNode('//div[contains(@class, "balance-description") and contains(., "Expiring")]/text()[last()]', null, true, "/Expiring (.+)/");
        $expirationPoints = $this->http->FindSingleNode('//div[contains(@class, "balance-description") and contains(., "Expiring")]/span[1]', null, false);

        if ($expirationPoints > 0) {
            $this->sendNotification("exp date was found // RR");
        }

        if (isset($expirationDate) && isset($expirationPoints)) {
            $expirationDate = strtotime($expirationDate);
            $expirationPoints = preg_replace('/([^\d.,]*)/ims', '$2', $expirationPoints);

            if (isset($expirationDate) && $expirationDate !== false) {
                $this->SetExpirationDate($expirationDate);
                $this->SetProperty('PointsToExpire', $expirationPoints);
            }// if (isset($expirationDate) && $expirationDate !== false)
        } elseif ($message = $this->http->FindPreg("/(There are no miles that expire)/ims")) {
            $this->logger->notice(">>>>>> " . $message);
        }
    }

    protected function processOTP()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $q = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Phone number:')]"), 15);
        $input = $this->waitForElement(WebDriverBy::xpath("//input[contains(@class, 'gig-tfa-phone-number')]"), 0);
        $loginButton = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Get the Code')]"), 0);
        $this->saveResponse();

        if (!$q || !$input || !$loginButton) {
            $this->logger->error("something went wrong");

            return false;
        }

        $this->driver->executeScript("
            document.querySelector(\"select.gig-tfa-phone-register-select\").selectedIndex = document.evaluate(\"//option[contains(., '{$this->AccountFields['Login2']}')]\", document, null, XPathResult.ANY_TYPE, null ).iterateNext().index;
            
            function createNewEvent(eventName) {
                var event;
                if (typeof(Event) === \"function\") {
                    event = new Event(eventName);
                } else {
                    event = document.createEvent(\"Event\");
                    event.initEvent(eventName, true, true);
                }
                return event;
            };
            
            document.querySelector(\"select.gig-tfa-phone-register-select\").dispatchEvent(createNewEvent('change'));
        ");
        $this->saveResponse();

        $question = "Please enter your phone number";

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "AskPhone");

            return false;
        }// if (!isset($this->Answers[$question]))

        $input->sendKeys($this->Answers[$question]);
        $this->logger->debug("click 'Continue'");
        $loginButton->click();
        $this->logger->debug("wait verification code...");

        sleep(5);
        $error = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'An error has occurred, please try again later')]"), 0);

        if ($error && strstr($error->getText(), 'An error has occurred, please try again later')) {
            $this->holdSession();
            $input->clear();
            $this->AskQuestion($question, $error->getText() . ". Please enter your phone number without country code", "AskPhone");
            $this->saveResponse();

            return false;
        }

        return $this->askOTP();
    }

    protected function askOTP()
    {
        $this->logger->notice(__METHOD__);
        $q = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'A verification code has been sent to your phone number:')] | //li[@data-device-expanded='true']//div[contains(text(), 'The Code has been sent to your selected device!')]"), 0);

        if ($choose = $this->waitForElement(WebDriverBy::xpath('//div[@data-tfa-method="email"]//button[contains(@class, "gigya-tfa-verification-action-btn")]'), 0)) {
            $choose->click();
            $q = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'A verification code has been sent to your phone number:')] | //li[@data-device-expanded='true']//div[contains(text(), 'The Code has been sent to your selected device!')]"), 10);
            $this->saveResponse();
        }

        $phone = $this->waitForElement(WebDriverBy::xpath("//span[contains(@class, 'gig-tfa-phone-code-phonenumber')] | //li[@data-device-expanded='true']//div[contains(@class, 'gigya-tfa-verification-device-label')]"), 0);
        $this->saveResponse();

        if (!$q || !$phone) {
            $this->logger->error("something went wrong");

            return false;
        }

        $question = $q->getText() . " " . $phone->getText();
        $this->holdSession();
        $this->AskQuestion($question, null, "Question");

        return false;
    }

    protected function enteringOTP()
    {
        $this->logger->notice(__METHOD__);
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $input = $this->waitForElement(WebDriverBy::xpath("//input[contains(@class, 'gig-tfa-code-textbox')] | //li[contains(@class, 'gigya-container-enabled')]//input[contains(@class, 'gigya-code-input')]"), 10);
        $submitBtn = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Submit')] | //li[contains(@class, 'gigya-container-enabled')]//input[@value='Continue']"), 0);
        $this->saveResponse();
        $this->driver->executeScript('
            try {
                $(\'input.gig-tfa-code-remember-checkbox, .gigya-input-checkbox\').prop(\'checked\', \'checked\');
            } catch (e) {}
        ');

        if (!$input || !$submitBtn) {
            return false;
        }

        $input->clear();
        $input->sendKeys($answer);
        $submitBtn->click();

        // Invalid code entry !! Please try again.
        $error = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'gig-tfa-error')]"), 5);
        $this->logger->debug("[Current URL]: " . $this->http->currentUrl());
        $this->saveResponse();

        // debug
        if (empty($error) && $submitBtn = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Submit')] | //li[contains(@class, 'gigya-container-enabled')]//input[@value='Continue']"), 0)) {
            $submitBtn->click();

            // Invalid code entry !! Please try again.
            $error = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'gig-tfa-error')]"), 5);
            $this->logger->debug("[Current URL]: " . $this->http->currentUrl());
            $this->saveResponse();
        }

        if (!empty($error)) {
            $message = $error->getText();
            $this->logger->error("error: " . $message);

            if (strstr($message, 'Please enter a valid code')) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $message, "Question");
            }

            return false;
        }// if (!empty($error))
        $this->logger->debug("success");

        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = '6LfN85khAAAAAJoyYm2JleYKYZ9db0-hdQ2h4io7';

        /* $postData = [
             "type"          => "RecaptchaV2EnterpriseTaskProxyless",
             "websiteURL"    => $this->http->currentUrl(),
             "websiteKey"    => $key,
             "apiDomain"     => 'www.google.com',
         ];
         $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
         $this->recognizer->RecognizeTimeout = 120;

         return $this->recognizeAntiCaptcha($this->recognizer, $postData);*/

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;

        $parameters = [
            "pageurl"     => $this->http->currentUrl(),
            "proxy"       => $this->http->GetProxy(),
            'type'        => 'ReCaptchaV2TaskProxyLess',
            "isInvisible" => true,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'Logout')]"), 0);
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        return false;
    }
}
