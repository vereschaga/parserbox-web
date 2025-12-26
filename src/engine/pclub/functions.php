<?php

class TAccountCheckerPclub extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.phnr.com/en/premier-club");

        if (!$this->http->ParseForm(null, "//form[@class = 'my-cabinet' and contains(@action, '/en/login/do-login')]")) {
            return false;
        }

        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = "https://www.phnr.com/en/cabinet/profile";

        return $arg;
    }

    public function checkErrors()
    {
        // provider error
        if ($this->http->Response['code'] == 500 && $this->http->FindSingleNode("//title[contains(., 'Registration Confirmation')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Access is allowed
        if ($this->http->FindPreg("/\{\"success\"/")) {
            return true;
        }
        // Incorrect login or password
        if ($message = $this->http->FindPreg("/\"(Incorrect login or password)\"/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // You haven't confirm your E-mail. We have sent mail to you to confirm your E-mail, after this you can authorize and create your booking
        if ($message = $this->http->FindPreg("/\"(You haven't confirm your E-mail. We have sent mail to you to confirm your E-mail, after this you can authorize and create your booking)\"/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.phnr.com/en/cabinet/profile");
//        // normalize spaces
//        $this->http->SetBody($this->http->Response['body'], true);
        // Balance - Points Balance
        $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'Points Balance')]", null, true, "/Points Balance\s*([^<]+)/"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(text(), 'Welcome,')]", null, true, "/Welcome\,\s*([^<\!]+)/")));
        // Account №
        $this->SetProperty("MemberAccount", $this->http->FindSingleNode("//div[contains(text(), 'Account №')]", null, true, "/№\s*([^<]+)/"));
        // Membership Level
        $this->SetProperty("MembershipLevel", $this->http->FindSingleNode("//em[contains(text(), 'Card:')]", null, true, "/:\s*([^<]+)№/"));
        // Card Number
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//em[contains(text(), 'Card:')]", null, true, "/№\s*([^<]+)/"));
        // Stays Completed
        $this->SetProperty("StaysCompleted", $this->http->FindSingleNode("//em[contains(text(), 'Stays:')]", null, true, "/:\s*([^<]+)/"));
        // Nights Completed
        $this->SetProperty("NightsCompleted", $this->http->FindSingleNode("//em[contains(text(), 'Nights:')]", null, true, "/:\s*([^<]+)/"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->http->GetURL("https://www.phnr.com/en/premier-club");
            if ($this->http->FindSingleNode("//span[contains(text(), 'Join Premier Club')]")) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }
    }
}
