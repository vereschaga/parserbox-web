<?php

namespace AwardWallet\Engine\sbtur\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AirBookingPDF extends \TAccountChecker
{
    public $mailFiles = "sbtur/it-706249938.eml, sbtur/it-718177000.eml, sbtur/it-872962215.eml, sbtur/it-885661095.eml";
    public $lang = 'pt';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "pt" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, "superviagem.com.br") !== false
                && (strpos($text, 'Reserva') !== false)
                && (strpos($text, 'Voos') !== false)
                && (strpos($text, 'Assentos') !== false)
            ) {
                return true;
            }

            if (preg_match("/\n {0,10}Cia +Origem *\/ *Destino +Voo/", $text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]superviagem\.com\.br$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $year = $this->re("/[ ]{2,}\d+\/\s*\d+\/\s*(\d{4})/", $text);

        $seatsText = $this->re("/\n {0,10}Assentos\n((?:.+\n){1,15})\n {0,10}(?:Serviços Auxiliares|Tarifamento|Valores)/u", $text);

        $pos = 45;

        if (preg_match_all("/^[ ]{0,40}(ADT[\s\-]+[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\b\s+(?:[-]+|\d+[A-Z])(?:\s+|\n)/m", $seatsText, $m)) {
            $pos = max(array_map(function ($v) {return strlen($v); }, $m[1]));
        } elseif (preg_match_all("/^( {0,5}(?:\S ?)+)(?: {2,}|\n)/m", $seatsText, $m)) {
            $pos = max(array_map(function ($v) {return strlen($v); }, $m[1]));
        }

        $onlySeats = trim($this->splitCols($seatsText, [0, $pos])[1]);
        $seatsTable = $this->splitCols($onlySeats);

        if (preg_match_all("/^( {0,5}(?:\S ?)+) {2,}[\d\-]+(?: {0,5}(?:\S ?)+ $)?/m", $seatsText, $m)) {
            $travellers = preg_replace('/\s*\n\s*/', ' ', $m[1]);
        } elseif (preg_match_all("/ADT[\s\-]+([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])/", $seatsText, $m)) {
            $travellers = $m[1];
        }
        $travellers = array_map('trim', $travellers);

        $f->general()
            ->noConfirmation()
            ->travellers($this->niceTraveller($travellers));

        foreach ($travellers as $traveller) {
            $traveller = trim($traveller);
            $ticket = $this->re("#^\s*(\d+\-?\s*\d+)(?:\s*[A-Z\d]{6})?\s.*{$this->opt($traveller)}#um", $text);

            if (!empty($ticket)) {
                $f->addTicketNumber(str_replace(' ', '', $ticket), false, $this->niceTraveller($traveller));
            }
        }

        $segmetsText = $this->re("#Voos\n+\s*Cia\s*Origem / Destino.+Loc Cia\n+(.+)\n*Assentos#s", $text);
        $segmetRows = array_filter(preg_split("/\n\n/", $segmetsText));

        foreach ($segmetRows as $segmentRow) {
            //bad segments
            if (preg_match("/^\s+Mochila ou bolsa/", $segmentRow)) {
                continue;
            }
            $segmentRow = preg_replace("/^(\s*\S+(?:.*\n+){3,}) *Mochila ou bolsa +.+$/", '$1', $segmentRow);

            $s = $f->addSegment();
            // $segmentTable = $this->splitCols($segmentRow, [0, 30, 62, 82, 120]);
            $segmentTable = $this->splitCols($segmentRow, $this->rowColsPos($this->inOneRow($segmentRow)));

            if (!preg_match("/\b[A-Z]{3}\s*\-\s*.+\b\d{4}\b/s", $segmentTable[1])
                && preg_match("/^( *[A-Z]{3} *- *.+? {3,})[A-Z]{3} *- *.+?/m", $segmentTable[0], $m)
            ) {
                $table = $this->splitCols($segmentTable[0], [0, mb_strlen($m[1])]);
                $segmentTable = array_merge($table, array_slice($segmentTable, 1));
            }

            if (count($segmentTable) > 2) {
                if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{2,4})/", $segmentTable[2], $m)) {
                    $s->airline()
                        ->name($m['aName'])
                        ->number($m['fNumber']);
                }

                $re = "/^\s*(?<code>[A-Z]{3})\s*\-\s*(?<name>[\s\S]+?)\n\s*(?<day>\d+\s*\w+)(?:\s*(?<year>\d{4}))?\s*(?<time>\d+\:\d+)(\s*\/\s*Terminal: (?<terminal>\w+))?/";

                if (preg_match($re, $segmentTable[0], $m)) {
                    if (isset($m['year']) && !empty($m['year'])) {
                        $year = $m['year'];
                    }

                    $s->departure()
                        ->code($m['code'])
                        ->name(preg_replace('/\s+/', ' ', trim($m['name'])))
                        ->terminal($m['terminal'] ?? null, true, true)
                        ->date($this->normalizeDate($m['day'] . ' ' . $year . ', ' . $m['time']));
                }

                if (preg_match($re, $segmentTable[1], $m)) {
                    if (isset($m['year']) && !empty($m['year'])) {
                        $year = $m['year'];
                    }

                    $s->arrival()
                        ->code($m['code'])
                        ->name(preg_replace('/\s+/', ' ', trim($m['name'])))
                        ->terminal($m['terminal'] ?? null, true, true)
                        ->date($this->normalizeDate($m['day'] . ' ' . $year . ', ' . $m['time']))
                    ;
                }

                $s->setConfirmation($this->re("/(?:^\s*|[ ]{2,})([A-Z\d]{6})\s*\n/m", $segmentTable[count($segmentTable) - 1]));
            }

            foreach ($seatsTable as $seatsColumn) {
                if (stripos($seatsColumn, $s->getAirlineName() . $s->getFlightNumber()) !== false
                    || stripos($seatsColumn, $s->getDepCode() . ' ' . $s->getArrCode()) !== false) {
                    if (preg_match_all("/\s(\d+[A-Z])(?:\s|$)/", $seatsColumn, $m)) {
                        $seats = array_filter($m[1]);

                        foreach ($seats as $seat) {
                            if (preg_match("#({$this->opt(preg_replace("/(\s+)$/", "", $travellers))}).*$seat#", $text, $m)) {
                                $s->addSeat($seat, false, false, $this->niceTraveller($m[1]));
                            } else {
                                $s->addSeat($seat);
                            }
                        }
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseFlightPDF($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s*', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\,\s*(\w+)\s(\d+)\,\s*(\d{4})\,\s*([\d\:]+)$#u", //Sunday, October 16, 2022, 13:15
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
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

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function niceTraveller($travellers)
    {
        return preg_replace("/\s(?:MRS|MR|MS|MISS)$/", "", $travellers);
    }
}
