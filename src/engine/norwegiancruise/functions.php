<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerNorwegiancruise extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://www.ncl.com/shorex/my-account";
    private $converter;
    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $proxy = $this->http->getLiveProxy("https://www.ncl.com/shorex/login", 5, null, [503]);
        $this->http->SetProxy($proxy);
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->setMaxRedirects(10);
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $this->getCookiesFromSelenium();

        $data = [
            'username'   => $this->AccountFields['Login'],
            'password'   => $this->AccountFields['Pass'],
            'rememberMe' => true,
        ];
        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Content-Type'    => 'application/json',
            'Referer'         => 'https://www.ncl.com/shorex/login',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.ncl.com/shorex/login/api/v1/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        if ($this->http->FindPreg('/Not Authenticated/')) {
            throw new CheckException('Incorrect username or password.', ACCOUNT_INVALID_PASSWORD);
        }

        $data = $this->http->JsonLog(null, 3, true);

        if (isset($data['client']['username'])) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $response = $this->http->JsonLog(null, 3);
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            // Bug AccountsID: 3653487, 4683727, 1875735
            $clientId = $response->client->clientId ?? null;
            $this->logger->debug("clientId: {$clientId}");

            if (
                $this->http->currentUrl() == "https://www.ncl.com/shorex/{$clientId}/my-account"
                && $this->http->FindSingleNode('//div[
                        contains(text(), "Looks Like This Page Took a Permanent Vacation.")
                        or contains(text(), "Lo sentimos. Visita alguna de nuestras otras páginas populares:")
                    ]')
                && $this->http->Response['code'] == 403
            ) {
                $this->SetBalanceNA();
                // Name
                $this->SetProperty("Name", beautifulName($response->client->firstName . " " . $response->client->lastName));
                // Member Number
                $this->SetProperty("MemberNumber", $response->client->clientId);

                return;
            }
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@class = 'data-name']")));
        // Latitudes Rewards #
        $this->SetProperty("MemberNumber", $this->http->FindSingleNode("//span[@class = 'data-number' and contains(text(), '#')]", null, true, "/#\s*:\s*([^<]+)/ims"));
        // Latitudes Tier Level
        $this->SetProperty("Tier", $this->http->FindSingleNode("//span[contains(text(), 'Tier')]/strong"));

        if (isset($this->Properties['MemberNumber'])) {
            $this->http->GetURL("https://www.ncl.com/shorex/{$this->Properties['MemberNumber']}/latitudes");
        }
        // Name
        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'name']")));
        }
        // Status
        if (!isset($this->Properties['Tier'])) {
            $this->SetProperty("Tier", $this->http->FindSingleNode("//span[@class = 'status']"));
        }
        // Points to Next Level
        $this->SetProperty("PointsToNextLevel", $this->http->FindSingleNode("//div[contains(@class, 'visible-desktops') and contains(., 'You are')]", null, true, "/You are ([\-\d\,\.]+) points? away from/ims"));
        // Balance
        if (!$this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Balance:')]/following-sibling::span[1]"))) {
            if (!$this->SetBalance($this->http->FindSingleNode("//span[contains(@id, 'prfl-rewards-balance')]", null, true, "/([\d\.\,]+)\s*Points/ims"))) {
                if (isset($this->Properties['Name'], $this->Properties['MemberNumber'])) {
                    $this->SetBalanceNA();
                }

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    //# Would you like to enroll in our Latitudes Rewards Program?
                    if ($this->http->FindSingleNode("//p[contains(text(), 'Would you like to enroll in our Latitudes Rewards Program?')]")) {
                        $this->SetWarning("You are not a member of Latitudes Rewards Program");
                    }
                    //# Your Latitudes Rewards enrollment is being processed
                    elseif ($message = $this->http->FindSingleNode("//p[contains(text(), 'Your Latitudes Rewards enrollment is being processed')]")) {
                        $this->SetWarning($message);
                    }
                    // hard code (AccountID: 3827211, 3701612, 3367166)
                    elseif ($this->http->Response['code'] == 500) {
                        $NCLUserData = urldecode($this->http->getCookieByName("NCLUserData"));
                        $this->logger->debug(var_export($NCLUserData, true), ['pre' => true]);
                        $NCLUserData = $this->http->JsonLog($NCLUserData, true, true);
                        // Name
                        $this->SetProperty("Name", beautifulName(ArrayVal($NCLUserData, 'full_name')));
                        // Member Number
                        $this->SetProperty("MemberNumber", ArrayVal($NCLUserData, 'user_id'));

                        if (!empty($this->Properties['Name']) && isset($this->Properties['MemberNumber'])) {
                            $this->SetBalanceNA();
                        }
                    }// elseif ($this->AccountFields['Login'] == 'justinparton')
                }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
            }
        }// if(!$this->SetBalance())
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.ncl.com/login';

        return $arg;
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->converter = new CruiseSegmentsConverter();

        return $this->ParseItinerariesJson();
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        return count($this->http->FindNodes('//a[contains(@href, "logout")]/@href')) > 0;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# System under maintenance
        if ($message = $this->http->FindSingleNode("(//div[@id = 'maintenancebgcontainer'])[1]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# System Under Maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'System Under Maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# System Under Maintenance
        if (
            $this->http->FindSingleNode("//img[@src = 'sites/all/themes/norway/images/maintenance.jpg']/@src")
            || $this->http->FindSingleNode("//img[@src = 'https://www.ncl.com/maintenance/ncl_logo.png']/@src")
            || $this->http->currentUrl() == 'https://www.ncl.com/maintenance'
        ) {
            throw new CheckException("Sorry for the inconvenience. Our system is undergoing maintenance.", ACCOUNT_PROVIDER_ERROR);
        }
        // 503 - Service Unavailable
        if ($this->http->FindSingleNode("//p[contains(text(), '503 - Service Unavailable')]")
            || $this->http->FindSingleNode("//p[contains(text(), '502 - Bad Gateway')]")
            || $this->http->FindSingleNode("//p[contains(text(), '500 - Internal Server Error')]")
            || $this->http->FindSingleNode("//span[contains(text(), '503 - Service Unavailable.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function ParseItinerariesJson()
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $this->http->GetURL('https://www.ncl.com/shorex/my-account');
        // var clientSailings = {"userSailings":
        if ($this->http->FindPreg('/var\s*clientSailings\s*=\s*\{\"userSailings\":\[\]\}\;/')) {
            return $this->noItinerariesArr();
        }

        $clientSailings = $this->http->JsonLog($this->http->FindPreg("/var\s*clientSailings\s*=\s*(.+?});/"), 2);
        $userSailings = $clientSailings->userSailings ?? [];
        $this->logger->info(sprintf("Found %s itinerary nodes", count($userSailings)));

        foreach ($userSailings as $sailing) {
            $this->ParseItineraryJson($sailing);
        }

        return $result;
    }

    private function ParseItineraryJson($sailing)
    {
        $this->logger->notice(__METHOD__);

        if (isset($sailing->itinerary) && property_exists($sailing->itinerary, 'portSequenceRAW') && $sailing->itinerary->portSequenceRAW === null) {
            $this->logger->error('Empty "itinerary". Perhaps a reservation is far in the future');

            return;
        }
        $c = $this->itinerariesMaster->add()->cruise();
        $confNo = null;

        if (isset($sailing->sailing->reservationId)) {
            $confNo = $sailing->sailing->reservationId;
        }

        if (!$confNo) {
            $this->logger->error('RecordLocator not found');

            return;
        }
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $confNo), ['Header' => 3]);
        $c->general()->confirmation($confNo);
        $c->setShip($sailing->sailing->shipName);
        $c->setDescription(sprintf('%s', !empty($sailing->itineraryTitle) ? $sailing->itineraryTitle : $sailing->selectedItineraryTitle), true, false);
        $c->setRoom($sailing->sailing->cabinNumber);

        foreach ($sailing->itinerary->portSequenceRAW as $i => $portRaw) {
            $port = $this->http->FindPreg('/\|\w\|(.+?)\|/', false, $portRaw);
            $this->logger->debug(sprintf('Name %s', $port));

            if ($this->http->FindPreg('/\|S\|/', false, $portRaw)) {
                $this->logger->info('At Sea');

                continue;
            }
            $port = $this->http->FindPreg('/\|\w\|(.+?)\|[A-Z]{3}/', false, $portRaw);
            $code = $this->http->FindPreg('/\|\w\|.+?\|([A-Z]{3})/', false, $portRaw);

            if (empty($port) || empty($code)) {
                $this->logger->error('Empty Name and Code');

                continue;
            }
            // ||",
            // ||9:00 PM",
            // |9:00 AM|6:00 PM",
            $date = strtotime($this->http->FindPreg('/(\d{4}-\d+-\d+)\|/', false, $portRaw));
            $arrTime = $depTime = null;
            // |9:00 AM|6:00 PM",
            if (preg_match('/\|(\d{1,2}:\d{1,2}\s*[AP]M)\|(\d{1,2}:\d{1,2}\s*[AP]M)$/', $portRaw, $m)) {
                $arrTime = $m[1];
                $depTime = $m[2];
                $this->logger->debug("$arrTime:$depTime");
            }
            // ||9:00 PM",
            elseif (preg_match('/\|\|(\d{1,2}:\d{1,2}\s*[AP]M)$/', $portRaw, $m)) {
                $depTime = $m[1];
            }
            // |7:00 AM|",
            elseif (preg_match('/\|(\d{1,2}:\d{1,2}\s*[AP]M)\|$/', $portRaw, $m)) {
                $arrTime = $m[1];
            }
            $s = $c->addSegment();
            $s->setName($port);
            $s->setCode($code);

            if (isset($depTime)) {
                $s->setAboard(strtotime($depTime, $date));
            }

            if (isset($arrTime)) {
                $s->setAshore(strtotime($arrTime, $date));
            }
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($c->toArray(), true), ['pre' => true]);
    }

    private function ParseItineraryHtml($name)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $result['Kind'] = 'T';
        $result['TripCategory'] = TRIP_CATEGORY_CRUISE;

        $result['RecordLocator'] = $this->http->FindSingleNode('//div[strong[contains(text(), "Reservation #:")]]/following-sibling::div[1]');
        $result['ShipName'] = $this->http->FindSingleNode("//div[a/strong[contains(text(), 'Ship:')]]/following-sibling::div[1]");
        $result['CruiseName'] = preg_replace('/\s+\&\s+/i', ' and ', $name);
        $result['Deck'] = $this->http->FindSingleNode("//div[a/strong[contains(text(), 'Stateroom:')]]/following-sibling::div[1]/text()[1]");
        $result['RoomNumber'] = $this->http->FindSingleNode("//div[a/strong[contains(text(), 'Stateroom:')]]/following-sibling::div[1]/text()[2]", null, true, '/(\d+)/');

        $result['Passengers'] = array_map(function ($elem) {
            return beautifulName($elem);
        }, $this->http->FindNodes('//div[@class = "col-guests"]/h3'));

        $nodes = $this->http->XPath->query('//div[contains(@class, "overview-table")]/table//tr[td]');
        $dates = $this->http->XPath->query("//ul[@class = 'itinerary-list']/li[@ng-click=\"itineraryOverviewOpen = false\"]");
        $filteredNodes = [];
        $this->logger->debug('Itinerary nodes:');

        foreach ($nodes as $node) {
            $this->logger->debug($node->nodeValue);

            if ($this->http->FindPreg('/At Sea/i', false, $node->nodeValue)) {
                continue;
            }

            if (!$this->http->FindPreg('/\d+:\d+/i', false, $node->nodeValue)) {
                continue;
            }
            $filteredNodes[] = $node;
        }
        $filteredDates = [];

        foreach ($dates as $date) {
            if ($this->http->FindPreg('/At Sea/i', false, $date->nodeValue)) {
                continue;
            }
            $filteredDates[] = $date;
        }
        $this->logger->debug(sprintf("Total %s / %s (nodes / dates) segments were found", sizeof($filteredNodes), sizeof($filteredDates)));
        $cruise = [];

        if (sizeof($filteredNodes) == sizeof($filteredDates)) {
            $yearStart = $this->http->FindSingleNode('//*[@id = "myReservationsDropDown"]', null, true, '/,\s+(\d{4})/i');

            for ($i = 0; $i < sizeof($filteredNodes); $i++) {
                $node = $filteredNodes[$i];
                $segment = [];

                $port = trim($this->http->FindSingleNode('td[2]', $node));

                if ($port !== 'At Sea') {
                    $segment['Port'] = $port;

                    if ($date = $this->http->FindSingleNode('.//span[@class = "date"]', $filteredDates[$i])) {
                        $yearPresent = $this->http->FindPreg('/\b(\d{4})\b/i', false, $date);

                        if (!$yearPresent) {
                            $date = sprintf('%s %s', $date, $yearStart);
                        }
                        $dateUnix = strtotime($date);

                        if ($time = $this->http->FindSingleNode('td[3]', $node, true, '/([^\-]+)/')) {
                            $segment['ArrDate'] = strtotime($time, $dateUnix);
                        }

                        if ($time = $this->http->FindSingleNode('td[4]', $node, true, '/([^\-]+)/')) {
                            $segment['DepDate'] = strtotime($time, $dateUnix);
                        }
                    }// if ($date = $this->http->FindSingleNode('.//span[@class = "date"]', $dates->item($i)))
                    $cruise[] = $segment;
                }// if ($port != 'At Sea')
            }// for ($i=0; $i < $nodes->length; $i++)
        }

        $result['TripSegments'] = $this->converter->Convert($cruise);

        return $result;
    }

    private function sendStatistic($success, $retry, $key)
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("norwegiancruise sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "retry"           => $retry,
            "attempt"         => $this->attempt,
            "sensor_data_key" => $key,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }

    private function sendSensorData($sensorDataUrl)
    {
        $this->logger->notice(__METHOD__);

        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9263491.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399601,3583326,1536,871,1536,960,1536,442,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6018,0.300782638150,812041791663,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1519,1519,0;1,0,0,0,1538,1538,0;-1,2,-94,-102,0,-1,0,0,1519,1519,0;1,0,0,0,1538,1538,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ncl.com/shorex/login-1,2,-94,-115,1,32,32,0,0,0,0,5,0,1624083583326,-999999,17373,0,0,2895,0,0,7,0,0,52B3B1601A6EE63E3E40C98914525B47~-1~YAAQBqw4F7HBXxp6AQAAjc/sIgbbx4UXSScrB7pfsySOuMy/GIjL0pAjkcRmBAcE5vMbs3c0jVxWs7cV43OrY9xOndTtoVUGth1G3K/FZzp53hZLmcV7HeyKJ7RenJZOu6ws5UablR8XctSYbF3G3kB2EV8IHDUQNvXdrcYKtMWOIaiAtptW3j6J3me7WRdkB2goL+aV6sUZewjLChF3rluUxaHK82A16lH2jTjzzj4IZLm2tJlfs1RWI+vvQPuSWuC/yktZ0/TmaTxrw09TSeoboK2oeKJgl1XL8Hl0VUxGWNUHZBAf3uETQYqI1Y8cTMSqbMd77CHWhI3txBG/GQTdyvfAXSvS14bdFj8dIWZBNkdydh3USrE1TroIr0uZJ3lB5atWorc=~-1~-1~-1,36714,-1,-1,26067385,PiZtE,104983,75,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,96749316-1,2,-94,-118,85015-1,2,-94,-129,-1,2,-94,-121,;13;-1;0",
            // 1
            "7a74G7m23Vrp0o5c9263491.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.106 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399601,3498073,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.722275788361,812041749036,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,2900,520,0;1,0,0,0,2553,2553,0;-1,2,-94,-102,0,0,0,0,2900,520,0;1,0,0,0,2553,2553,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.vitaminshoppe.com/s/myAccount/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,3,0,1624083498072,-999999,17373,0,0,2895,0,0,5,0,0,31DA4C30DA81101FF2A94A98FF96AD2A~-1~YAAQFmAZuO9ZrRl6AQAAsoTrIgYrcAWBgZPSlEnBqI6tkHSME1bmlSpNe2m5qvMNovEDKkjCt8I9SVZrtsGFOdP3/9hgP2hTSnoMrH/WcXgqJBJwIOXgmYw8L/lVnprr6fnC28bce/dRm5qXxhOV6GInLiL9udl0x09W7/7v9yyqxcvv47s1uJxVPxTejGdZmUnmqg4LaiNon9hPE5CdfcGQegmK4k+fxcnIN0mEebNnEPLNarB2zzSUqDmRkfj988JOQUo8xT/rWa1X0mRSByvVXG50A07yP4HBT44OIrb+/5EOR1xgqRK6NckE3Ba8SgvtY90YdEX2syWLOBXmhTCqVsR2L13oBtjltUmMXyhZD0FVGEpXvytRYCUGVuMw8h/yCUFsz6PI9U1dLFveQcU=~-1~-1~-1,37969,-1,-1,30261693,PiZtE,31794,52,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,94447677-1,2,-94,-118,90663-1,2,-94,-129,-1,2,-94,-121,;7;-1;0",
            // 2
            "7a74G7m23Vrp0o5c9263491.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.106 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399601,3149716,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.10574486152,812041574857.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1519,1519,0;1,0,0,0,1538,1538,0;-1,2,-94,-102,0,-1,0,0,1519,1519,0;1,0,0,0,1538,1538,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ncl.com/shorex/login-1,2,-94,-115,1,32,32,0,0,0,0,4,0,1624083149715,-999999,17373,0,0,2895,0,0,10,0,0,AD7B62DEB81F6606418C556D0AACB0AB~-1~YAAQ1etwaMqq2BN6AQAAJzTmIgYliuBBlrf8Sr35pOALohVmyWp4ZCfvrQ7o2B+aqivtssGICmaVrEzrRR4Q3TWWcLQ9S3vbQfJjYibx57PxLr61RJzbZ+ljNh3cwLJH95ijNFQWVAmTO7dkPbf86LYNIalKnyHvLJmSR/FfDAu9KRi0n5pOmWhFbjsYgBklsMBB1UUAPKwqXjlm7Tih/gNScZwxSLnX6kOf5w4fPwWJ9bz0GHdAiSYZ+s5D2K3VxWwDdL++AheVgsz4pUOhHTewQ2izIVVkHGHwOq/lYIgSeGQQHIXDtk9IKWxlWl6/YZKthmSUI/PQWpYrrFj/k86iO+ZeT0Qk20bSuaYUWlGipiVE520AqUaOVeSDmGzaa8jqFxHaVw==~-1~-1~-1,37071,-1,-1,30261693,PiZtE,104961,31,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,47245695-1,2,-94,-118,88208-1,2,-94,-129,-1,2,-94,-121,;20;-1;0",
        ];

        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9263491.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,399601,3583326,1536,871,1536,960,1536,442,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6018,0.941058950470,812041791663,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1519,1519,0;1,0,0,0,1538,1538,0;-1,2,-94,-102,0,-1,0,0,1519,1519,0;1,0,0,0,1538,1538,0;-1,-1,1,0,-1,317,0;-1,-1,1,0,-1,317,0;-1,-1,1,0,-1,219,0;-1,-1,1,0,-1,220,0;-1,-1,1,0,-1,231,0;-1,-1,0,0,-1,216,0;-1,-1,1,0,-1,118,0;-1,-1,1,0,-1,207,0;-1,-1,1,0,-1,214,0;-1,-1,1,0,-1,360,0;-1,-1,1,0,-1,427,0;-1,-1,1,0,-1,418,0;-1,-1,1,0,-1,421,0;-1,-1,1,0,-1,408,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ncl.com/shorex/login-1,2,-94,-115,1,32,32,0,0,0,0,1061,0,1624083583326,6,17373,0,0,2895,0,0,1065,0,0,52B3B1601A6EE63E3E40C98914525B47~0~YAAQBqw4F5HCXxp6AQAAaNPsIgbrURmndvWtfm6HCs38rmY2h4h5YlERp+M+YYHDavef7+yoLsOaBTLWD70FPSvdX8rpZlvSfkQ05quVDSuKEfRTHxQ2p4XPwqGcirtNCwWcIRP2Sn7WKhFlO2gQ58rTnRROp7V2RpgBq+EhwRg7tKySB8aGWHRqY23ZH8O4aUAVP2IMGx00gLSOtsHzsCyH+Uhn1ViDRDgPLP8oedHDll0VtwG3z+s8WCB1hISpi8OMAQLaBiAwn3bec0I+56Fq/rU3MryV41PD1ok/Gqw180lwt7qqCvIQjxYQeysHBYrijSkmBqB8Rlc/QGkRGSYiyC2BwoWbhxRxruIONg1upfYbPGqHnPcvArUFRGYdUWo78ncQhqApyqbM3vKJB0yocCGNCQ==~-1~||1-BuINeFJxex-1-10-1000-2||~-1,40342,992,995879336,26067385,PiZtE,47220,74,0,-1-1,2,-94,-106,8,1-1,2,-94,-119,0,0,0,0,0,0,200,0,0,0,0,400,400,0,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.444c9cfef525a8,0.0ab246a2b4c9a8,0.683cff6554ef68,0.5de98e321f713,0.d76a2db53e8a9,0.70cdc7556c2e5,0.79933b450b4ba8,0.54b97ef5789bf8,0.d4b5b14aa36f5,0.6dd1b848fc19b8;1,0,1,0,0,0,5,3,8,0;0,1,3,1,2,3,7,3,20,0;52B3B1601A6EE63E3E40C98914525B47,1624083583326,BuINeFJxex,52B3B1601A6EE63E3E40C98914525B471624083583326BuINeFJxex,1,1,0.444c9cfef525a8,52B3B1601A6EE63E3E40C98914525B471624083583326BuINeFJxex10.444c9cfef525a8,206,124,12,120,60,93,139,99,97,5,112,147,91,22,67,190,113,254,47,214,76,181,141,45,91,129,105,73,65,180,77,254,502,0,1624083584387;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,96749316-1,2,-94,-118,136633-1,2,-94,-129,3020cbfb5ef19092b59d4e995bbcff6b8733ed57a1acd8a071295ae21dff7101,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;5;19;0",
            // 1
            "7a74G7m23Vrp0o5c9263491.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.106 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399601,3498073,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.649138304324,812041749036,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,2900,520,0;1,0,0,0,2553,2553,0;-1,2,-94,-102,0,0,0,0,1365,1044,0;0,0,0,0,1813,630,0;0,0,0,0,1813,-1,0;0,0,0,0,2900,520,0;1,0,0,0,2553,2553,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.vitaminshoppe.com/s/myAccount/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,1510,0,1624083498072,26,17373,0,0,2895,0,0,1511,0,0,31DA4C30DA81101FF2A94A98FF96AD2A~0~YAAQFmAZuPJZrRl6AQAA+YXrIgbOPqV2NaSJq2ZwuSzQgxEsC3Ap644Cg1d2gGQDGo3Ac0eexkEnIqfAWWEL/dHnUztjxUTxC+mvhDfVr+LF2GSKSKijoS5/sK+PspmLk8cjixE1jiMulhc2do1nvVScxN9qBKnNp59LHt5pWzGbfcVZ10wwiHqlku8Gj5QLDxqnQYr2VfsXX6Au79oigHmL9+iykG/kPknmVoqBEQKT59wFkOm8GQU1vuk12LYU8o/E6Ng6GdeWCLbJ1upIrC8pMyvtUhPgd5dk/EYeJUMrHZtGAdJLgqNcY8EFKfq72ecE9qErJywzuBs0mykO76lagq9UcfYUa30sExZq+7eyTD5guUyuJ43v+1JVj7KpPUhEG2cEY4loT10v3P1Vl6jvhs3cszVylE6VNv4QRw==~-1~||1-fQSZaxVwFb-1-10-1000-2||~-1,41725,595,-1533856669,30261693,PiZtE,44499,113,0,-1-1,2,-94,-106,8,1-1,2,-94,-119,60,40,60,80,60,60,40,20,0,0,0,0,20,180,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.45e988ed4ca5c,0.b455a93a6c6e6,0.50109b0e444f5,0.eca401735db93,0.8afb52b9028bf,0.c96544d8d464a,0.52da16a610d8e,0.18509ab9096ab,0.aaf48ab60749b,0.d4192b31cb9d4;1,0,1,1,1,0,5,0,0,3;0,0,0,0,0,0,14,0,0,7;31DA4C30DA81101FF2A94A98FF96AD2A,1624083498072,fQSZaxVwFb,31DA4C30DA81101FF2A94A98FF96AD2A1624083498072fQSZaxVwFb,1,1,0.45e988ed4ca5c,31DA4C30DA81101FF2A94A98FF96AD2A1624083498072fQSZaxVwFb10.45e988ed4ca5c,231,26,178,206,239,173,42,208,166,225,70,69,197,100,238,35,12,9,255,195,174,19,146,211,144,53,184,87,148,38,113,183,323,0,1624083499582;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,94447677-1,2,-94,-118,132915-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0f7edf978cda71a82a5af4b78a8df770924ac922ce0e334cc52acd51016343e3,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;4;11;0",
            // 2
            "7a74G7m23Vrp0o5c9263491.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.106 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,399601,2640340,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8971,0.0176967508,812041320170,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,2900,520,0;1,0,0,0,2553,2553,0;-1,2,-94,-102,0,0,0,0,1365,1044,0;0,0,0,0,1813,630,0;0,0,0,0,1813,-1,0;0,0,0,0,2900,520,0;1,0,0,0,2553,2553,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.vitaminshoppe.com/s/myAccount/login.jsp-1,2,-94,-115,1,32,32,0,0,0,0,1671,0,1624082640340,28,17373,0,0,2895,0,0,1672,0,0,B2BE5E8611B178EEF7756412A31AC326~0~YAAQFmAZuKsmrRl6AQAABHDeIgbyoMQ1vcAZaLC9GHkiXFl+wf1f2VNyYx97ROJEtiD6k3uk7y3wZg5lvZfMAC3XlVld99KkbwfI0bfuv4UT7ebOGdrN2mqFUeTbJBT5V1mgFgWBgQhgTbklYgbdX/yT0RsuYU8vnfVDrcmQY/GKsukGhIRVcBNrhedYAUnmO2nji9hJdS+sqqPwZcCAL2LB12Oxk4udRo+jAeCugNFzJbrxHsef/jijQwk9y+HZNI0gVHK/9Z1j/uDxAH/yA9dHrGKkVl3x0ay5SI6UQZsVmDaltPmZz519nx8MWsc47X8I3YzYmdylU81fsO06Ggnms1NVp24rtTFeHaUSUq/Q/yYLMMvRFIsK7yDW0+2kmF7oW7Uefcss6mMFeT4Rph1UWIii6cQTILJafhL/6g==~-1~||1-WtUXUENvMP-1-10-1000-2||~-1,41485,306,-809149378,30261693,PiZtE,77754,72,0,-1-1,2,-94,-106,8,1-1,2,-94,-119,40,40,40,40,60,60,40,40,40,0,0,0,20,240,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.d52e2da29a1db,0.5c9db986fc106,0.bb7573226dad1,0.662322e9e3584,0.5ebdd81dd781d,0.4ddd874627f86,0.f84eddf69ebca,0.cbcc348cd267e,0.d6b9457c0283,0.b3c4abb7882c9;0,3,0,0,2,5,7,1,1,4;0,7,4,0,1,21,15,3,4,5;B2BE5E8611B178EEF7756412A31AC326,1624082640340,WtUXUENvMP,B2BE5E8611B178EEF7756412A31AC3261624082640340WtUXUENvMP,1,1,0.d52e2da29a1db,B2BE5E8611B178EEF7756412A31AC3261624082640340WtUXUENvMP10.d52e2da29a1db,222,123,174,63,118,139,72,131,202,152,19,201,103,210,67,165,11,199,205,227,156,11,230,2,57,122,137,160,229,247,202,157,447,0,1624082642011;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,198025440-1,2,-94,-118,132634-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0f7edf978cda71a82a5af4b78a8df770924ac922ce0e334cc52acd51016343e3,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;4;12;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $this->http->RetryCount = 0;
        $data = [
            'sensor_data' => $sensorData[$key],
        ];
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);
        $data = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
            $selenium->http->driver->dontSaveStateOnStop();
            $selenium->http->saveScreenshots = true;
            $req = \AwardWallet\Common\Selenium\FingerprintRequest::chrome();
            $req->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            /** @var \AwardWallet\Common\Entity\Fingerprint $fp */
            $fp = $this->services->get(\AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([$req]);

            if ($fp !== null) {
                $selenium->seleniumOptions->fingerprint = $fp->getFingerprint();
                $this->http->setUserAgent($fp->getUseragent());
            }
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.ncl.com/shorex/login');
            $selenium->waitForElement(WebDriverBy::id('input_username'), 5);
            $this->savePageToLogs($selenium);

            foreach ($selenium->driver->manage()->getCookies() as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            $selenium->http->cleanup();
        }
    }
}
