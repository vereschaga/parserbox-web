<?php

class TAccountCheckerJoyoflife extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.joyoflifeclub.com/");

        if (!$this->http->ParseForm("loginform")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('Login', $this->AccountFields['Login']);
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('LoginButton', "sign in");

        return true;
    }

    public function checkErrors()
    {
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# We’re sorry that you can’t reach our site
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\’re sorry that you can\’t reach our site")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Error 503 Service Unavailable
        if ($message = $this->http->FindPreg("/(Error 503 Service Unavailable)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# An unexpected error has occurred. Please contact the webmaster.
        if ($message = $this->http->FindPreg("/(An unexpected error has occurred. Please contact the webmaster.)/ims")) {
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
        if ($this->http->FindSingleNode("//form[contains(@action, 'logout')]/@action")) {
            return true;
        }

        //# Invalid credentials
        if ($message = $this->http->FindSingleNode("//div[@class='warning']/ul/li[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->SetBalance($this->http->FindSingleNode("//div[@id='current-points']"));
        $this->SetProperty('JoyPoints', $this->http->FindSingleNode("//div[@id='current-points']"));
        // Member Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//div[@class='wrapper-sign-in']/h3"));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//h4[@class='pink-text']", null, true, '/joy (.*)/ims'));
        // # of nights
        $this->SetProperty("NumberOfNights", $this->http->FindSingleNode("//div[@class='of-nignts']/div[@class='data-of-user']"));
        // Joy ID
        $this->SetProperty("Number", $this->http->FindSingleNode("//div[@class='wrapper-sign-in']/div[@class='user-id']", null, true, "/(\d+)/ims"));
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//div[@class='member-since']/div[@class='data-of-user']")); // Program Since

        // expiration date
        $expirationDate = null;
        $d = $this->http->FindSingleNode("//div[@class='points-expiring']/text()[1]", null, true, '/expiring on ([\d\/]+)/ims');

        if (isset($d)) {
            $d = strtotime($d);

            if ($d !== false) {
                $expirationDate = $d;
            }
        }

        if (isset($expirationDate)) {
            $this->SetExpirationDate($expirationDate);
        }

        // site bug
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindSingleNode("//div[@class='wrapper-sign-in']/div[@class='user-id']") == 'Joy ID:') {
            $this->SetBalanceNA();
        }
    }
}
