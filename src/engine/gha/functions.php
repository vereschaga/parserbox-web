<?php

class TAccountCheckerGha extends TAccountChecker
{
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $headers = [
        "Accept"          => "application/json, text/plain, */*",
        "Accept-Language" => "en-US,en;q=0.5",
        "Content-Type"    => "application/json",
        "authorization"   => "Basic Z2hhOnVFNlU4d253aExzVTVHa1k=",
        "Origin"          => "https://www.ghadiscovery.com",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if (
            !isset($this->State['apiauthorization'])
            || isset($this->State['multipleAccounts'])
        ) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        unset($this->State['multipleAccounts']);

        $this->http->removeCookies();
        $this->http->GetURL('https://www.ghadiscovery.com/member/login');

//        if (!$this->http->ParseForm(null, '//div[@class = "w-full"]/form', false)) {
        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://oscp.ghadiscovery.com/api/v3/member/login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        $message = $response->errors[0]->message ?? null;

        if ($message == 'Captcha required') {
            $captcha = $this->parseCaptcha("6LcKoFYdAAAAAI2gVSvYIWJa6TxeBtUvlWQIhlF6");

            if ($captcha === false) {
                return false;
            }

            $data = [
                "username" => $this->AccountFields['Login'],
                "password" => $this->AccountFields['Pass'],
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://oscp.ghadiscovery.com/api/v3/member/login?g-recaptcha-response={$captcha}", json_encode($data), $this->headers, 120);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
        }

        if (isset($response->token)) {
            $this->State['apiauthorization'] = "Bearer {$response->token}";

            if ($this->loginSuccessful()) {
                $this->captchaReporting($this->recognizer);

                return true;
            }

            $name = $response->errors[0]->name ?? null;

            if ($name == 'ClassCastException') {
                throw new CheckException('Something went wrong.', ACCOUNT_INVALID_PASSWORD);
            }

            $response = $this->http->JsonLog();
            $name = $response->errors[0]->name ?? null;

            if ($name == 'CacheRequestException') {
                throw new CheckException("Something went wrong. Please try again later.", ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $name;

            return false;
        }

        $name = $response->errors[0]->name ?? null;
        $message = $response->errors[0]->message ?? null;

        if ($name) {
            if ($name === 'ReCaptchaInvalidException' && $message == 'reCaptcha validation failed - [timeout-or-duplicate]') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 0);
            }

            $this->captchaReporting($this->recognizer);

            if (in_array($name, [
                'CacheRequestException',
                'ClassCastException',
                'ResourceAccessException',
            ])) {
                throw new CheckException('Something went wrong.', ACCOUNT_INVALID_PASSWORD);
            }

            if ($name === 'LoginOrPasswordIsNotCorrect' && $message == 'Wrong username or password.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($name === 'UserNameOrPasswordIncorrectException' && $message == 'Wrong Username or Password') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($name === 'username' && $message == 'Your account has been blocked due to multiple unsuccessful attempts.') {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if ($name === 'SoapRequestException' && $message == 'Unknown error') {
                throw new CheckRetryNeededException(2, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($name === 'ReCaptchaInvalidException' && $message == 'reCaptcha validation failed - [incorrect-captcha-sol]') {
                throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // this is not lockout
            if ($name === 'CacheIsLockedButDidNotReturnResult' && $message == 'Cache is locked for: BRANDS') {
                throw new CheckRetryNeededException();
            }

            if ($message == "Please, Accept Terms & Conditions of the GHA DISCOVERY Loyalty Programme") {
                $this->throwAcceptTermsMessageException();
            }

            $this->DebugInfo = $name . " / " . $message;

            return false;
        }

        if ($this->http->FindSingleNode("//h1[
                contains(text(), '504 Gateway Time-out')
                or contains(text(), '502 Bad Gateway')
                or contains(text(), '503 Service Temporarily Unavailable')
            ]")
        ) {
            throw new CheckException("Something went wrong. No response received.", ACCOUNT_PROVIDER_ERROR);
        }

        // Looks like you have multiple accounts. Please select from the accounts below
        // AccountID: 1725898
        if (isset($response[0]->profileId)) {
            $this->State['multipleAccounts'] = true;

            $subAccounts = [];

            foreach ($response as $profile) {
                $this->logger->info('Selected account: ' . $profile->profileId, ['Header' => 3]);
                $data = [
                    "username"  => $this->AccountFields['Login'],
                    "password"  => $this->AccountFields['Pass'],
                    "profileId" => $profile->profileId,
                ];
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://oscp.ghadiscovery.com/api/v3/member/login", json_encode($data), $this->headers);
                $profileResponse = $this->http->JsonLog();
                $this->http->RetryCount = 2;
                $message = $profileResponse->errors[0]->message ?? null;

                if ($message == 'Captcha required') {
                    $captcha = $this->parseCaptcha("6LcKoFYdAAAAAI2gVSvYIWJa6TxeBtUvlWQIhlF6");

                    if ($captcha === false) {
                        return false;
                    }

                    $this->http->RetryCount = 0;
                    $this->http->PostURL("https://oscp.ghadiscovery.com/api/v3/member/login?g-recaptcha-response={$captcha}", json_encode($data), $this->headers);
                    $this->http->RetryCount = 2;
                    $profileResponse = $this->http->JsonLog();
                }

                if (!isset($profileResponse->token)) {
                    continue;
                }

                $this->State['apiauthorization'] = "Bearer {$profileResponse->token}";

                if (!$this->loginSuccessful()) {
                    continue;
                }

                $this->Parse();
                $properties = $this->Properties;
                $this->Properties = [];

                $subAcc = [
                    "DisplayName"    => "Member #{$properties['Number']}",
                    "Code"           => $profile->profileId,
                    "Balance"        => $this->Balance,
                ];

                foreach ($properties as $property => $value) {
                    if ($property == 'AccountExpirationDate') {
                        $subAcc["ExpirationDate"] = $value;

                        continue;
                    }

                    $subAcc[$property] = $value;
                }

                $subAccounts[] = $subAcc;
            }// foreach ($response as $profile)

            foreach ($subAccounts as $subAccount) {
                $this->SetBalanceNA();
                $this->AddSubAccount($subAccount, true);
            }
        }// if (isset($response[0]->profileId))

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog();
        // Name
        $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));
        // Membership Number
        $this->SetProperty("Number", $response->membershipNumber);
        // Status
        $this->SetProperty("Status", $response->membershipLevel);
        // Status expires
        $this->SetProperty("StatusExpires", date("d M Y", strtotime($response->expirationDate)));

        // Expiration Date  // refs #10096, #note-10
        $this->http->GetURL("https://ljcp.ghadiscovery.com/api/v2/loyalty/point/expiration", $this->headers);
        $response = $this->http->JsonLog();

        // isLoggedIn issue
        if ($this->http->Response['code'] == 401 && $this->attempt == 0) {
            throw new CheckRetryNeededException(2, 0);
        }

        $expBalance = $response->points ?? null;
        $this->SetProperty('ExpiringBalance', $expBalance);

        if ($expBalance > 0) {
            $this->SetExpirationDate(strtotime($response->expirationDate));
        }

        $this->http->GetURL("https://ljcp.ghadiscovery.com/api/v2/loyalty/balance", $this->headers);
        $response = $this->http->JsonLog();
        // Balance - YOUR CURRENT BALANCE - D$0
        if (
            !$this->SetBalance($response->balance ?? null)
            && $this->http->FindPreg("/Balance not found/")
        ) {
            $this->SetBalance(0);
        }

        $this->http->GetURL("https://escp.ghadiscovery.com/discovery/v2/dashboard/progressToNextLevel", $this->headers);
        $response = $this->http->JsonLog();

        if (!isset($response->numberOfNightsBooked, $response->totalNumberOfNightsRequiredForMembershipUpgrade)) {
            return;
        }
        // for elite levels  // refs #10096
        $this->SetProperty("NightsStayed", $response->numberOfNightsBooked);
        // Nights to next level
        $this->SetProperty("NightsToNextLevel", $response->totalNumberOfNightsRequiredForMembershipUpgrade);
        // Stays
        $this->SetProperty("Stays", $response->numberOfStaysBooked);
        // Book ... more stays to level up
        $this->SetProperty("StaysToNextLevel", $response->totalNumberOfStaysRequiredForMembershipUpgrade);
        // Spend $... more to level up
        $this->SetProperty("ToNextLevel", "$" . $response->totalNumberOfUSRequiredForMembershipUpgrade);
        // booked brands
        $this->SetProperty("BookedBrands", $response->numberOfBrandsBooked);
    }

    protected function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "apiauthorization" => $this->State['apiauthorization'],
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://oscp.ghadiscovery.com/api/v2/member/full", $headers + $this->headers, 80);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->email ?? null;
        $membershipNumber = $response->membershipNumber ?? null;
        $this->logger->debug("[Email]: {$email}");
        $this->logger->debug("[Number]: {$membershipNumber}");

        if (
            strtolower($email) == strtolower($this->AccountFields['Login'])
            || $membershipNumber == $this->AccountFields['Login']
            || ($email && is_string($this->AccountFields['Login']))
            || ($this->AccountFields['Login'] == 'dwiser' && $membershipNumber == '8333290122') // AccountID: 1354542
            || ($this->AccountFields['Login'] == 'adahdouh@mail.com' && $membershipNumber == '8466123157') // AccountID: 3743874
            || ($this->AccountFields['Login'] == 'topcat_sing' && $membershipNumber == '8057471356') // AccountID: 5425311
            || ($this->AccountFields['Login'] == 'whitehurst.sean@gmail.com' && $membershipNumber == '8334812085') // AccountID: 7083149
            || ($this->AccountFields['Login'] == 'aky_84' && $membershipNumber == '8765742508') // AccountID: 2382501
            || ($this->AccountFields['Login'] == 'mcunha@amecbrasil.org.br' && $membershipNumber == '8885097094') // AccountID: 4073042
            || ($this->AccountFields['Login'] == 'lozalyy' && $membershipNumber == '8657495560') // AccountID: 1309694
        ) {
            $this->headers = $this->headers + $headers;

            return true;
        }

        return false;
    }
}
