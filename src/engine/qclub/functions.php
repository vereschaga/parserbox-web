<?php

class TAccountCheckerQclub extends TAccountChecker
{
    public function LoadLoginForm()
    {
        throw new CheckException("Dear Guest, We kindly inform that Q-Club Loyalty Programme was terminated on 31/12/2020. For more information please contact Central Reservation Office: Tel.: +48 71 782 87 65, E-mail: reservation@qubushotel.com", ACCOUNT_PROVIDER_ERROR);
        $this->http->FilterHTML = false;
        $this->http->removeCookies();
        // go to login page, like normal user does
        $this->http->GetURL("https://www.qubushotel.com/en/q-club");

        if (!$this->http->FindSingleNode("//meta[@name='title' and @content='Qubus Hotel']/@name")) {
            return $this->checkErrors();
        }
        $this->http->PostURL('https://www.qubushotel.com/api/en/qclub/login', json_encode([
            'login'    => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ]), [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ]);

        return true;
    }

    public function checkErrors()
    {
        // Technical Break
        //if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Technical Break')]"))
        //    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        // Access is allowed
        if (isset($response->data->userId, $response->data->accountNumber, $response->data->loyalGroupName)) {
            return true;
        } elseif (isset($response->data) && empty($response->data->accountNumber) && empty($response->data->loyalGroupName) && $response->data->points == -1) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // error
        if ($this->http->FindPreg('/"status":"NOK","message":"Wrong (?:password|login)"/')) {
            throw new CheckException('Invalid credentials', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->Response['code'] == 503 && in_array($this->AccountFields['Login'], ['award'])) {
            throw new CheckException('Invalid credentials', ACCOUNT_INVALID_PASSWORD);
        }

        // "error", AccountID: 1769820
        if ($this->http->currentUrl() == 'https://www.qubushotel.com/error.html') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, true);
        // Points
        $this->SetBalance($response->data->points);
        // Name
        $this->SetProperty("Name", beautifulName("{$response->data->sessionData->name} {$response->data->sessionData->surname}"));
        // Account number
        $this->SetProperty("CardNumber", $response->data->accountNumber);
        // Q-Club Guest status
        $this->SetProperty("Status", $response->data->loyalGroupName);
    }
}
