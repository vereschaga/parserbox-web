<?php

class TAccountCheckerOfficemax extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.officemaxperks.com/Login.aspx");

        if (stripos($this->http->currentUrl(), 'www.officedepot.com/a/loyalty-programs/maxperks-rewards') !== false) {
            throw new CheckException('Office Max (Max Perks) program has merged with Office Depot (Worklife Rewards) and is now supported via www.officedepot.com website. Please, make sure you have created new credentials with this website and then add Office Depot (Worklife Rewards) program to your profile.', ACCOUNT_PROVIDER_ERROR);
        }

        if (!$this->http->ParseForm("aspnetForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ctl00$ContentPlaceHolderLeftPanel$txtLoginField', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$ContentPlaceHolderLeftPanel$txtPassField', $this->AccountFields['Pass']);
        $this->http->Form['ctl00$ContentPlaceHolderLeftPanel$imgSubmit.y'] = '4';
        $this->http->Form['ctl00$ContentPlaceHolderLeftPanel$imgSubmit.x'] = '35';
        $this->http->FormURL = "https://www.officemaxperks.com/Login.aspx";

        return true;
    }

    public function checkErrors()
    {
        // Site Maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Site Maintenance')]")) {
            throw new CheckException("The MaxPerks website is currently unavailable as we perform maintenance and site enhancements. We apologize for this inconvenience. Please be sure to check back in the next few hours to view your account activity. Thank you.", ACCOUNT_PROVIDER_ERROR);
        }
        //# An unexpected error occurred
        if ($message = $this->http->FindPreg("/An unexpected error occurred\. Please try again later\./ims")) {
            throw new CheckException("Office Max website had a hiccup, please try to check your balance at a later time.", ACCOUNT_PROVIDER_ERROR);
        }
        //# HTTP Error 404
        if ($this->http->FindSingleNode("//p[contains(text(), 'HTTP Error 404')]")
            || $this->http->FindSingleNode("//h2[contains(text(), '500 - Internal server error')]")
            //# Server Error in '/' Application
            || $this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            //# Server Error in '/' Application
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindPreg("/Invalid login/ims")) {
            throw new CheckException("Your passcode could not be verified", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/This Account is not active/ims")) {
            throw new CheckException("The account is currently not active.", ACCOUNT_INVALID_PASSWORD);
        }

        //# This temporary password has already expired
        if ($message = $this->http->FindSingleNode("//div[@class = 'errorMsg']/span[@class = 'errortxt']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode("//a[contains(@id, 'Logout')]/@id")) {
            return true;
        }
        //# Needed update member profile
        if ($this->http->FindSingleNode("//span[contains(text(), 'Please review the highlighted fields below and complete the required information.')]")
            // Please select your primary reason for purchasing office products and services
            || $this->http->FindSingleNode("//p[contains(text(), 'Please select your primary reason for purchasing office products and services')]")) {
            throw new CheckException("Office Max (Max Perks) website is asking you to update your member profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Member ID
        $this->SetProperty("Number", $this->http->FindSingleNode('//*[@id="memberID"]', null, true, '/Member ID:(.*)/'));
        //# Amount of Expiration
        $this->SetProperty("ExpireAmount", $this->http->FindSingleNode('//span[@id="grb_expireAmount"]'));
        //# Expiration Date
        $expire = $this->http->FindSingleNode('//span[@id="grb_expireDate"]', null, true, '/will expire\s*([^<]+)/ims');

        if ($expire = strtotime($expire)) {
            $this->SetExpirationDate($expire);
        }
        //# Balance - My Available Rewards
        $this->SetBalance($this->http->FindSingleNode('//h2[contains(text(),"Available Rewards")]/following::h3[1]', null, true, '/\\$(.*)/'));
        //# Name
        $this->http->GetURL("https://www.officemaxperks.com/MyProfile.aspx");
        $name = $this->http->FindSingleNode("//input[contains(@name, 'FirstName')]/@value") . ' ' . $this->http->FindSingleNode("//input[contains(@name, 'Initial')]/@value") . ' ' . $this->http->FindSingleNode("//input[contains(@name, 'LastName')]/@value");
        $name = str_replace('  ', ' ', trim($name));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    public function GetExtensionFinalURL(array $fields)
    {
        return "https://www.officemaxperks.com/Home.aspx";
    }
}
