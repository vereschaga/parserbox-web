<?php

class TAccountCheckerMydnc extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.mydealsandcoupons.com/SignIn");

        if (!$this->http->ParseForm("aspnetForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$ucSignIN$txtlogEmail', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$ucSignIN$txtlogpassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$ucSignIN$btnLoginSubmit', "Sign me in");

        return true;
    }

    public function checkErrors()
    {
        //# 500 - Internal server error
        if ($this->http->FindSingleNode("//h2[contains(text(), 'server error')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")
            || $this->http->FindSingleNode('//h1[contains(., "Server Error in \'/\' Application.")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        $this->http->GetURL("http://www.mydealsandcoupons.com");

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "re performing some maintenance at the moment.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        //# Invalid Email Address or Password
        if ($message = $this->http->FindPreg("/Invalid Email/ims")) {
            throw new CheckException("Invalid Email Address or Password", ACCOUNT_INVALID_PASSWORD);
        }
        // LoginConnection Timeout Expired.
        if ($message = $this->http->FindPreg("/LoginConnection Timeout Expired./ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // LoginA connection was successfully established with the server
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'LoginA connection was successfully established with the server')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Access is allowed
        if ($this->http->FindPreg("/Log Out/")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('http://www.mydealsandcoupons.com/mya/MyAccount');
        // Balance - Total available
        $this->SetBalance($this->http->FindSingleNode("//h4[contains(text(), 'Total available')]/following-sibling::div/a[@id = 'toNavigateToTab']", null, true, self::BALANCE_REGEXP));
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//span[contains(@id, 'IdMemberShipYear')]", null, true, "/since\s*([^\•\<]+)/"));
        // Total earned
        $this->SetProperty("TotalEarned", $this->http->FindSingleNode("//a[contains(@id, 'anc_availableCB')]", null, true, '/earned\s*([^<]+)/'));
        // Cashback available
        $this->SetProperty("CashBack", $this->http->FindSingleNode("//h4[contains(text(), 'Cashback available')]/following-sibling::p[1]"));
        // Referral rewards available
        $this->SetProperty("ReferralRewards", $this->http->FindSingleNode("//h4[contains(text(), 'Referral rewards available')]/following-sibling::p[1]"));
        // Review rewards available
        $this->SetProperty("ReviewRewards", $this->http->FindSingleNode("//h4[contains(text(), 'Review rewards available')]/following-sibling::p[1]"));

        // Name
        $this->http->GetURL("http://www.mydealsandcoupons.com/mya/mUserProfile.aspx?PName=UserProfile");
        $name = CleanXMLValue($this->http->FindSingleNode("//input[contains(@id, 'txtFirstName')]/@value") . ' ' . $this->http->FindSingleNode("//input[contains(@id, 'txtlastName')]/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.mydealsandcoupons.com/SignIn";
//        $arg["SuccessURL"] = "http://www.mydealsandcoupons.com/mya/MyAccount";

        return $arg;
    }
}
