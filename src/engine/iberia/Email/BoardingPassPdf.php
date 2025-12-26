<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "iberia/it-11231187.eml, iberia/it-12233229.eml";

    public $reFrom = 'iberia.';
    public $reBody = [
        'en' => ['Boarding pass', 'BOOKING CODE'],
        'es' => ['Tarjeta de embarque', 'CÓDIGO DE RESERVA'],
        'nl' => ['Instapkaart', 'CODEBOEK'],
        'fr' => ["Carte d'embarquement", 'CODE DE RÉSERVATION'],
        'ca' => ["Targeta d'embarcament", 'CODI DE RESERVA'],
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = '.*pdf';
    public static $dict = [
        'en' => [
            'The baggage drop counter' => ['The baggage drop counter', 'antes de la salida del vuelo'],
        ],
        'es' => [ // filling doesn't metter
            'Boarding pass'            => 'Tarjeta de embarque',
            'BOARDING TIME'            => 'HORA DE EMBARQUE',
            'The baggage drop counter' => 'antes de la salida del vuelo',
        ],
        'nl' => [ // filling doesn't metter
            'Boarding pass'            => 'Instapkaart',
            'BOARDING TIME'            => 'BOARDING TIJD',
            'The baggage drop counter' => 'HET WEER IN',
        ],
        'fr' => [ // filling doesn't metter
            'Boarding pass'            => "Carte d'embarquement",
            'BOARDING TIME'            => "TEMPS D'EMBARQUEMENT",
            'The baggage drop counter' => 'Fermeture: Les comptoirs',
        ],
        'ca' => [ // filling doesn't metter
            'Boarding pass' => "Targeta d'embarcament",
            'BOARDING TIME' => "HORA D'EMBARCAMENT",
            //            'The baggage drop counter' => '',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $html = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        $this->assignLang($html);

        $its = $this->parseEmail($html, $parser);
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs[0])) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));
            $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"©Iberia ") or contains(normalize-space(.),"© Iberia ")]')->length > 0;
            $condition2 = $this->http->XPath->query('//a[contains(@href,"//iberia.es")]')->length > 0;

            if (stripos($textPdf, 'iberia') !== false || $condition1 || $condition2) {
                return $this->assignLang($textPdf);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            foreach (self::$dict as $lang => $val) {
                if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $lang)) {
                    return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
                }
            }
        }

        return $date;
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
                            $new = [];

                            if (isset($tsJ['Seats'])) {
                                $new = array_merge($new, (array) $tsJ['Seats']);
                            }

                            if (isset($tsI['Seats'])) {
                                $new = array_merge($new, (array) $tsI['Seats']);
                            }
                            $its[$j]['TripSegments'][$flJ]['Seats'] = array_values(array_filter(array_unique($new)));
                            $its[$i]['TripSegments'][$flI]['Seats'] = array_values(array_filter(array_unique($new)));
                        }
                    }
                }

                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));

                if (isset($its[$j]['Passengers']) || isset($its[$i]['Passengers'])) {
                    $new = [];

                    if (isset($its[$j]['Passengers'])) {
                        $new = array_merge($new, $its[$j]['Passengers']);
                    }

                    if (isset($its[$i]['Passengers'])) {
                        $new = array_merge($new, $its[$i]['Passengers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", $new))));
                    $its[$j]['Passengers'] = $new;
                }

                if (isset($its[$j]['AccountNumbers']) || isset($its[$i]['AccountNumbers'])) {
                    $new = [];

                    if (isset($its[$j]['AccountNumbers'])) {
                        $new = array_merge($new, $its[$j]['AccountNumbers']);
                    }

                    if (isset($its[$i]['AccountNumbers'])) {
                        $new = array_merge($new, $its[$i]['AccountNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", $new))));
                    $its[$j]['AccountNumbers'] = $new;
                }

                if (isset($its[$j]['TicketNumbers']) || isset($its[$i]['TicketNumbers'])) {
                    $new = [];

                    if (isset($its[$j]['TicketNumbers'])) {
                        $new = array_merge($new, $its[$j]['TicketNumbers']);
                    }

                    if (isset($its[$i]['TicketNumbers'])) {
                        $new = array_merge($new, $its[$i]['TicketNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", $new))));
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

    private function parseEmail($textPDF, $parser)
    {
        $its = [];

        $nodes = $this->splitter("/((?:ETKT\d+|BN\.\d{3}[ ]{5,}\d{13})\b.*?{$this->opt($this->t('Boarding pass'))})/isu", "\n  " . $textPDF);

        foreach ($nodes as $sText) {
            // table with destinations
            $table1Text = $this->re("/\n([^\n]*{$this->opt($this->t("FROM"))}.+?{$this->opt($this->t('BOARDING TIME'))})/s", $sText);
            $table1 = $this->splitCols($table1Text, $this->colsPos($table1Text, 10));

            if (count($table1) < 2) {
                $this->http->Log('Other PDF format!');

                continue;
            }

            // table with fligth info
            $table2Text = $this->re("/\n([^\n]*{$this->opt($this->t('BOARDING TIME'))}.+?)(?:\d+[ ]*)?(?:{$this->opt($this->t('The baggage drop counter'))}|\n[^\n]*[ ]{3,}\d{1,2}kg[ ]+\d{1,3}cm\s*\n)/s", $sText);
            $table2 = $this->splitCols($table2Text, $this->colsPos($table2Text, 10));

            if (count($table2) < 4) {
                $this->http->Log('Other PDF format!');

                continue;
            }

            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $this->re("#(?:BOOKING|RESERVATION)\s+CODE\s+([A-Z\d]{5,})#", $sText);
            $it['Passengers'][] = $this->re("#(?:BOOKING|RESERVATION)\s+CODE\s+[A-Z\d]{5,}\s+([^\n]+?)(?:[ ]{2,}[A-Z\d]{5,}|\n)#", $sText);
            $it['AccountNumbers'] = array_unique(array_filter([$this->re("#(?:BOOKING|RESERVATION)\s+CODE\s+[A-Z\d]{5,}\s+[^\n]+?[ ]{2,}([A-Z\d]{5,})\s+#", $sText)]));
            $it['TicketNumbers'][] = $this->re("#ETKT(\d+)#", $sText);
            $it['TicketNumbers'][] = $this->re("#^\s*BN\.\d{3}[ ]{5,}(\d{13})\b#", $sText);
            $it['TicketNumbers'] = array_filter($it['TicketNumbers']);

            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            if (preg_match('/Operated by\s+(.+)/i', $table2[count($table2) - 2], $m)) {
                $seg['Operator'] = $m[1];
            }

            if (preg_match('/Operated by\s+.+\s+(.*(?:Economy|Busines|Turista).*)/i', $sText, $m)) {
                $seg['Cabin'] = $m[1];
            }

            if (preg_match("#FROM\s+(.+)\s+.*?DEPARTURE\s+(\d+\s+\w+)\s+(\d+:\d+)#u", $table1[0], $m)) {
                if (preg_match("#(.+?)(?:,\s((?i).*?Terminal.*))?\s+\(([A-Z]{3})\)#", $m[1], $v)) {
                    $seg['DepName'] = $v[1];

                    if (isset($v[2]) && !empty($v[2])) {
                        $seg['DepartureTerminal'] = trim(str_ireplace('Terminal', '', $v[2]));
                    }
                    $seg['DepCode'] = $v[3];
                }
                $date = $this->dateStringToEnglish($m[2]);
                $date = EmailDateHelper::calculateDateRelative($date, $this, $parser);
                $seg['DepDate'] = strtotime($m[3], $date);
            }

            if (preg_match("#TO\s+(.+)\s+.*?ARRIVAL\s+(\d+\s+\w+)?\s*(\d+:\d+)#u", $table1[1], $m)) {
                if (preg_match("#(.+?)(?:,\s((?i).*?Terminal.*))?\s+\(([A-Z]{3})\)#", $m[1], $v)) {
                    $seg['ArrName'] = $v[1];

                    if (isset($v[2]) && !empty($v[2])) {
                        $seg['ArrivalTerminal'] = trim(str_ireplace('Terminal', '', $v[2]));
                    }
                    $seg['ArrCode'] = $v[3];
                }

                if (isset($m[2]) && !empty($m[2])) {
                    $date = $this->dateStringToEnglish($m[2]);
                    $date = EmailDateHelper::calculateDateRelative($date, $this, $parser);
                } elseif (isset($seg['DepDate'])) {
                    $date = $seg['DepDate'];
                }

                if (isset($date)) {
                    $seg['ArrDate'] = strtotime($m[3], $date);
                }
            }

            if (preg_match('/FLIGHT\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/u', $table2[count($table2) - 2], $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match('/SEAT\s+(\d+\w)\s/u', $table2[count($table2) - 1], $m)) {
                $seg['Seats'] = [$m[1]];
            }
            $it['TripSegments'][] = $seg;

            $its[] = $it;
        }

        $its = $this->mergeItineraries($its);

        return $its;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{4,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }
}
