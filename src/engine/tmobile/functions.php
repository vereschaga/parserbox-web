<?php

class TAccountCheckerTmobile extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FormURL = "https://my.t-mobile.com/Login/LoginController.aspx";
        $this->http->Form['txtMSISDN'] = $this->AccountFields['Login'];
        $this->http->Form['txtPassword'] = $this->AccountFields['Pass'];
        $this->http->setDefaultHeader('Referer', 'https://my.t-mobile.com/Login/');

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        $error_block = $this->http->FindSingleNode('//div[@id="Login1_pnlError"]');

        if ($error_block) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = $error_block;

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $this->http->GetURL('https://my.t-mobile.com/Services/account/accountservice.svc/GetPrepaidMonthlyBillingData?_=1303927083466&%22%22');
        $response_object = json_decode($this->http->Response['body'], true);
        $this->SetProperty("Account", $response_object['Number']);
        $this->SetProperty("RatePlanName", $response_object['RatePlanName']);
        $this->SetProperty("PrepaidBalance", $response_object['PrepaidBalance']);
        $this->SetProperty("UseBy", $response_object['BalanceExpirationDate']);

        if ($response_object['PMPGoldStatusCustomer'] == 'Gold') {
            $this->SetProperty("MemberStatus", $response_object['PMPGoldStatusCustomer'] . ' Rewards');
        }
        $this->SetBalance($response_object['MinutesRemaining']);
    }
}
