<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerWorldpoints extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const XPATH_LOGIN_SUCCESSFUL = '//span[contains(text(), "Total points")]';
    private const XPATH_ERROR = '//div[contains(@class, "error") and @style="display: block;"]';
    private const XPATH_TWO_FACTOR = '//p[contains(text(), "We have the following number on record for you")]';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->http->SetProxy($this->proxyUK());

        $this->useChromePuppeteer();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.heathrow.com/myheathrow/login?startURL=/myheathrow/s/");

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'signInName']"), 7);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 3);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "next"]'), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$btn) {
            $this->logger->error("something went wrong");

            return false;
        }

        $this->logger->debug("set login");
        $login->sendKeys($this->AccountFields['Login']);
        $this->logger->debug("set pass");
        $pass->click();
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $this->logger->debug("click btn");
        $btn->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath(
            self::XPATH_LOGIN_SUCCESSFUL
            .' | ' . self::XPATH_ERROR
            .' | ' . self::XPATH_TWO_FACTOR
        ), 30);
        $this->saveResponse();

        if ($this->http->FindSingleNode(self::XPATH_LOGIN_SUCCESSFUL)) {
            return true;
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode(self::XPATH_ERROR)) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Invalid username or password')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question =
            $this->http->FindSingleNode(self::XPATH_TWO_FACTOR)
            ?? $this->http->FindSingleNode("//label[contains(text(), 'Enter your code.')]")
        ;
        $email = $this->http->FindSingleNode("//div[@id = 'phoneNumbers']//div[contains(@class, 'number')]");

        if ($question && $email) {
            $question .= ' '. $email;
        }

        if (!$question) {
            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return true;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);
        $input = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'verificationCode' or @id = 'otpCode']"), 0);

        if (!$input) {
            $this->logger->error("Can't find answer input");

            return false;
        }

        $input->clear();
        $input->sendKeys($answer);
        $btn = $this->waitForElement(WebDriverBy::xpath("//button[(@id = 'verifyCode' or @id = 'continue') and not(@disabled)]"), 3);
        $this->saveResponse();

        if (!$btn) {
            $this->logger->error("Can't find button");

            return false;
        }

        $btn->click();
        $this->logger->debug("waiting an error...");
        $error = $this->waitForElement(WebDriverBy::xpath(self::XPATH_ERROR), 10);
        $this->saveResponse();

        if ($error) {
            $error = $error->getText();
            $this->logger->error("Error -> {$error}");

            if (strstr($error, 'The verification code you have entered does not match our records')) {
                $this->holdSession();
                $this->AskQuestion($question, $error, "Question");
            }

            return false;
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        switch ($step) {
            case "Question":
                return $this->parseQuestion();
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.heathrow.com/myheathrow/s/heathrow-rewards");
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "barcodeText")]'), 10);
        $this->saveResponse();

        if ($btn = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'tealium_ensCloseBanner']"), 0)) {
            $btn->click();
        }

        $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "TOTAL POINTS")]/following-sibling::div'), 10);
        $this->saveResponse();

        // Name
        $this->SetProperty("Name", beautifulName(implode(' ', $this->http->FindNodes('//div[contains(@class, "card-holder")]'))));
        // Card #
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode('//div[contains(@class, "barcodeText")]'));
        // YOUR PREMIUM TRACKER
        $this->SetProperty("PremiumTracker", $this->http->FindSingleNode('//div[contains(text(), "YOUR PREMIUM TRACKER")]/following-sibling::div'));

        $premiumMembership = $response->premiumMembership ?? null;

        if ($premiumMembership === true) {
            $this->SetProperty("Status", "Premium");
            // Current membership expires on
            $this->SetProperty("StatusExpiration", date("d.m.y", strtotime($response->endDate)));
        } elseif ($this->http->FindSingleNode('//div[contains(text(), "needed to reach Premium this year")]')) {
            $this->SetProperty("Status", "Member");
        } else {
            $this->sendNotification("need to check Status / StatusExpiration");
        }

        // Balance - TOTAL POINTS
        $this->SetBalance($this->http->FindSingleNode('//div[contains(text(), "TOTAL POINTS")]/following-sibling::div'));
        // Points expire: ...
        $date = $this->http->FindSingleNode("//div[contains(text(), 'Points expire:')]", null, true, "/Points expire:\s*(.+)/");

        if ($exp = strtotime($date)) {
            $this->SetExpirationDate($exp);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
//        $this->http->GetURL("https://lhrapi.heathrow.com/mp-api/member-portal/me", $this->headers);
//        $response = $this->http->JsonLog();
//
//        if (
//            isset($response->email, $response->cardNo)
//            && (
//                $response->cardNo == $login
//                || strtolower($response->email) == strtolower($login)
//            )
//        ) {
//            return true;
//        }

        return false;
    }
}
