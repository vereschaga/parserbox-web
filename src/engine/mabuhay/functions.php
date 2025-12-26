<?php

class TAccountCheckerMabuhay extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.philippineairlines.com/en/overview-account-page';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (!is_numeric($this->AccountFields['Login'])) {
            throw new CheckException("Input 9-digit Member ID without spaces and special characters.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://www.philippineairlines.com/ph/en/about-us.html");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        /*
        $this->http->GetURL("https://www.mabuhaymiles.com/");
        $this->http->GetURL("https://www.mabuhaymiles.com/Login");

        // provider bug fix
        if (
            !$this->http->ParseForm(null, "//form[@class = 'login-form']")
            && $this->http->FindSingleNode("//h2[contains(text(), 'Object moved to ')]")
        ) {
            $this->logger->warning("provider bug fix");
            sleep(3);
            $this->http->GetURL("https://www.mabuhaymiles.com/Login");
        }// if (!$this->http->ParseForm("logInForm") && $this->http->FindSingleNode("//h2[contains(text(), 'Object moved to ')]"))

        if (
            !$this->http->ParseForm(null, "//form[@class = 'login-form']")
            && $this->http->FindSingleNode("//h2[contains(text(), 'Object moved to ')]")
        ) {
            $this->logger->warning("provider bug fix");
            sleep(3);
            $this->http->GetURL("https://www.mabuhaymiles.com/");
        }// if (!$this->http->ParseForm("logInForm") && $this->http->FindSingleNode("//h2[contains(text(), 'Object moved to ')]"))

        if (!$this->http->ParseForm("logInForm") && !$this->http->ParseForm(null, "//form[@class = 'login-form']")) {
            return $this->checkErrors();
        }
//        $this->http->Inputs["MemberId"]['maxlength'] = 11;
//        $this->http->Inputs["Password"]['maxlength'] = 8;
        $login = $this->AccountFields['Login'];

        if (strlen($login) > 9) {
            $login = preg_replace('/^(0*)/', '', $login);
        }
        $this->http->FormURL = 'https://www.philippineairlines.com/api/Accounts/Login';
        $this->http->SetInputValue("Email", $login);
        $this->http->SetInputValue("Password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("returnUrl", "/en/");
        */

        $data = [
            "cardNumber"   => "",
            "pw"           => $this->AccountFields['Pass'],
            "email"        => "",
            "mobileNumber" => "",
            "accessToken"  => "",
            "smTypeCode"   => "",
        ];

        $login = $this->AccountFields['Login'];

        if (filter_var($login, FILTER_VALIDATE_EMAIL) === false) {
            $data["cardNumber"] = $login;
        } else {
            $data["email"] = $login;
        }

        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json",
            "CSRF-Token"   => "undefined",
//            "User-Agent"   => \HttpBrowser::PROXY_USER_AGENT,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.philippineairlines.com/content/palportal-configs/api/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->Response['code'] == 404) {
            $this->http->GetURL("https://www.philippineairlines.com/");

            if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Maintenance')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    public function Login()
    {
        /*
        if (!$this->http->PostForm()) {
            // AccountID: 3603514
            if ($this->http->Response['code'] == 504 && $this->http->FindSingleNode("//h2[contains(text(), 'The request could not be satisfied.')]")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }
        */

        $response = $this->http->JsonLog();
        $message = $response->message ?? null;

        if ($message) {
            if ($message == 'Login successful') {
                return true;
            }

            if ($message == 'Login Failed') {
                throw new CheckException("Please enter correct login credentials", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if (
            // AccountID: 3889463
            $this->http->currentUrl() == 'https://www.philippineairlines.com/ph/en/500.html'
            // AccountID: 6155839
            || $this->http->FindSingleNode('//h2[contains(text(), "The request is blocked.")]')
        ) {
            throw new CheckException("Please enter correct login credentials", ACCOUNT_INVALID_PASSWORD);
        }

        /*
        if (isset($response->Data->RedirectUrl)) {
            $url = $response->Data->RedirectUrl;
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
        }

        // Your session was lost.
        if (
            (
                stristr($this->http->currentUrl(), 'https://www.mabuhaymiles.com/en/messagepages/sessiontimeout')
                && $this->http->FindSingleNode("//h3[contains(text(), 'Your session was lost.')]")
            )
            || $this->http->currentUrl() == 'https://www.mabuhaymiles.com/_system/NotFound?aspxerrorpath=/en/account'
        ) {
            throw new CheckRetryNeededException(3);
        }

        if ($this->http->FindNodes("//button[contains(text(), 'Sign out')]")) {
            return true;
        }

        if ($message = $response->Data->ErrorMessage ?? null) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Either your Membership ID/email address or password is invalid.')
                || $message == 'Please enter correct login credentials'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->currentUrl() == 'https://www.philippineairlines.com/Error-Page/500?aspxerrorpath=/api/Accounts/Login') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        */

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $data = $response->data;
        // Balance - Mabuhay Miles
        $this->SetBalance($data->pointsBalanace);
        // Name
        $this->SetProperty("Name", beautifulName("{$data->firstName} {$data->lastName}"));
        // Your Membership Number
        $this->SetProperty("Number", $data->activeCardNo);
        // Your Membership Status
        $this->SetProperty("Status", $data->tier);
        // Expiration date  // refs #9378
        $expiringBalance = $data->nextExpiryMiles;
        $exp = $data->milesExpiryDate;
        $this->logger->debug("Expire {$expiringBalance} miles on {$exp}");
        // 3.a
        if ($expiringBalance != 0 && strtotime($exp)) {
            $this->SetExpirationDate(strtotime($exp));
            $this->SetProperty("ExpiringBalance", $expiringBalance);
        }
        // 3.b
        elseif ($expiringBalance == 0 && $this->Balance > 0 && $exp != "0001-01-01T00:00:00" && strtotime($exp)) {
            $this->SetExpirationDate(strtotime("+2 year", strtotime($exp)));
            $this->SetProperty("ExpiringBalance", $this->Balance);
        }

        return;

        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if (str_contains($this->http->currentUrl(), 'Error-Page/500')) {
            sleep(5);
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Balance - Redeemable Miles
        $this->SetBalance($this->http->FindSingleNode("//h2[contains(@class, 'miles__number')]/text()[1]"));
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//div[contains(@class, 'image__title')]", null, true, "/Mabuhay\s*([^!]+)/"));
        // Your Membership Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//div[contains(text(), 'Member ID Number:')]", null, true, "/:\s*(\d+)/"));
        // Your Membership Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//div[contains(text(), 'Member ID Number:')]/following-sibling::div[1]"));
        // Earn Flight Miles
        $this->SetProperty("EarnedThisYear", $this->http->FindSingleNode('//div[contains(text(), "Earn Flight Miles")]/following-sibling::div[1]', null, true, "/([^\/]+)/"));
        $this->SetProperty("MilesNeeded", $this->http->FindSingleNode('//div[contains(text(), "Earn Flight Miles")]/following-sibling::div[1]', null, true, "/\/(.+)/"));
        // Fly one-way qualifying flight any class
        $this->SetProperty("QualifyingFlights", $this->http->FindSingleNode('//div[contains(text(), "Fly one-way qualifying flight")]/following-sibling::div[1]', null, true, "/([^\/]+)/"));
        // Business Class sectors flown this year
        $this->SetProperty("SectorsFlown", $this->http->FindSingleNode('//div[contains(text(), "Business Class sectors flown this year")]/following-sibling::div[1]', null, true, "/([^\/]+)/"));
        // Expiration date  // refs #9378
        $expiringBalance = $this->http->FindSingleNode("//div[contains(text(), 'miles to expire:')]", null, true, "/:\s*(.+)\s+Mile/ims");
        $exp = $this->http->FindSingleNode("//div[contains(text(), 'miles to expire:')]", null, true, "/on\s*([^<]+)/ims");
        $this->logger->debug("Expire {$expiringBalance} miles on {$exp}");
        // 3.a
        if ($expiringBalance != 0 && strtotime($exp)) {
            $this->SetExpirationDate(strtotime($exp));
            $this->SetProperty("ExpiringBalance", $expiringBalance);
        }
        // 3.b
        elseif ($expiringBalance == 0 && $this->Balance > 0 && strtotime($exp)) {
            $this->SetExpirationDate(strtotime("+2 year", strtotime($exp)));
            $this->SetProperty("ExpiringBalance", $this->Balance);
        }

        $this->http->GetURL("https://www.philippineairlines.com/overview-account-card-page");
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//div[@id = 'cardContainer']//div[contains(@class, 'card-name')]"));
    }
}
