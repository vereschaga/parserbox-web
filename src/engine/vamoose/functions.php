<?php

class TAccountCheckerVamoose extends TAccountChecker
{
    private const REWARDS_PAGE_URL = "https://www.vamoosebus.com/site/modules/members/myaccount.aspx";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.vamoosebus.com/site/modules/members/login.aspx');

        if (!$this->http->ParseForm('form1')) {
            return $this->checkErrors();
        }

        $data = [
            "name"     => $this->AccountFields["Login"],
            "password" => $this->AccountFields["Pass"],
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-type"     => "application/json; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.vamoosebus.com/SITE/m/webService/member.asmx/Login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $status = $response->d ?? null;

        // Access is allowed
        if ($status === true) {
            return $this->loginSuccessful();
        }

        if ($status === false) {
            throw new CheckException("Login Failed! Please check the name and password and try again.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Points Balance
        $this->SetBalance($this->http->FindSingleNode('//div[contains(text(), "Points Balance:")]/span'));
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode('//div[@class="welcome_title"]', null, true, '/Welcome ([^\\(]*)/'));
        // Member ID
        $this->SetProperty("Number", $this->http->FindSingleNode('//div[@class = "welcome_member_id"]', null, true, '/ ID:\s*([\d]+)/'));
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Website is offline for maintenance
        if ($this->http->FindPreg("/(Our website is offline for maintenance)/ims")) {
            throw new CheckException("The website is offline for maintenance", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//span[contains(text(), 'Logout')]")) {
            return true;
        }

        return false;
    }
}
