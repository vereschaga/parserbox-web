<?php

namespace AwardWallet\Engine\qantas\Email;

class ChangeNotification extends \TAccountChecker
{
    public $mailFiles = "qantas/it-5348824.eml";
    public $reFrom = "do-not-reply@qantas.com.au";
    public $reBody = [
        "en" => "Your new flight details are as follows",
    ];
    public $reSubject = [
        "en" => "Qantas Flight Change Notification",
    ];

    public static $dictionary = [
        "en" => [
            'reservation' => 'Booking Reference',
            'Details'     => ['Date', 'From'],
        ],
    ];

    public $lang = "en";

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6,
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('reservation') . "')]", null, true, "#[A-Z\d]{5,}#");

        $w = $this->t('Details');

        if (!is_array($w)) {
            $w = [$w];
        }
        $rule = implode(' and ', array_map(function ($s) {
            return "contains(.,'{$s}')";
        }, $w));
        $xpath = "//tr[{$rule} and count(descendant::tr)=0]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[2]", $root)));

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $this->http->FindSingleNode("./td[1]", $root), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match("#^\s*(\d+:\d+)\s*(.+?)\s*$#", $this->http->FindSingleNode("./td[3]", $root), $m)) {
                $seg['DepDate'] = strtotime($m[1], $date);
                $seg['DepName'] = $m[2];
            }
            $seg['DepartureTerminal'] = $this->http->FindSingleNode("./td[4]", $root);

            if (preg_match("#^\s*(\d+:\d+)\s*(.+?)\s*$#", $this->http->FindSingleNode("./td[5]", $root), $m)) {
                $seg['ArrDate'] = strtotime($m[1], $date);
                $seg['ArrName'] = $m[2];
            }
            $seg['ArrivalTerminal'] = $this->http->FindSingleNode("./td[6]", $root);
            $seg['Duration'] = $this->http->FindSingleNode("./td[7]", $root);
            $seg['Cabin'] = $this->http->FindSingleNode("./td[8]", $root);
            $it['Status'] = $this->http->FindSingleNode("./td[9]", $root);
            $seg = array_filter($seg);
            $it['TripSegments'][] = $seg;
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'qantas.com.au')]")->length > 0) {
            foreach ($this->reBody as $re) {
                if ($this->http->XPath->query("//text()[contains(.,'{$re}')]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
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

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        foreach (self::$dictionary as $lang => $re) {
            if (strpos($this->http->Response["body"], $re['reservation']) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'ChangeNotification',
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
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
            "#^(\d+\s+\S{3}\s+\d{2})$#",
        ];
        $out = [
            "$1",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
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
