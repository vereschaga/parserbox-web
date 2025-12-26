<?php

class TAccountCheckerLeancuisine extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://deliciousrewards.leancuisine.com/public/splash.pg");

        if (!$this->http->ParseForm(null, 1, true, "//form[contains(@action, 'login.pg')]")) {
            return $this->checkErrors();
        }
        $this->http->FormURL = "https://deliciousrewards.leancuisine.com/auth/login.pg";
        $this->http->SetInputValue('userName', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        // The LEAN CUISINE® Delicious Rewards™ program ended on 12/31/14.
        if ($message = $this->http->FindPreg("/program ended on 12\/31\/14\./ims")) {
            throw new CheckException("The LEAN CUISINE® Delicious Rewards™ program ended on 12/31/14.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//body[contains(text(), 'Unable to determine client site.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//img[contains(@src, '/maintenance/images/stressContent.')]/@src")) {
            throw new CheckException("Lean Cuisine (Delicious Rewards) website is currently unavailable. We apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/
        // Maintenance
        if ($message = $this->http->FindSingleNode("//div[contains(@style, 'maintenance_background.jpg')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error in '/' Application
        if ($this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            // 504 Gateway Time-out
            || $this->http->FindPreg("/>504 Gateway Time-out</ims")
            // An error occurred while processing your request
            || $this->http->FindPreg("/An error occurred while processing your request\.</ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('(//form[@class="form"]/div[@class="error"])[1]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# I have read and agree to the Delicious Rewards Rules
        if ($message = $this->http->FindPreg("/I have read and agree to the/ims")) {
            $this->throwAcceptTermsMessageException();
        }
        //# New Password
        if (strstr($this->http->currentUrl(), 'NewPassword')
            && $this->http->FindPreg("/(Enter the email address you used to register with LEAN CUISINE<sup>\&trade;<\/sup> and we\'ll send you a link to reset your password\.)/ims")) {
            throw new CheckException("Lean Cuisine (Delicious Rewards) website is asking you to create a new password, until you do so we would not be able to retrieve your account information", ACCOUNT_PROVIDER_ERROR);
        }
        //# Invalid LoginID/Password
        $this->http->Log($this->http->currentUrl());

        if (($this->http->FindSingleNode("//title[contains(text(), 'Logging out...')]")
            && $this->http->currentUrl() == 'https://deliciousrewards.leancuisine.com/auth/login.pg')
            || $this->http->currentUrl() == 'https://www.leancuisine.com/Error.aspx?aspxerrorpath=%2FUser%2FDeliciousRewards%2FNewPassword.aspx') {
            throw new CheckException("Invalid LoginID/Password", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty("Name", $this->http->FindSingleNode('//div[@id="name"]', null, true, '/([\w\-]+)\s*\,\s*Welcome/ims'));
        //# Balance - My Points Balance
        $this->SetBalance($this->http->FindSingleNode('//div[@id="balance"]', null, true, '/\s*(\d*)\s+point/ims'));

        //# Site's feature
        $this->http->GetURL("http://www.leancuisine.com/User/EditMyInformation.aspx");
        $this->http->GetURL("http://www.leancuisine.com/User/EditMyInformation.aspx");
        //# Full Name
        $name = CleanXMLValue($this->http->FindSingleNode("//input[contains(@name, 'FirstName')]/@value")
        . ' ' . $this->http->FindSingleNode("//input[contains(@name, 'LastName')]/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }
}
