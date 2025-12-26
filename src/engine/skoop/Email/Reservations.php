<?php

namespace AwardWallet\Engine\skoop\Email;

use AwardWallet\Engine\MonthTranslate;

class Reservations extends \TAccountChecker
{
    public $reFrom = "@bcdcms.com";
    public $reSubject = [
        "en"=> "Reservation made through",
    ];
    public $reBody = ['bcdcms.com', '/bcd-enterprice-logo.jpg'];
    public $reBody2 = [
        "en" => "Offer Detail",
        "en2"=> "Transaction Detail",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $xpath = "//text()[normalize-space(.)='Segment Type:']/ancestor::p[1]";
        $nodes = $this->http->XPath->query($xpath);
        $hotels = [];
        $cars = [];
        $transfers = [];
        $airs = [];
        $trains = [];

        foreach ($nodes as $root) {
            $stype = $this->nextText("Segment Type:", $root);

            switch ($stype) {
                case "AIR":
                    if ($rl = $this->re("#^([A-Z]{6})$#", $this->nextText("PNR:", $root))) {
                        $airs[$rl][] = $root;
                    } elseif ($rl = $this->nextText("Confirmation Number:", $root)) {
                        $airs[$rl][] = $root;
                    } else {
                        $this->http->log("RL not found");

                        return;
                    }

                break;

                case "CAR RENTAL":
                    $cars[] = $root;

                break;

                case "HOTEL":
                    $hotels[] = $root;

                break;

                case "GROUND":
                    $transfers[] = $root;

                break;

                case "TRAIN":
                    if ($rl = $this->nextText("Confirmation Number:", $root)) {
                        $trains[$rl][] = $root;
                    } else {
                        $this->http->log("Train RL not found");

                        return;
                    }

                break;

                default:
                    $this->http->log("Unknown segment type {$stype}");

                    return;

                break;
            }
        }

        //###############
        //##   AIRS   ###
        //###############

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_filter([$this->nextText("Name Of Passenger:", $roots[0])]);

            // TicketNumbers
            // AccountNumbers
            // Cancelled
            if (count($roots) == 1) {
                // TotalCharge
                $it['TotalCharge'] = $this->amount($this->nextText("Total Charges:", $root));
                // Currency
                $it['Currency'] = $this->currency($this->nextText("Total Charges:", $root));
            }
            // BaseFare
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory

            foreach ($roots as $root) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->nextText("Flight number:", $root);

                // DepCode
                $itsegment['DepCode'] = $this->nextText("Destination short:", $root);

                // DepName
                $itsegment['DepName'] = $this->nextText("Destination full name:", $root);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->nextText("Departure terminal gate:", $root);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextText("Departure date:", $root) . ', ' . $this->nextText("Departure time:", $root)));

                // ArrCode
                $itsegment['ArrCode'] = $this->nextText("Arrival airport short:", $root);

                // ArrName
                $itsegment['ArrName'] = $this->nextText("Arrival airport full:", $root);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->nextText("Arrival terminal / gate:", $root);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->nextText("Arrival date:", $root) . ', ' . $this->nextText("Arrival time:", $root)));

                // AirlineName
                $itsegment['AirlineName'] = $this->nextText("Airline marketing & operating carrier:", $root);

                // Operator
                // Aircraft
                $itsegment['Aircraft'] = $this->nextText("Aircraft:", $root);

                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->nextText("Class:", $root);

                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                $itsegment['Duration'] = $this->nextText("Duration:", $root);

                // Meal
                $itsegment['Meal'] = $this->nextText("Meal basis:", $root);

                // Smoking
                // Stops
                $itsegment['Stops'] = $this->nextText("Number of stops:", $root);

                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }

        //#################
        //##   TRAINS   ###
        //#################

        foreach ($trains as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_filter([$this->nextText("Name Of Passenger:", $roots[0])]);

            // TicketNumbers
            // AccountNumbers
            // Cancelled
            if (count($roots) == 1) {
                // TotalCharge
                $it['TotalCharge'] = $this->amount($this->nextText("Total Charges:", $root));
                // Currency
                $it['Currency'] = $this->currency($this->nextText("Total Charges:", $root));
            }
            // BaseFare
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory
            $it["TripCategory"] = TRIP_CATEGORY_TRAIN;

            foreach ($roots as $root) {
                $itsegment = [];
                // FlightNumber
                $itsegment["FlightNumber"] = $this->nextText("Train number:", $root);

                // DepCode
                $itsegment["DepCode"] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment["DepName"] = $this->nextText("Departure city name:", $root) . ', ' . $this->nextText("Departure station:", $root);

                // DepAddress
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextText("Departure date:", $root) . ', ' . $this->nextText("Departure time:", $root)));

                // ArrCode
                $itsegment["ArrCode"] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment["ArrName"] = $this->nextText("Arrival city name:", $root) . ', ' . $this->nextText("Arrival station:", $root);

                // ArrAddress
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->nextText("Arrival date:", $root) . ', ' . $this->nextText("Arrival time:", $root)));

                // Type
                $itsegment["Type"] = $this->nextText("Rail name:", $root) . ' ' . $this->nextText("Train number:", $root);

                // Vehicle
                // TraveledMiles
                // Cabin
                $itsegment["Cabin"] = $this->nextText("Class of Service:", $root);

                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                $itsegment["Duration"] = $this->nextText("Duration:", $root);

                // Meal
                $itsegment["Meal"] = $this->nextText("Meal basis:", $root);

                // Smoking
                // Stops

                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }

        //#################
        //##   HOTELS   ###
        //#################

        foreach ($hotels as $root) {
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->nextText("Confirmation number:", $root);

            // TripNumber
            // ConfirmationNumbers

            // HotelName
            $it['HotelName'] = $this->nextText("Hotel name:");

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText("Check in date:", $root) . ', ' . $this->nextText("Check in time:", $root)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText("Check out date:", $root) . ', ' . $this->nextText("Check out time:", $root)));

            // Address
            $it['Address'] = $this->nextText("Address of hotel:", $root) . ', ' . $this->nextText("City of hotel:", $root) . ', ' . $this->nextText("Country of hotel:", $root);

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->nextText("Phone number:", $root);

            // Fax
            // GuestNames
            $it['GuestNames'] = array_filter([$this->nextText("Name Of Passenger:", $root)]);

            // Guests
            // Kids
            // Rooms
            $it['Rooms'] = $this->nextText("Number of rooms:", $root);

            // Rate
            $it['Rate'] = $this->nextText("Room rate - amount and currency:", $root);

            // RateType
            // CancellationPolicy
            // RoomType
            $it['RoomType'] = $this->nextText("Room type:", $root);

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            $it['Total'] = $this->amount($this->nextText("Total Charges:", $root));

            // Currency
            $it['Currency'] = $this->currency($this->nextText("Total Charges:", $root));

            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            $it['AccountNumbers'] = $this->nextText("Loyalty number:", $root);

            // Status
            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        //###############
        //##   CARS   ###
        //###############

        foreach ($cars as $root) {
            $it = [];

            $it['Kind'] = "L";

            // Number
            $it['Number'] = $this->nextText("Confirmation Number:", $root);

            // TripNumber
            // PickupDatetime
            $it['PickupDatetime'] = strtotime($this->normalizeDate($this->nextText("Date of pick up:") . ', ' . $this->nextText("Time of pick up:")));

            // PickupLocation
            $it['PickupLocation'] = $this->nextText("Departure location address or venue (category):", $root);

            // DropoffDatetime
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->nextText("Date of arrival:") . ', ' . $this->nextText("Expected time of arrival:")));

            // DropoffLocation
            $it['DropoffLocation'] = $this->nextText("Arrival location address or venue (category):", $root);

            // PickupPhone
            // PickupFax
            // PickupHours
            // DropoffPhone
            // DropoffHours
            // DropoffFax
            // RentalCompany
            $it['RentalCompany'] = $this->nextText("Name of service provider:", $root);

            // CarType
            $it['CarType'] = $this->nextText("Car type description:", $root);

            // CarModel
            // CarImageUrl
            // RenterName
            $it['RenterName'] = $this->nextText("Name Of Passenger:", $root);

            // PromoCode
            // TotalCharge
            $it['TotalCharge'] = $this->amount($this->nextText("Total Charges:", $root));

            // Currency
            $it['Currency'] = $this->currency($this->nextText("Total Charges:", $root));

            // TotalTaxAmount
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // ServiceLevel
            // Cancelled
            // PricedEquips
            // Discount
            // Discounts
            // Fees
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        //####################
        //##   TRANSFERS   ###
        //####################

        foreach ($transfers as $root) {
            $it = [];

            $it['Kind'] = "T";
            // RecordLocator
            $it['RecordLocator'] = $this->nextText("Confirmation Number:", $root);

            if (strpos($it['RecordLocator'], ":") !== false) {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }

            // TripNumber
            // Passengers
            $it['Passengers'] = array_filter([$this->nextText("Name Of Passenger:", $root)]);

            // AccountNumbers
            // Cancelled
            // TotalCharge
            $it['TotalCharge'] = $this->amount($this->nextText("Total Charges:", $root));

            // BaseFare
            // Currency
            $it['Currency'] = $this->currency($this->nextText("Total Charges:", $root));

            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory
            $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;
            $itsegment = [];

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->nextText("Departure location address or venue (category):", $root);

            if (strpos($itsegment['DepName'], ":") !== false) {
                $itsegment['DepName'] = null;
            }

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextText("Date of pick up:") . ', ' . $this->nextText("Time of pick up:")));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->nextText("Arrival location address or venue (category):", $root);

            if (strpos($itsegment['ArrName'], ":") !== false) {
                $itsegment['ArrName'] = null;
            }

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->nextText("Date of arrival:") . ', ' . $this->nextText("Expected time of arrival:")));

            if (strpos($this->nextText("Date of arrival:"), "Expected time of arrival:") !== false) {
                $itsegment['ArrDate'] = MISSING_DATE;
            }

            // $itsegment['Type'] = $this->nextText('Type:', $root);
            $itsegment['Type'] = $this->nextText('Name of service provider:', $root);

            if (strpos($itsegment['Type'], ":") !== false) {
                $itsegment['Type'] = null;
            }

            $freeform = $this->nextText('Freeform text:', $root);

            if (!empty($freeform)) {
                $it['ExtProperties']['FreeformText'] = $freeform;
            }

            $itsegment['Vehicle'] = $this->nextText('Car type description:', $root);

            if (strpos($itsegment['Vehicle'], ":") !== false) {
                $itsegment['Vehicle'] = null;
            }

            $it['TripSegments'][] = $itsegment;

            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // $this->http->log("DETECT BY BODY SCOOP");
        $body = $parser->getHTMLBody();
        $finded = false;

        foreach ($this->reBody as $re) {
            if (strpos($body, $re) !== false) {
                $finded = true;
            }
        }

        if (!$finded) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d{4})/(\d+)/(\d+),\s+(\d+:\d+)$#", //2017/12/31, 13:00
        ];
        $out = [
            "$3.$2.$1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d+\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        return $this->re("#^([A-Z]{3})\s*\d#", $s);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
