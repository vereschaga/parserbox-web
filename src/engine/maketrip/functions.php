<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerMaketrip extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $itinLength = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptchaVultr());
        $this->http->setRandomUserAgent();
    }

    public function IsLoggedIn()
    {
        return false;

        if (empty($this->State['mmt-auth'])) {
            return false;
        }

        $headers = [
            'Accept'       => '*/*',
            'Content-Type' => 'application/json',
            'mmt-auth'     => $this->State['mmt-auth'],
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://supportz.makemytrip.com/api/getuserdetails/", $headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->result->extendedUser->personalDetails->name)) {
            return true;
        }

        return false;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'maketripMyWallet')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "Rs. %0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->getCookiesFromSelenium(); // todo: error: Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2) - sensor_data issue
        $this->http->GetURL("https://supportz.makemytrip.com/login");

        if ($this->http->Response['code'] == 403) {
            $this->setProxyGoProxies(true);
            $this->http->removeCookies();
            $this->http->GetURL("https://supportz.makemytrip.com/login");
        }

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $uuid = $this->getUuid(); // '80dea4f8-1a0e-4718-b369-6ef482ee74a9';
        $this->State['loginHeaders'] = $headers = [
            'Accept'          => 'application/json',
            'Content-Type'    => 'application/json',
            'Origin'          => 'https://supportz.makemytrip.com',
            'Referer'         => 'https://supportz.makemytrip.com',
            'Authorization'   => 'h4nhc9jcgpAGIjp',
            'currency'        => 'usd',
            'deviceid'        => $uuid,
            'language'        => 'eng',
            'os'              => 'desktop',
            'region'          => 'us',
            'tid'             => $uuid,
            'user-identifier' => '{"ipAddress":"ipAddress","imie":"imie","appVersion":"appVersion","os":"DESKTOP","osVersion":"osVersion","timeZone":"timeZone","type":"mmt-auth","profileType":"","Authorization":"h4nhc9jcgpAGIjp","deviceId":"' . $uuid . '"}',
            'vid'             => $uuid,
            'visitor-id'      => $uuid,
        ];
        $data = [
            'loginId'     => $this->AccountFields['Login'],
            'version'     => '2',
            'type'        => 'EMAIL',
            'countryCode' => '1',
        ];
        $this->http->PostURL('https://mapi.makemytrip.com/ext/web/pwa/isUserRegistered?region=us&currency=usd&language=eng', json_encode($data), $headers);
        $isUserRegistered = $this->http->JsonLog();
        $success = $isUserRegistered->success ?? false;

        if (!$success) {
            $message = $isUserRegistered->message ?? null;

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if (
                    $message == "Email should be in the recommended email message format"
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;

                return false;
            }// if ($message)

            return false;
        }
        $this->State['loginData'] = $data = [
            'passwordType' => 'PASSWORD',
            'query'        => json_decode('[[{"name":"extendedUser","keys":["primaryEmailId","uuid","accountId","profileType","corporateData","personalDetails","createdAt","redirectToMyBiz"]}],[{"name":"extendedUser"},{"name":"contactDetails","keys":["contactId","type","category","name","info","additionalDetails"],"filters":[{"name":"status","value":"Active"}]}],[{"name":"personalDetails","keys":["name","gender","childNCount","maritalStatus","anniversaryDate","dateOfBirth"]}],[{"name":"extendedUser"},{"name":"loginInfoList","keys":["loginId","verified","status","loginType","countryCode"],"filters":[{"name":"verified","value":true},{"name":"status","value":"ACTIVE"}]}],[{"name":"extendedUser"},{"name":"userImages","keys":["id","fileName","filePath","status"],"filters":[{"name":"status","value":"Active"}]}]]'),
            'type'         => 'EMAIL',
            'userEmail'    => $this->AccountFields['Login'],
            'password'     => $this->AccountFields['Pass'],
            'countryCode'  => '1',
        ];
        $this->http->PostURL('https://mapi.makemytrip.com/ext/web/pwa/login?region=us&currency=usd&language=eng', json_encode($data), $headers);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.makemytrip.com/";

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // provider error
        if (
            $this->http->FindPreg("/(<H1>Server Error in '\/' Application\.)/ims")
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $json = $this->http->JsonLog();
        $message = $json->message ?? null;

        if ($message) {
            if ($message == 'SUCCESS') {
                return true;
            }

            if ($message == 'Login with OTP is required') {
                $question = 'Verify Your E-mail ID. Otp has been sent to mobile';
                $this->AskQuestion($question, null, 'Question');

                return false;
            }

            $this->logger->error("[Error]: {$message}");

            if (
                $message == "Username and Password do not match."
                || $message == "Wrong email Id entered."
                || strstr($message, 'Your account has been temporarily locked due to continuous failed attempts.')
                || $message == "Your password has expired. Please click on Forgot Password below to reset your password."
                || $message == "Email should be in the recommended email message format"
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == "Authentication Failure"
            ) {
                throw new CheckException('Either Username or Password is incorrect.', ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindPreg('/"message.":."sorry, you don\'t have access to this api."/')) {
            $this->DebugInfo = "need to upd sensor_data";

            throw new CheckRetryNeededException();
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step): bool
    {
        $this->logger->notice(__METHOD__);
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->sendNotification('check 2fa // MI');

        $data = $this->State['loginData'];
        $data['password'] = $answer;
        $data['passwordType'] = 'OTP';
        $this->http->PostURL('https://mapi.makemytrip.com/ext/web/pwa/login?region=us&currency=usd&language=eng', json_encode($data), $this->State['loginHeaders']);
        $response = $this->http->JsonLog();

        if (empty($response) && $this->http->Response['code'] == 403) {
            $this->AskQuestion($this->Question, 'Looks like we are facing some technical issues, please try again in some time.', 'Question');

            return false;
        }

        if (!empty($response) && $response->message == 'Authentication Failure') {
            $this->AskQuestion($this->Question, 'Incorrect OTP! Please enter the OTP delivered to you.', 'Question');

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);

        $name = $response->data->profileLoginResponse[0]->userDetails->extendedUser->personalDetails->name->firstName ?? null;
        $name .= ' ' . ($response->data->profileLoginResponse[0]->userDetails->extendedUser->personalDetails->name->lastName ?? null);
        $this->SetProperty('Name', beautifulName($name));

        if (!empty($this->Properties['Name']) || !empty($response->data->profileLoginResponse[0]->userDetails->extendedUser->primaryEmailId)) {
            $this->SetBalanceNA();
        }
        /*$data = '{"user":{"location":{},"deviceInfo":{"trafficSource":{"referringDomain":"https://supportz.makemytrip.com/"}}},"userEvents":{"searchEvents":{"flights":{},"hotels":{}}},"context":{},"refreshFlags":{"isWalletRefresh":true}}';
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://mapi.makemytrip.com/empeiria/api/v1/walletlanding?brand=mmt&profile=b2c&device=dt&version=undefined&region=us&language=eng&currency=usd', $data, $headers);
        $this->http->RetryCount = 2;
        $json = $this->http->JsonLog();




        if (1 !== null) {
            $this->SetProperty("CombineSubAccounts", false);
            $subAccount = [
                'Code'            => "maketripMyWallet",
                'DisplayName'     => "MyWallet",
                'Balance'         => $balance,
                // Reward Bonus
                'RewardBonus'     => $walletData->totalWalletBonus,
                // My Cash
                'MyCash'          => $walletData->totalRealAmount,
                // Expiring balance
                'ExpiringBalance' => $walletData->totalNearestRealExpiryAmount ?? null,
            ];
            // ... My Cash will expire on ...
            $exp = $walletData->nearestRealExpiryDate ?? null;

            if ($exp && strtotime($exp)) {
                $subAccount['ExpirationDate'] = strtotime($exp);
            }
            $this->AddSubAccount($subAccount, true);
        }// if ($balance !== null)
        // AccountID: 3191646, 3475994, 5635610, 3475994, 3191646
        elseif (
            isset($walletData->message)
            && $walletData->message == 'User Not Found'
            && ($walletData->loyaltyStatus == 'BL_BLACKLIST$DB_INACTIVE' || $walletData->loyaltyStatus == 'INACTIVE')
            && $walletData->statusCode == 420
        ) {
            $this->AddSubAccount([
                'Code'            => "maketripMyWallet",
                'DisplayName'     => "MyWallet",
                'Balance'         => 0,
                // Reward Bonus
                'RewardBonus'     => 0,
                // My Cash
                'MyCash'          => 0,
            ], true);
        }*/
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $this->http->setDefaultHeader('mmt-auth', $this->http->getCookieByName('mmt-auth'));
        $this->http->setDefaultHeader('Accept', 'application/json, text/plain, */*');
        // upcomingbookings=true ignores cancelled
        // so we ask for all and then possibly filter
        $this->http->GetURL('https://supportz.makemytrip.com/api/bookingsummary/upcomingbookings=false/cntUpcmgBookingsFetched=/');
        $data = $this->http->JsonLog(null, 1, true);

        if (!$data) {
            $this->logger->info('Empty json data from upcoming request');

            return [];
        }
        $details = ArrayVal($data, 'bookingDetails', []);
        $emptyDetails = array_key_exists('bookingDetails', $data) && !$data['bookingDetails'];
        $emptyCount = array_key_exists('countTotalUpcomingBookings', $data) && !$data['countTotalUpcomingBookings'];

        if ($emptyDetails && $emptyCount) {
            return $this->noItinerariesArr();
        }

        $bookingIds = [];
        $detailsWithoutPast = $details;
        $countItin = 0;

        foreach ($details as $i => $det) {
            $minDepartureDate = floor(ArrayVal($det, 'minDepartureDate') / 1000);
            $maxDepartureDate = floor(ArrayVal($det, 'maxDepartureDate') / 1000);

            if (!$this->ParsePastIts && $maxDepartureDate < strtotime('now')) {
                $this->logger->info('Skipping itinerary in the past');

                if (isset($detailsWithoutPast[$i]) && $detailsWithoutPast[$i] == $det) {
                    unset($detailsWithoutPast[$i]);
                }

                continue;
            }

            $sixMonths = strtotime('now') - strtotime('-6 months', strtotime('now'));

            if ($maxDepartureDate - $minDepartureDate > $sixMonths) {
                $this->logger->info('Skipping shady itinerary with max - min departure > 6 months');

                continue;
            }

            $id = ArrayVal($det, 'bookingID');
            $phone = ArrayVal($det, 'phoneNumber');
            $id = strtoupper($id);

            if (array_key_exists($id, $bookingIds)) {
                $this->logger->info('Skipping already processed itinerary');

                continue;
            }

            if (!array_key_exists('bookingID', $det)) {
                $this->logger->info('New json structure, bookingID is not present in:');
                $this->logger->info(print_r($det, true));

                continue;
            }
            $bookingIds[$id] = true;

            $itin = [];
            $type = $det['bookingType'];
            $status = ArrayVal($det, 'status', null);

            if ($status === 'FAILED') {
                $this->logger->info('Skipping itinerary with FAILED status');

                continue;
            }

            if ($this->itinLength > 45) {
                $this->logger->debug('Stop parse itinerary');

                break;
            }

            if ($this->itinLength == 13 || $this->itinLength == 26 || $this->itinLength == 39) {
                $this->logger->debug('Increase time limit');
                $this->increaseTimeLimit(300);
            }

            switch ($type) {
                case 'FLIGHT':
                    ++$this->itinLength;
                    $itin = $this->ParseFlightJson($id, $status);

                    break;

                case 'RAIL':
                    ++$this->itinLength;
                    $phone = ArrayVal($det, 'phoneNumber');
                    $itin = $this->parseTrainJson($id, $status);

                    break;

                case 'HOTEL':
                    ++$this->itinLength;
                    $itin = $this->parseHotelJson($id, $status);

                    break;

                case 'FPH':
                    ++$this->itinLength;
                    $this->logger->info('Skipping mixed FPH itinerary with invalid data.');

                    break;

                case 'BUS':
                    if (!$this->ParsePastIts) {
                        $this->sendNotification('check upcoming it // MI');
                    }
                    ++$this->itinLength;
                    $itin = $this->parseBusJson($id, $status);

                    break;

                case 'CAR':
                    ++$this->itinLength;
                    $itin = $this->parseRentalJson($id, $status);

                    break;

                case 'HOLIDAYS':
                    ++$this->itinLength;
                    $itins = $this->parseHoliday($id, $phone);

                    if ($itins) {
                        $result = array_merge($result, $itins);
                    }

                    break;

                default:
                    $this->logger->error('Unsupported itinerary type "' . $type . '"');
                    $this->sendNotification('maketrip - unsupported itinerary type "' . $type . '"');
                    $this->logger->info(var_export($det, true), ['pre' => true]);
            }

            if ($itin) {
                $result[] = $itin;
            }
        }

        if (empty($result) && empty($detailsWithoutPast) && $emptyCount) {
            return $this->noItinerariesArr();
        }

        return $result;
    }

    public function ArrayVal($ar, $indices)
    {
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return null;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu', '(/.+?)'\]\);#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            "7a74G7m23Vrp0o5c9171681.6-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,392355,3624748,1536,880,1536,960,1531,396,1531,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8962,0.593986840296,797316812373.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://supportz.makemytrip.com/-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1594633624747,-999999,17058,0,0,2843,0,0,3,0,0,71E42F2C9E30DA3B62470AA2AD9B6F40~-1~YAAQD+EZuBTq9UZzAQAATRKSRwSI8sfTMCdHWcRgOrClpfVtW91aDgcoE2xRAwohG25/c0tndSBvKig9TXlPt5f7MhNq4UYXdeJvP1DilrCLcOjQoVvRv7en5UA7QVUQgJUV+/aCNzQt4Qqe9coqdDr9whxBXuyYNqc+jtnTO2l2VTQocUpC0p54JpLg6oMh3F6dXwNuUKTnNlsp9eOXTXKOW4yhPWiEj5pmEg0o0/MNNGrvohuL0nx4QG4cthFEzC7YuF9ksp6hmYV1Jv3f7NZMjzIBt6bu5t65RZvN0N/t3FTqpX55o24G/R7Jaw==~-1~-1~-1,30216,-1,-1,30261693,PiZtE,60855,95-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,2446704771-1,2,-94,-118,76993-1,2,-94,-121,;4;-1;0",
        ];

        $secondSensorData = [
            "7a74G7m23Vrp0o5c9171681.6-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,392355,3624748,1536,880,1536,960,1531,396,1531,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8962,0.389986031194,797316812373.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://supportz.makemytrip.com/-1,2,-94,-115,1,32,32,0,0,0,0,524,0,1594633624747,13,17058,0,0,2843,0,0,525,0,0,71E42F2C9E30DA3B62470AA2AD9B6F40~-1~YAAQD+EZuBTq9UZzAQAATRKSRwSI8sfTMCdHWcRgOrClpfVtW91aDgcoE2xRAwohG25/c0tndSBvKig9TXlPt5f7MhNq4UYXdeJvP1DilrCLcOjQoVvRv7en5UA7QVUQgJUV+/aCNzQt4Qqe9coqdDr9whxBXuyYNqc+jtnTO2l2VTQocUpC0p54JpLg6oMh3F6dXwNuUKTnNlsp9eOXTXKOW4yhPWiEj5pmEg0o0/MNNGrvohuL0nx4QG4cthFEzC7YuF9ksp6hmYV1Jv3f7NZMjzIBt6bu5t65RZvN0N/t3FTqpX55o24G/R7Jaw==~-1~-1~-1,30216,725,430425483,30261693,PiZtE,101292,97-1,2,-94,-106,9,1-1,2,-94,-119,50,42,42,42,78,69,43,41,9,7,7,439,381,418,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,2446704771-1,2,-94,-118,80443-1,2,-94,-121,;3;7;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }

    protected function parseHolidayHotel($node)
    {
        $this->logger->notice(__METHOD__);
        $res = ['Kind' => 'R'];

        // ConfirmationNumber
        $res['ConfirmationNumber'] = CONFNO_UNKNOWN;
        $this->logger->info(sprintf('Parse Holiday Hotel #%s', $res['ConfirmationNumber']), ['Header' => 3]);
        // HotelName
        $res['HotelName'] = $this->http->FindSingleNode('./preceding-sibling::div[1]//span[contains(@class, "mi_fph_nHotel")]', $node);
        // Address
        $res['Address'] = $this->http->FindSingleNode('./preceding-sibling::div[1]//div[contains(@class, "mi_holidays_horizontal_padding_hotel_details")]/p[2]', $node);
        // CheckInDate
        $checkIn = $this->http->FindSingleNode('.//td[contains(text(), "Check-In")]/following-sibling::td[1]', $node);
        $checkIn = preg_replace("/'/", '', $checkIn);
        $date1 = $this->http->FindPreg('/(\d+\s+\w+\s+\d+),/', false, $checkIn);
        $time1 = $this->http->FindPreg('/(\d+:\d+)/', false, $checkIn);
        $this->logger->debug(var_export([
            'date1' => $date1,
            'time1' => $time1,
        ], true), ['pre' => true]);
        $res['CheckInDate'] = strtotime($time1, strtotime($date1));
        // CheckOutDate
        $checkOut = $this->http->FindSingleNode('.//td[contains(text(), "Check-Out")]/following-sibling::td[1]', $node);
        $checkOut = preg_replace("/'/", '', $checkOut);
        $date2 = $this->http->FindPreg('/(\d+\s+\w+\s+\d+),/', false, $checkOut);
        $time2 = $this->http->FindPreg('/(\d+:\d+)/', false, $checkOut);
        $res['CheckOutDate'] = strtotime($time2, strtotime($date2));

        if (!$res['CheckInDate'] || !$res['CheckOutDate']) {
            $this->sendNotification('check makemytrip holiday hotel dates');

            return [];
        }
        // RoomType
        $res['RoomType'] = $this->http->FindSingleNode('.//td[contains(text(), "Room Type")]/following-sibling::td[1]', $node);
        // Rooms
        $res['Rooms'] = $this->http->FindSingleNode('.//td[contains(text(), "Reservation")]/following-sibling::td[1]', $node, true, '/(\d+)\s+Room/');

        if (!$this->ParsePastIts && $res['CheckOutDate'] < strtotime('now')) {
            $this->logger->info('Skipping hotel in the past');

            return [];
        }

        return $res;
    }

    private function getUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function xpathQuery($query, $parent = null)
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query, $parent);
        $this->logger->info(sprintf('found %s nodes: %s', $res->length, $query));

        return $res;
    }

    private function parseHolidayFlight($node)
    {
        $this->logger->notice(__METHOD__);
        $res = ['Kind' => 'T'];

        // RecordLocator
        $res['RecordLocator'] = CONFNO_UNKNOWN;
        $this->logger->info(sprintf('Parse Holiday Flight #%s', $res['RecordLocator']), ['Header' => 3]);
        // TripSegments
        $segments = [];
        $year = $this->http->FindPreg('/(\b\d{4}\b)/', false, $node->nodeValue);
        $details = $this->xpathQuery('.//div[@class = "mi_fph_flight paddTB10"]', $node);
        $futureTrip = false;

        foreach ($details as $det) {
            $seg = [];
            // DepDate
            $depInfo = $this->http->FindSingleNode('(.//div[contains(@class, "mi_fph_time_details")])[1]', $det);
            $date1 = $this->http->FindPreg('/(\d+\s+\w+)/', false, $depInfo);
            $date1 = sprintf('%s %s', $date1, $year);
            $time1 = $this->http->FindPreg('/(\d+:\d+)/', false, $depInfo);
            $this->logger->debug(var_export([
                'date1' => $date1,
                'time1' => $time1,
            ], true), ['pre' => true]);
            $seg['DepDate'] = strtotime($time1, strtotime($date1));
            // ArrDate
            $arrInfo = $this->http->FindSingleNode('(.//div[contains(@class, "mi_fph_time_details")])[2]', $det);
            $date2 = $this->http->FindPreg('/(\d+\s+\w+)/', false, $arrInfo);
            $date2 = sprintf('%s %s', $date2, $year);
            $time2 = $this->http->FindPreg('/(\d+:\d+)/', false, $arrInfo);
            $seg['ArrDate'] = strtotime($time2, strtotime($date2));

            if (!$seg['DepDate'] || !$seg['ArrDate']) {
                $this->sendNotification('check makemytrip holiday flight dates');

                return [];
            }
            // DepCode
            $seg['DepCode'] = $this->http->FindPreg('/\b([A-Z]{3})\b/', false, $depInfo);
            // ArrCode
            $seg['ArrCode'] = $this->http->FindPreg('/\b([A-Z]{3})\b/', false, $arrInfo);
            // AirlineName
            $flightInfo = $this->http->FindSingleNode('.//div[contains(@class, "mi_fph_flight_details")]/div[1]', $det);
            $seg['AirlineName'] = $this->http->FindPreg('/\b(\w{2})\s+\d+/', false, $flightInfo);
            // FlightNumber
            $seg['FlightNumber'] = $this->http->FindPreg('/(\d+)\s*$/', false, $flightInfo);
            $segments[] = $seg;

            if (ArrayVal($seg, 'ArrDate') >= strtotime('now')) {
                $futureTrip = true;
            }
        }
        $res['TripSegments'] = $segments;

        if (!$this->ParsePastIts && !$futureTrip) {
            $this->logger->info('Skipping flight in the past');
            $this->logger->debug(var_export($res, true), ['pre' => true]);

            return [];
        }

        return $res;
    }

    private function parseHoliday($id, $phone)
    {
        $this->logger->notice(__METHOD__);
        $res = [];

        $this->http->ParseEncoding = false;
        $url = sprintf('https://support.makemytrip.com/HolidayBookingDetails.aspx?hdnBookingID=%s&hdnPhoneNumber=%s', $id, $phone);
        $retries = 5;

        for ($i = 0; $i < $retries; $i++) {
            $this->logger->info(sprintf('try #%s', $i + 1));
            $this->http->GetURL($url);

            if (!$this->http->FindPreg('/Error.aspx/', false, $this->http->currentUrl())) {
                break;
            }
        }
        $this->http->ParseEncoding = true;

        $bookings = $this->xpathQuery('//span[contains(text(), "Show Details")]/ancestor::div[1]/following-sibling::div[2]');

        foreach ($bookings as $booking) {
            $value = $booking->nodeValue;
            $itin = [];

            if ($this->http->FindPreg('/FLIGHT DETAILS/i', false, $value)) {
                $itin = $this->ParseHolidayFlight($booking);
            } elseif ($this->http->FindPreg('/HOTEL DETAILS/i', false, $value)) {
                $itin = $this->ParseHolidayHotel($booking);
            } else {
                $this->sendNotification('check maketrip holidays itinerary, something new inside');
            }
            $this->logger->info('Parsed Itinerary:');
            $this->logger->info(var_export($itin, true), ['pre' => true]);

            if ($itin) {
                $res[] = $itin;
            }
        }

        return $res;
    }

    private function parseFlightJson($id, $status)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://supportz.makemytrip.com/api/flightBookingDetails/v4/$id", [
            'Content-Type'      => 'application/json',
            'logging-trackinfo' => sprintf('{"userId":"","uniqueId":"%s","uniqueIdType":"FLIGHT_BOOKING_DETAIL","source":"DESKTOP","loginSource":"Desktop","agentId":"","clientIp":"","siteDomain":"ind"}', $id),
        ]);
        $this->http->RetryCount = 2;
        $data = $this->http->JsonLog(null, 0, true);
        $exception = $data['exception'] ?? null;

        if ($exception === 'com.netflix.hystrix.exception.HystrixRuntimeException') {
            $this->logger->error("Skipping flight: {$exception}");

            return null;
        }

        $pnrSet = [];

        if (isset($data['flightDetails']['segmentGroupDetailList'])) {
            $detailList = $data['flightDetails']['segmentGroupDetailList'];
        } else {
            return null;
        }

        foreach ($detailList as $seg) {
            foreach ($seg['segmentDetails'] as $segmentDetail) {
                $pnr = $segmentDetail['pnrNo'];
                $pnrSet[$pnr] = true;
            }
        }
        $pnrList = array_keys($pnrSet);
        // RecordLocator
        if (count($pnrList) > 1) {
            $result['ConfirmationNumbers'] = implode(', ', $pnrList); // need to objects: segment->airline->confirmation
            $locator = $id;

            if ($id !== 'FAILED') {
                $result['TripNumber'] = $id;
            }
            $result['RecordLocator'] = CONFNO_UNKNOWN;
        } else {
            $locator = $pnrList[0];
            $result['RecordLocator'] = $locator;
        }
        $result['Kind'] = 'T';
        $this->logger->info(sprintf('[%s] Parse Flight #%s', $this->itinLength, $locator), ['Header' => 3]);
        $this->logger->notice("Status -> {$status}");
        // ReservationDate
        $reservationDate = ArrayVal($data, 'bookingDateTime');
        $reservationDate = preg_replace('/(\d+:\d+):(\d{2})\b/', '\1:00', $reservationDate);
        $this->logger->debug("ReservationDate: {$reservationDate} / " . strtotime($reservationDate));

        if ($reservationDate = strtotime($reservationDate)) {
            $result['ReservationDate'] = $reservationDate;
        }
        // Passengers
        $passengers = [];

        foreach ($data['passengerList'] as $pas) {
            // may be 'Mr. J Smith' or 'J Smith' in different segments
            $passengers[] = beautifulName($pas['passengerName']['firstName'] . " " . $pas['passengerName']['lastName']);
        }
        $result['Passengers'] = array_unique($passengers);
        // TotalCharge
        $result['TotalCharge'] = $data['paymentDetails']['paymentAmount'];
        // Currency
        if (isset($data['paymentDetails']['paymentModeDetails'][0]['currencyCode'])) {
            $result['Currency'] = $data['paymentDetails']['paymentModeDetails'][0]['currencyCode'];
        }
        // Status
        $bookingStatus = ArrayVal($data, 'bookingStatus', '');

        if ($this->http->FindPreg('/Cancelled/i', false, $bookingStatus) || $status == 'CANCELLED') {
            $result['Status'] = 'Cancelled';
            $result['Cancelled'] = true;
        }

        $result['AccountNumbers'] = [];
        // Air Trip Segments
        $result['TripSegments'] = [];
        $futureTrip = false;

        foreach ($data['flightDetails']['segmentGroupDetailList'] as $seg) {
            $segmentDetails = $seg['segmentDetails'];

            foreach ($segmentDetails as $segmentDetail) {
                $seg = $segmentDetail;
                $tripSeg = [];
                // FlightNumber
                $tripSeg['FlightNumber'] = $seg['flightDesignator']['flightNumber'];
                // AirlineName
                $tripSeg['AirlineName'] = $seg['airLineCode'];
                // DepCode
                $tripSeg['DepCode'] = $seg['originCityCode'];
                // DepartureTerminal
                $tripSeg['DepartureTerminal'] = $seg['departureTerminal'];
                // DepName
                $tripSeg['DepName'] = $seg['originCity'];
                // ArrCode
                $tripSeg['ArrCode'] = $seg['destinationCityCode'];
                // ArrivalTerminal
                $tripSeg['ArrivalTerminal'] = $seg['arrivalTerminal'];
                // ArrName
                $tripSeg['ArrName'] = $seg['destinationCity'];
                // ArrDate
                $tripSeg['DepDate'] = strtotime($seg['travelDateTime']);
                // DepDate
                $tripSeg['ArrDate'] = strtotime($seg['arrivalDateTime']);
                // Stops
                $tripSeg['Stops'] = $seg['noOfStops'];
                // Duration
                $tripSeg['Duration'] = $seg['travelDuration'];
                // AccountNumbers
                $segmentPassengerDetail = ArrayVal($seg, 'segmentPassengerDetail', []);

                foreach ($segmentPassengerDetail as $passengerDetail) {
                    $ticketNo = ArrayVal($passengerDetail, 'ticketNo', null);

                    if ($ticketNo && $ticketNo != 'TO BE ISSUED' && !in_array($ticketNo, $pnrList)) {
                        $result['AccountNumbers'][] = $ticketNo;
                    }
                    $isPaxCancelled = ArrayVal($passengerDetail, 'isPaxCancelled', null);
                    $canceledSegment = $isPaxCancelled === true ? true : false;
                }

                // skip Cancelled segments
                if (isset($canceledSegment) && $canceledSegment === true) {
                    $this->logger->notice("skip Cancelled segment");
                    $this->logger->debug(var_export($tripSeg, true), ['pre' => true]);

                    continue;
                }

                $result['TripSegments'][] = $tripSeg;

                if (ArrayVal($tripSeg, 'ArrDate') >= time()) {
                    $futureTrip = true;
                }
            }// foreach ($segmentDetails as $segmentDetail)
        }// foreach ($data['flightDetails']['segmentGroupDetailList'] as $seg)

        if (!empty($result['AccountNumbers'])) {
            $result['AccountNumbers'] = array_unique($result['AccountNumbers']);
        }

        if (!$this->ParsePastIts && !$futureTrip) {
            $this->logger->info('Skipping flight in the past');
            $this->logger->debug(var_export($result, true), ['pre' => true]);

            return [];
        }

        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function parseHotelJson($id, $status)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $result['Kind'] = 'R';

        $this->http->RetryCount = 0;
        $this->http->GetURL(sprintf('https://supportz.makemytrip.com/api/hotelDetails/%s', $id));
        $this->http->RetryCount = 2;
        $data = $this->http->JsonLog(null, 0, true);
        $exception = $data['exception'] ?? null;

        if ($exception === 'java.lang.StringIndexOutOfBoundsException') {
            $this->logger->error("Skipping hotel: {$exception}");

            return [];
        }
        $info = ArrayVal($data, 'hotelBookingInfo');
        $hotelDetails = [];

        if (isset($info['hotelDetailsList'][0])) {
            $hotelDetails = $info['hotelDetailsList'][0];
        }

        if (!$info || !$hotelDetails) {
            $this->sendNotification('check hotels // MI');

            return [];
        }

        // ConfirmationNumber
        $result['ConfirmationNumber'] = ArrayVal($hotelDetails, 'pnr') ?: $id;
        $result['ConfirmationNumber'] = $this->http->FindPreg('/([\w\-]+)/', false, $result['ConfirmationNumber']);
        $this->logger->info(sprintf('[%s] Parse Hotel #%s', $this->itinLength, $result['ConfirmationNumber']), ['Header' => 3]);
        $this->logger->notice("Status -> {$status}");
        // HotelName
        $result['HotelName'] = ArrayVal($hotelDetails, 'name');
        // Address
        $result['Address'] = trim(sprintf('%s %s', ArrayVal($hotelDetails, 'addressLine1'), ArrayVal($hotelDetails, 'addressLine2')));
        // Phone
        $result['Phone'] = ArrayVal($hotelDetails, 'phoneNumber') ?: null;

        /*
        if (strstr($result['Phone'], ':') && $this->http->FindPreg("/(?:^\d{5,}:\(|[\d\s]+\s+\,)/", false, $result['Phone'])) {
            $result['Phone'] = $this->http->FindPreg("/([^\:\,]+)/", false, $result['Phone']);
        }

        $result['Phone'] = $this->http->FindPreg('/^\s*,(.+)/', false, $result['Phone']) ?: $result['Phone'];
        */
        if (isset($result['Phone'])) {
            // :8008200222:(reception):9666667843:(AshishShekar):8499862220:(srinu), 040-23230105
            $result['Phone'] = join(',', $this->http->FindPregAll("/:(\d{5,}):/", $result['Phone']));
        }

        if ($result['Phone']) {
            $result['Phone'] = trim($result['Phone']);
        }
        // Fax
        $result['Fax'] = ArrayVal($hotelDetails, 'faxNumber') ?: null;

        // CheckInDate
        $result['CheckInDate'] = strtotime(ArrayVal($info, 'checkInTime'), strtotime(ArrayVal($info, 'checkInDate')));
        // CheckOutDate
        $result['CheckOutDate'] = strtotime(ArrayVal($info, 'checkOutTime'), strtotime(ArrayVal($info, 'checkOutDate')));

        if (!$this->ParsePastIts && $result['CheckOutDate'] < strtotime('now')) {
            $this->logger->info('Skipping hotel in the past');

            return [];
        }

        // GuestNames
        $primaryCustomer = ArrayVal($info, 'primaryCustomerDetails');

        if ($primaryCustomer) {
            $name = trim(sprintf('%s %s',
                ArrayVal($primaryCustomer, 'firstName'),
                ArrayVal($primaryCustomer, 'lastName')
            ));
            $result['GuestNames'] = [beautifulName($name)];
        }

        // CancellationPolicy
        $cancellation = $this->http->JsonLog(ArrayVal($hotelDetails, 'cancellationRules'));
        $cancellation = $cancellation[0]->description ?? ArrayVal($hotelDetails, 'cancellationRules');
        $result['CancellationPolicy'] = $cancellation;

        if (isset($info['roomDetails'][0])) {
            $roomDetails = $info['roomDetails'][0];
            // RoomType
            $result['RoomType'] = trim(ArrayVal($roomDetails, 'roomTypeName'), '|');
        }
        // Guests
        $result['Guests'] = ArrayVal($info, 'totalNumberOfAdults');
        // Kids
        $result['Kids'] = ArrayVal($info, 'totalNumberOfChilds');

        // Total
        $payment = ArrayVal($data, 'paymentSummary');

        if ($payment) {
            // Total
            $result['Total'] = ArrayVal($payment, 'totalPrice');
            // Currency
            $result['Currency'] = ArrayVal($payment, 'currencyCode');
            // Taxes
            $result['Taxes'] = ArrayVal($payment, 'taxesAndServiceFee');
        }
        // Status
        $bookingStatus = ArrayVal($data, 'bookingStatus', '');

        if ($this->http->FindPreg('/Cancelled/i', false, $bookingStatus) || $status == 'CANCELLED') {
            $result['Status'] = 'Cancelled';
            $result['Cancelled'] = true;
        }

        if (!$this->ParsePastIts && $result['CheckOutDate'] < strtotime('now')) {
            $this->logger->info('Skipping hotel in the past');

            return [];
        }

        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function parseBusJson($id, $status)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $result['Kind'] = 'T';
        $result['TripCategory'] = TRIP_CATEGORY_BUS;

        $this->http->GetURL(sprintf('https://supportz.makemytrip.com/api/busbookingdetail/%s', $id));
        $data = $this->http->JsonLog(null, 0, true);
        $info = ArrayVal($data, 'busBookingInfo');
        $busDetails = [];

        if (isset($info['busDetail'])) {
            $busDetails = $info['busDetail'];
        }

        if (!$info || !$busDetails) {
            $this->sendNotification('check buses // MI');

            return [];
        }
        // RecordLocator
        $conf = $busDetails['operatorPnr'] ?? $id;

        if ($conf == 'FAILED') {
            $conf = $id;
        }
        $result['RecordLocator'] = $this->http->FindPreg('/([\w\-]+)/', false, $conf);
        $this->logger->info(sprintf('[%s] Parse Bus #%s', $this->itinLength, $result['RecordLocator']), ['Header' => 3]);
        $this->logger->notice("Status -> {$status}");
        // ReservationDate
        $reservationDate = ArrayVal($data, 'bookingDateTime');

        if ($reservationDate) {
            $result['ReservationDate'] = intval($reservationDate) / 1000;
        }
        // Passengers
        $passengers = [];
        $passengerDetails = ArrayVal($data, 'passengerDetails', []);

        foreach ($passengerDetails as $pas) {
            $passengers[] = beautifulName($pas['name']);
        }
        $result['Passengers'] = array_unique($passengers);
        // TotalCharge
        $result['TotalCharge'] = $this->ArrayVal($data, ['bookingPaymentDetail', 'sellingPrice']);
        // Currency
        $result['Currency'] = $this->ArrayVal($data, ['bookingPaymentDetail', 'paymentDetails', 0, 'currencyCode']);
        // Status
        $bookingStatus = ArrayVal($data, 'bookingStatus', '');

        if ($this->http->FindPreg('/Cancelled/i', false, $bookingStatus) || $status == 'CANCELLED') {
            $result['Status'] = 'Cancelled';
            $result['Cancelled'] = true;
        }

        // Bus Trip Segments
        $result['TripSegments'] = [];
        $segment = [
            'FlightNumber' => FLIGHT_NUMBER_UNKNOWN,
        ];
        // DepName
        $segment['DepName'] = $this->ArrayVal($data, ['busBookingInfo', 'busDetail', 'fromCity']);
        $segment['DepCode'] = TRIP_CODE_UNKNOWN;
        // ArrName
        $segment['ArrName'] = $this->ArrayVal($data, ['busBookingInfo', 'busDetail', 'toCity']);
        $segment['ArrCode'] = TRIP_CODE_UNKNOWN;
        // DepAddress
        $segment['DepAddress'] = $this->ArrayVal($data, ['busAdditionalInfo', 'departureDetail', 'address']);

        if (!$segment['DepAddress']) {
            $segment['DepAddress'] = $this->ArrayVal($data, ['busAdditionalInfo', 'arrivalDetail', 'pickupLocation']);
        }
        $segment['DepAddress'] = $this->addCountry($segment['DepAddress']);

        // ArrAddress
        $segment['ArrAddress'] = $this->ArrayVal($data, ['busAdditionalInfo', 'arrivalDetail', 'address']);

        if (!$segment['ArrAddress']) {
            $segment['ArrAddress'] = $this->ArrayVal($data, ['busAdditionalInfo', 'arrivalDetail', 'dropLocation']);
        }
        $segment['ArrAddress'] = $this->addCountry($segment['ArrAddress']);

        // DepDate
        $depDate = $this->ArrayVal($data, ['busAdditionalInfo', 'departureDetail', 'departureDayInMonth']);
        $depDate .= " " . $this->ArrayVal($data, ['busAdditionalInfo', 'departureDetail', 'departureMonth']);
        $depDate .= ", " . $this->ArrayVal($data, ['busAdditionalInfo', 'departureDetail', 'departureYear']);
        $time1 = $this->ArrayVal($data, ['busAdditionalInfo', 'departureDetail', 'departureTimeInTwentyFourHoursFormat']);

        $this->logger->debug('DepDate: ' . $depDate);

        if ($depDate && $time1) {
            if ($depDate = strtotime($depDate)) {
                $segment['DepDate'] = strtotime($time1, $depDate);
            }
        }
        // ArrDate
        $arrDate = $this->ArrayVal($data, ['busAdditionalInfo', 'arrivalDetail', 'arrivalDayInMonth']);
        $arrDate .= " " . $this->ArrayVal($data, ['busAdditionalInfo', 'arrivalDetail', 'arrivalMonth']);
        $arrDate .= ", " . $this->ArrayVal($data, ['busAdditionalInfo', 'arrivalDetail', 'arrivalYear']);
        $time2 = $this->ArrayVal($data, ['busAdditionalInfo', 'arrivalDetail', 'arrivalTimeInTwentyFourHoursFormat']);
        $this->logger->debug('ArrDate: ' . $arrDate);

        if ($arrDate && $time2) {
            if ($arrDate = strtotime($arrDate)) {
                $segment['ArrDate'] = strtotime($time2, $arrDate);
            }
        }
        // Duration
        $segment['Duration'] = $this->ArrayVal($data, ['busAdditionalInfo', 'duration']);
        // Type
        $segment['Type'] = $this->ArrayVal($data, ['busBookingInfo', 'busDetail', 'type']);
        $result['TripSegments'] = [$segment];

        if (!$this->ParsePastIts && ArrayVal($segment, 'ArrDate') < strtotime('now')) {
            $this->logger->info('Skipping bus in the past');

            return [];
        }

        $this->logger->debug('Parsed Bus:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function addCountry(?string $city)
    {
        $cities = [
            'Pune' => 'Pune, Maharashtra, India',
        ];

        foreach ($cities as $c => $fullC) {
            if ($city == $c) {
                return $fullC;
            }
        }

        return $city;
    }

    private function parseRentalJson($id, $status)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $result['Kind'] = 'L';

        $this->http->GetURL(sprintf('https://supportz.makemytrip.com/api/carbookingdetail/%s', $id));
        $data = $this->http->JsonLog(null, 0, true);
        $info = ArrayVal($data, 'carBookingInfo');
        $carDetails = ArrayVal($info, 'carDetail');

        if (!$info || !$carDetails) {
            $this->sendNotification('check cars // MI');

            return [];
        }
        // Number
        $result['Number'] = ArrayVal($carDetails, 'pnr') ?: $id;
        $this->logger->info(sprintf('[%s] Parse Rental #%s', $this->itinLength, $result['Number']), ['Header' => 3]);
        $this->logger->notice("Status -> {$status}");
        // ReservationDate
        $reservationDate = ArrayVal($data, 'bookingDateTime');

        if ($reservationDate) {
            $result['ReservationDate'] = intval($reservationDate) / 1000;
        }
        // TotalCharge
        $result['TotalCharge'] = $this->ArrayVal($data, ['bookingPaymentDetail', 'sellingPrice']);
        // Currency
        $result['Currency'] = $this->ArrayVal($data, ['bookingPaymentDetail', 'paymentDetails', 0, 'currencyCode']);
        // Status
        $bookingStatus = ArrayVal($data, 'bookingStatus', '');

        if ($this->http->FindPreg('/Cancelled/i', false, $bookingStatus) || $status == 'CANCELLED') {
            $result['Status'] = 'Cancelled';
            $result['Cancelled'] = true;
        }
        // PickupLocation
        $carAdditionalInfo = ArrayVal($data, 'carAdditionalInfo');
        $departureDetail = ArrayVal($carAdditionalInfo, 'departureDetail');
        $result['PickupLocation'] = ArrayVal($departureDetail, 'pickupLocation');
        // PickupDatetime
        $result['PickupDatetime'] = strtotime(ArrayVal($departureDetail, 'departureDayInMonth') . " " . ArrayVal($departureDetail, 'departureMonth') . " " . ArrayVal($departureDetail, 'departureYear') . " " . ArrayVal($departureDetail, 'departureTimeInTwentyFourHoursFormat'));
        // PickupPhone
        $result['PickupPhone'] = ArrayVal($carDetails, 'contactNumbers');
        // DropoffLocation
        $arrivalDetail = ArrayVal($carAdditionalInfo, 'arrivalDetail');
        $result['DropoffLocation'] = ArrayVal($arrivalDetail, 'location');
        // DropoffDatetime
        $result['DropoffDatetime'] = strtotime(ArrayVal($arrivalDetail, 'arrivalDayInMonth') . " " . ArrayVal($arrivalDetail, 'arrivalMonth') . " " . ArrayVal($arrivalDetail, 'arrivalYear') . " " . ArrayVal($arrivalDetail, 'arrivalTimeInTwentyFourHoursFormat'));
//        // todo: need to check it on other
        if ($result['DropoffDatetime'] < 0) {
            $this->logger->notice("correcting DropoffDatetime");
            $result['DropoffDatetime'] = strtotime("+" . ArrayVal($carAdditionalInfo, 'noOfDays') . " days", $result['PickupDatetime']);
        }

        if ($result['DropoffDatetime'] === $result['PickupDatetime']) {
            $this->logger->notice("correcting DropoffDatetime");
            $result['DropoffDatetime'] = strtotime('1 minutes', $result['DropoffDatetime']);
        }
        // CarType
        $result['CarType'] = ArrayVal($carDetails, 'carType');
        // CarModel
        $result['CarModel'] = ArrayVal($carDetails, 'carBrand');
        // CarImageUrl
        $result['CarImageUrl'] = ArrayVal($carDetails, 'cabUrl');
        // RenterName
        $personalInfo = ArrayVal($data, 'primaryCustomerDetails');
        $firstName = ArrayVal($personalInfo, 'firstName');
        $lastName = ArrayVal($personalInfo, 'lastName');
        $result['RenterName'] = beautifulName($firstName . " " . $lastName);

        if (!$this->ParsePastIts && ArrayVal($result, 'DropoffDatetime') < strtotime('now')) {
            $this->logger->info('Skipping car in the past');

            return [];
        }

        $this->logger->debug('Parsed Rental:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function parseTrainJson($id, $status)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $result['Kind'] = 'T';
        $result['TripCategory'] = TRIP_CATEGORY_TRAIN;
        $this->http->GetURL(sprintf('https://supportz.makemytrip.com/api/railbookingdetail/%s', $id));
        $data = $this->http->JsonLog(null, 3, true);

        $info = $data['train'][0]['pnrDetails'][0] ?? null;
        $trainDetails = $info['segmentInfo'][0] ?? null;

        if (!$info || !$trainDetails) {
            $this->sendNotification('check trains // MI');

            return [];
        }
        // RecordLocator
        $result['RecordLocator'] = ArrayVal($info, 'pnrNo') ?: $id;
        $this->logger->info(sprintf('[%s] Parse Train #%s', $this->itinLength, $result['RecordLocator']), ['Header' => 3]);
        $this->logger->notice("Status -> {$status}");

        // ReservationDate
        $reservationDate = ArrayVal($data, 'bookingDateTime');

        if ($reservationDate && strtotime($reservationDate)) {
            $result['ReservationDate'] = strtotime($reservationDate);
        }

        // Passengers
        $passengers = [];
        $passengerDetails = ArrayVal($trainDetails, 'passenger', []);

        foreach ($passengerDetails as $pas) {
            $passengers[] = beautifulName($pas['paxFirstName'] . " " . $pas['paxLastName']);
        }
        $result['Passengers'] = array_unique($passengers);
        // TotalCharge
        $result['TotalCharge'] = $this->ArrayVal($data, ['train', 0, 'paymentDetails', 'sellingPrice']);
        // Currency
        $result['Currency'] = $this->ArrayVal($data, ['train', 0, 'paymentDetails', 'paymentDetails', 0, 'currencyCode']);
        // Status
        $bookingStatus = ArrayVal($data, 'bookingStatus', '');

        if ($this->http->FindPreg('/Cancelled/i', false, $bookingStatus) || $status == 'CANCELLED') {
            $result['Status'] = 'Cancelled';
            $result['Cancelled'] = true;
        }

        // Bus Trip Segments
        $result['TripSegments'] = [];
        $segment = [
            'FlightNumber' => ArrayVal($trainDetails, 'trainNo'),
        ];

        $segment['Cabin'] = ArrayVal($info, 'travelClass');

        // DepName
        $segment['DepName'] = ArrayVal($trainDetails, 'sourceStationName');
        $segment['DepCode'] = TRIP_CODE_UNKNOWN;
        // ArrName
        $segment['ArrName'] = ArrayVal($trainDetails, 'destinationStationName');
        $segment['ArrCode'] = TRIP_CODE_UNKNOWN;
        // DepDate
        $depDate = ArrayVal($trainDetails, 'boardingDate');
        $time1 = ArrayVal($trainDetails, 'departureTime');

        if ($depDate && $time1) {
            $segment['DepDate'] = strtotime($depDate . " " . $time1);
        }
        // ArrDate
        $arrDate = ArrayVal($trainDetails, 'destinationArrivalDate');
        $time2 = ArrayVal($trainDetails, 'arrivalTime');

        if ($arrDate && $time2) {
            $segment['ArrDate'] = strtotime($arrDate . " " . $time2);
        }
        // Duration
        $segment['Duration'] = ArrayVal($trainDetails, 'journeyDuration');
        // TraveledMiles
        $segment['TraveledMiles'] = ArrayVal($trainDetails, 'distanceInKm');
        // Type
        $segment['Type'] = ArrayVal($trainDetails, 'trainName') . " / " . ArrayVal($trainDetails, 'trainNo');

        $result['TripSegments'] = [$segment];

        if (!$this->ParsePastIts && ArrayVal($segment, 'ArrDate') < strtotime('now')) {
            $this->logger->info('Skipping bus in the past');

            return [];
        }

        $this->logger->debug('Parsed Train:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
//            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);

            $selenium->useChromePuppeteer();

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 5;
            $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if (isset($fingerprint)) {
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://supportz.makemytrip.com/Mima/BookingSummary/");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('window.stop();');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@placeholder="Enter Mobile Number"]'), 7);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (
            NoSuchDriverException
            | Facebook\WebDriver\Exception\InvalidSessionIdException
            | Facebook\WebDriver\Exception\UnrecognizedExceptionException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }
}
