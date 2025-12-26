<?php

class TAccountCheckerTwinspires extends TAccountChecker
{
    private const REWARDS_PAGE_URL = "https://www.tscelite.com/user/";

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.tscelite.com/';

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
        $this->http->GetURL("https://www.tscelite.com/");

        if (!$this->http->ParseForm("loginform")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("log", $this->AccountFields['Login']);
        $this->http->SetInputValue("pwd", $this->AccountFields['Pass']);
        $this->http->SetInputValue("rememberme", "forever");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Site off-line
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'currently')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# login successful
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'callout alert')]")) {
            $this->logger->error($message);
            // There was a problem with your PID or PIN.
            if (
                strstr($message, 'There was a problem with your PID or PIN.')
                || strstr($message, 'There was a problem with your Username or Password.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Points Earned this Month
        $this->SetProperty("PointsEarnedThisMonth", $this->http->FindSingleNode("//td[contains(text(), 'Points Earned this Month')]/following::td[1]"));
        // Points Earned this Quarter
        $this->SetProperty("PointsEarnedThisQuarter", $this->http->FindSingleNode("//td[contains(text(), 'Points Earned this Quarter')]/following::td[1]"));
        // Points Earned this Year
        $this->SetProperty("PointsEarnedThisYear", $this->http->FindSingleNode("//td[contains(text(), 'Points Earned this Year')]/following::td[1]"));

        // Points to Expire
        $points = $this->http->FindSingleNode("//td[contains(text(), 'Points to Expire on')]/following-sibling::td[1]");

        if ($points > 0) {
            $this->SetProperty("PointsToExpire", $points);
            // Expire on
            $date = $this->http->FindSingleNode("//td[contains(text(), 'Points to Expire on')]", null, true, '/([a-z]+\s\d+)$/ims');
            $this->SetExpirationDate(strtotime($date . ' ' . date('Y')));
        }

        // Dollars Wagered this Month
        $this->SetProperty("DollarsWageredThisMonth", $this->http->FindSingleNode("//div[@id = 'dollars-wagered']//span[contains(text(), 'this month')]/preceding-sibling::h5"));
        // Dollars Wagered this Quarter
        $this->SetProperty("DollarsWageredThisQuarter", $this->http->FindSingleNode("//div[@id = 'dollars-wagered']//span[contains(text(), 'this quarter')]/preceding-sibling::h5"));
        // Dollars Wagered this Year
        $this->SetProperty("DollarsWageredThisYear", $this->http->FindSingleNode("//div[@id = 'dollars-wagered']//span[contains(text(), 'this year')]/preceding-sibling::h5"));
        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode('//h5[contains(text(), "Your current status is:")]/following-sibling::h3'));
        // Balance - Points Balance
        $this->SetBalance($this->http->FindSingleNode("//h4[contains(text(), 'Points Balance')]/following-sibling::h5[1]"));

        // Name
        $this->http->GetURL("https://www.tscelite.com/profile/");
        $this->SetProperty("Name", $this->http->FindSingleNode("//input[@id = 'first_name']/@value") . " " . $this->http->FindSingleNode("//input[@id = 'last_name']/@value"));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return false;
    }
}
