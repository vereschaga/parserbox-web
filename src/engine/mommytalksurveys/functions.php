<?php

class TAccountCheckerMommytalksurveys extends TAccountChecker
{
    private $Logins = 0;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.mommytalksurveys.com/");

        if (!$this->http->ParseForm("frmLogin")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('txtEmail', $this->AccountFields['Login']);
        $this->http->SetInputValue('txtPassword', $this->AccountFields['Pass']);
        $this->http->Form['hdMode'] = 'login';

        return true;
    }

    public function checkErrors()
    {
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'We are currently upgrading our website')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Problem
        if ($this->http->FindPreg("/(Server Problem - Please hit your browser refresh button and try again)/ims")) {
            throw new CheckException("Server Problem - Please try again later.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        // You are already member of MommyTalkSurveys.com. Please login to take surveys.
        if ($this->http->FindPreg("/(You are already member of MommyTalkSurveys\.com\. Please)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href", null, true, null, 0)) {
            return true;
        }

        //# Invalid credentials
        if ($message = $this->http->FindSingleNode('//td[@class="error_msg"]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Last Step: The information below will assist us in determining the best survey to take you to.
        if ($message = $this->http->FindPreg('/The information below will assist us in determining the best survey to take you to\./ims')) {
            throw new CheckException("MommyTalk Surveys website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        $this->checkErrors();

        //# Retry autorization
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Please reauthenticate yourself')]") && $this->Logins < 3) {
            $this->http->Log("Message => $message", true);
            $this->http->Log("Retry autorization $this->Logins", true);
            $this->Logins++;
            sleep(10);

            if ($this->LoadLoginForm()) {
                if ($this->Login()) {
                    $this->Parse();
                }
            }
        }

        return $this->checkErrors();
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['TotalAvailable']) && (strpos($properties['TotalAvailable'], '$') === false)) {
            return $properties['TotalAvailable'];
        } else {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }
    }

    public function Parse()
    {
        $this->http->getURL('https://www.mommytalksurveys.com/my_rewards.php');

        //# Reward history is currently unavailable
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Your reward history is currently unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        //# Total Earned
        $this->SetProperty("Totalearned", $this->http->FindSingleNode('//td[contains(text(), "Total Earned") and not(contains(@class, "black_bold" ))]/following::td[1]'));

        //# Balance - Total Available
        $this->SetBalance($this->http->FindSingleNode('//td[contains(text(), "Total Available") and not(contains(@class, "black_bold" ))]/following::td[1]', null, true, "/([\d\.\,]+)/ims"));
        $this->SetProperty("TotalAvailable", $this->http->FindSingleNode('//td[contains(text(), "Total Available") and not(contains(@class, "black_bold" ))]/following::td[1]', null, true, "/([^\(]+)/ims"));

        //# Total Pending
        $this->SetProperty("Totalpending", $this->http->FindSingleNode('//td[contains(text(), "Total Pending") and not(contains(@class, "black_bold" ))]/following::td[1]'));
        //# Redemption Threshold
        $this->SetProperty("RedemptionThreshold", $this->http->FindSingleNode('//td[contains(text(), "Redemption Threshold") and not(contains(@class, "black_bold" ))]/following::td[1]'));
        //# Needed to Redeem for Rewards
        $this->SetProperty("NeededForRewards", $this->http->FindSingleNode('//td[contains(text(), "Needed to Redeem for Rewards") and not(contains(@class, "black_bold" ))]/following::td[1]'));
        //# Total redemption amount
        $this->SetProperty("TotalRedemptionAmount", $this->http->FindSingleNode('//td[contains(text(), "Total Redemption Amount") and not(contains(@class, "black_bold" ))]/following::td[1]'));
        //# Sweepstakes Entries
        $this->SetProperty("Sweepstakesentries", $this->http->FindSingleNode('//td[contains(text(), "Sweepstakes Entries") and not(contains(@class, "black_bold" ))]/following::td[1]'));

        //# Full Name
        $this->http->getURL('https://www.mommytalksurveys.com/my_account.php');
        $name = $this->http->FindSingleNode("//td[contains(text(), 'first name')]/following-sibling::td") . ' ' . $this->http->FindSingleNode("//input[@name = 'txtLname']/@value");

        if (strlen(CleanXMLValue($name)) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }
}
