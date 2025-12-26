<?php

class TAccountCheckerGreenopolis extends TAccountChecker
{
    private $Logins = 0;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->getURL('http://greenopolis.com/');

        if (!$this->http->ParseForm('user-login-form')) {
            return $this->CheckErrors();
        }
        $this->http->SetInputValue('name', $this->AccountFields['Login']);
        $this->http->SetInputValue('pass', $this->AccountFields['Pass']);
        //$this->http->Form['__EVENTTARGET'] = 'btnMember';

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["NoCookieURL"] = true;

        return $arg;
    }

    public function CheckErrors()
    {
        //# Server Error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Server Error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        sleep(2);

        if (!$this->http->PostForm()) {
            return $this->CheckErrors();
        }
        //# Access successful
        if ($message = $this->http->FindSingleNode("//div[contains(@class,'message') and contains(@class,'error')]/text()[1]")) {
            //# Login Error
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class,'message')]/text()[contains(.,'success')]")) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        $this->http->getURL('http://greenopolis.com/node/add/userprofile');
        //# Balance - Total Points
        $balance = $this->http->FindSingleNode("//div[@id='control_panel_point_value']/text()", null, true, '/(\d+)\s*Points/i');
        $this->SetBalance($balance);
    }
}
