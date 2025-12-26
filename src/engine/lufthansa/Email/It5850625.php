<?php

namespace AwardWallet\Engine\lufthansa\Email;

class It5850625 extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-10514663.eml";

    public $reBody = [
        'de' => ['Share myTrip', 'Fluginformation'],
    ];
    public $reSubject = [
        '#Reiseinformation!#',
    ];
    public $lang = '';
    public static $dict = [
        'de' => [
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
        "de" => [
            "januar"    => 0, "jan" => 0,
            "februar"   => 1, "feb" => 1,
            "mae"       => 2, "maerz" => 2, "märz" => 2, "mrz" => 2, "mär" => 2,
            "apr"       => 3, "april" => 3,
            "mai"       => 4,
            "juni"      => 5, "jun" => 5,
            "jul"       => 6, "juli" => 6,
            "august"    => 7, "aug" => 7,
            "september" => 8, "sep" => 8,
            "oktober"   => 9, "okt" => 9,
            "nov"       => 10, "november" => 10,
            "dez"       => 11, "dezember" => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    private $tot;
    private $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "It5850625",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'Miles and more logo')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "lufthansa.com") !== false;
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

    protected function AssignLang($body)
    {
        $this->lang = "";

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        if (empty($this->lang)) {
            return false;
        }

        return true;
    }

    private function parseEmail()
    {
        $its = [];
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = TRIP_CODE_UNKNOWN;
        $it['Passengers'] = $this->http->FindNodes("//text()[contains(.,'" . $this->t('Passagier') . "') and not(contains(.,'Informationen'))]/following::text()[normalize-space(.)][1]");
        $xpath = "//*[contains(text(),'Flug') and contains(text(),'Reisezeit')]/ancestor::tr[1][contains(.,'Durchgeführt von')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("(./preceding::table[contains(.,'Reisezeit') and contains(.,';')]/descendant::tr[2])[last()]", $root, false, "#(\S{3,}\s+\d+,\s+\d{4})#")));

            $node = $this->http->FindSingleNode(".", $root);

            if (preg_match("#Flug\s*:\s+(\d+)\s+Reisezeit\s*:\s+(.+?)\s+Durchgeführt\s+von\s*:\s+(.+?)\s*$#", $node, $m)) {
                $seg['FlightNumber'] = $m[1];
                $seg['Duration'] = $m[2];
                $seg['Aircraft'] = $m[3];
                //TODO:  ???
                $seg['AirlineName'] = 'LH'; //not true
            }
            $node = $this->http->FindSingleNode("./following::table[normalize-space(.)][1]", $root);

            if (preg_match("#(\d+:\d+)\s+([A-Z]{3})\s+(.+)\s*\(#", $node, $m)) {
                $seg['DepDate'] = strtotime($m[1], $date);
                $seg['DepCode'] = $m[2];
                $seg['DepName'] = $m[3];
            }
            $node = $this->http->FindSingleNode("./following::table[normalize-space(.)][2]", $root);

            if (preg_match("#(\d+:\d+)\s+([A-Z]{3})\s+(.+)\s*\(#", $node, $m)) {
                $seg['ArrDate'] = strtotime($m[1], $date);
                $seg['ArrCode'] = $m[2];
                $seg['ArrName'] = $m[3];
            }

            $it['TripSegments'][] = $seg;
        }
        $its[] = $it;

        return $its;
    }

    private function normalizeDate($date)
    {
        $str = $date;
        $year = date('Y', $this->date);
        $in = [
            '#^[\S\s]*(\d{2})\s+(\D{3,})\s*$#',
        ];
        $out = [
            '$1 $2 ' . $year,
        ];
        $str = preg_replace($in, $out, $date);
        $str = $this->dateStringToEnglish($str);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
