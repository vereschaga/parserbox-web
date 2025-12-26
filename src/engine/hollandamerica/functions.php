<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerHollandamerica extends TAccountChecker
{
    use ProxyList;
    private $converter;
    private $version = '2.0';

    private $headers = [
        'Accept'       => '*/*',
        'Content-Type' => 'application/json',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->SetProxy($this->proxyReCaptcha(), false);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.hollandamerica.com/myAccount/Login.action?loginMessage=login&username=&emailRedirectURL=/myAccount/CruiseHistory.action&windowUrl=http%253A//www.hollandamerica.com/main/Main.action%253Fmessage%253Dlogin%2526username%253D%2526emailRedirectURL%253D%2525252FmyAccount%2525252FCruiseHistory.action';
        //	$arg['SuccessURL'] = 'https://www.hollandamerica.com/myAccount/CruiseHistory.action';
        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->GetURL("https://www.hollandamerica.com/en_US/log-in/sso-log-in.html");

//        if (!$this->http->ParseForm('login-form'))
        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

        if ($version = $this->http->FindPreg("#/api/(v\d+)/mariner/login#")) {
            $this->version = $version;
        }

        $this->http->setDefaultHeader('agencyid', 'Agency id');
        $this->http->setDefaultHeader('brand', 'hal');
        $this->http->setDefaultHeader('country', 'EN');
        $this->http->setDefaultHeader('currencycode', 'USD');
        $this->http->setDefaultHeader('locale', 'en_US');
        $this->http->setDefaultHeader('loyaltynumber', '');

        $login = 'username';

        // stupid user bug fix, AccountID: 5737011
        $this->AccountFields['Login'] = str_replace('@gmail..com', '@gmail.com', $this->AccountFields['Login']);

        if (is_numeric($this->AccountFields['Login'])) {
            $login = 'marinerNumber';
        }

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.hollandamerica.com/api/sso/v1/login', json_encode([
            $login     => $this->AccountFields['Login'],
            'password' => substr($this->AccountFields['Pass'], 0, 15),
        ]), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        sleep(1);
        $response = $this->http->JsonLog();
        // Access is allowed
        if (isset($response->access_token)) {
            $this->http->setDefaultHeader("sessiontoken", "Bearer {$response->access_token}");

            return true;
        }
        // catch errors
        $message = $response->errors[0]->message ?? $response->message ?? null;

        if ($message) {
            $this->logger->error($message);
            // Username or password is incorrect. Please try again
            if (
                $message == "Username or password is incorrect. Please try again"
                || $message == "Invalid username, marinerId or password given"
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, "username must match ")
                || strstr($message, "username Input does not conform to the regex for the email attribute. This is the regex")
                || strstr($message, "marinerNumber Input does not conform to the regex for the marinerId attribute. This is the regex")
            ) {
                throw new CheckException("Enter a valid Mariner ID or Email Address", ACCOUNT_INVALID_PASSWORD);
            }

            // Authentication information is incorrect. Please try again
            if ($message == "Authentication information is incorrect. Please try again") {
                throw new CheckException("Your username and/or password does not match our records.", ACCOUNT_INVALID_PASSWORD);
            }

            // There was an error trying carry out the operation. Please try again later.
            if ($message == "There was an error trying carry out the operation. Please try again later.") {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // Backend Bad Gateway
            if (
                $message == "Backend Bad Gateway"
                || $message == "Internal server error"
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;
        }// if ($message)

        // AccountID: 4721491
        if ($this->http->Response['body'] == '{"errors":[],"data":{"mariner":{"title":null,"firstName":null,"middleName":null,"lastName":null,"gender":null,"marinerId":null,"siebelId":null,"phoneNumber":null,"cellularPhone":null,"address":{"address1":null,"address2":null,"city":null,"country":null,"state":null,"postalCode":null},"dob":null,"emailAddress":"' . $this->AccountFields['Login'] . '","favorites":[],"campaignIds":[]}}}') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $email = $this->AccountFields['Login'];

        if (is_numeric($this->AccountFields['Login'])) {
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.hollandamerica.com/api/sso/v1/validator', "{}", $this->headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
            $email = $response->profile->webUserInfo->username ?? null;
        }

        if (!isset($email)) {
            return false;
        }

        $this->http->PostURL('https://www.hollandamerica.com/api/v2/mariner/me', json_encode([
            'email' => $email,
        ]), $this->headers);
        $response = $this->http->JsonLog();

        if (!isset($response->data->mariner)) {
            return;
        }
        $mariner = $response->data->mariner;
        // Name
        $this->SetProperty('Name', beautifulName($mariner->firstName . ' ' . $mariner->middleName . ' ' . $mariner->lastName));
        // Status - Mariner Star Level
        if (!empty($mariner->starLevel)) {
            $this->SetProperty('Status', $mariner->starLevel);
        }
        // MarinerNumber - Mariner Number
        if (!isset($this->Properties['MarinerNumber'])) {
            $this->SetProperty('MarinerNumber', $mariner->marinerId);
        }

        // prevent traces
        if (!isset($mariner->totalSailedDays, $mariner->totalTourDays)) {
            $this->setNA();

            return;
        }// if (!isset($mariner->totalSailedDays, $mariner->totalTourDays))

        // Balance - Total Cruise Day credits
        if (!$this->SetBalance($mariner->totalSailedDays)) {
            $this->setNA();
        }

        if ($mariner->totalSailedDays > 0 && $mariner->totalSailedDays != ($mariner->totalTourDays + $mariner->totalBonusCruiseDays)) {
            $this->sendNotification('hollandamerica - balance mismatch');
        }
        // CruiseTourDays - Cruise and Tour Days
        $this->SetProperty('CruiseTourDays', $this->http->FindPreg('/,"totalTourDays":(?:null|""),/') ? 0 : $mariner->totalTourDays);
        // BonusCruiseDays - Bonus Cruise Days:
        $this->SetProperty('BonusCruiseDays', $this->http->FindPreg('/,"totalBonusCruiseDays":(?:null|""),/') ? 0 : $mariner->totalBonusCruiseDays);
        // NeededToNextLevel - Credits to Next Star Level
        $this->SetProperty('NeededToNextLevel', $mariner->creditNextStarLevel);
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->PostURL('https://book2.hollandamerica.com/api/dspm/login/v1.0.0/authenticate?companyCode=HAL', json_encode([
            'key'      => $this->AccountFields['Login'],
            'secret'   => $this->AccountFields['Pass'],
            'role'     => 'GIFTER',
            'clientId' => 'secondaryFlow',
        ]), [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'adrum'        => 'isAjax:true',
            'client-id'    => 'secondaryFlow',
        ]);
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg('/"bookingList":\[\],/')) {
            return $this->noItinerariesArr();
        }

        if (!isset($response->details->bookingList)) {
            return [];
        }
        $this->converter = new CruiseSegmentsConverter();

        foreach ($response->details->bookingList as $item) {
            $this->http->GetURL("https://www.hollandamerica.com/secondary/api/guest/v1.0.0/booking/companyCode/HAL/bookingNumber/{$item->bookingNumber}", [
                'Accept'        => 'application/json, text/plain, */*',
            ]);
            $result[] = $this->parseItinerary();
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        //$selectValue = $this->selectConfirmationOptions();
        return [
            "ConfNo" => [
                "Caption"  => "Booking Number",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Caption"  => "First name",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Caption"  => "Last name",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
            /*"validationOptions" => [
                "Caption"  => "Select An Option",
                "Type"     => "string",
                "Size"     => 40,
                "Options"  => $selectValue['validationOptions'],
                "Required" => true,
            ],
            "shipNameOptions" => [
                "Caption"  => "Ship Name",
                "Type"     => "string",
                "Size"     => 40,
                "Options"  => $selectValue['shipNameOptions'],
                "Required" => true,
            ],*/
        ];
    }

    /*private function selectConfirmationOptions()
    {
        $result = [
            'validationOptions' => [],
            'shipNameOptions' => [],
        ];

        $cache = Cache::getInstance()->get('hollandamerica_confirmation_options');
        if ($cache !== false) {
            $result = $cache;
        } else {
            $browser = new HttpBrowser("none", new CurlDriver());
            $browser->GetURL('https://www.hollandamerica.com/en_US/log-in/sso-log-in.html?login=sf');
            $response = $browser->JsonLog($browser->FindPreg('/SR\.components\.data\.push\((\{"type":"ssoLogin","id":.+?)\);/'));
            if (isset($response->attributes->validationOptions->options)) {
                $i = 0;
                foreach ($response->attributes->validationOptions->options as $option) {
                    $i++;
                    $result['validationOptions']["0$i"] = $option->{"0$i"}->label;
                    break;
                }
                foreach ($response->attributes->shipNameOptions->options as $options) {
                    if (isset($options->Name->code) && $options->Name->code == 'Name') {
                        foreach ($options->Name->states as $option) {
                            $result['shipNameOptions'][$option->value] = $option->label;
                        }
                    }
                }
                if (!empty($result['validationOptions'])) {
                    Cache::getInstance()->set('hollandamerica_confirmation_options', $result, 3600 * 12 * 24);
                }
            }
        }
        return $result;
    }*/

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://book2.hollandamerica.com/secondaryFlow/login';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $this->sendNotification('check retrieve // MI');

        if (!$this->http->FindPreg('/currentFile\.ifExistsFallbackTest/')) {
            return null;
        }
        $this->logger->debug(var_export($arFields, true));

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://book2.hollandamerica.com/api/dspm/login/v1.0.0/authenticate?companyCode=HAL', json_encode([
            'bookingNumber' => $arFields['ConfNo'],
            'lastName'      => $arFields['LastName'],
            'role'          => 'GIFTER',
            'clientId'      => 'secondaryFlow',
        ]), [
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept'          => 'application/json, text/plain, */*',
            'Content-Type'    => 'application/json',
            'adrum'           => 'isAjax:true',
            'client-id'       => 'secondaryFlow',
        ]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if ($error = $this->http->FindPreg('/"message":"Failed to authenticate\."/')) {
            return 'The provided booking number or last name do not match our records.';
        }

        if (!isset($response->token, $response->principal, $response->principal)) {
            return null;
        }

        $this->converter = new CruiseSegmentsConverter();
        $this->http->GetURL("https://book2.hollandamerica.com/secondary/api/guest/v1.0.0/booking/companyCode/HAL/bookingNumber/{$response->principal}", [
            'Accept'        => 'application/json, text/plain, */*',
            'authorization' => 'Bearer ' . $response->token,
        ]);
        $it = [$this->parseItinerary()];

        return null;
    }

    private function setNA()
    {
        $this->logger->notice(__METHOD__);
        // AccountID: 4427159
        if (!empty($this->Properties['Name']) && !isset($this->Properties['Status'])) {
            if ($this->http->FindPreg('/,"totalSailedDays":(?:null|""),/')) {
                $this->SetBalance(0);
            }
        }
    }

    private function parseItinerary()
    {
        $reservation = $this->http->JsonLog();
        $result['Kind'] = 'T';
        $result['TripCategory'] = TRIP_CATEGORY_CRUISE;

        $confNo = $reservation->bookingNumber ?? null;

        if (empty($confNo)) {
            return null;
        }

        $result['RecordLocator'] = $confNo;
        $this->logger->info(sprintf('Parse Itinerary #%s', $result['RecordLocator']), ['Header' => 3]);

        if (isset($reservation->guests)) {
            foreach ($reservation->guests as $guest) {
                $result['Passengers'][] = beautifulName($guest->firstName . ' ' . $guest->lastName);
            }
        }

        if (isset($reservation->itineraryName)) {
            $result['CruiseName'] = $reservation->itineraryName;
        }

        if (isset($reservation->shipCode)) {
            $result['ShipCode'] = $reservation->shipCode;
        }

        if (isset($reservation->shipName)) {
            $result['ShipName'] = $reservation->shipName;
        }

        if (isset($reservation->stateroom)) {
            $result['RoomNumber'] = $reservation->stateroom;
        }

        if (isset($reservation->itinerary->itineraryDayList)) {
            $cruise = [];

            foreach ($reservation->itinerary->itineraryDayList as $i) {
                $segment = [];
                $arrDate = $depDate = null;

                if (empty($i->portName) || (isset($i->atSea) && $i->atSea == true)
                    || $this->http->FindPreg('/^(?:At Sea|Crossing the Equator)$/i', false, $i->portName)) {
                    $this->logger->debug('Skip item: ' . (!empty($i->portName) ? $i->portName : 'not portName'));

                    continue;
                }

                foreach ($i->itineraryActivityTimeList as $timeList) {
                    if (isset($timeList->itineraryActivityTypeList)) {
                        foreach ($timeList->itineraryActivityTypeList as $typeList) {
                            if (isset($typeList->itineraryActivityList)) {
                                foreach ($typeList->itineraryActivityList as $activityList) {
                                    if (isset($activityList->name) && $activityList->name == 'Ship Arrives') {
                                        $arrDate = $activityList->displayTime;
                                    }

                                    if (isset($activityList->name) && $activityList->name == 'Ship Departs') {
                                        $depDate = $activityList->displayTime;
                                    }
                                }
                            }
                        }
                    }
                }

                if (!isset($arrDate) && !isset($depDate)) {
                    continue;
                }

                $segment['Port'] = beautifulName($i->portName);

                if (isset($arrDate)) {
                    $segment['ArrDate'] = strtotime($arrDate, strtotime(str_replace('T', '', $i->arrivalDateTime), false));
                }

                if (isset($depDate)) {
                    $segment['DepDate'] = strtotime($depDate, strtotime(str_replace('T', '', $i->departureDateTime), false));
                }
                $cruise[] = $segment;
            }
            $result['TripSegments'] = $this->converter->Convert($cruise);
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($result, true), ['pre' => true]);

        if (
            isset($result['CruiseName'], $result['ShipCode'], $result['ShipName'])
            && $result['CruiseName'] == 'NON PUBLISHED VOYAGES'
            && $result['ShipCode'] == 'ZZ'
            && $result['ShipName'] == 'Future Request'
            && empty($result['TripSegments'])
        ) {
            $this->logger->notice('skip future trip without useful information about segments');

            return null;
        }

        return $result;
    }
}
