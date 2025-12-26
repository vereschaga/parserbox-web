<?php

class TAccountCheckerRecyclebank extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if (empty($this->State['localId']) || empty($this->State['idToken'])) {
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
        $this->http->GetURL('https://recyclebank.com/login');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $data = [
            "returnSecureToken" => true,
            "email"             => $this->AccountFields['Login'],
            "password"          => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"           => "*/*",
            "Accept-Encoding"  => "gzip, deflate, br",
            "Content-Type"     => "application/json",
            "Origin"           => "https://recyclebank.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=AIzaSyDW31QVLmUhhgrU8SaWrx6pDoEJdjXDf10", json_encode($data), $headers);
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

        if (isset($response->localId, $response->idToken)) {
            $this->State['idToken'] = $response->idToken;
            $this->State['localId'] = $response->localId;

            if ($this->loginSuccessful()) {
                return true;
            }

            // AccountID: 6856004
            if ($this->http->Response['code'] == 400 && $this->http->FindPreg("/Sequence contains no elements/")) {
                throw new CheckException("We're sorry. We've run into an issue while getting your information. Try again later!", ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $message = $response->error->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");
            // There is no existing user record corresponding to the provided identifier.
            if ($message == 'EMAIL_NOT_FOUND') {
                throw new CheckException("There is no existing user record corresponding to the provided identifier.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'INVALID_LOGIN_CREDENTIALS') {
                throw new CheckException("Server error.", ACCOUNT_PROVIDER_ERROR); // very strange message on the website
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance - Points
        $this->SetBalance($response->pointBalance);
        // Lifetime Points
        $this->SetProperty("LifetimePoints", $response->lifetimePoints);
        // Name
        $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            "Accept-Language" => "en-US,en;q=0.5",
            "Accept-Encoding" => "gzip, deflate, br",
            "Authorization"   => "Bearer {$this->State['idToken']}",
            "Origin"          => "https://recyclebank.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://apim-prod-eastus-002.azure-api.net/recyclebank-api/users/authid/{$this->State['localId']}", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->loginEmail ?? null;
        $this->logger->debug("[Email]: {$email}");

        return strtolower($email) == strtolower($this->AccountFields['Login']);
    }
}
