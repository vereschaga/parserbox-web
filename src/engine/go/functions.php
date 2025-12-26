<?php

class TAccountCheckerGo extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://ww3.dotres.com/meridia?posid=99G7");

        if (!$this->http->ParseForm("frmLogin")) {
            return $this->checkErrors();
        }
        $this->http->Form["accountID"] = $this->AccountFields['Login'];
        $this->http->Form["password"] = $this->AccountFields['Pass'];
        $this->http->Form["action"] = "doLogin";

        return true;
    }

    public function checkErrors()
    {
        // Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }
        //# Username or password is incorrect
        if ($message = $this->http->FindSingleNode("//span[@class = 'errorLogin']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Unable to retrieve your profile
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Unable to retrieve your profile')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[contains(text(), 'Welcome,')]", null, true, "/Welcome\,\s*([^!<]+)/ims")));
        //# Frequent Flyer #
        $this->SetProperty("Number", $this->http->FindSingleNode("//p[contains(text(), 'Frequent Flyer #')]", null, true, "/([\d]+)/ims"));
        //# Balance - Available Miles
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'Available Miles')]", null, true, "/Available\s*Miles\s*:\s*([\d\,\.]+)/ims"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://ww3.dotres.com/meridia?posid=99G7';
//        $arg['SuccessURL'] = '';
        return $arg;
    }
}
