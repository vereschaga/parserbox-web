<?php

class TAccountCheckerPepsipointsSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->AccountFields['BrowserState'] = null;
        $this->InitSeleniumBrowser();
        $this->disableImages();
        $this->useChromium();
        $this->keepCookies(false);
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://pass.pepsi.com/log-in?__locale__=en");

        $formXpath = "//form[@id = 'log-in']";
        $login = $this->waitForElement(WebDriverBy::xpath($formXpath . "//input[@name = 'email_address']"), 60, true);
        $this->saveResponse();

        if (empty($login)) {
            $this->logger->error('Failed to find "email" input');

            return $this->checkErrors();
        }

        if (!$this->http->ParseForm(null, 1, true, $formXpath)) {
            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);

        $passwordInput = $this->waitForElement(WebDriverBy::xpath($formXpath . "//input[@name = 'password']"));

        if (!$passwordInput) {
            $this->logger->error('Failed to find "password" input');

            return false;
        }
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        $submitButton = $this->waitForElement(WebDriverBy::xpath($formXpath . "//button[@type = 'submit']"));

        if (!$submitButton) {
            $this->logger->error('Failed to find "submit" button');

            return false;
        }
        $submitButton->click();

        return true;
    }

    public function checkErrors()
    {
        return false;
    }

    public function Login()
    {
        $this->saveResponse();

        $logout = $this->waitForElement(WebDriverBy::xpath("//span[@class = 'redeemable-points']"), 10);
        $this->saveResponse();

        if (!$logout) {
            $logout = $this->waitForElement(WebDriverBy::xpath("//span[@class = 'points']"), 5);
        }

        if ($logout) {
            return true;
        }
        // The login information was incorrect.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The login information was incorrect.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Your Redeemable Points
        if (!$this->SetBalance($this->http->FindSingleNode("//span[@class = 'redeemable-points']"))) {
            $this->SetBalance($this->http->FindSingleNode("//span[@class = 'points']"));
        }
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//span[@class = 'full_name']"));
        // Your Level
        $this->SetProperty("Status", $this->http->FindSingleNode("//div[@class = 'module module-level']/div[@class = 'text']/h3"));
    }
}
