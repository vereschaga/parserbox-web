<?php

namespace AwardWallet\Engine\piu\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketPdf extends \TAccountChecker
{
    public $mailFiles = "piu/it-39203779.eml, piu/it-98059810.eml";
    public static $dictionary = [
        "it" => [],
        "en" => [
            "CODICE BIGLIETTO"        => "TICKET CODE",
            "PARTENZA"                => "DEPARTURE",
            "ARRIVO"                  => "ARRIVAL",
            "NOME PASSEGGERO"         => "PASSENGER NAME",
            "DETTAGLI DELL' ACQUISTO" => "DETAILS OF THE PURCHASE",
            "CARROZZA"                => "COACH",
            "POSTO"                   => "SEAT",
            "TOTALE VIAGGIO"          => "TOTAL",
        ],
    ];

    private $detectFrom = "italotreno.it";

    private $detectCompany = ["italotreno.it", "Italo S.p.A."];
    private $detectBody = [
        "it" => [
            "DETTAGLI DELL' ACQUISTO",
        ],
        "en" => [
            "DETAILS OF THE PURCHASE",
        ],
    ];

    private $pdfPattern = ".*\.pdf";

    private $lang = "it";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if ($this->detectPdf($text) === true) {
                $this->parsePdf($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if ($this->detectPdf($text) === true) {
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

    private function parsePdf(Email $email, string $text): void
    {
        $t = $email->add()->train();

        $segments = $this->split("#(?:^|\n)(\s*" . $this->preg_implode($this->t("CODICE BIGLIETTO")) . "\s*\n)#", $text);

        $confirmations = [];
        $travellers = [];
        $totals = [];
        $currencies = [];

        foreach ($segments as $stext) {
            $confirmations[] = $this->re("#" . $this->preg_implode($this->t("CODICE BIGLIETTO")) . "\s+([A-Z\d]{5,7})\s+#i", $stext);

            $s = $t->addSegment();

            $info = $this->re("#\n\s*" . $this->preg_implode($this->t("PARTENZA")) . "[ ]+" . $this->preg_implode($this->t("ARRIVO")) . " .+\n([\s\S]+?)\n\s*" . $this->preg_implode($this->t("NOME PASSEGGERO")) . "#", $stext);
            $infoTable = $this->SplitCols($info);

            if (count($infoTable) == 5) {
                $date = trim($infoTable[2]);

                $s->extra()->number(trim($infoTable[3]));

                // Departure
                if (preg_match("#^\s*(.+)\n\s*(\d{1,2}:\d{1,2})#", $infoTable[0], $m)) {
                    $s->departure()
                        ->name($m[1] . ", Europe")
                        ->date($this->normalizeDate($date . ', ' . trim($m[2])));
                }
                // Arrival
                if (preg_match("#^\s*(.+)\n\s*(\d{1,2}:\d{1,2})#", $infoTable[1], $m)) {
                    $s->arrival()
                        ->name($m[1] . ", Europe")
                        ->date($this->normalizeDate($date . ', ' . trim($m[2])));
                }
            }

            $passengersInfo = $this->re("#\n([ ]*" . $this->preg_implode($this->t("NOME PASSEGGERO")) . "[ ]+.+\n[\s\S]+?)\n*[ ]{0,15}(?:RIC\. N\.|" . $this->preg_implode($this->t("DETTAGLI DELL' ACQUISTO")) . ")#", $stext);
            $passTablePos = $this->TableHeadPos($this->re("/^\s*(.{2,})/", $passengersInfo));

            if (preg_match("#^(.+? ){$this->preg_implode($this->t("POSTO"))}$#m", $passengersInfo, $matches)) {
                $passTablePos[4] = mb_strlen($matches[1]) - 1;
            }
            $passTable = $this->SplitCols($passengersInfo, $passTablePos);
            $travellers = array_merge($travellers, array_filter(preg_split("#\s*\n\s*#", trim($this->re("#^\s*" . $this->preg_implode($this->t("NOME PASSEGGERO")) . "\s+(.+)#s", $passTable[0] ?? ''))), function ($v) {return (preg_match("#^\D+$#", $v)) ? true : false; }));

            $coach = $this->re("#" . $this->preg_implode($this->t("CARROZZA")) . "\s+([A-Z\d]+)(?:\s+|$)#", $passTable[3] ?? '');

            if (!empty($coach)) {
                $s->extra()->car($coach);
            }
            $seats = array_filter(preg_split("#\s*\n\s*#", trim($this->re("#" . $this->preg_implode($this->t("POSTO")) . "\s+(.+)#s", $passTable[4] ?? ''))));

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }

            // Price
            $total = $this->re("#\s+" . $this->preg_implode($this->t("TOTALE VIAGGIO"), true) . "[ ]{2,}(.*\d.*)\n#", $text);

            if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)) {
                $totals[] = $this->amount($m['amount']);
                $currencies[] = $this->currency($m['currency']);
            }
        }

        $confirmations = array_unique(array_filter($confirmations));

        foreach ($confirmations as $conf) {
            $t->general()->confirmation($conf);
        }

        $travellers = array_unique(array_filter($travellers));
        $t->general()->travellers($travellers);

        // Price
        $totals = array_filter($totals);

        if (!empty($totals) && count(array_unique($currencies)) == 1) {
            $t->price()
                ->total(array_sum($totals))
                ->currency($currencies[0])
            ;
        }
    }

    private function detectPdf($text)
    {
        $foundCompany = false;

        foreach ($this->detectCompany as $detectCompany) {
            if (preg_match("#{$this->addSpacesWord($detectCompany)}#", $text)) {
                $foundCompany = true;

                break;
            }
        }

        if ($foundCompany === false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (preg_match("#{$this->addSpacesWord($dBody)}#", $text)) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
            //			"#^\s*(\d+:\d+)\s+[^\s\d]+[,\s]+(\d{1,2})\s*([^\d\s]+)\s+(\d{4})\s*$#",//19:30  Wednesday,  26 Dec 2018
            //			"#^\s*(\d{1,2})\s*([^\d\s]+)\s+(\d{4})\s+(\d+:\d+)\s*$#",//24 Desember 2018 22:50
        ];
        $out = [
            //			"$2 $3 $4, $1",
            //			"$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'id')) {
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

    private function amount($price)
    {
        $price = str_replace([' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field, bool $addSpaces = false): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode('|', array_map(function ($v) use ($addSpaces) {
            return $addSpaces ? $this->addSpacesWord($v) : preg_quote($v, '#');
        }, $field)) . ')';
    }

    private function addSpacesWord(string $text): string
    {
        return preg_replace('/(\S)/u', '$1 *', preg_quote($text, '#'));
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("%", preg_replace("#\s{2,}#", "%", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
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

    private function split($re, $text, $shiftFirst = true)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            if ($shiftFirst == true || ($shiftFirst == false && empty($r[0]))) {
                array_shift($r);
            }

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
