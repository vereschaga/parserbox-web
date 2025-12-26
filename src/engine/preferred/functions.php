<?php

class TAccountCheckerPreferred extends TAccountChecker
{
    private $headers = [
        'Accept'          => '*/*',
        'Accept-Language' => 'en-US',
        'Content-Type'    => 'application/json',
        'Program-Code'    => 'PHG',
        'Origin'          => 'https://preferredhotels.com',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['AccessToken'], $this->State['ProfileId'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://preferredhotels.com/");
//        if (!$this->http->ParseForm("login")) {
        if (!$this->http->FindSingleNode("//button[contains(., 'I Prefer Member Login') or contains(@class, 'header2__login__text')] | //div[contains(text(), 'Login or Join')]") && !$this->http->FindPreg("/>Login or Join </")) {
            return $this->checkErrors();
        }

        $this->headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $this->headers['Authorization'] = 'Basic UEhHX0RFVlRFQU1fS0VZOjk5QzFuOXlIb00=';
        $this->http->RetryCount = 0;
        $data = [
            'grant_type'    => 'password',
            'username'      => $this->AccountFields['Login'],
            'password'      => $this->AccountFields['Pass'],
            'response_type' => 'token',
        ];
        $this->http->PostURL('https://loyalty.ptgapis.com/v1/authorization/profiles/tokens', $data, $this->headers);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The website that you're trying to reach is having technical difficulties and is currently unavailable.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "The website that you\'re trying to reach is having technical difficulties and is currently unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 504 Gateway Time-out
        if ($this->http->FindSingleNode("
                //title[contains(text(), '504 Gateway Time-out')]
                | //h1[contains(text(), '503 Service Temporarily Unavailable')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/The website encountered an unexpected error. Please try again later\./") && $this->http->Response['code'] == 500) {
            $this->http->GetURL("https://preferredhotels.com/iprefer/login");

            if ($message = $this->http->FindSingleNode('//h6[contains(., "Hotel Rewards site will experience a planned service outage starting")]', null, true, "/(.+)\s+For questions, please review additional details below/")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    public function Login()
    {
        $auth = $this->http->JsonLog();

        if (isset($auth->ProfileId)) {
            $this->State['AccessToken'] = $auth->AccessToken;
            $this->State['ProfileId'] = $auth->ProfileId;

            return $this->loginSuccessful();
        }

        if (isset($auth->Message)) {
            if (
                $auth->Message == 'The username/password combination is invalid.'
                || $auth->Message == 'Password has been expired.'
            ) {
                throw new CheckException($auth->Message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($auth->Message == "The account is frozen.") {
                throw new CheckException($auth->Message, ACCOUNT_LOCKOUT);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);

        if (!isset($response->CardNumber)) {
            return;
        }
        // Member Number
        $this->SetProperty("MemberIPreferID", $response->CardNumber);
        // Tier
        $this->SetProperty("Status", $response->TierName);
        // Name
        $this->SetProperty("Name", beautifulName("{$response->FirstName} {$response->LastName}"));

        $this->http->GetURL("https://loyalty.ptgapis.com/v1/profiles/{$this->State['ProfileId']}/points/balance", $this->headers);
        $response = $this->http->JsonLog(null, 3, false, 'PointsBalance');
        // Balance - POINTS
        foreach ($response->PointsBalance as $item) {
            if ($item->PointTypeShortDescription == 'Point Credits') {
                $this->SetBalance($item->PointAmount);

                break;
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->headers['Content-Type'] = 'application/json';
        $this->headers['Authorization'] = "OAuth {$this->State['AccessToken']}";

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://loyalty.ptgapis.com/v1/profiles/{$this->State['ProfileId']}", $this->headers, 20);
        $response = $this->http->JsonLog();
        $this->http->RetryCount = 2;

        if (isset($response->CardNumber, $response->TierName)) {
            return true;
        }

        return false;
    }
}
