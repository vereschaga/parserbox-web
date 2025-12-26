<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerMycokeSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->KeepState = true;

        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_100);

        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL("https://myaccount.us.coca-cola.com/edit-profile.html?redirect_uri=https://us.coca-cola.com/brand=undefined&activeTab=my-rewards");

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'signInName']"), 20);
        $this->saveResponse();

        if (empty($login)) {
            $this->logger->error('Failed to find "login" input');

            if ($this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Complete your account for a personalized experience.')]"), 0)) {
                $this->throwProfileUpdateMessageException();
            }

            return $this->checkErrors();
        }
        $login->sendKeys($this->AccountFields['Login']);

        $passwordInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 0);

        if (!$passwordInput) {
            $this->logger->error('Failed to find "password" input');

            return false;
        }
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        // remember me
        $this->driver->executeScript("document.querySelector('input[name = \"rememberMe\"]').checked = true;");

        $loginButton = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'next']"), 0);
        $this->saveResponse();

        if (!$loginButton) {
            $this->logger->error('Failed to find login button');

            return false;
        }
//        $loginButton->click();
        $this->driver->executeScript("document.querySelector('button[id = \"next\"]').click();");

        return true;
    }

    public function checkErrors()
    {
        // Maintenance
        if ($message = $this->http->FindSingleNode('//strong[contains(normalize-space(.), "My Coke Rewards is temporarily unavailable")]')) {
            throw new CheckException(trim(preg_replace('#\s+#i', ' ', $message)), ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//strong[contains(normalize-space(.), "This site is undergoing scheduled maintenance.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $logged = $this->waitForElement(WebDriverBy::xpath("
            //div[contains(@class, 'profile__menu--is-logged')]
            | //div[contains(@class, 'error')]/p
            | //p[contains(text(), 'Your sign-in was rejected by Fraud Protection.')]
            | //h2[contains(text(), 'For added security, we need to validate your phone number. A one time passcode will be sent via text to the number provided below.')]
        "), 10);
        $this->saveResponse();

        if (!$logged) {
            $this->http->GetURL("https://us.coca-cola.com/");
            $logged = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'profile__menu--is-logged')]"), 10);
            $this->saveResponse();

            return true;
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Your sign-in was rejected by Fraud Protection.')]")) {
            $this->DebugInfo = self::ERROR_REASON_BLOCK;
            $this->ErrorReason = self::ERROR_REASON_BLOCK;

            return false;
        }

        if ($this->processPhone()) {
            return false;
        }

        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error') and @style='display: block;']/p")) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'We don’t recognize your email address or password.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $input = $this->waitForElement(WebDriverBy::xpath('//input[@id = "phoneNumber"]'), 0);
        $this->saveResponse();

        if (
            !$input
            && (
            $this->waitForElement(WebDriverBy::xpath("
                    //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                    | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                    | //p[contains(text(), 'Health check')]
                "), 0)
            )
        ) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == '2fa') {
            $this->saveResponse();

            return $this->process2fa();
        }

        if ($step == 'QuestionPhone') {
            $this->saveResponse();

            return $this->processPhone();
        }

        return true;
    }

    public function process2fa()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode('//div[@id = "phoneVerificationControl_success_message"]');
        $questionInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "VerificationCode"]'), 0);
        $send = $this->waitForElement(WebDriverBy::xpath('//button[@id = "phoneVerificationControl_but_verify_code"]'), 0);
        $this->saveResponse();

        if (!$question || !$questionInput || !$send) {
            $this->logger->error("something went wrong");
            $error = $this->http->FindSingleNode('//div[@id = "wrongPhoneFormat" and contains(@class , "error")]');
            $this->logger->error("[Error]: {$error}");

            if (strstr($error, 'Please enter a valid 10-digit mobile number')) {
                $this->AskQuestion($question, $error, "QuestionPhone");
            }

            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "2fa");

            return false;
        }

        $questionInput->clear();
        $questionInput->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);
        $this->logger->debug("ready to click");
        $this->saveResponse();

        $this->logger->debug("clicking 'Verify Code'");
        $send->click();

        $res = $this->waitForElement(WebDriverBy::xpath('
            //button[contains(@class, "custom-submit-button") and contains(text(), "Continue")]
            | //div[@id = "phoneVerificationControl_error_message"]
        '), 7);
        $this->saveResponse();

        if ($res && strstr($res->getText(), 'Wrong code entered, please try again.')) {
            $this->holdSession();
            $this->AskQuestion($question, $res->getText(), "2fa");

            return false;
        }

        if ($contBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "custom-submit-button") and contains(text(), "Continue")]'), 0)) {
            $contBtn->click();
        }

        sleep(5); //todo
        $this->saveResponse();

        return true;
    }

    public function processPhone()
    {
        $this->logger->notice(__METHOD__);

        $question = $this->http->FindSingleNode("//h2[contains(text(), 'For added security, we need to validate your phone number. A one time passcode will be sent via text to the number provided below.')]");
        $questionInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "phoneNumber"]'), 0);
        $send = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Send Code")]'), 0);
        $this->saveResponse();

        if (!$question || !$questionInput || !$send) {
            $this->logger->error("something went wrong");

            return false;
        }

        if ($accept = $this->waitForElement(WebDriverBy::xpath('//button[@id = "accept-recommended-btn-handler"]'), 0)) {
            $accept->click();
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "QuestionPhone");

            return false;
        }

        $questionInput->clear();
        $questionInput->sendKeys($this->Answers[$question]);
        $this->logger->debug("ready to click");
        $this->saveResponse();

        $this->logger->debug("clicking Continue");
        $send->click();

        $this->waitForElement(WebDriverBy::xpath('//input[@id = "VerificationCode"]'), 5); //todo
        $this->saveResponse();

//        if ($error = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "error-text__error")]'), 0)) {
//            $message = $error->getText();
//            $this->logger->error("[Question Error]: {$message}");
//
//            if (strstr($message, 'That answer doesn\'t match. Please try again.')) {
//                unset($this->Answers[$question]);
//                $this->holdSession();
//                $this->AskQuestion($question, $message, "2fa");
//            }
//
//            return false;
//        }

        return $this->process2fa();
    }

    public function Parse()
    {
        $this->http->GetURL("https://myaccount.us.coca-cola.com/edit-profile.html?redirect_uri=https://us.coca-cola.com/brand=undefined&activeTab=my-rewards");
        $noRewards = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'You have no unclaimed rewards at the moment.')]"), 10);
        $this->saveResponse();

        if ($noRewards) {
            $this->SetBalanceNA();
        } elseif ($this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Complete your account for a personalized experience.')]"), 0)) {
            $this->throwProfileUpdateMessageException();
        }

        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);

            if ($cookie['name'] == 'tcccLogin_userData') {
                $tcccLogin_userData = $this->http->JsonLog(urldecode($cookie['value']));
                // Name
                $this->SetProperty('Name', beautifulName($tcccLogin_userData->givenName ?? null));
            }
        }
    }
}
