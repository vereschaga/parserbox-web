<?php

namespace AwardWallet\Engine\klm\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "klm/it-12373422.eml, klm/it-7140221.eml, klm/it-7163884.eml, klm/it-7192950.eml, klm/it-7296971.eml, klm/it-7366426.eml, klm/it-7436422.eml, klm/it-8219230.eml, klm/it-8258622.eml, klm/it-8258676.eml, klm/it-9089588.eml";

    public $reFrom = "@klm.com";
    public $reSubject = [
        // fr
        "Votre/vos document(s) d'embarquement KLM",
        "Vos documents de confirmation d'enregistrement KLM pour le vol du",
        // en
        "Your KLM boarding document(s)",
        "Your KLM boarding pass(s) on",
        "Your KLM check-in confirmation documents for your flight on",
        // nl
        "Uw KLM-documenten met incheckbevestiging voor uw vlucht op",
    ];
    public $reBody2 = [
        "en"=> "This is not a boarding pass",
    ];
    public $pdfPattern = "(?:Boarding\s*(?:-documents|passes)|Check[\s-]*in\s*confirmation\s*document|Documentos.de.confirma|Vos documents de confirmation|Incheckbevestiging).*.pdf";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            'Booking code' => [ // from html
                "Booking code", // en
                "Código de reserva", // es
                "Code de réservation", // fr
                "Boekingscode", // nl
            ]
        ],
    ];


    public function parsePdf(Email $email, $text)
    {
        $f = $email->add()->flight();

        // General
        $confs = [];
        if (preg_match_all("/\n *BOOKING REFERENCE\s+([A-Z\d]{5,7})(?: {2,}|\n)/", $text, $m)) {
            $confs = array_unique($m[1]);
        }
        if (empty($confs)) {
            $confs[] = $this->http->FindSingleNode("//text()[".$this->eq($this->t("Booking code"))."]/ancestor::table[1]/descendant::tr[2]/td[last()]");
        }
        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        preg_match_all("#NAME\s+(.*?)(?:\s{2,}|\n)#", $text, $m);
        $f->general()
            ->travellers(array_unique($m[1]));


        // Issued
        preg_match_all("#TICKET NUMBER\s+(\d[\d ]{10,})\n#", $text, $m);
        preg_match_all("#TICKET NUMBER\s+[^\n]+\n(\d[\d ]{10,})\n#", $text, $m2);
        $f->issued()
            ->tickets(array_unique(preg_replace('/\s+/', '', array_merge($m[1], $m2[1]))), false);


        // Program
        preg_match_all("#FREQUENT FLYER NUMBER\s+\b([A-Z\d]{5,})\b\s+#", $text, $m);
        $m[1] = array_unique(array_filter($m[1]));
        if (!empty($m[1])) {
            $f->program()
                ->accounts($m[1], false);
        }



        $flights = $this->splitText($text, "/(^[ ]*{$this->opt($this->t('NAME'))}[: ]+(?:.+\n+){1,6}[ ]*{$this->opt($this->t('TICKET NUMBER'))}[: ]+[-A-Z\d])/m", true);
        foreach ($flights as $fText) {
            $itText = preg_replace("/^[\s\S]+?(\n\n( *\S.*\n)?.+ DEPARTURE)/", '$1', $fText);
            $itText = preg_replace("/^([\s\S]+ DEPARTURE(?:.*\n){3,10}?\n\n)[\s\S]*/", '$1', $itText);
            $itText = "\n\n" . $itText;
//            $this->logger->debug('$itText = '."\n".print_r( $itText,true));

            $segments = $this->splitText($itText, "/\n\n((?: *\S.*\n)?.+ DEPARTURE)/", true);
//            $this->logger->debug('$segments = '."\n".print_r( $segments,true));

            foreach ($segments as $i => $stext) {
                $s = $f->addSegment();

                if (preg_match("#OPERATED BY\s*(.+)#", $stext, $m)) {
                    $s->airline()
                        ->operator($m[1]);
                }

                $stext = preg_replace("/\n\s*OPERATED BY[\s\S]+/", '', $stext);

                $table = $this->SplitCols($stext, $this->TableHeadPos($this->inOneRow($stext)));

                if (count($table) < 5) {
                    continue;
                }

                if (preg_match("/^\s*DATE\s+/", $table[4], $m)) {
                    if (preg_match("/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,5})\s*$/", $table[1])) {
                        // DepName  |   Flight  | ArrName  |          |   Date
                        // Depcode  |           |          | ArrCode  |

                        // union columns 2 and 3
                        $col2Rows = explode("\n", $table[2]);
                        $col3Rows = explode("\n", $table[3]);
                        $newCol = '';
                        for ($c = 0; $c < max(count($col2Rows),count($col3Rows)); $c++) {
                            $newCol .= ($col2Rows[$c] ?? '') . ' ' . ($col3Rows[$c] ?? '') . "\n";
                        }
                        $table[2] = $newCol;
                        unset($table[3]);
                        $table = array_values($table);
                    }
                } elseif (preg_match("/^\s*DATE\s+/", $table[2], $m)) {
                    if (preg_match("/^\s*((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,5})\s+(\S+[\S\s]+)/", $table[1], $m)) {
                        // DepName  |   Flight ArrName  |   Date
                        // Depcode  |           ArrCode |

                        // union columns 2 and 3
                        for ($c = max(array_keys($table)); $c > 1; $c--) {
                            $table[$c+1] = $table[$c];
//                            $newCol .= ($col2Rows[$c] ?? '') . ' ' . ($col3Rows[$c] ?? '') . "\n";
                        }
                        $table[1] = $m[1];
                        $table[2] = $m[2];
                    }
                }

                if (count($table) < 7) {
                    continue;
                }
//                $this->logger->debug('$table = '."\n".print_r( $table,true));

                // Airline
                if (preg_match("/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,5})\s*$/", $table[1], $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }

                // Departure
                if (preg_match("/^\s*(.*)\s*\n\s*([A-Z]{3})\s*$/s", $table[0], $m)) {
                    $s->departure()
                        ->code($m[2]);
                    $m[1] = trim($m[1]);
                    if (!empty($m[1])) {
                        $s->departure()
                            ->name(preg_replace("/\s+/", ' ', $m[1]));
                    }
                }
                if (preg_match("/^\s*DATE\s*\n\s*(.+)\s*$/", $table[3], $dm)
                    && preg_match("/^\s*DEPARTURE\s*\n\s*(\d{1,2}:\d{2})\s*$/", $table[5], $tm)
                ) {
                    $s->departure()
                        ->date($this->normalizeDate($dm[1] . ', ' . $tm[1]));
                    $s->arrival()
                        ->noDate();
                }
                if (preg_match("/^\s*TERMINAL\s*\\/\s*.*\s*\n\s*([A-Z\d]+) *\\/\s*/", $table[6], $m)) {
                    $s->departure()
                        ->terminal($m[1]);
                }

                // Arrival
                if (preg_match("/^\s*(.*)\s*\n\s*([A-Z]{3})\s*$/s", $table[2], $m)) {
                    $s->arrival()
                        ->code($m[2]);
                    $m[1] = trim($m[1]);
                    if (!empty($m[1])) {
                        $s->arrival()
                            ->name(preg_replace("/\s+/", ' ', $m[1]));
                    }
                }

                // Extra
                if (preg_match("/^\s*SEAT\s*\n\s*(\d{1,3}[A-Z]+)\s*/", $table[7], $m)) {
                    $s->extra()
                        ->seat($m[1]);
                } elseif (preg_match("/^\s*SEAT +TRAVEL CLASS\s*\n\s*(\d{1,3}[A-Z]+)\s+(.+)/", $table[7], $m)) {
                    $s->extra()
                        ->seat($m[1])
                        ->cabin($m[2])
                    ;
                }

                if (preg_match("/^\s*TRAVEL CLASS\s*\n\s*(.+)\s*$/", $table[8] ?? '', $m)) {
                    $s->extra()
                        ->cabin($m[1])
                    ;
                }

                $segments = $f->getSegments();

                foreach ($segments as $segment) {
                    if ($segment->getId() !== $s->getId()) {
                        if (serialize(array_diff_key($segment->toArray(),
                                ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                            if (!empty($s->getSeats())) {
                                $segment->extra()->seats(array_unique(array_merge($segment->getSeats(),
                                    $s->getSeats())));
                            }
                            $f->removeSegment($s);

                            break;
                        }
                    }
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
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

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return false;
            }

            if (strpos($text, ' KL') === false && strpos($text, 'Sec.nr.:') === false) {
                return false;
            }

            foreach ($this->reBody2 as $re) {
                if (strpos($text, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->reBody2 as $lang => $re) {
                if (strpos($textPdf, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }

            $this->parsePdf($email, $textPdf);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));

        $in = [
            // 29 NOV 16, 04:05
            "/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{2})\s*,\s*(\d{1,2}:\d{2})\s*$/ui", //28 AUG 16
        ];
        $out = [
            "$1 $2 20$3, $4",
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

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function TableHeadPos($row)
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                return 'normalize-space(' . $node . ')="' . $s . '"';
            }, $field)) . ')';
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        if (empty($textRows)) {
            return '';
        }
        $length = [];
        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';
        for($l = 0; $l<$length; $l++) {
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
}
