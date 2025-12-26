<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirnewzealand extends TAccountChecker
{
    use OtcHelper;
    use ProxyList;
    public $regionOptions = [
        ""                => "Select your region",
        "Australia"       => "Australia",
        "Canada"          => "Canada",
        "China"           => "China",
        /*
        "Deutschland"     => "Deutschland",
        "France"          => "France",
        */
        "HongKong"        => "Hong Kong",
        "Japan"           => "Japan",
        "NewZealand"      => "New Zealand & Continental Europe",
        "PacificIslands"  => "Pacific Islands",
        /*
        "FrenchPolynesia" => "French Polynesia",
        */
        "UK"              => "United Kingdom & Republic of Ireland",
        "USA"             => "United States",
    ];
    private $xAnchorMailbox;
    private $host = 'www.airnewzealand.co.nz';
    private $domain = 'airnewzealand.co.nz';
    private $surname;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams();

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->setRegionSettings();

        if ($this->attempt == 2) {
            $this->setProxyGoProxies(null, 'gb');
        } elseif (in_array($this->AccountFields['Login2'], ['HongKong', 'NewZealand'])) {
            $this->http->SetProxy($this->proxyReCaptcha());
        } else {
            $this->setProxyMount();
        }

        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 0) {
            $this->http->setRandomUserAgent(10);
            $agent = $this->http->getDefaultHeader($userAgentKey);

            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }
        } else {
            $this->http->setUserAgent($this->State[$userAgentKey]);
        }
    }

    public function IsLoggedIn()
    {
        return $this->generatePSToken();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;

        $clientID = 'ca26a955-b1c6-434c-9e44-a12ca9158158';
        $scope = 'openid profile offline_access https://customerairnz.onmicrosoft.com/airpoints/airpoints.claims.readwrite.my https://customerairnz.onmicrosoft.com/airpoints/ancillary.sale.readwrite.my https://customerairnz.onmicrosoft.com/airpoints/flightsearch.read.my https://customerairnz.onmicrosoft.com/airpoints/mfa.read.my https://customerairnz.onmicrosoft.com/airpoints/onlineprofile.read.my https://customerairnz.onmicrosoft.com/airpoints/onlineprofile.readwrite.my https://customerairnz.onmicrosoft.com/airpoints/summarize.read.my https://customerairnz.onmicrosoft.com/airpoints/airpoints.accountdetails.read.my';
        $redirectUri = "https://{$this->host}/identityreturn";
        $clientRequestId = '0194a687-2f2f-7fa1-a037-3c4b63008de0';
        $responseMode = 'fragment';
        $responseType = 'code';
        $xClientSku = 'msal.js.browser';
        $xClientVer = '3.13.0';
        $clientInfo = '1';
        $codeChallenge = 'gbqW0iM2xwzV-Z5e7kH9uWy-RYw8d3YdTA86ukKY5vM';
        $codeChallengeMethod = 'S256';
        $nonce = '0194a687-2f30-7081-8a37-fe1272408bb6';
        $state = 'eyJpZCI6IjAxOTRhNjg3LTJmMzAtN2E0MC1hNTRhLWNjYTljNWVkMzg4NiIsIm1ldGEiOnsiaW50ZXJhY3Rpb25UeXBlIjoicmVkaXJlY3QifX0=';

        $this->http->GetURL("https://identity.airnewzealand.com/customerairnz.onmicrosoft.com/b2c_1a_airnz_susi/oauth2/v2.0/authorize?" . http_build_query([
            'client_id'             => $clientID,
            'scope'                 => $scope,
            'redirect_uri'          => $redirectUri,
            'client-request-id'     => $clientRequestId,
            'response_mode'         => $responseMode,
            'response_type'         => $responseType,
            'x-client-SKU'          => $xClientSku,
            'x-client-VER'          => $xClientVer,
            'client_info'           => $clientInfo,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'nonce'                 => $nonce,
            'state'                 => $state,
        ]));

        if (!$this->http->FindPreg("/<form id=\"localAccountForm\" action/")) {
            return $this->checkErrors();
        }

        $csrf = $this->http->FindPreg('/"csrf":"(.+?)",/');
        $tenant = $this->http->FindPreg("/\"tenant\"\s*:\s*\"([^\"]+)/");
        $transId = $this->http->FindPreg("/\"transId\"\s*:\s*\"([^\"]+)/");
        $p = $this->http->FindPreg("/\"policy\"\s*:\s*\"([^\"]+)/");

        if (!$csrf || !$transId || !$p) {
            return $this->checkErrors();
        }

        $data = [
            "request_type" => "RESPONSE",
            "signInName"   => $this->AccountFields['Login'],
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Accept-Encoding"  => "gzip, deflate, br",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $csrf,
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->State['headers'] = $headers;
        $this->http->PostURL("https://identity.airnewzealand.com{$tenant}/SelfAsserted?tx={tx}&p={$p}", $data, $headers);
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status !== "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if ($message == 'Airpoints™ number / username doesn\'t match our records.') {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;
            }

            return false;
        }

        $this->logger->notice("Logging in...");
        $param = [];
        $param['rememberMe'] = "true";
        $param['csrf_token'] = $csrf;
        $param['tx'] = $transId;
        $param['p'] = $p;
        $param['diags'] = '{"pageViewId":"241a009e-bc7f-4444-b7d9-e37dbfe538d8","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1737960722,"acD":0},{"ac":"T021 - URL:https://identity.airnewzealand.com/branding/v3.html","acST":1737960722,"acD":1081},{"ac":"T019","acST":1737960723,"acD":3},{"ac":"T004","acST":1737960723,"acD":1},{"ac":"T003","acST":1737960723,"acD":1},{"ac":"T035","acST":1737960724,"acD":0},{"ac":"T030Online","acST":1737960724,"acD":0},{"ac":"T002","acST":1737960731,"acD":0},{"ac":"T018T010","acST":1737960729,"acD":1261}]}';
        $this->http->GetURL("https://identity.airnewzealand.com{$tenant}/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));
        $this->http->RetryCount = 2;

        $this->State['tenant'] = $tenant;
        $this->State['p'] = $p;
        $this->State['transId'] = $transId;
        $this->State['csrf_token'] = $csrf;

        return true;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if (
            !strstr($this->http->currentUrl(), '/challenge')
            && !strstr($this->http->currentUrl(), '/token')
        ) {
            return false;
        }

        $token = $this->http->FindPreg("/token=([^&]+)/", false, $this->http->currentUrl());
        $redirectUrl = $this->http->FindPreg("/redirectUrl=([^&]+)/", false, $this->http->currentUrl());
        $state = $this->http->FindPreg("/state=([^&]+)/", false, $this->http->currentUrl());

        if (!$token || !$redirectUrl || !$state) {
            $this->logger->error("token not found");

            return false;
        }

        $this->State['state'] = urldecode($state);

        $this->http->PostURL("https://au.signal.authsignal.com/v1/client/token", "{}", [
            "Accept"        => "application/json",
            "Authorization" => "Bearer {$token}",
            "Content-Type"  => "application/json",
            "Origin"        => "https://auth.identity.airnewzealand.com",
        ]);

        $responseToken = $this->http->JsonLog();

        if (!isset($responseToken->token)) {
            $this->logger->error("responseToken not found");

            return false;
        }

        $headers = [
            "Accept"        => "application/json",
            "Authorization" => "Bearer {$responseToken->token}",
            "Content-Type"  => "application/json",
            "Origin"        => "https://auth.identity.airnewzealand.com",
            "Referer"       => "https://auth.identity.airnewzealand.com/",
        ];
        $this->http->GetURL("https://au.signal.authsignal.com/v1/client/user-authenticators", $headers);
        $responseAuthenticators = $this->http->JsonLog();

        if (!$responseAuthenticators) {
            return false;
        }

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $email = null;
        $emailOTP = false;
        $emailUserAuthenticatorId = null;
        $emailVerificationMethod = null;
        $phone = null;
        $phoneUserAuthenticatorId = null;
        $phoneVerificationMethod = null;

        foreach ($responseAuthenticators as $responseAuthenticator) {
            // AUTHENTICATOR_APP
            if (!isset($responseAuthenticator->oobChannel)) {
                continue;
            }

            if ($responseAuthenticator->oobChannel == 'EMAIL_MAGIC_LINK') {
                $email = $responseAuthenticator->email;
                $emailUserAuthenticatorId = $responseAuthenticator->userAuthenticatorId;
                $emailVerificationMethod = $responseAuthenticator->oobChannel;
            }

            if ($responseAuthenticator->oobChannel == 'SMS') {
                $phone = $responseAuthenticator->phoneNumber;
                $phoneUserAuthenticatorId = $responseAuthenticator->userAuthenticatorId;
                $phoneVerificationMethod = $responseAuthenticator->oobChannel;
            }

            if ($responseAuthenticator->oobChannel == 'EMAIL_OTP' && !$email) {
                $emailOTP = true;
                $email = $responseAuthenticator->email;
                $emailUserAuthenticatorId = $responseAuthenticator->userAuthenticatorId;
                $emailVerificationMethod = $responseAuthenticator->oobChannel;
            }
        }

        $verificationMethod = $email ? $emailVerificationMethod : $phoneVerificationMethod;
        $data = [
            "userAuthenticatorId" => $email ? $emailUserAuthenticatorId : $phoneUserAuthenticatorId,
        ];
        $verificationMethodChallenge = strtolower(str_replace('_', '-', $verificationMethod));
        $this->http->PostURL("https://au.signal.authsignal.com/v1/client/challenge/{$verificationMethodChallenge}", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->challengeId)) {
            return false;
        }

        $this->State['headers'] = $headers;
        $this->State['token'] = $responseToken->token;
        $this->State['challengeId'] = $response->challengeId;

        if ($email && !$emailOTP) {
            $this->AskQuestion("A magic link was sent to {$email}. Copy and paste the link below", null, "VerificationViaLink");
        } elseif ($email && $emailOTP) {
            $this->AskQuestion("An authentication code was sent to {$email}.", null, "QuestionEmailOTP");
        } else {
            $this->AskQuestion("An authentication code was sent to {$phone}", null, "Question2fa");
        }

        return true;
    }

    public function Login()
    {
        if ($this->parseQuestion()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        // To sign in to our website you now need to be an Airpoints™ Member. If you are not already an Airpoints Member, you can join up now for free!
        if ($this->http->FindPreg("/To sign in to our website you now need to be an Airpoints\&trade\;\s*Member\./ims")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_INVALID_PASSWORD);
        }
        // Service unavailable
        if ($message = $this->http->FindSingleNode('//div[@id = "message" and @class = "app-error"]/p[contains(text(), "Service unavailable")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We were not able to log you in. It seems our server is currently under load.
        if ($this->http->FindSingleNode("//p[contains(text(), 'We were not able to log you in. It seems our server is currently under load.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Confirm your contact details')]")) {
            $this->throwProfileUpdateMessageException();
        }
        // gap
        if ($this->host == 'www.airnewzealand.com.sg') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/window\.location\.replace\('\/vloyalty\/action\/serviceunavailable'\);/")) {
            throw new CheckException("Service unavailable", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->State['PSToken'],
            'idpToken'      => $this->State['idToken'],
        ];

        $data = [
            'object' => [
                'companyCode'         => 'NZ',
                'programCode'         => 'AP',
                'membershipNumber'    => $this->AccountFields['Login'],
                'isBonusRequired'     => true,
                'tierOptionsRequired' => true,
                'creditBalance'       => 'Y',
            ],
        ];

        $this->http->PostURL("https://{$this->host}/airpoints-account/api/member-service/impl/member/v1/account-summary", json_encode($data), $headers);
        $accountSummary = $this->http->JsonLog();

        if (!isset($accountSummary)) {
            return $this->checkErrors();
        }

        if (isset($accountSummary->object->familyName)) {
            $this->surname = $accountSummary->object->familyName;
        }

        // Name
        $this->SetProperty('Name', beautifulName($accountSummary->object->givenName . ' ' . $accountSummary->object->familyName));
        // Status, refs #24544
        $this->SetProperty('Status', $accountSummary->object->tierName === 'Airpoints' ? 'Member' : $accountSummary->object->tierName);

        foreach ($accountSummary->object->pointDetails as $pointDetailsItem) {
            if ($pointDetailsItem->pointType !== 'APDNZ') {
                continue;
            }
            // Balance - Airpoints Dollars™ balance
            $this->SetBalance($pointDetailsItem->points);
        }

        $expDateData = $accountSummary->object->expiryDetails ?? [];
        $expDateFiltered = [];

        foreach ($expDateData as $expDateItem) {
            if ($expDateItem->pointType !== 'APDNZ') {
                continue;
            }
            $expDateFiltered[] = $expDateItem;
        }

        uasort($expDateFiltered, function ($a, $b) {
            $dateA = strtotime($a->expiryDate);
            $dateB = strtotime($b->expiryDate);

            if ($dateA == $dateB) {
                return 0;
            }

            return ($dateA < $dateB) ? -1 : 1;
        });

        $rightExpDateItem = current($expDateFiltered);

        if ($rightExpDateItem) {
            // Expiration Date
            $this->SetExpirationDate(strtotime($rightExpDateItem->expiryDate));
            // Airpoints Dollar Expiry
            $this->SetProperty('ExpiringBalance', $rightExpDateItem->points);
        }

        $tierOptions = $accountSummary->object->tierOptions ?? [];

        foreach ($tierOptions as $tierOption) {
            $options = $tierOption->options ?? [];

            if (count($options) != 1) {
                $this->sendNotification('refs #24353 airnewzeland - need to check tierOptions // IZ');

                continue;
            }

            $optionDetails = $options[0]->optionDetails ?? [];

            if (count($optionDetails) != 2) {
                $this->sendNotification('refs #24353 airnewzeland - need to check optionDetails // IZ');

                continue;
            }

            foreach ($optionDetails as $optionDetail) {
                if ($optionDetail->name == "Status Points" && $tierOption->type == 'upgrade') {
                    // Status Points Needed to Upgrade Status
                    $this->SetProperty('UpgradeNeeded', $optionDetail->next);
                    // Status Points Earned to Upgrade Status
                    $this->SetProperty('UpgradeEarned', $optionDetail->current);
                }

                if ($optionDetail->name == "Status Points" && $tierOption->type == 'retain') {
                    // Status Points Needed to Upgrade Status
                    $this->SetProperty('RetainNeeded', $optionDetail->next);
                    // Status Points Earned to Upgrade Status
                    $this->SetProperty('RetainEarned', $optionDetail->current);
                }
            }
        }
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);

        $clientId = '91b270e8-2507-47ff-bf1c-59c0ea4f4c01';
        $scope = 'openid profile offline_access https://customerairnz.onmicrosoft.com/airpoints/airpoints.claims.readwrite.my https://customerairnz.onmicrosoft.com/airpoints/ancillary.sale.readwrite.my https://customerairnz.onmicrosoft.com/airpoints/flightsearch.read.my https://customerairnz.onmicrosoft.com/airpoints/mfa.read.my https://customerairnz.onmicrosoft.com/airpoints/onlineprofile.read.my https://customerairnz.onmicrosoft.com/airpoints/onlineprofile.readwrite.my https://customerairnz.onmicrosoft.com/airpoints/summarize.read.my https://customerairnz.onmicrosoft.com/airpoints/airpoints.accountdetails.read.my';
        $redirectUri = "https://{$this->host}/identity/ssi";
        $clientRequestId = '0194a7f4-6d05-7d5f-b132-bd4770442ab1';
        $responseMode = 'fragment';
        $responseType = 'code';
        $xClientSku = 'msal.js.browser';
        $xClientVer = '3.14.0';
        $clientInfo = '1';
        $codeChallenge = 'dBsJGNUnQ8LXl8_NTErSHnX2j6DHSIhaBhbuTe11T84';
        $codeChallengeMethod = 'S256';
        $prompt = 'none';
        $nonce = '0194a7f4-6d06-7bcd-b768-5dd46d30532c';
        $state = 'eyJpZCI6IjAxOTRhN2Y0LTZkMDUtN2Y3OC1hYjkxLWIwMDgxNGY3MWY3MiIsIm1ldGEiOnsiaW50ZXJhY3Rpb25UeXBlIjoicmVkaXJlY3QifX0=';

        $params = [
            'client_id'             => $clientId,
            'scope'                 => $scope,
            'redirect_uri'          => $redirectUri,
            'client-request-id'     => $clientRequestId,
            'response_mode'         => $responseMode,
            'response_type'         => $responseType,
            'x-client-SKU'          => $xClientSku,
            'x-client-VER'          => $xClientVer,
            'client_info'           => $clientInfo,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'prompt'                => $prompt,
            'nonce'                 => $nonce,
            'state'                 => $state,
        ];

        $this->http->RetryCount = 0;
        $this->http->setMaxRedirects(3);
        $this->http->GetURL('https://identity.airnewzealand.com/customerairnz.onmicrosoft.com/b2c_1a_airnz_susi/oauth2/v2.0/authorize?' . http_build_query($params));
        $this->http->setMaxRedirects(5);
        $this->http->RetryCount = 2;

        $data = [
            'client_id'                  => $clientId,
            'redirect_uri'               => $redirectUri,
            'scope'                      => $scope,
            'code'                       => $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl()),
            'x-client-SKU'               => $xClientSku,
            'x-client-VER'               => $xClientVer,
            'x-ms-lib-capability'        => 'retry-after, h429',
            'x-client-current-telemetry' => '5|865,0,,,|@azure/msal-react,2.0.16',
            'x-client-last-telemetry'    => '5|0|61,0194a7f4-3f95-7eb2-9e4e-cb3957a34254,863,0194a7f4-3f95-7eb2-9e4e-cb3957a34254|no_tokens_found,monitor_window_timeout|2,0',
            'code_verifier'              => 'zciXme6ylLmh3UWe1j9OSmoYyquhJCq7Wc86XeniaqY',
            'grant_type'                 => 'authorization_code',
            'client_info'                => $clientInfo,
            'client-request-id'          => $clientRequestId,
            'X-AnchorMailbox'            => $this->xAnchorMailbox,
        ];

        $headers = [
            'content-type' => 'application/x-www-form-urlencoded;charset=utf-8',
            'Accept'       => '*/*',
        ];

        $this->http->PostURL('https://identity.airnewzealand.com/customerairnz.onmicrosoft.com/b2c_1a_airnz_susi/oauth2/v2.0/token', $data, $headers);
        $authResult = $this->http->JsonLog();

        if (!isset($authResult->access_token)) {
            $this->logger->debug('failed to get reservations token');

            return [];
        }

        $headers = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $authResult->access_token,
        ];

        $this->http->GetURL("https://{$this->host}/identity/api/my-bookings-web/v1/customers/my/bookings", $headers);

        $bookingsData = $this->http->JsonLog();

        $bookings = $bookingsData->bookings ?? [];

        if (count($bookings) > 0) {
            $this->sendNotification('refs #24353 airnewzeland - need to check bookings // IZ');
        }

        $this->http->RetryCount = 0;

        foreach ($bookings as $booking) {
            $this->logger->info('parse booking # ' . $booking->bookingReference);
            $data = [
                'surname' => $this->surname,
                'pnr'     => $booking->bookingReference,
            ];

            $this->http->PostURL("https://flightbookings.{$this->domain}/vmanage/actions/retrieve/manageBookings", $data, $headers);
            $this->parseItinerary();

            /*
            $this->http->GetURL('https://flightbookings.airnewzealand.co.nz/vmanage/ajax/bui/ancillaries');
            $this->http->JsonLog();
            $this->http->GetURL('https://flightbookings.airnewzealand.co.nz/vmanage/ajax/bui/metadata');
            $this->http->JsonLog();
            */
        }
        $this->http->RetryCount = 2;

        return [];
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        if ($step == 'Question2fa') {
            $data = [
                "sessionToken"     => $this->State['challengeId'],
                "verificationCode" => $answer,
            ];
            $this->http->PostURL("https://au.signal.authsignal.com/v1/client/verify/sms", json_encode($data), $this->State['headers']);
        } elseif ($step == 'QuestionEmailOTP') {
            $data = [
                "verificationCode" => $answer,
            ];
            $this->http->PostURL("https://au.signal.authsignal.com/v1/client/verify/email-otp", json_encode($data), $this->State['headers']);
        } else {
            if (!filter_var($answer, FILTER_VALIDATE_URL)) {
                $this->AskQuestion($this->Question, "The link you entered seems to be incorrect", "VerificationViaLink"); /*review*/

                return false;
            }

            $this->http->GetURL($answer);

            $this->http->RetryCount = 0;
            $data = [
                'challengeId' => $this->State['challengeId'],
            ];
            $this->http->PostURL("https://au.signal.authsignal.com/v1/client/verify/email-magic-link/finalize", json_encode($data), $this->State['headers']);
            $this->http->RetryCount = 2;
        }

        $response = $this->http->JsonLog();

        if (!empty($response->failureReason)) {
            switch ($response->failureReason) {
                case 'CODE_INVALID_OR_EXPIRED':
                    $this->AskQuestion($this->Question, "Code is invalid or has expired", $step);

                    break;

                default:
                    $this->DebugInfo = "2fa: {$response->failureReason}";
            }

            return false;
        }

        $headers = [
            "Accept"  => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
            "Referer" => "https://auth.identity.airnewzealand.com/",
        ];

        $this->http->setMaxRedirects(10);
        $this->http->GetURL("https://au-connect.authsignal.com/callback?redirect_uri=https://identity.airnewzealand.com/customerairnz.onmicrosoft.com/oauth2/authresp&token={$response->accessToken}&state={$this->State['state']}", $headers);
        $this->http->setMaxRedirects(7);

        unset($this->State['headers']);
        unset($this->State['token']);
        unset($this->State['state']);
        unset($this->State['challengeId']);

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8',
            'Accept'       => '*/*',
        ];

        $payload = $this->jwtExtractPayload($response->accessToken);

        $this->xAnchorMailbox = "Oid:{$payload->sub}-b2c_1a_airnz_susi@25ef46a8-e271-4c37-9261-c7cd9e41a2b5";

        $data = [
            'client_id'                  => '5cfd7b3b-f095-4a1e-9860-5f03ade4d715',
            'redirect_uri'               => "https://{$this->host}/airpoints-account/airpoints/auth/login",
            'scope'                      => 'openid profile offline_access',
            'code'                       => $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl()),
            'x-client-SKU'               => 'msal.js.browser',
            'x-client-VER'               => '3.13.0',
            'x-ms-lib-capability'        => 'retry-after, h429',
            'x-client-current-telemetry' => '5|865,0,,,|@azure/msal-react,2.0.16',
            'x-client-last-telemetry'    => '5|0|||0,0',
            'code_verifier'              => 'hL-fOPvbShYFPvDruhVFy8Fl5QAQY0gmHsnk0s73PEk',
            'grant_type'                 => 'authorization_code',
            'client_info'                => '1',
            'client-request-id'          => '0194a687-2f2f-7fa1-a037-3c4b63008de0',
            'X-AnchorMailbox'            => $this->xAnchorMailbox,
        ];

        $this->http->PostURL('https://identity.airnewzealand.com/customerairnz.onmicrosoft.com/b2c_1a_airnz_susi/oauth2/v2.0/token', $data, $headers);

        return $this->loginSuccessful();
    }

    private function parseItinerary()
    {
        $baseUrl = $this->http->FindPreg('/^(.*)\/booking/', false, $this->http->currentUrl());
        $this->http->GetURL("{$baseUrl}/vmanage/ajax/bui/travel-details");
        $travelDetails = $this->http->JsonLog();
        $this->http->GetURL("{$baseUrl}/vmanage/ajax/bui/passengers");
        $passengers = $this->http->JsonLog();

        // checking that currency code is the same for all payments
        $currencyCodes = [];

        foreach ($travelDetails->payments->paymentBreakup->otherPayments as $payment) {
            $currencyCodes[] = $payment->amount->currencyCode;
        }

        foreach ($travelDetails->payments->paymentBreakup->airpointsPayments as $payment) {
            $currencyCodes[] = $payment->amount->currencyCode;
        }

        // if more than one currency code then return because this case need to be checked
        if (count(array_unique($currencyCodes)) > 1) {
            $this->sendNotification('refs #25144 aiurnewzealand - need to check currency code // IZ');

            return;
        }

        $f = $this->itinerariesMaster->createFlight();
        $f->general()->confirmation($travelDetails->contentHeader->pnr, 'Booking reference', true);

        foreach ($travelDetails->itinerary as $itineraryCounter => $itinerary) {
            foreach ($itinerary->flights as $flightCounter => $flight) {
                $s = $f->addSegment();

                $s->airline()->number($this->http->FindPreg('/\d+/', false, $flight->flightNumber));
                $s->airline()->name($flight->operatingCarrier->code);

                $s->departure()->strict();
                $s->departure()->code($flight->departure->airport->code);
                $s->departure()->name($flight->departure->airport->name);
                $s->departure()->date2($flight->departure->dateTimeLocal);

                $s->arrival()->strict();
                $s->arrival()->code($flight->arrival->airport->code);
                $s->arrival()->name($flight->arrival->airport->name);
                $s->arrival()->date2($flight->arrival->dateTimeLocal);

                $s->extra()->aircraft($flight->aircraft->name);
                $s->extra()->cabin($flight->cabin->name);
                $s->extra()->bookingCode($flight->cabin->bookingClass);
                $s->extra()->duration($flight->duration);

                $meals = [];

                foreach ($passengers->itinerary[$itineraryCounter]->flights[$flightCounter]->passengers as $passenger) {
                    $title = $passenger->title ?? '';
                    $firstName = $passenger->firstName ?? '';
                    $middleName = $passenger->middleName ?? '';
                    $surname = $passenger->surname ?? '';
                    $f->general()->traveller(beautifulName($title . ' ' . $firstName . ' ' . $middleName . ' ' . $surname), true);
                    $meals[] = $passenger->meal ?? null;
                }

                $s->extra()->meals(array_unique($meals));
            }
        }

        if (count($travelDetails->payments->paymentBreakup->otherPayments) > 1) {
            $this->sendNotification('refs #25144 airnewzealand - need to check other payments // IZ');
        }

        if (count($travelDetails->payments->paymentBreakup->airpointsPayments) > 1) {
            $this->sendNotification('refs #25144 airnewzealand - need to check airpoints payments // IZ');
        }

        $f->price()->total($travelDetails->payments->paymentBreakup->otherPayments[0]->amount->amount);

        $fareConstants = [
            'ADULT_FARES'  => 'Adult Fare',
            'CHILD_FARES'  => 'Child Fare',
            'INFANT_FARES' => 'Infant Fare',
        ];

        /*
        foreach ($travelDetails->payments->paymentBreakup->otherPayments as $payment) {
            $f->price()->fee($fareConstants[$payment->paymentLineItemType], $payment->amount->amount);
        }
        */

        $spentAwards = 0;

        foreach ($travelDetails->payments->paymentBreakup->airpointsPayments as $payment) {
            $spentAwards += $payment->amount->amount;
            $f->price()->fee($fareConstants[$payment->paymentLineItemType], $payment->amount->amount);
        }

        $f->price()->spentAwards($spentAwards);
        $f->price()->currency(array_unique($currencyCodes)[0]);

        if (count($travelDetails->payments->taxes) > 0) {
            $this->sendNotification('refs #25144 airnewzealand - need to check taxes // IZ');
        }
    }

    private function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions))) {
            $region = 'NewZealand';
        }

        return $region;
    }

    private function setRegionSettings()
    {
        $this->logger->notice(__METHOD__);
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2'] ?? null);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);
        // Identification host
        if (!empty($this->AccountFields['Login2'])) {
            // http://www.airnewzealand.eu/gateway
            switch ($this->AccountFields['Login2']) {
                case 'Australia':
                    $this->host = 'www.airnewzealand.com.au';

                    break;

                case 'Canada':
                    $this->host = 'www.airnewzealand.ca';

                    break;

                case 'China':
                    $this->host = 'www.airnewzealand.com.cn';

                    break;
                /*
                // program is not supported for this region now
                case 'Deutschland':
                    $this->host = 'www.airnewzealand.de';
                    break;
                case 'France':
                    $this->host = 'www.airnewzealand.fr';
                    break;
                */
                case 'HongKong':
                    $this->host = 'www.airnewzealand.com.hk';

                    break;

                case 'Japan':
                    $this->host = 'www.airnewzealand.co.jp';

                    break;

                case 'PacificIslands':
                    $this->host = 'www.pacificislands.airnewzealand.com';

                    break;
                /*
                case 'FrenchPolynesia':
                    $this->host = 'www.airnewzealand.pf';
                    break;
                */
                case 'UK':
                    $this->host = 'www.airnewzealand.co.uk';

                    break;

                case 'USA':
                    $this->host = 'www.airnewzealand.com';

                    break;

                default:
                    $this->host = 'www.airnewzealand.co.nz';
            }

            if (isset($this->State['Host'])) {
                $this->logger->notice('Get Region from State (Login2: ' . $this->AccountFields['Login2'] . ') => ' . $this->State['Host']);
                $this->host = $this->State['Host'];
            }

            $this->domain = $this->http->FindPreg('/www\.(.+)$/', false, $this->host);
        }
    }

    private function generatePSToken()
    {
        if (!isset($this->State['idToken'])) {
            return false;
        }

        $headers = [
            'Accept'      => 'application/json, text/plain, */*',
            'idpToken'    => $this->State['idToken'],
            'companyCode' => 'NZ',
            'Host'        => 'www.airnewzealand.com',
        ];

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://{$this->host}/airpoints-account/api/authservice/impl/auth/generatePSToken", $headers);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        $PSToken = $this->http->Response['headers']['ps-token'] ?? null;

        if (!isset($PSToken)) {
            return $this->checkErrors();
        }
        $this->State['PSToken'] = $PSToken;

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindPreg('/(?:Operation timed out after|Network error 56 - Received HTTP code 503 from proxy after CONNECT|Network error 56 - Proxy CONNECT aborted)/', false, $this->http->Error)
        ) {
            throw new CheckRetryNeededException(3, 1);
        }

        // Website temporarily unavailable
        if ($message = $this->http->FindSingleNode("//h1[
                contains(text(), 'Website temporarily unavailable')
                or contains(text(), 'Website Temporarily Unavailable')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The Air New Zealand website is temporarily unavailable.
        if ($this->http->FindSingleNode("//p[contains(text(), 'The Air New Zealand website is temporarily unavailable.')]")
            || $this->http->FindPreg("/The Air\&nbsp;New\&nbsp;Zealand website is temporarily unavailable\./ims")) {
            throw new CheckException("The Air New Zealand website is temporarily unavailable.", ACCOUNT_PROVIDER_ERROR);
        }
        // maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently performing planned maintenance to our website')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The system is currently not available.
        if ($message = $this->http->FindPreg("/(The system is currently not available\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 502 Bad Gateway
        if (
            $this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $tokenData = $this->http->jsonLog();

        if (!isset($tokenData->id_token)) {
            return false;
        }

        $tokenDataDecoded = $this->jwtExtractPayload($tokenData->id_token);

        if (!isset($tokenDataDecoded->airpointsMember)) {
            return false;
        }

        $this->State['idToken'] = $tokenData->id_token;

        if (
            (
                strtolower($tokenDataDecoded->airpointsMember) === strtolower($this->AccountFields['Login'])
                || strtolower($tokenDataDecoded->username) === strtolower($this->AccountFields['Login'])
            )
            && $this->generatePSToken()
        ) {
            return true;
        }

        return false;
    }

    private function convertBase64UrlToBase64(string $input): string
    {
        $remainder = strlen($input) % 4;

        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }

        return strtr($input, '-_', '+/');
    }

    private function jwtExtractPayload(string $jwt)
    {
        $this->logger->debug('DECODING JWT: ' . $jwt);
        $bodyb64 = explode('.', $jwt)[1];
        $this->logger->debug('JWT BODY: ' . $bodyb64);
        $bodyb64Prepared = $this->convertBase64UrlToBase64($bodyb64);
        $this->logger->debug('JWT BODY B64 PREPARED: ' . $bodyb64Prepared);
        $payloadRaw = base64_decode($bodyb64Prepared);
        $this->logger->debug('JWT PAYLOAD RAW: ' . $bodyb64Prepared);

        return $this->http->JsonLog($payloadRaw);
    }
}
