<?php

class TAccountCheckerAzal extends TAccountChecker
{
    private $headers = [
        'Accept'         => 'application/json, text/plain, */*',
        'Content-Type'   => 'application/json',
        'rsl-flow'       => 'comarch',
        'x-client-id'    => 'ibe',
        'x-locale'       => 'en',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        return $this->loginSuccessful();
    }

    public function LoadLoginForm()
    {
        // Check your password or card
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false) {
            throw new CheckException("Check your password or card", ACCOUNT_INVALID_PASSWORD);
        }

        if (empty($this->AccountFields['Pass'])) {
            throw new CheckException("To update this Azerbaijan Airlines (Azal Miles) account you need to update your credentials. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        $this->http->GetURL('https://profile.azal.az/login');

        if (
            !$this->http->FindSingleNode('//title[contains(text(), "Azal.az")]')
            || $this->http->Response['code'] != 200
        ) {
            return $this->checkErrors();
        }

        $data = [
            'userId'	    => $this->AccountFields['Login'],
            'password'	  => $this->AccountFields['Pass'],
            'rememberMe' => true,
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://profile.azal.az/api/auth/login', json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $authResult = $this->http->JsonLog();

        if (isset($authResult->accessToken->value)) {
            $this->State['accessToken'] = $authResult->accessToken->value;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if (isset($authResult->error)) {
            $message = $authResult->error->message ?? null;
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Wrong membership number or password')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'We are unable to perform this action at the moment, please try again later or report the problem to us')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $userInfo = $this->http->JsonLog();

        $this->SetBalance($userInfo->loyaltyProgram->flightMiles->total);

        if (isset($userInfo->loyaltyProgram->membershipId)) {
            // Account #
            $this->SetProperty('Account', $userInfo->loyaltyProgram->membershipId);
        }

        if (isset($userInfo->name->displayName)) {
            // Name
            $this->SetProperty('Name', $userInfo->name->displayName);
        }

        if (isset($userInfo->loyaltyProgram->tier->name->en)) {
            // Active/Classic or Active/Gold or Active/Platinum
            $this->SetProperty('Status', $userInfo->loyaltyProgram->tier->name->en);
        }

        if (isset($userInfo->enrollDate)) {
            // Enroll date
            $this->SetProperty('MemberSince', strtotime($userInfo->enrollDate));
        }

        if (isset($userInfo->loyaltyProgram->statusMiles->total)) {
            // Status points
            $this->SetProperty('PointsNextTier', $userInfo->loyaltyProgram->statusMiles->total);
        }

        if (isset($userInfo->loyaltyProgram->expiredFlightMiles->total)) {
            // Expired points
            $this->SetProperty('MilesExpired', $userInfo->loyaltyProgram->expiredFlightMiles->total);
        }

        if (isset($userInfo->validityDate)) {
            // Status validity
            $this->SetProperty('CardValidityPeriod', strtotime($userInfo->validityDate));
        }

        if (isset($userInfo->loyaltyProgram->tierUpgrade->requiredMiles->total)) {
            // Status Points needed to next tier
            $this->SetProperty('StatusPointsNeededToNextTier', $userInfo->loyaltyProgram->tierUpgrade->requiredMiles->total);
        }
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg('/We are unable to perform this action at the moment, please try again later or report the problem to us/')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->State['accessToken'])) {
            return false;
        }

        $headers = $this->headers;
        $headers['authorization'] = $this->State['accessToken'];

        $userID = $this->http->FindPreg('/"sub":\s*"(.+?)"/', false, base64_decode($this->State['accessToken']));

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://profile.azal.az/api/profiles/' . $userID, $headers);
        $this->http->RetryCount = 2;

        $userInfo = $this->http->JsonLog();
        $membershipId = $userInfo->loyaltyProgram->membershipId ?? null;

        if ($membershipId === $this->AccountFields['Login']) {
            return true;
        }

        return false;
    }
}
