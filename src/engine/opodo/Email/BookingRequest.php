<?php

namespace AwardWallet\Engine\opodo\Email;

class BookingRequest extends \TAccountChecker
{
    public $mailFiles = "opodo/it-5056052.eml, opodo/it-6018850.eml, opodo/it-6018851.eml";

    public $reFrom = "opodo";
    public $reSubject = [
        "en" => "Booking on request",
        "de" => "Ihre Buchungsbestätigung von Opodo",
    ];
    public $reBody = 'opodo';

    public static $dictionary = [
        "en" => [
            'textTicket' => "This is an e-ticket booking",
            'segment'    => 'Flight\s+details[\s>]*Booking\s+reference:',
            'startSeg'   => 'Outbound|Inbound|Leg',
            'endSeg'     => 'Aircraft type|View fare rules',
        ],
        "de" => [
            'textTicket'                               => "Sie haben ein E-Ticket gebucht",
            'segment'                                  => 'Flugdetails[\s>]*Buchungsnummer:',
            'Contact email address:'                   => 'E-Mail-Adresse:',
            'Payment details for your booking request' => 'Zahlung',
            'Name(s) of traveller(s):'                 => 'Namen der Reisenden:',
            'startSeg'                                 => 'Hinreise|Rückreise|Flug',
            'endSeg'                                   => 'Flugzeugtyp|Alle Tarifbestimmungen',
            'Departing'                                => 'Abreise',
            'Arriving'                                 => 'Ankunft',
            'Important information about your booking' => 'Wichtige Hinweise zu Ihrer Buchung',
        ],
    ];

    public $lang = "";

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
            "mae"       => 2, "maerz" => 2, "märz" => 2, "mrz" => 2,
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

    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[not(contains(@alt,'opodo') or contains(@src,'logo'))]")->length > 0) { //go to parse by another parsers.
            return null;
        }

        if (empty(trim($parser->getHTMLBody())) !== true) {
            $body = text($parser->getHTMLBody());
        } else {
            $body = text($parser->getPlainBody());
        }

        foreach (self::$dictionary as $lang => $re) {
            if (strpos($body, $re['textTicket']) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        //		$body = preg_replace("#[\[\<].*?image.*?[\]\>]#", "", $body);//garbage

        $itineraries = $this->mergeItineraries($this->parseEmail($body));

        $tot = $this->getTotalCurrency($this->parseTotal($this->findСutSection($body, $this->t('Payment details for your booking request'), $this->t('Important information about your booking'))));

        if (!empty($tot['Total'])) {
            if (count($itineraries) === 1) {
                $itineraries[0]['TotalCharge'] = $tot['Total'];
                $itineraries[0]['Currency'] = $tot['Currency'];
            } else {
                return [
                    'parsedData' => ['Itineraries' => $itineraries, 'TotalCharge' => ['Amount' => $tot['Total'], 'Currency' => $tot['Currency']]],
                ];
            }
        }

        return [
            'parsedData' => ['Itineraries' => $itineraries],
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

        foreach (self::$dictionary as $re) {
            if (stripos($body, $re['textTicket']) !== false) {
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
        $its = [];
        $segmentsSplitter = $this->t('segment');
        $subText = $this->findСutSection($plainText, $this->t('Contact email address:'), $this->t('Payment details for your booking request'));
        $subText = preg_replace("#[\[\<].*?image.*?[\]\>]#", "", $subText); //garbage

        foreach (preg_split('/' . $segmentsSplitter . '/s', $subText, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $value = trim($value);

            if (empty($value) !== true && strlen($value) > 200) {
                $this->result = [];
                $this->result['Kind'] = 'T';
                $this->result['RecordLocator'] = $this->recordLocator(substr($value, 0, strpos($value, $this->t('Name(s) of traveller(s):'))));
                $this->result['Passengers'] = $this->parsePassengers($this->findСutSection($value, $this->t('Name(s) of traveller(s):'), $this->t('textTicket')));

                if (preg_match_all("#[\s>]*(?:" . $this->t('startSeg') . ")\s*:\s*(.+?)[\s>]*(?:" . $this->t('endSeg') . ")#s", $value, $v)) {
                    foreach ($v[1] as $s) {
                        $date = strtotime($this->normalizeDate($this->re("#^(\d+.+?\d+),#", $s)));

                        foreach (preg_split('/' . $this->t('Departing') . '/s', $s, -1, PREG_SPLIT_NO_EMPTY) as $valueSeg) {
                            $valueSeg = trim($valueSeg);

                            if (strlen($valueSeg) > 50) {
                                $this->result['TripSegments'][] = $this->iterationSegments($valueSeg, $date);
                            }
                        }
                    }
                }

                $its[] = $this->result;
            }
        }

        return $its;
    }

    protected function recordLocator($recordLocator)
    {
        if (preg_match('#^\s*([A-Z\d]{5,})#', $recordLocator, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function parseTotal($total)
    {
        if (preg_match('#[:\s>]*' . $this->t('Total') . '\s*([A-Z]{3}\s+[\d\.\,]+)[:\s>]*#', $total, $m)) {
            return $m[1];
        }

        return "";
    }

    protected function parsePassengers($plainText)
    {
        if (preg_match_all("#[\n>]*(.+?)[\n>]#us", $plainText, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                $its[$j]['Passengers'] = array_merge($its[$j]['Passengers'], $its[$i]['Passengers']);
                $its[$j]['Passengers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['Passengers'])));
                unset($its[$i]);
            }
        }

        return $its;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    private function iterationSegments($value, $date)
    {
        $segment = [];

        if (preg_match('#^[:\s>]*(\d+:\d+).*?,\s+(.+?)\s*\(([A-Z]{3})\)#u', $value, $m)) {
            $segment['DepCode'] = $m[3];

            if (stripos($m[2], 'Terminal') && preg_match("#(Terminal\s*:.+?),\s*(.+)#", $m[2], $mm)) {
                $segment['DepartureTerminal'] = $mm[1];
                $segment['DepName'] = $mm[2];
            } else {
                $segment['DepName'] = $m[2];
            }
            $segment['DepDate'] = strtotime($m[1], $date);
        }

        if (preg_match('#' . $this->t('Arriving') . '[:\s>]*(\d+:\d+).*?,\s+(.+?)\s*\(([A-Z]{3})\)#u', $value, $m)) {
            $segment['ArrCode'] = $m[3];

            if (stripos($m[2], 'Terminal') && preg_match("#(Terminal\s*:.+?),\s*(.+)#", $m[2], $mm)) {
                $segment['ArrivalTerminal'] = $mm[1];
                $segment['ArrName'] = $mm[2];
            } else {
                $segment['ArrName'] = $m[2];
            }
            $segment['ArrDate'] = strtotime($m[1], $date);
        }

        if (preg_match('#' . $this->t('Arriving') . '[:\s>]*.+?\s*\([A-Z]{3}\).+?\(([A-Z\d]{2})\s*(\d+)\)#su', $value, $m)) {
            $segment['AirlineName'] = $m[1];
            $segment['FlightNumber'] = $m[2];
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
        $in = [
            "#^(\d+)\.?\s+(\S{3}\s+\d{4})\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1 $2, $3",
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

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
