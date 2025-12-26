<?php

class TAccountCheckerRewardone extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://rewardone.continental.com/roAccount.aspx");

        if (!$this->http->ParseForm("roSignIn")) {
            return false;
        }
        $this->http->Form['txtRewardOneNum'] = $this->AccountFields['Login'];
        $this->http->Form['txtRewardOnePIN'] = $this->AccountFields['Pass'];
        $this->http->Form['btnSignIn.x'] = '101';
        $this->http->Form['btnSignIn.y'] = '6';

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        //# Access is allowed /*checked*/
        if ($this->http->FindSingleNode("//a[contains(text(), 'Sign Out')]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//span[@class = 'Error' and @id = 'lblMsg']", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Error processing request
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'has been an error processing your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        //# Balance - Current Balance
        $this->SetBalance($this->http->FindSingleNode("//span[@id = 'lblPointBalance']"));
        //# Company Name
        $this->SetProperty("CompanyName", $this->http->FindSingleNode("//span[@id = 'LblCompanyName']")); // Company Name
        //# Account ID
        $this->SetProperty("AccountNumber", $this->AccountFields['Login']);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://rewardone.continental.com/roAccount.aspx";
        $arg["URL"] = "http://rewardone.continental.com/rosignin.aspx?status=Acct";

        return $arg;
    }
}
