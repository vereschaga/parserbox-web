<?php

namespace AwardWallet\Engine\qantas\Email;

class DepartureInfo extends \TAccountChecker
{
    public $mailFiles = "qantas/it-5857020.eml";

    public $reBody = [
        'en' => ['Date', 'From'],
    ];
    public $reSubject = [
        'Qantas Departure Information',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

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

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "DepartureInfo",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'qantas')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "qantas.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
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

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Your Booking Reference') . "')]", null, true, "#:\s*([A-Z\d]+)#");
        $it['Passengers'] = $this->http->FindNodes("//text()[contains(.,'Passenger Name')]/ancestor::table[1][contains(.,'Frequent Flyer No.')]/ancestor::tr[1]/following-sibling::tr[normalize-space(.) and descendant::tr[count(.//td[normalize-space(.)])>2]]/descendant::tr[normalize-space(.)]/td[normalize-space(.)][1]");
        $xpath = "//text()[contains(.,'Date')]/ancestor::table[1][contains(.,'From')]/ancestor::tr[1]/following-sibling::tr[normalize-space(.) and descendant::tr[count(.//td[normalize-space(.)])>4]]/descendant::tr[normalize-space(.)]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $date = strtotime($this->dateStringToEnglish($this->http->FindSingleNode("./td[normalize-space(.)][1]", $root)));
            $seg['Cabin'] = $this->http->FindSingleNode("./td[normalize-space(.)][last()]", $root);
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $node = $this->http->FindSingleNode("./td[normalize-space(.)][4]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $node = $this->http->FindSingleNode("./td[normalize-space(.)][2]", $root);

            if (preg_match("#(\d+:\d+)\s*(.+)#", $node, $m)) {
                $seg['DepName'] = $m[2];
                $seg['DepDate'] = strtotime($m[1], $date);
            }
            $node = $this->http->FindSingleNode("./td[normalize-space(.)][3]", $root);

            if (preg_match("#(\d+:\d+)\s*(.+)#", $node, $m)) {
                $seg['ArrName'] = $m[2];
                $seg['ArrDate'] = strtotime($m[1], $date);
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

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
