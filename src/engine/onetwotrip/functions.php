<?php

class TAccountCheckerOnetwotrip extends TAccountChecker
{
    private $domain = "www.onetwotrip.com";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["SuccessURL"] = "https://secure.onetwotrip.com/p/";
        $arg["CookieURL"] = "https://www.onetwotrip.com/_api/visitormanager/get/?callback=jQuery1820860955784132428_1513329339082&referrer=empty&_=" . date('UB');

        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->LogHeaders = true;
        $this->http->GetURL("https://www.onetwotrip.com/_api/visitormanager/get/?callback=jQuery1820860955784132428_1513329339082&referrer=empty&_=" . date('UB'));
        $this->http->GetURL("https://www.onetwotrip.com/en-us/");

        if (!$this->http->FindSingleNode("//title[contains(text(), 'Buy cheap flights online on')]")) {
            return $this->checkErrors();
        }
        $this->http->Form = [];
        $this->http->FormURL = 'https://www.onetwotrip.com/_api/visitormanager/auth';
        $this->http->SetInputValue("email", $this->AccountFields["Login"]);
        $this->http->SetInputValue("pwd", $this->AccountFields["Pass"]);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // No Connection. Please check your Internet connection and try again.
        if ($this->http->Response['code'] == 524) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "application/x-www-form-urlencoded",
            "Origin"       => "https://www.onetwotrip.com",
            "Pragma"       => "no-cache",
            "Referer"      => "https://www.onetwotrip.com/en-us/",
        ];

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog(null, 3, true);

        if (isset($response['result']['redirect'])) {
            $this->http->GetURL($response['result']['redirect']);

            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->http->Form = [];
            $this->http->FormURL = 'https://captcha.onetwotrip.com/_limiter/limits/check-captcha';
            $this->http->SetInputValue("captchaResponse", $captcha);
            $this->http->SetInputValue("keys", $response['result']['keys']);

            if (!$this->http->PostForm($headers)) {
                return $this->checkErrors();
            }

            $this->http->JsonLog(null, 3, true);

            $this->http->GetURL("https://www.onetwotrip.com/en-us/");

            if (!$this->http->FindSingleNode("//title[contains(text(), 'Buy cheap flights online on')]")) {
                return $this->checkErrors();
            }

            $this->http->Form = [];
            $this->http->FormURL = 'https://www.onetwotrip.com/_api/visitormanager/auth';
            $this->http->SetInputValue("email", $this->AccountFields["Login"]);
            $this->http->SetInputValue("pwd", $this->AccountFields["Pass"]);

            if (!$this->http->PostForm($headers)) {
                return $this->checkErrors();
            }

            $response = $this->http->JsonLog(null, 3, true);
        }

        if (isset($response['error'])) {
            switch ($response['error']) {
                case 'TOO_MANY_TRIES':
                    throw new CheckException('Too many login attempts', ACCOUNT_PROVIDER_ERROR);

                    break;

                case 'EMAIL_NOT_VALID':
                    throw new CheckException('Email not valid', ACCOUNT_INVALID_PASSWORD);

                    break;

                case 'WRONG_PWD_OR_EMAIL':
                    throw new CheckException('Wrong email or password', ACCOUNT_INVALID_PASSWORD);

                case 'REQUEST_LIMIT_REACHED':
                    throw new CheckException('Incorrect Password or Email', ACCOUNT_INVALID_PASSWORD);

                    break;

                default:
                    return false;
            }
        }

        // AccountID: 4820778 -> https://sberbank.onetwotrip.com/ru-kz/
        if (isset($response['redirect'])) {
            $this->http->GetURL("https://sberbank.onetwotrip.com/_api/visitormanager/get/?referrer=empty&_=" . date('UB'));
            $this->http->GetURL($response['redirect']);
            $this->http->Form = [];
            $this->http->FormURL = 'https://sberbank.onetwotrip.com/_api/visitormanager/auth/';
            $this->http->SetInputValue("email", $this->AccountFields["Login"]);
            $this->http->SetInputValue("pwd", $this->AccountFields["Pass"]);

            if (!$this->http->PostForm($headers)) {
                return $this->checkErrors();
            }

            $response = $this->http->JsonLog(null, 3, true);
            $this->domain = 'sberbank.onetwotrip.com';
        }

        if (isset($response['auth']) && $response['auth'] == true) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://{$this->domain}/_api/buyermanager/getUserInfo/?allPaxLocales=true&showInActive=true&showBonusesNewFormat=true&_=" . date('UB'));
        $response = $this->http->JsonLog(null, 3, true);
        // set name
        if (isset($response["firstName"], $response["lastName"])) {
            $this->SetProperty("Name", beautifulName($response["firstName"] . " " . $response["lastName"]));
        }
        // set balance
        $allBonuses = ArrayVal($response, 'allBonuses');

        if (!$this->SetBalance(ArrayVal($allBonuses, 'total'))) {
            return;
        }
        // set visits
        $leads = ArrayVal($response, 'leads');
        $this->SetProperty("Visits", strval(ArrayVal($leads, 'visits')));
        // set orders
        $this->SetProperty("Orders", strval(ArrayVal($leads, 'orders')));
        // To be rewarded
        $this->SetProperty("ToBeRewarded", intval(ArrayVal($allBonuses, 'expected')) . " " . ArrayVal($allBonuses, 'currency'));
        // Profile Level
        // $userStatus = ArrayVal($response, 'leads');
        $userStatus = ArrayVal($response, 'userStatus', []);
        $status = ArrayVal($userStatus, 'type');

        switch ($status) {
            case 'classic':
            case 'pro':
                $this->SetProperty("Status", "Basic");

                break;

            case 'premium':
                $this->SetProperty("Status", "Premium");

                break;

            default:
                $this->sendNotification("New status was found: {$status}");

                break;
        }// switch ($status)
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
//        $this->http->GetURL('https://www.onetwotrip.com/_api/buyermanager/getUserInfo/?showInActive=true&showBonusesNewFormat=true&_='.date('UB'));
        $response = $this->http->JsonLog(null, 0, false);

        if (empty($response)) {
            return $result;
        }

        if (!empty($response->hotelOrders)) {
            // $this->sendNotification('onetwotrip - check hotel parsing');
            for ($i = -1, $iCount = count($response->hotelOrders); ++$i < $iCount;) {
                if (!empty($response->hotelOrders[$i]->number)
                        && !empty($response->hotelOrders[$i]->orderEmail)) {
                    // Skip old itineraries
                    if (!$this->ParsePastIts && isset($response->hotelOrders[$i]->arrival_date) && strtotime($response->hotelOrders[$i]->arrival_date) < strtotime('now')) {
                        $this->logger->notice("Skip old itinerary: {$response->hotelOrders[$i]->number}");

                        continue;
                    }

                    $data = $this->parseHotel($response->hotelOrders[$i]);
                    empty($data) ?: $result = array_merge($result, $data);
                }
            }
        }

        if (!empty($response->orderInfo)) {
            // $this->sendNotification('onetwotrip - check train parsing');
            for ($i = -1, $iCount = count($response->orderInfo); ++$i < $iCount;) {
                if (isset($response->orderInfo[$i]->service)
                        && !empty($response->orderInfo[$i]->number)
                        && !empty($response->orderInfo[$i]->orderEmail)) {
                    // Skip old itineraries
                    if (!$this->ParsePastIts && isset($response->orderInfo[$i]->arrival_date) && strtotime($response->orderInfo[$i]->arrival_date) < strtotime('now')) {
                        $this->logger->notice("Skip old itinerary: {$response->orderInfo[$i]->number}");

                        continue;
                    }

                    switch ($response->orderInfo[$i]->service) {
                        case 'railroad':
                            $data = $this->parseTrain($response->orderInfo[$i]);

                            break;

                        case 'avia':
                            $data = $this->parseAir($response->orderInfo[$i]);

                            break;

                        case 'cars':
                            $data = $this->parseCar($response->orderInfo[$i]);

                            break;

                        default:
                            $this->sendNotification("onetwotrip, New reservation type was found {$response->orderInfo[$i]->service} ({$response->orderInfo[$i]->number})");

                            break; }
                    empty($data) ?: $result = array_merge($result, $data);
                }
            }
        }

        return $result;
    }

    protected function parseHotel($it)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $this->http->PostURL("https://{$this->domain}/_api/issuetracker/getRequests/", [
            'number'           => $it->number,
            'email'            => $it->orderEmail,
            'smd'              => true,
            'noUpdateRsvInGds' => false,
        ], [
            'Accept' => 'application/json, text/javascript, */*',
        ]);
        $response = $this->http->JsonLog(null, 1, false);

        if (empty($response->result)) {
            return $result;
        }

        $iCount = (is_array($response->result) || ($response->result instanceof Countable))
            ? count($response->result)
            : 0;

        for ($i = -1; ++$i < $iCount;) {
            $item = $response->result[$i];

            for ($j = -1, $jCount = count($item->reservations); ++$j < $jCount;) {
                $this->logger->info(sprintf('Parse Hotel #%s', $item->number), ['Header' => 3]);
                $hotel = $item->reservations[$j]->hotel;
                $dateIn = strtotime($item->reservations[$j]->startDate . ' ' . $hotel->checkin);
                $dateOut = strtotime($item->reservations[$j]->endDate . ' ' . $hotel->checkout);

                if (false === $dateIn || false === $dateOut) {
                    continue;
                }
                $seg = [
                    'Kind'                => 'R',
                    'ConfirmationNumber'  => $item->number,
                    'HotelName'           => $hotel->name,
                    'Address'             => implode(', ', [$hotel->city, $hotel->address, $hotel->country]),
                    'Phone'               => str_replace('‑', '-', $hotel->phone ?? null),
                    'ReservationDate'     => strtotime($item->dateCreate),
                    'CheckInDate'         => $dateIn,
                    'CheckOutDate'        => $dateOut,
                    'RoomType'            => $item->reservations[$j]->room->name,
                    'RoomTypeDescription' => isset($item->reservations[$j]->room->desc) ? substr(trim($item->reservations[$j]->room->desc), 0, 2000) : null,
                    'Cost'                => $item->reservations[$j]->room->price,
                    'Currency'            => $item->reservations[$j]->room->currency,
                    'Status'              => $item->reservations[$j]->status,
                    //'CancellationPolicy'  => $item->reservations[$j]->hotelDetails->policy,
                ];
                $seg['ReservationDate'] -= $seg['ReservationDate'] % 60;

                if (!empty($item->reservations[$j]->customer->firstName)) {
                    $seg['GuestNames'] = [beautifulName(trim($item->reservations[$j]->customer->firstName . ' ' . $item->reservations[$j]->customer->lastName))];
                }

                $result[] = $seg;
            }
        }

        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    protected function parseCar($it)
    {
        $this->logger->notice(__METHOD__);
        $res = [];
        $this->http->PostURL("https://{$this->domain}/_api/issuetracker/getRequests/", [
            'number'           => $it->number,
            'email'            => $it->orderEmail,
            'smd'              => true,
            'noUpdateRsvInGds' => false,
        ], [
            'Accept' => 'application/json, text/javascript, */*',
        ]);
        $response = $this->http->JsonLog(null, 0, false);

        if (empty($response->result)) {
            return $res;
        }

        for ($i = -1, $iCount = count($response->result); ++$i < $iCount;) {
            $item = $response->result[$i];
            $result = [];

            for ($j = -1, $jCount = count($item->reservations); ++$j < $jCount;) {
                $result['Kind'] = 'L';
                $result['Status'] = $item->reservations[$j]->status;
                // Number
                $result["Number"] = $item->number;
                $this->logger->info(sprintf('Parse Car #%s', $result['Number']), ['Header' => 3]);
                $result['ReservationDate'] = strtotime(substr($item->dateCreate, 0, strpos($item->dateCreate, "T")));
                // TotalCharge
                $result['TotalCharge'] = $item->reservations[$j]->rentRequest->totalAmount ?? null;
                // Currency
                $result['Currency'] = $item->reservations[$j]->rentResult->payment->currency ?? null;
                // PickupDatetime
                $pickUp = $item->reservations[$j]->rentRequest->begin->month . "/" .
                    $item->reservations[$j]->rentRequest->begin->day . "/" .
                    $item->reservations[$j]->rentRequest->begin->year . " " .
                    $item->reservations[$j]->rentRequest->begin->hour . ":";

                if (strlen($item->reservations[$j]->rentRequest->begin->minutes) == 1) {
                    $pickUp .= $item->reservations[$j]->rentRequest->begin->minutes . '0';
                } else {
                    $pickUp .= $item->reservations[$j]->rentRequest->begin->minutes;
                }
                $this->logger->debug("PickupDatetime: {$pickUp}");
                $result['PickupDatetime'] = strtotime($pickUp);
                // DropoffDatetime
                $dropOff = $item->reservations[$j]->rentRequest->end->month . "/" .
                    $item->reservations[$j]->rentRequest->end->day . "/" .
                    $item->reservations[$j]->rentRequest->end->year . " " .
                    $item->reservations[$j]->rentRequest->end->hour . ":";

                if (strlen($item->reservations[$j]->rentRequest->end->minutes) == 1) {
                    $dropOff .= $item->reservations[$j]->rentRequest->end->minutes . '0';
                } else {
                    $dropOff .= $item->reservations[$j]->rentRequest->end->minutes;
                }
                $this->logger->debug("DropoffDatetime: {$dropOff}");
                $result['DropoffDatetime'] = strtotime($dropOff);
                // CarModel
                $result['CarModel'] = $item->reservations[$j]->rentResult->carSegment->carModels[0]->brand ?? '';
                $result['CarModel'] .= isset($item->reservations[$j]->rentResult->carSegment->carModels[0]->model) ? ' ' . $item->reservations[$j]->rentResult->carSegment->carModels[0]->model : null;
                // RenterName
                $result['RenterName'] = beautifulName($item->reservations[$j]->rentRequest->mainDriver->firstName . " " . $item->reservations[$j]->rentRequest->mainDriver->lastName);
                // RentalCompany
                $result['RentalCompany'] = $item->reservations[$j]->rentRequest->providerCode ?? null;

                $this->http->GetURL("https://{$this->domain}/_api/cars/getRentalInfo?rentalId={$item->reservations[$j]->id}", ["X-Requested-With" => "XMLHttpRequest", "Accept" => "*/*"]);
                $sameLocation = true;
                $responseLocations = $this->http->JsonLog(null, 0, false);

                if (isset($responseLocations->data->locations)) {
                    foreach ($responseLocations->data->locations as $location) {
                        if ($location->type == 'pickUp') {
                            // PickupLocation
                            $result['PickupLocation'] = $location->line1;
                            $result['PickupPhone'] = $location->phone;

                            continue;
                        }

                        if ($location->type == 'dropOff') {
                            // PickupLocation
                            $result['DropoffLocation'] = $location->line1;
                            $result['DropoffPhone'] = $location->phone ?? null;
                            $sameLocation = false;

                            continue;
                        }
                        $this->sendNotification("Car - New location was found");
                    }// foreach ($responseLocations->data->locations as $location)

                    if ($sameLocation) {
                        $result['DropoffLocation'] = $result['PickupLocation'];
                        $result['DropoffPhone'] = $result['PickupPhone'];
                    }
                }// if (isset($responseLocations->data->locations))

                $res[] = $result;
            }
        }

        $this->logger->debug('Parsed Car:');
        $this->logger->debug(var_export($res, true), ['pre' => true]);

        return $res;
    }

    protected function parseTrain($it)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $this->http->PostURL("https://{$this->domain}/_api/issuetracker/getRequests/", [
            'number'           => $it->number,
            'email'            => $it->orderEmail,
            'smd'              => true,
            'noUpdateRsvInGds' => false,
        ], [
            'Accept' => 'application/json, text/javascript, */*',
        ]);
        $response = $this->http->JsonLog(null, 0, false);

        if (empty($response->result)) {
            return $result;
        }

        for ($i = -1, $iCount = count($response->result); ++$i < $iCount;) {
            $item = $response->result[$i];
            $train = [
                'Kind'            => 'T',
                'TripCategory'    => TRIP_CATEGORY_TRAIN,
                'RecordLocator'   => $item->number,
                'ReservationDate' => strtotime($item->dateCreate),
            ];
            $this->logger->info(sprintf('Parse Train #%s', $train['RecordLocator']), ['Header' => 3]);
            $seg = [];

            for ($j = -1, $jCount = count($item->reservations); ++$j < $jCount;) {
                $train['TotalCharge'] = $item->reservations[$j]->amount;
                $train['Currency'] = $item->reservations[$j]->currency;
                $train['Status'] = $item->reservations[$j]->status;

                $direct = [
                    'FlightNumber' => $item->reservations[$j]->gdsOrderId,
                    'DepCode'      => TRIP_CODE_UNKNOWN,
                    'DepName'      => $item->reservations[$j]->directions[0]->fromName,
                    'ArrCode'      => TRIP_CODE_UNKNOWN,
                    'ArrName'      => $item->reservations[$j]->directions[0]->toName,
                ];

                $depDate = strtotime($item->reservations[$j]->directions[0]->trainInfo->departure->time);
                $arrDate = strtotime($item->reservations[$j]->directions[0]->trainInfo->arrival->time);
                false === $depDate ?: $direct['DepDate'] = $depDate;
                false === $arrDate ?: $direct['ArrDate'] = $arrDate;

                empty($item->reservations[$j]->passengers) ?:
                    $train['Passengers'] = array_map(function ($item) {
                        return beautifulName(trim($item->name->first . ' ' . $item->name->last));
                    }, $item->reservations[$j]->passengers);

                $seg[] = $direct;
            }

            $train['TripSegments'] = $seg;
            $result[] = $train;
        }

        $this->logger->debug('Parsed Train:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    protected function parseAir($it)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $data = [
            'number'           => $it->number,
            'email'            => $it->orderEmail,
            'smd'              => true,
            'noUpdateRsvInGds' => false,
        ];
        $headers = [
            'Accept' => 'application/json, text/javascript, */*',
        ];
        $this->http->PostURL("https://{$this->domain}/_api/issuetracker/getRequests/", $data, $headers);
        $response = $this->http->JsonLog(null, 0, true);

        if (empty($response['result'])) {
            $this->logger->info('Json has changed');

            return $result;
        }

        if (isset($response['result'][0]['reservations'])) {
            $reservations = $response['result'][0]['reservations'];
        } else {
            $reservations = [];
        }

        foreach ($reservations as $reserv) {
            $air = [];
            $air['Kind'] = 'T';
            $vendorPnr = $reserv['vendorPnr'];
            $air['RecordLocator'] = $this->http->FindPreg('/^\s*(\w+)/', false, $vendorPnr);
            $this->logger->info(sprintf('Parse Air #%s', $air['RecordLocator']), ['Header' => 3]);
            $tripSegments = [];

            foreach (ArrayVal($reserv, 'trips', []) as $trip) {
                $ts = [];
                // DepDate
                $depDate = sprintf('%s, %s', $trip['stDt'], $trip['stTm']);
                $this->logger->debug('depDate:');
                $this->logger->debug($depDate);
                $depDate = DateTime::createFromFormat('Ymd, Gi', $depDate);
                $ts['DepDate'] = $depDate ? $depDate->getTimestamp() : 0;
                // ArrDate
                $date2 = ArrayVal($trip, 'endDate') ?: ArrayVal($trip, 'stDt');
                $arrDate = sprintf('%s, %s', $date2, $trip['endTm']);
                $this->logger->debug('arrDate:');
                $this->logger->debug($arrDate);
                $arrDate = DateTime::createFromFormat('Ymd, Gi', $arrDate);
                $ts['ArrDate'] = $arrDate ? $arrDate->getTimeStamp() : 0;
                // DepCode
                $ts['DepCode'] = $trip['from'];
                // ArrCode
                $ts['ArrCode'] = $trip['to'];
                // BookingClass
                $ts['BookingClass'] = $trip['cls'];
                // Aircraft
                $ts['Aircraft'] = $trip['planeStr'] ?? null;
                // AirlineName
                $ts['AirlineName'] = $trip['airCmp'];
                // Cabin
                $cabin = ArrayVal($trip, 'srvCls');

                if ($cabin === 'E') {
                    $ts['Cabin'] = 'Economy';
                } elseif ($cabin === 'B') {
                    $ts['Cabin'] = 'Business';
                } elseif ($cabin) {
                    $this->sendNotification("onetwotrip - new cabin was found: {$cabin}");
                }
                // Stops
                $ts['Stops'] = ArrayVal($trip, 'stopNum');
                // FlightNumber
                $ts['FlightNumber'] = $trip['fltNm'];
                // Duration
                if ($this->http->FindPreg('/\b(\d{4})\b/', false, $trip['fltTm'])) {
                    $h = $this->http->FindPreg('/\b(\d{2})\d{2}\b/', false, $trip['fltTm']);
                    $m = $this->http->FindPreg('/\b\d{2}(\d{2})\b/', false, $trip['fltTm']);
                    $h = ltrim($h, '0');
                    $m = ltrim($m, '0');

                    if ($h && $m) {
                        $ts['Duration'] = sprintf('%sh %sm', $h, $m);
                    } elseif ($h) {
                        $ts['Duration'] = sprintf('%sh', $h);
                    } elseif ($m) {
                        $ts['Duration'] = sprintf('%sm', $m);
                    }
                }
                $tripSegments[] = $ts;
            }

            if ($tripSegments && $tripSegments[count($tripSegments) - 1]['ArrDate'] < strtotime('now')) {
                continue;
            }
            $air['TripSegments'] = $tripSegments;

            $this->logger->debug('Parsed Air:');
            $this->logger->debug(var_export($air, true), ['pre' => true]);
            $result[] = $air;
        }

        return $result;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = '6LePmCITAAAAAOMx5rQ7P-Ycs6rygGMBFxyxYLSS';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
