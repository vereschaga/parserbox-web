<?php

class TAccountCheckerTrump extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://secure.trumpcasinos.com/');
        // parse form
        if (!$this->http->ParseForm('login')) {
            return $this->checkErrors();
        }
        // fill fields
        $this->http->SetInputValue('accountID', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        // 500 - Internal server error.
        if ($this->http->FindSingleNode("//h2[contains(text(), '500 - Internal server error.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // This site is currently under maintenance
        $this->http->GetURL("http://www.trumpcasinos.com");

        if ($this->http->Response['code'] == 503) {
            $this->logger->debug($this->http->Response['body']);

            throw new CheckException("This site is currently under maintenance", ACCOUNT_PROVIDER_ERROR);
        }// if ($this->http->Response['code'] == 503)

        return false;
    }

    public function Login()
    {
        // post form
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // successful login?
        if ($this->http->FindNodes('//a[contains(text(), "Sign Out")]')) {
            return true;
        }
        // failed to login
        else {
            $errorCode = ACCOUNT_PROVIDER_ERROR;
            $errorMsg = $this->http->FindSingleNode('//td[@id="cell1_1"][@class="Text-Only"]/p[1]'); // ../span/p not working. Why?
            // another place for error
            if (!$errorMsg) {
                $errorMsg = $this->http->FindSingleNode('//div[@id="error"]');
            }
            // unknown error
            if (!$errorMsg) {
                return false;
            }
            // wrong login/pass
            if (strpos($errorMsg, 'your Trump One Card login was not accepted as entered') !== false
                || strpos($errorMsg, 'Sorry, we are unable to log you in') !== false
                || strpos($errorMsg, 'Please enter pin number') !== false
                || strpos($errorMsg, 'Please enter account number') !== false) {
                $errorCode = ACCOUNT_INVALID_PASSWORD;
            }
            // Exception
            throw new CheckException($errorMsg, $errorCode);
        }
    }

    public function Parse()
    {
        // SubAccounts
        $subAccounts = [];
        $balance = $this->http->FindSingleNode('//div[@class="mycompvalue"]', null, true, '/(\d+)/');
        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode('//*[contains(text(), "Tier Level:")]', null, true, '/Tier Level:\s+(.+)\s+|/ims'));
        // Taj Mahal
        if (isset($balance)) {
            $subAccounts[] = [
                // Code
                'Code' => ' trumpTajMahal',
                // Display Name
                'DisplayName' => 'Taj Mahal',
                // My Comp Dollard - Balance
                'Balance' => $balance,
                // My Tier Points
                'MyTierPoints' => $this->http->FindSingleNode('//div[@class="mytiervalue"]'),
            ];
        }
        // Plaza
        if ($plazaUrl = $this->http->FindSingleNode('//span[@class="plaza"]/a/@href')) {
            // get page
            $this->http->GetURL('https://secure.trumpcasinos.com/' . $plazaUrl);
            // Plaza subAccount
            $balance2 = $this->http->FindSingleNode('//div[@class="mycompvalue"]', null, true, '/(\d+)/');

            if (isset($balance2)) {
                $subAccounts[] = [
                    // Code
                    'Code' => ' trumpPlaza',
                    // Display Name
                    'DisplayName' => 'Plaza',
                    // My Comp Dollard - Balance
                    'Balance' => $balance2,
                    // My Tier Points
                    'MyTierPoints' => $this->http->FindSingleNode('//div[@class="mytiervalue"]'),
                ];
            }
        }
        // SubAccounts
        if (count($subAccounts)) {
            // duplicate for elite
            $this->SetProperty('MyTierPointsElite', $subAccounts[0]['MyTierPoints']);
            $this->SetBalanceNA();
            $this->SetProperty("CombineSubAccounts", false);
            $this->SetProperty('SubAccounts', $subAccounts);
        }
        // Profile
        if ($profileUrl = $this->http->FindSingleNode('//div[@id="navacct"]/ul/li[2]/a/@href')) {
            // get page
            $this->http->GetURL('https://secure.trumpcasinos.com/' . $profileUrl);
            // Name
            $this->SetProperty('Name', $this->http->FindSingleNode('//span[@id="name"]'));
        }
    }
}
