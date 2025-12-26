<?php

namespace AwardWallet\Engine\hoggrob\Email;

class YourJourneyTo extends \TAccountChecker
{
    public $mailFiles = "hoggrob/it-11342400.eml, hoggrob/it-11342419.eml, hoggrob/it-11342430.eml";

    public $reFrom = '@hrgworldwide.com';
    public $reSubject = [
        'Your journey to',
    ];
    public $reBody = [
        'en' => 'Transport',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail($parser->getPlainBody());

        return [
            'emailType'  => "YourJourneyTo",
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (strpos($body, ' HRG ') === false) {
            return false;
        }

        foreach ($this->reBody as $reBody) {
            if (stripos($body, $reBody) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail($body)
    {
        $pos = strpos($body, "Itinerary	");

        if (!empty($pos)) {
            $body = substr($body, $pos);
        }

        $it = ['Kind' => 'T'];

        if (preg_match("#\(reservation\s*\#\s*([A-Z\d]+)\s*\)#", $body, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        if (preg_match("#Traveller\s*:\s*(.+)#", $body, $m)) {
            $it['Passengers'][] = trim($m[1]);
        }

        if (preg_match("#Total\s+Booked\s*:\s*(.+)#", $body, $m)) {
            $it['TotalCharge'] = $this->amount($m[1]);
            $it['Currency'] = $this->currency($m[1]);
        }

        if (preg_match("#Total\s*Estimated\s*:\s*(.+)#", $body, $m)) {
            $it['TotalCharge'] = $this->amount($m[1]);
            $it['Currency'] = $this->currency($m[1]);
        }
        preg_match_all("#\sSegment\s*\#\s*\d+(?:.+\n){1,7}?\r?\n#", $body, $flights);

        if (empty($flights[0])) {
            return [$it];
        }

        foreach ($flights[0] as $stext) {
            $seg = [];

            if (preg_match("#\s+Flight[ ]*:[ ]*.+? ([A-Z\d]{2})\s*(\d{1,5})\s*(?:\n|\(Operated by ([^\)]+))#", $stext, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];

                if (!empty($m[3])) {
                    $seg['Operator'] = trim($m[3]);
                }
            }

            if (preg_match("#\s+Departure[ ]*:[ ]*(.+)? (\d{1,2}/\d{2}.+?)[ ]*(?:\(Terminal ([^\)]+)\))?(?:\n|\r)#", $stext, $m)) {
                $seg['DepName'] = trim($m[1]);
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['DepDate'] = strtotime($this->normalizeDate($m[2]));

                if (!empty($m[3])) {
                    $seg['DepartureTerminal'] = trim($m[3]);
                }
            }

            if (preg_match("#\s+Arrival[ ]*:[ ]*(.+)? (\d{1,2}/\d{2}.+?)[ ]*(?:\s+\(Terminal ([^\)]+)\))?(?:\n|\r)#", $stext, $m)) {
                $seg['ArrName'] = trim($m[1]);
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrDate'] = strtotime($this->normalizeDate($m[2]));

                if (!empty($m[3])) {
                    $seg['ArrivalTerminal'] = trim($m[3]);
                }
            }

            if (preg_match("#\s+Class[ ]*:[ ]*([^\(\n]+)#", $stext, $m)) {
                $seg['Cabin'] = trim($m[1]);
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
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

    private function normalizeDate($str)
    {
        $in = [
            '#^\s*(\d{1,2})/(\d{2})/(\d{4})\s+(\d{2}:\d{2})\s*$#', // 17/08/2017 18:20
        ];
        $out = [
            "$1.$2.$3 $4",
        ];

        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}

        return $str;
    }
}
