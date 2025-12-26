<?php

namespace AwardWallet\Engine\skywards\Email;

class It4680279 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "skywards/it-4680279.eml";

    public $reFrom = "grouphandling@emirates.com";
    public $reSubject = [
        "en"=> "Group Query",
    ];
    public $reBody = 'Emirates';
    public $reBody2 = [
        "en"=> "GROUP BOOKING REQUEST DETAILS",
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
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        // Passengers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->cost($this->http->FindSingleNode("//text()[normalize-space(.)='Grand Total']/ancestor::tr[1]/td[string-length(normalize-space(.))>1][last()]"));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->http->FindSingleNode("//text()[normalize-space(.)='Grand Total']/ancestor::tr[1]/td[string-length(normalize-space(.))>1][last()-1]"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[normalize-space(.)='Available Options']/ancestor::tr[2]/following-sibling::tr[contains(., 'Adults') and contains(., 'FOC') and .//td[12]]/following-sibling::tr[1]//tr[count(./td)=16]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $cols = array_values(array_filter($this->http->FindNodes("./td", $root), function ($v, $k) { return $v !== ''; }, ARRAY_FILTER_USE_BOTH));
            $cols2 = array_values(array_filter($this->http->FindNodes("./following-sibling::tr/td", $root), function ($v, $k) { return $v !== ''; }, ARRAY_FILTER_USE_BOTH));

            if (count($cols) != 8 || count($cols2) != 9) {
                return null;
            }
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $cols[1];

            // DepCode
            $itsegment['DepCode'] = $cols[4];

            // DepName
            $itsegment['DepName'] = $cols[3];

            // DepDate
            $itsegment['DepDate'] = strtotime($cols[7] . ',' . $cols[5]);

            // ArrCode
            $itsegment['ArrCode'] = $cols2[5];

            // ArrName
            $itsegment['ArrName'] = $cols2[0];

            // ArrDate
            $itsegment['ArrDate'] = strtotime($cols2[8] . ',' . $cols2[6]);

            // AirlineName
            $itsegment['AirlineName'] = $cols[1];

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $cols[2];

            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./ancestor::tr[2]/preceding-sibling::tr[1]/descendant::text()[string-length(normalize-space(.))>1][last()]", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment['Duration'] = implode(" ", array_slice($cols2, 1, 4));

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
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

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
            "#^\w+\s+-\s+\w+,\s+(\w+)\s+(\d+)$#",
            "#^\w+,\s+(\w+)\s+(\d+)\s+(\d+:\d+\s+[AP]M)$#",
        ];
        $out = [
            "$2 $1 $year",
            "$2 $1 $year, $3",
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
