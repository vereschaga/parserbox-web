<?php

class TAccountCheckerJumeirah extends TAccountChecker
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
//        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->logger->notice(__METHOD__);

        /*
        if (!isset($this->State['code'])) {
            return false;
        }
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.jumeirah.com/services/get-decrypted-data', json_encode(["value" => $this->State['code']]), $this->headers);
        $this->http->RetryCount = 2;
        */

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $this->http->GetURL('https://www.jumeirah.com/en/login');

        if (!$this->http->ParseForm(null, '//div[@id = "account-login-form"]')) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.jumeirah.com/services/csrf-token');
        $this->http->RetryCount = 2;

        $data = [
            "userName" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.jumeirah.com/loyalty-services/loyalty-login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable - DNS failure")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Our website is currently down for maintenance and should be live again at approximately")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // We are experiencing a technical problem at the moment and sincerely apologize for any inconvenience this might cause you. Please try again shortly or contact our reservations team at the link below for further assistance. Thank you.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We are experiencing a technical problem at the moment and sincerely apologize for any inconvenience this might cause you.")]')) {
            throw new CheckException("We are experiencing a technical problem at the moment and sincerely apologize for any inconvenience this might cause you. Please try again shortly or contact our reservations team for further assistance. Thank you.", ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("https://www.jumeirah.com/en");

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "We are working on enriching our online experience.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->token)) {
            $this->http->RetryCount = 0;

            if (empty($response)) {
                return false;
            }
//            $this->http->PostURL('https://www.jumeirah.com/services/get-encrypted-data', json_encode(["value" => $response]), $this->headers);
//            $this->http->RetryCount = 2;
//            $code = $this->http->FindPreg("/(.+)/");
//            $this->http->RetryCount = 0;
//            $this->http->PostURL('https://www.jumeirah.com/services/get-decrypted-data', json_encode(["value" => $code]), $this->headers);
//            $this->http->RetryCount = 2;
//
            if ($this->loginSuccessful()) {
//                $this->State['code'] = $code;

                return true;
            }

            return false;
        }
        // Errors
        $response = $this->http->JsonLog(null, 0);
        $errorCode = $response->errorCodes[0]->errorCode ?? null;

        if (!empty($errorCode)) {
            $this->logger->error("[errorCode]: {$errorCode}");
            // LOGIN FAILED. PLEASE TRY AGAIN
            if ($errorCode === 'INVALID_CREDENTIALS') {
                throw new CheckException('LOGIN FAILED. PLEASE TRY AGAIN', ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }// if(!empty($errorCode)

        if (
            $this->AccountFields['Login'] == '4110280494'
            && strstr($this->AccountFields['Pass'], '%')
            && strstr($this->AccountFields['Pass'], '*')
            && strstr($this->AccountFields['Pass'], '^')
        ) {
            throw new CheckException('LOGIN FAILED. PLEASE TRY AGAIN', ACCOUNT_INVALID_PASSWORD);
        }

        $error = $response->error ?? null;

        if ($error) {
            $this->logger->error("[error]: {$error}");

            if ($errorCode === 'Internal Server Error') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);
        // Name
        $firstName = $response->memberData->firstName ?? '';
        $lastName = $response->memberData->lastName ?? '';
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));
        // Membership Number
        $memberId = $response->memberData->memberId ?? null;
        $this->SetProperty("Number", $memberId);
        // EXPIRY: 07/2021 - Tier Expiration
        $tierExpiration = $response->memberData->tierEndDate ?? null;

        if (!empty($tierExpiration)) {
            $this->SetProperty("TierExpiration", date('m/Y', (strtotime($tierExpiration))));
        }
        // POINTS DUE TO EXPIRE
        $pointsExpiration = $response->memberData->pointsExpiration ?? null;

        if (isset($pointsExpiration)) {
            // Send notification
            if (count($pointsExpiration) > 1) {
                $this->logger->info("PointsExpiration", ['Header' => 3]);
                $this->logger->debug(var_export(["PointsExpiration" => $pointsExpiration], true), ['pre' => true]);
                $this->sendNotification("refs #19262: Found multiple point expiration's : " . count($pointsExpiration) . " //KS");
            } // Send notification

            foreach ($pointsExpiration as $item) {
                // Expiring Balance
                $this->SetProperty("ExpiringBalance", number_format($item->points) ?? null);

                if ($exp = strtotime($item->expirationDate ?? null)) {
                    $this->SetExpirationDate($exp);
                }
                // Send notification
                $loyaltyAccount = $item->loyaltyAccount ?? null;

                if (!empty($item->loyaltyAccount) && $loyaltyAccount !== 'Points') {
                    $this->sendNotification("refs #19262: Found new loyalty Account : " . $loyaltyAccount . " //KS");
                } // Send notification
            }
        }
        // Status
        $statuses = [
            "JM" => "MEMBER",
            "JS" => "SILVER",
            "JG" => "GOLD",
            "JP" => "PLATINUM",
            "JV" => "PRIVATE",
            "JR" => "OWNER",
        ];
        $tierClass = $response->memberData->tierClass ?? null;

        if (!empty($tierClass)) {
            $status = $statuses[$tierClass] ?? null;
            $this->SetProperty('Status', $statuses[$tierClass] ?? null);

            if (empty($status)) {
                $this->sendNotification("refs #19262: unknown status appeared : {$tierClass} //KS");
            }
        }
        // Rewards
        if (!isset($memberId)) {
            return;
        }
        $noBalance =
            $this->http->FindPreg('/"balances":\[\],"/')
            ?? $this->http->FindPreg('/"balances":\[\{"loyaltyAccount":"Tier Points","balance":"0.0","balanceCurrencyAmount":"0","totalExpire":"0","loyaltyAccountId":"2"\}\],/')
        ;

        $this->headers["Loyalty-Token"] = $response->token;
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.jumeirah.com/loyalty-services/loyalty-get-rewards/{$memberId}", [], $this->headers);

        // Network error 28
        if (strstr($this->http->Error, 'Network error 28 - Operation timed out after')) {
            $this->http->PostURL("https://www.jumeirah.com/loyalty-services/loyalty-get-rewards/{$memberId}", [], $this->headers);
        }

        $this->http->RetryCount = 2;
        $rewards = $this->http->JsonLog() ?? [];
        // isLoggedIn issue: provider bug fix, session is lost on rewards page after some time
        $errorStatus =
            $rewards->errorStatus
            ?? $rewards->error
            ?? null
        ;

        if (
            in_array($errorStatus, ["UNAUTHORIZED", "INTERNAL_SERVER_ERROR", "Internal Server Error"])
            || $this->http->FindSingleNode('//title[contains(text(), "HTTP Status 500 – Internal Server Error")]')
        ) {
            $this->logger->error($errorStatus);

            // always 500 on this page
            if ($this->AccountFields['Login'] != 'zaid@suleman.co.za') {
                throw new CheckRetryNeededException(2, 0);
            }
        }

        // Balances
        $balances = $response->memberData->balances ?? [];

        foreach ($balances as $item) {
            // Jumeirah one Balance
            if ($item->loyaltyAccount == "Points") {
                $this->SetBalance($item->balance);
            }
            // Tier Points
            if ($item->loyaltyAccount == "Tier Points") {
                $this->SetProperty("TierPoints", number_format($item->balance) ?? null);
            }
        }
        // Account ID: 5193425
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $noBalance
            && count($pointsExpiration) <= 1
            && empty($pointsExpiration[0]->pointsExpiration)
            && ($pointsExpiration[0]->points === "0.0")
            && empty($pointsExpiration[0]->loyaltyAccount)
        ) {
            $this->SetBalanceNA();
        }

        // provider bug fix
        if (
            $this->http->Response['code'] == 403
            && $this->http->FindSingleNode('//center[contains(text(), "Microsoft-Azure-Application-Gateway/v2")]')
        ) {
            return;
        }

        foreach ($rewards as $reward) {
            if (!isset($reward->rewardID)) {
                $this->logger->error("something went wrong");

                continue;
            }

            $this->AddSubAccount([
                "Code"           => "jumeirahReward" . $reward->rewardID,
                "DisplayName"    => $reward->rewardName,
                "Balance"        => null,
                "ExpirationDate" => strtotime($reward->validTill),
                "RewardId"       => $reward->rewardID,
                "Location"       => $reward->sponsor ?? null,
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();
        $token = $response->token ?? null;
        $memberId = $response->memberData->memberId ?? null;

        if (!empty($token) && !empty($memberId)) {
            return true;
        }

        return false;
    }
}
