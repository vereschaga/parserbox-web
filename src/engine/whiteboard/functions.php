<?php

class TAccountCheckerWhiteboard extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.thewhiteboard.com/");

        if (!$this->http->ParseForm('frmLogin')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('txtEmail', $this->AccountFields['Login']);
        $this->http->SetInputValue('txtPassword', $this->AccountFields['Pass']);
        $this->http->Form['hdMode'] = 'login';

        return true;
    }

    public function checkErrors()
    {
        //# Server Problem
        if ($message = $this->http->FindPreg("/(Server Problem - Please hit your browser refresh button and try again\.)/ims")) {
            throw new CheckException("Server Problem. Please try again later", ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'We are currently upgrading our website')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // The survey panel you are looking for has been shut down.
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'The survey panel you are looking for has been shut down.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            $this->http->GetURL("http://www.thewhiteboard.com/index.php");
        }
//            return $this->checkErrors();

        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'mode=logout')]/@href")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error_msg_wrapper')]/div[contains(@class, 'error_msg')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Welcome back
        $this->SetProperty("Name", $this->http->FindSingleNode("//p[contains(@class, 'optionalWelcomeMsg')]/strong", null, true, '/Welcome back, ([^\.]+)\./ims'));

        $this->http->PostURL('http://www.thewhiteboard.com/ajaxRewardBoxV2.php', []);
        // Current Balance
        $this->SetBalance($this->http->FindSingleNode("//div[contains(@id, 'currBal')]/h2", null, true, '/([\d\.,]+)/ims'));

        $this->http->GetURL("http://www.thewhiteboard.com/my_rewards.php");

        // Total Earned
        $this->SetProperty("TotalEarned", $this->http->FindSingleNode("//td[@class='Box-border-my-rewards']//td[contains(text(), 'Total Earned')]/following::td[1]"));
        // Total Available
        $this->SetProperty("TotalAvailable", $this->http->FindSingleNode("//td[@class='Box-border-my-rewards']//td[contains(text(), 'Total Available')]/following::td[1]"));
        // Total Pending
        $this->SetProperty("TotalPending", $this->http->FindSingleNode("//td[@class='Box-border-my-rewards']//td[contains(text(), 'Total Pending')]/following::td[1]"));
        // Redemption Threshold
        $this->SetProperty("RedemptionThreshold", $this->http->FindSingleNode("//td[@class='Box-border-my-rewards']//td[contains(text(), 'Redemption Threshold')]/following::td[1]"));
        // Needed to Redeem for Rewards
        $this->SetProperty("NeededToRedeemForRewards", $this->http->FindSingleNode("//td[@class='Box-border-my-rewards']//td[contains(text(), 'Needed to Redeem for Rewards')]/following::td[1]"));
        // Total redemption amount
        $this->SetProperty("TotalRedemptionAmount", $this->http->FindSingleNode("//td[@class='Box-border-my-rewards']//td[contains(text(), 'Total Redemption Amount')]/following::td[1]"));
        // Sweepstakes Entries
        $this->SetProperty("SweepstakesEntries", $this->http->FindSingleNode("//td[@class='Box-border-my-rewards']//td[contains(text(), 'Sweepstakes Entries')]/following::td[1]"));

        //# Full Name
        $this->http->GetURL("https://www.thewhiteboard.com/my_account.php");
        $name = CleanXMLValue($this->http->FindSingleNode("//td[contains(text(), 'first name')]/following-sibling::td[1]")
                . ' ' . $this->http->FindSingleNode("//input[@name = 'txtLname']/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }
}
