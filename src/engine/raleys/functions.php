<?php

class TAccountCheckerRaleys extends TAccountChecker
{
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
            throw new CheckException('Please enter a valid email', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.raleys.com/");

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

        $this->http->GetURL("https://www.raleys.com/api/auth/csrf");
        $response = $this->http->JsonLog();

        if (!isset($response->csrfToken)) {
            return false;
        }

        $csrfToken = $response->csrfToken;
        $data = [
            'email'       => $this->AccountFields['Login'],
            'password'    => $this->AccountFields['Pass'],
            'rememberMe'  => 'true',
            'redirect'    => 'false',
            'csrfToken'   => $csrfToken,
            'callbackUrl' => 'https://www.raleys.com/',
            'json'        => 'true',
        ];
        $headers = [
            "Accept"       => "*/*",
            "content-type" => "application/x-www-form-urlencoded",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.raleys.com/api/auth/callback/credentials", $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->url)) {
            if (strstr($response->url, "error=Error%3A%20Invalid%20Username%20or%20Password")) {
                throw new CheckException('Invalid email and/or password.', ACCOUNT_INVALID_PASSWORD);
            }

            $headers = [
                "Accept"       => "*/*",
                "content-type" => "application/json",
            ];
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.raleys.com/api/auth/session", $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (isset($response->user)) {
                return $this->loginSuccessful();
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));
        // Loyalty Number
        $this->SetProperty("LoyaltyNumber", $response->loyaltyId ?? null);
        // Balance - points
        $this->SetBalance($response->pointsBalance ?? null);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.raleys.com/api/user/profile");
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }
}
