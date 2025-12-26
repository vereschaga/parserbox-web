<?php

namespace AwardWallet\Engine\skywards\Email;

class It4274857 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "skywards/it-4274857.eml";

    public $reFrom = "@emirates.com";
    public $reSubject = [
        "en"=> "Emirates flight re-scheduled notice",
    ];
    public $reBody = 'Emirates';
    public $reBody2 = [
        "en"=> "OUR REFERENCE",
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(., 'OUR REFERENCE')]", null, true, "#OUR REFERENCE:\s+(\w+)#");

        // TripNumber
        // Passengers
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

        $xpath = "//text()[contains(., 'FLIGHT') and contains(., 'CLASS') and contains(., 'STATUS:CONFIRMED')]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".", $root, true, "#FLIGHT\s*:\s*\w{2}\s+(\d+)#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./following::text()[normalize-space(.)][1]", $root, true, "#DEPART\s*:\s*(.*?)\s*ARRIVE\s*:\s*.+#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::text()[normalize-space(.)][4]", $root, true, "#DATE\s*:\s*(.*?)\s*DATE\s*:\s*.+#") . ', ' . $this->http->FindSingleNode("./following::text()[normalize-space(.)][5]", $root, true, "#TIME\s*:\s*(\d+:\d+)\s*TIME\s*:\s*\d+:\d+#")));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./following::text()[normalize-space(.)][1]", $root, true, "#DEPART\s*:\s*.*?\s*ARRIVE\s*:\s*(.+)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::text()[normalize-space(.)][4]", $root, true, "#DATE\s*:\s*.*?\s*DATE\s*:\s*(.+)#") . ', ' . $this->http->FindSingleNode("./following::text()[normalize-space(.)][5]", $root, true, "#TIME\s*:\s*\d+:\d+\s*TIME\s*:\s*(\d+:\d+)#")));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode(".", $root, true, "#FLIGHT\s*:\s*(\w{2})\s+\d+#");

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $this->http->FindSingleNode("./following::text()[normalize-space(.)][6]", $root, true, "#AIRCRAFT\s*:\s*(.+)#");

            // TraveledMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode("./following::text()[normalize-space(.)][7]", $root, true, "#ELAPSED FLYING TIME\s*:\s*(.*?)\s*DIRECT FLIGHT#");

            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
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

        $this->http->FilterHTML = false;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
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
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\d\s]+)\s+(\d+)\s+[^\d\s]+,\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1 $2 20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
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
}
