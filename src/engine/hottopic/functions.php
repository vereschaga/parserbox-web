<?php

// refs #2012, hottopic

class TAccountCheckerHottopic extends TAccountChecker
{
    use \AwardWallet\Engine\ProxyList;

    public const REWARDS_PAGE_URL = 'https://www.hottopic.com/myrewards';

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //$arg['CookieURL'] = 'https://www.hottopic.com/account';
        //$arg['SuccessURL'] = 'https://www.hottopic.com/loyalty';
        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->FilterHTML = false;
        $this->KeepState = true;
//        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->setProxyGoProxies();
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
        $this->http->GetURL('https://www.hottopic.com');

        if ($this->http->FindSingleNode('//img[@src = "ht_sitedown_mobile.jpg"]/@src')) {
            throw new CheckException("hottopic.com is temporarily down for maintenance", ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if (!$this->http->ParseForm('login-form')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("loginEmail", $this->AccountFields['Login']);
        $this->http->SetInputValue("loginPassword", $this->AccountFields['Pass']);
        $this->http->SetInputValue("loginRememberMe", "true");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->BodyContains("'host':'geo.captcha&#x2d;delivery.com'", false)) {
            $this->DebugInfo = 'geo captcha, request blocked';
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        /*
         * Welcome to our new site!
         * Since this is your first time logging in, you’ll need to reset your password.
         * Check your inbox for an email with a link to reset.
         * Need help? Call Customer Service at 1.800.892.8674.
         */
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Welcome to our new site! Since this is your first time logging in')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, this does not match our records.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Sorry, this does not match our records.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        $message = $response->error[0] ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Invalid login or password. Remember that password is case-sensitive.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindPreg("/\"accountLocked\": true/")) {
            throw new CheckException("Your account was locked for security reasons. Please reset your password to unlock and access your account.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Balance - Points Earned
        $this->SetBalance($this->http->FindSingleNode("//div[contains(@class,'progress current-progress')]//span[contains(@class, 'rewards-progress-bar__value')]"));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//span[contains(@class, 'rewards-progress-bar__current-tier')]/b"));
        // Available Rewards - You have $0 in rewards.
        $this->SetProperty("AvailableRewards", $this->http->FindSingleNode("//h2[contains(text(),'You have')]/span[contains(@class, 'billing-torrid-insider')]"));
        // You're 14 points away from reaching your next reward.
        $this->SetProperty("PointsNeededToNextReward", $this->http->FindSingleNode('//p[contains(text(), "points away from reaching your next reward.")]',
            null, true, "/re\s+([\d.,]+)\s+point/i"));

        $this->http->GetURL("https://www.hottopic.com/profile");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//input[@id = "firstName"]/@value') . " " . $this->http->FindSingleNode('//input[@id = "lastName"]/@value')));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindNodes("//a[contains(@href, 'Logout')]/@href")
            || $this->http->FindPreg("/\"success\": true/")
        ) {
            return true;
        }

        return false;
    }
}
