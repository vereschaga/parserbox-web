<?php

// ritz like marriott
class TAccountCheckerRitz extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->setCookie("EUCookieShowOnce", "true", "rewards.ritzcarlton.com");
        $this->http->GetURL("https://rewards.ritzcarlton.com/signIn.mi");

        if (!$this->http->ParseForm("sign-in-form")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('j_username', "rewardsWebService@" . preg_replace('/\s*/', '', $this->AccountFields['Login']));
        $this->http->Form["userNamePrefix"] = "rewardsWebService@";
        $this->http->SetInputValue('visibleUserName', preg_replace('/\s*/', '', $this->AccountFields['Login']));
        $this->http->SetInputValue('j_password', $this->AccountFields['Pass']);
        $this->http->Form["remember_me"] = "on";

        return true;
    }

    public function checkErrors()
    {
        //# Internal Server Error
        if ($error = $this->http->FindPreg("/(Internal Server Error)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# We’re sorry, there’s been an error in the system
        if ($error = $this->http->FindPreg('/(We\’re sorry, there\’s been an error in the system\.)/ims')) {
            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Access is successful
        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        if ($error = $this->http->FindSingleNode("//span[contains(@class, 'errorText')]/text()")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        if ($error = $this->http->FindSingleNode("//div[@class='signInError']/span[contains(@class, 'redText')]")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        //# Account information is not available
        if ($error = $this->http->FindSingleNode("//td[contains(text(), 'We are unable to display your account information')]/text()[1]")) {
            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Change Password')]")
            && strstr($this->http->currentUrl(), 'changePasswordChallenge')) {
            throw new CheckException("Ritz-Carlton (Rewards) website is asking you to change your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//li[contains(text(), 'Rewards #')]/preceding-sibling::li[3]")));
        //# Rewards #
        $this->SetProperty("RewardsNumber", $this->http->FindSingleNode("//li[contains(text(), 'Rewards #')]", null, true, '/\#:\s*([^<]+)/ims'));
        //# Status
        $this->SetProperty('Status', $this->http->FindSingleNode("//li[contains(text(), 'Rewards #')]/preceding-sibling::li[1]"));
        //# Balance
        $this->SetBalance($this->http->FindSingleNode("//li[contains(text(), 'Rewards #')]/preceding-sibling::li[2]", null, true, "/(.*)Point/ims"));

        //# Nights this year
        $this->SetProperty("NightsThisYear", $this->http->FindSingleNode("//dt[contains(text(), 'Total this year:')]/following-sibling::dd[1]"));
        //# Nights Stayed
        $this->SetProperty("NightsStayed", $this->http->FindSingleNode("//span[contains(text(), 'Stayed:')]/following-sibling::span[1]"));
        //# Bonus Nights Earned
        $this->SetProperty("BonusNightsEarned", $this->http->FindSingleNode("//span[contains(text(), 'Bonus:')]/following-sibling::span[1]"));
        //# Nights Needed to Achieve Next Level
        $this->SetProperty('NightsToAchieveNextLevel', $this->http->FindSingleNode("//p[contains(text(), 'Nights Needed to Achieve Next Level')]", null, true, '/(\d+)\s+Nights Needed to Achieve Next Level/ims'));
        //# Nights Needed to Renew Level
        $this->SetProperty('NightsToRenewLevel', $this->http->FindSingleNode("//p[contains(text(), 'Nights Needed to Renew Level')]", null, true, '/(\d+)\s+Nights Needed to Renew Level/ims'));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://rewards.ritzcarlton.com/signIn.mi';
        $arg['SuccessURL'] = 'https://rewards.ritzcarlton.com/ritz/rewards/myAccount/activity.mi';

        return $arg;
    }
}
