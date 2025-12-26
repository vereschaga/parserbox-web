<?php

class TAccountCheckerGogowifi extends TAccountChecker
{
    use AwardWallet\Engine\ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization']) || !isset($this->State['user_name'])) {
            return false;
        }

        $headers = [
            "Accept"        => "application/json, text/plain, */*",
            "Content-Type"  => "application/json",
            "Authorization" => $this->State['Authorization'],
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://secureground.gogoair.com/ground/gateway/v1/customer/user/{$this->State['user_name']}?data_types=PERSONAL,PMTINSTRUMENTS", $headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if ($this->loginSuccessful($response)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Password must be at least 6 characters.
        // AccountID: 4764458
        if (strlen($this->AccountFields['Pass']) < 6) {
            throw new CheckException('Password must be at least 6 characters.', ACCOUNT_INVALID_PASSWORD);
        }

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://secureground.gogoair.com/app/gogo/account/#account-sign-in');

        if ($this->http->Response['code'] != 200 || !$this->http->getCookieByName("uxdId")) {
            return $this->checkErrors();
        }
        $data = [
            "user"       => $this->AccountFields['Login'],
            "password"   => $this->AccountFields['Pass'],
            "uxd_id"     => $this->http->getCookieByName("uxdId"),
            "data_types" => [
                "PERSONAL",
                "PMTINSTRUMENTS",
            ],
        ];
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://secureground.gogoair.com/ground/gateway/v1/account/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->auth_token)) {
            $this->State['Authorization'] = "Bearer {$response->auth_token}";
            $this->State['user_name'] = $response->user_name;

            return $this->loginSuccessful($response);
        }
        //Errors
        $message = $response->error_msg ?? null;

        if ($message) {
            $this->logger->error($message);

            if (
                $message == "Password Invalid"
                || $message == "[Incorrect value for password]"
            ) {
                throw new CheckException("The username or password entered is invalid. Please check your use of punctuation, special characters, and/or spaces.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == "Email address does not exist") {
                throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty('Name', beautifulName($response->personal_data->first_name . " " . $response->personal_data->last_name));

        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }

        // PURCHASE HISTORY
//        $this->http->GetURL("https://secureground.gogoair.com/ground/gateway/v1/customer/purchasehistory/user/{$response->user_name}");
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = '6LcNKdoUAAAAAD86DufDm8mx8e35DIWudkgwoJgP';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful($response)
    {
        $this->logger->notice(__METHOD__);

        if (
            isset($response->user_name, $response->personal_data->email_address)
            && (
                strtolower($response->personal_data->email_address) == strtolower($this->AccountFields['Login'])
                || strtolower($response->user_name) == strtolower($this->AccountFields['Login'])
            )
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->Response['code'] == 404
            && $this->http->FindPreg("/Message: The specified key does not exist./")
            && $this->http->FindPreg("/An Error Occurred While Attempting to Retrieve a Custom Error Document/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
