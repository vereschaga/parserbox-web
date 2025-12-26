<?php

namespace AwardWallet\Engine\skywards\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "skywards/it-12234250.eml, skywards/it-13.eml, skywards/it-1704924.eml, skywards/it-1741337.eml, skywards/it-1788504.eml, skywards/it-1884131.eml, skywards/it-2.eml, skywards/it-4366474.eml, skywards/it-4640553.eml, skywards/it-4645649.eml, skywards/it-6.eml";

    public $reFrom = "emirates.com";
    public $reSubject = [
        "お客様のご予約が変更されました",
        "Su itinerario Emirates",
        "Your eReceipt Details",
    ];
    public $reBody = 'Emirates';
    public $reBody2 = [
        "en" => "e-Ticket Receipt & Itinerary",
    ];
    public $pdfPattern = "(.*.(?:ticket|tikcet|\d{7,}).*pdf|\d+)";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private $total = [];
    private $text;

    public function parsePdf(&$itinerary)
    {
        $text = $this->text;

        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = [];

        $it['Kind'] = "T";

        $it['RecordLocator'] = $this->re("#\s{2,}BOOKING REFERENCE\s+([A-Z\d\-]+)#", $text);
        $it['Passengers'][] = $this->re("#\n\s*PASSENGER NAME\s+([^\n]+?)(?:\s{3,}|\n)#", $text);
        $it['TicketNumbers'][] = $this->re("#\n\s*E-TICKET NUMBER\s+([^\n]+?)(?:\s{3,}|\n)#", $text);
        $it['ReservationDate'] = $this->normalizeDate($this->re("#\n\s*ISSUED BY/DATE\s+.+?\s+(\d+\D+?\d{4})#s",
            $text));

        $textSum = strstr($text, $this->t('FARE AND ADDITIONAL INFORMATION'));

        $tot = $this->getTotalCurrency($this->re("#\n\s*FARE\s+([A-Z]{3}\s*[\d.]+)#", $textSum));

        if (!empty($tot['Total'])) {
            $it['BaseFare'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $tot = $this->getTotalCurrency($this->re("#\n\s*TOTAL\s+([A-Z]{3}\s*[\d.]+)#", $textSum));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $nodes = $this->splitter("#\n( *FLIGHT\s+DEPART\/ARRIVE\s+AIRPORT\/TERMINAL\s+CHECK\-IN\s+OPENS\s+CLASS\s+COUPON\s+VALIDITY)#ims",
            $text);

        foreach ($nodes as $node) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $table = $this->re("#^(.+?)\n\n\n#s", $node);

            if (empty($table)) {
                $table = $node;
            }
            $table = $this->SplitCols($table, $this->colsPos($table, 10));

            if (count($table) < 6) {
                $this->http->Log("other format");

                return null;
            }

            if (preg_match("#FLIGHT\s+([A-Z\d]{2})\s*(\d+)\s+(\w+)\s+#", $table[0], $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $it['Status'] = $m[3];
            }

            if (preg_match("#DEPART\/ARRIVE\s+(.+)\s+(\d{2})(\d{2})\s+(.+)\s+(\d{2})(\d{2})#", $table[1], $m)) {
                $seg['DepDate'] = strtotime($m[2] . ':' . $m[3], $this->normalizeDate($m[1]));
                $seg['ArrDate'] = strtotime($m[5] . ':' . $m[6], $this->normalizeDate($m[4]));
            }

            if (preg_match("#AIRPORT\/TERMINAL\s*\n(.+?)\s+\(([A-Z]{3})\)\s*([^\n]*TERMINAL[^\n]*)?\n(.+?)\s+\(([A-Z]{3})\)\s*(?:\n([^\n]*TERMINAL[^\n]*)|$)#s",
                $table[2], $m)) {
                $seg['DepName'] = trim(preg_replace("#\s+#", ' ', $m[1]));
                $seg['DepCode'] = $m[2];

                if (isset($m[3]) && !empty(trim($m[3]))) {
                    $seg['DepartureTerminal'] = trim(str_ireplace("TERMINAL", ' ', $m[3]));
                }
                $seg['ArrName'] = trim(preg_replace("#\s+#", ' ', $m[4]));
                $seg['ArrCode'] = $m[5];

                if (isset($m[6]) && !empty(trim($m[6]))) {
                    $seg['ArrivalTerminal'] = trim(str_ireplace("TERMINAL", ' ', $m[6]));
                }
            }
            $seg['Cabin'] = $this->re("#CLASS\s+(.+)#", $table[4]);
            $seg['Seats'][] = $this->re("#SEAT\s+(\d[a-zA-Z])#", $table[4]);

            $it['TripSegments'][] = $seg;
        }

        $itinerary = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) !== false) {
            return true;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }
            $itinerary = [];
            $this->parsePdf($itinerary);
            $itineraries[] = $itinerary;

            foreach ($this->reBody2 as $lang => $re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }
        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
                'TotalCharge' => $this->total,
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

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\s\d]+)(\d{4})\s+(\d+:\d+)$#", //19Aug2016 15:30
            "#^(\d+)([^\s\d]+)(\d{4})\s*$#", //19Aug2016 15:30
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$i], $pos[$j])) {
                    if ($pos[$i] - $pos[$j] < $correct) {
                        unset($pos[$i]);
                    }

                    break;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
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
}
