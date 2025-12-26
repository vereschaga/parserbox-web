<?php

// refs #1883, macdonaldhotels

class TAccountCheckerMacdonaldhotels extends TAccountChecker
{
    public function LoadLoginForm()
    {
        throw new CheckException("The Macdonald Hotels loyalty programme - The Club has now closed. Please refer to <a href=\"http://www.MacdonaldHotels.co.uk/ClubFAQ\" target=\"_blank\">here</a> for further details.", ACCOUNT_PROVIDER_ERROR);
        $this->http->removeCookies();
        $this->http->setMaxRedirects(8);
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://account.theclub.macdonaldhotels.co.uk/customer/login.aspx');

        if (!$this->http->ParseForm('aspnetForm')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ctl00$Main$txtEmail', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$Main$txtPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('ctl00$Main$cmdLogin', "Log In");

        return true;
    }

    public function checkErrors()
    {
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently upgrading our reservations system and unfortunately')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Macdonald Hotels website is currently unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Macdonald Hotels website is currently unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Unfortunately an error has occured
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Unfortunately an error has occured')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application.
        if ($this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($link = $this->http->FindPreg("/Object\s*moved\s*to\s*<a\s*href=\"([^\"]+)/ims")) {
            $this->logger->debug("Redirect: [$link]");
            $this->http->GetURL($link);
        }

        if ($message = $this->http->FindSingleNode('//div[@class="validationErrors"][1]//ul[1]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode('//a[contains(@href, "logout")]/@href', null, false)
            || $this->http->FindPreg("/Logged in as:/ims")) {
            return true;
        }

        if ($message = $this->http->FindPreg("/Unable to Log In/ims")) {
            throw new CheckException("Your passcode could not be verified", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//fieldset[contains(@class, 'form login')]/div[3]/span/text()")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        /*
         * Sorry, you are not a member of the club
         * although you are a registered user with Macdonald Hotels.
         * Please use the link on the right to join the club.
         */
        /*if ($this->http->currentUrl() == 'https://account.theclub.macdonaldhotels.co.uk/customer/login.aspx'
            && $this->http->ParseForm("aspnetForm")
            && $this->http->FindSingleNode("//a[contains(@href, 'register.aspx?skin=loyalty') and contains(text(), 'Join The Club')]")) {
                throw new CheckException("Sorry, you are not a member of the club although you are a registered user with Macdonald Hotels. Please use the link on the right to join the club.", ACCOUNT_PROVIDER_ERROR);
        }*/

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Account Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//h5[contains(text(), 'Account Number:')]", null, true, '/\:\s*([^<]+)/ims'));
        // Balance - Current balance
        $this->SetBalance($this->http->FindSingleNode('//p[@class = \'current-ballance\']'));
        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode("//span[contains(@class, 'f-btn-primary')]/span[contains(@class, 'small')]", null, true, "/\-\s*(\w+)\s*$/"));

        // Name
        $this->http->GetURL("http://account.theclub.macdonaldhotels.co.uk/customer/amenddetails.aspx");
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@id = 'ctl00_Main_txtFirstName']/@value") . " " . $this->http->FindSingleNode("//input[@id = 'ctl00_Main_txtLastName']/@value")));

//        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && !isset($this->Properties['Number'], $this->Properties['Status'])
//            && empty($this->Properties['Name'])) {
//            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h1[@class = 'pageTitle']", null, true, "/Hello,\s*([^<]+)/ims")));
//            if (!empty($this->Properties['Name']))
//                $this->SetBalanceNA();
//        }
    }
}
