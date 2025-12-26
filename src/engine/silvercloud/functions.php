<?php

class TAccountCheckerSilvercloud extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.silverrewards.com/members/");

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('emailMId', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue($this->http->FindSingleNode("//input[@class = 'cffp_mm']/@name"), "0");
        $this->http->SetInputValue($this->http->FindSingleNode("//input[@class = 'cffp_kp']/@name"), "3");

        return true;
    }

    public function checkErrors()
    {
        // Error Occurred While Processing Request
        if ($this->http->FindSingleNode("//h3[contains(text(), 'Error Occurred While Processing Request')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Access is allowed /*checked*/
        if ($this->http->FindSingleNode("//a[contains(text(), 'LOGOUT')]")) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'The login combination that you entered does not match our records')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Create Your Password
        if ($this->http->FindSingleNode("//h2[contains(text(), 'Create Your Password')]")) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Your current point
        $this->SetBalance($this->http->FindPreg("/Your current point balance is ([\d\,\.\-]+)/ims"));

        $this->http->GetURL("https://www.silverrewards.com/members/memberInfo.cfm?_=" . time() . date("B"));
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//p[contains(text(), 'Member Id:')]/following-sibling::p[1]/text()[1]"));
        // Silver Rewards Member Number
        $this->SetProperty("MemberNumber", $this->http->FindSingleNode("//p[contains(text(), 'Member Id:')]", null, true, "/:\s*([^<]+)/"));
    }

    // Setting 'SuccessURL' fails autologin in IE, so this code was commented
//    function GetRedirectParams($targetURL = NULL) {
//        $arg = parent::GetRedirectParams($targetURL);
//        $arg["SuccessURL"] = "https://www.silverrewards.com/silverrewardsmember/default.cfm";
//        return $arg;
//    }
}
