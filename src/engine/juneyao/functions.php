<?php

class TAccountCheckerJuneyao extends TAccountChecker
{
    private $headers = [
        "Accept"          => "*/*",
        "Accept-Encoding" => "gzip, deflate, br",
        "Content-Type"    => "application/json",
        "Origin"          => "https://global.juneyaoair.com",
        "Referer"         => "https://global.juneyaoair.com/",
    ];

    private $dataToken = [
        "currCd" => "USD",
        "lang"   => "en",
        "token"  => '',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->State['token'])) {
            return false;
        }
        $this->dataToken['token'] = $this->State['token'];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://globalapi.juneyaoair.com//api/account/memberDetail", json_encode($this->dataToken), $this->headers);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://global.juneyaoair.com/");

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

        $data = [
            'userName' => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
            "currCd"   => "USD",
            "lang"     => "en",
            "blackBox" => "tWPS91680847360Jd5Uwh9NzF3",
        ];

        $this->http->PostURL("https://globalapi.juneyaoair.com//api/account/login", json_encode($data), $this->headers);

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $token = $response->token ?? null;
        $errorMsg = $response->errorMsg ?? null;

        if (empty($token)) {
            if (!empty($errorMsg)) {
                $this->logger->error("Message: {$errorMsg}");

                if (
                    stripos($errorMsg, 'Username does not exist') !== false
                    || stripos($errorMsg, 'Wrong username or password') !== false
                    || stripos($errorMsg, 'Password cannot be null') !== false
                    || stripos($errorMsg, 'Login fails or the login certificate is invalid') !== false
                ) {
                    throw new CheckException($errorMsg, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $errorMsg;

                return false;
            }

            return $this->checkErrors();
        }

        $this->dataToken['token'] = $token;
        $this->http->PostURL("https://globalapi.juneyaoair.com//api/account/memberDetail", json_encode($this->dataToken), $this->headers);

        if ($this->loginSuccessful()) {
            $this->State['token'] = $token;

            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog();
        $status = [
            "0" => "Unknow Member",
            "1" => "Lucky Member",
            "2" => "Silver Member",
            "3" => "Specialsilver Member",
            "4" => "Golden Member",
            "5" => "Specialgolden Member",
            "6" => "Platinum Member",
            "7" => "Specialvipplatinum Member",
            "8" => "Specialplatinum Member",
            "9" => "Visaplatinum Member",
        ];
        // Status
        $this->SetProperty('Status', $status[$response->memberLevelCode]);
        // Name
        $firstName = $response->eFirstName ?? '';
        $lastName = $response->eLastName ?? '';
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));
        // Membership Number
        $this->SetProperty('Number', $response->cardNo);
        // Member Since
        $this->SetProperty('MemberSince', date('Y.n.d', mb_substr($response->registDate, 0, 10)));

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://globalapi.juneyaoair.com//api/account/crmPoints', json_encode($this->dataToken), $this->headers);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        // Balance - Available Points
        $this->SetBalance($response->availablePoint);
        // 2000 points are needed to upgrade to Silver Member
        $this->SetProperty('PointsNextLevel', $response->levelUP_mile);

        // freezePoint 0
        if (isset($response->freezePoint) && $response->freezePoint !== 0) {
            $this->sendNotification("refs #18324: The freezePoint value has changed: {$response->freezePoint} //KS");
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();

        if (isset($response->isSuccess) && $response->isSuccess === true) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('
            //h1[contains(text(), "502 Bad Gateway")]
        ')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
