<?php

class TAccountCheckerSouthernsun extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://www.southernsun.com/frequentguest/my-account';

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $time = time() . date("B");
        $arg['CookiesURL'] = "https://www.tsogosun.com/tsogo-sun-rewards-programme/authenticate?callback=jQuery111205360287933052462_{$time}&task=authenticate&_={$time}";
        $arg['SuccessURL'] = self::REWARDS_PAGE_URL;

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // cookies
        $time = date("UB");
        $this->http->GetURL("https://www.tsogosun.com/tsogo-sun-rewards-programme/authenticate?callback=jQuery111205360287933052462_{$time}&task=authenticate&_={$time}");
        // load login form
        $this->http->GetURL("https://www.southernsun.com/frequentguest");

        if (!$this->http->ParseForm(null, "//form[contains(@action, '/authenticate')]")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('task', "login");
        $this->http->SetInputValue('remember', "on");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Server is too busy
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Server is too busy')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm(["X-Requested-With" => "XMLHttpRequest"])) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        if (isset($response->status) && $response->status == 'true') {
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            return $this->loginSuccessful();
        }
        // catch errors
        if (isset($response->message)) {
            $this->logger->error($response->message);
            // Invalid credentials
            if (strstr($response->message, 'Invalid credentials')) {
                throw new CheckException($response->message, ACCOUNT_INVALID_PASSWORD);
            }
            // Wrong error is showing on the website
            if (strstr($response->message, 'System error')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if (isset($response->message))

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//div[@class = 'personal-details']/h1"));
        // Card number
        $this->SetProperty("MemberNo", $this->http->FindSingleNode("//label[contains(text(), 'Membership Number:')]/following-sibling::strong[1]"));
        // Card Status
        $this->SetProperty("TierStatus", $this->http->FindSingleNode("//label[contains(text(), 'Membership Status:')]/following-sibling::strong[1]"));
        // Card Expiry
        $this->SetProperty("MembershipExpiry", $this->http->FindSingleNode("//label[contains(text(), 'Membership Expiry:')]/following-sibling::strong[1]"));
        // Balance - SunRands Balance
        $this->SetBalance($this->http->FindSingleNode("//label[contains(text(), 'SunRands Balance:')]/following-sibling::strong[1]"));
        // Status Points - Status Points earned in a rolling 12-month period
        $this->SetProperty("StatusPoints", $this->http->FindSingleNode("//p[contains(text(), 'Status Points earned in a rolling 12-month period:')]/strong[1]"));
        // Points to next level -  You require Status Points to upgrade
        $this->SetProperty("PointsToNextLevel", $this->http->FindSingleNode("(//p[contains(text(), 'Status Points to upgrade to')])[1]", null, true, "/require ([0-9]+) Status Points/"));
        // Points expiring
        $this->SetProperty("ExpiringPoints", $this->http->FindSingleNode("(//strong[contains(text(), 'SunRands Expiring')])[1]", null, true, "/([0-9]+) SunRands expiring/ims"));
        // Expiration Date - SunRands expiring
        $expirationDate = $this->http->FindSingleNode("(//strong[contains(text(), 'SunRands Expiring')]/em)[1]");
        $this->http->Log("Exp date: {$expirationDate}");

        if ($date = strtotime(str_replace("/", "-", $expirationDate))) {
            $this->SetExpirationDate($date);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode("//p[contains(., 'You must update your login information being continuing')]")
                // Error: Please update your PIN to continue
                || $this->http->FindSingleNode("//p[contains(., 'Please update your PIN to continue')]")) {
                $this->throwProfileUpdateMessageException();
            }
            // provider bug fix (AccountID: 4263529)
            if ($this->AccountFields['Login'] == '1187186') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//div[@class = 'personal-details']/h1")) {
            return true;
        }

        return false;
    }
}
