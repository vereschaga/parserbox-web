<?php

class TAccountCheckerInsidehotels extends TAccountChecker
{
    private $parseUrl = 'https://www.insidehotels.com/account/rewards';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->parseUrl, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.insidehotels.com/login');

        if (!$this->http->ParseForm(null, 1, true, '//form[contains(@action, "/login")]')) {
            return false;
        }

        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[@role = "alert" and contains(@class, "alert")]')) {
            if ($message == 'An invalid email or password was specified. Please try again.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Sorry that email address was not found. Please check your login information and try again.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->logger->error($message);
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != $this->parseUrl) {
            $this->http->GetURL($this->parseUrl);
        }

        // Balance
        $this->SetBalance($this->http->FindSingleNode(
            '//h2[contains(text(), "Current Rewards Balance:")]',
            null,
            true,
            '/:\s[$]([0-9.]+)/ims'
        ));
        // Name
        $this->http->GetURL('https://www.insidehotels.com/account/billing-info');
        $this->SetProperty('Name', beautifulName(
            $this->http->FindSingleNode('//input[@name="first_name"]/@value')
            . ' ' .
            $this->http->FindSingleNode('//input[@name="last_name"]/@value')
        ));
        // Notification
        $this->http->GetURL('https://www.insidehotels.com/account/bookings');

        if (!$this->http->FindSingleNode('//div[contains(text(), "Sorry, you don\'t have any active bookings.")]')) {
            $this->sendNotification('insidehotels - refs #17068. Bookings found');
        }
    }

    private function loginSuccessful()
    {
        if ($this->http->FindSingleNode('//li[@class = "hidden-xs"]/a[contains(@href, "logout")]')) {
            return true;
        }

        return false;
    }
}
