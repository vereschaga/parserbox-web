<?php

class TAccountCheckerSurinam extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://reservations.flyslm.com/ibe/loyalty/myTickets/upcomingFlights';

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
        $this->http->GetURL('https://reservations.flyslm.com/ibe/loyalty?&lang=en');

        if (!$this->http->ParseForm(null, "//form[contains(@action, '/ibe/loyalty')]")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('isRemember', 'on');
        $this->http->SetInputValue('button', 'login');

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
            return $this->checkErrors();
        }
        // login successful
        if ($this->loginSuccessful()) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            return true;
        }

        if ($message = $this->http->FindSingleNode("//p[@id = 'errorModalText']")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Please check your credentials and try again.')
                || strstr($message, 'The account will be locked if one more login try fails')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'The account is locked') {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//span[@class = "loyalty-cover-name"]')));
        // Number
        $this->SetProperty('AccountNumber', $this->http->FindSingleNode('//span[@class = "loyalty-cover-value"]'));
        // Loyalty Member
        $this->SetProperty('Tier', $this->http->FindSingleNode('//span[@class = "loyalty-cover-title"]'));

        if (!$this->http->FindSingleNode('//h3[contains(text(), "Looks like you don\'t have any upcoming flights yet.")]')) {
            $this->sendNotification("future itineraries were found");
        }

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && !empty($this->Properties['Name'])
            && !empty($this->Properties['AccountNumber'])
            && !empty($this->Properties['Tier'])
        ) {
            $this->SetBalanceNA();
        }
    }

    private function loginSuccessful()
    {
        if ($this->http->FindSingleNode("//a[@id = 'logout-button']")) {
            return true;
        }

        return false;
    }
}
