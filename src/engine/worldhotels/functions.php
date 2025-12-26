<?php

class TAccountCheckerWorldhotels extends TAccountChecker
{
    private $parseUrl = 'https://www.thelistrewards.com/member-rewards-and-stays';

    public function GetRedirectParams($targetURL = null)
    {
        $params = parent::GetRedirectParams($targetURL);
        $params['SuccessURL'] = 'https://www.thelistrewards.com/member-overview';

        return $params;
    }

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

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // down for maintenance
        if ($message = $this->http->FindSingleNode("//div[contains(text(),'down for maintenance')]")) {
            throw new CheckException('Down for maintenance', ACCOUNT_PROVIDER_ERROR);
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.thelistrewards.com/login');
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm('loginForm')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('profile_email', $this->AccountFields['Login']); // Email
        $this->http->SetInputValue('profile_password', $this->AccountFields['Pass']); // Password

        return true;
    }

    public function Login()
    {
        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        if (!$this->http->PostForm($headers)) {
            return false;
        }

        $data = $this->http->JsonLog(null, true, true);

        if (!is_array($data) || !array_key_exists('status', $data) || !array_key_exists('error', $data)) {
            return false;
        }

        if ($data['status'] == 1) {
            return true;
        }

        if ($data['error'] == 'WrongPassword') {
            throw new CheckException('Incorrect Password', ACCOUNT_INVALID_PASSWORD);
        }

        if ($data['error'] == 'UserNotExist') {
            throw new CheckException('The email address does not exist', ACCOUNT_INVALID_PASSWORD);
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
            '//a[contains(text(), "You have") and contains(text(), "available reward")]',
            null,
            false,
            '/You have ([0-9]+) available reward/ims'
        ));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode(
            '//div[@class = "header__my-account"]//select//option[@value = "/member-profile"]'
        )));

        if (!$this->http->FindSingleNode("//p[contains(text(), 'No Reward History Available')]")) {
            $this->sendNotification('worldhotels - refs #8382. Rewards found');
        }
    }

    private function loginSuccessful()
    {
        if ($this->http->FindSingleNode('//a[contains(@href, "/logout")]/@href')) {
            return true;
        }

        return false;
    }
}
