<?php

class TAccountCheckerMyhotspex extends TAccountChecker
{
    public function LoadLoginForm()
    {
        // reset cookie
        $this->http->removeCookies();
        // getting the HTML code
        $this->http->getURL('http://myhotspex.com/member/login.aspx');
        // parsing form on the page
        if (!$this->http->ParseForm('form1')) {
            return false;
        }
        // enter the login and password
        $this->http->SetInputValue("txtEmail", $this->AccountFields["Login"]);
        $this->http->SetInputValue("txtPassword", $this->AccountFields["Pass"]);
        $this->http->Form['btnLogin'] = 'LOG IN';

        return true;
    }

    public function Login()
    {
        // form submission
        if (!$this->http->PostForm()) {
            return false;
        }
        // check for invalid password
        if ($message = $this->http->FindSingleNode("//span[@id='lblopps']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // login successful
        if ($this->http->FindSingleNode("//input[@id='tLogin_btnLogout']/@id")) {
            return true;
        }

        //# The Hotspex account associated with this email address is currently Pending
        if ($this->http->currentUrl() == 'http://myhotspex.com/member/pendingaccount.aspx') {
            throw new CheckException("myHotspex.com website is asking you to complete your registration, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL('http://myhotspex.com/surveys/');
        // set Surveys Completed
        $this->SetProperty("Surveys", $this->http->FindSingleNode("//span[@id='PageBody_lblMySurveyCount']"));
        // set Trees Planted
        $this->SetProperty("Trees", $this->http->FindSingleNode("//span[@id='PageBody_lblMyTreeCount']"));
        // set Spexmail
        $this->SetProperty("Spexmail", $this->http->FindSingleNode("//span[@id='tLogin_lblEmailValue']"));
        // set Balance
        $this->SetBalance($this->http->FindSingleNode("//span[@id='tInfo_lblBalanceValue']"));
        $this->http->GetURL('http://myhotspex.com/profile/');
        // set Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@id='PageBody_txtfname']/@value") . " " .
        $this->http->FindSingleNode("//input[@id='PageBody_txtlname']/@value")));
    }
}
