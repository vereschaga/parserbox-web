<?php

// check deploy 1

use AwardWallet\Common\Parser\Util\PriceHelper;

class TAccountCheckerZuji extends TAccountCheckerExtended
{
    public $regionOptions = [
        ""                => "Select a country",
        "www.zuji.com.hk" => "Hong Kong",
        "www.zuji.com.sg" => "Singapore",
        //        "www.zuji.com.au" => "Australia",// Thanks for visiting today. It is with a heavy heart that we inform you that ZUJI Australia has closed its virtual doors.
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields, $values);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = 'http://' . $this->AccountFields['Login2'];

        return $arg;
    }

    public function LoadLoginForm()
    {
        if ($this->AccountFields['Login2'] == 'www.zuji.com.au') {
            throw new CheckException("Thanks for visiting today. It is with a heavy heart that we inform you that ZUJI Australia has closed its virtual doors.", ACCOUNT_PROVIDER_ERROR);
        }

        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice("Region => " . $this->AccountFields['Login2']);

        if (!strstr($this->AccountFields['Login'], "@")) {
            throw new CheckException("Please enter a valid email address to continue.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->FilterHTML = false;
        $this->http->removeCookies();
        // prepare link
        $host = str_replace('www.', '', $this->AccountFields['Login2']);
        $this->http->GetURL("https://flights.{$host}/FlightSearch/SignIn/Index");

        if (!$this->http->ParseForm("SignInNoGuestForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("Email", $this->AccountFields['Login']);
        $this->http->SetInputValue("Password", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('http://' . $this->AccountFields['Login2']);

        if ($message = $this->http->FindSingleNode("//h1[contains(text(),'New site coming soon')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The web app you have attempted to reach is currently stopped and does not accept any requests.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The web app you have attempted to reach is currently stopped and does not accept any requests.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindSingleNode("//p[@class='errorMessage']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//span[@class='field-validation-error']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode("//a[contains(@href, 'LogOut')]/@href")) {
            return true;
        }
        // provider error
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "We\’re experiencing site issues. Don\’t worry, we’re working on it!")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "We\'re sorry, this page is temporarily unavailable")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->notice("Region => " . $this->AccountFields['Login2']);
        //# Get profile page
        $host = str_replace('www.', '', $this->AccountFields['Login2']);
        $this->http->GetURL("https://flights." . $host . "/FlightSearch/User/MyAccount?_=" . time() . date("B"));
        $this->SetProperty("Name", beautifulName(CleanXMLValue(
            $this->http->FindSingleNode("//input[@name = 'FirstName']/@value") . ' ' .
            $this->http->FindSingleNode("//input[@name = 'LastName']/@value")
        )));

        if (isset($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $trips = [];
        $this->logger->notice("Region => " . $this->AccountFields['Login2']);
        $host = str_replace('www.', '', $this->AccountFields['Login2']);
        $this->http->GetURL("https://flights." . $host . "/FlightSearch/User/MyBookings?_=" . time() . date("B"));
        // No Itineraries
        if ($this->http->FindSingleNode("//div[@id = 'my-bookings-list']//p[contains(text(), 'No bookings found.')]")) {
            return $this->noItinerariesArr();
        }

        $links = $this->http->FindNodes("//a[contains(text(), 'View Itinerary')]/@href");

        foreach ($links as $link) {
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
            $trips = array_merge($trips, $this->ParseItinerary());
        }

        return $trips;
    }

    public function ParseItinerary()
    {
        $trip = [];
        $its = $this->http->XPath->query("//div[@id = 'confirmation-flight-details']/div[@class = 'content']");
        $this->logger->notice("Total {$its->length} flights were found");

        foreach ($its as $item) {
            $it = [];
//            $class = $this->http->FindSingleNode('./@class', $item, true, '/\w+$/');
//            $table = $this->http->XPath->query('./following::table[1]', $item)->item(0);
//            if ($class == 'flights') {
            $it['Kind'] = 'T';
            $it['RecordLocator'] = $this->http->FindSingleNode("div[@class and not(contains(@class , 'header'))]/div[6]/p", $item) ?: CONFNO_UNKNOWN;
            $this->logger->info(sprintf('Parse Flight #%s', $it['RecordLocator']), ['Header' => 4]);
//                $it['Status'] = $this->http->FindSingleNode(".//th[contains(text(), 'Booking status:')]/following::td[1]", $item);
            $it['TripNumber'] = $this->http->FindSingleNode("//h3[contains(text(), 'ZUJI Reference:')]", $item, true, "/:\s*([^<]+)/");
            $it['Passengers'] = array_map(function ($elem) {
                return beautifulName($elem);
            }, $this->http->FindNodes(".//div[@class = 'pax-info']/div[@class = 'detail-row']/div[1]", $item));
            $accountNumbers = $this->http->FindNodes(".//div[@class = 'pax-info']/div[@class = 'detail-row']/div[4]", $item);
            $accountNumbers = array_filter($accountNumbers, function ($s) { return trim($s); });
            $it['AccountNumbers'] = $accountNumbers;
            // TotalCharge
            $it['TotalCharge'] = PriceHelper::cost($this->http->FindSingleNode("//div[@id = 'confirmation-pricing-details-total']/div/h3[@class = 'pull-right']", null, true, self::BALANCE_REGEXP_EXTENDED));
            // Currency
            $it['Currency'] = $this->http->FindSingleNode('//span[contains(text(), "Booking Price including")]', null, true, "/\(([A-Z]{3})\)\s*$/");

            $segments = $this->http->XPath->query("div[@class and not(contains(@class , 'header'))]", $item);
            $this->logger->debug("Total {$segments->length} segments were found");

            foreach ($segments as $segment) {
                $depName = $this->http->FindSingleNode('div[3]/p[1]', $segment);
                $depCode = $this->http->FindPreg('/[A-Z]{3}/', false, $depName);
                $arrName = $this->http->FindSingleNode('div[4]/p[1]', $segment);
                $arrCode = $this->http->FindPreg('/[A-Z]{3}/', false, $arrName);
                $it['TripSegments'][] = [
                    'DepCode'           => $depCode ?? TRIP_CODE_UNKNOWN,
                    'DepName'           => preg_replace("/\s+Terminal.+/", "", $depName),
                    'DepartureTerminal' => $this->http->FindPreg('/Terminal (.+)/', false, $depName),
                    'DepDate'           => strtotime($this->http->FindSingleNode("div[3]/div[1]", $segment, true, '/, (.*)/') . $this->http->FindSingleNode("div[3]/div[2]", $segment)),
                    'ArrCode'           => $arrCode ?? TRIP_CODE_UNKNOWN,
                    'ArrName'           => preg_replace("/\s+Terminal.+/", "", $arrName),
                    'ArrivalTerminal'   => $this->http->FindPreg('/Terminal (.+)/', false, $arrName),
                    'ArrDate'           => strtotime($this->http->FindSingleNode("div[4]/div[1]", $segment, true, '/,\s*(.*)/') . $this->http->FindSingleNode("div[4]/div[2]", $segment)),
                    'AirlineName'       => $this->http->FindSingleNode("div[2]/p[1]", $segment),
                    'FlightNumber'      => $this->http->FindSingleNode("div[2]/p[2]", $segment),
                    'Cabin'             => $this->http->FindSingleNode("div[5]/p", $segment),
                ];
            }
            $this->logger->debug('Parsed Itinerary:');
            $this->logger->debug(var_export($it, true), ['pre' => true]);
            $trip[] = $it;
//            }

            /*if ($class == 'hotels') {
                $it['Kind'] = 'R';
                $it['HotelName'] = $this->http->FindSingleNode('./text()', $item);
                $it['ConfirmationNumber'] = $this->http->FindSingleNode(".//th[contains(text(), 'Hotel order no:')]/following::td[1]", $table);
                $it['GuestNames'] = $this->http->FindSingleNode(".//th[contains(text(), 'Booked in Name:')]/following::td[1]", $table);
                $it['Guests'] = count($this->http->FindNodes(".//div[contains(text(), 'adult')]", $table));
                $it['Kids'] = count($this->http->FindNodes(".//div[contains(text(), 'child')]", $table));
                $it['RoomType'] = $this->http->FindSingleNode(".//th[contains(text(), 'Room Type:')]/following::td[1]", $table);
                $it['CheckInDate'] = strtotime($this->http->FindSingleNode(".//th[contains(text(), 'Arrival:')]/following::td[1]", $table));
                $it['CheckOutDate'] = strtotime($this->http->FindSingleNode(".//th[contains(text(), 'Departure:')]/following::td[1]", $table));
                $it['Status'] = $this->http->FindSingleNode(".//th[contains(text(), 'Booking status:')]/following::td[1]", $table);
                $it['Address'] = implode(', ', $this->http->FindNodes(".//th[contains(text(), 'Address:')]/following::td[1]/text()", $table));
                $it['Phone'] = $this->http->FindSingleNode(".//th[contains(text(), 'Phone:')]/following::td[1]", $table);
                $trip[] = $it;
            }*/
        }

        $its = $this->http->XPath->query("//div[@id = 'confirmation-hotel-details']/div[contains(@class, 'content')]/div[contains(@class, 'highlight-row')]");
        $this->logger->notice("Total {$its->length} hotel reservations were found");

        foreach ($its as $item) {
            $it = [];
            $it['Kind'] = 'R';
            $it['ConfirmationNumber'] = $this->http->FindSingleNode('./div[last()]', $item);

            if (stripos($it['ConfirmationNumber'], '|') !== false) {
                $it['ConfirmationNumbers'] = explode('|', $it['ConfirmationNumber']);
                $it['ConfirmationNumber'] = $it['ConfirmationNumbers'][0];
                $it['ConfirmationNumbers'] = implode(', ', $it['ConfirmationNumbers']);
            }
            $this->logger->info(sprintf('Parse Hotel #%s', $it['ConfirmationNumber']), ['Header' => 4]);
            $it['HotelName'] = $this->http->FindSingleNode('./div[2]/ul/li[1]', $item);
            $it['Address'] = $this->http->FindSingleNode('./div[2]/ul/li[3]', $item);
            $it['RoomType'] = $this->http->FindSingleNode('./div[3]', $item);

            foreach (['CheckInDate' => 1, 'CheckOutDate' => 2] as $key => $value) {
                $s = $this->http->FindSingleNode('./div[4]/ul/li[' . $value . ']', $item);
                $it[$key] = strtotime(str_replace('/', '.', $s));
            }
            // Total
            $it['Total'] = PriceHelper::cost($this->http->FindSingleNode("//div[@id = 'confirmation-pricing-details-total']/div/h3[@class = 'pull-right']", null, true, self::BALANCE_REGEXP_EXTENDED));
            // Currency
            $it['Currency'] = $this->http->FindSingleNode('//span[contains(text(), "Booking Price including")]', null, true, "/\(([A-Z]{3})\)\s*$/");

            $this->logger->debug('Parsed Itinerary:');
            $this->logger->debug(var_export($it, true), ['pre' => true]);

            $trip[] = $it;
        }

        return $trip;
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) || empty($region)) {
            $region = 'www.zuji.com.hk';
        }

        return $region;
    }
}
