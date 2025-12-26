<?php

class TAccountCheckerTarget extends TAccountChecker
{
    public function LoadLoginForm()
    {
        // reset cookie
        $this->http->removeCookies();
        // getting the HTML code
        $this->http->getURL('https://targetpharmacyrewards.com');
        // parsing form on the page
        if (!$this->http->ParseForm('CPForm')) {
            return $this->checkErrors();
        }
        // enter the login and password
        $this->http->SetInputValue("login\$email", $this->AccountFields["Login"]);
        $this->http->SetInputValue("login\$password", $this->AccountFields["Pass"]);
        $this->http->Form["login\$btnLogin.x"] = "41";
        $this->http->Form["login\$btnLogin.y"] = "14";

        return true;
    }

    public function checkErrors()
    {
        //# We apologize: we encountered a problem while trying to process your request.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'we encountered a problem while trying to process your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'are currently performing system maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Provider Error
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We apologize: we encountered a problem while trying to process your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // hard code
        if ($this->http->FindSingleNode('//input[@name = "login$email"]/@value') == $this->AccountFields["Login"]
            && $this->http->ParseForm('CPForm')) {
            throw new CheckException("Please check your credentials and try again.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Login()
    {
        // form submission
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // check for invalid password
        if ($message = $this->http->FindSingleNode("//div[@id='login_vsError']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // login successful
        if ($this->http->FindSingleNode("//p[@id='welcomeTxt']")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance
        $credits = $this->http->FindSingleNode("//div[@id='ringsWrapper']//strong", null, true, "/(\d+) credit/");

        if (isset($credits) && (5 - $credits) >= 0) {
            $this->SetBalance(5 - $credits);
        } else {
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'I authorize Target, its subsidiaries and affiliates (collectively \"Target\") to disclose and use my protected health information')]")) {
                throw new CheckException("Target.com (Pharmacy rewards) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }
            // You've earned a 5%-off Pharmacy Rewards certificate.
            if ($this->http->FindPreg("/(You\'ve earned a 5%-off Pharmacy Rewards certificate\.)/ims")) {
                $this->SetBalance(5);
            }
        }
        $this->http->GetURL("https://targetpharmacyrewards.com/cp/s_edit_info.aspx");
        // Name
        $fName = trim($this->http->FindSingleNode("//input[@id='edit_info_tFirstName']/@value"));
        $mName = $this->http->FindSingleNode("//input[@id='edit_info_tInitial']/@value");

        if ($mName) {
            $mName = " " . trim($mName);
        }
        $lName = trim($this->http->FindSingleNode("//input[@id='edit_info_tLastName']/@value"));
        $this->SetProperty("Name", beautifulName("$fName$mName $lName"));
    }

    public function GetExtensionFinalURL(array $fields)
    {
        return "https://targetpharmacyrewards.com/CP/myaccount.aspx";
    }
}
