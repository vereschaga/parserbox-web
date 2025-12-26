<?php

class TAccountCheckerSpringboardamerica extends TAccountChecker
{
    private $headers = [
        'Accept'       => 'application/json, text/plain, */*',
        'Content-Type' => 'application/json',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if (
            !isset($this->State['token'], $this->State['memberID'])
        ) {
            return false;
        }

        return $this->loginSuccessful();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.springboardamerica.com/");

        if (!$this->http->FindSingleNode('//title[contains(text(), "Unlock")]')) {
            return $this->checkErrors();
        }

        $data = [
            'user'        => $this->AccountFields['Login'],
            'password'    => $this->AccountFields['Pass'],
            'captcha_key' => $this->parseCaptcha(),
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://unlock-orchestrator-api.stagwellmarketingcloud.io/v1/auth/login', json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $authResult = $this->http->JsonLog();

        if (!isset($authResult)) {
            return $this->checkErrors();
        }

        if (isset($authResult->token, $authResult->member_id)) {
            $this->State['token'] = $authResult->token;
            $this->State['memberID'] = $authResult->member_id;
            $this->SetProperty('MemberSince', strtotime($authResult->joined_at));

            return $this->loginSuccessful();
        }

        /*
        if ($this->loginSuccessful()) {
            return true;
        }
        */

        if (isset($authResult->detail)) {
            $message = $authResult->detail;

            if (strstr($message, "Invalid username or password.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "Invalid CAPTCHA. Please try again.")) {
                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://unlock-orchestrator-api.stagwellmarketingcloud.io/v1/users/' . $this->State['memberID'] . '/points', $this->headers);
        $data = $this->http->JsonLog();

        if (!isset($data->points)) {
            return $this->checkErrors();
        }
        $this->SetBalance($data->points);
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindSingleNode("//span[@id = 'ErrorMessageLabel']", null, false)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Springboard America is currently undergoing maintenance
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Springboard America is currently undergoing maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'We are performing emergency maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The application you are trying to access is currently down for scheduled maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The application you are trying to access is currently down for scheduled maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * We are working on the last of our changes as we bring you your new and improved Springboard America Community.
         * We expect our maintenance to be completed by noon on April 9th or sooner, please try again after this time.
         */
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We expect our maintenance to be completed by')]")) {
            throw new CheckException("We are working on the last of our changes as we bring you your new and improved Springboard America Community. " . $message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Sparq is currently unavailable
        if ($message = $this->http->FindPreg("/(Sparq is currently unavailable)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application.
        if ($message = $this->http->FindPreg("/(Server Error in \'\/\' Application\.)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = '6LdDcyMUAAAAAFun4E-qVd3Quan2z6LqO0WF68DH';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;

        $parameters = [
            "pageurl"     => $this->http->currentUrl(),
            "proxy"       => $this->http->GetProxy(),
            'type'        => 'ReCaptchaV2TaskProxyLess',
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->headers['Authorization'] = 'Bearer ' . $this->State['token'];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://unlock-orchestrator-api.stagwellmarketingcloud.io/v1/users/" . $this->State['memberID'], $this->headers);
        $this->http->RetryCount = 2;
        $data = $this->http->JsonLog();

        if (!isset($data)) {
            return false;
        }

        if (
            isset($data->email)
            && strtolower($data->email) === strtolower($this->AccountFields['Login'])
        ) {
            $this->SetProperty('Name', beautifulName($data->first_name . " " . $data->last_name));

            return true;
        }
    }
}
