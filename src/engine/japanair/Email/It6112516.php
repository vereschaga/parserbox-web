<?php

namespace AwardWallet\Engine\japanair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class It6112516 extends \TAccountChecker
{
    public $mailFiles = "japanair/it-11692123.eml, japanair/it-6112516.eml, japanair/it-11713608.eml";

    public $reSubject = [
        "en"=> "Confirmation e-mail",
    ];
    public $reBody = 'Japan Airlines';
    public $reBody2 = [
        "en"=> "Your booking is confirmed!",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $xpathFragments = [
            'blueColor' => 'contains(@color,"#37558b") or contains(@color,"#37558B") or contains(@style,"#37558b") or contains(@style,"#37558B")',
            'redColor'  => 'contains(@color,"#c30000") or contains(@color,"#C30000") or contains(@style,"#c30000") or contains(@style,"#C30000")',
        ];

        $xpath = "//text()[" . $this->eq("Seats") . "]/ancestor::tr[1]/following-sibling::tr[./td[2]]";
        $nodes = $this->http->XPath->query($xpath);
        $seats = [];

        foreach ($nodes as $root) {
            $seats[strtolower(str_replace(" to ", "_", $this->http->FindSingleNode("./td[1]", $root)))][] = $this->http->FindSingleNode("./td[2]", $root);
        }

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->eq("Booking Reference/Confirmation Number:") . "]/following::text()[string-length(normalize-space(.))=6][1]");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq("Date of Birth") . "]/ancestor::tr[1]/preceding-sibling::tr[1]");

        // TicketNumbers
        $it['TicketNumbers'] = array_map('trim', explode(";", $this->http->FindSingleNode("(//text()[" . $this->starts("Ticket Number") . "]/ancestor::p[1])[1]", null, true, "#:(.+)#s")));

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText("Total"));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->http->FindSingleNode("//text()[" . $this->starts("Adult") . "]/following::text()[normalize-space(.)][1]"));

        // Currency
        $it['Currency'] = $this->re("#\(([A-Z]{3})\)#", $this->nextText("Total", null, 3));

        // Tax
        $it['Tax'] = $this->amount($this->nextText("Taxes/Others"));

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[" . $this->eq("Cabin") . "]/ancestor::tr[2]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($segments as $root) {
            $itsegment = [];

            $date = $this->normalizeDate($this->http->FindSingleNode("./td[1]/table", $root));

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('./td[normalize-space(.)][2]/descendant::*[' . $xpathFragments['blueColor'] . '][string-length(normalize-space(.))>2][1]', $root);

            if (preg_match('/^([A-Z\d]{2})\s*(\d+)/', $flight, $matches)) {
                $itsegment['AirlineName'] = $matches[1];
                $itsegment['FlightNumber'] = $matches[2];
            }

            // DepName
            $itsegment['DepName'] = $this->nextText("Dep.", $root, 2);

            if (empty($itsegment['DepName'])) {
                $itsegment['DepName'] = $this->http->FindSingleNode("(.//text()[" . $this->starts("Dep.") . "])[1]/following::text()[normalize-space(.)][1]", $root);
            }

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->re("#Terminal\s+(\w+)#", $this->nextText("Dep.", $root, 3));

            if (empty($itsegment['DepartureTerminal'])) {
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("(.//text()[" . $this->starts("Dep.") . "])[1]/following::text()[normalize-space(.)][2]", $root, true, "#Terminal\s+(\w+)#");
            }

            // DepDate
            $time = $this->nextText("Dep.", $root);

            if (empty($time)) {
                $time = $this->http->FindSingleNode("(.//text()[" . $this->starts("Dep.") . "])[1]", $root, true, "#Dep.\s*(.+)#");
            }

            if (!empty($date) && !empty($time)) {
                $itsegment['DepDate'] = strtotime($time, $date);
            }

            // DepCode
            // ArrCode
            $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->nextText("Arr.", $root, 2);

            if (empty($itsegment['ArrName'])) {
                $itsegment['ArrName'] = $this->http->FindSingleNode("(.//text()[" . $this->starts("Arr.") . "])[1]/following::text()[normalize-space(.)][1]", $root);
            }

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->re("#Terminal\s+(\w+)#", $this->nextText("Arr.", $root, 3));

            if (empty($itsegment['ArrivalTerminal'])) {
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("(.//text()[" . $this->starts("Arr.") . "])[1]/following::text()[normalize-space(.)][2]", $root, true, "#Terminal\s+(\w+)#");
            }

            // ArrDate
            $time = $this->nextText("Arr.", $root);

            if (empty($time)) {
                $time = $this->http->FindSingleNode("(.//text()[" . $this->starts("Arr.") . "])[1]", $root, true, "#Arr.\s*(.+)#");
            }

            if (!empty($date) && !empty($time)) {
                $itsegment['ArrDate'] = strtotime($time, $date);
            }

            // Operator
            $itsegment['Operator'] = $this->http->FindSingleNode(".//text()[" . $this->starts("Operated by") . "]", $root, true, "#Operated by\s+(.+)#");

            // Aircraft
            $itsegment['Aircraft'] = $this->nextText("Aircraft Type", $root);

            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = trim($this->nextText("Cabin", $root), ' :');

            // BookingClass
            $itsegment['BookingClass'] = trim($this->nextText("Booking class", $root), ' :');

            // PendingUpgradeTo
            // Seats
            $skey = strtolower(trim($this->re("#(.*?)(?:,|$)#", $itsegment['DepName'])) . '_' . trim($this->re("#(.*?)(?:,|$)#", $itsegment['ArrName'])));

            if (isset($seats[$skey])) {
                $itsegment['Seats'] = $seats[$skey];
            }

            // Duration
            $duration = $this->http->FindSingleNode('./td[normalize-space(.)][2]/descendant::*[' . $xpathFragments['redColor'] . '][string-length(normalize-space(.))>1][1]/descendant::time[string-length(normalize-space(.))>1][1]', $root, true, '/^(\d[hm\d\s]+)$/i');

            if (!$duration) {
                $duration = $this->http->FindSingleNode('./td[normalize-space(.)][2]/descendant::*[' . $xpathFragments['redColor'] . '][string-length(normalize-space(.))>1][1]', $root, true, '/^(\d[hm\d\s]+)$/i');
            }

            if ($duration) {
                $itsegment['Duration'] = $duration;
            }

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Japan Airline') !== false
            || stripos($from, '@jal.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'Confirmation_' . $this->lang,
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

    //	private function t($word){
    //		if(!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word]))
    //			return $word;
//
    //		return self::$dictionary[$this->lang][$word];
    //	}

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $datePattern = [
            "#(?<month>\w+)\s+(?<day>\d+)\s+(?<week>\w+)#",
        ];

        foreach ($datePattern as $pattern) {
            if (preg_match($pattern, $str, $m)) {
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

                if ($en = \AwardWallet\Engine\MonthTranslate::translate($m['month'], $this->lang)) {
                    $m['month'] = $en;
                }
                $str = EmailDateHelper::parseDateUsingWeekDay($m['day'] . ' ' . $m['month'] . ' ' . $year, $weeknum);

                return $str;
            }
        }
        //		$in = [
        //			"#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4})\s+(\d+:\d+)$#",
        //		];
        //		$out = [
        //			"$1 $2 $3, $4",
        //		];
        //		$str = preg_replace($in, $out, $str);
        //		if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return strtotime($str);
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

    //	private function currency($s){
    //		$sym = [
    //			'€'=>'EUR',
    //			'$'=>'USD',
    //		];
    //		if($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) return $code;
    //		foreach($sym as $f=>$r)
    //			if(strpos($s, $f) !== false) return $r;
    //		return null;
    //	}

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

    //	private function contains($field){
//		$field = (array)$field;
//		if(count($field)==0) return 'false';
//		return implode(" or ", array_map(function($s){ return "contains(normalize-space(.), \"{$s}\")"; }, $field));
//	}
}
