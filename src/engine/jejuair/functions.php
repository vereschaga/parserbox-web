<?php

class TAccountCheckerJejuair extends TAccountChecker
{
    private const REWARDS_PAGER_URL = "https://www.jejuair.net/en/memberBenefit/refreshPoint/main.do";

    private $headersJS = [
        "Content-Type" => "application/x-www-form-urlencoded; charset=UTF-8",
        "Accept"       => "application/json, text/javascript, */*; q=0.01",
        //"X-Requested-With" => "XMLHttpRequest"
    ];

    private $ffp_no;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'], $properties['Currency'])) {
            switch ($properties['Currency']) {
                case 'GBP':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");

                case 'EUR':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");

                case 'USD':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");

                case 'KRW':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&#8361;%0.2f");
            }// switch ($properties['Currency'])
        }// if (isset($properties['SubAccountCode'], $properties['Currency']))

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGER_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.jejuair.net/en/member/auth/login.do");
        $this->sendSensorData();
        $this->http->GetURL("https://www.jejuair.net/en/member/auth/login.do");

        if (!$this->http->ParseForm("frm")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("apiRequestType", "1");
        $this->http->SetInputValue("userId", $this->AccountFields['Login']);
        $this->http->SetInputValue("userPwd", $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            $this->http->GetURL(self::REWARDS_PAGER_URL);

            return true;
        }

        if ($message = $this->http->FindSingleNode('//form[@id = "frm"]//p[@class = "input__error" and @id = "pwErrorArea"]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, '등록되지 않은 아이디이거나 아이디, 비밀번호가 일치하지 않습니다.')
                || $message == 'Invalid ID or ID and password do not match'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//div[contains(text(), "Password Change Notification")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "This account is not registered.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Inactive account information
        if ($message = $this->http->FindSingleNode('//div[contains(@class, "page-sub-title") and contains(., "inactive right now.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $data = base64_decode($this->http->FindPreg("/var data =\"([^\"]+)/"));
        $FName = $this->http->FindPreg("#engFirstName\"\s*:\s*\"(.+?)\",#", false, $data);
        $LName = $this->http->FindPreg("#engLastName\"\s*:\s*\"(.+?)\",#", false, $data);
        // Name
        $this->SetProperty("Name", beautifulName($FName . ' ' . $LName));
        $this->ffp_no = $this->http->FindPreg("#ffpNo\"\s*:\s*\"(.+?)\",#", false, $data);
        // Membership Number
        $this->SetProperty("MembershipNumber", $this->ffp_no);
        // My Level
        $label = $this->http->FindPreg("#membergrade\"\s*:\s*\"(.+?)\",#", false, $data);

        switch ($label) {
            case 'S':
                $this->SetProperty("Level", "Silver");

                break;

            case 'U':
                $this->SetProperty("Level", "Silver+");

                break;

            case 'G':
                $this->SetProperty("Level", "Gold");

                break;

            case 'V':
                $this->SetProperty("Level", "Vip");

                break;

            default:
                if ($this->ffp_no) {
                    $this->sendNotification("Unknown status: {$label}");
                }
        }

        $expNodes = $this->http->XPath->query("//div[@id = 'refreshPointPopUserPointExpireList']//tbody//tr");
        $this->logger->debug("Total {$expNodes->length} exp nodes were found");

        foreach ($expNodes as $expNode) {
            $expBalance = $this->http->FindSingleNode('td[1]', $expNode);
            $date = $this->http->FindSingleNode('td[2]', $expNode);

            if ($expBalance > 0 && (!isset($exp) || $exp > strtotime(preg_replace("#^(\d{4})(\d{2})(\d{2})$#", '$1-$2-$3', $date)))) {
                $exp = strtotime(preg_replace("#^(\d{4})(\d{2})(\d{2})$#", '$1-$2-$3', $date));
                $this->SetProperty("ExpiringBalance", str_replace('P', '', $expBalance));
                $this->SetExpirationDate($exp);
            }
        }

        if (!$this->ffp_no) {
            return;
        }

        $data = [
            "ffpNo" => $this->ffp_no,
        ];
        $this->http->PostURL("https://www.jejuair.net/en/memberBenefit/refreshPoint/getMyGradeInq.json", $data, $this->headersJS);
        $this->http->JsonLog(null, 3, true);
        //You need 100,000 P more to be GOLD    -> 100,000 P
        $pointsForNextLevel = $this->http->FindPreg("#nextgradepoint\"\s*:\s*\"(.+?)\",#");
        //20 more times to be GOLD level   ->   20
        $flightsForNextLevel = $this->http->FindPreg("#nextgradeDomesticBoardcnt\"\s*:\s*\"(.+?)\",#");
        //Valid Period 2017.09.13 ~ 2020.09.12  ->   2020.09.12
        $this->SetProperty("LevelValidTill", preg_replace("#^(\d{4})(\d{2})(\d{2})$#", '$1-$2-$3', $this->http->FindPreg("#gradedateto\"\s*:\s*\"(.+?)\",#")));

        $data = [
            "ffpNo"          => $this->ffp_no,
            "offerType"      => "JPNT",
            "apiRequestType" => "BQ",
        ];
        $this->http->PostURL("https://www.jejuair.net/api/common/biz/setUserPoint.json", $data, $this->headersJS);
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data');
        $resultData = ArrayVal($data, 'resultData');

        if (is_array($resultData)) {
            // Balance - Available Points
            $this->SetBalance(ArrayVal($resultData, "balancePoint"));
            //Level Points
            $this->SetProperty("LevelPoints", $accuredPoints = ArrayVal($resultData, "accPoint_3"));
            //My Flight
            $this->SetProperty("Flights", $flownWithUs = ArrayVal($resultData, "accBoardCnt_3"));
            //Next Level
            $this->SetProperty("PointsToNextLevel", $pointsForNextLevel - $accuredPoints);
            $this->SetProperty("FlightsToNextLevel", $flightsForNextLevel - $flownWithUs);
            //Points To Expire
            $expBalance = ArrayVal($resultData, "totalPreExpPoint");

            if (!isset($this->Properties['ExpiringBalance'])) {
                $this->SetProperty("ExpiringBalance", $expBalance);
            }
        }

        /*
        $this->logger->info('Coupons', ['Header' => 3]);

        $data = [
            "couponNum"     => "",
            "iEndPage"      => "15", //???? TODO
            "iStartPage"    => "0",
            "language"      => "EN",
            "sMemberNumber" => $this->AccountFields['Login'],
            "useFlag"       => "N",
        ];
        $this->http->PostURL("https://www.jejuair.net/jejuair/com/jeju/ibe/mypage/jjclub/coupon_point.do", $data, $this->headersJS);
        $response = $this->http->JsonLog();

        $this->SetProperty("CombineSubAccounts", false);

        if (isset($response->totalcnt2) && $response->totalcnt2 > 0) {
            $this->logger->debug("Total {$response->totalcnt2} coupons were found");

            if ($response->totalcnt2 > 15) {
                $this->sendNotification("jejuair - refs #15473. coupons count more than 15", "awardwallet");
            }
            $coupons = [];

            if (isset($response->couponOfList) && is_array($response->couponOfList)) {
                $coupons = $response->couponOfList;
            }

            foreach ($coupons as $coupon) {
                $coupon = json_decode(json_encode($coupon), true);
                //Add only not used
                if (isset($coupon["PromotionCPNNo"]) && isset($coupon["PromoName"]) && isset($coupon["ToDate"]) && isset($coupon["CouponAmt"]) && isset($coupon["CouponCy"]) && isset($coupon["UseFlag"]) && $coupon["UseFlag"] == "N") {
                    $exp = strtotime(preg_replace("#^(\d{4})(\d{2})(\d{2})$#", '$1-$2-$3', $coupon["ToDate"]));

                    if ($exp) {
                        $subAccount = [
                            'Code'           => 'jejuair' . $coupon["PromotionCPNNo"],
                            'DisplayName'    => 'Coupon №' . $coupon["PromotionCPNNo"],
                            'ExpirationDate' => $exp,
                            'Balance'        => $coupon["CouponAmt"],
                            'Currency'       => $coupon["CouponCy"],
                            'TravelTill'     => preg_replace("#^(\d{4})(\d{2})(\d{2})$#", '$1-$2-$3', $coupon["ToFltDate"]),
                        ];
                        $this->AddSubAccount($subAccount);
                    } else {
                        $this->sendNotification("jejuair - refs #15473. coupons ExpirationDate changed format", "awardwallet");
                    }
                }// if (isset($coupon["PromotionCPNNo"]) && isset($coupon["PromoName"]) && isset($coupon["ToDate"]) && isset($coupon["CouponAmt"]) && isset($coupon["CouponCy"]))
            }// foreach ($coupons as $coupon)
        }
        */
    }

    public function ParseItineraries()
    {
        $this->http->FilterHTML = false;
        $result = [];
        $startTimer = $this->getTime();

        $this->logger->debug('[Parse itineraries]');

        $data = [
            "resvListReq" => '{"orderType":"R","startIndex":1,"listCount":10,"customerNumber":"' . $this->ffp_no . '","cultureCode":"ko-KR"}',
        ];
        $this->http->PostURL("https://www.jejuair.net/en/ibe/mypage/getReservationList.json", $data, $this->headersJS, 100);
        $response = $this->http->JsonLog();
        //{"Result":{"message":"","data":[],"code":"0000"}}
        //{"code":"9998"}
        $code = $response->code ?? null;

        if ((isset($response->data) && !is_null($code)) && $code == "9998") {
            $this->logger->error("Error 9998 from reservationListData.do");
            $this->sendNotification("refs #15473. Error: 9998 // RR");

            sleep(5);
            $this->http->PostURL("https://www.jejuair.net/en/ibe/mypage/getReservationList.json", $data, $this->headersJS, 100);
            $response = $this->http->JsonLog();
            $code = $response->code ?? null;
        }

        /*
        if (!(isset($response->data->bookingList) && isset($response->Result->code) && $response->Result->code == "0000")) {
            $this->logger->error("Error in format Json from reservationListData.do");
            $this->sendNotification("refs #15473. Might be format Json from reservationListData.do was changed");
            $this->getTime($startTimer);

            return $result;
        }
        */

        $data = $response->data->bookingList;

        if (count($data) == 0) {
            $this->logger->debug('There is no result of inquiry');

            if ($this->http->FindPreg('/^\s*\{"code":"0000","message":"SUCCESS","data":\{"bookingList":\[\]\}\}\s*$/')) {
                return $this->noItinerariesArr();
            }

            return $result;
        }

        foreach ($data as $item) {
            $confNo = $item->recordLocator;
            $this->logger->info('Parse Itinerary #' . $confNo, ['Header' => 3]);

            if (isset($item->info->status) && $item->info->status == 'Closed') {
                $f = $this->itinerariesMaster->add()->flight();
                $f->general()->confirmation($confNo);
                $f->general()->cancelled();
                $f->general()->status($item->info->status);
                $f->general()->date2($this->http->FindPreg('/^(.+?)T/', false, $item->info->bookedDate));

                continue;
            }
            $flightDate = $this->http->FindPreg('/^(.+?)T/', false, $item->sortFlightDate);
            $data = [
                'resvDetailReq' => '{"recordLocator":"' . $confNo . '","cultureCode":"ko-KR","depDate":"' . $flightDate . '","orderSearchType":"P"}',
                'domIntType'    => '',
                'rpnr'          => $confNo,
            ];
            $headers = [
            ];
            $this->http->PostURL('https://www.jejuair.net/en/ibe/mypage/viewReservationDetail.do', $data, $headers);
            $response = $this->http->FindPreg('/if\((\{"message":"\w+","data":\{"closedBooking":"\w*","journeys":\[.+?)\.data\.code != null/');
            $response = $this->http->JsonLog($response);
            $this->parseItinerary($response);
        }

        $this->getTime($startTimer);

        return $result;
    }

    private function parseItinerary($data)
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->add()->flight();
        $f->general()->confirmation($data->data->recordLocator);
        $f->general()->status($data->data->info->status);
        $f->general()->date2($this->http->FindPreg('/^(.+?)T/', false, $data->data->info->bookedDate));

        foreach ($data->data->passengers as $passenger) {
            $f->general()->traveller("{$passenger->name->first} {$passenger->name->last}");
        }

        foreach ($data->data->journeys as $journey) {
            foreach ($journey->segments as $segment) {
                $s = $f->addSegment();
                $s->airline()->name($segment->identifier->carrierCode);
                $s->airline()->number($segment->identifier->identifier);

                foreach ($segment->legs as $leg) {
                    $s->extra()->aircraft($leg->legInfo->equipmentType);

                    $s->departure()->name($leg->designator->originName);
                    $s->departure()->code($leg->designator->origin);
                    $s->departure()->date2($leg->designator->departure);

                    if (isset($leg->legInfo->departureTerminal)) {
                        $s->departure()->terminal($leg->legInfo->departureTerminal);
                    }

                    $s->arrival()->name($leg->designator->destinationName);
                    $s->arrival()->code($leg->designator->destination);
                    $s->arrival()->date2($leg->designator->arrival);

                    if (isset($leg->legInfo->arrivalTerminal)) {
                        $s->arrival()->terminal($leg->legInfo->arrivalTerminal);
                    }
                }
            }
        }

        $f->price()->total($data->data->breakdown->totalAmount);
        $f->price()->currency($data->data->currencyCode);

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//button[@id = "logout"] | //a[@id = "logout"]')) {
            return true;
        }

        return false;
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            "7a74G7m23Vrp0o5c9263271.7-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:87.0) Gecko/20100101 Firefox/87.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,399582,5987608,1536,824,1536,864,1536,239,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5565,0.506319949253,812002993804,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1555,113,0;0,-1,0,0,796,796,0;1,-1,0,0,461,461,0;0,-1,0,0,661,661,0;0,-1,0,0,1120,1120,0;-1,2,-94,-102,0,-1,0,0,1555,113,0;0,-1,0,0,796,796,0;1,-1,0,0,461,461,0;0,-1,0,0,661,661,0;0,-1,0,0,1120,1120,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.jejuair.net/jejuair/en/com/jeju/ibe/goLogin.do-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1624005987608,-999999,17373,0,0,2895,0,0,6,0,0,3BA8E4FE27998D6766ACCCA564B997EC~-1~YAAQNWcQAgQ5BOt5AQAA8r9MHgaJnMwwyjdgeS2jajQouUaxOJRD3JsIi2j8LEHIsVJXob6o6s0Ljk4BUXSUeGkGMGHemC+xjEND1Rq3F/RNhgXbMcG8q2q1ZpBSkIV8DrPRF/BfG4b1/H/8XR7EHNZL/+H3S+/KTXHPhaCuY4+iLe16zv3Y+XXt2O20irE8u4a3gjvpHgf7UT2JhVSwsPlAeUaomfjC6iB1X7SdfOydtzQXG5PNP3/JPs+bWANfLKjdOwLGeaA9vZ5+Yom2jRODFcsqVZG0TLYD+4SNlpXIA9tnVTk9kCypai0Ekrgh/wKVoWwQmCmEtVU6IUqtqhS+o5VOmV11+CHtm11cGHzeew+v/uwFilVmUCzN4ZPIQQ1zpZNRo711XQyt~-1~-1~-1,36327,-1,-1,25543097,PiZtE,29517,54,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,1454988339-1,2,-94,-118,92749-1,2,-94,-129,-1,2,-94,-121,;10;-1;0",

            //            "7a74G7m23Vrp0o5c9152091.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:73.0) Gecko/20100101 Firefox/73.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,389823,1572653,1536,880,1536,960,1536,423,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6004,0.06636587733,792170786326.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1555,113,0;0,-1,0,0,796,796,0;1,-1,0,0,461,461,0;0,-1,0,0,661,661,0;0,-1,0,0,1120,1120,0;-1,2,-94,-102,0,-1,0,0,1555,113,0;0,-1,0,0,796,796,0;1,-1,0,0,461,461,0;0,-1,0,0,661,661,0;0,-1,0,0,1120,1120,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.jejuair.net/jejuair/en/com/jeju/ibe/goLogin.do?pageType=001&target_url=%2Fen%2Frefresh%2FgoRefreshPoint.do%3FsystemType%3DIBE-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1584341572653,-999999,16948,0,0,2824,0,0,4,0,0,C2C313EA7693B677480FBE9727594FDF~-1~YAAQFNxgaHWSRttwAQAAhbwd4gNQRFtyWkWIzqyzXgPGEmMAMPtQij3Yq0njYktjFsRyzO6VJVDvEsHn3ePTQNt32PuGY9hoMO6anD56TnIw+6k570Y5JV+M+JE1+FHM2EQvmgnj+nlc95tTRK0hBtYn13M94qQxb51cgM8Qm8zEzAg67nMV13ApfIciaSsBrQcF15YbcQWjauiPznq1w3KRLQNRl3kMVfo/QxvX0pUFdwJtwbiaT0duri/VJN6kOKxDXN83PiBcmCgy4xgjVc2dj8QuQlvetoMP0spITtLJgNmmKrzW6UT4Yw==~-1~-1~-1,30142,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,1572637-1,2,-94,-118,92081-1,2,-94,-121,;2;-1;0",
        ];

        $secondSensorData = [
            "7a74G7m23Vrp0o5c9263271.7-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:87.0) Gecko/20100101 Firefox/87.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,399582,5987608,1536,824,1536,864,1536,239,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5565,0.686684414343,812002993804,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1555,113,0;0,-1,0,0,796,796,0;1,-1,0,0,461,461,0;0,-1,0,0,661,661,0;0,-1,0,0,1120,1120,0;-1,2,-94,-102,0,-1,0,0,1555,113,0;0,-1,0,0,796,796,0;1,-1,0,0,461,461,0;0,-1,0,0,661,661,0;0,-1,0,0,1120,1120,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.jejuair.net/jejuair/en/com/jeju/ibe/goLogin.do-1,2,-94,-115,1,32,32,0,0,0,0,830,0,1624005987608,30,17373,0,0,2895,0,0,831,0,0,3BA8E4FE27998D6766ACCCA564B997EC~-1~YAAQNWcQAgQ5BOt5AQAA8r9MHgaJnMwwyjdgeS2jajQouUaxOJRD3JsIi2j8LEHIsVJXob6o6s0Ljk4BUXSUeGkGMGHemC+xjEND1Rq3F/RNhgXbMcG8q2q1ZpBSkIV8DrPRF/BfG4b1/H/8XR7EHNZL/+H3S+/KTXHPhaCuY4+iLe16zv3Y+XXt2O20irE8u4a3gjvpHgf7UT2JhVSwsPlAeUaomfjC6iB1X7SdfOydtzQXG5PNP3/JPs+bWANfLKjdOwLGeaA9vZ5+Yom2jRODFcsqVZG0TLYD+4SNlpXIA9tnVTk9kCypai0Ekrgh/wKVoWwQmCmEtVU6IUqtqhS+o5VOmV11+CHtm11cGHzeew+v/uwFilVmUCzN4ZPIQQ1zpZNRo711XQyt~-1~-1~-1,36327,938,2109111370,25543097,PiZtE,43943,72,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,0,0,0,0,0,0,0,200,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,1436327638;1247333925;dis;;true;true;true;-180;true;24;24;true;false;1-1,2,-94,-80,5343-1,2,-94,-116,1454988339-1,2,-94,-118,95490-1,2,-94,-129,2473b50a9b0e97e41802d24c10b608a2b55eadccff7f9ad75fde708992443f6e,1.25,0,Google Inc.,ANGLE (Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0),2669885e0376ed57265f041bfbd5a2174ca5b7a1d3bc28048453faef8450e0ef,26-1,2,-94,-121,;19;12;0",
            //            "7a74G7m23Vrp0o5c9152091.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:73.0) Gecko/20100101 Firefox/73.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,389823,1572653,1536,880,1536,960,1536,423,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6004,0.679011432339,792170786326.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1555,113,0;0,-1,0,0,796,796,0;1,-1,0,0,461,461,0;0,-1,0,0,661,661,0;0,-1,0,0,1120,1120,0;-1,2,-94,-102,0,-1,0,0,1555,113,0;0,-1,0,0,796,796,0;1,-1,0,0,461,461,0;0,-1,0,0,661,661,0;0,-1,0,0,1120,1120,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.jejuair.net/jejuair/en/com/jeju/ibe/goLogin.do?pageType=001&target_url=%2Fen%2Frefresh%2FgoRefreshPoint.do%3FsystemType%3DIBE-1,2,-94,-115,1,32,32,0,0,0,0,552,0,1584341572653,6,16948,0,0,2824,0,0,553,0,0,C2C313EA7693B677480FBE9727594FDF~-1~YAAQFNxgaHWSRttwAQAAhbwd4gNQRFtyWkWIzqyzXgPGEmMAMPtQij3Yq0njYktjFsRyzO6VJVDvEsHn3ePTQNt32PuGY9hoMO6anD56TnIw+6k570Y5JV+M+JE1+FHM2EQvmgnj+nlc95tTRK0hBtYn13M94qQxb51cgM8Qm8zEzAg67nMV13ApfIciaSsBrQcF15YbcQWjauiPznq1w3KRLQNRl3kMVfo/QxvX0pUFdwJtwbiaT0duri/VJN6kOKxDXN83PiBcmCgy4xgjVc2dj8QuQlvetoMP0spITtLJgNmmKrzW6UT4Yw==~-1~-1~-1,30142,543,198331435,26067385-1,2,-94,-106,9,1-1,2,-94,-119,200,0,0,200,0,200,0,0,0,0,0,200,200,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,1572637-1,2,-94,-118,95258-1,2,-94,-121,;1;5;0",
        ];
        $thirdSensorData = [
            "7a74G7m23Vrp0o5c9263271.7-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:87.0) Gecko/20100101 Firefox/87.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,399582,5987608,1536,824,1536,864,1536,239,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:73.5999984741211,vib:1,bat:0,x11:0,x12:1,5565,0.678275775339,812002993804,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1555,113,0;0,-1,0,0,796,796,0;1,-1,0,0,461,461,0;0,-1,0,0,661,661,0;0,-1,0,0,1120,1120,0;-1,2,-94,-102,0,-1,0,0,1555,113,0;0,-1,0,0,796,796,0;1,-1,0,0,461,461,0;0,-1,0,0,661,661,0;0,-1,0,0,1120,1120,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.jejuair.net/jejuair/en/com/jeju/ibe/goLogin.do-1,2,-94,-115,1,32,32,0,0,0,0,1664,0,1624005987608,30,17373,0,0,2895,0,0,1665,0,0,3BA8E4FE27998D6766ACCCA564B997EC~-1~YAAQNWcQAiI5BOt5AQAAGMxMHgZyYcS+64W6G/8QtnN6Uva8hkaGIHKADK1IgehPdeM6AApv5a8gUKEFqk6x1ye6N45izWSenPljZ+myL7Af1NV7UW2rbFys9jmQcT5jwOnOF0Id3kn7aIk47O0ehjuVjWmAHlkAZ1IfqMwLOxiKEXYz95OZssrHpYZooC+Pd4lv+TA0lvg9RQ1+0XmVHHpXXUnammAH4JFrp3XIJqeQvRZN0PWIOBCaYfQrgbWRuOn+ku+iZB6+HFED2pR1AEsWrM+pR0IAbR7spRnW6jIW+zZX3eaEKGlZ3QIO/gTqNgMx328avjSykZGXURdv0CI2YCCK2ZVezTPmEdBz/iL0Eocw6TRnlxqmeBwqs/OmMRzJScrXWRWKmbRZ~-1~||1-OuROFlukxz-1-10-1000-2||~-1,38811,938,2109111370,25543097,PiZtE,95477,94,0,-1-1,2,-94,-106,8,2-1,2,-94,-119,0,0,0,0,0,0,0,0,0,0,0,200,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.2c8adbd3acc39,0.4f988da87ad9b,0.2423a8adf4b1a,0.2946a611909238,0.1e223fe4fbd7b,0.6820a33e52391,0.3805fc6b4a8b1,0.b6a443d055962,0.a35c39c12d6448,0.5668e70a258fc8;0,0,2,1,1,1,1,0,1,0;0,0,1,3,4,0,2,0,8,1;3BA8E4FE27998D6766ACCCA564B997EC,1624005987608,OuROFlukxz,3BA8E4FE27998D6766ACCCA564B997EC1624005987608OuROFlukxz,1,1,0.2c8adbd3acc39,3BA8E4FE27998D6766ACCCA564B997EC1624005987608OuROFlukxz10.2c8adbd3acc39,35,21,178,49,217,32,43,82,47,182,164,131,142,121,175,57,71,126,9,202,212,204,32,158,210,213,149,69,139,217,25,33,1606,0,1624005989272;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,1436327638;1247333925;dis;;true;true;true;-180;true;24;24;true;false;1-1,2,-94,-80,5343-1,2,-94,-116,1454988339-1,2,-94,-118,130796-1,2,-94,-129,2473b50a9b0e97e41802d24c10b608a2b55eadccff7f9ad75fde708992443f6e,1.25,0aff22edcc367b46cca4c87eaa3a9207ba86a7fb36f3574a2dade646c5d0b2dd,Google Inc.,ANGLE (Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0),2669885e0376ed57265f041bfbd5a2174ca5b7a1d3bc28048453faef8450e0ef,26-1,2,-94,-121,;3;12;0",
            //            "7a74G7m23Vrp0o5c9152091.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:73.0) Gecko/20100101 Firefox/73.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,389823,1572653,1536,880,1536,960,1536,423,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6004,0.679011432339,792170786326.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1555,113,0;0,-1,0,0,796,796,0;1,-1,0,0,461,461,0;0,-1,0,0,661,661,0;0,-1,0,0,1120,1120,0;-1,2,-94,-102,0,-1,0,0,1555,113,0;0,-1,0,0,796,796,0;1,-1,0,0,461,461,0;0,-1,0,0,661,661,0;0,-1,0,0,1120,1120,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.jejuair.net/jejuair/en/com/jeju/ibe/goLogin.do?pageType=001&target_url=%2Fen%2Frefresh%2FgoRefreshPoint.do%3FsystemType%3DIBE-1,2,-94,-115,1,32,32,0,0,0,0,552,0,1584341572653,6,16948,0,0,2824,0,0,553,0,0,C2C313EA7693B677480FBE9727594FDF~-1~YAAQFNxgaHWSRttwAQAAhbwd4gNQRFtyWkWIzqyzXgPGEmMAMPtQij3Yq0njYktjFsRyzO6VJVDvEsHn3ePTQNt32PuGY9hoMO6anD56TnIw+6k570Y5JV+M+JE1+FHM2EQvmgnj+nlc95tTRK0hBtYn13M94qQxb51cgM8Qm8zEzAg67nMV13ApfIciaSsBrQcF15YbcQWjauiPznq1w3KRLQNRl3kMVfo/QxvX0pUFdwJtwbiaT0duri/VJN6kOKxDXN83PiBcmCgy4xgjVc2dj8QuQlvetoMP0spITtLJgNmmKrzW6UT4Yw==~-1~-1~-1,30142,543,198331435,26067385-1,2,-94,-106,9,1-1,2,-94,-119,200,0,0,200,0,200,0,0,0,0,0,200,200,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,1572637-1,2,-94,-118,95258-1,2,-94,-121,;1;5;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        if (count($sensorData) != count($thirdSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        sleep(1);
        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        sleep(1);
        $sensorData = [
            'sensor_data' => $thirdSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // maintenance
        if ($this->http->FindSingleNode("//img[@src = 'sysinfo_kr.jpg']/@src")) {
            throw new CheckException("Maintenance in progress. We apologize for the inconvenience. The site will be available shortly.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//p[contains(text(), 'We apologize for any inconvenience you may have experienced.')]")) {
            throw new CheckException("Maintenance in progress. We apologize for the inconvenience you may have experienced. We will do our best to provide better service.", ACCOUNT_PROVIDER_ERROR);
        }
        // We're sorry, but website was not found
        if ($this->http->FindSingleNode('//title[contains(text(), "We\'re sorry, but website was not found")]')
            && $this->http->Response['code'] == 404) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
