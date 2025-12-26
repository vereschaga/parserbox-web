<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerHotwire extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    use PriceTools;

    private const REWARDS_PAGE_URL = "https://www.hotwire.com/account/overview";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    /** @var TAccountCheckerHotwire|null */
    private $selenium = null;
    /*
    public static function GetAccountChecker($accountInfo)
    {
        if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
            return new static();
        } else {
            require_once __DIR__ . "/TAccountCheckerHotwireSelenium.php";

            return new TAccountCheckerHotwireSelenium();
        }
    }
    */
    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        //$this->http->setHttp2(true);
        //$this->http->setRandomUserAgent();
        $this->http->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36');
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
        //$this->selenium();
        $this->http->GetURL('https://www.hotwire.com');

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

        $clientId = $this->http->getCookieByName('hwcl', '.hotwire.com');

        if (!$clientId) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
            $clientId = $this->http->getCookieByName('hwcl', '.hotwire.com');
        }

        if (!$clientId) {
            return $this->checkErrors();
        }

        $gmaks = $this->getGmaks('JfWYRSf3p7uFbcecvMmCCCrhFHKrA6vREHhBxpep3uavsc822whmug5zdh9h');
        $captcha = $this->parseReCaptcha();

        if (!$captcha) {
            return $this->checkErrors();
        }
        $query = http_build_query([
            'from' => 'https://www.hotwire.com',
        ]) . $gmaks;
        $headers = [
            'Accept'          => '*/*',
            'Content-Type'    => 'application/json;charset=UTF-8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Cache-Control'   => 'no-cache',
            'Pragma'          => 'no-cache',
            'Origin'          => 'https://me.hotwire.com',
            'Referer'         => 'https://me.hotwire.com/',
        ];
        $data = [
            'challengeToken' => $captcha,
            'challengeType'  => 'recaptcha',
            'clientId'       => $clientId,
            'loginId'        => $this->AccountFields['Login'],
            'password'       => $this->AccountFields['Pass'],
            'placementCode'  => 110100,
            'rememberMe'     => true,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.hotwire.com/api2/login?' . $query,
            json_encode($data), $headers);
        $this->http->RetryCount = 2;
        //return $this->sendSensorData();
        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = self::REWARDS_PAGE_URL;

        return $arg;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->token)) {
            return true;
        }

        $message = $response->errorSet[0]->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Invalid login') {
                throw new CheckException('The email or password you have entered is incorrect. Please try again.', ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Seems like you have registered that email address using a third party login.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Unexpected error') {
                throw new CheckException("Sorry, our login service is down. Please try again later.", ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);

        // set Name property
        $this->SetProperty("Name", beautifulName($response->firstName . ' ' . $response->lastName));
        // set balanceNA
        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $user = $this->http->JsonLog(null, 0);
        $gmaks = $this->getGmaks('wTOK7Wg8InOCMjKZV3Xke7Vdr84strgnq5tdztumueuv2by8');

        $headers = [
            'Accept'        => '*/*',
            'Content-Type'  => 'application/json;charset=UTF-8',
            'Authorization' => $user->token,
        ];
        $date = urlencode(date("m/d/Y", strtotime('-10 year')));
        $this->http->GetURL("https://www.hotwire.com/api/account/secure/trip-summary/trip/{$user->customerId}?completionDateAfter={$date}&limit=50&{$gmaks}", $headers);

        if ($this->http->FindPreg('/\{"searchErrorCollectionList":\[\],"orderSummaryList":\[\]\}/')) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }
        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Content-Type'  => 'text/xml',
            'Authorization' => 'Bearer ' . $user->token,
        ];
        $gmaks = trim($this->getGmaks('PGPNz9TcGHCu6UagTywEAYaRexfvbvj3az2zg39dwteda3yh'), '&');
        $response = $this->http->JsonLog();

        // provider bug issue
        if (!isset($response->orderSummaryList)) {
            return [];
        }
        $orderSummaryList = [];

        foreach ($response->orderSummaryList as $list) {
            if (!isset($orderSummaryList[$list->hotwireItinerary ?? $list->itineraryNumber])) {
                $orderSummaryList[$list->hotwireItinerary ?? $list->itineraryNumber] = $list;
            }
        }

        foreach ($orderSummaryList as $list) {
            foreach ($list->orderLineSummaryList as $item) {
                $data = strtotime(
                    $item->hotelReservationSummary->hotelConfirmation->checkInDate ??
                    $item->carReservationSummary->pickUpDateTime ??
                    $item->airReservationSummary->airRecordSummaryList[0]->airSimpleSegments[0]->departureTime ??
                    null
                );

                if (!$this->ParsePastIts && $data < time()) {
                    $this->logger->debug('skip past reservation : ' . date($data));

                    continue 2;
                }

                if (isset($item->airReservationSummary->airRecordSummaryList)) {
                    $this->sendNotification('check airReservationSummary // MI');
                }

                if (isset($item->carReservationSummary->carConfirmation->confirmationNumber)
                    || isset($item->hotelReservationSummary->hotelConfirmation->confirmationNumber)) {
                    $number = $list->hotwireItinerary ?? $list->itineraryNumber;
                    $data = '<RetrieveTripDetailsRQ echoToken="token1"><ReservationRQ ItineraryNumber="' . $number . '"/></RetrieveTripDetailsRQ>';
                    $this->http->PostURL("https://www.hotwire.com/api/1/v1.1/secure/trip/details?{$gmaks}", $data, $headers);

                    if ($this->http->Response['code'] == 400) {
                        continue;
                    }
                    $detail = $this->http->JsonLog();

                    if (!isset($detail->tripDetails)) {
                        $this->logger->error('Car, Hotel: empty tripDetails');

                        return [];
                    }
                } elseif (isset($item->airReservationSummary->airRecordSummaryList[0]->airSimpleSegments)) {
                    $this->http->GetURL("https://vacation.hotwire.com/trips/{$list->itineraryNumber}?email={$this->AccountFields['Login']}");
                    $detail = $this->http->FindPreg('/var utag_data = (.+?);/');
                    $detail = $this->http->JsonLog($detail);

                    if (!isset($detail->entity->tripDetails)) {
                        $this->logger->error('Flight: empty tripDetails');

                        return [];
                    }
                }

                if (in_array($this->currentItin, [30, 60, 90, 120])) {
                    $this->logger->notice('increaseTimeLimit 300');
                    $this->increaseTimeLimit(60);
                }

                // Hotel
                if (isset($item->hotelReservationSummary->hotelConfirmation->confirmationNumber)) {
                    $this->parseHotelNew($item->hotelReservationSummary, $detail->tripDetails);
                } // Car
                elseif (isset($item->carReservationSummary->carConfirmation->confirmationNumber)) {
                    foreach ($detail->tripDetails->reservation as $reservation) {
                        if ($reservation->productVertical == 'Car') {
                            $this->parseCarNew($item->carReservationSummary, $reservation, $detail->tripDetails);
                        }

                        if ($reservation->productVertical == 'Air') {
                            $this->sendNotification('check Air // MI');
                            $this->parseFlightNew($item->carReservationSummary, $reservation, $detail->tripDetails);
                        }
                    }
                } // Flight
                elseif (isset($item->airReservationSummary->airRecordSummaryList[0]->airSimpleSegments)) {
                    $this->parseFlightNewHtml($detail->entity->tripDetails);
                }
                $this->currentItin++;
            }
        }

        if (empty($response->orderSummaryList) && empty($this->itinerariesMaster->getItineraries()) && !$this->ParsePastIts) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        return [];
    }

    private function parseFlightNew($itr, $res, $detail)
    {
        $this->logger->notice(__METHOD__);

        /*$f = $this->itinerariesMaster->add()->flight();
        $conf = $res->information->confirmationCode;
        $this->logger->info("[$this->currentItin] Parse Flight #{$conf}", ['Header' => 3]);
        $f->ota()
            ->confirmation($detail->itineraryNumber);
        $f->general()
            ->confirmation($conf)
            ->status($itr->carConfirmation->reservationStatus)
            ->traveller($res->reservationDetails->driverName);*/
    }

    private function parseHotelNew($itr, $detail)
    {
        $this->logger->notice(__METHOD__);

        if (count($detail->reservation) > 1) {
            $this->sendNotification('hotel detail->reservation > 1 // MI');
        }
        $res = current($detail->reservation);
        $h = $this->itinerariesMaster->add()->hotel();
        $conf = $res->information->confirmationCode;
        $this->logger->info("[$this->currentItin] Parse Hotel #{$conf}", ['Header' => 3]);
        $h->ota()
            ->confirmation($detail->itineraryNumber);

        foreach (explode(',', $conf) as $cnf) {
            $h->general()->confirmation($cnf);
        }
        $h->general()
            ->status($res->information->bookingStatus)
            ->travellers(array_column($res->reservationDetails->guests, 'guestName'));

        $h->hotel()->name($res->reservationDetails->hotelDetails->hotelName)
            ->phone($res->reservationDetails->hotelDetails->phoneNumber, false, true)
            ->fax($res->reservationDetails->hotelDetails->faxNumber, false, true);
        $addressDetail = $res->reservationDetails->hotelDetails->address;

        if (isset($addressDetail->addressLine1)) {
            $h->hotel()->noAddress();
            $address = join(', ', array_filter([$addressDetail->addressLine1, $addressDetail->addressLine2]));
            $detailed = $h->hotel()->detailed();
            $detailed->address($address)
                ->city($addressDetail->city)
                ->state($addressDetail->state)
                ->country($addressDetail->country);

            $addressDetail->postalCode = trim($addressDetail->postalCode);

            if (!empty($addressDetail->postalCode)) {
                $detailed->zip($addressDetail->postalCode);
            }
        }

        $h->booked()->guests($res->reservationDetails->adult)
            ->kids($res->reservationDetails->child);
        $h->booked()->checkIn2($res->duration->checkInTime ?? preg_replace('/:\d+$/', '', $res->duration->startDate));
        $h->booked()->checkOut2($res->duration->checkOutTime ?? preg_replace('/:\d+$/', '', $res->duration->endDate));

        $r = $h->addRoom();
        $r->setType($res->reservationDetails->roomInfo->bedType ?? $res->reservationDetails->roomInfo->roomType, false, true);
        $r->setDescription(join(', ', array_column($res->reservationDetails->roomInfo->roomAmenities, 'name')), true);

        $h->price()->total(round($res->reservationDetails->summaryOfCharges->total, 2));
        $h->price()->tax(round($res->reservationDetails->summaryOfCharges->taxesAndFees, 2));
        $h->price()->discount(round($res->reservationDetails->summaryOfCharges->discountAmountApplied, 2));
        $h->price()->currency($detail->localCurrencyCode);

        if (isset($res->reservationDetails->travelerAdvisory->bookingRules)
            && $this->http->FindPreg('/No refunds or changes/', false, $res->reservationDetails->travelerAdvisory->bookingRules)) {
            $h->booked()->nonRefundable();
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function parseCarNew($itr, $res, $detail)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->rental();
        $conf = $res->information->confirmationCode;
        $this->logger->info("[$this->currentItin] Parse Car #{$conf}", ['Header' => 3]);
        $r->ota()
            ->confirmation($detail->itineraryNumber);
        $r->general()
            ->confirmation($conf)
            ->status($itr->carConfirmation->reservationStatus)
            ->traveller($res->reservationDetails->driverName);
        $r->extra()->company($res->reservationDetails->rentalAgency->agencyName);

        if ($itr->carConfirmation->reservationStatus == 'canceled') {
            $r->general()->cancelled();
        }

        if (isset($res->location->origin->originalLocation)) {
            $r->pickup()
                ->location($res->location->origin->originalLocation);
        } elseif (isset($res->location->originalLocation)) {
            $r->pickup()
                ->location($res->location->originalLocation);
        } else {
            $r->pickup()->noLocation();
        }
        $r->pickup()
            ->date2(preg_replace('/(\d+:\d+):\d+$/', '$1', $res->reservationDetails->pickupTime))
            ->phone($res->reservationDetails->rentalAgency->agencyContact);
        $address = $res->reservationDetails->serviceAddress->pickupAddress;

        if (isset($address->addressLine1)) {
            $d = $r->pickup()->detailed();
            $d->address(join(', ', array_filter([$address->addressLine1, $address->addressLine2])))
                ->city($address->city)
                ->state($address->state)
                ->country($address->country);

            if (!empty($address->postalCode)) {
                $d->zip($address->postalCode);
            }
        }

        $r->dropoff()
            ->date2(preg_replace('/(\d+:\d+):\d+$/', '$1', $res->reservationDetails->dropoffTime))
            ->phone($res->reservationDetails->rentalAgency->agencyContact);
        $address = $res->reservationDetails->dropOffLocation->address;

        if (isset($res->location->origin->destinationLocation)) {
            $r->dropoff()
                ->location($res->location->origin->destinationLocation);
        } elseif (isset($res->location->destinationLocation)) {
            $r->dropoff()
                ->location($res->location->destinationLocation);
        } else {
            $r->dropoff()
                ->noLocation();
        }

        if (isset($address->addressLine1)) {
            $detailed = $r->dropoff()->detailed();
            $detailed->address(join(', ', array_filter([$address->addressLine1, $address->addressLine2])))
                ->city($address->city)
                ->state($address->state)
                ->country($address->country);

            if (!empty($addressDetail->postalCode)) {
                $detailed->zip($address->postalCode);
            }
        }

        $r->car()->type($res->reservationDetails->carType->name)
            ->image($res->reservationDetails->carType->carTypeImageUrls->largeImageUrl)
            ->model($res->reservationDetails->carDetails->model, false, true);
        $r->price()->total(round($res->reservationDetails->summaryOfCharges->total, 2));
        $r->price()->tax(round($res->reservationDetails->summaryOfCharges->taxesAndFees, 2));
        $r->price()->discount(round($res->reservationDetails->summaryOfCharges->discountAmountApplied, 2));
        $r->price()->currency($detail->localCurrencyCode);
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function parseFlightNewHtml($detail)
    {
        $this->logger->notice(__METHOD__);

        $f = $this->itinerariesMaster->add()->flight();
        $conf = $this->http->FindSingleNode("//*[@id='overview_booking_id']");

        $this->logger->info("[$this->currentItin] Parse Flight #{$conf}", ['Header' => 3]);

        if (!$conf && $this->http->FindSingleNode("//*[@id='bookingMessage' and contains(text(),'No need to reconfirm.')]")) {
            $f->general()->noConfirmation();
        } else {
            $f->general()->confirmation($conf);
        }
        $f->ota()
            ->confirmation($this->http->FindSingleNode("//*[@id='itinerary_number']"));
        $f->general()
            ->status($this->http->FindSingleNode("//*[@id='bookingStatusText']"))
             ;

        $travelers = $this->http->XPath->query("//*[@id='travelers']//*[@class='section-content']");

        foreach ($travelers as $traveler) {
            $f->general()->traveller($this->http->FindSingleNode(".//*[@class='passengerName']", $traveler));
            $acc = $this->http->FindSingleNode(".//*[@id='frequentFlyer']", $traveler, false, '/(\w{5,})$/');

            if (!$acc) {
                $acc = $this->http->FindSingleNode(".//*[contains(text(),'Known Traveler Number')]/following-sibling::*[1]", $traveler, false, '/(\w{5,})$/');
            }
            $f->program()->account($acc, false, true);
        }

        foreach ($detail->flightOffers as $flightOffers) {
            foreach ($flightOffers->flight->legs as $legs) {
                foreach ($legs->segments as $segment) {
                    $s = $f->addSegment();
                    $s->airline()->name($segment->carrierCode);
                    $s->airline()->number($segment->flightNumber);

                    $s->departure()->code($segment->departureAirportCode);
                    $s->departure()->date($segment->utcDepartureTimestamp);
                    $s->arrival()->code($segment->arrivalAirportCode);
                    $s->arrival()->date($segment->utcArrivalTimestamp);
                }
            }
        }

        $f->price()->currency($detail->totalPrice->currency);
        $f->price()->total($detail->totalPrice->amount);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(text(), 'Sign out')]")) {
            return true;
        }

        return false;
    }

    private function getGmaks($param)
    {
        // https://me.hotwire.com/me/gate.html?ver=1cc37d8
        $script = '
        function limac(f, d) {
            var a = f[0], b = f[1], c = f[2], e = f[3], a = ff(a, b, c, e, d[0], 7, -680876936),
                e = ff(e, a, b, c, d[1], 12, -389564586), c = ff(c, e, a, b, d[2], 17, 606105819),
                b = ff(b, c, e, a, d[3], 22, -1044525330), a = ff(a, b, c, e, d[4], 7, -176418897),
                e = ff(e, a, b, c, d[5], 12, 1200080426), c = ff(c, e, a, b, d[6], 17, -1473231341),
                b = ff(b, c, e, a, d[7], 22, -45705983), a = ff(a, b, c, e, d[8], 7, 1770035416),
                e = ff(e, a, b, c, d[9], 12, -1958414417), c = ff(c, e, a, b, d[10], 17, -42063),
                b = ff(b, c, e, a, d[11], 22, -1990404162), a = ff(a, b, c, e, d[12], 7, 1804603682),
                e = ff(e, a, b, c, d[13], 12, -40341101),
                c = ff(c, e, a, b, d[14], 17, -1502002290), b = ff(b, c, e, a, d[15], 22, 1236535329),
                a = gg(a, b, c, e, d[1], 5, -165796510), e = gg(e, a, b, c, d[6], 9, -1069501632),
                c = gg(c, e, a, b, d[11], 14, 643717713), b = gg(b, c, e, a, d[0], 20, -373897302),
                a = gg(a, b, c, e, d[5], 5, -701558691), e = gg(e, a, b, c, d[10], 9, 38016083),
                c = gg(c, e, a, b, d[15], 14, -660478335), b = gg(b, c, e, a, d[4], 20, -405537848),
                a = gg(a, b, c, e, d[9], 5, 568446438), e = gg(e, a, b, c, d[14], 9, -1019803690),
                c = gg(c, e, a, b, d[3], 14, -187363961), b = gg(b, c, e, a, d[8], 20, 1163531501),
                a = gg(a, b, c, e, d[13], 5, -1444681467), e = gg(e,
                a, b, c, d[2], 9, -51403784), c = gg(c, e, a, b, d[7], 14, 1735328473),
                b = gg(b, c, e, a, d[12], 20, -1926607734), a = hh(a, b, c, e, d[5], 4, -378558),
                e = hh(e, a, b, c, d[8], 11, -2022574463), c = hh(c, e, a, b, d[11], 16, 1839030562),
                b = hh(b, c, e, a, d[14], 23, -35309556), a = hh(a, b, c, e, d[1], 4, -1530992060),
                e = hh(e, a, b, c, d[4], 11, 1272893353), c = hh(c, e, a, b, d[7], 16, -155497632),
                b = hh(b, c, e, a, d[10], 23, -1094730640), a = hh(a, b, c, e, d[13], 4, 681279174),
                e = hh(e, a, b, c, d[0], 11, -358537222), c = hh(c, e, a, b, d[3], 16, -722521979),
                b = hh(b, c, e, a, d[6], 23, 76029189), a = hh(a, b, c, e, d[9],
                4, -640364487), e = hh(e, a, b, c, d[12], 11, -421815835), c = hh(c, e, a, b, d[15], 16, 530742520),
                b = hh(b, c, e, a, d[2], 23, -995338651), a = ii(a, b, c, e, d[0], 6, -198630844),
                e = ii(e, a, b, c, d[7], 10, 1126891415), c = ii(c, e, a, b, d[14], 15, -1416354905),
                b = ii(b, c, e, a, d[5], 21, -57434055), a = ii(a, b, c, e, d[12], 6, 1700485571),
                e = ii(e, a, b, c, d[3], 10, -1894986606), c = ii(c, e, a, b, d[10], 15, -1051523),
                b = ii(b, c, e, a, d[1], 21, -2054922799), a = ii(a, b, c, e, d[8], 6, 1873313359),
                e = ii(e, a, b, c, d[15], 10, -30611744), c = ii(c, e, a, b, d[6], 15, -1560198380),
                b = ii(b, c, e, a, d[13], 21, 1309151649),
                a = ii(a, b, c, e, d[4], 6, -145523070), e = ii(e, a, b, c, d[11], 10, -1120210379),
                c = ii(c, e, a, b, d[2], 15, 718787259), b = ii(b, c, e, a, d[9], 21, -343485551);
            f[0] = add32(a, f[0]);
            f[1] = add32(b, f[1]);
            f[2] = add32(c, f[2]);
            f[3] = add32(e, f[3])
        }
        
        function cmn(f, d, a, b, c, e) {
            d = add32(add32(d, f), add32(b, e));
            return add32(d << c | d >>> 32 - c, a)
        }
        
        function ff(f, d, a, b, c, e, h) {
            return cmn(d & a | ~d & b, f, d, c, e, h)
        }
        
        function gg(f, d, a, b, c, e, h) {
            return cmn(d & b | a & ~b, f, d, c, e, h)
        }
        
        function hh(f, d, a, b, c, e, h) {
            return cmn(d ^ a ^ b, f, d, c, e, h)
        }
        
        function ii(f, d, a, b, c, e, h) {
            return cmn(a ^ (d | ~b), f, d, c, e, h)
        }
        
        function lima1(f) {
            txt = "";
            var d = f.length, a = [1732584193, -271733879, -1732584194, 271733878], b;
            for (b = 64; b <= f.length; b += 64) limac(a, limablk(f.substring(b - 64, b)));
            f = f.substring(b - 64);
            var c = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
            for (b = 0; b < f.length; b++) c[b >> 2] |= f.charCodeAt(b) << (b % 4 << 3);
            c[b >> 2] |= 128 << (b % 4 << 3);
            if (55 < b) for (limac(a, c), b = 0; 16 > b; b++) c[b] = 0;
            c[14] = 8 * d;
            limac(a, c);
            return a
        }
        
        function limablk(f) {
            var d = [], a;
            for (a = 0; 64 > a; a += 4) d[a >> 2] = f.charCodeAt(a) + (f.charCodeAt(a + 1) << 8) + (f.charCodeAt(a + 2) << 16) + (f.charCodeAt(a + 3) << 24);
            return d
        }
        
        var hex_chr = "0123456789abcdef".split("");
        
        function rhex(f) {
            for (var d = "", a = 0; 4 > a; a++) d += hex_chr[f >> 8 * a + 4 & 15] + hex_chr[f >> 8 * a & 15];
            return d
        }
        
        function hex(f) {
            for (var d = 0; d < f.length; d++) f[d] = rhex(f[d]);
            return f.join("")
        }
        
        function lima(f) {
            return hex(lima1(f))
        }
        
        function add32(f, d) {
            return f + d & 4294967295
        }
        "5d41402abc4b2a76b9719d911017c592" != lima("hello") && (add32 = function (f, d) {
            var a = (f & 65535) + (d & 65535);
            return (f >> 16) + (d >> 16) + (a >> 16) << 16 | a & 65535
        });
        var rs = function (f) {
            return f.split("").reverse().join("")
        }, gen = function (f) {
            return function (d) {
                return lima(f + d)
            }
        }, gts = function (f) {
            return Math.round((new Date).getTime() / 1E3 + (f ? f : 0))
        }, gmaks = function (f, d) {
            var a = rs(d), b = rs(["\x3d", f, "\x26"].join("")), c = rs("\x3dgis\x26"), e = a.substring(0, 24),
                a = gen(a)(gts(null));
            return [b, e, c, a].join("")
        };
        sendResponseToPhp(gmaks(\'yekipa\',\'' . $param . '\'));
        ';
        // login - gmaks('yekipa','JfWYRSf3p7uFbcecvMmCCCrhFHKrA6vREHhBxpep3uavsc822whmug5zdh9h');
        // login - gmaks('yekipa','JfWYRSf3p7uFbcecvMmCCCrhFHKrA6vREHhBxpep3uavsc822whmug5zdh9h');
        // &apikey=h9hdz5gumhw228csvau3pepx&sig=340fb23ee83ce7335301b258da7483c3
        // &apikey=8yb2vueumutzdt5qngrts48r&sig=36a4c6e36526c464253681dd27d032c0

        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
        $gmaks = $jsExecutor->executeString($script);
        $this->logger->debug("gmaks: " . $gmaks);

        if (empty($gmaks)) {
            return $this->checkErrors();
        }

        return $gmaks;
    }

    private function selenium(): bool
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $selenium->UseSelenium();
        $selenium->http->saveScreenshots = true;
        $selenium->useChromium();
        $selenium->usePacFile(false);
        $selenium->http->start();
        $selenium->Start();

        $selenium->http->GetURL('https://www.hotwire.com/');
        sleep(7);

//        $email = $selenium->waitForElement(WebDriverBy::cssSelector('input#sign-in-email'), 10);
//        $password = $selenium->waitForElement(WebDriverBy::cssSelector('input#sign-in-password'), 0);
//        $signin = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-bdd = "do-login"]'), 0);
//        if (!$email || !$password || !$signin) {
//            return false;
//        }
        $selenium->saveResponse();
        $success = false;
        $cookies = $selenium->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            if ($cookie['name'] == 'me_token') {
                $success = true;
            }
            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        if ($success) {
        }

        sleep(5);

        return false;
    }

    private function loginSelenium(): bool
    {
        $this->logger->notice(__METHOD__);

        $selenium = $this->selenium;
        $selenium->UseSelenium();
        $selenium->disableImages();
        $selenium->http->saveScreenshots = true;
        $selenium->useChromium();
        $selenium->usePacFile(false);
        $selenium->http->start();
        $selenium->Start();
        $this->http->brotherBrowser($selenium->http);

        $selenium->http->GetURL('https://www.hotwire.com/checkout/#!/account');

        $email = $selenium->waitForElement(WebDriverBy::cssSelector('input#sign-in-email'), 10);
        $password = $selenium->waitForElement(WebDriverBy::cssSelector('input#sign-in-password'), 0);
        $signin = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-bdd = "do-login"]'), 0);

        if (!$email || !$password || !$signin) {
            return false;
        }
        $selenium->saveResponse();
        $this->logger->debug("execute");
        $selenium->driver->executeScript("
            window.grecaptcha = {};
            window.grecaptcha.execute = () => Promise.resolve('');
            window.grecaptcha.render = () => {};
        ");
        $captcha = $this->parseReCaptcha();

        if (!$captcha) {
            return false;
        }
        $this->logger->debug("do login");
        $this->logger->debug("pass: '{$this->AccountFields['Pass']}'"); //todo: issue with '&'
        $selenium->driver->executeScript("
            hotwireMe.login('{$this->AccountFields['Login']}', '{$this->AccountFields['Pass']}', '7407019928304672924', '1000', 'true', 'f');
            window.meHotwireComTokenCallback('{$captcha}');
        ");
        sleep(5);

        $success = false;
        $cookies = $selenium->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            if ($cookie['name'] == 'me_token') {
                $success = true;
            }
            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        if ($success) {
            $selenium->http->GetURL('https://www.hotwire.com/checkout/#!/account/mytrips/upcoming');
            $selenium->saveResponse();
            $greet = $selenium->waitForElement(WebDriverBy::xpath('//span[@data-bdd = "my-test-name"]'), 5);
            $selenium->saveResponse();

            if ($greet) {
                $this->logger->info('Successful login');

                return true;
            }
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We are performing site maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are performing site maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We couldn't process your request
        if ($message = $this->http->FindSingleNode('//img[contains(@alt, "We couldn\'t process your request")]/@alt')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            // 502 Bad Gateway
            $this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function notifyFuture($date)
    {
        if ($date && $date > strtotime('now')) {
            $this->sendNotification('refs #19106, check future itin // MI');
        }

        return $date;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg('/"siteKey":"([^\"]+)/') ?? '6LfALSEUAAAAAE7yBRtT5pyunsHWgCb7KldyereX'; // https://me.hotwire.com/me/hotwireMe.js?v=1.216.1
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

    private function loginToSecondSite()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.hotwire.com/checkout/#!/account');
//        $clientId = $this->http->getCookieByName("_sdsat_ClientID");
        $clientId = '312284734' . time();
        $this->logger->debug("clientId: {$clientId}");
//        if (!$this->http->ParseForm('loginForm') || !$clientId) {
//            return $this->checkErrors();
//        }

        $this->http->SetInputValue('clientId', $clientId);
        $this->http->SetInputValue('loginId', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('rememberMe', "true");

        $headers = [
            "Accept"        => "application/json, text/plain, */*",
            "JSESSIONID"    => $this->http->getCookieByName("JSESSIONID"),
            "X-CHANNEL-ID"  => "CHECKOUT@1.219.1",
            "Origin"        => "https://me.hotwire.com",
        ];
        $this->http->GetURL("https://www.hotwire.com/api/v1/stats?apikey=9nebmnrc9rdukjpwa3bgb9ag&sig=8b3e2451750349a3e11d8e1a5c19ddd9&useCluster=2", $headers);
        $this->http->JsonLog();

        $headers = [
            "Accept"          => "*/*",
            "Accept-Language" => "en-US,en;q=0.5",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-Type"    => "application/json;charset=UTF-8",
            "Connection"      => "keep-alive",
            "Origin"          => "https://me.hotwire.com",
            "User-Agent"      => \HttpBrowser::PROXY_USER_AGENT,
        ];
        $this->http->RetryCount = 0;
        $this->http->OptionsURL("https://www.hotwire.com/api2/login?from=https://www.hotwire.com&apikey=h9hdz5gumhw228csvau3pepx&sig=8b3e2451750349a3e11d8e1a5c19ddd9", $headers);
        $this->http->JsonLog();
//        if ($this->http->Response['code'] == 403) {
        return false;
//        }

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
//        $this->http->SetInputValue('challengeType', "recaptcha");
//        $this->http->SetInputValue('challengeToken', $captcha);

        $data = [
            "loginId"        => $this->AccountFields['Login'],
            "password"       => $this->AccountFields['Pass'],
            "clientId"       => $clientId,
            "challengeToken" => $captcha,
            "challengeType"  => "recaptcha",
            "rememberMe"     => true,
            "placementCode"  => 10000,
        ];
        $headers = [
            "Accept"          => "*/*",
            "Accept-Language" => "en-US,en;q=0.5",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-Type"    => "application/json;charset=UTF-8",
            "Connection"      => "keep-alive",
            "Origin"          => "https://me.hotwire.com",
            "User-Agent"      => \HttpBrowser::PROXY_USER_AGENT,
            "Referer"         => "https://me.hotwire.com/me/gate.html?ver=4d82900",
        ];
//        $this->http->disableOriginHeader();
        $this->http->PostURL("https://www.hotwire.com/api2/login?from=https://www.hotwire.com&apikey=h9hdz5gumhw228csvau3pepx&sig=8b3e2451750349a3e11d8e1a5c19ddd9", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->token)) {
            return false;
        }

        $headers = [
            "Accept"        => "application/json, text/plain, */*",
            "JSESSIONID"    => $this->http->getCookieByName("JSESSIONID"),
            "X-CHANNEL-ID"  => "CHECKOUT@1.219.1",
            "Authorization" => $response->token,
            "Origin"        => "https://me.hotwire.com",
        ];
        $this->http->GetURL("https://www.hotwire.com/api/v1/stats?apikey=9nebmnrc9rdukjpwa3bgb9ag&sig=bde681504f319a0c9023611961b253e1&useCluster=1", $headers);
        $response = $this->http->JsonLog();
        $this->http->GetURL("https://www.hotwire.com/api2/profile/dnssubscription/secure?apikey=dacfr989zfmsvpdzd47829s7&sig=4c1efd50144138294fadd542a99aa8c9&useCluster=1", $headers);
        $response = $this->http->JsonLog();
        $this->http->RetryCount = 2;

        return false;
    }
}
