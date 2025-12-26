<?php

class TAccountCheckerBevmo extends TAccountChecker
{
    use \AwardWallet\Common\OneTimeCode\OtcHelper;

    private $customerID;
    private $token;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            $this->sendNotification('refs #24523 bevmo - need to check 2fa on phone // IZ');
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://bevmo.com/account/login');
        $this->http->GetURL('https://fulfillment.partners.gopuff.com/shopify/v1/bevmo/account/login?shop=bevmo-ca.myshopify.com&redirect_url=https://bevmo.com');

        if (
            !$this->http->FindSingleNode('//title[contains(text(), "BevMo!")]')
            || $this->http->Response['code'] != 200
        ) {
            return $this->checkErrors();
        }

        return true;
    }

    public function Login()
    {
        if ($this->processQuestion()) {
            return false;
        }

        $authResult = $this->http->JsonLog();

        if (isset($authResult->error, $authResult->description)) {
            $message = $authResult->description;

            if (strstr($message, "Invalid email format")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $data = [
            'code'      => $answer,
            'recipient'	=> $this->AccountFields['Login'],
        ];

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $this->http->PostURL('https://goauth.gopuff.com/oauth/login-with-otp', $data, $headers);

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $first_name = $this->http->FindPreg('/first_name:\s`(.*)`/i');
        $last_name = $this->http->FindPreg('/last_name:\s`(.*)`/i');
        $this->SetProperty("Name", beautifulName("{$first_name} {$last_name}"));

        $data = [
            'customer_id' => $this->customerID,
            'token'	      => $this->token,
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => '*/*',
        ];

        $this->http->PostURL('https://fulfillment.partners.gopuff.com/shopify/v1/bevmo/account/rewards?shop=bevmo-ca.myshopify.com', json_encode($data), $headers);

        $programInfo = $this->http->JsonLog();

        // Balance - ClubBev! Points / Current Point Balance
        $this->setBalance($programInfo->reward_points);
        // Account
        $this->SetProperty("AccountNumber", $programInfo->bevmo_club_id);

        $coupons = $programInfo->coupons ?? [];

        foreach ($coupons as $coupon) {
            $this->AddSubAccount([
                'Code'           => $coupon->online_code,
                'DisplayName'    => $coupon->title,
                'Balance'        => null,
                'ExpirationDate' => strtotime($coupon->end_date),
            ]);
        }
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function processQuestion()
    {
        $this->logger->notice(__METHOD__);

        if ($this->getWaitForOtc()) {
            $this->sendNotification('refs #24523 bevmo - user with mailbox was found // IZ');
        }

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $data = [
            'delivery_channel' => 'EMAIL',
            'recipient'        => $this->AccountFields['Login'],
        ];

        $headers = [
            'Accept'       => '*/*',
            'Content-Type' => 'application/json',
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://goauth.gopuff.com/oauth/otp/send?client-id=bevmo', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        $questionData = $this->http->JsonLog();

        if (!isset($questionData->phone, $questionData->expireTime)) {
            $this->logger->debug('2fa is not present');

            return false;
        }

        $this->AskQuestion("To keep your account secure we need to make sure it's really you before you can login.");

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://bevmo.com/account');

        $email = $this->http->FindPreg('/email:\s"(.*)"/i');
        $phone = $this->http->FindPreg('/phone:\s"(.*)"/i');

        $this->logger->debug('EMAIL: ' . $email);
        $this->logger->debug('PHONE: ' . $phone);

        if (
            (isset($email) && $email === $this->AccountFields['Login'])
            || (isset($phone) && $phone === $this->AccountFields['Login'])
        ) {
            $this->customerID = $this->http->FindPreg('/id:\s(.*),/i');
            $this->token = $this->http->FindPreg('/customer[*\S\s]+token:\s`(.*)`/i');

            return true;
        }

        return false;
    }
}
