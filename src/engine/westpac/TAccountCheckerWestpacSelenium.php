<?php

class TAccountCheckerWestpacSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->KeepState = true;
        $this->useGoogleChrome();
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://banking.westpac.com.au/wbc/banking/handler?TAM_OP=login&segment=personal&logout=false");
        $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'fakeusername']"), 10);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
        $submitButton = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'signin']"), 0);

        if (!$login || !$pass || !$submitButton) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $submitButton->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        switch ($step) {
            case "2fa":
                return $this->processOneTimePin();
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath("
            //div[contains(@class, 'alert-error')]/div
            | //a[contains(@href, 'logout')]
            | //p[contains(text(), 't complete your sign in right now. Please call us on')]
            | //button[contains(@class, 'mfa-registrationinterrupt-notnow')]
            | //p[@id = 'sms-code-sent']
            | //h1[contains(text(), 'Forgot customer ID or password')]
        "), 10);
        $this->saveResponse();

        if ($notnow = $this->waitForElement(WebDriverBy::xpath("//button[contains(@class, 'mfa-registrationinterrupt-notnow')]"), 0)) {
            $notnow->click();
            $this->waitForElement(WebDriverBy::xpath("
                //div[contains(@class, 'alert-error')]/div
                | //a[contains(@href, 'logout')]
                | //p[contains(text(), 't complete your sign in right now. Please call us on')]
            "), 10);
            $this->saveResponse();
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Forgot customer ID or password')]")) {
            throw new CheckException("The details entered don't match those on our system.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->processOneTimePin()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'alert-error')]/div")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'The details entered don\'t match those on our system.')
                || strstr($message, 'Please enter a valid 8 digit Customer ID')
                || strstr($message, 'Please enter your Customer ID using a valid format')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");

        // me by account lock
        if ($currentUrl == 'https://banking.westpac.com.au/wbc/banking/initiatesecurelogin') {
            $this->throwProfileUpdateMessageException();
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We can\'t complete your sign in right now. Please call us on")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://banking.westpac.com.au/secure/banking/targetmarketing/offers");
        $this->waitForElement(WebDriverBy::xpath("
            //h3[@id = 'rewards']
            | //p[contains(text(), 'You do not have any products which are eligible for the Westpac Rewards & Offers program.')]
        "), 10);
        $this->saveResponse();

        // Balance - Altitude Reward Points
        if (!$this->SetBalance($this->http->FindSingleNode("//h3[@id = 'rewards']", null, true, "/:\s*(.+)/"))) {
            if (
                $this->http->FindSingleNode("//p[
                    contains(text(), 'You do not have any products which are eligible for the Westpac Rewards & Offers program.')
                    or contains(text(), 't have any offers at this time. Please check back later.')
                ]")
                || in_array($this->AccountFields['Login'], [
                    '05364861',
                    '13055108',
                    '19872035',
                    '55953020',
                    '74742162',
                ])
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindSingleNode('//h1[contains(text(), "Change password")]')) {
                $this->throwProfileUpdateMessageException();
            }
        }

        // Name
        $this->http->GetURL("https://banking.westpac.com.au/secure/banking/manage/settings/#account-settings");
        $this->waitForElement(WebDriverBy::xpath("//span[contains(@class, 'profile-name')]"), 5);
        $this->saveResponse();
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@class, 'profile-name')]")));
    }

    protected function processOneTimePin()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question (2fa)', ['Header' => 3]);

        $question = $this->waitForElement(WebDriverBy::xpath('//p[@id = "sms-code-sent"]'), 0);
        $this->saveResponse();

        if (!$question) {
            $this->logger->error("question not found");

            return false;
        }

        $otp = $this->waitForElement(WebDriverBy::xpath('//input[@name="AuthorisationCode"]'));
        $authoriseBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Authorise")]'));

        if (!$otp || !$authoriseBtn) {
            $this->logger->error("something went wrong");

            return false;
        }

        $questionValue = $question->getText();

        if (!isset($this->Answers[$questionValue])) {
            $this->holdSession();
            $this->AskQuestion($questionValue, null, "2fa");

            return true;
        }// if (!isset($this->Answers[$question]))

        $answer = $this->Answers[$questionValue];
        unset($this->Answers[$questionValue]);

        $otp->sendKeys($answer);
        $this->saveResponse();
        $authoriseBtn->click();

        // The code you entered is incorrect. Please enter your Identification Code again exactly as you received it
//        $error = $this->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'The code you entered is incorrect.')]"), 5);
        sleep(5); //todo
        $this->saveResponse();
//
//        if (!empty($error)) {
//            $error = $error->getText();
//            $this->logger->notice("error: " . $error);
//            $this->holdSession();
//            $this->AskQuestion($q, $error, "IdentificationCode");
//
//            return false;
//        }// if (!empty($error))

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")
            || $this->http->FindSingleNode("//img[@alt = 'Sign Out With Cart']/@alt")
        ) {
            return true;
        }

        return false;
    }
}
