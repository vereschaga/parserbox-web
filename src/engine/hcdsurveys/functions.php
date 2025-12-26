<?php

// refs #1541

class TAccountCheckerHcdsurveys extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.hcdsurveys.com/panel/myaccount.cfm';

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['NoCookieURL'] = true;

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.hcdsurveys.com/panel/index.cfm');

        if (!$this->http->ParseForm("form1")) {
            return false;
        }
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("LoginButton", "Log In");

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Invalid Username and/or Password. Please try again.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode('//p[contains(text(),"Welcome back!")]/b'));
        // Balance
        $this->SetBalance($this->http->FindSingleNode('//span[contains(text(),"You have")]/span', null, true, '/(.*)/'));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
//        if ($this->http->FindPreg('/points in your account/')) {
        if ($this->http->FindSingleNode('//a[@href="logout.cfm"]')) {
            return true;
        }

        return false;
    }
}
