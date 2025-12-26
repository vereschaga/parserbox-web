<?php

class TAccountCheckerCat extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
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
        $this->http->GetURL('https://www.cityairporttrain.com/en/login');

        if (
            !$this->http->FindSingleNode('//title[contains(text(), "City Airport Train")]')
            || $this->http->Response['code'] != 200
        ) {
            return $this->checkErrors();
        }

        $this->State['sw-access-key'] = $this->http->FindPreg('/shopwareAccessToken:"(.+?)"/');

        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];

        $headers = [
            'sw-access-key' => $this->State['sw-access-key'],
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://backend.cityairporttrain.com/store-api/account/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $authResult = $this->http->JsonLog();

        $errors = $authResult->errors ?? [];

        foreach ($errors as $error) {
            $message = $error->detail ?? null;

            if (isset($message) && strstr($message, "No matching customer for the email")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (isset($message) && strstr($message, "Invalid username and/or password.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if (!isset($authResult->contextToken)) {
            return $this->checkErrors();
        }

        $this->State['contextToken'] = $authResult->contextToken;

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $userInfo = $this->http->JsonLog();

        $this->SetProperty('Name', $userInfo->firstName . ' ' . $userInfo->lastName);
        $this->SetProperty('MemberSince', strtotime($userInfo->createdAt));

        $headers = [
            'accept'           => 'application/json',
            'sw-context-token' => $this->State['contextToken'],
            'sw-access-key'    => $this->State['sw-access-key'],
        ];

        $this->http->PostURL('https://backend.cityairporttrain.com/store-api/iwvs-bonus-system/points', [], $headers);

        $pointsInfo = $this->http->JsonLog();

        $this->SetBalance($pointsInfo->points);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        // $arg['CookieURL'] = 'https://www.cityairporttrain.com/SpecialPages/Login.aspx?ReturnUrl=%2fSpecialPages%2fC-Club-Portal%2fBenefits%2fHotel---Kulinarik.aspx';
        // $arg['SuccessURL'] = 'https://www.cityairporttrain.com/SpecialPages/C-Club-Portal/Benefits/Hotel---Kulinarik.aspx';
        return $arg;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->State['contextToken'], $this->State['sw-access-key'])) {
            return false;
        }

        $headers = [
            'accept'           => 'application/json',
            'sw-context-token' => $this->State['contextToken'],
            'sw-access-key'    => $this->State['sw-access-key'],
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://backend.cityairporttrain.com/store-api/account/customer', [], $headers);
        $this->http->RetryCount = 2;

        $userInfo = $this->http->JsonLog();

        if (isset($userInfo->email) && $userInfo->email === $this->AccountFields['Login']) {
            return true;
        }

        return $this->checkErrors();
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
