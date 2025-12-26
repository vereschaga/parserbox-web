<?php

namespace AwardWallet\Engine\oman\Email;

use AwardWallet\Engine\MonthTranslate;

class OnlineCheckInNotification extends \TAccountChecker
{
    public $mailFiles = "oman/it-6428919.eml, oman/it-6498809.eml";
    public $reFrom = "check_in@omanair.com";
    public $reSubject = [
        "en"=> "Online Check_In Notification",
    ];
    public $reBody = 'Oman Air';
    public $reBody2 = [
        "en"=> "Itinerary",
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
        $it['RecordLocator'] = $this->http->FIndSingleNode("//text()[normalize-space(.)='PNR']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[3]");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='Passengers']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]", null, "#\d+\.\s+(.+)#");

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

        // $xpath = "//text()[".$this->eq($this->t("Departing from"))."]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]";
        // $nodes = $this->http->XPath->query($xpath);
        // if($nodes->length == 0){
        // $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        // }

        // foreach($nodes as $root){
        // $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]", $root)));

        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->http->FIndSingleNode("//text()[normalize-space(.)='Flight']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[1]", null, true, "#-\s+\w{2}\s+(\d+)#");

        // DepCode
        $itsegment['DepCode'] = $this->http->FIndSingleNode("//text()[normalize-space(.)='Departure']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[1]/descendant::text()[normalize-space(.)][1]", null, true, "#^[A-Z]{3}$#");

        // DepName
        $itsegment['DepName'] = $this->http->FIndSingleNode("//text()[normalize-space(.)='Departure']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[1]/descendant::text()[normalize-space(.)][2]");

        // DepartureTerminal
        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FIndSingleNode("//text()[normalize-space(.)='Departure']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/following::tr[normalize-space(.) and ./td[3]][1]/td[1]")));

        // ArrCode
        $itsegment['ArrCode'] = $this->http->FIndSingleNode("//text()[normalize-space(.)='Arrival']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[3]/descendant::text()[normalize-space(.)][1]", null, true, "#^[A-Z]{3}$#");

        // ArrName
        $itsegment['ArrName'] = $this->http->FIndSingleNode("//text()[normalize-space(.)='Departure']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[3]/descendant::text()[normalize-space(.)][2]");

        // ArrivalTerminal
        // ArrDate
        $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FIndSingleNode("//text()[normalize-space(.)='Arrival']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/following::tr[normalize-space(.) and ./td[3]][1]/td[3]")));

        // AirlineName
        $itsegment['AirlineName'] = $this->http->FIndSingleNode("//text()[normalize-space(.)='Flight']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[1]", null, true, "#-\s+(\w{2})\s+\d+#");

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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
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
        $in = [
            "#^(\d+:\d+)\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#",
        ];
        $out = [
            "$2 $3 $4, $1",
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
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
