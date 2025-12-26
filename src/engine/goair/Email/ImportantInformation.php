<?php

namespace AwardWallet\Engine\goair\Email;

use AwardWallet\Engine\MonthTranslate;

class ImportantInformation extends \TAccountChecker
{
    public $mailFiles = "goair/it-10098022.eml, goair/it-10140694.eml, goair/it-10182449.eml, goair/it-30370794.eml, goair/it-30419994.eml, goair/it-30431371.eml, goair/it-30760422.eml";
    public $reFrom = "flightstatus@goair.in";
    public $reSubject = [
        "en"=> "Important Information about Your Flight",
    ];
    public $reBody = 'GoAir';
    public $reBody2 = [
        "en" => "has been rescheduled",
        "en2"=> "has been pre-poned",
        "en3"=> "is CANCELLED",
        "en4"=> "is scheduled",
    ];

    public static $dictionary = [
        "en" => [
            "has been rescheduled"=> ["has been rescheduled", "has been pre-poned", "is CANCELLED", "is scheduled"],
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = [];
        $it['Kind'] = "T";

        $text = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("has been rescheduled")) . "]");

        if (preg_match("#^For your PNR (?<RecordLocator>[A-Z\d]+) on GoAir flight (?<AirlineName>\w{2})-(?<FlightNumber>\d+) from (?<DepName>.*?) \((?<DepCode>[A-Z]{3})\) to (?<ArrName>.*?) \((?<ArrCode>[A-Z]{3})\) on .*? (?:has been rescheduled|has been pre-poned) to depart at (?<DepDate>.*?)\.#", $text, $m)) {
            $it['RecordLocator'] = $m["RecordLocator"];

            $itsegment = [];
            $itsegment['FlightNumber'] = $m["FlightNumber"];
            $itsegment['AirlineName'] = $m["AirlineName"];
            $itsegment['DepCode'] = $m["DepCode"];
            $itsegment['DepName'] = $m["DepName"];
            $itsegment['ArrCode'] = $m["ArrCode"];
            $itsegment['ArrName'] = $m["ArrName"];
            $itsegment['DepDate'] = strtotime($this->normalizeDate($m["DepDate"]));
            $itsegment['ArrDate'] = MISSING_DATE;

            $it['TripSegments'][] = $itsegment;
            $itineraries[] = $it;
        } elseif (preg_match("#^GoAir (?:regrets to inform you that your )?flight (?<AirlineName>\w{2})-(?<FlightNumber>\d+) from (?<DepName>.*?) to (?<ArrName>.*?) on .*? has been rescheduled to depart at (?<DepDate>.*?)\.#", $text, $m)) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;

            $itsegment = [];
            $itsegment['FlightNumber'] = $m["FlightNumber"];
            $itsegment['AirlineName'] = $m["AirlineName"];
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            $itsegment['DepName'] = $m["DepName"];
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            $itsegment['ArrName'] = $m["ArrName"];
            $itsegment['DepDate'] = strtotime($this->normalizeDate($m["DepDate"]));
            $itsegment['ArrDate'] = MISSING_DATE;

            $it['TripSegments'][] = $itsegment;
            $itineraries[] = $it;
        } elseif (preg_match("#^GoAir flight (?<AirlineName>\w{2})-(?<FlightNumber>\d+) from (?<DepName>.*?) to (?<ArrName>.*?) on .*? has been pre-poned to depart at (?<DepDate>.*?)\.#", $text, $m)) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;

            $itsegment = [];
            $itsegment['FlightNumber'] = $m["FlightNumber"];
            $itsegment['AirlineName'] = $m["AirlineName"];
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            $itsegment['DepName'] = $m["DepName"];
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            $itsegment['ArrName'] = $m["ArrName"];
            $itsegment['DepDate'] = strtotime($this->normalizeDate($m["DepDate"]));
            $itsegment['ArrDate'] = MISSING_DATE;

            $it['TripSegments'][] = $itsegment;
            $itineraries[] = $it;
        } elseif (preg_match("#^For your PNR (?<RecordLocator>[A-Z\d]+) on GoAir flight (?<AirlineName>\w{2})-(?<FlightNumber>\d+) from (?<DepName>.*?) \((?<DepCode>[A-Z]{3})\) to (?<ArrName>.*?) \((?<ArrCode>[A-Z]{3})\) on (?<DepDate>.*?) is (?<status>CANCELLED)\.#", $text, $m)) {
            $it['RecordLocator'] = $m["RecordLocator"];
            $it['Status'] = $m['status'];
            $it['Cancelled'] = true;

            $itsegment = [];
            $itsegment['FlightNumber'] = $m["FlightNumber"];
            $itsegment['AirlineName'] = $m["AirlineName"];
            $itsegment['DepCode'] = $m["DepCode"];
            $itsegment['DepName'] = $m["DepName"];
            $itsegment['ArrCode'] = $m["ArrCode"];
            $itsegment['ArrName'] = $m["ArrName"];
            $itsegment['DepDate'] = strtotime($this->normalizeDate($m["DepDate"]));
            $itsegment['ArrDate'] = MISSING_DATE;

            $it['TripSegments'][] = $itsegment;
            $itineraries[] = $it;
        } elseif (preg_match("#^Your Flight (?<AirlineName>\w{2})-(?<FlightNumber>\d+) from .*? to .*? on (?<DepDate>.+?\d{4}) is scheduled to depart from (?<DepCode>[A-Z]{3}) at (?<DepTime>\d+:\d+.*?) and arrive (?<ArrCode>[A-Z]{3}) (?<ArrTime>\d+:\d+.*?)\.#", $text, $m)) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;

            $itsegment = [];
            $itsegment['FlightNumber'] = $m["FlightNumber"];
            $itsegment['AirlineName'] = $m["AirlineName"];
            $itsegment['DepCode'] = $m["DepCode"];
            $itsegment['ArrCode'] = $m["ArrCode"];
            $itsegment['DepDate'] = strtotime($this->normalizeDate($m["DepDate"] . ' ' . $m["DepTime"]));
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($m["DepDate"] . ' ' . $m["ArrTime"]));

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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!self::detectEmailByBody($parser)) {
            return null;
        }

        $this->http->FilterHTML = true;
        $itineraries = [];

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
        return count(self::$dictionary) * 2;
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
        //		 $this->http->log($str);
        $in = [
            "#^(\d+:\d+) hrs on (\d+) ([^\s\d]+) '(\d{2})$#", //20:10 hrs on 22 Nov '17
            "#^(\d+) ([^\s\d]+) '(\d{2}) at (\d+:\d+) hrs$#", //06 Sep '18 at 18:30 hrs
            "#^(\d+)\w* ([^\s\d]+) (\d{4}) (\d+:\d+) hrs$#i", //11th Nov 2016 19:55 Hrs
        ];
        $out = [
            "$2 $3 $4, $1",
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
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
