<?php

namespace AwardWallet\Engine\wizz\Email;

use AwardWallet\Engine\MonthTranslate;

class BookingConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "wizz/it-10287474.eml, wizz/it-10293928.eml, wizz/it-11702911.eml, wizz/it-11858312.eml, wizz/it-18587025.eml";
    public $reFrom = "reservation@wizztours.com";
    public $reSubject = [
        "en"=> "Booking Confirmation",
    ];
    public $reBody = 'Wizz Tours';
    public $reBody2 = [
        "en"=> "Thank you for booking with",
        "pl"=> "za zarezerwowanie oferty w",
        "de"=> "Vielen Dank für Ihre Buchung mit",
    ];

    public static $dictionary = [
        "en" => [],
        "pl" => [
            "Wizz Air confirmation number:"=> "Numer potwierdzenia Wizz Air:",
            "Name"                         => "Nazwa",
            "Flight number:"               => "Numer lotu:",
            "Departs from"                 => "Wylot z",
            "Arrives to:"                  => "Przylot do:",

            "Accommodation"      => "Zakwaterowanie",
            "Accom. Booking Ref."=> "Zakw. Nr ref. rezerwacji",
            "Hotel:"             => "Hotel:",
            "Check-in"           => "Odprawa",
            "Check-out"          => "Wymeldowanie",
            "Tel:"               => "Tel.:",
            "Fax:"               => "Faks:",
            "Room Type"          => "Typ pokoju",

            "Grand Total"=> "Ogółem",
        ],
        "de" => [
            "Wizz Air confirmation number:"=> "Bestätigungsnummer Wizz Air:",
            "Name"                         => "Name",
            "Flight number:"               => "Flugnummer:",
            "Departs from"                 => "Ab",
            "Arrives to:"                  => "Nach:",

            "Accommodation"      => "Unterbringung",
            "Accom. Booking Ref."=> "Unterbr. Buchungsref.",
            "Hotel:"             => "Hotel:",
            "Check-in"           => "Check-in",
            "Check-out"          => "Check-out",
            "Tel:"               => "Tel:",
            "Fax:"               => "Fax:",
            "Room Type"          => "Zimmertyp",

            "Grand Total"=> "Gesamtsumme",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Wizz Air confirmation number:"));

        // TripNumber
        // Passengers
        $it['Passengers'] = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Name")) . "]/ancestor::tr[1]/following-sibling::tr/td[3]")));

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->starts($this->t("Flight number:")) . "]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Flight number:")) . "]", $root, true, "#\w{2} (\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", $this->nextText($this->t("Departs from"), $root));

            // DepName
            $itsegment['DepName'] = trim($this->re("#(.*?) \([A-Z]{3}\)#", $this->nextText($this->t("Departs from"), $root)), '- ');

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Departs from"), $root, 2)));

            // ArrCode
            $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})\)#", $this->nextText($this->t("Arrives to:"), $root));

            // ArrName
            $itsegment['ArrName'] = trim($this->re("#(.*?) \([A-Z]{3}\)#", $this->nextText($this->t("Arrives to:"), $root)), '- ');

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Arrives to:"), $root, 2)));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Flight number:")) . "]", $root, true, "#(\w{2}) \d+$#");

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;

        foreach ($this->http->XPath->query("//text()[" . $this->eq($this->t("Accommodation")) . "]/following::table[1]") as $root) {
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = trim($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Accom. Booking Ref.")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]", $root), " :");

            // TripNumber
            // ConfirmationNumbers

            // HotelName
            $it['HotelName'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Hotel:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]", $root);

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate(trim($this->http->FindSingleNode("./following-sibling::table[1]//text()[" . $this->eq($this->t("Check-in")) . "]/ancestor::td[1]/following-sibling::td[1]", $root), ": ")));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate(trim($this->http->FindSingleNode("./following-sibling::table[1]//text()[" . $this->eq($this->t("Check-out")) . "]/ancestor::td[1]/following-sibling::td[1]", $root), ": ")));

            // Address
            if (!$it['Address'] = trim(implode(" ", $this->http->FindNodes(".//text()[" . $this->eq($this->t("Hotel:")) . "]/ancestor::td[1]/following-sibling::td[1]/*[position()>1]", $root)))) {
                $it['Address'] = $it['HotelName'];
            }

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->nextText($this->t("Tel:"), $root);

            // Fax
            $it['Fax'] = $this->nextText($this->t("Fax:"), $root);

            // GuestNames
            $it['GuestNames'] = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Name")) . "]/ancestor::tr[1]/following-sibling::tr/td[3]")));

            // Guests
            $it['Guests'] = $this->http->FindSingleNode("./following-sibling::table[2]//text()[" . $this->eq($this->t("Room Type")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[4]", $root);

            // Kids
            $it['Kids'] = $this->http->FindSingleNode("./following-sibling::table[2]//text()[" . $this->eq($this->t("Room Type")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[5]", $root);

            // Rooms
            $it['Rooms'] = $this->http->FindSingleNode("./following-sibling::table[2]//text()[" . $this->eq($this->t("Room Type")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[3]", $root);

            // Rate
            // RateType

            // CancellationPolicy
            // RoomType
            $it['RoomType'] = $this->http->FindSingleNode("./following-sibling::table[2]//text()[" . $this->eq($this->t("Room Type")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", $root);

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            // Currency
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ReservationDate
            // NoItineraries
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
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
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

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
                'TotalCharge' => [
                    "Amount"   => $this->amount($this->nextText($this->t("Grand Total"))),
                    "Currency" => $this->currency($this->nextText($this->t("Grand Total"))),
                ],
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

    private function t($word)
    {
        // $this->http->log($word);
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
            "#(\d+)/(\d+)/(\d+) (\d+:\d+)#", //12/18/17 10:35
            "#(\d+)/(\d+)/(\d+)#", //12/18/17
        ];
        $out = [
            "$2.$1.20$3, $4",
            "$2.$1.20$3",
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
