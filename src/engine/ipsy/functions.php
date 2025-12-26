<?php

class TAccountCheckerIpsy extends TAccountChecker
{
    public function InitBrowser(): void
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn(): bool
    {
        return $this->loginSuccessful();
    }

    public function LoadLoginForm(): bool
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.ipsy.com/');
        $trackingID = $this->http->getCookieByName('ipstr');

        if ($this->http->Response['code'] !== 200 || !$trackingID) {
            return $this->checkErrors();
        }

        $graphQL = '{"operationName":"login","variables":{"username":"' . $this->AccountFields['Login'] . '","password":"' . $this->AccountFields['Pass'] . '"},"query":"mutation login($username: String!, $password: String!) {\n  login(username: $username, password: $password) {\n    userId\n    passwordExpired\n    __typename\n  }\n}\n"}';
        $this->http->PostURL('https://graphql.prod.ipsy.com/graphql', $graphQL, [
            'Content-Type'      => 'application/json',
            'Accept'            => 'application/json',
            'Ipsy-Tracking-Id'  => $trackingID,
            'x-ipsy-tid'        => $trackingID,
            'x-bfa-owner'       => 'Ipsy Member Growth',
            'x-bfa-previewpass' => 'undefined',
            'Origin'            => 'https://www.ipsy.com',
        ]);

        return true;
    }

    public function Login(): bool
    {
        $message = $this->http->JsonLog()->errors[0]->message ?? null;

        if (!empty($message)) {
            $this->logger->error("[Error]: {$message}");

            if (str_contains($message, 'Your login attempt has been denied for security purposes')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (str_contains($message, 'Username and password do not match')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'INCORRECT_CREDENTIALS') {
                throw new CheckException("Username and password do not match.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse(): void
    {
        $this->http->GetURL('https://www.ipsy.com/points/overview');
        $response = $this->http->JsonLog()->ipsyPoints ?? null;
        // Balance - points
        $this->SetBalance($response->balance ?? null);

        if (!empty($response->nextToExpire)
            && is_numeric($response->nextToExpire)
            && $exp = strtotime($response->nextToExpireDate ?? null)
        ) {
            $this->SetExpirationDate($exp);
            $this->SetProperty('ExpiringBalance', $response->nextToExpire);
        }
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.ipsy.com/points');
        $user = $this->http->FindPreg("/USER_INFO_FOR_CLIENT = JSON\.parse\('([^']+)/");

        if (empty($user)) {
            return false;
        }
        $user = $this->http->JsonLog(stripslashes($user));
        $name = $user->username ?? '';
        $email = $user->email ?? '';
        $this->logger->debug("[Username]: " . $name);
        $this->logger->debug("[Email]: " . $email);
        $loginToLower = strtolower($this->AccountFields['Login']);

        return strtolower($name) == $loginToLower
            || strtolower($email) == $loginToLower;
    }

    private function checkErrors(): bool
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
