<?php

class TAccountCheckerWallypark extends TAccountChecker
{
    private $headers = [
        "Accept"          => "application/json, text/plain, */*",
        "Accept-Encoding" => "gzip, deflate, br",
        "Content-Type"    => "application/json",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->State['ConsumerId']) || !isset($this->State['Authorization'])) {
            return false;
        }

        if ($this->loginSuccessful($this->State["ConsumerId"], $this->State['Authorization'])) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);
        // A valid email address is required
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }
        $this->http->removeCookies();
        $this->http->GetURL("https://www.wallypark.com/loyalty/#/login");

        $data = [
            "Email"    => $this->AccountFields['Login'],
            'Password' => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Authorization"   => "MjZFNTdBRDAtNDY4OC00NDJELTgzNTYtMkM5NDYzRTgzRTcz",
            "Accept"          => "application/json, text/plain, */*",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-Type"    => "application/json",
            "Referer"         => "https://www.wallypark.com/loyalty/",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://smartmiddleware.azurewebsites.net/api/authentication", json_encode($data), $headers);
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
        $message = $response->Errors->Message ?? null;

        if ($message) {
            $this->logger->error($message);

            if ($message = "Authentication Failed.") {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }
        $authorizationToken = $response->Result->AuthorizationToken ?? null;
        $consumerId = $response->Result->ConsumerId ?? null;

        if (
            empty($consumerId)
            || empty($authorizationToken)
        ) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful($consumerId, $authorizationToken)) {
            $this->State["ConsumerId"] = $consumerId;
            $this->State["Authorization"] = $authorizationToken;

            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);

        $firstName = $response->Result->FirstName ?? null;
        $lastName = $response->Result->LastName ?? null;
        // Name
        $this->SetProperty("Name", beautifulName($firstName . " " . $lastName));

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://smartmiddleware.azurewebsites.net/api/memberships/pointsummary", $this->State['ConsumerId'], $this->headers + ["Authorization" => $this->State['Authorization']]);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        // Member ID
        $this->SetProperty("ID", $response->Result->MemberID ?? null);
        // Point Balance
        $this->SetBalance($response->Result->TotalPointBalance ?? null);
        // Value
        $totalValue = $response->Result->TotalValue ?? null;

        if (isset($totalValue)) {
            $this->SetProperty("Value", "$" . $totalValue);
        }
        // Earned Points
        $this->SetProperty("EarnedPoints", $response->Result->EarnedPoints ?? null);
        // Adjustments
        $this->SetProperty("Adjustments", $response->Result->Adjustments ?? null);
        // Redeemed Points
        $this->SetProperty("RedeemedPoints", $response->Result->RedeemedPoints ?? null);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://www.wallypark.com/loyalty/#/login';

        return $arg;
    }

    private function loginSuccessful($consumerId, $authorization)
    {
        $this->logger->notice(__METHOD__);
        $data = [
            "ConsumerId"        => $consumerId,
            "IncludeMembership" => "true",
            "IncludeVehicles"   => "true",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://smartmiddleware.azurewebsites.net/api/consumers/get", json_encode($data), ["Authorization" => $authorization]);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        $errorId = $response->Errors->ErrorId ?? null;
        $message = $response->Errors->Message ?? null;
        $userName = $response->Result->UserName ?? null;
        $email = $response->Result->Email ?? null;

        if (empty($errorId)
            && empty($message)
            && empty($additionalInfo)
            && (
                strtolower($userName) == strtolower($this->AccountFields['Login'])
                || strtolower($email) == strtolower($this->AccountFields['Login'])
            )
        ) {
            return true;
        }

        return false;
    }
}
