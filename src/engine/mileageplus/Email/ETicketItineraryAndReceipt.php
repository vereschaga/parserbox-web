<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketItineraryAndReceipt extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-6444648.eml, mileageplus/it-6444649.eml";
    public $reFrom = "@united.com";
    public $reSubject = [
        "en"=> "MileagePlus eTicket Itinerary and Receipt for Confirmation",
    ];
    public $reBody = 'United Airlines';
    public $reBody2 = [
        "en"=> "FLIGHT INFORMATION",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $text = str_replace(" ", " ", $this->http->Response['body']);
        // echo $text; die();
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->opt($this->t("Confirmation:")) . "[\s\|]+(\w+)#ms", $text);

        // TripNumber
        // Passengers
        preg_match_all("#\|(\w+/\w+)#", $this->re("#\|Traveler(.*?)FLIGHT INFORMATION#ms", $text), $Passengers);
        $it['Passengers'] = array_unique($Passengers[1]);

        // TicketNumbers
        preg_match_all("#\|\w+/\w+[\s\|]+(\d+)#ms", $this->re("#\|Traveler(.*?)FLIGHT INFORMATION#ms", $text), $TicketNumbers);
        $it['TicketNumbers'] = array_unique($TicketNumbers[1]);

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

        $segments = $this->split("#(\|[^\d\s]+,\s+\d+[^\d\s]+\d{2}[\s\|]+\w{2}\d+\s+)#ms", $this->re("#FLIGHT INFORMATION(.*?)FARE INFORMATION#ms", $text));

        foreach ($segments as $stext) {
            if (!preg_match("#\|(?<Date>[^\d\s]+,\s+\d+[^\d\s]+\d{2})[\s\|]+" .
            "(?<AirlineName>\w{2})(?<FlightNumber>\d+)[\s\|]+" .
            "(?<BookingClass>\w)[\s\|]+" .
            "(?<DepName>.*?)[\s\|]+" .
            "\((?<DepCode>[A-Z]{3})\)\s+(?<DepTime>\d+:\d+\s+[AP]M)[\s\|]+" .
            "(?<ArrName>.*?)[\s\|]+" .
            "\((?<ArrCode>[A-Z]{3})\)\s+(?<ArrTime>\d+:\d+\s+[AP]M)[\s\|]+" .
            "#ms", $stext, $m)) {
                return;
            }

            $date = strtotime($this->normalizeDate($m['Date']));

            $itsegment = [];

            $keys = ['AirlineName', 'FlightNumber', 'DepName', 'DepCode', 'ArrName', 'ArrCode', 'BookingClass'];

            foreach ($keys as $key) {
                $itsegment[$key] = $m[$key];
            }

            $itsegment['DepDate'] = strtotime($m['DepTime'], $date);
            $itsegment['ArrDate'] = strtotime($m['ArrTime'], $date);

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
        $body = $parser->getPlainBody();

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

        $this->http->setBody($parser->getPlainBody());

        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

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
            "#^[^\d\s]+,\s+(\d+)([^\d\s]+)(\d{2})$#", //Wed, 21MAY14
        ];
        $out = [
            "$1 $2 20$3",
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
