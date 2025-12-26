<?php

class TAccountCheckerStarwoodbusiness extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.spgbusiness.com/en/signin");

        if (!$this->http->ParseForm(null, 1, true, "//form[contains(@action, 'en/signin')]")) {
            return false;
        }
        $this->http->FormURL = 'https://www.spgbusiness.com/en/signin';
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("signin", "Sign In");

        return true;
    }

    public function checkErrors()
    {
        // Internal Server Error
        if ($this->http->FindSingleNode("//h2[contains(text(), 'Internal Server Error')]") && $this->http->Response['code'] == 500) {
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
        if ($this->http->FindSingleNode("//a[contains(text(), 'Logout') or contains(text(), 'ABMELDEN')]")) {
            return true;
        }

        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//p[@class = 'errors']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // I have read and accept the Terms &amp; Conditions
        if ($message = $this->http->FindPreg("/I have read and accept the Terms \&amp; Conditions/")) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Room Nights Consumed
        $this->SetBalance($this->http->FindSingleNode("//div[@id='boxSavingsNights']/span[@class='saveValue']"));
        // Company Contact
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id = 'boxProfileContact']/b")));
        // Corporate (SET) Number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//div[@id = 'boxProfileSet']"));
        // Company Name
        $this->SetProperty("CompanyName", $this->http->FindSingleNode("//div[@id = 'boxProfileName']"));
        // Current Savings
        $this->SetProperty("CurrentSavings", $this->http->FindSingleNode("//div[@id='boxSavingsDollars']/span[@class='saveValue']"));
        // Next Reward
        $this->SetProperty("NextReward", $this->http->FindSingleNode("//div[@id='boxSavingsProgressNext']/div[@class='valueEarned']"));

        // Elite Memberships
        $awards = $this->http->XPath->query("//div[(contains(@id,'boxEliteGold') or contains(@id,'boxElitePlatinum')) and .//span[contains(@id,'Available')] > 0]");

        foreach ($awards as $award) {
            $code = $this->http->FindSingleNode(".//div[@class='caption']", $award, false, '/Spg\s+(Gold|Platinum)/i');
            $balance = $this->http->FindSingleNode(".//span[contains(@id,'Available')]", $award);

            if (isset($balance, $code)) {
                $this->AddSubAccount([
                    'Code'        => "starwoodbusinessAward{$code}",
                    'DisplayName' => "{$code} Status Awards",
                    'Balance'     => $balance,
                ]);
            }
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.spgbusiness.com/en/signin';
        //		$arg['SuccessURL'] = 'https://www.starwoodpreferredbusiness.com/manage/account_main.cfm';
        return $arg;
    }
}
