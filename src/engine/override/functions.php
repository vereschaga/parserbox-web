<?php

// refs #1631

class TAccountCheckerOverride extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FormURL = 'http://www.override.com/Account/Login';
        $this->http->SetFormText('btnSubmit.x=22&btnSubmit.y=22&btnSubmit=Login', '&');
        $this->http->Form['LoginEmailAddressLogin'] = $this->AccountFields['Login'];
        $this->http->Form['LoginPasswordLogin'] = $this->AccountFields['Pass'];

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        $error = $this->http->FindSingleNode('//p[contains(text(),"We\'re sorry.")]');

        if (isset($error)) {
            $this->ErrorMessage = $error;
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;

            return false;
        }

        if (preg_match("/Welcome back,/ims", $this->http->Response['body'])) {
            return true;
        }

        if (preg_match("/Email address and password do not match./ims", $this->http->Response['body'])) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = "Your passcode could not be verified";

            return false;
        }

        if (preg_match("/Please enter your email address and password to log in/ims", $this->http->Response['body'])) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = "Your passcode could not be verified";

            return false;
        }

        return false;
    }

    public function Parse()
    {
        $this->http->getURL('https://www.override.com/Account/MyAccountSummary');
        $this->SetProperty("Name", $this->http->FindSingleNode('//div[@id="LoginWidget"]/span[1]', null, true, '/(.*)/'));
        $this->SetProperty("SavingsAvail", $this->http->FindSingleNode('//div[@class="SavingsAvail"]/big'));
        $this->SetBalance(/*"Earned"*/ $this->http->FindSingleNode('//div[@class="SavingsAvail"]/../ul/li[1]', null, true, '/Earned: (.*)\//'));
        $this->SetProperty("Redeemed", $this->http->FindSingleNode('//div[@class="SavingsAvail"]/../ul/li[2]', null, true, '/Redeemed: (.*)\//'));
        $this->SetProperty("Expired", $this->http->FindSingleNode('//div[@class="SavingsAvail"]/../ul/li[3]', null, true, '/Expired: (.*)\//'));
    }
}
