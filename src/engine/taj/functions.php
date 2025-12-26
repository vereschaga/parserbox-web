<?php

// refs #2011

use AwardWallet\Engine\ProxyList;

class TAccountCheckerTaj extends TAccountChecker
{
    use ProxyList;

    private $codeVerifier = '';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setHttp2(true);
    }

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerTajSelenium.php";

        return new TAccountCheckerTajSelenium();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        //		$this->http->GetURL('https://www.tajinnercircle.com/en-in/point-summary/');
        // <meta name="tdl-sso-client_id" content="IHCL-WEB-APP"/>
        $clientId = "IHCL-WEB-APP"; //$this->http->FindSingleNode('//meta[@name = "tdl-sso-client_id"]/@content');

        if (!$clientId) {
            return $this->checkErrors();
        }
        $this->http->GetURL("https://members.tajhotels.com/login?clientId={$clientId}&redirectURL=https://www.tajhotels.com/en-in/tajinnercircle/My-Profile/");

        $this->State['headers'] = [
            "Accept"           => "application/json, text/plain, */*",
            "Content-Type"     => "application/json",
            "X-Requested-With" => "XMLHttpRequest",
            "client_id"        => $clientId,
            "client_secret"    => "4be3ca1b-29ce-4452-8bf9-144f338380d0", // https://members.tajhotels.com/bundle.js
        ];

        $o = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~";

        for ($r = 0; $r < 128; $r++) {
            $pos = (int) (floor((float) rand() / (float) getrandmax() * mb_strlen($o)));
            $this->codeVerifier .= $o[$pos];
        }

        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
        $codeChallenge = $jsExecutor->executeString('
            var encrypted = CryptoJS.SHA256("' . $this->codeVerifier . '").toString(CryptoJS.enc.Base64).replace(/=/g, "").replace(/\+/g, "-").replace(/\//g, "_");
            sendResponseToPhp(encrypted.toString());', 5, ['https://members.tajhotels.com/crypto-min.js']);
        $this->logger->debug("codeVerifier: {$this->codeVerifier}");
        $this->logger->debug("codeChallenge: {$codeChallenge}");
        $this->State['codeChallenge'] = $codeChallenge;

        $data = [
            'ticNumber'         => $this->AccountFields['Login'],
            'password'          => $this->AccountFields['Pass'],
            "codeChallenge"     => $codeChallenge,
            "redirectClientUrl" => "https%3A%2F%2Fwww.tajhotels.com%2F%3Fstate%3Dnull%26",
            "redirectUrl"       => "https%3A%2F%2Fwww.tajhotels.com%2F%3Fstate%3Dnull%26",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://members.tajhotels.com/api/v1/sso/login/tic', json_encode($data), $this->State['headers']);
        $this->http->RetryCount = 2;

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        //$arg['NoCookieURL'] = true;
        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Tajhotels.com is currently down for a planned maintenance.We expect to be back in a few minutes")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // Access is allowed
        if (isset($response->authCode)) {
            return $this->authComplete($response);
        }
        // Catch errors
        $error = $response->error ?? $response->message ?? null;
        $error =
            $error
            ?? $response->success
            ?? $response->SUCCESS
            ?? null
        ;

        if ($error) {
            $this->logger->error($error);

            switch ($error) {
                case 'Unable to locate user with provided login id.':
                case 'We have upgraded our website/app for a seamless browsing experience. Please reset your password to access your account.':
                case 'Invalid Login and/or Password':
                case 'TIC Number is not registered':
                case 'Invalid credentials':
                case 'Incorrect Password':
                case 'Password is not set, please login using OTP':
                    throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);

                    break;
                /**
                 * Activate Account
                 * Your profile has been created successfully
                 * One Time Password (OTP) has been sent to your registered email and phone number
                 * Not received OTP?Resend OTP.
                 */
                case 'User verification is pending, dynamic access code is send to your email registered with us.':
                    $this->throwProfileUpdateMessageException();
//                    https://www.tajinnercircle.com/bin/validate-code?username=james.rourke%40hotmail.co.uk&otp=123245&action=new-online-user&_=1595841330313
                    break;

                case 'Error during sign in, please try again':
                case 'User registration not complete. Please complete registration to login.':
                case 'User registration not complete. Please complete registration with phone number to login.':
                case 'You have exhausted all email verification attempts.':
                case 'Something went wrong please try again after sometime':
                case strstr($error, 'Email is not verified. Email verification code is sent to '):
                case strstr($error, 'Email is not verified. Email verification code is resent to'):
                    throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);

                    break;

                case 'Login failed - Your account has been locked':
                    throw new CheckException($error, ACCOUNT_LOCKOUT);

                    break;

                case 'TIC in GR. User not enrolled and OTP sent':
                    $this->State['data'] = [
                        "refId"                  => $response->refId,
                        "countryCode"            => str_replace('+', '', $response->countryCode),
                        "mobileNumber"           => $response->mobileNumber,
                        "otp"                    => "",
                        "phoneVerificationToken" => null,
                    ];
                    $this->AskQuestion("Enter the OTP we’ve sent you", null, "Question");

                    break;

                case 'Something went wrong please try again after sometime.':
                    $code = $response->code ?? null;

                    if (stripos($code, 'E11000 duplicate key error collection') !== false) {
                        throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    if (stripos($code, ' returned non unique result.') !== false) {// AccountID: 2939855
                        throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
                    }

                    break;
            }

            return false;
        }
        // AccountID: 3747625
        if ($this->http->Response['code'] == 503) {
            throw new CheckException("There seems to be an error during your login. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }
        // AccountID: 4643891
        if ($this->http->Response['code'] == 504 && $this->http->FindSingleNode("//h2[contains(text(), 'The request could not be satisfied.')]")) {
            throw new CheckException("There seems to be an error during your login. Please try again.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] == 504 && $this->http->FindSingleNode("//h1[contains(text(), '504 Gateway Time-out')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification("2fa, need to check - refs #20454 // RR");
        $data = $this->State['data'];
        $data['otp'] = $this->Answers[$this->Question];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://members.tajhotels.com/api/v1/sso/verify-signup-otp', json_encode($data), $this->State['headers']);
        $this->http->RetryCount = 2;
        // Remove OTP code
        unset($this->Answers[$this->Question]);

        $response = $this->http->JsonLog();
        $error = $response->error ?? null;
        // Please enter the valid OTP
        if ($error == "OTP Invalid") {
            $this->AskQuestion($this->Question, $error, "Question");

            return false;
        }

        $data = [
            "codeChallenge" => $this->State['codeChallenge'],
        ];
        $this->http->PostURL("https://members.tajhotels.com/api/v1/sso/check-session", json_encode($data), $this->State['headers']);
        $response = $this->http->JsonLog();

        unset($this->State['data']);
        unset($this->State['headers']);
        unset($this->State['codeChallenge']);

        if (isset($response->authCode)) {
            return $this->authComplete($response);
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog($this->http->JsonLog(null, 0), 0);
        $membershipId = $response->tcpNumber ?? null;

        if (!$membershipId) {
            $this->logger->error("tcpNumber not found");
//            return;
        }

        // Name
        $this->SetProperty("Name", beautifulName(($response->nameDetails->firstName ?? null) . " " . $response->nameDetails->lastName ?? null));
        // Membership Number
        $this->SetProperty("Number", $membershipId);

        if (!isset($response->loyaltyInfo) || count($response->loyaltyInfo) != 1) {
            $this->logger->notice("need to check this account");

            return;
        }

        // Balance - TIC
        $this->SetBalance($response->loyaltyInfo[0]->loyaltyPoints ?? null);
        // Current tier
        $this->SetProperty("Tier", $response->loyaltyInfo[0]->currentSlab ?? null);
        // Your membership is valid till
        $this->SetProperty("TierExpiration", date("d-m-Y", strtotime(preg_replace("/T.+/", '', $response->loyaltyInfo[0]->slabExpiryDate))));

        // Epicure
//        $this->SetProperty("EpicurePointsBalance", $response->epicureAvailablePoints ?? null);??

        /*
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (
                // AccountID: 4378157, 1901047, 4209468, 4979418
//                in_array($this->AccountFields['Login'], [101015388880, 	101014100367, 101015272041])
                (!empty($this->Properties['Tier']) || in_array($this->AccountFields['Login'], [101015203077, 101015670269, 101015674614, 101014794343]))
                && !empty($this->Properties['Name'])
                && !empty($this->Properties['Number'])
                && $this->http->Response['body'] == '{"ticAvailablePoints":"","epicureAvailablePoints":"","tapAvailablePoints":"","tappmeAvailablePoints":""}'
            ) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)


        $this->logger->info('Vouchers', ['Header' => 3]);
        $this->http->GetURL("https://www.tajinnercircle.com/bin/queryVouchers?MembershipNumber={$membershipId}&description=Epicure%20Complimentary%20Room%20Night%20-%201%20Night%20Stay&_=".date("UB"), $headers);
        $response = $this->http->JsonLog();
        if (!$response) {
            return;
        }
        foreach ($response as $voucher) {
            if ($voucher->status != 'Available') {
                continue;
            }
            $this->AddSubAccount([
                'Code'           => 'tajVoucher'.$voucher->voucherNumber,
                'DisplayName'    => $voucher->description,
                'Balance'        => null,
                'ExpirationDate' => strtotime($voucher->expirationDate),
            ]);
        }
        */
    }

    private function authComplete($response)
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "Referer"          => "https://www.tajhotels.com/en-in/tajinnercircle/My-Profile/",
            "X-Requested-With" => "XMLHttpRequest",
        ];
//            $this->http->GetURL("https://www.tajhotels.com/?state=null&authCode={$response->authCode}&existingUser=1&codeVerifier={$this->codeVerifier}");
        $data = [
            "authToken" => $response->authCode,
            "req_data"  => json_encode(['codeVerifier' => $this->codeVerifier]),
        ];
        $this->http->PostURL("https://www.tajhotels.com/bin/ssoTd", $data, $headers);
        $response = $this->http->JsonLog($this->http->JsonLog());

        if (!isset($response->accessToken) || !isset($response->idToken->customerHash)) {
            $this->logger->error("something went wrong");

            return false;
        }

        $data = [
            "authToken" => $response->accessToken,
            "req_data"  => "\"{$response->accessToken}\"",
        ];
        $this->http->PostURL("https://www.tajhotels.com/bin/validateTokenTd", $data, $headers);
        $this->http->JsonLog();

        // todo
        $data = [
            "authToken" => $response->accessToken,
            "req_data"  => json_encode([
                "getOffersFromVaultRequest" => [
                    "customerHash" => $response->idToken->customerHash,
                    "programId"    => "b098cfab-0bfd-4c0e-9a7d-4bee045ee809",
                ],
            ]),
        ];
        $this->http->PostURL("https://www.tajhotels.com/bin/getOfferTd", $data, $headers);
        $this->http->JsonLog($this->http->JsonLog(), 3);

        if (
            !$this->http->FindPreg("/(?:No Promotion found for the search criteria|Missing or Invalid Authorization Token|Service Unavailabe)/")
            && $this->http->Response['code'] != 403
        ) {
            $this->sendNotification("offers were found // RR");
        }

        $data = [
            "authToken" => $response->accessToken,
            "req_data"  => json_encode(['customerHash' => $response->idToken->customerHash]),
        ];
        $this->http->PostURL("https://www.tajhotels.com/bin/fetchTdCustomer", $data, $headers);

        // it helps sometimes
        if ($this->http->FindPreg("/(Internal Server Error)/")) {
            sleep(3);
            $this->http->PostURL("https://www.tajhotels.com/bin/fetchTdCustomer", $data, $headers);
        }

        // it helps sometimes
        if ($this->http->FindPreg("/(Internal Server Error)/")) {
            sleep(7);
            $this->http->PostURL("https://www.tajhotels.com/bin/fetchTdCustomer", $data, $headers);
        }

        $this->http->JsonLog($this->http->JsonLog(), 3, false, 'loyaltyPoints');

        /*
        $this->http->PostURL("https://www.tajhotels.com/bin/getTdLoyalty", ["authToken" => $response->accessToken]);
        $response = $this->http->JsonLog($this->http->JsonLog());
        */

        return true;
    }
}
