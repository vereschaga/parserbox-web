<?php

class TAccountCheckerSurveyspree extends TAccountChecker
{
    public function LoadLoginForm()
    {
        // reset cookie
        $this->http->removeCookies();
        $this->http->GetURL("http://surveyspree.com");

        if (!$this->http->ParseForm(null, "//form[@action = '/#memberLogin']")) {
            return $this->checkErrors();
        }
        // form data to a format string
        $this->http->SetFormText('hdMode=login', '&');
        // enter the login and password
        $this->http->SetInputValue('txtEmail', $this->AccountFields['Login']);
        $this->http->SetInputValue('txtPassword', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        //site maintenance error
        if ($message = $this->http->FindSingleNode('//td[@class="textTD" and @Title="English"]', null, false)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(We are currently upgrading our website)/ims")) {
            throw new CheckException($message . " We apologize for any inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }
        // The survey panel you are looking for has been shut down.
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'The survey panel you are looking for has been shut down.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // form submission
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // check for invalid password
        if ($message = $this->http->FindSingleNode("//*[@class='error_msg']/text()")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // login successful
        if ($this->http->FindSingleNode("//*[@id='btnLogOut']")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->PostURL("http://surveyspree.com/ajaxRewardBoxV2.php", []);
        $balance = $this->http->FindSingleNode("//*[@id='currBal']/h2/text()");
        // set balance
        $this->SetBalance($balance);

        $this->http->GetURL("http://surveyspree.com/my_rewards.php");
        //# Total Earned
        $this->SetProperty("TotalEarned", $this->http->FindSingleNode("//td[contains(text(), 'Total Earned')]/following-sibling::td"));
        //# Total Available
        $this->SetProperty("TotalAvailable", $this->http->FindSingleNode("//td[contains(text(), 'Total Available')]/following-sibling::td"));
        //# Total Pending
        $this->SetProperty("TotalPending", $this->http->FindSingleNode("//td[contains(text(), 'Total Pending')]/following-sibling::td"));
        //# Redemption Threshold
        $this->SetProperty("RedemptionThreshold", $this->http->FindSingleNode("//td[contains(text(), 'Redemption Threshold') and contains(@class, 'cellBorder')]/following-sibling::td"));
        //# Needed to Redeem for Rewards
        $this->SetProperty("NeededToRedeem", $this->http->FindSingleNode("//td[contains(text(), 'Needed to Redeem') and contains(@class, 'cellBorder')]/following-sibling::td"));
        //# Total redemption amount
        $this->SetProperty("TotalAmount", $this->http->FindSingleNode("//td[contains(text(), 'Total Redemption Amount')]/following-sibling::td"));
        //# Sweepstakes Entries
        $this->SetProperty("SweepstakesEntries", $this->http->FindSingleNode("//td[contains(text(), 'Sweepstakes Entries') and contains(@class, 'cellBorder')]/following-sibling::td"));

        //# Name
        $this->http->GetURL("http://surveyspree.com/my_account.php");
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//td[contains(text(), 'first name')]/following-sibling::td") . ' ' . $this->http->FindSingleNode("//input[contains(@name, 'txtLname')]/@value")));
    }
}
