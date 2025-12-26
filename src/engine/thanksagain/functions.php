<?php

class TAccountCheckerThanksagain extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

        if ($this->loginSuccessful($this->State['Authorization'])) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.thanksagain.com/");
        $this->http->GetURL("https://sso.thanksagain.com/realms/thanksagain/protocol/openid-connect/auth?client_id=thanksagain-front-end&redirect_uri=https%3A%2F%2Fthanksagain.com%2F&state=c9860bf4-eb16-4169-88bb-043a3306d304&response_mode=fragment&response_type=code&scope=openid&nonce=7cce7754-d9a1-42e8-89b9-f96df466a92b");

        if (!$this->http->ParseForm("kc-form-login")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('rememberMe', "on");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;
        $this->http->setMaxRedirects(1);

        if (!$this->http->PostForm() && !in_array($this->http->Response['code'], [307, 308])) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        $code = $this->http->FindPreg("/&code=([^\&]+)/", false, $this->http->currentUrl());

        if (isset($this->http->Response['headers']['location'])) {
            $this->http->setMaxRedirects(5);
            $this->http->GetURL($this->http->Response['headers']['location']);
        }

        if ($code) {
            $data = [
                "code"         => $code,
                "grant_type"   => "authorization_code",
                "client_id"    => "thanksagain-front-end",
                "redirect_uri" => "https://thanksagain.com/",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://sso.thanksagain.com/realms/thanksagain/protocol/openid-connect/token", $data);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (!isset($response->access_token)) {
                return false;
            }

            $authorization = "{$response->token_type} {$response->access_token}";

            return $this->loginSuccessful($authorization);
        }// if ($code)

        $message = $this->http->FindSingleNode('//span[@id = "input-error"]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Invalid username or password.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Account ID
        $this->SetProperty("Number", $response->ParticipantID);
        // Name
        $this->SetProperty("Name", beautifulName($response->FName . ' ' . $response->LName));

        $this->http->GetURL("https://api.walletcx.com/member-api/counters");
        $counters = $this->http->JsonLog();

        foreach ($counters as $counter) {
            if ($counter->key == 'Points') {
                // Balance - Pts
                $this->SetBalance($counter->value);

                break;
            }
        }
    }

    private function loginSuccessful($authorization)
    {
        $this->logger->notice(__METHOD__);

        $this->http->GetURL("https://api.walletcx.com/member-api/members/details", ["Authorization" => $authorization]);
        $response = $this->http->JsonLog();
        $email = $response->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            $this->State['Authorization'] = $authorization;
            $this->http->setDefaultHeader("Authorization", $authorization);

            return true;
        }

        return false;
    }
}
