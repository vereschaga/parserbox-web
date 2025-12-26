<?php

class TAccountCheckerShoebuy extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.shoes.com/account-loyalty';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
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
        // Login must be correct email
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            $this->logger->info("invalid email");

            throw new CheckException('Invalid e-mail and/or password', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.shoes.com/login');

        if (!$this->http->ParseForm('login-form')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('loginEmail', $this->AccountFields['Login']);
        $this->http->SetInputValue('loginPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('loginRememberMe', "true");

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog();

        if (isset($response->success) && $response->success == true) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $message = $response->error[0] ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message === 'Invalid login or password. Remember that password is case-sensitive. Please try again.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Current Points
        $this->SetBalance($this->http->FindSingleNode('//p[contains(text(), "Current Points:")]', null, true, "/\:\s*(.+)/"));
        // Points to Next Reward
        $this->SetProperty('NextReward', $this->http->FindSingleNode('//div[contains(@class, "account-loyalty__progressbar-label")]/p[contains(text(), "point")]', null, true, "/(.+)\spoint/ims"));
        // Reward Amount Available
        $this->SetProperty('RewardAvailable', $this->http->FindSingleNode('//p[contains(text(), "Reward Amount Available:")]', null, true, "/\:\s*(.+)/"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//p[contains(text(), "Welcome")]', null, true, "/Welcome\s*(.+)/")));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(@href, "Logout")]/@href')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
