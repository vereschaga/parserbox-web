<?php

class TAccountCheckerAmbest extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useGoogleChrome();
        $this->KeepState = true;
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL("https://register.am-best.com/login");
        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'CardNumber']"), 5);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'PIN']"), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'submitBtn']"), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$btn) {
            return false;
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $btn->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Point Balance')] | //div[contains(@class, \"alert-danger\")]/span"), 10);
        $this->saveResponse();

        if ($this->http->FindSingleNode("//span[contains(text(), 'Point Balance')]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//font[contains(normalize-space(), "Card Number, ' . $this->AccountFields['Login'] . ', was not found.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-danger")]/span')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Invalid card number and/or pin.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return false;
    }

    public function Parse()
    {
        $this->waitForElement(WebDriverBy::xpath("//input[@id = 'FirstName']"), 10);
        $this->saveResponse();
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//b[contains(text(), 'Cardholder Information for')]/parent::td/following::td[1]")));
        // Total Purchases
        $this->SetProperty("Purchases", $this->http->FindSingleNode("//span[contains(text(), 'Total Purchases')]", null, true, "/\:\s*([^<]+)/"));
        // Total Redemptions
        $this->SetProperty("Redemptions", $this->http->FindSingleNode("//span[contains(text(), 'Total Redemptions')]", null, true, "/\:\s*([^<]+)/"));
        // Balance - Point Balance
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Point Balance')]/following::span[1]"));
    }
}
