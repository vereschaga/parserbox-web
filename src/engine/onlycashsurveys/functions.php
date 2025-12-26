<?php

class TAccountCheckerOnlycashsurveys extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FormURL = "http://panel.onlycashsurveys.com/index.php";
        $this->http->GetURL($this->http->FormURL);

        if (!$this->http->ParseForm('frmLogin')) {
            return $this->checkErrors();
        }
        $this->http->Form['hdMode'] = 'login';
        $this->http->SetInputValue('txtEmail', $this->AccountFields['Login']);
        $this->http->SetInputValue('txtPassword', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'We are currently upgrading our website')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Problem
        if ($message = $this->http->FindSingleNode("//body[text() = 'Server Problem - Please hit your browser refresh button and try again.']")) {
            throw new CheckException('Server Problem - Please try again later.', ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        // The survey panel you are looking for has been shut down.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'The survey panel you are looking for has been shut down.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindPreg("/logout.jpg/")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[@class="error_msg"]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('/To activate your account/ims')) {
            throw new CheckException('Only Cash Surveys website is asking you to update your profile,
            until you do so we would not be able to retrieve your account information.', ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//strong[contains(text(), 'Welcome back')]", null, true, '/Welcome back, (\w+)./')));

        $this->http->PostURL("http://panel.onlycashsurveys.com/ajaxRewardBoxV2.php", []);
        //# Balance - Total Earnings
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'Current Balance')]/following-sibling::h2[1]", null, true, '/(\d+.\d+)/ims'));

        $this->http->GetURL("http://panel.onlycashsurveys.com/my_rewards.php");
        //# Total Earned
        $this->SetProperty("TotalEarned", $this->http->FindSingleNode("//td[contains(text(), 'Total Earned')]/following-sibling::td"));
        //# Total Pending
        $this->SetProperty("TotalPending", $this->http->FindSingleNode("//td[contains(text(), 'Total Pending')]/following-sibling::td"));
        //# Redemption Threshold
        $this->SetProperty("RedemptionThreshold", $this->http->FindSingleNode("//td[contains(text(), 'Redemption Threshold') and contains(@class, 'cellBorder')]/following-sibling::td"));
        //# Needed to Redeem for Rewards
        $this->SetProperty("NeededToRedeem", $this->http->FindSingleNode("//td[contains(text(), 'Needed to Redeem') and contains(@class, 'cellBorder')]/following-sibling::td"));
        //# Total redemption amount
        $this->SetProperty("TotalAmount", $this->http->FindSingleNode("//td[contains(text(), 'Total redemption amount') or contains(text(), 'Total Redemption Amount')]/following-sibling::td"));

        $this->http->GetURL("http://panel.onlycashsurveys.com/my_account.php");
        //# Full Name
        $name = CleanXMLValue($this->http->FindSingleNode("//td[contains(text(), 'first name')]/following-sibling::td") . ' ' . $this->http->FindSingleNode("//input[@name = 'txtLname']/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['TotalEarned']) && preg_match('/^€/', $properties['TotalEarned'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");
        } else {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }
    }
}
