<?php

namespace AwardWallet\Engine\transavia\Email;

class ItineraryPDF extends \TAccountChecker
{
    public $mailFiles = "transavia/it-10462721.eml, transavia/it-12232561.eml";

    public $reFrom = "transavia.com";
    public $reBody = [
        'en' => ['Booking number', 'Booking date'],
        'fr' => ['Numéro de réservation', 'Date de réservation'],
    ];
    public $reSubject = [
        '',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'fr' => [
            'Booking number' => 'Numéro de réservation',
            'Booking date'   => 'Date de réservation',
            'Flight number'  => 'Numéro de vol',
            'Departure time' => 'Heure de départ',
            'Arrival time'   => 'Heure d\'arrivée',
            'Passengers'     => 'Passagers',
        ],
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $this->assignLang($text);
                    $its[] = $this->parseEmail($text);
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

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

            if (strpos($text, 'Boarding pass') === false) {
                return $this->assignLang($text);
            }
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

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
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($left, $searchFinish, true);
            } else {
                $inputResult = $left;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function parseEmail($textPDF)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->re("#{$this->opt($this->t('Booking number'))}\s+([A-Z\d]{5,})#", $textPDF);
        $it['ReservationDate'] = $this->normalizeDate($this->re("#{$this->opt($this->t('Booking date'))}\s+(.+)#",
            $textPDF));
        $textPax = strstr($textPDF, $this->t('Passengers'));

        if (preg_match_all("#\s*(MR.+?)\s+\(\s*\d+\/#", $textPax, $m)) {
            $it['Passengers'] = array_values(array_unique(array_filter($m[1])));
        }

        $tot = $this->getTotalCurrency($this->re("#^ *{$this->opt($this->t('Total'))}\s+(\d.+)#m", $textPax));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $table = $this->re("#\n\n\n(^ +\S+.+?{$this->opt($this->t('Flight number'))}.+?\d+:\d+.*?)\n\n#sm", $textPDF);
        $table = $this->SplitCols($table, $this->colsPos($table, 10));

        if ((count($table) % 2) !== 0) {
            $this->http->Log("other format");

            return null;
        }
        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $cntFlights = (int) (count($table) / 2);

        for ($i = 1; $i <= $cntFlights; $i++) {
            $num = $i * 2 - 2;
            $seg = [];

            if (preg_match("#(.+?)\s+{$this->opt($this->t('Flight number'))}\s+([A-Z\d]{2})\s*(\d+)\s+{$this->opt($this->t('Departure time'))}\s+(\d+:\d+(?:[apAPmM]{2})?)#su",
                $table[$num], $m)) {
                $seg['DepName'] = trim(preg_replace("#\s+#", ' ', $m[1]));
                $seg['AirlineName'] = $m[2];
                $seg['FlightNumber'] = $m[3];
                $depTime = $m[4];
            }
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            if (preg_match("#(.+?)\s+{$this->opt($this->t('Date'))}\s+(.+?)\s+{$this->opt($this->t('Arrival time'))}\s+(\d+:\d+(?:[apAPmM]{2})?)#s",
                $table[$num + 1], $m)) {
                $seg['ArrName'] = trim(preg_replace("#\s+#", ' ', $m[1]));
                $date = $this->normalizeDate($m[2]);

                if (isset($depTime)) {
                    $seg['DepDate'] = strtotime($depTime, $date);
                }
                $seg['ArrDate'] = strtotime($m[3], $date);
            }

            if (($cntFlights === 1) && preg_match_all("#^ *\- *{$this->opt($this->t('Seat'))}\s+(\d+[A-Za-z])#m", $textPax, $m)) {
                $seg['Seats'] = $m[1];
            }

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+)\-(\d+)\-(\d{4])\s*$#',
        ];
        $out = [
            '$3-$2-$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
            return str_replace(' ', '\s+', preg_quote($s));
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
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
