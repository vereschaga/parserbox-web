<?php

class TAccountCheckerGotobus extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.gotobus.com/app/member/account';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
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
        $this->http->GetURL('https://www.gotobus.com/app/user-login.htm?p=%2Fapp%2Fmember%2Faccount&email=');

        if (!$this->http->ParseForm('login_form')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('pass1', $this->AccountFields['Pass']);
        $this->http->SetInputValue('remeberMe', "1");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindPreg("#var redirectUrl = '/app/member/account';#")) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $message = $this->http->FindSingleNode('//small[@id = "errorMessage"]');

        if ($message) {
            $this->logger->error($message);

            if (strpos($message, 'Invalid login/password!') !== false) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->RetryCount = 0;
        // Wallet Balance
        $this->SetProperty('WalletBalance', $this->http->FindSingleNode('//li[contains(text(), "My Wallet")]/a'));

        $firstName = $this->http->getCookieByName("IvyCustomer_FirstName", ".gotobus.com");
        $lastName = $this->http->getCookieByName("IvyCustomer_LastName", ".gotobus.com");
        $this->SetProperty('Name', beautifulName(urldecode($firstName . " " . $lastName)));

        if (!isset($firstName, $lastName)) {
            $firstName = $this->http->getCookieByName("IvyCustomer_Uid", ".gotobus.com");
            $this->SetProperty('Name', urldecode($firstName));
        }
        // My Points
        $this->http->GetURL("https://www.gotobus.com/cgi-bin/ce.fcgi?a=view_point");
        // Available Points - balance
        $this->SetBalance($this->http->FindSingleNode('//tr[th[normalize-space() = "Available Points"]]/td'));
        // Pending Points
        $this->SetProperty('PendingPoints', $this->http->FindSingleNode('//tr[th[normalize-space() = "Pending Points"]]/td'));
        // YTD Points: (Points Accumulated Over The Past Year)
        $this->SetProperty('YTDPoints', $this->http->FindSingleNode('//tr[th[normalize-space() = "Points Accumulated Over The Past Year"]]/td'));
        // Lifetime earned: (The History Of The Accumulation Of Points)
        $this->SetProperty('LifetimeEarned', $this->http->FindSingleNode('//tr[th[normalize-space() = "The History of The Accumulation of Points"]]/td'));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//li[contains(text(), "My Wallet")]/a')
            && $this->http->FindSingleNode('//li[contains(text(), "My Available Points:")]')
        ) {
            return true;
        }

        return false;
    }
}
