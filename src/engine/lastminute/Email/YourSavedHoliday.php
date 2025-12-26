<?php

namespace AwardWallet\Engine\lastminute\Email;

use AwardWallet\Engine\MonthTranslate;

class YourSavedHoliday extends \TAccountChecker
{
    public $mailFiles = "lastminute/it-8731775.eml, lastminute/it-8731789.eml";
    public $reFrom = "sales@holidays.lastminute.com";
    public $reSubject = [
        "en"=> "Your Saved Holiday - holidays.lastminute.com",
    ];
    public $reBody = '.lastminute.com';
    public $reBody2 = [
        "en"=> "Your Holiday Details",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText("Reference");

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->nextText("Lead Guest")];

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
        $xpath = "//text()[" . $this->starts("Flight Number") . "]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]", $root, true, "#Flight Number\s+\w{3}(\d+)#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./following-sibling::tr[1]", $root, true, "#\s+([A-Z]{3})\s+to\s+#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./following-sibling::tr[1]", $root, true, "#(.*?)\s+[A-Z]{3}\s+to\s+#");

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[2]", $root, true, "#Dep\.?\s+(.*?)\s+Arr\.?#")));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root, true, "#\s+([A-Z]{3})$#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./following-sibling::tr[1]", $root, true, "#\s+to\s+(.*?)\s+[A-Z]{3}$#");

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[2]", $root, true, "#Arr\.?\s+(.+)#")));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#Flight Number\s+(\w{3})\d+#");

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[2]", $root, true, "#\((.*?)\)#");

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

        $nodes = $this->http->XPath->query("//text()[" . $this->eq("Room") . "]/ancestor::tr[1]/..");

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->nextText("Reference");

            // TripNumber
            // ConfirmationNumbers

            // Hotel Name
            $it['HotelName'] = $this->http->FindSingleNode("./tr[1]", $root);

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]/td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+to\s+#")));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]/td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#\s+to\s+(.*?)\s+\(#")));

            // Address
            $it['Address'] = implode(", ", $this->http->FindNodes("./tr[2]/td[3]/descendant::text()[normalize-space(.)][position()>1]", $root));

            // DetailedAddress

            // Phone
            // Fax
            // GuestNames
            $it['GuestNames'] = [$this->nextText("Lead Guest")];

            // Guests
            $it['Guests'] = $this->http->FindSingleNode("./tr[3]/td[3]", $root, true, "#(\d+) People#");

            // Kids
            // Rooms
            // Rate
            // RateType

            // CancellationPolicy
            // RoomType
            $it['RoomType'] = $this->http->FindSingleNode("./tr[3]/td[3]", $root, true, "#(.*?)(\s+\(|-)#");

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
            "#^(\d+) ([^\s\d]+) '(\d{2})$#", //24 Aug '15
            "#^(\d+) ([^\s\d]+) '(\d{2}) (\d+:\d+)$#", //24 Aug '15 06:10
        ];
        $out = [
            "$1.$2.20$3",
            "$1.$2.20$3, $4",
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
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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
