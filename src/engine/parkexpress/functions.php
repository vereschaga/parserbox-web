<?php

class TAccountCheckerParkexpress extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (empty($this->AccountFields['Pass'])) {
            throw new CheckException("To update this Park Express (St. Louis, MO) account you need to set the password. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_INVALID_PASSWORD);
        }/*review*/

        $this->http->GetURL("http://www.frequentparker.net/");
        $this->http->GetURL("https://www.theparkingspot.com/SpotLogin.aspx");

        if (!$this->http->ParseForm("form1")) {
            return false;
        }
        $this->http->SetInputValue('txtUsername', $this->AccountFields['Login']);
        $this->http->SetInputValue('txtpassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('btnLogin.x', '49');
        $this->http->SetInputValue('btnLogin.y', '21');

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        // redirect
        if ($this->http->FindPreg("/window\.parent\.location\.href = \'SpotclubMember\.aspx\'/")) {
            $this->http->Log("JS Redirect");
            $this->http->GetURL("https://www.theparkingspot.com/spotclubmember.aspx");

            if ($this->http->FindSingleNode("//a[contains(@href, 'SignOut')]/@href")) {
                return true;
            }
        }// if ($this->http->FindPreg("/window\.parent\.location\.href = \'SpotclubMember\.aspx\'/"))
        // Either your email address or password is incorrect.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Either your email address or password is incorrect.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Please Enter the correct password.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Please Enter the correct password.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@id, 'lblMemberName')]")));
        // Member Number
        $this->SetProperty("MemberNumber", $this->http->FindSingleNode("//span[contains(@id, 'lblMemberID')]"));
        // Balance - My Points
        $this->SetBalance($this->http->FindSingleNode("//span[contains(@id, 'lblAwardedPoint')]"));
    }
}
