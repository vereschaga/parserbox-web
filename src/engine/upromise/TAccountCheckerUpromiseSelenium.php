<?php

// refs #16995

use AwardWallet\Engine\ProxyList;

class TAccountCheckerUpromiseSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useGoogleChrome();
        //$this->disableImages();
        $this->useCache();
        $this->http->saveScreenshots = true; //todo: debug
        $this->http->SetProxy($this->proxyDOP());
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['cookies'])) {
            return false;
        }
        $this->http->GetURL('https://www.upromise.com');
        $this->driver->manage()->deleteAllCookies();

        foreach ($this->State['cookies'] as $cookie) {
            $this->logger->debug("{$cookie['name']}={$cookie['value']}, {$cookie['domain']}");
            $this->driver->manage()->addCookie([
                'name'   => $cookie['name'],
                'value'  => $cookie['value'],
                'domain' => $cookie['domain'],
            ]);
        }
        $this->http->GetURL('https://www.upromise.com');

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL('https://www.upromise.com/login/');

        if (!$this->http->ParseForm("signinForm")) {
            return $this->checkErrors();
        }
        $email = $this->waitForElement(WebDriverBy::id("email"), 10);
        $password = $this->waitForElement(WebDriverBy::id("password"), 0);
        $btn = $this->waitForElement(WebDriverBy::id("loginBtn"), 0);

        if (!$email || !$password || !$btn) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }
        $email->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);
        $btn->click();

        $iframe2 = $this->waitForElement(WebDriverBy::xpath("//iframe[@title = 'recaptcha challenge']"), 10, true);
        $this->saveResponse();

        if ($iframe2) {
            $this->http->Log('Failed to pass captcha');
            // retries
            throw new CheckRetryNeededException(2, 7, self::CAPTCHA_ERROR_MSG);
        }// if ($iframe2)

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "The Upromise website is temporarily down for scheduled maintenance.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are sorry but the application that you are trying to access is unavailable at this time.
        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "We are sorry but the application that you are trying to access is unavailable at this time.")]')) {
            throw new CheckException('We are sorry but the application that you are trying to access is unavailable at this time. Please try again later.', ACCOUNT_PROVIDER_ERROR);
        }
        // We are down for maintenance. Sorry for the inconvenience. We'll be back shortly.
        if ($message = $this->http->FindSingleNode('//td[span[normalize-space() = "https://upromise.force.com is under construction"]]')) {
            throw new CheckException("We are down for maintenance. Sorry for the inconvenience. We'll be back shortly.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        $sleep = 20;
        $startTime = time();
        sleep(3);

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
            $this->saveResponse();

            if ($this->loginSuccessful()) {
                return true;
            }
            // Invalid credentials
            if ($message = $this->waitForElement(WebDriverBy::xpath("
                    //div[contains(text(),'Your login attempt has failed. Make sure the username and password are correct.')]
                    | //div[contains(text(), 'Your access is disabled. Contact your site administrator')]
                    | //p[contains(text(), 'That email and password combination does not match our records.')]
                "), 0)
            ) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            /* There is a problem accessing your Upromise account. Please contact the Support team at support@upromise.com from the email address associated with your account.
            || We can't send you a verification code right now. Please try again later.*/
            if ($message = $this->waitForElement(WebDriverBy::xpath("
                    //div[contains(text(), 'There is a problem accessing your Upromise account.')]
                    | //p[contains(text(), \"We can't send you a verification code right now. Please try again later.\")]
                    | //div[contains(text(), 'Please enter a valid email address')]
            "), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            // Update your password
            $error = $this->waitForElement(WebDriverBy::xpath("
                //div[contains(text(),'Please click on the \"Update your password\" link to setup your new password')]
                | //h2[contains(text(), 'Change Your Password')]
            "), 0);

            if ($error) {
                $this->throwProfileUpdateMessageException();
            }

            if ($this->http->FindSingleNode("//h1[contains(text(), 'How would you like to confirm your identity?')]")) {
                $next = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Next')]"), 0);

                if (!$next) {
                    return false;
                }
                $next->click();
                $this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Verify Your Identity')]"), 3);
                $this->saveResponse();
            }

            if ($this->parseQuestion()) {
                if ($this->loginSuccessful()) {
                    return true;
                }

                return false;
            }

            sleep(1);
            $this->saveResponse();
        }// while ((time() - $startTime) < $sleep)

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'Question') {
            return $this->parseQuestion();
        }

        return true;
    }

    public function Parse()
    {
        $this->State['cookies'] = $this->driver->manage()->getCookies();

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $pending = $this->waitForElement(WebDriverBy::xpath('//div[@class = "flex-container" and .//div[normalize-space(text()) = "Pending Rewards"]]//div[@class = "sb-amount"]'), 5);
        $this->saveResponse();
        $name = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'account-fullname']"), 0);

        if (!$pending || !$name) {
            return;
        }
        $this->SetProperty('Name', beautifulName($name->getText()));

        // Pending
        $pending = $this->http->FindPreg(self::BALANCE_REGEXP_EXTENDED, false, str_replace('$', '', $pending->getText()));
        $this->AddSubAccount([
            "Code"              => "upromisePending",
            "DisplayName"       => "Pending",
            "Balance"           => $pending,
            "BalanceInTotalSum" => true,
        ]);
        // Confirmed
        $earned = $this->waitForElement(WebDriverBy::xpath('//div[@class = "flex-container" and .//div[normalize-space(text()) = "Earned Rewards"]]//div[@class = "sb-amount"]'), 0);
        $earned = $this->http->FindPreg(self::BALANCE_REGEXP_EXTENDED, false, str_replace('$', '', $earned->getText()));
        $this->AddSubAccount([
            "Code"              => "upromiseEarned",
            "DisplayName"       => "Earned",
            "Balance"           => $earned,
            "BalanceInTotalSum" => true,
        ]);
        // Payable
        $transferred = $this->waitForElement(WebDriverBy::xpath('//div[@class = "flex-container" and .//div[normalize-space(text()) = "Transferred Rewards"]]//div[@class = "sb-amount"]'), 0);
        $transferred = $this->http->FindPreg(self::BALANCE_REGEXP_EXTENDED, false, str_replace('$', '', $transferred->getText()));
        $this->AddSubAccount([
            "Code"        => "upromiseTransferred",
            "DisplayName" => "Transferred",
            "Balance"     => $transferred,
        ]);

        // https://redmine.awardwallet.com/issues/19878#note-8
        if (isset($pending, $earned)) {
            $this->SetBalance(str_replace(',', '', $pending) + str_replace(',', '', $earned));
        }

        $this->saveResponse();
        // Total Rewards
        $totalTransferred = $this->waitForElement(WebDriverBy::xpath('//div[@class = "flex-container" and .//div[normalize-space(text()) = "Total Rewards"]]//div[@class = "sb-amount"]'), 0);

        if ($totalTransferred) {
            $this->SetProperty('TotalTransferred', $totalTransferred->getText());
        }

        $this->http->GetURL('https://www.upromise.com/profile');
        /*
        // Upromise® ID
        $text = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Upromise') and contains(., 'ID')]/following-sibling::span"), 10);
        $this->saveResponse();
        if ($text)
            $this->SetProperty('Number', $text->getText());
        */
        // Member since
        $text = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Member since')]/following-sibling::div"), 10);
        $this->saveResponse();

        if ($text) {
            $this->SetProperty('MemberSince', $text->getText());
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $logout = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'account-lifetime-amount']"), 10);
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        return false;
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        $question = $this->http->FindSingleNode("//text()[contains(.,'Enter the verification code we emailed to ')] | //h2[contains(text(), 'Enter your verification code')]");
        $this->logger->debug("Question -> {$question}");

        if (empty($question)) {
            return false;
        }
        $question = trim($question);

        if (empty($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, 'Question');

            return true;
        }
        $emc = $this->waitForElement(WebDriverBy::xpath('//input[@id = "emc" or contains(@id, ":codeInput")]'), 2);
        $btn = $this->waitForElement(WebDriverBy::xpath('//input[@id = "save"] | //a[@onclick="crtCookies()" and contains(text(), "Next")]'), 0);

        if (!$emc || !$btn) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return true;
        }
        $emc->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);
        $btn->click();

        $this->waitForElement(WebDriverBy::xpath('
            //div[contains(text(), "Invalid or expired verification code. Try again.")]
            | //label[contains(text(), "Please enter correct verification code.")]
            | //h3[normalize-space(text()) = "Pending"]/ancestor::div[1]//lightning-formatted-number
            | //h2[contains(text(), "Change Your Password")]
        '), 7);
        $this->saveResponse();

        $error = $this->waitForElement(WebDriverBy::xpath('
            //div[contains(text(), "Invalid or expired verification code. Try again.")]
            | //label[contains(text(), "Please enter correct verification code.")]
        '), 0);

        if ($error) {
            $this->holdSession();
            $this->AskQuestion($error->getText(), null, 'Question');
            $this->logger->debug('answer was wrong');

            return true;
        }
        // Change Your Password
        if ($this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Change Your Password")]'), 0)) {
            $this->throwProfileUpdateMessageException();
        }

        return true;
    }
}
