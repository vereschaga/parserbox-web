<?php

class TAccountCheckerSportsauth extends TAccountChecker
{
    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])
            && (strstr($properties['SubAccountCode'], "sportsauthCertificate"))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function LoadLoginForm()
    {
        // reset cookie
        $this->http->removeCookies();
        $this->http->GetURL('https://www.sportsauthorityleague.com/SignIn.aspx?ReturnUrl=');

        if (!$this->http->ParseForm('PopupForm')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ctl00$MainContent$txt_Username', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$MainContent$txt_Pass', $this->AccountFields['Pass']);
        $this->http->SetInputValue('ctl00$MainContent$btn_SignIn', "Sign in");
        $this->http->SetInputValue('ctl00$ScriptManager', 'ctl00$MainContent$UpdatePanel|ctl00$MainContent$btn_SignIn');
        $this->http->SetInputValue('__ASYNCPOST', 'true');

        return true;
    }

    public function checkErrors()
    {
        //# Scheduled Maintenance
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'system maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our system is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Our system is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our system is temporarily unavailable while we make changes
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our system is temporarily unavailable while we make changes')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We encountered a problem while trying to process your request
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We apologize: we encountered a problem while trying to process your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 500 - Internal server error.
        if ($message = $this->http->FindSingleNode("//title[contains(text(), '500 - Internal server error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // User Name or Password is incorrect.
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "User Name or Password is incorrect")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Login failed. Try again later or contact Support.
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Login failed. Try again later or contact Support")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Email or Password is incorrect. Account is now locked.
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Email or Password is incorrect. Account is now locked.")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Email or Password is incorrect.
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Email or Password is incorrect.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://www.sportsauthorityleague.com/Points.aspx");

        if ($this->http->FindSingleNode('//li[not(contains(@class, "hidden"))]/a[contains(@onclick, "LogOut")]')) {
            return true;
        }

        $this->http->Log("Current URL: " . $this->http->currentUrl());
        // retries
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            throw new CheckRetryNeededException(2, 7);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode('//span[@id ="MainContent_accountInfo_lblPoints"]'));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//label[@id="MainContent_accountInfo_lblUserName"]')));
        // Acct #
        $this->SetProperty('AccountNumber', $this->http->FindSingleNode('//span[@id="MainContent_accountInfo_lblCardNumber"]'));
        // Current Tier
        $this->SetProperty('Tier', $this->http->FindSingleNode('//span[@id="MainContent_accountInfo_lblCurrentTier"]'));

        //		// Current Reward Amount
        //		$this->SetProperty('CurrentRewardAmount', $this->http->FindSingleNode("//div[@id = 'member_points_pnlQualifiedReward']/div"));
        //		// Points to Next Reward
        //		$this->SetProperty('PointsToNextReward', $this->http->FindSingleNode('//div[@id="acct-unlock"]/strong', null, true, '/(.*)\s+(?:more points|mÃ¡s puntos)/ims'));
        // Member Since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//span[@id="MainContent_accountInfo_lblEnrollDate"]'));

        // Certificates
        $this->http->GetURL("https://www.sportsauthorityleague.com/RewardCertificates.aspx");
        $certificates = $this->http->XPath->query("//table[contains(@id, 'gridRewardCerts')]//tr[contains(@id, 'gridRewardCerts')]");
        $this->http->Log("Totel {$certificates->length} certificates were found");

        for ($i = 0; $i < $certificates->length; $i++) {
            // Cert #
            $code = $this->http->FindSingleNode("td[1]", $certificates->item($i));
            // Dollar Value
            $balance = $this->http->FindSingleNode("td[4]", $certificates->item($i), true, '/\$([\d\.\,]+)/ims');
            // Name
            $name = $this->http->FindSingleNode("td[3]", $certificates->item($i));
            // Expiration Date
            $exp = $this->http->FindSingleNode("td[5]", $certificates->item($i));
            // Status
            $status = $this->http->FindSingleNode("td[6]", $certificates->item($i));

            if (strtotime($exp) && isset($code, $balance) && !in_array($status, ["Redeemed", "Expired"])) {
                $subAccounts[] = [
                    'Code'           => 'sportsauthCertificate' . $code,
                    'DisplayName'    => "Cert # " . $code . " - " . $name,
                    'Balance'        => $balance,
                    'ExpirationDate' => strtotime($exp),
                    // Issued Date
                    'IssuedDate'     => $this->http->FindSingleNode("td[7]", $certificates->item($i)),
                ];
            }
        }// for ($i = 0; $i < $certificates->length; $i++)

        if (isset($subAccounts)) {
            $this->SetProperty("CombineSubAccounts", false);
            $this->SetProperty("SubAccounts", $subAccounts);
        }
    }

    public function GetExtensionFinalURL(array $fields)
    {
        return "https://www.sportsauthorityleague.com/RewardCertificates.aspx";
    }
}
