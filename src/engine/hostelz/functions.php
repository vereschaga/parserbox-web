<?php

class TAccountCheckerHostelz extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->useGoogleChrome();
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL("https://secure.hostelz.com/user/settings/points");

        $loginInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'userEmail']"), 5);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'userPassword']"), 0);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Log In')]"), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }

        $loginInput->clear();
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->clear();
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $button->click();

        $success = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "login-dropdown-avatar")] | //div[contains(@class, "alert-warning")]'), 15);
        $this->saveResponse();
        /*
        $csrf = $this->http->getCookieByName("XSRF-TOKEN");

        if (!$this->http->ParseForm(null, "//form[contains(@class, 'login-form')]") || !$csrf) {
            return $this->checkErrors();
        }


        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
            "remember" => true,
        ];
        $headers = [
            "Accept"           => "application/json, text/plain, *
        /*",
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type"     => "application/json",
            "X-XSRF-TOKEN"     => $csrf,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.hostelz.com/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        */

        return true;
    }

    public function checkErrors()
    {
        // System maintenance
        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

//        if (!$this->http->PostForm()) {
//            return $this->checkErrors();
//        }
        // Access is allowed
        if ($this->http->FindNodes('//div[contains(@class, "login-dropdown-avatar")]/@class')) {
//        if ($this->http->FindNodes("//a[contains(@href,'/logout')]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-warning")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Unknown or invalid email address')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://secure.hostelz.com/user/settings/points");
        // Balance - ... points
        $this->SetBalance($this->http->FindSingleNode("//h3[contains(text(), 'Your current Hostelz.com points')]/strong"));

        // Get page with names
        $this->http->GetURL("https://secure.hostelz.com/user/settings/settings");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@name='data[name]']/@value")));
    }
}
