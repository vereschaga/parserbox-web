<?php

class TAccountCheckerOutback extends TAccountChecker
{
    public function SetFormParam($a, $b)
    {
        if (isset($this->http->Form[$a])) {
            if ($this->http->Form[$a] == $b) {
                $this->http->Log("Param[$a]:same[$b]");
            } else {
                $this->http->Log("Param[$a]:[{$this->http->Form[$a]}]=>[$b]");
            }
        }
        $this->http->Form[$a] = $b;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('http://www.myoutbackrewards.com/');

        if (!$this->http->ParseForm("login_form")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('email', $this->AccountFields["Login"]);
        $this->http->SetInputValue('password', $this->AccountFields["Pass"]);
        $this->SetFormParam('login.x', '21');
        $this->SetFormParam('login.y', '13');

        return true;
    }

    public function checkErrors()
    {
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'currently unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'This site is currently down for maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error occurred while processing your request
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# As of August 19, 2013, the My Outback Rewards Program has ended
        if ($message = $this->http->FindPreg("/(As of August 19, 2013, the My Outback Rewards Program has ended\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Access is allowed
        if ($this->http->FindSingleNode("//a[@id = 'sign_out']/@id")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[@class="forgot_pass"]')) {
            throw new CheckException("Invalid password", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[@class="error"]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Balance
        $this->SetBalance($this->http->FindSingleNode('//span[@id="points_num"]', null, true));
        //# Name
        $this->SetProperty('Name', $this->http->FindSingleNode('//div[@id="greeting"]/text()[1]', null, true, '/Welcome, ([^!]*)/'));

        if ($cid = $this->http->FindSingleNode("//form[@name = 'top_nav_form']//input[@name = 'cid']/@value")) {
            $this->http->GetURL("http://www.myoutbackrewards.com/account_overview?cid=" . $cid);
            //# Total points earned
            $this->SetProperty('TotalPointsEarned', $this->http->FindSingleNode("//label[contains(text(), 'total points earned')]/following-sibling::div[1]"));
            //# Total points spent
            $this->SetProperty('TotalPointsSpent', $this->http->FindSingleNode("//label[contains(text(), 'total points spent')]/following-sibling::div[1]"));
            //# Total rewards redeemed
            $this->SetProperty('TotalRewardsRedeemed', $this->http->FindSingleNode("//label[contains(text(), 'total rewards redeemed')]/following-sibling::div[1]"));

            //# Full Name
            $this->http->PostURL("http://www.myoutbackrewards.com/update_my_profile", ['cid' => $cid]);
            $name = CleanXMLValue($this->http->FindSingleNode("//input[@name = 'first_name']/@value") . ' ' . $this->http->FindSingleNode("//input[@name = 'last_name']/@value"));

            if (strlen($name) > 2) {
                $this->SetProperty('Name', beautifulName($name));
            }
        } else {
            $this->http->Log(">>> cid is not found");
        }

        // refs #6652
        $this->SetExpirationDate(mktime(0, 0, 0, 8, 19, 2013));
    }
}
