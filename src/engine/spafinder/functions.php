<?php

class TAccountCheckerSpafinder extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        $this->http->GetURL("https://www.spafinder.com/account/clubspa/login.jsp");

        if (!$this->http->ParseForm("log-in-form")) {
            return false;
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        // Find error message
        if ($message = $this->http->FindSingleNode("//*[contains(text(),'Please try again')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Find logout link
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout.jsp')]/@href")) {
            return true;
        } else {
            return false;
        }
    }

    public function Parse()
    {
        // Balance - POINTS EARNED
        $this->SetBalance($this->http->FindSingleNode("//*[contains(text(), 'Points Earned')]/span"));
        // Points Needed - \d+ points away from
        $this->SetProperty("PointsNeeded", $this->http->FindSingleNode("//*[contains(text(), 'points away from')]", null, true, "/^([0-9]+)/"));
        // Name - Your Profile
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//*[contains(text(), 'Your Profile')]/following-sibling::div/ul/li[1]")));

        return true;
    }
}
