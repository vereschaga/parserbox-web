<?php

namespace AwardWallet\Engine\ryanair\Email;

class RyanairTravelItineraryPlain2 extends \TAccountChecker
{
    public $mailFiles = "ryanair/it-5402291.eml, ryanair/it-5590777.eml, ryanair/it-5828869.eml";

    public $reFrom = "itinerary@ryanair.com";
    public $reSubject = [
        "en" => "Ryanair Travel Itinerary",
    ];
    public $reBody = 'ryanair';
    public $reBody2 = [
        "en" => "THIS IS YOUR BOOKING CONFIRMATION, PASSENGER ITINERARY AND RECEIPT EMAIL",
    ];

    public static $dictionary = [
        "en" => [],
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

    protected $result = [];

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            $inputResult = mb_strstr($left, $searchFinish, true);
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (empty(trim($parser->getHTMLBody())) !== true) {
            $body = text($parser->getHTMLBody());
        } else {
            $body = text($parser->getPlainBody());
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $itineraries = $this->parseEmail($body);

        return [
            'parsedData' => ['Itineraries' => [$itineraries]],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty(trim($parser->getHTMLBody())) !== true) {
            $body = text($parser->getHTMLBody());
        } else {
            $body = text($parser->getPlainBody());
        }

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    protected function parseEmail($plainText)
    {
        $this->result['Kind'] = 'T';
        $plainText = str_replace(">", "", $plainText); //garbage in it-5828869.eml
        $this->recordLocator($this->findСutSection($plainText, 'FLIGHT RESERVATION NUMBER', 'THANK YOU FOR BOOKING'));
        $this->parsePassengers($this->findСutSection($plainText, 'PASSENGER(S)', ['All customers are required', 'You must check-in online and print']));
        $this->parseTotal($this->findСutSection($plainText, 'Total paid', 'MANAGE YOUR BOOKING'));

        $this->parseSegments($this->findСutSection($plainText, 'FLIGHT(S) DETAILS', 'PASSENGER(S)'));

        return $this->result;
    }

    protected function recordLocator($recordLocator)
    {
        $recordLocator = preg_replace("#[\[\<]https:\/\/www\.ryanair\.com\/emails.+?[\]\>]#", "", $recordLocator); //garbage like in it-5334445.eml

        if (preg_match('#^\s*([A-Z\d]{5,})#', $recordLocator, $m)) {
            $this->result['RecordLocator'] = $m[1];
        }

        if (preg_match('#FLIGHT\s+STATUS\s+(.+?)\s*$#', $recordLocator, $m)) {
            $this->result['Status'] = $m[1];
        }
    }

    protected function parseTotal($total)
    {
        $total = preg_replace("#[\[\<]https:\/\/www\.ryanair\.com\/emails.+?[\]\>]#", "", $total); //garbage like in it-5334445.eml

        if (preg_match('#(\d[\d\.\,\s]*\d*)\s*([A-Z]{3})#', $total, $m)) {
            $this->result['TotalCharge'] = $m[1];
            $this->result['Currency'] = $m[2];
        }
    }

    protected function parsePassengers($plainText)
    {
        $plainText = preg_replace("#[\[\<]https:\/\/www\.ryanair\.com\/emails.+?[\]\>]#", "", $plainText); //garbage like in it-5334445.eml

        if (preg_match_all("#((?:Mr|Ms)\s*\.?[A-Z\s]+?)\s*\(#us", $plainText, $m)) {
            if (is_array($m[1])) {
                $this->result['Passengers'] = $m[1];
            } else {
                $this->result['Passengers'] = [$m[1]];
            }
        }
    }

    protected function parseSegments($plainText, $segmentsSplitter = '\n\s*From')
    {
        $plainText = preg_replace("#[\[\<]https:\/\/www\.ryanair\.com\/emails.+?[\]\>]#", "", $plainText); //garbage like in it-5334445.eml

        foreach (preg_split('/' . $segmentsSplitter . '/', $plainText, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $value = trim($value);

            if (empty($value) !== true) {
                $this->result['TripSegments'][] = $this->iterationSegments(html_entity_decode($value));
            }
        }
    }

    private function iterationSegments($value)
    {
        $segment = [];
        $date = null;

        if (preg_match('#\(\s*([A-Z\d]{2})\s*(\d+)\s*\)#u', $value, $m)) {
            $segment['AirlineName'] = $m[1];
            $segment['FlightNumber'] = $m[2];
        }

        if (preg_match('#DEPART\s*\(\s*([A-Z]{3})\s*\)\s*(.+?)\s*ARRIVAL\s*\(\s*([A-Z]{3})\s*\)\s*(.+?)\s+(?:\S{3}|\d+)#u', $value, $m)) {
            $segment['DepCode'] = $m[1];

            if (preg_match("#(.+?)\s+(T\d)$#", $m[2], $mm)) {
                $segment['DepName'] = $mm[1];
                $segment['DepartureTerminal'] = $mm[2];
            } else {
                $segment['DepName'] = $m[2];
            }

            $segment['ArrCode'] = $m[3];

            if (preg_match("#(.+?)\s+(T\d)$#", $m[4], $mm)) {
                $segment['ArrName'] = $mm[1];
                $segment['ArrivalTerminal'] = $mm[2];
            } else {
                $segment['ArrName'] = $m[4];
            }

            if (preg_match_all('#\s+\S{3}\s+(\d+\s+\S+\s+\d{4}\s+\d+:\d+)\s*hrs#', $value, $m)) {
                $segment['DepDate'] = strtotime($this->normalizeDate($m[1][0]));
                $segment['ArrDate'] = strtotime($this->normalizeDate($m[1][1]));
            }
        }

        return $segment;
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
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+\s+\S{3}\s+\d{4})\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
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
}
