<?php

namespace AwardWallet\Engine\jetblue\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-4.eml";

    public $airCodes = [];
    public $airCodesCache = [];
    public $skip = false;

    public function ParseItineraryForUpcomingTrip()
    {
        $this->http->FilterHTML = false;
        $this->http->CreateXPath();

        $http = $this->http;
        $xpath = $http->XPath;
        $result = [
            "Kind"         => "T",
            "TripSegments" => [],
        ];
        $result['RecordLocator'] = $http->FindSingleNode('//*[contains(text(), "confirmation number is")]', null, true, '/confirmation number is\s+(\w+)/ims');

        //$flightRowNodes = $xpath->query('//td[contains(text(), "Date")]/following-sibling::td[contains(text(), "Departs")]/ancestor::tr[1][count(td)=8]/following-sibling::tr[count(td)=6]');
        $flightRowNodes = $xpath->query('//*[descendant-or-self::*[contains(text(), "Date")]]/following-sibling::*[descendant-or-self::*[contains(text(), "Departs")]]/ancestor::tr[1][count((td|th))>6]/following-sibling::tr[count(td)>5]');
        $passengers = [];
        $accountNumbers = [];
        $segments = [];

        foreach ($flightRowNodes as $flightRowNode) {
            $segment = [];
            $baseDate = $http->FindSingleNode('td[1]', $flightRowNode);
            // check for Tue, Oct 02 - Wed, Oct 03
            if (count($baseDateParts = explode('-', $baseDate)) === 2) {
                $baseDate = $baseDateParts;
            }
            $time = str_ireplace("&nbsp;", " ", $http->FindSingleNode('td[2]//br/preceding-sibling::text()[1]', $flightRowNode));

            if (preg_match("/(\d{1,2}:\d{2}).*([ap]\.m)/", $time, $m)) {
                $time = $m[1] . " " . $m[2];
            }
            $segment['DepDate'] = strtotime((is_array($baseDate) ? $baseDate[0] : $baseDate) . ' ' . $time);
            $time = str_ireplace("&nbsp;", " ", $http->FindSingleNode('td[2]//br/following-sibling::text()[1]', $flightRowNode));

            if (preg_match("/(\d{1,2}:\d{2}).*([ap]\.m)/", $time, $m)) {
                $time = $m[1] . " " . $m[2];
            }
            $segment['ArrDate'] = strtotime((is_array($baseDate) ? $baseDate[1] : $baseDate) . ' ' . $time);

            $subj = join(' ', $http->FindNodes('./td[3]//text()', $flightRowNode));

            if (preg_match('#^(.*?)(?:\s+\((\w+)\))?\s+to\s+(.*?)(?:\s+\((\w+)\))?$#', $subj, $m)) {
                $segment['DepName'] = nice($m[1]);
                $segment['DepCode'] = (isset($m[2]) && !empty($m[2])) ? $m[2] : TRIP_CODE_UNKNOWN;
                $segment['ArrName'] = nice($m[3]);
                $segment['ArrCode'] = (isset($m[4]) && !empty($m[4])) ? $m[4] : TRIP_CODE_UNKNOWN;
            }

            $segment['FlightNumber'] = trim($http->FindSingleNode('td[4]//img/@title', $flightRowNode) . ' ' . $http->FindSingleNode('td[4]', $flightRowNode));
            $segment['AirlineName'] = AIRLINE_UNKNOWN;

            $travelerNodes = $xpath->query('td[5]//tr', $flightRowNode);

            if ($travelerNodes->length > 0) {
                $seats = [];

                foreach ($travelerNodes as $travelerNode) {
                    $passengers[] = $http->FindSingleNode('td[1]', $travelerNode);

                    if (strcasecmp($accountNumber = $http->FindSingleNode('td[2]', $travelerNode), 'n/a')) {
                        $accountNumbers[] = $accountNumber;
                    }
                    $seats[] = $http->FindSingleNode('td[3]', $travelerNode);
                }
                // filter empty values
                $segment['Seats'] = implode(', ', array_filter($seats, 'strlen'));
            } elseif ($xpath->query('td', $flightRowNode)->length == 8) {
                $passengers[] = $http->FindSingleNode('td[5]', $flightRowNode);
                $accountNumbers[] = $http->FindSingleNode('td[6]', $flightRowNode);
                $segment['Seats'] = $http->FindSingleNode('td[7]', $flightRowNode);
            }

            if (isset($segment['Seats'])) {
                $segment['Seats'] = trim($segment['Seats'], ' *');
            }
            $segments[] = $segment;
        }
        // internal use only, for checks via CheckConfirmationNumberInternal()
        $result['Passengers'] = array_filter(array_unique($passengers), 'strlen');
        $result['AccountNumbers'] = implode(', ', array_filter(array_unique($accountNumbers), 'strlen'));

        if (!empty($segments)) {
            $result['TripSegments'] = $segments;
        }

        return ['Properties' => [], 'Itineraries' => [$result]];
    }

    public function getAirCode($searchName)
    {
        if (isset($this->airCodesCache[$searchName])) {
            return $this->airCodesCache[$searchName];
        }
        $searchCity = CleanXMLValue(explode(',', $searchName)[0]);
        $results = [];
        $searchCityParts = explode(' ', $searchCity);

        foreach ($this->airCodes as $code => $airportData) {
            // simple search by name
            if (stripos($airportData['Name'], $searchCity) !== false) {
                $this->airCodesCache[$searchName] = $code;

                return $code;
            }
            // find by regions
            foreach ($searchCityParts as $needlePart) {
                foreach ($airportData['Regions'] as $region) {
                    if (stripos(trim($region), trim($needlePart)) !== false) {
                        // count matches
                        $results[$code] = isset($results[$code]) ? $results[$code] + 1 : 1;
                    }
                }
            }
        }
        // return code with a highest matches count
        if (!empty($results)) {
            arsort($results);
            reset($results);

            $this->airCodesCache[$searchName] = key($results);

            return key($results);
        }

        return false;
    }

    public function getCodes()
    {
        $cache = \Cache::getInstance()->get('jetblue_aircodes_GEN3');

        if ($cache !== false) {
            return $cache;
        } else {
            $browser = new \HttpBrowser("none", new \CurlDriver());
            $browser->FilterHTML = false;
            $browser->GetURL('http://www.jetblue.com/BookerData/CityData.aspx');
            $codes = [];

            foreach (explode("\n", $browser->Response['body']) as $line) {
                if (preg_match('/\{"code":"([^"]+)","name":"([^"]+)","cc":"([^"]+)","jb":([^\}]+)};/ims', $line, $matches)) {
                    $codes[$matches[1]] = [
                        'Name'        => $matches[2],
                        'CountryCode' => $matches[3],
                        'Regions'     => array_map('trim', array_values(array_filter(explode(',', $matches[2])))),
                    ];
                }
            }

            if (!empty($codes)) {
                \Cache::getInstance()->set('jetblue_aircodes_GEN3', $codes, 3600 * 24);
            }

            return $codes;
        }
    }

    public function ParseItineraryForUpcomingTripWithBarcode()
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $result = [
            "Kind"         => "T",
            "TripSegments" => [],
        ];

        $result['RecordLocator'] = $http->FindSingleNode('//td[contains(text(), "Confirmation Number:")]/following-sibling::td[1]', null, true, '/(\w+)/ims');
        $result['ReservationDate'] = strtotime($http->FindSingleNode('//*[contains(text(), "Date Booked:")]/following-sibling::td[1]'));
        $passengers = [];
        $accountNumbers = [];

        $passengerRowNodes = $xpath->query('//td[contains(text(), "Name")]/following-sibling::td[contains(text(), "TrueBlue Number")]/ancestor::tr[1]/following-sibling::tr[count(td)=2]/td[last()]//tr');

        foreach ($passengerRowNodes as $passengerRowNode) {
            $passengers[] = beautifulName($http->FindSingleNode('td[1]', $passengerRowNode));
            $accountNumbers[] = $http->FindSingleNode('td[2]', $passengerRowNode);
        }

        // internal use only, for checks via CheckConfirmationNumberInternal()
        $result['Passengers'] = array_filter(array_unique($passengers), 'strlen');
        $result['AccountNumbers'] = implode(', ', array_filter(array_unique($accountNumbers), 'strlen'));

        $baseFare = $http->FindSingleNode('//td[contains(text(), "Fare:")]/following-sibling::td[1]');

        if (preg_match('/(\d+.\d+|\d+)/ims', $baseFare, $matches)) {
            $result['BaseFare'] = $matches[1];
        }
        $total = $http->FindSingleNode('//td[contains(text(), "Total:")]/following-sibling::td[1]');

        if (preg_match('/(\d+.\d+|\d+)/ims', $total, $matches)) {
            $result['BaseFare'] = $matches[1];
        }

        if (preg_match('/([\$]{1})/', $baseFare) || preg_match('/([\$]{1})/', $total)) {
            $result['Currency'] = 'USD';
        } else {
            unset($result['BaseFare']);
            unset($result['Total']);
        }

        $result['BaseFare'] = $http->FindSingleNode('//td[contains(text(), "Fare:")]/following-sibling::td[1]', null, true, '/(\d+.\d+|\d+)/ims');

        $segments = [];
        $flightRowNodes = $xpath->query('//td[contains(text(), "Date")]/following-sibling::td[contains(text(), "Depart")]/following-sibling::td[contains(text(), "Arrive")]/ancestor::tr[1]/following-sibling::tr[not(td[img])]');

        foreach ($flightRowNodes as $flightRowNode) {
            $segment = [];
            $baseDate = $http->FindSingleNode('td[1]', $flightRowNode);
            $segment['FlightNumber'] = $http->FindSingleNode('td[2]', $flightRowNode);
            $segment['AirlineName'] = AIRLINE_UNKNOWN;
            // dep/arr data
            foreach ([['Dep', 3], ['Arr', 4]] as $vars) {
                [$Dep, $index] = $vars;
                // New York City, NY (JFK) 02:29pm
                if (preg_match('/^([^\(]+)\s+\(([^\)]+)\)\s+(.*)$/ims', $http->FindSingleNode("td[{$index}]", $flightRowNode), $matches)) {
                    $segment["{$Dep}Code"] = $matches[1];
                    $segment["{$Dep}Name"] = $matches[2];
                    $segment["{$Dep}Date"] = strtotime($baseDate . ' ' . $matches[3]);
                }
            }

            if (($stops = $http->FindSingleNode('td[5]')) !== null) {
                $segment['Stops'] = $stops;
            }
            $segments[] = $segment;
        }

        if (!empty($segments)) {
            $result['TripSegments'] = $segments;
        }

        return ['Properties' => [], 'Itineraries' => [$result]];
    }

    public function ParseItineraryForUpcomingTrip2()
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $xpath->registerNamespace('php', 'http://php.net/xpath');
        $xpath->registerPhpFunctions(['CleanXMLValue']);

        $result = ['Kind' => 'T'];

        $result['RecordLocator'] = $http->FindSingleNode('(//text()[contains(., "Your confirmation number is")]/following-sibling::*[php:functionString("CleanXMLValue", string()) != ""][1])[1]');

        if (!isset($result['RecordLocator'])) {
            $result['RecordLocator'] = $http->FindSingleNode("//*[contains(., 'Your confirmation number is')]/ancestor-or-self::strong[1]/following-sibling::strong[1]");
        }
        $result['Passengers'] = $http->FindNodes('//table[not(.//table) and .//*[contains(text(), "For a detailed receipt, select a customer")]]//tr[1]/following-sibling::tr[count(td)=2][1]/td[1]');

        $accountNumbers = [];
        $segments = [];
        $segmentNodes = $xpath->query('//tr[
            *[descendant-or-self::*[contains(text(), "Date")]]/
            following-sibling::*[1][descendant-or-self::*[contains(text(), "Departs")]]/
            following-sibling::*[descendant-or-self::*[contains(string(), "operated")]]/
            following-sibling::*[descendant-or-self::*[contains(string(), "Seats")]]
        ]/following-sibling::tr[count(td) > 4]');

        foreach ($segmentNodes as $segmentNode) {
            $segment = [];
            $baseDate = $http->FindSingleNode('./td[1]', $segmentNode);
            $segment['DepDate'] = strtotime($baseDate . ' ' . $http->FindSingleNode('./td[2]/descendant::br/preceding-sibling::node()[1]', $segmentNode));
            $segment['ArrDate'] = strtotime($baseDate . ' ' . $http->FindSingleNode('./td[2]/descendant::br/following-sibling::node()[1]', $segmentNode));

            $subj = nice(join(' ', $http->FindNodes('./td[3]//text()', $segmentNode)));

            if (preg_match('#^(.*?)(?:\s+\(\s*(\w+)\s*\))?\s+to\s+(.*?)(?:\s+\(\s*(\w+)\s*\))?$#', $subj, $m)) {
                $segment['DepName'] = nice($m[1]);
                $segment['DepCode'] = (isset($m[2]) && !empty($m[2])) ? $m[2] : TRIP_CODE_UNKNOWN;
                $segment['ArrName'] = nice($m[3]);
                $segment['ArrCode'] = (isset($m[4]) && !empty($m[4])) ? $m[4] : TRIP_CODE_UNKNOWN;
            }

            $airlineCode = $http->FindSingleNode('./td[4]//img/@alt', $segmentNode);
            $segment['FlightNumber'] = $http->FindSingleNode('./td[4]', $segmentNode);

            if ('B6' == $airlineCode) {
                $segment['AirlineName'] = 'JetBlue';
            } else {
                $segment['AirlineName'] = AIRLINE_UNKNOWN;
            }

            $accountNumbers = array_merge($accountNumbers, array_filter($http->FindNodes('./td[6]//a', $segmentNode), 'strlen'));
            $seats = $http->FindSingleNode('./td[7]', $segmentNode);

            if (!preg_match('/seat/ims', $seats)) {
                $segment['Seats'] = $seats;
            }
            $segments[] = $segment;
        }

        if (!empty($segments)) {
            $result['TripSegments'] = $segments;
        }

        if (!empty($accountNumbers)) {
            $result['AccountNumbers'] = $accountNumbers;
        }

        return ['Properties' => [], 'Itineraries' => [$result]];
    }

    public function ParseGetawaysItinerary()
    {
        $http = $this->http;
        $xpath = $http->XPath;

        $result = [];

        //# Parse trip
        $trip = ['Kind' => 'T'];
        $trip['RecordLocator'] = $http->FindSingleNode('(//text()[contains(., "Your flight confirmation number is")]/following-sibling::*[1])[1]');

        $passengers = [];
        $accountNumbers = [];
        $segments = [];
        $segmentNodes = $xpath->query('//table[tr[
            *[self::td or self::th][contains(string(), "Date")]/
            following-sibling::*[self::td or self::th][contains(string(), "Departs")]/
            following-sibling::*[self::td or self::th][contains(string(), "operated")]/
            following-sibling::*[self::td or self::th][contains(string(), "Terminal")]
        ]]/following-sibling::table/tr[count(td) = 7]');

        foreach ($segmentNodes as $segmentNode) {
            $segment = [];
            $baseDate = $http->FindSingleNode('./td[2]', $segmentNode);
            $segment['DepDate'] = strtotime($baseDate . ' ' . $http->FindSingleNode('./td[3]//br/preceding-sibling::node()[1]', $segmentNode));
            $segment['ArrDate'] = strtotime($baseDate . ' ' . $http->FindSingleNode('./td[3]//br/following-sibling::node()[1]', $segmentNode));

            $point = $http->FindSingleNode('./td[4]//node()[normalize-space(.) = "to" or normalize-space(text()) = "to"]/preceding-sibling::*[1]', $segmentNode);

            if (preg_match('#(.+)\s+\((\w+)\)#ims', $point, $matches)) {
                $segment['DepName'] = $matches[1];
                $segment['DepCode'] = $matches[2];
            }

            $point = $http->FindSingleNode('./td[4]//node()[normalize-space(.) = "to" or normalize-space(text()) = "to"]/following-sibling::*[not(self::br)][1]', $segmentNode);

            if (preg_match('#(.+)\s+\((\w+)\)#ims', $point, $matches)) {
                $segment['ArrName'] = $matches[1];
                $segment['ArrCode'] = $matches[2];
            }

            $airlineCode = $http->FindSingleNode('./td[5]//img/@alt', $segmentNode);
            $segment['FlightNumber'] = implode(' ', array_filter([
                $airlineCode,
                $http->FindSingleNode('./td[5]', $segmentNode),
            ], 'strlen'));

            if ('B6' == $airlineCode) {
                $segment['AirlineName'] = 'JetBlue';
            } else {
                $segment['AirlineName'] = AIRLINE_UNKNOWN;
            }

            $passengers = array_merge($http->FindNodes('./td[6]//table//tr[count(td) = 2]/td[1]', $segmentNode), $passengers);
            $accountNumbers = array_merge($http->FindNodes('./td[6]//table//tr[count(td) = 2]/td[2]', $segmentNode), $accountNumbers);

            $segments[] = $segment;
        }

        $trip['Passengers'] = array_unique(array_filter($passengers));
        $accountNumbers = array_unique(array_filter(array_unique($accountNumbers), function ($elem) {
            return (strlen(trim($elem)) !== 0) && stripos($elem, 'N/A');
        }));

        if (!empty($segments)) {
            $trip['TripSegments'] = $segments;
        }

        if (!empty($accountNumbers)) {
            $trip['AccountNumbers'] = $accountNumbers;
        }

        $result[] = $trip;

        //# Parse reservations

        $staysNodes = $xpath->query('//table[tr[
            *[self::td or self::th][contains(string(), "Check in")]/
            following-sibling::*[self::td or self::th][contains(string(), "Property")]/
            following-sibling::*[self::td or self::th][contains(string(), "Lead")]/
            following-sibling::*[self::td or self::th][contains(string(), "Room type")]
        ]]/following-sibling::table[
            count(./following-sibling::table[contains(string(), "Your activities")]) = 1
        ]/tr[count(td) = 4]');

        foreach ($staysNodes as $stayNode) {
            $reservation = ['Kind' => 'R'];
            $reservation['ConfirmationNumber'] = $http->FindSingleNode('(//text()[contains(., "Your Getaways confirmation number is")]/following-sibling::*[1])[1]');
            $reservation['CheckInDate'] = strtotime($http->FindSingleNode('./td[2]/*[1]', $stayNode));
            $reservation['CheckOutDate'] = strtotime($http->FindSingleNode('./td[2]/*[2]', $stayNode));
            $reservation['HotelName'] = $http->FindSingleNode('./td[3]//*[count(br) = 2]/*[1]', $stayNode);
            $reservation['Address'] = implode(', ', $http->FindNodes('./td[3]//*[count(br) = 2]/text()', $stayNode));
            $reservation['GuestNames'] = $http->FindSingleNode('./td[4]/table/tr[1]/td[1]', $stayNode);
            $reservation['RoomType'] = $http->FindSingleNode('./td[4]/table/tr[1]/td[2]', $stayNode);

            if (preg_match("/Nights:\s*(\d+)\s+Adults:\s*(\d+)\s+Children:\s*(\d+)/ims", $http->FindSingleNode('../following-sibling::table[4]', $stayNode), $matches)) {
                $reservation['Rooms'] = $matches[1];
                $reservation['Guests'] = $matches[2];
                $reservation['Kids'] = $matches[3];
            }

            if (count(array_filter($reservation, 'strlen')) > 1) {
                $result[] = $reservation;
            }
        }

        return ['Properties' => [], 'Itineraries' => $result];
    }

    public function ParseDomesticGroupItineraryPlainText()
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $result = ['Kind' => 'T'];
        $result['RecordLocator'] = $http->FindSingleNode('//text()[contains(., "Confirmation:")]', null, true, '/Confirmation:\s*(\S+)/ims');
        $result['ReservationDate'] = strtotime($http->FindSingleNode('//text()[contains(., "Date Booked:")]', null, true, '/Date Booked:\s*(.+)/ims'));

        $segments = [];

        $headNodes = $xpath->query('//text()[contains(., "Outbound Travel:") and following-sibling::text()[count(following-sibling::text()[contains(., "Base Fare")]) = 1]]');
        $headNodesCount = $headNodes->length;

        foreach ($headNodes as $headNode) {
            $segment = [];
            // Outbound Travel:New York City, NY (JFK) - Burbank, CA (BUR)
            if (preg_match('/^Outbound Travel:\s*(.+)\s+\((\S+)\)\s+-\s+(.+)\s+\((\S+)\)/ims', CleanXMLValue($headNode->nodeValue), $matches)) {
                $segment['DepName'] = $matches[1];
                $segment['DepCode'] = $matches[2];
                $segment['ArrName'] = $matches[3];
                $segment['ArrCode'] = $matches[4];
            }
            $baseDate = null;
            $headNodesCount--;
            $innerNodes = $xpath->query("./following-sibling::text()[
                count(following-sibling::text()[contains(., 'Outbound Travel:')]) = {$headNodesCount} and
                following-sibling::text()[count(following-sibling::text()[contains(., 'Base Fare')]) = 1]
            ]", $headNode);

            foreach ($innerNodes as $innerNode) {
                $value = CleanXMLValue($innerNode->nodeValue);

                if (preg_match('/^Date:\s*(.+)/ims', $value, $matches)) {
                    if (strtotime($matches[1]) !== false) {
                        $baseDate = $matches[1];
                    }
                }

                if (preg_match('/^Flight:\s*(.+)/ims', $value, $matches)) {
                    $segment['FlightNumber'] = $matches[1];
                }
                $segment['AirlineName'] = AIRLINE_UNKNOWN;

                if (isset($baseDate) && preg_match('/^Depart:\s*(.+)/ims', $value, $matches)) {
                    $segment['DepDate'] = strtotime($baseDate . ' ' . $matches[1]);
                }

                if (isset($baseDate) && preg_match('/^Arrive:\s*(.+)/ims', $value, $matches)) {
                    $segment['ArrDate'] = strtotime($baseDate . ' ' . $matches[1]);
                }

                if (preg_match('/^Stops:\s*(\d+)/ims', $value, $matches)) {
                    $segment['Stops'] = $matches[1];
                }
            }

            if (!empty($segment)) {
                $segments[] = $segment;
            }
        }

        if (preg_match('/Base Fare\s*(?:\([^\)]+\)):\s*(\w+)\s*(\S)?\s+(\d+.\d+|\d+)/ims', $http->FindSingleNode('//text()[contains(., "Base Fare ")]'), $matches)) {
            $result['Currency'] = $matches[1];
            $result['BaseFare'] = $matches[3];
        }

        if (preg_match('/Group Total Estimate\s*(?:\([^\)]+\)):\s*(\w+)\s*(\S)?\s+(\d+.\d+|\d+)/ims', $http->FindSingleNode('//text()[contains(., "Group Total Estimate ")]'), $matches)) {
            if (empty($result['Currency'])) {
                $result['Currency'] = $matches[1];
            }
            $result['TotalCharge'] = $matches[3];
        }

        if (!empty($segments)) {
            $result['TripSegments'] = $segments;
        }

        return ['Properties' => [], 'Itineraries' => [$result]];
    }

    public function ParseItineraryChanged()
    {
        $result = ['Kind' => 'T'];
        $result['RecordLocator'] = $this->http->FindSingleNode('//*[contains(text(), "Your Confirmation Number")]/ancestor-or-self::tr[1]/following-sibling::tr[1]/descendant::span[1]');
        $result['Passengers'] = $this->http->FindNodes('//td[contains(text(), "Customer")]/ancestor::tr[1]/following-sibling::tr[count(descendant::td) > 1]/descendant::td[1]');

        foreach ($result['Passengers'] as $k => &$v) {
            $v = beautifulName($v);
        }
        $nodes = $this->http->XPath->query('//*[contains(text(), "Your new flight itinerary:")]/ancestor::tr[1]/following-sibling::tr[count(descendant::td)> 1]');

        for ($i = 1; $i < $nodes->length; $i++) {
            $ts = [];
            $ts['FlightNumber'] = $this->http->FindSingleNode('td[2]', $nodes->item($i), true);
            $ts['AirlineName'] = AIRLINE_UNKNOWN;
            $ts['DepName'] = $this->http->FindSingleNode('td[4]', $nodes->item($i), true);
            $ts['DepCode'] = 'UnknownCode';
            $ts['ArrName'] = $this->http->FindSingleNode('td[6]', $nodes->item($i), true);
            $ts['ArrCode'] = 'UnknownCode';
            $ts['DepDate'] = strtotime($this->http->FindSingleNode('td[5]', $nodes->item($i), true) . ' ' . $this->http->FindSingleNode('td[1]', $nodes->item($i), true));
            $ts['ArrDate'] = strtotime($this->http->FindSingleNode('td[7]', $nodes->item($i), true) . ' ' . $this->http->FindSingleNode('td[1]', $nodes->item($i), true));

            if ($ts['ArrDate'] < $ts['DepDate']) {
                $ts['ArrDate'] = strtotime('+1 day', $ts['ArrDate']);
            }
            $ts['Seats'] = implode(', ', $this->http->FindNodes("//td[contains(text(), 'Customer')]/ancestor::tr[1]/following-sibling::tr[count(descendant::td) > 1]/descendant::td[$i+1]"));
            $result['TripSegments'][] = $ts;
        }
        $this->skip = true;

        return ['Properties' => [], 'Itineraries' => $result];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->airCodes = $this->getCodes();
        $type = $this->getEmailType();

        switch ($type) {
            case "ItineraryForUpcomingTrip":
                // Parser toggled off as this format is covered by emailItineraryForYourUpcomingTripChecker.php
                return null;
                $result = $this->ParseItineraryForUpcomingTrip();

                break;

            case "ItineraryForUpcomingTripWithBarcode":
                $result = $this->ParseItineraryForUpcomingTripWithBarcode();

                break;

            case "ItineraryForUpcomingTrip2":
                // Parser toggled off as this format is covered by emailItineraryForYourUpcomingTripChecker.php
                return null;
                $result = $this->ParseItineraryForUpcomingTrip2();

                break;

            case "GetawaysItinerary":
                // Parser toggled off as this format is covered by emailItineraryForYourUpcomingTripChecker.php
                return null;
                $result = $this->ParseGetawaysItinerary();

                break;

            case "ItineraryChanged":
                $result = $this->ParseItineraryChanged();

                break;

            case "DomesticGroupItineraryPlainText":
                $lines = explode("\n", $parser->getPlainBody());
                $dom = new \DOMDocument();

                foreach ($lines as $line) {
                    if (stripos($line, "I, the undersigned, do hereby certify that I have read, understand, and agree to comply") !== false) {
                        break;
                    }
                    $dom->appendChild($dom->createTextNode($line));
                }
                $this->http->XPath = new \DOMXPath($dom);
                $result = $this->ParseDomesticGroupItineraryPlainText();

                break;

            default:
                $result = 'Undefined email type';

                break;
        }
        /* todo: fix
        if (!$this->skip && $this->RefreshData && !empty($result['RecordLocator']) && !empty($result['Passengers'])) {
            // get last name of the first passenger
            $nameParts = explode(' ', $result['Passengers'][0]);
            $lastName = strtoupper(end($nameParts));

            $errorMsg = $this->CheckConfirmationNumberInternal([
                'reloc' => $result['RecordLocator'],
                'lastname' => $lastName
            ], $itinerary);

            if ($errorMsg === null && !empty($itinerary)) {
                $result = $itinerary;
                $type = "CheckConfirmationNumberInternal";
            }
        }
        */

        return [
            'parsedData' => $result,
            'emailType'  => $type,
        ];
    }

    public static function getEmailTypesCount()
    {
        return 8;
    }

    public function getEmailType()
    {
        if ($this->http->XPath->query('//*[contains(text(), "You\'re all set")]')->length > 0
            || $this->http->XPath->query('//*[contains(text(), "Skip the line, check in now")]')->length > 0
        ) {
            return 'ItineraryForUpcomingTrip';
        }

        if ($this->http->XPath->query('//*[contains(text(), "You\'re set to jet")]')->length > 0) {
            return 'ItineraryForUpcomingTrip2';
        }

        if ($this->http->XPath->query('//img[contains(@src, "barcode.aspx")]')->length > 0) {
            return 'ItineraryForUpcomingTripWithBarcode';
        }

        if ($this->http->XPath->query('//*[contains(text(), "Your JetBlue Getaways Itinerary")]')->length > 0) {
            return 'GetawaysItinerary';
        }

        if ($this->http->FindPreg('/JetBlue Airways Domestic Group Terms/ims')) {
            return 'DomesticGroupItineraryPlainText';
        }

        if ($this->http->FindPreg('/We want to inform you about a schedule change/ims')) {
            return 'ItineraryChanged';
        }

        return 'Undefined';
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['subject']) && ((stripos($headers['subject'], 'JetBlue') !== false) || (stripos($headers['subject'], 'Jet Blue') !== false));
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false !== stripos($parser->emailRawContent, '@jetblue.com')
        || false !== stripos($parser->emailRawContent, '.jetblue.com');
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "jetblue.com") !== false;
    }
}
