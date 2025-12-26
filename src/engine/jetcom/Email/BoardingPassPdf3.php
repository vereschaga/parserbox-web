<?php

namespace AwardWallet\Engine\jetcom\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassPdf3 extends \TAccountChecker
{
    public $mailFiles = "jetcom/it-10684853.eml, jetcom/it-11813059.eml, jetcom/it-11938895.eml, jetcom/it-7297200.eml";

    public $reFrom = "@jet2.com";
    public $reSubject = [
        "en"=> "Your Jet2 Boarding pass",
    ];
    public $reBody = 'erved by our cabin crew may be consumed';
    public $reBody2 = [
        "en"=> "BOOKING REF:",
    ];
    public $pdfPattern = "[A-Z\d]{6}_\w{2}\d+_\d+[^\s\d]+\d{4}\.pdf";
    public $pdfFileName;

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public $flightArray = [];

    public function parsePdf(Email $email)
    {
        $text = $this->text;

        preg_match_all("#\n([^\n]* NAME:.*?DEPARTS:.*?)\n\n\n#ms", $text, $m);

        if (preg_match("/SEQ \/ CUSTOMER NAME\:\n*\s+\d/", $text)) {
            preg_match_all("#\n([^\n]* SEQ \/ CUSTOMER NAME\:\n\s*\d+.*?DEPARTS:.*?)\n\n\n#ms", $text, $m);
        }
        $airs = [];

        foreach ($m[1] as $stext) {
            $table = $this->splitCols($stext, [min(array_filter([strpos($stext, 'NAME:'), strpos($stext, 'SEQ / NAME'), strpos($stext, 'SEQ / CUSTOMER NAME:')]))]);

            if (empty($table[0])) {
                $this->logger->info("table not matched");

                return;
            }
            $table = $this->splitCols($table[0], $this->colsPos($table[0]));

            if (count($table) != 2 && count($table) != 3) {
                $this->logger->info("incorrect parse table");

                return;
            }

            if (!$rl = $this->re("#BOOKING REF:\n(.+)#", $table[0])) {
                $this->logger->info("RL not matched");

                return;
            }
            $airs[$rl][] = [$stext, $table];
        }

        foreach ($airs as $rl=>$segments) {
            $f = $email->add()->flight();
            $f->general()
                ->confirmation($rl);

            $travellers = [];

            foreach ($segments as $data) {
                $travellers[] = $this->re("#NAME:\n[ ]*(?:\d+ ?/ ?)?(?:Miss |Ms |Mrs |Mr )?(.+)#", $data[0]);
            }

            if (count($travellers) > 0) {
                $f->general()
                    ->travellers(array_unique($travellers));
            }

            foreach ($segments as $key => $data) {
                $stext = $data[0];
                $table = $data[1];
                $date = strtotime($this->normalizeDate($this->re("#DATE:\n(.+)#", $table[0])));

                $s = $f->addSegment();

                $airlineName = $this->re("#FLIGHT:\n(\w{2})\d+\n#", $table[1]);
                $flightNumber = $this->re("#FLIGHT:\n\w{2}(\d+)\n#", $table[1]);

                $s->airline()
                    ->number($flightNumber)
                    ->name($airlineName);

                $depCode = $this->re("#FROM:\n.*? \(([A-Z]{3})\)#", $table[0]);

                if ((empty($depCode)) && !empty($s->getFlightNumber()) && !empty($s->getAirlineName())
                        && preg_match("#\n[ ]*([A-Z]{3})[ ]{3,}([A-Z]{3})\s*\n.+\n[ ]*" . $s->getAirlineName() . $s->getFlightNumber() . "#", $text, $m)) {
                    $s->departure()
                        ->code($m[2]);

                    $s->arrival()
                        ->code($m[1]);
                } else {
                    $s->departure()
                        ->code($depCode);
                }

                if ((empty($s->getDepCode())) && strpos($table[0], '(') === false) {
                    $s->departure()
                        ->noCode();
                }

                $depName = $this->re("#FROM:\n(.*?)(?: Terminal .+)? \([A-Z]{3}\)#", $table[0]);

                if (empty($depName)) {
                    $depName = $this->re("#FROM:\n(.*?)(?: Terminal .+| T\w)?\n#", $table[0]);
                }

                $s->departure()
                    ->name($depName)
                    ->date(strtotime($this->re("#\n\s*(\d+:\d+)\s+\d+:\d+\s*\n#", $stext), $date));

                $depTerminal = $this->re("#FROM:\n.*?(?: Terminal (.+))? \([A-Z]{3}\)#", $table[0]);

                if (empty($depTerminal)) {
                    $depTerminal = $this->re("#FROM:\n.*?(?: Terminal (.+))?\n#", $table[0]);
                }

                if (empty($depTerminal)) {
                    $depTerminal = $this->re("#FROM:\n.*?(?: T(\w))?\n#", $table[0]);
                }

                if (!empty($depTerminal)) {
                    $s->departure()
                        ->terminal($depTerminal);
                }

                if (empty($s->getArrCode() && empty($arrCode = $this->re("#TO:\n.*? \(([A-Z]{3})\)#", $table[1])) && strpos($table[1], '(') === false)) {
                    $s->arrival()
                        ->noCode();
                }

                if (!$arrName = $this->re("#TO:\n(.*?)(?: Terminal .+)? \(([A-Z]{3})\)#", $table[1])) {
                    $arrName = $this->re("#TO:\n(.*?)(?: Terminal .+| T\w)?\n#", $table[1]);
                }

                if (!empty($arrName)) {
                    $s->arrival()
                        ->name($arrName);
                }

                $arrTerminal = $this->re("#\bTO:\n.*?(?: Terminal (.+))? \([A-Z]{3}\)#", $table[1]);

                if (empty($arrTerminal)) {
                    $arrTerminal = $this->re("#\bTO:\n.*?(?: Terminal (.+))?\n#", $table[1]);
                }

                if (empty($arrTerminal)) {
                    $arrTerminal = $this->re("#\bTO:\n.*?(?: T(\w))?\n#", $table[1]);
                }

                if (!empty($arrTerminal)) {
                    $s->arrival()
                        ->terminal($arrTerminal);
                }

                $s->arrival()
                    ->noDate();

                $bp = $email->add()->bpass();

                $bp->setDepDate($s->getDepDate())
                    ->setTraveller($travellers[$key])
                    ->setFlightNumber($airlineName . $flightNumber)
                    ->setRecordLocator($rl)
                    ->setDepCode($s->getDepCode())
                    ->setAttachmentName($this->pdfFileName);

                if (in_array($airlineName . $flightNumber, $this->flightArray) !== false) {
                    $f->removeSegment($s);

                    $seat = $this->re("#SEAT:\n(\d+\w)#", $table[1]);

                    if (!empty($seat)) {
                        $allSegments = $f->getSegments();

                        foreach ($allSegments as $seg) {
                            if ($seg->getAirlineName() === $airlineName && $seg->getFlightNumber() === $flightNumber) {
                                $seg->addSeat($seat);
                            }
                        }
                    }

                    continue;
                }

                $this->flightArray[] = $airlineName . $flightNumber;

                $s->extra()
                    ->seat($this->re("#SEAT:\n(\d+\w)#", $table[1]));
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
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

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];
        $this->pdfFileName = $this->getAttachmentName($parser, $pdf);

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($email);
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

    private function getAttachmentName(PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
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
        //$this->logger->warning($str);
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
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
}
