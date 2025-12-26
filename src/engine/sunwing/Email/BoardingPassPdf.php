<?php

namespace AwardWallet\Engine\sunwing\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "sunwing/it-11536457.eml, sunwing/it-427727397.eml";

    public $subjects = [
        'en' => ['Boarding Passes for', 'Boarding Pass for'],
    ];

    public $langDetectors = [
        'en' => ['BOARDING PASS'],
    ];
    public $pdfPattern = "[A-Z, ]+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf()
    {
        $itineraries = [];
        $airs = [];
        $pdfs = $this->parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($this->parser->getAttachmentBody($pdf));

            if (!preg_match("#\n((?:Passenger )?Name:[^\n]+)\n(.*?)\n(?:\S+\s)*\S+:#ms", $text, $m)) {
                $itineraries = [];
                $this->logger->info("passTable not matched");

                return;
            }
            $passTable = $this->splitCols($m[2], $this->colsPos($m[1]));

            if (count($passTable) !== 4) {
                $itineraries = [];
                $this->logger->info("incorrect parse passTable");

                return;
            }

            if (!preg_match("#\n((?:Record Locator|Reservation):.+)\n([\s\S]+?)\n(?: {0,5}[A-Z].*\n| {20,}\*.+\n)* *[[:alpha:]]+ *:#", $text, $m)) {
                $itineraries = [];
                $this->logger->info("mainTable not matched");

                return;
            }
            $mainTable = $this->splitCols($m[2], $this->colsPos($m[1]));

            if (count($mainTable) !== 6) {
                $itineraries = [];
                $this->logger->info("incorrect parse mainTable");

                return;
            }
            $airs[trim($mainTable[0])][] = [
                'passTable'=> $passTable,
                'mainTable'=> $mainTable,
                'text'     => $text,
            ];
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];
            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = trim($mainTable[0]);

            // Passengers
            $it['Passengers'] = [];

            foreach ($segments as $data) {
                $it['Passengers'][] = trim($data['passTable'][0]);
            }
            $it['Passengers'] = array_unique($it['Passengers']);

            $it['TripSegments'] = [];
            $uniq = [];

            foreach ($segments as $data) {
                $passTable = $data['passTable'];
                $mainTable = $data['mainTable'];
                $text = $data['text'];

                $itsegment = [];

                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) (\d+)$#", trim($passTable[1]));

                if (isset($uniq[$itsegment['FlightNumber']])) {
                    $n = $uniq[$itsegment['FlightNumber']];
                    $it['TripSegments'][$n]['Seats'] = array_merge($it['TripSegments'][$n]['Seats'], array_filter([trim($mainTable[5])]));

                    continue;
                }
                $uniq[$itsegment['FlightNumber']] = count($it['TripSegments']);

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->re("#From:\n(.*?)(?:\s{2,}|\n)#", $text);

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($passTable[2] . ', ' . $passTable[3]));

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->re("#To:\n(.*?)(?:\s{2,}|\n|$)#", $text);

                // ArrDate
                $itsegment['ArrDate'] = MISSING_DATE;

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#^([A-Z][A-Z\d]|[A-Z\d][A-Z]) \d+$#", trim($passTable[1]));

                // BookingClass
                $itsegment['BookingClass'] = trim($mainTable[1]);

                // Seats
                $itsegment['Seats'] = array_filter([trim($mainTable[5])]);

                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }

        return $itineraries;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@sunwing.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
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

        if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($textPdf, 'Sunwing') === false && self::detectEmailFromProvider($parser->getHeader('from')) !== true) {
            return false;
        }

        return $this->assignLang($textPdf);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->parser = $parser;
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        $this->assignLang($this->text);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parsePdf(),
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignLang($text = ''): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (empty($text) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                } elseif (!empty($text) && strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)/(\d+)/(\d{2})$#", //06/19/16
            "#^(\d+:\d+)\s+(\d+)/(\d+)/(\d{2})$#", //08:25 06/19/16
        ];
        $out = [
            "$2.$1.20$3",
            "$3.$2.20$4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
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

        foreach ($pos as $i=> $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
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
            foreach ($pos as $k=>$p) {
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
}
