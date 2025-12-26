<?php

class TAccountCheckerVirginaubiz extends TAccountChecker
{
    private $tierToDate;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && str_contains($properties['SubAccountCode'], 'FlightSpend')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser(): void
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn(): bool
    {
        $exp = $this->State['expiration'] ?? 0;

        if ($exp < time()) {
            return false;
        }

        $this->http->setDefaultHeader('Authorization', 'Bearer ' . $this->State['token']);

        return $this->loginSuccessful();
    }

    public function LoadLoginForm(): bool
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://business.velocityfrequentflyer.com/#/auth/login');
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://business.velocityfrequentflyer.com/lmpapi/api/business-config-service/v1/config/data/login/VA/SME', ['Accept' => 'application/json, text/plain, */*']);
        $response = $this->http->JsonLog(null, 3, false, 'secret')->object->ui ?? null;
        $secret = $response->secret ?? null;

        if ($secret === null) {
            return false;
        }

        $clientId = $response->clientId ?? 'auth-channel';
        $data = [
            'companyCode'      => 'VA',
            'customerPassword' => $this->AccountFields['Pass'],
            'userId'           => $this->AccountFields['Login'],
        ];
        $this->http->PostURL('https://business.velocityfrequentflyer.com/lmpapi/api/authservice/users/login', json_encode($data), [
            'content-type' => 'application/json',
            'Accept'       => 'application/json, text/plain, */*',
            'clientId'     => $clientId,
            'secret'       => $secret,
        ]);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login(): bool
    {
        $response = $this->http->JsonLog();
        $token = $this->http->Response['headers']['ps-token'] ?? null;

        if (!empty($response->success) && !empty($token)) {
            $this->State['token'] = $token;
            $this->State['expiration'] = $response->details->cliams->exp ?? time() + 300; // session lives minimum 5 minutes
            $this->http->setDefaultHeader('Authorization', 'Bearer ' . $token);

            return $this->loginSuccessful();
        }

        $message = $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                str_contains($message, 'Username or Password is not correct')
                || str_contains($message, 'Your password has expired')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Login failed. Your user account has not been activated.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'Login failed. Your account has been locked due to the incorrect login attempts.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function Parse(): void
    {
        $response = $this->http->JsonLog();
        // Name
        $this->SetProperty('Name', beautifulName($response->name ?? null));
        // Number
        $number = $response->userDetails->programs->corporateInfo[0]->membershipNumber ?? null;
        $this->SetProperty('Number', $number);

        $data = [
            'object' => [
                'companyCode'         => $response->userDetails->programs->corporateInfo[0]->companyCode ?? 'VA',
                'isBonusRequired'     => 'Y',
                'membershipNumber'    => $number,
                'programCode'         => $response->userDetails->programs->corporateInfo[0]->programCode ?? 'SME',
                'tierOptionsRequired' => true,
            ],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://business.velocityfrequentflyer.com/lmpapi/api/member-service/v1/member/account-summary', json_encode($data), [
            'content-type' => 'application/json',
            'Accept'       => 'application/json, text/plain, */*',
        ]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog()->object ?? null;
        $tierOptions = null;

        if (!empty($response)) {
            $tierOptions = $this->makeAssoc($response->tierOptions ?? [], 'type');
        }
        $mainBalance = null;
        $flightSpendSubacc = [
            'Code'        => 'FlightSpend',
            'DisplayName' => 'Flight Spend',
            'Balance'     => null,
        ];
        $this->tierToDate = strtotime($response->tierToDate ?? '');

        foreach ($response->pointDetails ?? [] as $balance) {
            if (!is_numeric($balance->points ?? null) || !isset($balance->pointTypeGroup)) {
                continue;
            }

            switch ($balance->pointTypeGroup) {
                case 'Velocity Points':
                    // Main balance - Velocity Points
                    $mainBalance += $balance->points;

                    break;

                case 'Flight Spend':
                    // Flight Spend
                    $flightSpendSubacc['Balance'] += $balance->points;

                    break;

                case 'Benefits':
                    $this->parseBenefit($balance);

                    break;

                default:
                    if ($balance->points == 0) {
                        break;
                    }
                    $this->sendNotification('found new pointTypeGroup // BS');

                    break;
            }
        }
        $this->SetBalance($mainBalance);
        // Company Name
        $this->SetProperty('CompanyName', $response->companyName ?? null);

        foreach ($response->expiryDetails ?? [] as $exp) {
            if (!isset($exp->pointType) || !is_numeric($exp->points ?? null) || $exp->points == 0 || !strtotime($exp->expiryDate ?? null)) {
                continue;
            }

            switch ($exp->pointType) {
                case 'BUSPNT':
                case 'BNS':
                    // Expiration date for main balance
                    if (!isset($this->Properties["AccountExpirationDate"])) {
                        $this->SetExpirationDate(strtotime($exp->expiryDate));
                        $this->SetProperty('ExpiringBalance', $exp->points);
                    }

                    break;

                case 'FLTSPND':
                    // Expiration date for flight spend
                    if (!isset($flightSpendSubacc['ExpirationDate'])) {
                        $flightSpendSubacc['ExpirationDate'] = strtotime($exp->expiryDate);
                    }

                    break;

                default:
                    $this->sendNotification('found new pointType in expirations // BS');

                    break;
            }
        }
        // Tier
        $this->SetProperty('Status', $response->tierName ?? null);
        $spent = $tierOptions['upgrade']->options[0]->optionDetails[0]->diff ?? null;

        if (isset($spent)) {
            // You're only $X,XXX from
            $this->SetProperty('SpentToNextStatus', '$' . $spent);
        }
        // from reaching Tier X
        $this->SetProperty('NextStatus', $tierOptions['upgrade']->tierName ?? null);
        $this->AddSubAccount($flightSpendSubacc);
    }

    private function makeAssoc(array $data, string $keyColumn): array
    {
        if (count($data) === 0) {
            return [];
        }

        return array_combine(array_column($data, $keyColumn), $data);
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://business.velocityfrequentflyer.com/lmpapi/api/authservice/social-login/user/me', ['Accept' => 'application/json, text/plain, */*']);
        $this->http->RetryCount = 2;
        $email = $this->http->JsonLog(null, 0)->email ?? null;

        return strtolower($email) === strtolower($this->AccountFields['Login']);
    }

    private function parseBenefit($balance)
    {
        if ($balance->points == 0) {
            return;
        }

        switch ($balance->pointType) {
            case 'PGPNT':
                $this->AddSubAccount([
                    'Code'        => 'PilotGold',
                    'DisplayName' => 'Pilot Gold',
                    'Balance'     => $balance->points,
                ]);

                return;

            case 'CGPNT':
                if ($this->tierToDate) {
                    $this->AddSubAccount([
                        'Code'           => 'CorporateGold',
                        'DisplayName'    => 'Corporate Gold',
                        'Balance'        => $balance->points,
                        'ExpirationDate' => $this->tierToDate,
                    ]);
                }

                return;

            default:
                $this->sendNotification('new benefit // BS');
        }
    }
}
