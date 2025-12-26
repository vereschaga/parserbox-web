<?php

class TAccountCheckerNintendo extends TAccountChecker
{
    // old
    /*	function IsLoggedIn(){
            $this->http->GetURL("https://club.nintendo.com/home.do");
            return $this->GetMemberSince() != null;
        }

        function GetMemberSince(){
            return $this->http->FindSingleNode('///p[@class="member-duration"]/node()[1]', null, true,'/Member since (.*)/ims');
        }*/

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://club.nintendo.com');
        /*
         * Club Nintendo has been discontinued
         *
         * Our heartfelt thanks to our members for your support over the years.
         * Please stay tuned for more information on our new loyalty program.
         */
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Club Nintendo has been discontinued.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (!$this->http->ParseForm("login-form")) {
            return $this->checkErrors();
        }
        $this->http->FormURL = "https://club.nintendo.com/api/authentication/login";
        $this->http->Form["username"] = $this->AccountFields['Login'];
        $this->http->Form["password"] = $this->AccountFields['Pass'];

        //		$this->http->setDefaultHeader('User-Agent','Mozilla/5.0 (Windows; U; Windows NT 6.1; ru; rv:1.9.2.15) Gecko/20110303 Firefox/3.6.15');

        return true;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindPreg("/is currently unavailable/ims")) {
            throw new CheckException("Club Nintendo is currently unavailable due to site maintenance.
We apologize for any inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        if ((isset($response->errors)) && (count($response->errors > 0))) {
            if (strstr($response->errors['0']->message, 'Due to high traffic volumes, the sign in function may not be working properly')) {
                throw new CheckException($response->errors['0']->message, ACCOUNT_PROVIDER_ERROR);
            } else {
                throw new CheckException($response->errors['0']->message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        if ((isset($response->success)) && ($response->success === "true")) {
            $this->http->getURL('https://club.nintendo.com/account.do');

            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Balance
        $this->SetBalance($this->http->FindSingleNode('//p[@class="coin-count"]', null, true, "/(\d+)/ims"));
        //# Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//p[@class = 'zeta']", null, true, "/Member since([^<]+)/ims"));
        //# Name
        $name = trim($this->http->FindSingleNode("//input[@id ='firstname']/@value") . ' ' . $this->http->FindSingleNode("//input[@id ='lastname']/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
        //# Status in current year
        $status = trim($this->http->FindSingleNode("//p[contains(@class, 'account-status')]", null, true, "/([^\(<]+)/ims"));
        $this->http->Log(var_export($status, true));

        switch ($status) {
            case 'Gold': $this->SetProperty("Status", "Gold");

break;

            case 'Platinum': $this->SetProperty("Status", "Platinum");

break;

            default: $this->SetProperty("Status", "Member");

break;
        }
        //# Elite Status Coins in current year
        $this->SetProperty("EliteStatusCoins", $this->http->FindSingleNode("//div[contains(@class, 'coins-til')]", null, true, "/(\d+)/ims"));

        // Expiration Date  // refs #9142
        if ($this->http->currentUrl() != "https://club.nintendo.com/account.do") {
            $this->http->GetURL("https://club.nintendo.com/account.do");
        }
        // Expiring Balance
        $coinsExpiring = $this->http->FindSingleNode("//span[contains(text(), 'Coins expiring')]", null, true, "/(\d+)/ims");
        $this->SetProperty("ExpiringBalance", $coinsExpiring);
        // Expiration Date
        $exp = $this->http->FindSingleNode("//span[contains(text(), 'Coins expiring')]/parent::p", null, true, "/expiring\s*on\s*([^<]+)/ims");
        // All Coins will be deleted when Club Nintendo accounts are closed on July 1, 2015.
        if (!strtotime($exp) || strtotime($exp) > strtotime("July 1, 2015")) {
            $exp = "July 1, 2015";
        }

        $this->http->Log("$coinsExpiring Coins expiring on $exp / " . strtotime($exp));

        if ((!empty($coinsExpiring) && strtotime($exp)) || strtotime("July 1, 2015") == strtotime($exp)) {
            $this->SetExpirationDate(strtotime($exp));
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        return [
            "URL"           => 'https://club.nintendo.com/home.do',
            "RequestMethod" => "POST",
            "PostValues"    => $this->http->Form,
        ];
    }
}
