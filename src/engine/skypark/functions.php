<?php

class TAccountCheckerSkypark extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.skypark.com/customer/login/");

        if (!$this->http->ParseForm("login_page_0")) {
            return false;
        }
        $this->http->FormURL = 'https://www.skypark.com/wp-content/plugins/netPark/ajax.php';
        $this->http->SetInputValue("login", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("action", "np_ajax");
        $this->http->SetInputValue("method", "VerifyLogin");

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindPreg("/\"data\":\{\"status\":\"success\"/ims")) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindPreg("/(Account and password do not match\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Account is disabled
        if ($message = $this->http->FindPreg("/\"(Account is disabled)\"/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL("https://www.skypark.com/customer/profile/");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@id ='first_name_p1']") . ' ' . $this->http->FindSingleNode("//span[@id ='last_name_p1']")));
        // Customer #
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("(//span[@class = 'customer_id'])[1]"));
        // Join Date
        $this->SetProperty("JoinDate", $this->http->FindSingleNode("//span[contains(text(), 'Join Date:')]/following-sibling::span"));
        // Balance - Point Balance
        $this->SetBalance($this->http->FindSingleNode("//span[@class = 'fpp_points']"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // account without Balance (AccountID: 1294097)
            if (!empty($this->Properties['Name']) && isset($this->Properties['AccountNumber']) && isset($this->Properties['JoinDate'])) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'http://www.skypark.com/customer/login/';
        $arg['SuccessURL'] = 'https://www.skypark.com/customer/profile/';

        return $arg;
    }
}
