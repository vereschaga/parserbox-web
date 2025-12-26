<?php

class TAccountCheckerKestrelflyer extends TAccountCheckerExtended
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://member.airmauritius.com/dashboard';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
        $this->useFirefox();
        $this->setKeepProfile(true);

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 15);
        $passInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//input[@id = "kc-login"]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passInput || !$btn) {
            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passInput->sendKeys($this->AccountFields['Pass']);
        $this->driver->executeScript("let remember = document.getElementById('rememberMe'); if (remember) remember.checked = true;");
        $this->saveResponse();
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
        $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Kestrelflyer Number: ") and normalize-space(text()) != "Kestrelflyer Number:"] | //div[@class = "errorMessage"]'), 10);
        $this->saveResponse();

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[@class = "errorMessage"]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'We can\'t log you in. Make sure you provided correct information.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Available Miles
        $this->SetBalance($this->http->FindSingleNode('//label[contains(text(), "Available Miles")]/following-sibling::div'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[a[@href="/profile"]]/h2')));
        // Kestrelflyer Number
        $this->SetProperty("Number", $this->http->FindSingleNode('//div[contains(text(), "Kestrelflyer Number")]', null, true, "/:\s*(.+)/"));
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode('//div[contains(text(), "Member since")]', null, true, "/:\s*(.+)/"));
        // Tier
        $this->SetProperty("Tier", $this->http->FindSingleNode('//div[button[contains(text(), "View card")]]/preceding-sibling::div[1]/div'));
        // Qualifying Miles
        $this->SetProperty("QualifyingMiles", $this->http->FindSingleNode('//label[contains(text(), "Qualifying Miles")]/following-sibling::div'));
        // Expiring Miles
        $this->SetProperty("ExpiringBalance", $this->http->FindSingleNode('//label[contains(text(), "Expiring Miles")]/following-sibling::div', null, true, "/(.+) on /ims"));

        $expDate = $this->http->FindSingleNode('//label[contains(text(), "Expiring Miles")]/following-sibling::div', null, true, "/.+ on (.+)/ims");

        if (strtotime($expDate)) {
            $this->SetExpirationDate(strtotime($expDate));
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//div[contains(text(), "Kestrelflyer Number:") and normalize-space(text()) != "Kestrelflyer Number:"]')) {
            return true;
        }

        return false;
    }
}
