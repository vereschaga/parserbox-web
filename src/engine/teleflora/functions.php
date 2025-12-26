<?php

class TAccountCheckerTeleflora extends TAccountChecker
{
    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.teleflora.com';

        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.teleflora.com');
        $this->http->GetURL('https://www.teleflora.com/account/login.jsp');

        if (!$this->http->ParseForm('loginfileForm')) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://www.teleflora.com/account/login.jsp?_DARGS=/account/login.jsp.loginfileForm';
        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('logInfileBtn', "Log In To Account");
        $this->http->SetInputValue('_D:logInfileBtn', "");
        $this->http->SetInputValue('/atg/userprofiling/ProfileFormHandler.loginErrorURL', '/account/login.jsp?message=loginFailed');
        $this->http->SetInputValue('_D:/atg/userprofiling/ProfileFormHandler.loginErrorURL', '');
        $this->http->SetInputValue('_DARGS', '/account/login.jsp.loginfileForm');
        $this->http->SetInputValue('checkbox1', 'true');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        // loop
        if ($this->http->currentUrl() == 'https://www.teleflora.com/account/login.jsp' && $this->http->Response['code'] == 301) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // provider error: empty body
        if (stristr($this->http->currentUrl(), 'http://www.teleflora.com:80/?_requestid=') && $this->http->Response['code'] == 200
            && empty($this->http->Response['body'])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm(['Content-Type' => 'application/x-www-form-urlencoded'])) {
            return $this->checkErrors();
        }
        // The entered username or password is invalid
        if ($message = $this->http->FindPreg('/(The entered username or password is invalid)/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid credentials
        if ($message = $this->http->FindPreg('/User not registered for the site/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The password is incorrect
        if ($message = $this->http->FindPreg('/(The password is incorrect)/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // successful login
        if ($this->http->FindSingleNode("//a[contains(text(), 'Log out')]")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.teleflora.com/rewards/rewards.jsp');
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[@id = "accInfoPopup"]//div[contains(@class, "is-loggedin")]')));

        $uid = $this->http->FindPreg("/var t_profileid = '([^\']+)/");
        $email = $this->http->FindPreg("/\{\"email\":\"([^\"]+)/");

        if (!$uid || !$email) {
            if (
                $uid
                && !empty($this->Properties['Name'])
                && $this->http->FindPreg("/var bluecore_customer_info = \{\"email\":\"\",\"sign_up_date\":\"\"\};/")
            ) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }

            return;
        }

//        $this->http->GetURL('https://app.zinrelo.com/end_user/auth_user?merchant_id=90f34b3f49&user_info='.urlencode('{"name":"","email":"'.$email.'","uid":"'.$uid.'","language":"","ts":""}'));
//        $response = $this->http->JsonLog($this->http->FindPreg("/authenticate_user_resp=([^;]+)/"));
        $this->http->GetURL('https://loyalty.yotpo.com/api/v1/customer_details?customer_email=' . urlencode($email) . '&customer_external_id=' . $uid . '&merchant_id=110082');
        $response = $this->http->JsonLog();
        // Point Balance - Balance
        if (
            !$this->SetBalance($response->points_balance ?? null)
            // AccountID: 3161243
            && $this->http->FindPreg('/\{"merchant_id":\d+,"perks":\[\],"point_redemptions":\[\],"campaigns":\[\],"referral_receipts":\[\],"email":"[^\"]+","referral_code":"[^\"]+","referral_discount_code":null,"referral_discount_code_id":null\}/')
        ) {
            $this->SetBalanceNA();

            return;
        }
        // Level
//        $this->SetProperty('Level', $response->user_data->loyalty_level_name ?? null);
        // Name
        $name = ($response->first_name ?? '') . " " . ($response->last_name ?? '');

        if (strlen($name) > 2) {
            $this->SetProperty('Name', beautifulName($name));
        }

        if ($response->points_expire_at != null) {
            $this->SetExpirationDate(strtotime($response->points_expire_at));
        }
    }
}
