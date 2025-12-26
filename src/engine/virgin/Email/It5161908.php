<?php

namespace AwardWallet\Engine\virgin\Email;

class It5161908 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "virgin/it-5161908.eml";

    public $reFrom = "@mobile.virginatlantic.com";
    public $reSubject = [
        "en"=> "Boarding Pass:",
    ];
    public $reBody = 'Virgin Atlantic';
    public $reBody2 = [
        "en"=> "Gate Information",
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Booking Ref']/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter([implode(" ", $this->http->FindNodes("//text()[normalize-space(.)='Passenger Name']/ancestor::tr[1]/following-sibling::tr[1]/td[1]/descendant::text()[normalize-space(.)]"))]);

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

        // $xpath = "//text()[normalize-space(.)='Departing']/ancestor::tr[contains(., 'Arriving')][1]";
        // $nodes = $this->http->XPath->query($xpath);
        // if($nodes->length == 0){
        // $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        // }

        // foreach($nodes as $root){
        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Flight Number']/ancestor::tr[1]/following-sibling::tr[1]/td[1]", null, true, "#^\w{2}(\d+)$#");

        // DepCode
        $itsegment['DepCode'] = $this->http->FindSingleNode("//*[@class='entypo-flight']/ancestor::tr[1]/td[1]");

        // DepName
        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space(.)='Flight Number']/ancestor::tr[1]/following-sibling::tr[1]/td[3]")));

        // ArrCode
        $itsegment['ArrCode'] = $this->http->FindSingleNode("//*[@class='entypo-flight']/ancestor::tr[1]/td[3]");

        // ArrName
        // ArrDate
        $itsegment['ArrDate'] = MISSING_DATE;

        // AirlineName
        $itsegment['AirlineName'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Flight Number']/ancestor::tr[1]/following-sibling::tr[1]/td[1]", null, true, "#^(\w{2})\d+$#");

        // Operator
        // Aircraft
        // TraveledMiles
        // Cabin
        $itsegment['Cabin'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Passenger Name']/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

        // BookingClass
        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Flight Number']/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

        // Duration
        // Meal
        // Smoking
        // Stops
        $it['TripSegments'][] = $itsegment;
        // }
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
            "#^(\d+)([^\d\s]+)\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1 $2 $year, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./:]#", $str)) {
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }
}
