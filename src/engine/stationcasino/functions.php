<?php

class TAccountCheckerStationcasino extends TAccountChecker
{
    private $QuestionForPIN = 'Enter your PIN Number please.';
    private $headers = [
        'Accept'       => 'application/json, text/plain, */*',
        'Content-Type' => 'application/json',
        'Referer'      => 'https://myrewards.stationcasinos.com/',
        'Origin'       => 'https://myrewards.stationcasinos.com',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->RetryCount = 0;
    }

    public function IsLoggedIn()
    {
        if (empty($this->State['authToken'])) {
            return false;
        }
        $this->headers['Authorization'] = $this->State['authToken'];

        return $this->loginSuccessful();
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email.', ACCOUNT_INVALID_PASSWORD);
        }

        if (strlen($this->AccountFields['Login2'] ?? '') < 1) {
            throw new CheckException("To update this {$this->AccountFields['DisplayName']} account you need to correctly fill in the 'Boarding Pass' field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }

        if (empty($this->Answers[$this->QuestionForPIN])) {
            $this->AskQuestion($this->QuestionForPIN);

            return false;
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://myrewards.stationcasinos.com/accountmanagement/myrewards');

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

        $this->http->PostURL('https://api-dmzaks.stationcasinos.com/accountmanagement/mobileapigateway/sso/v1/session', '{"deviceType":"Web","deviceId":"1683796444126","sourceType":"STNWeb","propertyTenant":"redrock"}', $this->headers);

        if ($this->http->Response['code'] !== 200 || empty($this->http->Response['headers']['authorization'])) {
            return false;
        }

        $this->headers['Authorization'] = $this->State['authToken'] = $this->http->Response['headers']['authorization'];

        $this->http->GetURL('https://api-dmzaks.stationcasinos.com/accountmanagement/mobileapigateway/sso/v1/stnaccount?email=' . $this->AccountFields['Login'], $this->headers);
        $status = $this->http->JsonLog()->status ?? null;

        if ($status == 404) {
            $this->logger->notice("send email");
            $data = [
                "email"        => $this->AccountFields['Login'],
                "customerName" => "Guest",
                "emailType"    => "verifyEmail",
            ];
            $this->http->PostURL("https://api-dmzaks.stationcasinos.com/accountmanagement/mobileapigateway/sso/v1/sendemailotp", json_encode($data), $this->headers);
            $this->http->JsonLog();
            $this->http->RetryCount = 2;

            if ($this->http->Response['code'] !== 200) {
                return false;
            }

            $this->AskQuestion("Please enter the 6-digit code we sent to {$this->AccountFields['Login']}", null, "Question");

            return false;
        }

        if ($this->http->Response['code'] != 200 || empty($this->http->Response['headers']['authorization'])) {
            return false;
        }
        $this->headers['Authorization'] = $this->http->Response['headers']['authorization'];
        $data = [
            'userId'           => $this->AccountFields['Login'],
            'password'         => $this->AccountFields['Pass'],
            'identityProvider' => 'adb2c',
        ];
        $this->http->PostURL('https://api-dmzaks.stationcasinos.com/accountmanagement/mobileapigateway/sso/v2/authenticate', json_encode($data), $this->headers);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function ProcessStep($step)
    {
        if ($this->Question == $this->QuestionForPIN) {
            if (!$this->LoadLoginForm()) {
                return false;
            }

            return $this->Login();
        }
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->headers['Authorization'] = $this->State['authToken'];

        $data = [
            "email"         => $this->AccountFields['Login'],
            "otpToValidate" => $answer,
        ];
        $this->http->PostURL("https://api-dmzaks.stationcasinos.com/accountmanagement/mobileapigateway/sso/v1/validateotp", json_encode($data), $this->headers);
        $response = $this->http->JsonLog();

        if (isset($response->message) && $response->message == "Invalid Otp {$answer} for email: {$this->AccountFields['Login']}") {
            $this->AskQuestion($this->Question, "That’s an invalid code. Try again.", "Question");

            return false;
        }

        if ($this->http->Response['code'] != 200 || empty($this->http->Response['headers']['authorization'])) {
            return false;
        }
        $this->headers['Authorization'] = $this->http->Response['headers']['authorization'];
        $data = [
            'customerName' => 'Guest',
            'password'     => $this->AccountFields['Pass'],
        ];
        $this->http->PostURL('https://api-dmzaks.stationcasinos.com/accountmanagement/mobileapigateway/sso/v1/stnaccount', json_encode($data), $this->headers);

        if ($this->http->Response['code'] != 200 || empty($this->http->Response['headers']['authorization'])) {
            return false;
        }
        $this->headers['Authorization'] = $this->http->Response['headers']['authorization'];
        $data = [
            'userId'           => $this->AccountFields['Login2'],
            'password'         => $this->Answers[$this->QuestionForPIN],
            'identityProvider' => 'cms',
        ];
        $this->http->PostURL('https://api-dmzaks.stationcasinos.com/accountmanagement/mobileapigateway/sso/v2/authenticate', json_encode($data), $this->headers);

        if ($this->http->Response['code'] != 200 || empty($this->http->Response['headers']['authorization'])) {
            $this->logger->error("authorization not found");

            return false;
        }
        $this->headers['Authorization'] = $this->http->Response['headers']['authorization'];

        return $this->loginSuccessful();
    }

    public function Login()
    {
        if (!empty($this->http->Response['headers']['authorization'])) {
            $this->headers['Authorization'] = $this->State['authToken'] = $this->http->Response['headers']['authorization'];

            if ($this->loginSuccessful()) {
                return true;
            }

            if ($this->http->Response['body'] == '{"profileElements":{"addresses":[],"phones":[],"emails":[],"applicationEnrollments":[]},"identifierElements":[]}') {
                $this->throwProfileUpdateMessageException();
            }

            return false;
        }

        $this->logger->error("authorization not found");
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;
        $message = $response->message ?? null;
        // Password doesn’t match our records.
        if ($status == 401 && $message == "Request failed with status code 401") {
            throw new CheckException("Password doesn’t match our records.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $profile = $response->profileElements ?? null;
        $firstName = $profile->firstName ?? null;
        $middleName = $profile->middleName ?? null;
        $lastName = $profile->lastName ?? null;
        // Name
        $this->SetProperty('Name', beautifulName("$firstName $middleName $lastName"));
        // Boarding Pass
        $this->SetProperty('AccountNumber', $response->identifierElements[0]->identifierValue ?? null);
        $this->http->GetURL('https://api-dmzaks.stationcasinos.com/accountmanagement/mobileapigateway/sso/v1/summary', $this->headers);
        $summary = $this->http->JsonLog();
        // Balance - Points
        $this->SetBalance($summary->points->pointTotal ?? null);
        // Status
        $this->SetProperty('Status', $summary->status->tierName ?? null);
        // Status credits
        if (is_numeric($summary->status->credits ?? null)) {
            $this->SetProperty('StatusCredits', floor($summary->status->credits));
        }
        // Status credits to next level
        if (is_numeric($summary->status->maximumCredits ?? null) && is_numeric($summary->status->credits ?? null)) {
            $this->SetProperty('StatusCreditsToNextLevel', ceil($summary->status->maximumCredits) - floor($summary->status->credits));
        }
        // Status Expiration
        if ($statusExp = strtotime($summary->status->tierExpiration ?? '')) {
            $this->SetProperty('StatusExpiration', date('d/m/Y', $statusExp));
        }

        // offers
        $currentYearAndMonth = date('Y-m');
        $plusTwoYears = date('Y-m-t', strtotime('+2 years'));
        $this->http->GetURL("https://api-dmzaks.stationcasinos.com/accountmanagement/mobileapigateway/sso/v2/offer?fromDate={$currentYearAndMonth}-01&toDate={$plusTwoYears}", $this->headers);
        $offers = $this->http->JsonLog();
        $this->logger->debug("Total " . ((is_array($offers) || ($offers instanceof Countable)) ? count($offers) : 0) . " offers were found");
        $this->SetProperty("CombineSubAccounts", false);
        $now = time();

        // errors possible with filtering, maybe more conditions required
        foreach ($offers as $offer) {
            $displayName = trim($offer->displayTitle ?? '');
            $expStr = $offer->endDate ?? '';
            $exp = strtotime($expStr);

            if ($exp < $now) {
                $this->logger->notice("[skip old offer]: {$displayName} / {$expStr}");

                continue;
            }

            if (isset($offer->accepted->status) && $offer->accepted->status != ' ') {
                $this->logger->notice("[skip redeemed offer]: {$displayName} / {$expStr}");

                continue;
            }
            $barcode = trim($offer->barcode ?? null);
            $name = str_replace(' ', '', $offer->name ?? '');
            $balance = (is_numeric($offer->awardAmount ?? null) && is_numeric($offer->amountUsed ?? null))
                ? $offer->awardAmount - $offer->amountUsed
                : null;
            $this->AddSubAccount([
                'Code'           => "Offer{$name}{$barcode}",
                'DisplayName'    => $displayName,
                'Balance'        => $balance,
                'ExpirationDate' => $exp,
                // 'BarCode'        => $barcode,
                // 'BarCodeType'    => ??? not visible on website
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        unset($this->headers['Content-Type']);

        $this->http->GetURL('https://api-dmzaks.stationcasinos.com/accountmanagement/mobileapigateway/sso/v1/profile', $this->headers, 20);
        $response = $this->http->JsonLog();
        $firstName = $response->profileElements->firstName ?? null;
        $this->logger->debug("[Name]: {$firstName}");
        $identifierValue = $response->identifierElements[0]->identifierValue ?? null;
        $this->logger->debug("[identifierValue]: {$identifierValue}");

        return isset($firstName) || isset($identifierValue);
    }
}
