<?php

class TAccountCheckerLaoair extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://ffqv.loyaltyplus.aero/qvloyalty/main.jsf');
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('http://ffqv.loyaltyplus.aero/qvloyalty/index.jsf');

        if (!$this->http->ParseForm('login_form')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('login_form:userName', $this->AccountFields['Login']);
        $this->http->SetInputValue('login_form:password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('login_form:login_btn', 'login_form:login_btn');

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.), "Login Failed")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance -
        $this->SetBalance($this->http->FindSingleNode('//text()[starts-with(normalize-space(.), "Balance:")]/following::td[1]'));

        // Miles Balance
        $propertyPair = [
            'BaseMiles'      => 'Base Miles:',
            'BonusMiles'     => 'Bonus Miles:',
            'UsedMiles'      => 'Used Miles:',
            'ExpiredMiles'   => 'Expired Miles:',
            'SegmentsToTier' => 'Segments to Tier:',
            'MilesToTier'    => 'Miles to Tier:',
        ];

        foreach ($propertyPair as $propertyName => $matchText) {
            $this->SetProperty($propertyName,
                $this->http->FindSingleNode('//text()[starts-with(normalize-space(.), "' . $matchText . '")]/following::td[1]'));
        }

        //Membership
        $link = $this->http->FindSingleNode('//img[@id="memberProfileSummary:virtualCard:virtualCardImage"]/@src');

        if (preg_match("/prod=[A-Z]{2}([A-Z]{2})&mpacc=(\d+)&/", $link, $m)) {
            $this->SetProperty("Membership", $m[1] . $m[2]);
        }

        //Name
        $this->http->GetURL('http://ffqv.loyaltyplus.aero/qvloyalty/update.jsf');
        $firstName = $this->http->FindSingleNode('//input[contains(@id,"register_update:profile:firstName")]/@value');
        $lastName = $this->http->FindSingleNode('//input[contains(@id,"register_update:profile:lastName")]/@value');

        if (!empty($name = beautifulName($firstName . " " . $lastName))) {
            $this->SetProperty('Name', $name);
        }

        //Reservation check
        $this->http->GetURL('https://ffqv.loyaltyplus.aero/qvloyalty/myFutureFlights.jsf');

        if (!$this->http->FindSingleNode(' //text()[starts-with(normalize-space(.), "No records found.")]')) {
            $this->sendNotification("refs #18791 - Reservations  is not empty");
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(normalize-space(.), "Logout")]/@href')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
