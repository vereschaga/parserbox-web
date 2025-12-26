<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSobeys extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyDOP());
        }
    }

    public function LoadLoginForm()
    {
        // reset cookie
        $this->http->removeCookies();
        $this->http->setCookie("sobeys_comp", "lang=en&prov=BC&reg=west", "www.clubsobeys.com");
        $this->http->getURL("https://www.clubsobeys.com/Home.aspx");
        // parsing form on the page
        if (!$this->http->ParseForm("form1")) {
            return $this->checkErrors();
        }
        $this->http->Form["formids"] = "userName,password,saveLoginId,ImageSubmit";
        $this->http->Form["submitmode"] = "submit";
        $this->http->Form["submitname"] = "";
        $this->http->SetInputValue("userName", $this->AccountFields["Login"]);
        $this->http->SetInputValue("password", $this->AccountFields["Pass"]);
        $this->http->FormURL = "https://www.clubsobeys.com/member/LoginFrame,\$LoginComponent.\$Form.sdirect";

        return true;
    }

    public function checkErrors()
    {
        // As of July 1, 2015, the Club Sobeys Program has come to an end.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'As of July 1, 2015, the Club Sobeys Program has come to an end.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Site Maintenance in Progress
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently trying to improve our site')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // This page is temporarily unavailable due to scheduled maintenance.
        if ($message = $this->http->FindPreg("/(This page is temporarily unavailable due to scheduled maintenance\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // form submission
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindSingleNode("//div[@id='error.login-invalid']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The card number associated to this account is not active
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'The card number associated to this account is not active')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // login successful
        if ($this->http->FindSingleNode("//a[@id='logout']")) {
            return true;
        }
        // For security reasons, you have been temporarily blocked from accessing your account online.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'For security reasons, you have been temporarily blocked')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Access Denied. Please Note: The Club Sobeys program has closed in your area.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Access Denied. Please Note: The Club Sobeys program has closed in your area.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // set Balance
        $this->SetBalance($this->http->FindSingleNode("//span[@class='points' and not(@id='uptodatepoints')]"));
        // set Up to date points
        $this->SetProperty("UpToDatePoints", $this->http->FindSingleNode("//span[@id='uptodatepoints']"));
        $this->http->GetURL("https://www.clubsobeys.com/member/MemberProfile.page");
        // set Account number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//h2[@class='cardnumber']", null, true, "/(\d+)/ims"));
        $fname = trim($this->http->FindSingleNode("//input[@name='firstName']/@value"));
        $lname = trim($this->http->FindSingleNode("//input[@name='lastName']/@value"));
        // set Name
        $this->SetProperty("Name", beautifulName("$fname $lname"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["SuccessURL"] = "https://www.clubsobeys.com/Home.aspx";
        $arg["CookieURL"] = "https://www.clubsobeys.com/Home.aspx";

        return $arg;
    }
}
