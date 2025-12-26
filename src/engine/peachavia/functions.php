<?php

class TAccountCheckerPeachavia extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://myaccount.flypeach.com/login?lang=en');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

//        if (!$this->sendSensorData()) {
//            return $this->checkErrors();
//        }

        $this->getCookiesFromSelenium();

        if ($message = $this->http->FindPreg("/(Currently, we are carrying out a regular system maintenance and some of the procedures are temporarily suspended.)<br>/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $data = [
            'email'          => $this->AccountFields['Login'],
            'password'       => $this->AccountFields['Pass'],
            'sustainSession' => "true",
        ];

        $this->http->RetryCount = 0;
        $headers = [
            "Accept"        => "application/json, text/plain, */*",
            "Content-type"  => "application/json",
            "x-revision"    => "4c85aee00962e738ce9f55e337eb351d4bbb25fd",
        ];
        $this->http->PostURL('https://myaccount.flypeach.com/api/session?lang=en', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        // code=204 - auth success
        if ($this->http->Response['code'] == 204 && $this->loginSuccessful()) {
            return true;
        }
        //email && password validation
        $json = $this->http->JSonLog(null, 5, true);
        $key = $json['validationErrors'][0]['key'] ?? null;
        $message = $json['validationErrors'][0]['messages'][0] ?? null;

        if (
            $key == 'email'
            && $message == 'Email address or passwords are incorrect.'
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $json = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty('Name', beautifulName($json->firstname . ' ' . $json->lastname));

        // peachpoints
        $this->http->RetryCount = 0;
        $this->http->GetUrl('https://myaccount.flypeach.com/api/peachpoints/summary?lang=en');
        $response = $this->http->JsonLog();

        if ($this->http->Response['body'] == '{"error":"System error occurred."}') {
            $this->SetBalanceNA();

            return;
        }

        $balances = [];

        foreach ($response->available as $available) {
            $balances[$available->currencyCode]['Balance'] = $available->amount;
            $balances[$available->currencyCode]['Balance'] = $available->amount;
        }

        foreach ($response->expireThisMonth as $expireThisMonth) {
            $balances[$expireThisMonth->currencyCode]['expireThisMonth'] = $expireThisMonth->amount;
        }

        foreach ($response->expireNextMonth as $expireNextMonth) {
            $balances[$expireNextMonth->currencyCode]['expireNextMonth'] = $expireNextMonth->amount;
        }

        foreach ($balances as $key => $value) {
            $expData = [];

            if ($value['expireThisMonth'] > 0) {
                $expData = [
                    "ExpiringBalance" => $value['expireNextMonth'],
                    "ExpirationDate"  => strtotime("last day of this month"),
                ];
            } elseif ($value['expireNextMonth'] > 0) {
                $expData = [
                    "ExpiringBalance" => $value['expireNextMonth'],
                    "ExpirationDate"  => strtotime("last day of next month"),
                ];
            }

            $this->AddSubAccount([
                "Code"        => "BalanceOf{$key}",
                "DisplayName" => "Active Peach Point of {$key}",
                "Balance"     => $value['Balance'],
                'Currency'    => $key,
            ] + $expData);

            if (count($this->Properties['SubAccounts']) > 0) {
                $this->SetBalanceNA();
            }
        }// foreach ($balances as $key => $value)
    }

    public function ParseItineraries()
    {
        $this->http->GetUrl('https://myaccount.flypeach.com/api/reservations?lang=en&lastDepartDateFrom=2021-05-19&status=ACTIVE&order=asc&page=1&perPage=5');

        if ($this->http->Response['body'] == '{"currentPage":1,"totalPages":0,"perPage":5,"total":0,"reservations":[]}') {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }
        $response = $this->http->JsonLog();

        if (!isset($response->reservations)) {
            return [];
        }

        foreach ($response->reservations as $reservation) {
            $this->http->GetURL("https://myaccount.flypeach.com/api/reservations/{$reservation->pnr}/edit?lang=en");
            $editResponse = $this->http->JsonLog();

            $headers = [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'Origin'       => 'https://manage.flypeach.com',
            ];
            $data = [
                'clientId'           => '29222978338458925777954627887837', // hard code js
                'clientSecret'       => 'hEP9YY4hH8xAWVQ4ZC54CbdSUtX9nqUbh6xEkJ9kxAqeDqWeGE92jCpzpmHJvzxW', // hard code js
                'confirmationNumber' => $reservation->pnr,
                'grantType'          => 'reservation',
                'lastName'           => $editResponse->params->lastName,
                'scope'              => '*',
            ];
            $this->http->PostURL("https://api.flypeach.com/manage/api/mmb/auth/login", json_encode($data), $headers);
            $response = $this->http->JsonLog();

            $headers = [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Referer'       => 'https://manage.flypeach.com/en/manage/itinerary-confirm',
                'Origin'        => 'https://manage.flypeach.com',
                'Authorization' => "Bearer {$response->accessToken}",
            ];
            $this->http->PostURL("https://api.flypeach.com/manage/api/mmb/reservation/{$reservation->pnr}", [],
                $headers);
            $data = $this->http->JsonLog(null, 2);
            $this->parseItineraryFlight($data);
        }

        return [];
    }

    private function parseItineraryFlight($data)
    {
        $f = $this->itinerariesMaster->createFlight();
        $this->logger->info(sprintf('[%s] Parse Flight #%s', $this->currentItin++, $data->confirmationNumber),
            ['Header' => 3]);
        $f->general()->confirmation($data->confirmationNumber);
        $f->general()->date2($data->bookDate);

        foreach ($data->persons as $person) {
            $f->general()->traveller(beautifulName("$person->firstName $person->lastName"));
        }

        foreach ($data->logicalFlights as $segment) {
            $s = $f->addSegment();
            $s->departure()->code($segment->origin);
            $s->departure()->date2(preg_replace('/\+\d+:00$/', '', $segment->departureTime));
            $s->arrival()->code($segment->destination);
            $s->arrival()->date2(preg_replace('/\+\d+:00$/', '', $segment->arrivaltime));
            $s->extra()->bookingCode($segment->fareClassCode);

            foreach ($data->physicalFlights as $physical) {
                if ($segment->logicalFlightId == $physical->logicalFlightId) {
                    if (!in_array($physical->flightNumber, ['572', '023', '025', '291'])) {
                        $this->sendNotification('check airlineName // MI');
                    }
                    $s->extra()->aircraft($physical->aircraftType);
                    $s->airline()->number($physical->flightNumber);
                    $s->airline()->name(in_array($physical->flightNumber, ['572', '023', '025', '291']) ? 'MM' : null);
                    $s->departure()->terminal($segment->fromTerminal ?? null, false, true);
                    $s->arrival()->terminal($segment->toTerminal ?? null, false, true);

                    break;
                }
            }

            foreach ($data->seats as $seats) {
                if ($segment->logicalFlightId == $seats->logicalFlightId) {
                    if (isset($seats->rowNumber)) {
                        $s->extra()->seat($seats->rowNumber . $seats->seat);
                    }

                    break;
                }
            }

            if (count($data->payments) > 1) {
                $this->sendNotification('check price // MI');
            }

            foreach ($data->payments as $payments) {
                $f->price()->total($payments->paymentAmount);
                $f->price()->currency($data->reservationCurrency);
            }
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://myaccount.flypeach.com/api/user?lang=en&disableCache=false', [], 20);
        $this->http->RetryCount = 2;
        $json = $this->http->JsonLog();

        if (isset($json->email) && strtolower($this->AccountFields['Login']) == strtolower($json->email)) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data url not found");

            return false;
        }

        $this->http->setCookie("_abck", "547A8929E337BA40F4352BECF2A397AF~0~YAAQhfnerSc57N2AAQAAF3gG5gdD32VFoECxd/Ou+mQXv08NUTYX0aXohhpA3FkD58JtXGHVWzP+SNkuAovJ+eKkV/B8BVikcyODhnQnrn7FrUr0eg/jH5rrwlvx4rs7dNLkQsVRlKND8Ke1rMTZjXa3vx9K+Q3kQrOOG7lEjpS7t9jsztHrto3tDl2+oCkcDUz6OJ6srYi/ue0qZlNMJD7Fg5ajnHXVlePGrQCxm6xFpEXo/YpSIcB6YTQ7bOUSW+HcDSspTrMkxh+iKjbhvAdckmCt6SIYZLfFmk91Dt0bxDJIbSjBEuiJs57jnGkHoNi0pw2K/xgqebKCxApWzUICyzEs//lFkSuyVdNwLpsgOrL8cIUqXVTXGEdMsqX1GNCXZySKbInsuuXGTc239PHlNOUeUAxoWWn3~-1~||-1||~-1", ".flypeach.com");

        $this->http->NormalizeURL($sensorPostUrl);
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];

        $sensorData = [
            "7a74G7m23Vrp0o5c9244161.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:100.0) Gecko/20100101 Firefox/100.0,uaend,11059,20100101,en-US,Gecko,5,0,0,0,406747,6624043,1536,871,1536,960,1536,433,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6082,0.04940164824,826563312021.5,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://myaccount.flypeach.com/login?lang=en-1,2,-94,-115,1,32,32,0,0,0,0,0,0,1653126624043,-999999,17684,0,0,2947,0,0,2,0,0,547A8929E337BA40F4352BECF2A397AF~-1~YAAQhfnerSA57N2AAQAAaW8G5gfeBBBRDSByH9OMmM26UbKKkHzOb2EoWFSg+wDt5743FUySpFhu3VAP6vUQqmZpr9ZGIMqGu+UCAELyquF4w/MJswqRIiGtzK37Bz3D+s+M3YvIbSN3VN1Q+1miJLkaxUQypPeJtI68KjnaGOQECK1VNyJ6SwKV4fr+BqMqNawpS3Ua/eJV0/ixWFpkn+6VH7o2tE3VDfHXOce/pfuyXkUsfUVm5fgK7D6tZgeJDuws2PxZH8T+gttCsBjqSs8nIadc66n1RH72wyiYENVipLK2ew3db1Xx9gsclP0sgCZtCGPU/+qTiFv83mMnqUnUW/6PsKhk7BJDaq3ESCzGYc/WOtnD652YCjwQK+gKa/PkML8lzG0awEijJA==~-1~-1~-1,36733,-1,-1,26067385,PiZtE,57481,102,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,496802916-1,2,-94,-118,82982-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
        ];

        $secondSensorData = [
            "7a74G7m23Vrp0o5c9244161.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:100.0) Gecko/20100101 Firefox/100.0,uaend,11059,20100101,en-US,Gecko,5,0,0,0,406747,6624043,1536,871,1536,960,1536,433,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6082,0.269161991134,826563312021.5,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://myaccount.flypeach.com/login?lang=en-1,2,-94,-115,1,32,32,0,0,0,0,526,0,1653126624043,11,17684,0,0,2947,0,0,526,0,0,547A8929E337BA40F4352BECF2A397AF~-1~YAAQhfnerSA57N2AAQAAaW8G5gfeBBBRDSByH9OMmM26UbKKkHzOb2EoWFSg+wDt5743FUySpFhu3VAP6vUQqmZpr9ZGIMqGu+UCAELyquF4w/MJswqRIiGtzK37Bz3D+s+M3YvIbSN3VN1Q+1miJLkaxUQypPeJtI68KjnaGOQECK1VNyJ6SwKV4fr+BqMqNawpS3Ua/eJV0/ixWFpkn+6VH7o2tE3VDfHXOce/pfuyXkUsfUVm5fgK7D6tZgeJDuws2PxZH8T+gttCsBjqSs8nIadc66n1RH72wyiYENVipLK2ew3db1Xx9gsclP0sgCZtCGPU/+qTiFv83mMnqUnUW/6PsKhk7BJDaq3ESCzGYc/WOtnD652YCjwQK+gKa/PkML8lzG0awEijJA==~-1~-1~-1,36733,355,-2048681253,26067385,PiZtE,102569,42,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,25610054;2077731666;dis;,7;true;true;true;-300;true;30;30;true;false;1-1,2,-94,-80,5320-1,2,-94,-116,496802916-1,2,-94,-118,84499-1,2,-94,-129,,,0,,,,0-1,2,-94,-121,;0;6;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = 0; // array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $data = [
            "sensor_data" => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        $data = [
            "sensor_data" => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
        sleep(1);

        return true;
    }

    private function getCookiesFromSelenium($url = "https://myaccount.flypeach.com/login?lang=en", $retry = false)
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL($url);
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }
            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "email"]'), 5);
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return null;
    }
}
