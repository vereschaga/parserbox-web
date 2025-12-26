<?php

class TAccountCheckerPullups extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->http->GetURL('https://www.pull-ups.com/ign_in.aspxaspx');
        $this->http->FilterHTML = false;
        $this->http->GetURL('http://www.pull-ups.com/na/auth.sso');

        if ($this->http->ParseForm("samlform")) {
            $this->http->PostForm();
        }

        if (!$this->http->ParseForm(null, 1, true, "//form[@action = '/signin.sso?ReturnUrl=http://www.pull-ups.com/na/registration_complete.aspx']")) {
            return false;
        }
        $this->http->FormURL = 'https://www.pull-ups.com/na//signin.sso?ReturnUrl=http://www.pull-ups.com/na/registration_complete.aspx';
        $this->http->SetInputValue('consumer_email', $this->AccountFields['Login']);
        $this->http->SetInputValue('consumer_password', $this->AccountFields['Pass']);
        $this->http->Form['consumer_rememberme'] = 'true';

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->ParseForm("samlform")) {
            $this->http->PostForm();
        }

        if ($this->http->FindSingleNode("//a[contains(@href, 'signout')]/@href")) {
            return true;
        }

        //# You have not setup your security questions yet
        if ($message = $this->http->FindSingleNode('//p[contains(text(),"You have not setup your security questions yet.")]')) {
            throw new CheckException("You have not setup your security questions yet.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        //# Balance - Points Balance
        $this->SetBalance($this->http->FindSingleNode("//div[@id = 'points']/span"));
        //# Name
        $this->SetProperty("Name", $this->http->FindPreg("/Hi\s*<![^>]+>([^<]+)/ims"));
    }
}
