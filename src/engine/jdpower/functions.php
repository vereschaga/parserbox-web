<?php

class TAccountCheckerJdpower extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.jdpowerpanel.com/");

        if (!$this->http->ParseForm("frmLogin")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('txtEmail', $this->AccountFields['Login']);
        $this->http->SetInputValue('txtPassword', $this->AccountFields['Pass']);
        $this->http->Form['action'] = 'index.php';
        $this->http->Form['hdMode'] = 'login';

        return true;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['TotalEarned']) && preg_match('/^€/ims', $properties['TotalEarned'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");
        } elseif (isset($properties['TotalEarned']) && preg_match('/^£/ims', $properties['TotalEarned'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");
        } else {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }
    }

    public function checkErrors()
    {
        // Maintenance
        if ($message = $this->http->FindSingleNode('//td[contains(text(), "We are currently upgrading our website and we")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Problem - Please hit your browser refresh button and try again.
        if ($this->http->FindPreg("/Server Problem - Please hit your browser refresh button and try again\./")
            // No server is available to handle this request.
            || $this->http->FindPreg("/No server is available to handle this request\./")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($error = $this->http->FindPreg("/(Please enter correct email address and password)/ims")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg('/(id="divRewardDetails")/ims')) {
            return true;
        }
        //# Your account has been successfully closed.
        if ($error = $this->http->FindPreg("/(Your account has been successfully closed\.)/ims")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance is not at the default URL
        $this->http->GetURL("http://www.jdpowerpanel.com/my_rewards.php");
        // Balance - Total Available
        $this->SetBalance($this->http->FindSingleNode("//td[contains(text(), 'Total Available') and contains(@class, 'cellBorder')]/following::td[1]", null, true, "/([\d\.\,]+)/ims"));
        // Total Earned
        $this->SetProperty("TotalEarned", $this->http->FindSingleNode("//td[contains(text(), 'Total Earned') and contains(@class, 'cellBorder')]/following::td[1]"));
        // Total Available
        $this->SetProperty("TotalAvailable", $this->http->FindSingleNode("//td[contains(text(), 'Total Available') and contains(@class, 'cellBorder')]/following::td[1]"));
        // Total Pending
        $this->SetProperty("TotalPending", $this->http->FindSingleNode("//td[contains(text(), 'Total Pending') and contains(@class, 'cellBorder')]/following::td[1]"));
        // Redemption Threshold
        $this->SetProperty("RedemptionThreshold", $this->http->FindSingleNode("//td[contains(text(), 'Redemption Threshold') and contains(@class, 'cellBorder')]/following::td[1]"));
        // Needed to Redeem for Rewards
        $this->SetProperty("NeededToRedeemForRewards", $this->http->FindSingleNode("//td[contains(text(), 'Needed to Redeem for Rewards') and contains(@class, 'cellBorder')]/following::td[1]"));
        // Total Redemption Amount
        $this->SetProperty("TotalRedemptionAmount", $this->http->FindSingleNode("//td[contains(text(), 'Total Redemption Amount') and contains(@class, 'cellBorder')]/following::td[1]"));
        // Sweepstakes Entries
        $this->SetProperty("SweepstakesEntries", $this->http->FindSingleNode("//td[contains(text(), 'Sweepstakes Entries') and contains(@class, 'cellBorder')]/following::td[1]"));
        // Collecting Name
        $this->http->GetURL("http://www.jdpowerpanel.com/my_account.php");
        $n = $this->http->FindPreg('#Your first name:</td>.*?<td.*?>\s*(.*?)\s#ims');
        $l = $this->http->FindPreg('#<input name="txtLname".*?value="(.*?)"#ims');
        $this->SetProperty("Name", beautifulName($n . ' ' . $l));
    }
}
