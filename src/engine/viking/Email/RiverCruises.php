<?php

namespace AwardWallet\Engine\viking\Email;

// use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RiverCruises extends \TAccountChecker
{
    public $mailFiles = "viking/it-19598498.eml, viking/it-22359993.eml, viking/it-34373499.eml, viking/it-46265340.eml";

    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = '@vikingcruises.com';
    private $detectSubject = [
        'en' => 'Viking River Cruises',
    ];
    private $detectCompany = ['Viking Cruises', 'vikingcruises.com'];
    private $detectBody = [
        "en" => ['Cruise Itinerary', 'Agency Statement', 'Guest Statement'],
    ];
    private $pdfPattern = 'Guest.+\.pdf';
    private $pdfPattern2 = '.+\.pdf';
    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfPattern2);
        }

        if (0 < count($pdfs)) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]))) === null) {
                return $email;
            }

            foreach ($this->detectBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($text, $dBody) !== false && $this->containsText($text, $this->detectCompany) !== false) {
                        $this->lang = $lang;
                        $this->parseEmail($email, $text);

                        continue 2;
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfPattern2);
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->containsText($textPdf, $this->detectCompany) === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($textPdf, $dBody) !== false) {
                        return true;
                    }
                }
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

    private function parseEmail(Email $email, string $text): void
    {
        //$this->logger->debug($text);
        // Travel Agency
        $email->ota()
            ->confirmation($this->re("#Booking Number:[ ]*(\d{5,})\s+#", $text), "Booking Number")
            // Page 1 of 4 Viking Cruises • 5700 Canoga Avenue Suite 200 • Woodland Hills, CA 91367 • 1-855-338-4546 • www.vikingcruises.com
            // 3 / 6 Viking • Suite 601 Level 6 • 66 Wentworth Ave, Surry Hills 2010 • +61 02-9299-9220 • vikingrivercruises.com.au
            ->phone($this->re("#\d+\s+Viking\s+.+? • ([\d+\-()\s]{7,}) • (?:www\.)?(?:vikingcruises|vikingrivercruises|viking)\.com#", $text));

        /*
         * CRUISE
         */

        $c = $email->add()->cruise();

        // General
        $c->general()
            ->noConfirmation();

        $pos = stripos($text, 'Shipping Address - Final Documents');
        $travellers = $this->http->FindNodes("//tr[starts-with(normalize-space(.), 'Guest Name') and not(.//tr)]/following-sibling::tr[normalize-space(.)]/td[normalize-space(.)][1]");

        if (0 < count($travellers)) {
            $c->general()
                ->travellers($travellers, true);
        } elseif (0 === count($c->getTravellers()) && !empty($pos) && preg_match_all("#\s*(.+)\n\s*ATTN:#", substr($text, $pos), $m)) {
            $travellers = [];

            foreach ($m[1] as $v) {
                $travellers = array_merge($travellers, preg_split('/\s{5,}/', $v));
            }
            $c->general()
                ->travellers($travellers, true);
        }

        // Price
        $c->price()
            ->currency($this->re("#\s{2,}Currency:?[ ]*([A-Z]{3})\s+#", $text));
        $total = $this->re("#[ ]{30,}Gross.*\n\s*Grand Total[ ]+[^\d\n]*(\d[\d\,\.]*)\s+#", $text);

        if (empty($total)) {
            $total = $this->re("#[ ]{30,}Amount.*\n\s*Total[ ]+[^\d\n]*(\d[\d\,\.]*)\s+#", $text);
        }

        if (empty($total)) {
            $total = $this->re("#[ ]{30,}Amount.*\n\s*Gross Total[ ]+[^\d\n]*(\d[\d\,\.]*)\s+#", $text);
        }

        if (!empty($total)) {
            $c->price()
                ->total($this->amount($total));
        }

        // Details
        $c->details()
            ->description($this->re("#\s{2,}Cruise ID:[ ]*(.+)#", $text) . ' ' . $this->re("#\s{2,}Cruise Name:[ ]*(.+)#", $text))
            ->roomClass($this->re("#\s{2,}Category:[ ]*(.+)#", $text))
            ->room($this->re("#\s{2,}Suite/Stateroom:[ ]*(.+)#", $text), false, true)
            ->ship($this->re("#\s{2,}Ship:[ ]*(.+)#", $text));

        //delete some comments in itinerary
        $text = preg_replace("#\n[ ]+Iceland & Golden Circle Extension[ ]*#", '', $text);
        $findedRows = false;

        if (preg_match_all("/\n([ ]*Day[ ]+Date[ ]+Description[ ]+Port Arrival[ ]+Port Depart.*)\n+((?:[ ]{0,17}[A-Z]{3} .{4,}(?:\n[ ]{27}.*){0,2}\n+)+)/", $text, $m, PREG_SET_ORDER)) {
            $findedRows = true;
            // $this->logger->debug(var_export($m, true));
            foreach ($m as $rowItem) {
                $headPos = $this->TableHeadPos($rowItem[1]);

                if (count($headPos) == 5) {
                    $rows = $this->split("#(?:^|\n)([ ]*[A-Z]{3}[ ]+\S.+)#", $rowItem[2]);

                    foreach ($rows as $row) {
                        $table = $this->SplitCols($row, $headPos);

                        if (empty(trim($table[3])) && empty(trim($table[4]))) {
                            continue;
                        }
                        $sc = $c->addSegment();
                        $sc->setName($this->re("#(?:Embark In |Disembark In )?(.+)#", $table[2]));

                        if (!empty(trim($table[3]))) {
                            $sc->setAshore($this->normalizeDate($table[1] . ' ' . $table[3]));
                        }

                        if (!empty(trim($table[4]))) {
                            $sc->setAboard($this->normalizeDate($table[1] . ' ' . $table[4]));
                        }
                    }
                }
            }
        }

        if ($findedRows == false) {
            $c->addSegment()
                ->setName($this->re("#\s{2,}Embarkation Date:[ ]*\S+?[ ]+(.+)#", $text))
                ->setAboard($this->normalizeDate($this->re("#\s{2,}Embarkation Date:[ ]*(\S+?)[ ]+.+#", $text)));
            $c->addSegment()
                ->setName($this->re("#\s{2,}Disembarkation Date:[ ]*\S+?[ ]+(.+)#", $text))
                ->setAshore($this->normalizeDate($this->re("#\s{2,}Disembarkation Date:[ ]*(\S+?)[ ]+.+#", $text)));
        }
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
        ];
        $out = [
        ];
        $str = preg_replace($in, $out, $str);

        //		if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
        //			if ($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
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

    private function SplitCols($text, $pos = false): array
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

    private function amount($price)
    {
        $price = str_replace(',', '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function containsText($text, $needle)
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                $p = stripos($text, $n);

                if ($p !== false) {
                    return $p;
                }
            }
        } elseif (is_string($needle)) {
            return stripos($text, $needle);
        }

        return false;
    }
}
