<?php

class TAccountCheckerSaudigulf extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('http://theclub.saudigulfairlines.com/en/dashboard', [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('http://theclub.saudigulfairlines.com/en/login');

        if (!$this->http->ParseForm('aspnetForm')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$txtMembershipID', $this->AccountFields["Login"]);
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$txtPassword', $this->AccountFields["Pass"]);
        $this->http->SetInputValue('ctl00$ContentPlaceHolder1$btnLogin', 'Login');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
//        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'We are currently undergoing system maintenance')]")) {
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
//        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // login successful
        if ($this->loginSuccessful()) {
            return true;
        }
        // check for invalid password
        if ($message = $this->http->FindSingleNode("//span[@id='ctl00_ContentPlaceHolder1_lblMessage' and contains(.,'Invalid ID or Password')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//span[@id='ctl00_ContentPlaceHolder1_lblMessage' and contains(.,'The underlying connection was closed: An unexpected error occurred on a send.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', beautifulName(join(' ',
            $this->http->FindNodes("//div[@class='member-details']/h4/span[@class='first-name' or @class='last-name']"))));
        // Club Card
        $this->SetProperty('AccountNumber', $this->http->FindSingleNode("//div[@class='member-details']/h5/span[@class='membership-number']"));
        // Status
        $status = $this->http->FindSingleNode("//img[@id='ctl00_ctl00_ContentPlaceHolder1_imgCard']/@src");

        switch ($status) {
            case '/ui/media/img/membership-card-green.png':
                $status = 'Green';
                $this->SetProperty('Status', $status);

                break;

            default:
                $this->sendNotification('refs #14729, saudigulf - new status: ' . $status);

                break;
        }

        // Balance - Miles Balance
        $this->SetBalance($this->http->FindSingleNode("//h6[normalize-space(text())='Miles Balance']/preceding-sibling::h3"));
        // Tier Qualifying Miles
        $this->SetProperty('TierQualifyingMiles', $this->http->FindSingleNode("//h6[normalize-space(text())='Tier Qualifying Miles']/preceding-sibling::h3"));
        // Tier Qualifying Points
        $this->SetProperty('TierQualifyingPoints', $this->http->FindSingleNode("//h6[normalize-space(text())='Tier Qualifying Points']/preceding-sibling::h3"));

        if (!$this->http->FindSingleNode("//tr[.//th[contains(.,'Miles set to Expire')]]/following-sibling::tr/td/i[contains(text(),'Sorry, no milage is available.')]")) {
            $this->sendNotification('refs #14729, saudigulf - new exp date');
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(text(), 'Log out')]")) {
            return true;
        }

        return false;
    }
}
