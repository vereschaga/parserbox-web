<?php

class TAccountCheckerParkngo extends TAccountChecker
{
    private $headers = [
        'X-Requested-With' => 'XMLHttpRequest',
        "Origin"           => "https://rez.daytonparking.com",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://rez.daytonparking.com/customer/login/");

        $token = $this->http->FindPreg("/window._wpnonce\s*=\s*'([^\']+)/");

        if (!$this->http->FindPreg("/login-modal login-class=\"loginWid\"/") || !$token) {
            return $this->checkErrors();
        }
        $data = [
            "login"        => $this->AccountFields['Login'],
            "password"     => $this->AccountFields['Pass'],
            "method"       => "verifyLogin",
            "location"     => "440-1-21",
        ];
        $this->headers["x-csrf-token"] = $token;
        $this->http->PostURL("https://rez.daytonparking.com/wp-content/plugins/netParkV2/ajax.php", json_encode($data), $this->headers);

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
        // Access is allowed
        if ($this->loginSuccessful($response)) {
            return true;
        }

        if (isset($response->errors)) {
            foreach ($response->errors as $error) {
                // Account or password is invalid
                if ($error->text == 'Account or password is invalid') {
                    throw new CheckException($error->text, ACCOUNT_INVALID_PASSWORD);
                }
            }
        }

        if (trim($this->http->Response['body']) == '<strong>ERROR:</strong> That action is currently not allowed.<br /><br />') {
            $this->DebugInfo = 'Blocked';
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance - Point Balance
        $this->SetBalance($response->data->fpp ?? null);
        // Name
        $this->SetProperty('Name', beautifulName($response->data->first_name . " " . $response->data->last_name));
        // Customer #
        $this->SetProperty('CustomerNumber', $response->data->customer ?? null);
        // Join Date
        $this->SetProperty('JoinDate', date("M d, Y", strtotime($response->data->date_added)));
    }

    private function loginSuccessful($response)
    {
        $this->logger->notice(__METHOD__);

        if (isset($response->data->email) && $response->data->email == $this->AccountFields['Login']) {
            return true;
        }

        return false;
    }
}
