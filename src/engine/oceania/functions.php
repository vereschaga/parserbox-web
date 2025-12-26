<?php

class TAccountCheckerOceania extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.oceaniacruises.com/myaccount/oceania-club/', [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Login must be correct email
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Invalid e-mail and/or password', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.oceaniacruises.com/login/?ReturnUrl=%2fexperience%2foceania-club%2f');

        if (!$this->http->ParseForm(null, '//form[@data-js="signin-form"]')) {
            return $this->checkErrors();
        }

        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#") ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");

            return false;
        }

        $this->http->NormalizeURL($sensorPostUrl);
        $this->sendSensorData($sensorPostUrl);

        $data = [
            "username"   => $this->AccountFields['Login'],
            "password"   => $this->AccountFields['Pass'],
            "rememberMe" => "true",
        ];
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json",
            "Origin"           => "https://www.oceaniacruises.com",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.oceaniacruises.com/api/UserMembership/Login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $this->http->JsonLog();

        if ($this->http->FindPreg("/^\"Success\"/")) {
            $this->logger->debug('>>> go to /myaccount/oceania-club');
            $this->http->GetURL('https://www.oceaniacruises.com/myaccount/oceania-club/');
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // Sorry, we don’t recognize that email and password combination. Please try again or recover your password.
        if ($this->http->Response['code'] == 401 && $this->http->FindSingleNode('//h2[contains(text(), "401 - Unauthorized: Access is denied due to invalid credentials.")]')) {
            throw new CheckException("Sorry, we don’t recognize that email and password combination. Please try again or recover your password.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Get user data
        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'gzip, deflate, br',
        ];
        $result = $this->http->PostURL('https://www.oceaniacruises.com/api/guest/getpastpassenger', '', $headers);
        $passenger = $this->http->JsonLog(null);

        if (!$result || !isset($passenger->firstName) || !isset($passenger->lastName) || !isset($passenger->credits)) {
            return;
        }

        // Balance - You have ... Cruise Credit(s)
        $this->SetBalance($passenger->credits);
        // Name
        $this->SetProperty('Name', beautifulName($passenger->firstName . ' ' . $passenger->lastName));
        // Status
        $this->SetProperty('Status', $passenger->currentLevel->name ?? null);
        // Account Number - Loyalty Number: ...
        $this->SetProperty('AccountNumber', $passenger->societyNumber);
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"           => "application/json, text/plain, */*",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->GetURL('https://www.oceaniacruises.com/api/bookedcruises/getbookedcruises/', $headers);
        $result = $this->http->JsonLog(null, 0);

        if ($this->http->FindPreg("/^\{\"results\":\[\],\"ships\":/")) {
            return $this->noItinerariesArr();
        }

        $cruises = $result->results ?? [];
        $ships = $result->ships ?? [];
        $this->logger->debug("Total " . count($cruises) . " reservations were found");

        if (count($cruises) > 0) {
            $this->sendNotification('check cruise // MI');
        }

        foreach ($cruises as $cruise) {
            $this->parseItinerary($cruise, $ships);
        }

        return [];
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//div[@title = 'My Account: Oceania Club']/@title")) {
            return true;
        }

        return false;
    }

    private function sendSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data url not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $abck = [
            // 0
            "4CA15D7F1B0AD5546A70404C2904DD43~0~YAAQl2rcF8DA0MmSAQAAkijV0gzKN7tLiO3NONmc4klZUch3zKFf8dmluy1/QmO8UN/ZpZo1FreglDhSn1Y1ln340y5K/uVaGZw1Ttky3lBXkF9KgaDJrZe11zFUWRCRRi5hvTQfgRd6V49+4RqVsWBo/8ioBV1aEJStVRc1RX52eFKVGHCQkKHic0I9wu3N/n3mT1DxtbiY4yIn7s8nhoS2h2pc1rmCE+X4S4cQ3930cCGiGIFXnSiT9PhwPoJ8ur0J5t/qlxnAoWMxtdFvjX9ds3lRsu7X8x9P5hlW7+oCZGNg5Wf4fdLOeehGE67CsTwMDYaiQ1tNZZ8EWyjdubFOp6l4rhAm+JuxBlQ/3jbLiSBgaE1TbddbOLhRUfboMDrGMBV7I+eVrfJjHHxXKgS64B+hORvtqNiGsk11RWjj7NEN1536qXaahLQ51eDkmhEO5MY5i5/yanChO8WmCFqS37j706uz6MVmPWw=~-1~||0||~-1",
            // 1
            "AACD43599D6781CFDA7E70FE63EEA073~0~YAAQkWrcF5p/Cr+SAQAAIEfU0gyvck8zKO3UGC8qDbFYUM59jA5y0UjVJz3iYeLq9ErhE4JQKU9rmCNvejCGXQHU2KbVbBDV82fTALDGSevw1I6MSoEtR4V64YFTfCnF4Uxy7dF/9P6c2na3V8MS/UpoQaBnz3bdrrJmQK7dnBp7vy8kHzPhVdJmLaSds+HKBICT/nuEtkqgBmAZOV8FYiNiiZc3OJSl4L+070vRmE+Biy0/aB3XloIxc8g1ga5EDwlKS7NFuGbMJyIbSM3iwcy1iXVzXraTb46H70GvOqKeXp42e9zKDyZ+O6mglb6hS5bPLfybMORdYZA+48RyfTYNPMFyDVcOh6xTxFnrJoadeqOUpQHTfes3JwP4sqTm1VF8xgdjBv0ETadGpEwXRIwo/cNxd4c/0H2Lt4CEPFfDl/744Ub84EeVYcs/NBKHVYrruyIcdA/8Zaeg1KAZW5Tk7eQyR9wgs5z0aD2J~-1~||0||~-1",
            // 2
            "FA7373E435857D59F943EF27C94447DB~0~YAAQl2rcF62X0MmSAQAA+uPU0gx9ulc/5Sw8vIPsz7dPXZ9wH/PnDG9GdKhcS3gZIq5mCkTBIBcNHkUj07bYf3MjV8K6nB6GTKDTOtM6ocOy6e+tAa+oke3bEqcljaQSiHw4gMj52rGTGbp7yv1FvOfGe4Ml9w1mOHlTB/u6rr95UTyFHw8IQaVFhwfzPxbW/JTi/ONj57kPSAFs8/VLQnmz+si4zrrqQzLE1fV6kegAIeorU/umBZLfkbwbTjoPWK68HAiG3FaArqY8FcfX7Gw0krIgKbUCV6mZ+cu8KTK0UJfbhSpCQFBXR6gcKj3UpEvXQBXZI7amp9YQNI+tWhdlXYzEgpwMjEqnplBC5Cf8iVlBQ6/6dRZzq3akGkkT30X7eDG0feGw7RmPc9C/ZshvolObSnJs1oAOIgy31aoAxuvd6VUgkz1QR/Xln9CL/5iptlsv/SOfTA/H6QRyoHSaOw+RQibLWJfmyhA=~-1~||0||~-1",
        ];
        $this->http->setCookie("_abck", $abck[array_rand($abck)]); // todo: sensor_data workaround

        /*
        $sensorData = "7a74G7m23Vrp0o5c9112521.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395095,219360,1536,872,1536,960,1536,407,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.373706941186,802885109679.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,963,864,0;1,-1,0,0,918,883,0;0,-1,0,0,973,1230,0;0,-1,0,0,923,864,0;1,-1,0,0,957,883,0;0,-1,0,0,971,937,0;0,-1,0,0,981,821,0;0,-1,0,0,982,545,0;0,-1,0,0,968,1230,0;0,-1,0,0,977,1155,0;0,-1,0,0,911,864,0;1,-1,0,0,953,883,0;0,-1,0,0,976,1230,0;0,-1,0,0,963,937,0;0,-1,0,0,967,821,0;0,-1,0,0,968,545,0;0,-1,0,0,958,1155,0;0,0,0,0,910,1230,0;0,0,0,0,966,1230,0;0,0,0,0,960,1948,0;1,-1,0,0,957,883,0;1,-1,0,0,965,1601,0;-1,2,-94,-102,0,-1,0,0,963,864,0;1,-1,0,0,918,883,0;0,-1,0,0,973,1230,0;0,-1,0,0,923,864,0;1,-1,0,0,957,883,0;0,-1,0,0,971,937,0;0,-1,0,0,981,821,0;0,-1,0,0,982,545,0;0,-1,0,0,968,1230,0;0,-1,0,0,977,1155,0;0,-1,0,0,911,864,0;1,-1,0,0,953,883,0;0,-1,0,0,976,1230,0;0,-1,0,0,963,937,0;0,-1,0,0,967,821,0;0,-1,0,0,968,545,0;0,-1,0,0,958,1155,0;0,0,0,0,910,1230,0;0,0,0,0,966,1230,0;0,0,0,0,960,1948,0;1,-1,0,0,957,883,0;1,-1,0,0,965,1601,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.oceaniacruises.com/login/?ReturnUrl=%2fexperience%2foceania-club%2f-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1605770219359,-999999,17178,0,0,2863,0,0,6,0,0,AB2E542E3786EEAE104A0D6E09B1BA54~-1~YAAQkr4oFzu/v951AQAAoQpd3wS/fB2U8QSosdlgeMLleSW9aQz6N8sMzf18k/ndqgPTXfvov7NfT5rhdMfgSTllaOE7O1/6Svfnmr9VnxuhT7BkN+r+euVB9qpPxXbxXHmLPlKAc7ndy+hL/9OWFEuN/kiAddXmqr7YvKoD5XpnLTCzf70lOJaq24qYVM3aqzoAoy86oslxNslABDL6Stl4xAvD935TJCxWP45VnduEkcdArxHc0SLJ2NiY+A0D4Zyz7R3LC23FHyXAGdPIVzqS3z/Kog3uunsEiJsTzGNELf7Q3eFXk6DfHiZjkmD/iFY=~-1~-1~-1,30709,-1,-1,30261693,PiZtE,97359,70-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,658083-1,2,-94,-118,123398-1,2,-94,-129,-1,2,-94,-121,;8;-1;0";
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData]));
        $this->http->JsonLog();
        sleep(1);

        $sensorData = "7a74G7m23Vrp0o5c9112521.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395095,219360,1536,872,1536,960,1536,407,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.236057000118,802885109679.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,963,864,0;1,-1,0,0,918,883,0;0,-1,0,0,973,1230,0;0,-1,0,0,923,864,0;1,-1,0,0,957,883,0;0,-1,0,0,971,937,0;0,-1,0,0,981,821,0;0,-1,0,0,982,545,0;0,-1,0,0,968,1230,0;0,-1,0,0,977,1155,0;0,-1,0,0,911,864,0;1,-1,0,0,953,883,0;0,-1,0,0,976,1230,0;0,-1,0,0,963,937,0;0,-1,0,0,967,821,0;0,-1,0,0,968,545,0;0,-1,0,0,958,1155,0;0,0,0,0,910,1230,0;0,0,0,0,966,1230,0;0,0,0,0,960,1948,0;1,-1,0,0,957,883,0;1,-1,0,0,965,1601,0;-1,2,-94,-102,0,-1,0,0,963,864,0;1,-1,0,0,918,883,0;0,-1,0,0,973,1230,0;0,-1,0,0,923,864,0;1,-1,0,0,957,883,0;0,-1,0,0,971,937,0;0,-1,0,0,981,821,0;0,0,0,0,-1,-1,0;0,0,0,0,-1,-1,0;0,-1,0,0,982,545,0;0,-1,0,0,968,1230,0;0,-1,0,0,977,1155,0;0,-1,0,0,911,864,0;1,-1,0,0,953,883,0;0,-1,0,0,976,1230,0;0,0,0,0,-1,-1,0;0,-1,0,0,963,937,0;0,-1,0,0,967,821,0;0,0,0,0,-1,-1,0;0,0,0,0,-1,-1,0;0,-1,0,0,968,545,0;0,-1,0,0,958,1155,0;0,0,0,0,910,1230,0;0,0,0,0,966,1230,0;0,0,0,0,960,1948,0;1,-1,0,0,957,883,0;1,-1,0,0,965,1601,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.oceaniacruises.com/login/?ReturnUrl=%2fexperience%2foceania-club%2f-1,2,-94,-115,1,32,32,0,0,0,0,653,0,1605770219359,67,17178,0,0,2863,0,0,654,0,0,AB2E542E3786EEAE104A0D6E09B1BA54~-1~YAAQkr4oF0a/v951AQAA9xBd3wSvW/OyM3QPWff2QVBwObYLnUw15m1Uo1j1Mx8l8SRUjUUNKYx5/VSp9UWo/wkSAqDiMQM6gN/ZjqOKKJCSe0Hh9ZinhsmTFwr8e+ct01h8HTiivhLbR62Cwfafjoh6O0QmZ9VWKu1Ez3mqcCNKTD7PhmQ+/jQe2rXhkXtk6u95vu/WCgHt2mc3upt7D5huVpBgr7HsmSgP0tB6YEnl1YcNzrGyRYdVucFPLqSoECHrZnIuBNY9URy5FBLHo+MJNL9pOINmm6dg67DzUrC3ctbBi8FIQjyd6THBcpcu0HXYlMkNCCUBQe1pbP1PjA==~-1~-1~-1,31943,403,78802206,30261693,PiZtE,54012,90-1,2,-94,-106,9,1-1,2,-94,-119,28,31,31,33,50,54,18,9,7,6,6,1268,1384,334,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,658083-1,2,-94,-118,131653-1,2,-94,-129,7c9a29d5cbb17baaa0421ccc938945af3ff70a3ce17f5f18f0b2777292ff90aa,2,0,,,,0-1,2,-94,-121,;15;11;0";
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData]));
        $this->http->JsonLog();
        sleep(1);
        */

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('h1[contains(text(), "Service Unavailable - Zero size object")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseItinerary($cruise, $ships)
    {
        $this->http->JsonLog(json_encode($cruise), 1);

        if (empty($cruise->cruiseDetailsUrl)) {
            $this->logger->error('not found cruiseDetailsUrl');

            return;
        }
        $cruise->cruiseDetailsUrl = str_replace('ø', '%C3%B8', $cruise->cruiseDetailsUrl);
        $this->http->NormalizeURL($cruise->cruiseDetailsUrl);
        $this->http->GetURL($cruise->cruiseDetailsUrl);

        $bookNumber = $cruise->bookingNumber ?? null;
        $this->logger->info("Parse itinerary #{$bookNumber}", ['Header' => 3]);
        $c = $this->itinerariesMaster->add()->cruise();

        // Travellers, Account Numbers
        $travellers = $accNumbers = [];

        foreach ($cruise->guestDetails as $guest) {
            $travellers[] = beautifulName($guest->fullName);

            if (!empty($guest->oceaniaClubNumber)) {
                $accNumbers[] = $guest->oceaniaClubNumber;
            }
        }

        // General
        $c->general()
            ->confirmation($bookNumber, 'Booking #', true)
            ->status($cruise->status)
            ->date2($cruise->depositDueDate)
            ->travellers($travellers, true);

        // Program
        $c->program()
            ->accounts($accNumbers, false);

        // Details
        $shipCode = '';

        foreach ($ships as $ship) {
            if ($ship->name == $cruise->shipName) {
                $shipCode = $ship->id;

                break;
            }
        }
        $c->details()
            ->number($cruise->voyageId)
            ->deck(substr($cruise->stateroomNumber, 0, 1)) // Deck G, suite: GTY || Deck 8, Suite: 8057
            ->shipCode($shipCode)
            ->ship($cruise->shipName)
            ->room($cruise->stateroomNumber)
            ->roomClass($cruise->stateroomDescription);

        if (!empty($cruise->voyageName)) {
            $c->details()->description($cruise->voyageName);
        }

        // Segments
        /*if (strpos($cruise->portToPort, ' to ')) {
            $ports = explode(' to ', $cruise->portToPort);
            $c->addSegment()->setName(trim($ports[0]))
                ->parseAboard($cruise->startDay . ' ' . $cruise->startMonth . ' ' . $cruise->startYear);
            $c->addSegment()->setName(trim($ports[1]))
                ->parseAshore($cruise->endDay . ' ' . $cruise->endMonth . ' ' . $cruise->endYear);
        }*/
        $startYear = $cruise->startYear;
        $endYear = $cruise->endYear;
        $items = $this->http->XPath->query("//h3[contains(.,'Itinerary')]/following-sibling::div[1]//table[@class='table table-sm table-responsive itinerary table-oc']/tbody//tr");
        $this->logger->debug("Total " . count($items) . " segments were found");

        $currentDate = -1;

        foreach ($items as $item) {
            // Dec 21 Sat
            $date = preg_replace('/(\w+) (\d+) \w+/', '$2 $1', $this->http->FindSingleNode(".//td[@class='itin-date']", $item));
            $starTime = $this->http->FindSingleNode(".//td[@class='itin-start-time']", $item, false, '/\d+.+/');
            $endTime = $this->http->FindSingleNode(".//td[@class='itin-end-time']", $item, false, '/\d+.+/');
            $name = $this->http->FindSingleNode(".//td[@class='itin-port-name']/a", $item);

            $s = $c->addSegment();
            $s->setName($name);
            //$this->logger->debug("Ashore $date $startYear, $starTime");
            $ashore = strtotime("$date $startYear, $starTime");

            if ($ashore < $currentDate) {
                $s->setAshore(strtotime("$date $endYear, $endTime"));
            } else {
                $s->setAshore($ashore);
            }

            if (!empty($endTime)) {
                //$this->logger->debug("Aboard $date, $endTime");
                $aboard = strtotime("$date, $endTime", $s->getAshore());
                $s->setAboard($aboard);
            }
            $currentDate = $s->getAshore();
        }

        $this->logger->debug("Parsed Itinerary:");
        $this->logger->debug(var_export($c->toArray(), true), ["pre" => true]);
    }
}
