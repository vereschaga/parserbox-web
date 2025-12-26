<?php

// refs #2021

class TAccountCheckerIslandair extends TAccountChecker
{
    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
//        $arg['CookieURL'] = "https://www.islandair.com/login-island-miles";

        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.islandair.com/login-island-miles');

        if ($this->http->Response['code'] == 404) {
            $this->http->GetURL('https://www.islandair.com/');

            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are no longer accepting new reservations at Island Air and will cease operations')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            } else {
                $this->http->GetURL('https://www.islandair.com/login-island-miles');
            }
        }

        if (!$this->http->ParseForm("login")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('island-miles-member-id', $this->AccountFields['Login']);
        $this->http->SetInputValue('island-miles-member-password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('login', "login");

        return true;
    }

    public function checkErrors()
    {
        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Success
        if ($this->http->FindSingleNode("//a[@id = 'profile-logout']")) {
            return true;
        }
        // Invalid Member Number or Password.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Invalid Member Number or Password.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Too many attempts... Your account is locked!
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Too many attempts... Your account is locked!')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // no errors on these accounts
        $this->logger->debug("'{$this->http->FindSingleNode("//form[@id = 'login']")}'");

        if ($this->http->FindSingleNode("//form[@id = 'login']") == 'Member Number * Password * submit') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
//        if (in_array($this->AccountFields['Login'], ['50017300642', '50018045464', '50017263892', '50018106445', '50018015596', '50018066475]))
//            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//span[contains(text(), 'Name:')]", null, true, "/\:\s*([^<]+)/"));
        // Member ID
        $this->SetProperty("MemberID", $this->http->FindSingleNode("//span[contains(text(), 'Member ID:')]", null, true, "/\:\s*([^<]+)/"));
        // Tier
        $this->SetProperty("Tier", $this->http->FindSingleNode("//span[contains(text(), 'Tier:')]", null, true, "/\:\s*([^<]+)/"));
        // Balance - AWARD MILES
        $this->SetBalance($this->http->FindSingleNode("//span[@id = 'award-miles-value']"));
    }
}
