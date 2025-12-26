<?php

// refs #6168
use AwardWallet\Engine\ProxyList;

class TAccountCheckerWoolworths extends TAccountChecker
{
    use ProxyList;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerWoolworthsSelenium.php";

        return new TAccountCheckerWoolworthsSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->SetProxy($this->proxyAustralia());
        $this->http->setRandomUserAgent();
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.woolworthsrewards.com.au/login.html');
        $this->http->GetURL('https://accounts.woolworthsrewards.com.au/secure/login.html');

        if (!$this->http->ParseForm(null, 1, true, '//form[contains(@action, "/secure/login/submit")]')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->PostForm();

//        $this->http->RetryCount = 0;
//        $this->http->PostURL('https://prod.api-wr.com/wx/v2/security/login/rewards', json_encode([
//            'remember' => true,
//            'username' => $this->AccountFields['Login'],
//            'password' => substr($this->AccountFields['Pass'], 0, 20),// AccountID: 3593696
//        ]), [
//            'Accept'       => 'application/json, text/plain, */*',
//            'Content-Type' => 'application/json;charset=utf-8',
//            'origin'       => 'https://www.woolworthsrewards.com.au',
//        ]);
//        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (empty($response)) {
            return $this->checkErrors();
        }

        if (!empty($response->data->bearer)) {
            $this->http->setDefaultHeader('Authorization', 'Bearer ' . $response->data->bearer);

            return true;
        }// if (!empty($response->data->bearer))

        $message = $response->errors[0]->message ?? null;

        switch ($message) {
            case strpos($message, 'username or password provided were incorrect') !== false:
            case strpos($message, 'Multiple accounts were found') !== false:
            case strpos($message, 'You do not have an active EDR card. Please register.') !== false:
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

                break;
            // Your password has expired.
            case strpos($message, 'Your password has expired. Please click ‘Reset Password’') !== false:
                throw new CheckException('Your password has expired.', ACCOUNT_INVALID_PASSWORD);

                break;

            case strpos($message, 'Cannot login with email as the user name. Please use Card No') !== false:
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

            case strpos($message, 'Your account has been disabled. Please call our contact centre') !== false:
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

                break;

            case strpos($message, 'Apologies, we are unable to process your request at the moment') !== false:
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

                break;
            // Your account has been locked as a security precaution. Please reset your password using the link below.
            case strpos($message, 'Your account has been locked as a security precaution.') !== false:
                throw new CheckException('Your account has been locked as a security precaution.', ACCOUNT_LOCKOUT);

                break;

            case strpos($message, 'Server Busy') !== false:
            case strpos($message, 'Request is blocked.') !== false:
            case strpos($message, '"Execution of js_extract_customer failed with error: Javascript runtime error:') !== false:
                $this->DebugInfo = $message;

                throw new CheckRetryNeededException(2);

                break;
        }// switch ($message)

        return false;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Sorry, we are unable to process your request right now. Please try again later.
        if ($this->http->Response['code'] = 500 && strpos($this->http->Response['body'], 'Problem routing to https://prd-idm-services-vip/cust/idm/identitymanager/services/IdentityManagerPort. Error msg: Unable to obtain HTTP response from') !== false) {
            throw new CheckException('Sorry, we are unable to process your request right now. Please try again later.', ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, the page you are looking for is currently unavailable
        if ($this->http->FindSingleNode("//p[contains(text(), 'Sorry, the page you are looking for is currently unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
    }

    public function Parse()
    {
//        $this->http->GetURL('https://prod.api-wr.com/wx/v1/member/profile');
//        $profile = $this->http->JsonLog();
//        // Name
//        if (!isset($profile->member->firstName) || !isset($profile->member->lastName))
//            return;
//        $this->SetProperty('Name', beautifulName(trim($profile->member->firstName.' '.$profile->member->lastName)));

        $this->http->GetURL('https://prod.api-wr.com/wx/v1/member/accounts/rewards/cards?addname=true');
        $response = $this->http->JsonLog();

        if ($this->http->Response['code'] == 500 && $this->http->FindPreg('/"message":"Cannot read property \'data\' of undefined"/')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (empty($response)) {
            return;
        }
        $cards = $response->data->cards ?? [];

        foreach ($cards as $card) {
            if ($card->type == 'loyalty_card') {
//            if ($card->firstName == $profile->member->firstName && $card->lastName == $profile->member->lastName) {
                // Primary card
                $this->SetProperty('CardNumber', $card->number);
                // Name
                $this->SetProperty('Name', beautifulName(trim($card->firstName . ' ' . $card->lastName)));

                break;
            }// if ($card->holderType == 'secondary')
        }// foreach ($cards as $card)

        $this->http->GetURL("https://prod.api-wr.com/wx/v1/member/points");
        $response = $this->http->JsonLog();

        if (empty($response->data->rewardSummary)) {
            $message = $response->error->message ?? null;

            if ($message == 'The Service is temporarily unavailable') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Oops, looks like this page isn't available.
            $message = $response->errors[0]->message ?? null;

            if ($message == "Cannot read property 'toLowerCase' of undefined" && $this->AccountFields['Login'] == 'bradmartin1992@hotmail.com') {
                throw new CheckException("Oops, looks like this page isn't available.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 'An unknown error has occured. Please contact the API vendor') {
                throw new CheckException('An unknown error has occured. Please contact the API vendor', ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }
        $rewardSummary = $response->data->rewardSummary ?? [];

        foreach ($rewardSummary as $reward) {
            // Woolworths Dollars redeemed
            if ($reward->key == 'lifetimeRedeemed') {
                $this->SetProperty('AmountRedeemed', '$' . $reward->value ?? null);
            }
            // WOOLWORTHS DOLLARS TO CONVERT
            if ($reward->key == 'currentVoucherBalance' || $reward->key == 'qffVoucherBalance') {
                $currentVoucherBalance = (isset($currentVoucherBalance) && $currentVoucherBalance > $reward->value) ?: $reward->value;
            }
            // Balance - Points earned
            if ($reward->key == 'currentCreditBalance') {
                $this->SetBalance($reward->value ?? null);
            }
            // CURRENT FUEL DISCOUNTS
            if ($reward->key == 'availableVouchers') {
                $this->SetProperty('FuelDiscounts', $reward->value ?? null);
            }
        }
        // Woolworths dollars to convert
        if (!empty($currentVoucherBalance)) {
            $this->SetProperty('DollarsToConvert', '$' . $currentVoucherBalance);
        }

        /*
        $this->http->GetURL("https://prod.api-wr.com/wx/v1/member/points/vouchers/current");
        $response = $this->http->JsonLog();
        $vouchers = $response->data ?? [];

        $countDays = 90;
        $this->http->GetURL("https://prod.api-wr.com/wx/v1/member/accounts/rewards/transactions/?days={$countDays}");
        $response = $this->http->JsonLog();
        $vouchers = $response->data ?? [];
        if (!empty($response)) {
            // Saved on fuel
            $savedFuel = 0;
            if (!empty($response->data->vouchers))
                foreach ($response->data->vouchers as $item) {
                    $savedFuel += $item->Savings;
                }
            $this->SetProperty('AmountSavedOnFuel', '$' . round($savedFuel, 2));

            // Current fuel discounts
            $fueldDiscount = round($savedFuel / 10);
            $fueldDiscount = ($fueldDiscount > 0 ? $fueldDiscount : 0);
            $this->SetProperty('FuelDiscount', (string) ($vouchersCount > $fueldDiscount ? $vouchersCount : $fueldDiscount));

            //if ($savedFuel > 25)
            //    $this->sendNotification('fish - refs #6168 [woolworths] valid account :: saved fuel > 25$');
        }// if (!empty($response))
        */
    }
}
