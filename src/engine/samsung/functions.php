<?php

use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSamsung extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const WAIT_TIMEOUT = 7;

//    private const REWARDS_PAGE_URL = 'https://www.samsung.com/us/support/account/';
    private const REWARDS_PAGE_URL = "https://www.samsung.com/us/web/account/my-rewards/";

    private const XPATH_NOT_NOW = '//a[@*="btnNotNow();"] | //button[@id = "btnNotNow"] | //button[@*="btnNotNow()"] | //a[@id = "termsUpdateNotNow"] | //button[contains(text(), "Read later")]"]';

    private const XPATH_REWARDS_POINTS = '//div[@id="rewards-summary-wrapper"]//strong[contains(text(), "Available Now")]/following-sibling::div/span[1]/text()[1] | //p[contains(@class, "available-reward-points")]/strong | //p[contains(text(), "Available now")]/following-sibling::p/strong';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

//        $this->useFirefoxPlaywright();
        $this->useFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        /*
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->setKeepProfile(true);
        */

        $this->http->SetProxy($this->proxyReCaptcha());

        $wrappedProxy = $this->services->get(WrappedProxyClient::class);
        $proxy = $wrappedProxy->createPort($this->http->getProxyParams());
        $this->seleniumOptions->antiCaptchaProxyParams = $proxy;
        $this->seleniumOptions->addAntiCaptchaExtension = true;

        $this->usePacFile(false);

//        $this->disableImages();
//        $this->useCache();
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        } catch (UnexpectedJavascriptException | WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->removeCookies();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 9);
        }

        // Please enter a valid Email.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid Email.', ACCOUNT_INVALID_PASSWORD);
        }

        try {
//            $this->http->GetURL("https://www.samsung.com/us/support/");
//            sleep(2);
//            $this->http->GetURL("https://account.samsung.com/membership/auth/sign-in");
//            $this->http->GetURL("https://www.samsung.com/us/support/account/");
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }
        /*
        $loginFormlink = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "SIGN UP NOW")]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if (!$loginFormlink) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }

        $loginFormlink->click();
        */

        // visible-invisible
        $this->waitForElement(WebDriverBy::xpath('//input[@id = "iptLgnPlnID"]'), self::WAIT_TIMEOUT * 7, false);
        $ssoCommonAlertClose = $this->waitForElement(WebDriverBy::xpath('//button[@id = "ssoCommonAlertClose"]'), self::WAIT_TIMEOUT);

        if ($ssoCommonAlertClose) {
            $ssoCommonAlertClose->click();
            $this->waitForElement(WebDriverBy::xpath('//input[@id = "iptLgnPlnID"]'), self::WAIT_TIMEOUT, false);
        }

        $this->saveResponse();

        $logout = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Log Out")] | //p[contains(@class, "available-reward-points")]/strong | //p[contains(text(), "Available now")]/following-sibling::p/strong'), 0);
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        try {
            $this->driver->executeScript("document.getElementById(\"iptLgnPlnID\").parentElement.classList = ['focus'];");
        } catch (Facebook\WebDriver\Exception\JavascriptErrorException | UnexpectedJavascriptException | UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);

            if (!$this->waitForElement(WebDriverBy::xpath('//input[@id = "iptLgnPlnID"]'), 0)) {
                $this->saveResponse();

                throw new CheckRetryNeededException(2, 0);
            }
        }

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "iptLgnPlnID"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if (!$login) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }

        if ($rememberMe = $this->waitForElement(WebDriverBy::xpath('//label[@for="remIdChkYN"]'), 0)) {
            $rememberMe->click();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $this->logger->debug("click by btn");
        $signInButton = $this->waitForElement(WebDriverBy::xpath('//button[@id = "signInButton" and not(contains(@class, "hide"))]'), 5);
        $signInButton->click();

        $pass = $this->waitForElement(WebDriverBy::xpath('//input[@id = "iptLgnPlnPD"]'), 5);
        $this->saveResponse();

        if (!$pass) {
            $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 10);

            $this->waitFor(function () {
                $this->logger->warning("Solving is in process...");
                sleep(3);
                $this->saveResponse();

                return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
            }, 250);

            $signInButton = $this->waitForElement(WebDriverBy::xpath('//button[@id = "signInButton" and not(contains(@class, "hide"))]'), 5);
            $this->saveResponse();
            $signInButton->click();
            $pass = $this->waitForElement(WebDriverBy::xpath('//input[@id = "iptLgnPlnPD"]'), 5);
            $this->saveResponse();
        }

        if (!$pass) {
            $this->logger->error("something went wrong");

            if ($this->http->FindSingleNode('//p[contains(text(), "Your Samsung account was created using your Google account, so you need to sign in with your Google account.")]')) {
                throw new CheckException('Sorry, login via Google is not supported', ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode('//div[(@class="" or @class="ng-scope") and contains(text(), "ID not valid.")]')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return $this->checkErrors();
        }

        $pass->sendKeys($this->AccountFields['Pass']);
        /*
        $pass->sendKeys(WebDriverKeys::ENTER);
        */
        $signInButton = $this->waitForElement(WebDriverBy::xpath('//button[@id = "signInButton" and not(contains(@class, "hide"))]'), 5);
        $this->saveResponse();

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            try {
                $signInButton->click();
            } catch (UnrecognizedExceptionException | NoSuchElementException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                $this->driver->executeScript('document.getElementById("signInButton").click();');
            }

            return true;
        }
        $this->driver->executeScript("
            var scope = angular.element('.one-forms-login form').scope();
            scope.\$apply(() => {
                scope.vm.responseCapthcha = '{$captcha}';
                }
            )

            scope.\$parent.submitHandler();
        ");

//        $signInButton->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "This site is currently down for maintenance.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $res = $this->waitForElement(WebDriverBy::xpath(
            self::XPATH_REWARDS_POINTS . '
                | //div[@class="error-msg"]/div[@class=""]
                | ' . self::XPATH_NOT_NOW . '
                | //p[contains(text(), "The Terms and conditions have been updated. Read and accept the new terms and conditions to continue using Samsung services.")]
                | //p[contains(text(), "To continue, agree to our updated")]
                | //h1[contains(text(), "Your Samsung account has been locked")]
                | //*[self::h1 or self::h2][contains(text(), "Two-step verification")]
                | //h1[contains(text(), "Verify it\'s you")]
                | //h1[contains(text(), "Samsung account policies updated")]
                | //b[contains(text(), "Couldn\'t load webpage")]
                | //input[@id = "enrollTerms"]
        '), self::WAIT_TIMEOUT * 2);
        $this->saveResponse();

        $this->waitFor(function () {
            $this->logger->warning("Solving is in process...");
            sleep(3);
            $this->saveResponse();

            return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
        }, 250);

        if (!$res && ($signInButton = $this->waitForElement(WebDriverBy::xpath('//button[@id = "signInButton" and not(contains(@class, "hide"))]'), 0))) {
            $signInButton->click();
            $this->saveResponse();

            $this->waitForElement(WebDriverBy::xpath(
                    self::XPATH_REWARDS_POINTS . '
                    | //div[@class="error-msg"]/div[@class=""]
                    | ' . self::XPATH_NOT_NOW . '
                    | //p[contains(text(), "The Terms and conditions have been updated. Read and accept the new terms and conditions to continue using Samsung services.")]
                    | //p[contains(text(), "To continue, agree to our updated")]
                    | //h1[contains(text(), "Your Samsung account has been locked")]
                    | //*[self::h1 or self::h2][contains(text(), "Two-step verification")]
                    | //h1[contains(text(), "Verify it\'s you")]
                    | //h1[contains(text(), "Samsung account policies updated")]
                    | //b[contains(text(), "Couldn\'t load webpage")]
                    | //input[@id = "enrollTerms"]
            '), self::WAIT_TIMEOUT * 2);
            $this->saveResponse();
        }

        $ssoCommonAlertClose = $this->waitForElement(WebDriverBy::xpath('//button[@id = "ssoCommonAlertClose"]'), self::WAIT_TIMEOUT);

        if ($ssoCommonAlertClose) {
            $ssoCommonAlertClose->click();
            $this->waitForElement(WebDriverBy::xpath(
                self::XPATH_REWARDS_POINTS . '
                | //div[@class="error-msg"]/div[@class=""]
                | ' . self::XPATH_NOT_NOW . '
                | //p[contains(text(), "The Terms and conditions have been updated. Read and accept the new terms and conditions to continue using Samsung services.")]
                | //p[contains(text(), "To continue, agree to our updated")]
                | //h1[contains(text(), "Your Samsung account has been locked")]
                | //*[self::h1 or self::h2][contains(text(), "Two-step verification")]
                | //h1[contains(text(), "Verify it\'s you")]
                | //h1[contains(text(), "Samsung account policies updated")]
                | //b[contains(text(), "Couldn\'t load webpage")]
                | //input[@id = "enrollTerms"]
            '), self::WAIT_TIMEOUT);
            $this->saveResponse();
        }

        if ($this->checkCredentialsErrors()) {
            return false;
        }

        // false/positive message
        if ($this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Samsung account policies updated")]'), 0)) {
            $this->waitForElement(WebDriverBy::xpath('//label[@for="iptTncTC"]'), 0)->click();
            $this->waitForElement(WebDriverBy::xpath('//label[@for="iptTncST"]'), 0)->click();

            if ($iptTncNFI = $this->waitForElement(WebDriverBy::xpath('//label[@for="iptTncNFI"]'), 0)) {
                $iptTncNFI->click();
            }

            if ($option3 = $this->waitForElement(WebDriverBy::xpath('//label[@for="option3"]'), 0)) {
                $option3->click();
            }

            $agreeBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Agree") and not(@disabled)]'), 3);
            $this->saveResponse();

            if (!$agreeBtn) {
                return false;
            }

            $agreeBtn->click();
        }

        // Updated Terms and Conditions
        if ($this->waitForElement(WebDriverBy::xpath('//p[
                contains(text(), "The Terms and conditions have been updated. Read and accept the new terms and conditions to continue using Samsung services.")
                or contains(text(), "To continue, agree to our updated")
            ]
            '), 0)
        ) {
            $this->throwAcceptTermsMessageException();
        }

        $this->skipOffer();

        // Two-step verification
        if ($this->waitForElement(WebDriverBy::xpath('
                //*[self::h1 or self::h2][contains(text(), "Two-step verification")]
                | //h1[contains(text(), "Verify it\'s you")]
            '), 0)
        ) {
            return $this->processTwoStepVerification();
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if (
            $this->http->currentUrl() == 'https://account.samsung.com/membership/'
            || $this->http->currentUrl() == 'https://www.samsung.com/us/'
            || strstr($this->http->currentUrl(), '.account.samsung.com/dashboard')
        ) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        // very strange account^ may be provider bug, AccountID: 4965884
        if ($this->AccountFields['Login'] == 'gouin.yannick@gmail.com'
            && $this->http->FindSingleNode('//body[@id = "error"]/h1/img[@src = "/rewards/public/COMPILED/images/SS_Reward_Horz_Logo_BLK.a1a994e9c159e0cb0d49fc831f05031e.png"]/@src')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        /*
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "We are undergoing a system maintenance to enhance our customer experience.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        */
        // TODO
        $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "my-profileInfo_profileInfo-right-address__email")]'), 10);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Your Samsung account has been locked")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($this->http->FindSingleNode('
                //h2[contains(text(), "We\'re sorry...")]/following-sibling::p[contains(text(), "An error has occurred. Refresh your web browser to get back on track.")]
                | //h1[contains(text(), "502 Bad Gateway")]
                | //b[contains(text(), "Couldn\'t load webpage")]
            ')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//input[@id = "enrollTerms"]/@id')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->checkCredentialsErrors()) {
            return false;
        }

        // no auth, no errors
        /*
        if (
            $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "LOG IN/SIGN UP") or contains(text(), "SIGN UP NOW")]'), 0)
            && in_array($this->AccountFields['Login'], [
        */
        if (in_array($this->AccountFields['Login'], [
            /*
                "jeph36@gmail.com",
                "bhauwd@amazon.com",
                "matthewplese0916@gmail.com",
                "anandm5346@gmail.com",
                "john@gilham.net",
                "peter@peterwestcarey.com",
                "vernierengine@gmail.com",
                "jamal2193@fb.com",
                */
            "ives.junfei@outlook.com",
            /*
                "yazeen23@hotmail.com",
                "bobs013@aol.com",
                "cvergh1@yahoo.com",
                */
        ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function checkCredentialsErrors(): Bool
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//div[@class="error-msg"]/div[@class="" and normalize-space(.) != ""]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Incorrect ID or password')
                || strstr($message, 'Incorrect password.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Invalid ID or password has been entered more than 5 times. To protect your personal information, enter your security code.
            if (strstr($message, 'Invalid ID or password has been entered more than')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return true;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == "Question") {
            return $this->processTwoStepVerification();
        }

        return false;
    }

    public function Parse()
    {
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        // Balance - Available Points
        $this->SetBalance($this->http->FindSingleNode(self::XPATH_REWARDS_POINTS));
        // Name
        $this->SetProperty('Name', beautifulName(
            $this->http->FindSingleNode('(//span[contains(@class, "gnb__user-name-inner")])[1]', null, true, "/([^!]+)/")
            ?? $this->http->FindSingleNode('//div[contains(@class, "user-account-wrap")]//p[contains(@class, "user-name")]')
        ));
        // Balance worth
        $this->SetProperty('BalanceWorth',
            $this->http->FindSingleNode('//div[@id="rewards-summary-wrapper"]//strong[contains(text(), "Available Now")]/following-sibling::p[1]/span')
            ?? $this->http->FindSingleNode('//p[contains(@class, "available-reward-points")]/text()[last()]', null, true, "/Equal to (.+)/")
            ?? $this->http->FindSingleNode('//div[contains(@class, "ra-available-points")]', null, true, "/Equal to (.+)/")
        );
        // Pending Points
        $this->SetProperty('PendingPoints', $this->http->FindSingleNode('//div[@id="rewards-summary-wrapper"]//strong[contains(text(), "Pending")]/following-sibling::div/span[1]/text()[1] | //p[contains(@class, "pending-reward-points")]/strong | //p[contains(text(), "Pending")]/following-sibling::p/strong'));
        // Pending points worth
        $this->SetProperty('PendingPointsWorth',
            $this->http->FindSingleNode('//div[@id="rewards-summary-wrapper"]//strong[contains(text(), "Pending")]/following-sibling::p[1]/span')
            ?? $this->http->FindSingleNode('//p[contains(@class, "pending-reward-points")]/span[contains(@class, "equals-to-value")]', null, true, "/Equal to (.+)/")
            ?? $this->http->FindSingleNode('//div[contains(@class, "ra-pending-points")]/p[contains(@class, "accrued-points-value")]', null, true, "/Equal to (.+)/")
        );

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode('//h2[contains(text(), "Sign up for rewards")]')) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }
    }

    protected function processTwoStepVerification()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $questionObject = $this->waitForElement(WebDriverBy::xpath('//p[
            contains(text(), "Please enter the verification code to complete the sign-in process.")
            or contains(., "To verify it\'s you, get a code from your authenticator app and enter it below.")
            or contains(., "To verify it\'s you, enter the code we sent to your Samsung phone or tablet.")
            or contains(., "For verification, enter the code we sent to your phone or tablet.")
        ]'), 0);
        $this->saveResponse();
        $phone = $this->http->FindPreg('/To verify it\'s you, we\'ll send a verification code to ([^\.]+)\./') ?? $this->http->FindSingleNode('//div[input[@checked="checked"]]');
        $email = $this->http->FindSingleNode('//div[@class = "user-email"]');
        $this->logger->debug("Phone -> {$phone}");

        if (!$questionObject && !$phone && !$email) {
            $this->logger->error("something went wrong");

            return false;
        }

        if (!$questionObject && $email) {
            $this->logger->debug("Send Verification Code to email: {$email}");
            $question = "Please enter Verification Code which was sent to the following email address: $email. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        } elseif (!$questionObject && $phone) {
            $this->logger->debug("Send Verification Code to phone: {$phone}");
            $question = "Please enter Verification Code which was sent to the following phone number: $phone. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        } else {
            $question = trim($questionObject->getText());
        }

        $this->logger->debug("Question -> {$question}");

        if (
            $this->http->FindSingleNode('//p[contains(text(), "To verify it\'s you, click Send code")]')
            && ($btnSms = $this->waitForElement(WebDriverBy::xpath('//button[@id = "btnSms"]'), 0))
        ) {
            $btnSms->click();
            sleep(3);
            $this->saveResponse();
        }

        if (empty($this->Answers[$question]) || !is_numeric($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }
        $this->logger->debug("Entering answer on question -> {$question}...");
        $answerInput = $this->driver->findElement(WebDriverBy::xpath("
            //input[@id = 'smsNumber']
            | //input[@id = 'otp']
            | //input[@id = 'iptAuthNum']
        "));
        $btn = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Verify') or contains(text(), 'Extend time')]"), 0);

        if (!empty($question) && $answerInput && $btn) {
            // Don't ask me again on this device
            $this->driver->executeScript('if (checkBox = document.getElementById("isRegisterTrustedDeviceCB")) { checkBox.click(); checkBox.checked = true; }');
            $this->driver->executeScript('if (checkBox = document.getElementById("isTrustedLocation")) { checkBox.click(); checkBox.checked = true; }');
            $this->driver->executeScript('if (checkBox = document.getElementById("isRegisterTrustedDeviceCB")) { checkBox.click(); checkBox.checked = true; }');
            $this->saveResponse();
            $this->driver->executeScript('$(\'#isRegisterTrustedDeviceCB, button.one-primary\').removeAttr(\'disabled\');');
            $this->saveResponse();
            $answer = $this->Answers[$question];
            unset($this->Answers[$question]);
            $answerInput->sendKeys($answer);
            $this->saveResponse();
            $this->logger->debug("click 'Submit'...");
            $btn = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(),'Verify')]"), 0);

            if ($btn) {
                $btn->click();
            }

            sleep(5);

            $this->logger->debug("find errors...");
            $error = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Sorry. That code is incorrect.') or contains(text(), 'Incorrect code.')]"), 5);
            $this->saveResponse();

            if ($error) {
                $this->holdSession();
                $this->AskQuestion($question, $error->getText(), "Question");
                $this->logger->error("answer was wrong");

                return false;
            }

            if ($message = $this->http->FindSingleNode('//h2[contains(text(), "Error Message [500]")]/following-sibling::p[contains(text(), "Sorry, an error occurred while processing the page you requested.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->skipOffer();

            $this->logger->debug("done");
            $this->saveResponse();

            return true;
        }

        return false;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class = 'g-recaptcha' and @data-badge=\"bottomright\"]/@data-sitekey");

        if (!$key) {
            return false;
            $key = $this->http->FindSingleNode("//div[@class = 'grecaptcha-badge' and @data-style = 'bottomright']//iframe/@src", null, true, "/k=([^&]+)/");

            if ($key) {
                $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
                $this->recognizer->RecognizeTimeout = 120;
                $parameters = [
                    "pageurl"   => $this->http->currentUrl(),
                    "proxy"     => $this->http->GetProxy(),
                    "version"   => "enterprise",
                    "action"    => "signInIdentification",
                    "min_score" => 0.3,
                ];

                return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
            }

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

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $logout = $this->waitForElement(WebDriverBy::xpath('
            //a[contains(text(), "Log Out") or contains(text(), "LOG OUT")]
            | //div[@id="rewards-summary-wrapper"]//strong[contains(text(), "Available Now")]
            | //p[contains(text(), "Available now")]/following-sibling::p/strong
            | //p[contains(@class, "available-reward-points")]/strong
        '), 5);
        $this->saveResponse();

        if ($logout || $this->http->FindSingleNode('//div[@id="rewards-summary-wrapper"]//strong[contains(text(), "Available Now")] | //p[contains(@class, "available-reward-points")] | //p[contains(text(), "Available now")]/following-sibling::p/strong') !== null) {
            return true;
        }

        return false;
    }

    private function skipOffer()
    {
        $this->logger->notice(__METHOD__);

        try {
            // Click to "Not now" link
            $this->driver->executeScript("let btnNotNow = document.querySelector('#btnNotNow'); if (btnNotNow) btnNotNow.click();");

            if ($link = $this->waitForElement(WebDriverBy::xpath(self::XPATH_NOT_NOW), 0)) {
                $dontAskAgain = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "one-checkBox") and contains(., "Don\'t show again for 7 days")]'), 0);

                if ($dontAskAgain) {
                    $dontAskAgain->click();
                }
                $this->saveResponse();
                $link->click();

                if (
                    !$this->waitForElement(WebDriverBy::xpath(self::XPATH_REWARDS_POINTS), self::WAIT_TIMEOUT)
                    && ($link = $this->waitForElement(WebDriverBy::xpath(self::XPATH_NOT_NOW), 0))
                ) {
                    $this->saveResponse();
                    $link->click();
                }

                if (
                    !$this->waitForElement(WebDriverBy::xpath(self::XPATH_REWARDS_POINTS), self::WAIT_TIMEOUT)
                    && ($link = $this->waitForElement(WebDriverBy::xpath(self::XPATH_NOT_NOW), 0))
                ) {
                    $this->saveResponse();
                    $link->click();
                    $this->waitForElement(WebDriverBy::xpath(self::XPATH_REWARDS_POINTS), self::WAIT_TIMEOUT);
                }
                $this->saveResponse();
            }

            if (
                $link = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'SEE MY POINTS')]"), 0)
            ) {
                $this->saveResponse();
                $link->click();

                if (
                    !$this->waitForElement(WebDriverBy::xpath(self::XPATH_REWARDS_POINTS), self::WAIT_TIMEOUT)
                    && ($link = $this->waitForElement(WebDriverBy::xpath(self::XPATH_NOT_NOW), 0))
                ) {
                    $this->saveResponse();
                    $link->click();
                    $this->waitForElement(WebDriverBy::xpath(self::XPATH_REWARDS_POINTS), self::WAIT_TIMEOUT);
                }
                $this->saveResponse();
            }

            if ($this->http->FindSingleNode('//h1[
                    contains(text(), "Samsung Account policies updated")
                    or contains(text(), "Samsung account policies updated")
                    or contains(text(), "Samsung account Privacy Notice updated")
                ]')
            ) {
                $this->throwProfileUpdateMessageException();
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException();
        }
    }
}
