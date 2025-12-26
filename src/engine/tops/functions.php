<?php

// refs #1928

class TAccountCheckerTops extends TAccountChecker
{
    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www3.topsmarkets.com/consumers/retailers/767/accounts/dashboard", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www3.topsmarkets.com/retailers/767/login");

        if (!$this->http->ParseForm(null, '//form[contains(@action, "authenticate")]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('remember_me', "true");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // Access is successful
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//li[@class = "error-message"]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Password is not valid')
                || $message == 'Consumer session is invalid: email/password combination do not match any users.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($this->AccountFields['Login'] == 'lindaweber928@gmail.com') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (!$href = $this->http->FindSingleNode("//a[contains(@title, 'BonusPlus') and contains(@href, 'GasBonusPoints')]/@href")) {
            $this->http->GetURL('https://www.topsmarkets.com/');
            $href = $this->http->FindSingleNode("//a[contains(@title, 'BonusPlus') and contains(@href, 'GasBonusPoints')]/@href");
        }

        if (isset($href)) {
            $this->http->GetURL($href);
            $src = $this->http->FindSingleNode("//div[@id = 'pointsinfo']/iframe/@src");

            if (!isset($src)) {
                return;
            }
            parse_str(parse_url($src, PHP_URL_QUERY), $out);
            // Account Number
            $this->SetProperty("Number", $out['cn']);
        }

        if (isset($src)) {
            $this->logger->debug("Loading iframe...");
            $this->http->GetURL($src);

            if ($this->http->currentUrl() != $src) {
                $this->logger->debug("[Retry]: Loading iframe...");
                $this->http->GetURL($src);
            }
            // 503 - Request timed out waiting to execute
            if ($this->http->Response['code'] == 503 && $this->http->FindPreg("/Request timed out waiting to execute/ims")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // 500 - The resource you have requested is located on a third-party server.  WebSEAL has attempted to send your request to that server, but it is not responding.
            if ($this->http->Response['code'] == 500 && $this->http->FindPreg("/The resource you have requested is located on a third-party server./ims")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }
        // Balance - TOPS GasPoints
        $this->SetBalance($this->http->FindSingleNode("(//div[contains(text(), 'GasPoints point') or contains(text(), 'GasPoints Erie points') or contains(text(), 'GAS BONUSPOINT') or contains(text(), 'TOPS GASPOINT')])[1]", null, true, '/You have\s*(\d+)[A-Z\s*](TOPS\s*)?(?:GasPoint|GAS BONUSPOINT)/ims'));

        // REDEEM ANYTIME THRU 9/18/21 as exp date
        $exp = $this->http->FindSingleNode('(//div[contains(text(), "GasPoints point") or contains(text(), "GasPoints Erie points") or contains(text(), "GAS BONUSPOINT") or contains(text(), "TOPS GASPOINT")]//b[contains(text(), "REDEEM ANYTIME THRU")])[1]', null, true, "/THRU\s*([^<]+)/");

        if ($this->Balance > 0) {
            $this->SetExpirationDate(strtotime($exp));
        }

        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//span[contains(text(), 'Welcome')]", null, true, '/Welcome (.*)/'));
        // Saved
        $this->SetProperty("Saved", $this->http->FindSingleNode('/html/body/table/tr[2]/td[2]/table/tr[3]/td/p/span'));
        // You need an additional...
        $this->SetProperty("Need", $this->http->FindSingleNode("(//div[contains(text(), 'GasPoints point') or contains(text(), 'GasPoints Erie point') or contains(text(), 'GAS BONUSPOINT') or contains(text(), 'TOPS GASPOINT')])[1]", null, true, '/.*You need an additional (\d+)/'));
        // Gold Anniversary points
        $this->SetProperty("GoldAnniversaryPoints", $this->http->FindSingleNode("//div[contains(text(), 'Gold Anniversary point')]", null, true, '/You\s*have\s*(\d+)\s*Gold\s*Anniversary\s*point/ims'));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (
                !empty($this->Properties['Name'])
                && !empty($this->Properties['Saved'])
                && !empty($this->Properties['Number'])
                && $this->http->FindPreg("/You are eligible to participate in these Extra Rewards promotions\:/")
            ) {
                $this->SetBalanceNA();
            } elseif ($message = $this->http->FindSingleNode('//td[contains(text(), "We are sorry, but our system is temporarily unavailable.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }//if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'logout')]")) {
            return true;
        }

        return false;
    }
}
