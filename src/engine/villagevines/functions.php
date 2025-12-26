<?php

class TAccountCheckerVillagevines extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://savored.com/login/");

        if (!$this->http->ParseForm(null, 1, true, "//form[@action='/login/']")) {
            return false;
        }

        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();

        $access = $this->http->FindSingleNode("//a[contains(@href, '/logout/')]");

        if (isset($access)) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'erer-box')]/div[@class='holder']/p")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//ul[contains(@class, 'sub-nav')]/li[1]", null, true, "/^Hi, (.+)/ims"));

        //# Balance
        $this->SetBalance($this->http->FindSingleNode("//ul[contains(@class, 'sub-nav')]/li[2]", null, true, "/([\d\.\,]+)/ims"));

        //# Full Name
        $this->http->GetURL("http://savored.com/myaccount/?settings=1");

        if ($name = $this->http->FindSingleNode("//*[@id= 'name']")) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://savored.com/login/';
        //	$arg['SuccessURL'] = 'http://savored.com/nyc/';
        return $arg;
    }
}
