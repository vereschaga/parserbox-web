<?php

class TAccountCheckerVicsecrets extends TAccountChecker
{
    /*
     * LIKE as jcrew
     */
    use SeleniumCheckerHelper;

    private $questionIdCode = 'Please enter Identification Code which was sent to your email address. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.';

    /*
     * Scripts
     *
     * https://static.gpshopper.com/mp2/js/ads/encrypt.js
     *
     * https://c.comenity.net/common/js/md5.js
     * https://c.comenity.net/common/js/ecsScript.min.js
     */

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useChromium();
        $this->disableImages();
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        try {
            $this->http->GetURL("https://c.comenity.net/victoriassecret/");
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        $signInBtn = $this->waitForElement(WebDriverBy::id('existing-cardmember-sign-in-button-link'), 5);

        if ($signInBtn) {
            $signInBtn->click();
        }

        try {
            $iframe = $this->waitForElement(WebDriverBy::id('public-home-signin-iframe'), 10);
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            sleep(3);
            $this->logger->notice("wait result, attempt 2");
            $iframe = $this->waitForElement(WebDriverBy::id('public-home-signin-iframe'), 10);
        }

        $this->saveResponse();

        if ($iframe) {
            $this->logger->error("switch to iframe");
            $this->driver->switchTo()->frame($iframe);
        }
        $this->logger->error("find login filed");
        $loginInput = $this->waitForElement(WebDriverBy::id('userNameSignInV2_input'), 5);
        $passwordInput = $this->waitForElement(WebDriverBy::id('passwordSignInV2_input'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "iframeSignInBtn"] | //a[@id = "signInButton_submit"]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            return $this->checkErrors();
        }
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        if ($rememberMeCheckboxSignIn = $this->waitForElement(WebDriverBy::id('rememberMeInput'), 0)) {
            $rememberMeCheckboxSignIn->click();
        }

        try {
            $button->click();
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        return true;
    }

    public function providerErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h2[contains(text(), "Error 404--Not Found")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath("
            //label[@for = 'securityQuestion_input']
            | //a[contains(@id, 'mvc_logOut')]
            | //a[contains(@id, 'ac_signout_link')]
            | //h2[contains(text(), 'Account Summary')]
            | //strong[contains(text(), 'How do you want to receive your unique ID code?')]
            | //span[@id = 'securityQuestionLogin_error']
            | //div[contains(@class, 'aria-live-area')]
            | //div[contains(text(), 'The information you entered does not match our records.')]
            | //p[contains(text(), 're sorry, but the account you are trying to access is closed.')]
            | //span[contains(text(), 'Account Center is currently taking payments only.')]
            | //p[contains(text(), 'Due to numerous incorrect attempts, the ability to sign in to your online account has been temporarily frozen.')]
            | //p[contains(text(), 'Due to a scheduled system update, our website can only accept same-day payments at this time.')]
            | //h2[contains(text(), 'Go Paperless')]
            | //h1[@id = 'paperless-nag-text-block-title']
            | //h2[contains(text(), 'We're experiencing technical difficulties')]
        "), 15);
        $this->saveResponse();

        /*
        if ($rewardsPage = $this->waitForElement(WebDriverBy::id("ac_rewards_link"), 0)) {
            $rewardsPage->click();
            sleep(5);
            $this->saveResponse();
        }
        */

        if (
            $this->http->FindNodes("//a[contains(@id, 'mvc_logOut') or contains(@id, 'ac_signout_link')]/@id | //h2[contains(text(), 'Account Summary')]")
            && !$this->http->FindSingleNode("//strong[contains(text(), 'How do you want to receive the code?')]")
        ) {
            return true;
        }

        if (
            $this->http->FindNodes("//h2[contains(text(), 'Go Paperless')] | //h1[@id = 'paperless-nag-text-block-title']")
        ) {
            if ($remindMeLater = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Remind Me Later')]"), 0)) {
                $remindMeLater->click();
            } else {
                $this->http->GetURL("https://c.comenity.net/ac/victoriassecret-vsvip/secure/rewards");
            }

            $this->waitForElement(WebDriverBy::xpath("//a[contains(@id, 'mvc_logOut') or contains(@id, 'ac_signout_link')]/@id | //h2[contains(text(), 'Account Summary')]"), 5);
            $this->saveResponse();

            return true;
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if ($this->http->FindSingleNode("//span[@id = 'securityQuestionLogin_error']") && $this->parseQuestion()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode("//div[@class = 'aria-live-area sr-only']")) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'The information you entered does not match our records. Please try again. Forgot username or password?')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Sorry, that\'s too many incorrect sign in attempts. Forgot your username or password')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, 'We\'re sorry. A technical glitch occurred and your request could not be completed. Please try again.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }
        // We're sorry, but the account you are trying to access is closed.
        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "We\'re sorry, but the account you are trying to access is closed.")]
                | //p[contains(text(), "Due to numerous incorrect attempts, the ability to sign in to your online account has been temporarily frozen.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Account Center is currently taking payments only.')]")
            ?? $this->http->FindSingleNode("//span[contains(text(), 'Due to a scheduled system update, our website can only accept same-day payments at this time.')]/text()[last()]")
            ?? $this->http->FindSingleNode('//h2[contains(text(), "We\'re experiencing technical difficulties")]')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // skip enrollment in paperless communications
        /*
        if ($this->http->ParseForm("frmPaperlessNAG")
            && $this->http->FindSingleNode("//h2[contains(text(), 'Convenient. Secure. Smart. Go Paperless for a whole host of benefits')]")) {
            $this->logger->notice("skip enrollment in paperless communications");
            $this->http->SetInputValue("inpPaperlessStatement_input", "notEnrolled");
            $this->http->SetInputValue("btnRegistrationConfirm", "btnRegistrationConfirm");
            $this->http->PostForm();
        }
        if ($this->http->ParseForm("paperlessNAGForm")
            && $this->http->FindSingleNode("//h1[contains(text(), 'Go Paperless')]")) {
            $this->logger->notice("skip enrollment in paperless communications");
            $this->http->SetInputValue("paperlessNAGForm_paperlessNAGRemindMeLater", "paperlessNAGForm_paperlessNAGRemindMeLater");
            $this->http->PostForm();
        }
        */

        $this->checkErrors();

        return $this->providerErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if (
            !$this->waitForElement(WebDriverBy::xpath("
                //strong[contains(text(), 'How do you want to receive your unique ID code?')]
            "), 0)
            && !$this->waitForElement(WebDriverBy::xpath("
                //a[@id = 'sendEmail']
            "), 0, false)
        ) {
            return false;
        }

        $sendEmailBtn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'sendEmailBtn']"), 0)
            ?? $this->waitForElement(WebDriverBy::xpath("//a[@id = 'sendEmail']"), 0, false);
        $this->saveResponse();

        if (!$sendEmailBtn && $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'No email address found')]"), 0)) {
            $sendPhoneBtn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'sendTextBtn']"), 0);

            if (!$sendPhoneBtn && $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'No mobile number found')]"), 0)) {
                $this->throwProfileUpdateMessageException();
            }
        }

        if (!$sendEmailBtn && !$sendPhoneBtn) {
            return false;
        }

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        if ($sendEmailBtn) {
            $sendEmailBtn->click();
        } else {
            $sendPhoneBtn->click();
        }

        $receivedMyCodeYesClickable = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'receivedMyCodeYesClickable'] | input[@id = 'didYouReceiveYourCode_input_0']"), 7);
        $this->saveResponse();

        if (!$receivedMyCodeYesClickable) {
            return false;
        }
        $receivedMyCodeYesClickable->click();

        $this->processIdentificationCode();

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        switch ($step) {
            case "Question":
                return $this->processIdentificationCode();

                break;
        }

        return false;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($error = $this->http->FindSingleNode("//div[@id = 'signInContainerSection-message']//span[@id = 'signInContainerSection-error']")) {
            if ($this->http->FindPreg("/account has been locked/ims", false, $error)) {
                throw new CheckException($error, ACCOUNT_LOCKOUT);
            } else {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
        }
        // We're sorry but this Account is closed.
        if ($message = $this->http->FindSingleNode('//strong[contains(text(), "We\'re sorry but this Account is closed.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        /*
         * We apologize for the inconvenience.
         * The specific operation you are trying to do is not available now.
         */
        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'The specific operation you are trying to do is not available now')]
                | //h2[contains(text(), 'Sign-in is not available at this time. We apologize for any inconvenience. Please try again later.')]
                | //p[contains(text(), 'We know the recent system outage has been frustrating and we will work to ensure a fair resolution for impacted customers')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We’re sorry. There’s been an unexpected interruption and this action cannot be completed right now
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We’re sorry. There’s been an unexpected interruption and this action cannot be completed right now") or contains(text(), "We\'re sorry. There\'s been an unexpected interruption and this action cannot be completed right now. Please try again later.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We apologize for the inconvenience. We are temporarily experiencing technical difficulties.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We apologize for the inconvenience. We are temporarily experiencing technical difficulties.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (($this->http->currentUrl() == 'https://c.comenity.net/victoriassecret/public/signin/ForcedPasswordReset.xhtml' && $this->http->FindSingleNode("//p[contains(text(), 'To enable these features, you must update your password')]"))
            || $this->http->FindSingleNode("//div[@data-trackvalue and contains(., 'To protect your privacy and account security, please change your password.')]")) {
            throw new CheckException("Victoria Secrets (Angle card) website is asking you to create a new password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }
        // Your identity is confirmed. Click or tap Continue to go to your Account Center home page.
//        if ($this->http->ParseForm("outOfBandSuccess")) {
//            $this->http->SetInputValue("continueToYourAccount", "continueToYourAccount");
//            $this->http->PostForm();
//        }// if ($this->http->ParseForm("outOfBandSuccess"))

        return false;
    }

    public function Parse()
    {
        /*
        // Balance - Current Total Points
        $b = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Current Total')]/preceding-sibling::span[@class = 'total']"), 1);
        $this->saveResponse();

        if (!$b) {
            if ($message = $this->http->FindSingleNode('//h2[contains(text(), "We\'re experiencing technical difficulties")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }
        $this->driver->executeScript("var modal = document.getElementById('AccountSummaryDialogId'); if (modal) modal.style.display = 'none';");
        $this->saveResponse();
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Current Total')]/preceding-sibling::span[@class = 'total']"));
        // Minimum payment
        $this->SetProperty('MinimumPayment', $this->http->FindSingleNode("//span[@id = 'accountInfoHeaderContent_minimumPayment']/following-sibling::section/h3/strong"));
        // Statement balance
        $this->SetProperty('StatementBalance', $this->http->FindSingleNode("//span[@id = 'accountInfoHeaderContent_statementBalancePeriod']/following-sibling::section/h3/strong"));
        // Available Credit
        $this->SetProperty('CurrentBalance', $this->http->FindSingleNode('//h3[@id = "accountInfoTitle_creditLimit"]/strong'));
        // Points until next reward
        $this->SetProperty('UntilNextReward', $this->http->FindSingleNode('//h3[@id = "accountInfoTitle_rewards"]/strong'));

        if ($rewardsBtn = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'rewardsBtn']"), 0)) {
//            $rewardsBtn->click();
            $this->driver->executeScript("document.querySelector('#rewardsBtn button').click();");
            $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Current Total')]/preceding-sibling::span[@class = 'total']"), 7); //todo
            $this->increaseTimeLimit();

            try {
                $this->saveResponse();
            } catch (TimeOutException | ScriptTimeoutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
            }
        }

        // rare accounts with a different design
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
        */

        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//h1[contains(text(), 'Welcome,')]", null, true, "/,\s*([^\!\,\.]+)/")));

        // Rewards gage
        $rewardsLink = $this->http->FindSingleNode("//a[@id = 'rewards-and-benefits-header']/@href");

        if (!$rewardsLink) {
            $rewardsLink = "https://c.comenity.net/ac/victoriassecret-vsforever/secure/rewards";
        }
        $this->http->NormalizeURL($rewardsLink);

        try {
            $this->http->GetURL($rewardsLink);
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        $this->waitForElement(WebDriverBy::xpath("//span[@id = 'currentBasePointsValue']"), 5);
        $this->saveResponse();

        // Base Points
        $this->SetProperty('BasePoints', $this->http->FindSingleNode('//p[contains(., "You\'ve earned ")]', null, true, "/\(([\d\.\,]+) base \+ [\d\.\,]+ bonus\)/"));
        // Bonus Points
        $this->SetProperty('BonusPoints', $this->http->FindSingleNode('//p[contains(., "You\'ve earned ")]', null, true, "/\([\d\.\,]+ base \+ ([\d\.\,]+) bonus\)/"));
        // Points until next reward
        $this->SetProperty('UntilNextReward', $this->http->FindSingleNode("//span[contains(., ' to Next Reward As')]", null, true, "/(\d+)\s*Point/ims"));

        // Balance - You've earned ... points this fulfillment period!
        if (!$this->SetBalance($this->http->FindSingleNode('//p[contains(., "You\'ve earned ")]', null, true, "/earned\s*([\-\.\,\s\d]+)(?:\s*total reward)\s*point/ims"))) {
            if ($message = $this->http->FindSingleNode("//span[
                    contains(text(), 'We are sorry; no rewards detail information is available at this time.')
                    or contains(text(), 're sorry, no reward information is available at this time.')
                ]")
                ?? $this->http->FindPreg('/Effective February 22nd, your rewards, benefits and tier status information will be available in your Victoria’s Secret account. Visit <a href="https:\/\/www.victoriassecret\.com\/us\/\" target="_blank" aria-label="Go to the Victoria\'s Secret website">VictoriasSecret.com<\/a> to create an account and link your card\./')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }
//        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // Name
        $profileLink = $this->http->FindSingleNode("//a[@id = 'emailAddressNeedsConfirmedOrUpdatedAlert']/@href");

        if (!$profileLink) {
            $profileLink = "https://c.comenity.net/ac/victoriassecret-vsforever/secure/account-profile";
        }
        $this->http->NormalizeURL($profileLink);

        try {
            $this->http->GetURL($profileLink);
            $this->waitForElement(WebDriverBy::xpath("//span[@id = 'nameDisplay_namePanelGroup']"), 5);
            $this->saveResponse();
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }
        $name = $this->http->FindSingleNode("//span[@id = 'nameDisplay_namePanelGroup'] | //div[@id = 'nameAccordionInnerHtmlContainerId']");

        if ($name) {
            $this->SetProperty('Name', beautifulName($name));
        }
    }

    protected function processIdentificationCode()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        // Enter Your Identification Code
        $q = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Your unique ID code was sent to")]'), 10);
        $this->saveResponse();

        if (!$q) {
            $this->logger->error("question not found");

            return false;
        }
        $question = $q->getText();

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }// if (!isset($this->Answers[$question]))

        $idCodeInput = $this->waitForElement(WebDriverBy::id('idCodeInput'), 0);
        $rememberMeLabel = $this->waitForElement(WebDriverBy::id('rememberMeLabel'), 0);
        $confirmMyIdentityBtn = $this->waitForElement(WebDriverBy::id('confirmMyIdentityBtn'), 0);
        $this->saveResponse();

        if (!$idCodeInput || !$rememberMeLabel || !$confirmMyIdentityBtn) {
            $this->logger->error("something went wrong");

            return false;
        }
        $rememberMeLabel->click();
        $this->saveResponse();
        $idCodeInput->clear();
        $idCodeInput->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);
        $confirmMyIdentityBtn->click();
        sleep(5);
        // The code you entered is incorrect. Please enter your Identification Code again exactly as you received it
        $error = $this->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'The code you entered is incorrect.') or contains(text(), 'You entered an invalid code.')]"), 0);
        $this->saveResponse();

        if (!empty($error)) {
            $error = $error->getText();
            $this->logger->notice("error: " . $error);
            $this->holdSession();
            $this->AskQuestion($question, $error, "Question");

            return false;
        }// if (!empty($error))

//        $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Current Total')]/preceding-sibling::span[@class = 'total']"), 0);
//        $this->saveResponse();

        return true;
    }
}
