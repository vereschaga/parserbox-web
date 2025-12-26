<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class CodeShareCheckInNotificationPlain extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-10613651.eml, mileageplus/it-10692671.eml, mileageplus/it-10742308.eml, mileageplus/it-10879732.eml, mileageplus/it-8016899.eml, mileageplus/it-8049898.eml";
    public $reFrom = "unitedairlines@united.com";
    public $reSubject = [
        "en"=> "CodeShare check-in notification",
    ];
    public $reBody = 'United';
    public $reBody2 = [
        "en"=> "The first flight in your upcoming",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $text = strip_tags(preg_replace("#<br[^>]*>#", "\n", $this->text));
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#United confirmation number:\s+(.+)#", $text);

        // TripNumber
        // Passengers
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

        $itsegment = [];

        if (preg_match("#Flight:\s+United flight\s+(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s+operating as\s+(?<Operator>.*?)\s+flight\s+\w{2}\d+#", $text, $m)) {
            // FlightNumber
            $itsegment['FlightNumber'] = $m['FlightNumber'];

            // AirlineName
            $itsegment['AirlineName'] = $m['AirlineName'];

            // Operator
            $itsegment['Operator'] = $m['Operator'];
        }

        if (preg_match("#Departs:\s+(?<Time>\d+:\d+\s+[ap].m.)\s+on\s+(?<Date>[^\s\d]+\s+\d+)\s+from\s+(?:(?<Code>[A-Z]{3})|(?<Name>.*?)\s+\((?<Code2>[A-Z]{3})(?:\s+.*)?\))\n#", $text, $m)) {
            // DepCode
            $itsegment['DepCode'] = !empty($m['Code']) ? $m['Code'] : $m['Code2'];

            // DepName
            if (isset($m['Name'])) {
                $itsegment['DepName'] = $m['Name'];
            }

            // DepartureTerminal

            // DepDate
            $itsegment['DepDate'] = $this->normalizeDate($m['Date'] . ', ' . $m['Time']);
        }
        // Arrives: 2:00 p.m. on November 27 at Washington, DC, US (IAD - Dulles)
        // Arrives: 3:59 p.m. on September 3 at YYZ
        if (preg_match("#Arrives:\s+(?<Time>\d+:\d+\s+[ap]\.m\.)\s+on\s+(?<Date>[^\s\d]+\s+\d+)\s+at\s+(?:(?<Code>[A-Z]{3})|(?<Name>.*?)\s+\((?<Code2>[A-Z]{3})(?: - .*)?\))\n#", $text, $m)) {
            // ArrCode
            $itsegment['ArrCode'] = !empty($m['Code']) ? $m['Code'] : $m['Code2'];

            // ArrName
            if (isset($m['Name'])) {
                $itsegment['ArrName'] = $m['Name'];
            }

            // ArrivalTerminal

            // ArrDate
            $itsegment['ArrDate'] = $this->normalizeDate($m['Date'] . ', ' . $m['Time']);
        }

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
        $this->date = EmailDateHelper::calculateOriginalDate($this, $parser);

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->text = $parser->getHTMLBody();

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
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
        // $this->logger->info($str);
        $in = [
            "#^([^\s\d]+)\s+(\d+),\s+(\d+:\d+)\s+([ap])\.m\.$#", //August 23, 7:50 p.m.
        ];
        $out = [
            "$2 $1 %Y%, $3 $4m",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|Y)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // $this->logger->info($str);
        return EmailDateHelper::parseDateRelative("D", $this->date, true, $str);
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
