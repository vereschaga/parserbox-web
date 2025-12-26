<?php

class TAccountCheckerTripalertz extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        /*$this->http->GetURL("http://www.tripalertz.com/login/");
        if(!$this->http->ParseForm("login_form"))
            return false;

        $this->http->Form["data[User][username]"] = $this->AccountFields['Login'];
        $this->http->Form["data[User][password]"] = $this->AccountFields['Pass'];*/

        return true;
    }

    public function checkErrors()
    {
        if ($this->http->FindPreg("/The requested URL \/ajax\/login\/ was not found on this server\./ims")
            || $this->http->FindSingleNode("//h1[contains(text(), '500 Internal Server Error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        //$this->http->PostForm();
        $this->http->GetURL("http://www.tripalertz.com/ajax/login/?rand=" . rand() . "&username=" . urlencode($this->AccountFields['Login']) . "&password=" . "&password=" . urlencode($this->AccountFields['Pass']) . "");
        $response = $this->http->JsonLog();

        if (!isset($response->msg)) {
            return $this->checkErrors();
        }

        $msg = $response->msg;

        //# Access is allowed
        if ($msg == "Login Successful") {
            return true;
        }
        //# Invalid Login
        elseif ($msg == "Invalid Login") {
            throw new CheckException($msg, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("http://www.tripalertz.com/user/index");
        $name = $this->http->FindSingleNode("//input[@id ='first_name']/@value") . ' ' . $this->http->FindSingleNode("//input[@id ='last_name']/@value");

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }

        $this->http->GetURL("http://www.tripalertz.com/trip_cash/");
        //# Balance - My Trip Cash
        $this->SetBalance($this->http->FindSingleNode("//span[contains(@class, 'trip-cash-value')]/text()"));

        //# Lifetime Trip Cash Earned
        $this->SetProperty("LifetimeTripCashEarned", $this->http->FindPreg("/Lifetime Trip Cash Earned<\/b>\s*<[^>]+>([^<]+)/ims"));
        //# Trip Cash Used or Expired
        $this->SetProperty("TripCashUsedOrExpired", $this->http->FindPreg("/Trip Cash Used or Expired<\/b>\s*<[^>]+>([^<]+)/ims"));
        //# Trip Cash Still Available
        $this->SetProperty("TripCashStillAvailable", $this->http->FindPreg("/Trip Cash Still Available<\/b>\s*<[^>]+>([^<]+)/ims"));
        //# Friends who have joined
        $this->SetProperty("Friends", $this->http->FindPreg("/Friends who have joined:\s*([^<]+)/ims"));
        //# Email invites who have not joined
        $this->SetProperty("Email", $this->http->FindPreg("/Email invites who have not joined:\s*([^<]+)/ims"));
    }

    /*	function GetRedirectParams($targetURL = NULL){
            $arg = parent::GetRedirectParams($targetURL);
            $arg['CookieURL'] = 'http://www.tripalertz.com/login/';
            $arg['SuccessURL'] = 'http://www.tripalertz.com/trip_cash/';
            return $arg;
        }*/
}
