<?php

class TAccountCheckerMalindoair extends TAccountChecker
{
    // Not working isLoggedIn()
    private $headers = [];

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.malindomiles.com/inspirenetz/app/');

        if (!$this->http->ParseForm('frmLoginForm')) {
            return $this->checkErrors();
        }
        //$this->http->SetInputValue('username', $this->AccountFields['Login']);
        //$this->http->SetInputValue('password', $this->AccountFields['Pass']);

        $this->http->RetryCount = 0;
        $authenticateUrl = 'https://www.malindomiles.com/inspirenetz-api/api/0.9/json/user/authenticate?cacheVal=' . time() . date('B');
        $this->http->GetURL($authenticateUrl, [
            'Accept'           => '*/*',
            'ngbrowserversion' => 'true',
        ]);

        $authenticateHeader = ArrayVal($this->http->Response['headers'], 'www-authenticate', null);

        if (empty($authenticateHeader)) {
            if ($this->http->FindSingleNode('//title[contains(text(), "Apache Tomcat/8.5.11 - Error report")]')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }
        $this->logger->debug($authenticateHeader);
        $realm = $this->http->FindPreg('/realm="(.+?)"/', false, $authenticateHeader);
        $nonce = $this->http->FindPreg('/nonce="(.+?)=*"/', false, $authenticateHeader);
        $qop = $this->http->FindPreg('/qop="(.+?)",/', false, $authenticateHeader);
        $nc = '00000002';

        $cnonce = $this->generateCnonce();
        $response = $this->formulateResponse($authenticateUrl, $realm, $nonce, $cnonce, $qop, $nc);

        $this->headers = [
            'Accept'           => '*/*',
            'ngbrowserversion' => 'true',
            'authorization'    => 'Digest username="'
                . $this->AccountFields['Login'] . '", realm="' . $realm
                . '", nonce="' . $nonce . '", uri="' . $authenticateUrl
                . '", response="' . $response . '", qop=' . $qop . ', nc=' . $nc . ', cnonce="' . $cnonce . '"',
        ];
        $this->http->GetURL($authenticateUrl, $this->headers);

        $this->http->RetryCount = 2;

        return true;
    }

    // https://www.malindomiles.com/inspirenetz/app/js/digestAuthRequest.js
    public function formulateResponse($authenticateUrl, $realm, $nonce, $cnonce, $qop, $nc)
    {
        $HA1 = md5("{$this->AccountFields['Login']}:{$realm}:{$this->AccountFields['Pass']}");
        $this->logger->debug($HA1);
        $HA2 = md5("GET:{$authenticateUrl}");

        $response = md5("$HA1:$nonce:$nc:$cnonce:$qop:$HA2");

        return $response;
    }

    public function generateCnonce()
    {
        $characters = 'abcdef0123456789';
        $token = '';

        for ($i = 0; $i < 16; $i++) {
            $randNum = round((float) rand() / (float) getrandmax() * strlen($characters));
            $token .= substr($characters, $randNum, 1);
        }

        return $token;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        if ($this->http->Response['code'] == 403) {
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Incorrect response')]")) {
                throw new CheckException('Malindo Air (Malindo Miles) website is asking you to change your password, until you do so we would not be able to retrieve your account information.', ACCOUNT_PROVIDER_ERROR);
            }
            // Please enter the correct username/password
            if ($this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 403 - Username {$this->AccountFields['Login']} not found')]")) {
                throw new CheckException('Please enter the correct username/password', ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 403 - User credentials have expired')]")) {
                throw new CheckException('The password you have entered is incorrect. Kindly try again with the correct password.', ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        $response = $this->http->JsonLog();

        if ($response->data->usrUserNo ?? false) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.malindomiles.com/inspirenetz-api/api/0.9/json/user/memberships/9/0/0?cacheVal=' . date('UB'), $this->headers);
        $response = $this->http->JsonLog();

        if (!isset($response->data[0]->crbRewardBalance)) {
//            $this->logger->debug(">{$this->http->Response['body']}<");
            if ($this->http->Response['body'] == '{"data":[],"status":"success"}') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }

        if (isset($response->data) && count($response->data) > 1) {
            $this->sendNotification('refs #15637, malindoair - response->data > 1');
        }

        $data = $response->data[0];
        // Balance - 0 MILES
        $this->SetBalance(beautifulName($data->crbRewardBalance));

        $this->http->GetURL('https://www.malindomiles.com/inspirenetz-api/api/0.9/json/customer/customerprofile/9?cacheVal=' . time() . date('B'), $this->headers);
        $response = $this->http->JsonLog();

        if (!isset($response->data->cusFName, $response->data->cusLoyaltyId)) {
            return;
        }

        // Name
        $this->SetProperty('Name', beautifulName("{$response->data->cusFName} {$response->data->cusLName}"));
        // Account Status
        $this->SetProperty('Status', $response->data->tieName);
        // AccountNumber
        $this->SetProperty('AccountNumber', $response->data->cusLoyaltyId);

        // refs #15637, Expiration date
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking Reference",
                "Type"     => "string",
                "Size"     => 6,
                "Required" => true,
            ],
            "LastName" => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 80,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://dx.checkin.malindoair.com/dx/ODCI/#/check-in/start?locale=en-US";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $it = [];
        //$this->eventTarget('ctl00$lnkManageBooking');
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://airline.api.platform.sabre.com/v917/dcci/passenger/details?jipcc=ODCI', json_encode([
            'reservationCriteria' => [
                'recordLocator' => $arFields['ConfNo'],
                'lastName'      => $arFields['LastName'],
            ],
            'outputFormat' => 'BPXML',
        ]), [
            'Accept'         => 'application/json',
            'Content-Type'   => 'application/json',
            'Application-ID' => $this->http->FindPreg("/sabre\['appId'\] = '(.+?)';/"),
            'Authorization'  => 'Bearer ' . $this->http->FindPreg("/sabre\['access_token'\] = '(.+?)';/"),
        ]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg('/,"message":"Reservation not found."/')) {
            return 'We were unable to find your reservation. Please contact our call center at +603 - 7841 5388 or email us at customer_care@malindoair.com';
        }
        // Flight is not yet initialized.
        if ($this->http->FindPreg('/,"message":"FLIGHT_NOT_INITIALIZED"/')) {
            return 'Flight is not yet initialized. Sorry, your flight is currently not available for web check-in. Please note that web check-in will be available from 48 hours to 4 hours prior to your flight departure time. Thank you.';
        }

        $this->sendNotification("malindoair - new reservation #", 'all', true,
            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>LastName: {$arFields['LastName']}");

        /*if (!$this->http->ParseForm('form1')) {
            $this->sendNotification("payless - failed to retrieve itinerary by conf #", 'all', true, "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}<br/>Email: {$arFields["Email"]}");
            return null;
        }

        $this->http->SetInputValue('ctl00$cph$txtReservationID', $arFields["ConfNo"]);
        $this->http->PostForm();

        $it = $this->ParseItinerary();*/
        return null;
    }

    public function ParseItinerary()
    {
        return [];
    }
}
