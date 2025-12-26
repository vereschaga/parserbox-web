<?php

namespace AwardWallet\Engine\scoot\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "scoot/it-10711350.eml, scoot/it-10874428.eml, scoot/it-10881417.eml, scoot/it-10885783.eml";

    public $reFrom = "@flyscoot.com";
    public $reBody = [
        'en' => ['Boarding Pass', 'Scoot Booking Reference'],
    ];
    public $reSubject = [
        'Your Boarding Pass',
    ];

    /** @var \HttpBrowser */
    public $pdf;
    public $pdfNamePattern = "BoardingPass.pdf";

    public $lang = '';
    public static $dict = [
        'en' => [
            'splitString' => 'Please print boarding pass onto single sided A4 paper',
        ],
    ];

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
        } else {
            return null;
        }

        $body = $this->pdf->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail(text($body));
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->AssignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
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

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                foreach ($its[$j]['TripSegments'] as $flJ => $tsJ) {
                    foreach ($its[$i]['TripSegments'] as $flI => $tsI) {
                        if (isset($tsI['FlightNumber']) && isset($tsJ['FlightNumber']) && ((int) $tsJ['FlightNumber'] === (int) $tsI['FlightNumber'])
                            && (isset($tsJ['Seats']) || isset($tsI['Seats']))
                        ) {
                            $new = "";

                            if (isset($tsJ['Seats'])) {
                                $new .= "," . $tsJ['Seats'];
                            }

                            if (isset($tsI['Seats'])) {
                                $new .= "," . $tsI['Seats'];
                            }
                            $new = implode(",", array_filter(array_unique(array_map("trim", explode(",", $new)))));
                            $its[$j]['TripSegments'][$flJ]['Seats'] = $new;
                            $its[$i]['TripSegments'][$flI]['Seats'] = $new;
                        }
                    }
                }

                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));

                if (isset($its[$j]['Passengers']) || isset($its[$i]['Passengers'])) {
                    $new = "";

                    if (isset($its[$j]['Passengers'])) {
                        $new .= "," . implode(",", $its[$j]['Passengers']);
                    }

                    if (isset($its[$i]['Passengers'])) {
                        $new .= "," . implode(",", $its[$i]['Passengers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(",", $new)))));
                    $its[$j]['Passengers'] = $new;
                }

                if (isset($its[$j]['AccountNumbers']) || isset($its[$i]['AccountNumbers'])) {
                    $new = "";

                    if (isset($its[$j]['AccountNumbers'])) {
                        $new .= "," . implode(",", $its[$j]['AccountNumbers']);
                    }

                    if (isset($its[$i]['AccountNumbers'])) {
                        $new .= "," . implode(",", $its[$i]['AccountNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(",", $new)))));
                    $its[$j]['AccountNumbers'] = $new;
                }

                if (isset($its[$j]['TicketNumbers']) || isset($its[$i]['TicketNumbers'])) {
                    $new = "";

                    if (isset($its[$j]['TicketNumbers'])) {
                        $new .= "," . implode(",", $its[$j]['TicketNumbers']);
                    }

                    if (isset($its[$i]['TicketNumbers'])) {
                        $new .= "," . implode(",", $its[$i]['TicketNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(",", $new)))));
                    $its[$j]['TicketNumbers'] = $new;
                }

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

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmail($textPDF)
    {
        $its = [];

        $nodes = $this->splitter("#({$this->opt($this->t('splitString'))}).*#", $textPDF);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $this->re("#Scoot Booking Reference\s+([A-Z\d]{5,})#", $root);

            if (preg_match("#PASSENGER.+?NATIONALITY\s+([^\n]+?)\s+[A-Z\d]{2}\s*\d+.+?([^\n]+)\s+(?:DEPART|ARRIVE)#si", $root, $m)) {
                $name = $m[1];

                if (strlen($m[2]) > 2) {
                    $name .= ' ' . $m[2];
                }
                $it['Passengers'][] = $name;
            }
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            if (preg_match("#PASSENGER.+?NATIONALITY\s+[^\n]+?\s+(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)#is", $root, $m)) {
                foreach ($m as $key => $value) {
                    if (!is_numeric($key)) {
                        $seg[$key] = $value;
                    }
                }
            }

            if (preg_match("#DEPART\s+ARRIVE\s+[^\n]+?\n(?<DepDate>\d.+)\n(?<ArrDate>\d.+)\n.+\n(?<DepName>(?!\d).+)\n(?<ArrName>(?!\d).+)#i", $root, $m)) {
                foreach ($m as $key => $value) {
                    if (!is_numeric($key)) {
                        if (in_array($key, ['DepDate', 'ArrDate'])) {
                            $seg[$key] = strtotime($this->correctTimeString($value),
                                strtotime($this->re("#(.+?)\s*(?:,|\d+:\d+)#", $value)));
                        } else {
                            $seg[$key] = $value;
                        }
                    }
                }
            } elseif (preg_match("#DEPART\s+ARRIVE\s+[^\n]+?\n(?<DepName>.+?)\s+(?<DepDate>\d.+)\n(?<ArrName>(?!\d).+)\n\d+\s+\w+\s+\d+\n(?<ArrDate>\d.+)#i", $root, $m)
                || preg_match("#DEPART\s+ARRIVE\s+[^\n]+?\n(?<DepName>.+?)\s+(?<DepDate>\d.+)\n(?<ArrName>(?!\d).+)\s+(?<ArrDate>\d+:.+)#i", $root, $m)
                || preg_match("#ARRIVE\s+DEPART\s+[^\n]+?\n(?<ArrName>.+?)\n(?<DepName>(?!\d).+?)\s+(?<DepDate>\d.+)\n\d+\s+\w+\s+\d+\s+(?<ArrDate>\d+:.+)#i", $root, $m)
                || preg_match("#DEPART\s+ARRIVE\s+[^\n]+?\n(?<DepName>.+?)\n(?<ArrName>(?!\d).+?)\s+(?<DepDate>\d.+)\n\d+\s+\w+\s+\d+\s+(?<ArrDate>\d+:.+)#i", $root, $m)
            ) {
                $date = strtotime($this->normalizeDate($this->re("#SEAT\s+(\d+\s+\w+\s+\d+)#i", $root)));

                foreach ($m as $key => $value) {
                    if (!is_numeric($key)) {
                        if (in_array($key, ['DepDate', 'ArrDate'])) {
                            $seg[$key] = strtotime($this->correctTimeString($value), $date);
                        } else {
                            $seg[$key] = $value;
                        }
                    }
                }
            }

            if (isset($seg['DepName'], $seg['ArrName'])) {
                $code = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Departure'))}]/following::text()[normalize-space(.)!=''][1][contains(.,'{$seg['DepName']}')]", null, true, "#([A-Z]{3})\s*$#");

                if (!empty($code)) {
                    $seg['DepCode'] = $code;
                }
                $code = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrival'))}]/following::text()[normalize-space(.)!=''][1][contains(.,'{$this->re("#^(\w+)#u", $seg['ArrName'])}')]", null, true, "#([A-Z]{3})\s*$#");

                if (!empty($code)) {
                    $seg['ArrCode'] = $code;
                }
            }

            if ($seg['DepCode'] === TRIP_CODE_UNKNOWN && $seg['ArrCode'] === TRIP_CODE_UNKNOWN && isset($seg['DepDate'], $seg['ArrDate']) && $seg['DepDate'] > $seg['ArrDate']) {
                $seg['ArrDate'] = strtotime("+1 day", $seg['ArrDate']);
            }

            $seg['Seats'] = $this->re('#^SEAT$.+?\d+:\d+[^\n]+?\n^(\d+\w)$#ism', $root);
            $seg['Cabin'] = $this->re('#CLASS[\s:]+(.+)#i', $root);

            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }
        $its = $this->mergeItineraries($its);

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+\s+\w+\s+\d{4})\s*$#u',
        ];
        $out = [
            '$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }

    private function correctTimeString($time)
    {
        if (preg_match("#(\d+):(\d+)(?::\d+)?\s*([ap]m)#i", $time, $m)) {
            if (($m[1] == 0 && stripos($m[3], 'am') !== false) || $m[1] > 12) {
                return $m[1] . ":" . $m[2];
            }
        }

        return $time;
    }
}
