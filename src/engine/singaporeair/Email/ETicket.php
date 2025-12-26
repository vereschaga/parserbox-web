<?php

namespace AwardWallet\Engine\singaporeair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicket extends \TAccountCheckerExtended
{
    public $mailFiles = "singaporeair/it-1.eml, singaporeair/it-10.eml, singaporeair/it-1795610.eml, singaporeair/it-1925573.eml, singaporeair/it-2.eml, singaporeair/it-3392365.eml, singaporeair/it-33926788.eml, singaporeair/it-4.eml, singaporeair/it-4623506.eml, singaporeair/it-5.eml, singaporeair/it-5125746.eml, singaporeair/it-6.eml, singaporeair/it-6228017.eml, singaporeair/it-6288085.eml, singaporeair/it-6722586.eml, singaporeair/it-7.eml, singaporeair/it-8.eml, singaporeair/it-8169225.eml";
    public static $dictionary = [
        "en" => [
            "Ticket Number:"        => ["Ticket Number:", "Electronic Ticket Receipt:", 'In connection with ticket:'],
            "Booking of reference:" => ["Booking of reference:", "Booking Reference:", "Booking reference:", "SQ Booking Reference:"],
            //            "Date of Issue:" => "",
            //            "Frequent Flyer Number:" => "",
            "FLIGHT DETAILS" => ['FLIGHT DETAILS', 'Flight Details'],
            //            "Operated by" => "",
            //            "From:" => "",
            //            "Terminal:" => "",
            //            "Depart:" => "",
            //            "Status:" => "",
            //            "To:" => "",
            //            "Arrive:" => "",
            "Stopovers:" => ["Stopovers:", "Stopover:"],
            //            "Requested Seat:" => "",
            //            "Flight Meal:" => "",
            "Important Notices" => ['Payment Details', 'Important Notices', 'IMPORTANT NOTICES'],
            //            "Payment Details" => "",
            //            "Fare:" => "",
            //            "Form of Payment" => "",
            //            "Tax:" => "",
            //            "Total:" => "",
        ],
    ];

    private $detectFrom = ['singaporeair.com', 'silkair.com'];
    private $detectSubject = [
        'en' => 'SIA E-Ticket - ',
        'SIA Itinerary - ',
        'SilkAir E-Ticket - ',
        'SilkAir Itinerary - ',
    ];
    private $pdfNamePattern = '.+\.pdf';
    private $detectBody = [
        "en" => ['FLIGHT DETAILS', 'Flight Details'],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $type = true;

        if (empty($pdfs)) {
            $pdfs = $parser->getAttachments();
            $type = false;
        }

        foreach ($pdfs as $i => $pdf) {
            $pos = is_int($pdf) ? $pdf : $i;

            if ($type === false && strpos($parser->getAttachmentHeader($pos, 'content-type'), 'application/pdf') === false) {
                continue;
            }
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pos));

            if (empty($textPdf)) {
                continue;
            }

            foreach ($this->detectBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($textPdf, $dBody) !== false) {
                        $this->lang = $lang;
                        $this->parsePdf($email, $textPdf);

                        continue 3;
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
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $type = true;

        if (empty($pdfs)) {
            $pdfs = $parser->getAttachments();
            $type = false;
        }

        foreach ($pdfs as $i => $pdf) {
            $pos = is_int($pdf) ? $pdf : $i;

            if ($type === false && strpos($parser->getAttachmentHeader($pos, 'content-type'), 'application/pdf') === false) {
                continue;
            }
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pos));

            if (empty($textPdf)) {
                continue;
            }
            $detectProvider = false;

            foreach ($this->detectFrom as $dFrom) {
                if (stripos($textPdf, $dFrom) !== false) {
                    $detectProvider = true;

                    break;
                }
            }

            if (stripos($textPdf, 'www.singaporeair.com') !== false
                || stripos($textPdf, 'please contact the Singapore Airlines') !== false
                || stripos($textPdf, 'You may provide feedback or send queries to Singapore Airlines') !== false
                || stripos($textPdf, 'Singapore Company Registration Number') !== false
            ) {
                $detectProvider = true;
            }

            if ($detectProvider !== true) {
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

    private function parsePdf(Email $email, string $text)
    {
        $text = preg_replace("#[ ]+Page \d+ of \d+.*\n#", '', $text);

        if (is_array($this->t("FLIGHT DETAILS"))) {
            foreach ($this->t("FLIGHT DETAILS") as $value) {
                $posMainBegin = strpos($text, $value);

                if (!empty($posMainBegin)) {
                    break;
                }
            }
        } else {
            $posMainBegin = strpos($text, $this->t("FLIGHT DETAILS"));
        }
        $partHeader = substr($text, 0, $posMainBegin);

        //in segements could be notice like:
        //"Please refer to the Important Notices section below for details regarding your Scoot flight."
        //so Important Notices +\n
        if (is_array($this->t("Important Notices"))) {
            foreach ($this->t("Important Notices") as $value) {
                $posMainEnd[] = strpos($text, $value . "\n", $posMainBegin);
            }
        } else {
            $posMainEnd[] = strpos($text, $this->t("Important Notices") . "\n", $posMainBegin);
        }
        $posMainEnd = array_filter($posMainEnd);
        $posMainEnd = (empty($posMainEnd)) ? 0 : min($posMainEnd);

        $partMain = substr($text, $posMainBegin, $posMainEnd - $posMainBegin);

        if (strpos($text, $this->t("Payment Details"), $posMainEnd) !== false) {
            if (is_array($this->t("Important Notices"))) {
                foreach ($this->t("Important Notices") as $value) {
                    $posPaymentEnd[] = strpos($text, $value . "\n", $posMainEnd);
                }
            } else {
                $posPaymentEnd[] = strpos($text, $this->t("Important Notices") . "\n", $posMainEnd);
            }
            $posPaymentEnd = array_filter(array_map(function ($v) use ($posMainEnd) {return $v - $posMainEnd; }, array_filter($posPaymentEnd)));
            $posPaymentEnd = (empty($posPaymentEnd)) ? 0 : min($posPaymentEnd);

            $partPayment = substr($text, $posMainEnd, $posPaymentEnd);
        }

        $f = $email->add()->flight();

        $pax = explode("\n", $this->re("#\n[ ]{0,10}(\D+)\s*\n\s*" . $this->preg_implode($this->t("Booking of reference:")) . "#", $partHeader));

        foreach ($pax as $i => $p) {
            if (preg_match("/^\s*(.+?)\s*\(" . $this->preg_implode($this->t("with infant")) . " (.+)\)\s*$/", $p, $m)) {
                $pax[$i] = $m[1];
                $m[2] = preg_replace("# (MR|MISS|DR|MSTR)$#", '', $m[2]);
                $f->general()
                    ->infant($m[2], true);
            }
        }
        $pax = preg_replace("# (MR|MISS|DR|MSTR)$#", '', array_filter(array_map("trim", $pax)));

        // General
        $f->general()
            ->confirmation($this->re("#" . $this->preg_implode($this->t("Booking of reference:")) . "[ ]*([A-Z\d]{5,7})\s+#", $partHeader))
            ->travellers($pax)
            ->date($this->normalizeDate($this->re("#" . $this->preg_implode($this->t("Date of Issue:")) . "[ ]*(.+)\s+#", $partHeader)))
        ;

        if (preg_match("#It does not entitle you to travel on the listed flight#", $partHeader)) {
            $f->general()->status("Not Ticketed");
        }

        // Program
        $account = $this->re("#" . $this->preg_implode($this->t("Frequent Flyer Number:")) . "[ ]*SQ(\d+)\s+#", $partHeader);

        if (!empty($account)) {
            $f->program()->account($account, false);
        }

        // Issued
        $tickets = str_replace(' ', '', array_unique(array_filter($this->res("#" . $this->preg_implode($this->t("Ticket Number:")) . "[ ]*([\d ]{10,}?)(?:[ ]{2,}|\n|\/[A-Z\d]{2})#", $partHeader . $partMain))));

        if (!empty($tickets)) {
            $f->issued()->tickets($tickets, false);
        }

        // Price
        if (!empty($partPayment)) {
            $partPayment = $this->re("#\n(.*" . $this->preg_implode($this->t("Fare:")) . "(?:.*\n)+?" . $this->preg_implode($this->t("Total:")) . ".+)#", $partPayment);
            $ptable = $this->SplitCols($partPayment, [0, strlen($this->re("#(.+)" . $this->preg_implode($this->t("Form of Payment")) . "#", $partPayment))]);

            if (!empty($ptable[0])) {
                $currency = $this->re("#" . $this->preg_implode($this->t("Total:")) . "[ ]+\d[\d.]*[ ]*([A-Z]{3})(?:\s+|$)#", $ptable[0]);
                $f->price()
                    ->cost($this->amount($this->re("#" . $this->preg_implode($this->t("Fare:")) . "[ ]+(\d[\d.]*)[ ]*[A-Z]{3}\s+#", $ptable[0])), false, true)
                    ->total($this->amount($this->re("#" . $this->preg_implode($this->t("Total:")) . "[ ]+(\d[\d.]*)[ ]*[A-Z]{3}(?:\s+|$)#", $ptable[0])), false, true)
                    ->currency($currency, false, true)
                ;
            }
            $taxesText = $this->re("#" . $this->preg_implode($this->t("Tax:")) . "((?:.*\n)+?)" . $this->preg_implode($this->t("Total:")) . "#", $ptable[0]);
            $taxesRows = array_filter(explode("\n", $taxesText));

            foreach ($taxesRows as $row) {
                if (preg_match("#\s*(.+)\s+(\d[\d.]+)[ ]*([A-Z]{3})(?:\s+|$)#", $row, $m)) {
                    $f->price()->fee(trim($m[1]), $this->amount($m[2]));

                    if (!$currency) {
                        $currency = $m[3];
                        $f->price()->currency($currency);
                    }
                }
            }

            if (!empty($ptable[1])) {
                if (preg_match("#Form of Payment[ ]*\d+:[ ]*FFRSQ(?<ff>[A-Z\d]{5,})-M(?<miles>\d+)\*#", $ptable[1], $m)) {
                    $f->price()->spentAwards($m['miles'] . ' Miles');

                    if (!in_array($m['ff'], array_column($f->getAccountNumbers(), 0))) {
                        $f->program()
                            ->account($m['ff'], false);
                    }
                }
            }
        }

        // Segments
        $segments = $this->split("#\n([ ]*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]?\d{1,5}[ ]+" . $this->preg_implode($this->t("Operated by")) . ")#", $partMain);

        if (empty($segments)) {
            $this->logger->debug("empty segments");

            return $email;
        }

        foreach ($segments as $stext) {
            $s = $f->addSegment();
            // Airline
            $s->airline()
                ->name($this->re("#^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]?\d{1,5}[ ]+#m", $stext))
                ->number($this->re("#^\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]?(\d{1,5})[ ]+#m", $stext))
                ->operator($this->re("#" . $this->preg_implode($this->t("Operated by")) . " (\S.+?)[ ]{2,}#m", $stext), true, true)
            ;

            // Departure
            $code = $this->re("#\s+" . $this->preg_implode($this->t("From:")) . ".*?\(([A-Z]{3})[ ]*-[ ]*.*?(?:" . $this->t("Terminal:") . "|" . $this->t("Depart:") . ")#", $stext);

            if (empty($code)) {
                $code = $this->re("#\s+" . $this->preg_implode($this->t("From:")) . "[ ]*([A-Z]{3})[ ]*(?:" . $this->t("Depart:") . ")#", $stext);
            }
            $s->departure()
                ->code($code)
                ->terminal(trim($this->re("#" . $this->t("Terminal:") . "(.+?)[ ]+" . $this->t("Depart:") . "#", $stext), '-'), true, true)
                ->date($this->normalizeDate($this->re("#" . $this->t("Depart:") . "[ ]*(.+?)(?:" . $this->t("Status[]:") . "|[ ]{2,}|\n)#", $stext)));

            $from = $this->re("#\s+" . $this->preg_implode($this->t("From:")) . "[ ]*(.+?)(?:" . $this->t("Terminal:") . "|" . $this->t("Depart:") . ")#", $stext);

            if (preg_match("#(.+?)\s*\([A-Z]{3}\s*-\s*(.*?)\)?\s*$#", $from, $m)) {
                $s->departure()
                    ->name(implode(', ', array_filter([trim($m[1]), trim($m[2])])));
            }

            // Arrival
            $code = $this->re("#\s+" . $this->preg_implode($this->t("To:")) . ".*?\(([A-Z]{3})[ ]*-[ ]*.*?(?:" . $this->t("Terminal:") . "|" . $this->t("Arrive:") . ")#", $stext);

            if (empty($code)) {
                $code = $this->re("#\s+" . $this->preg_implode($this->t("To:")) . "[ ]*([A-Z]{3})[ ]*(?:" . $this->t("Arrive:") . ")#", $stext);
            }
            $s->arrival()
                ->code($code)
                ->terminal(trim($this->re("#" . $this->t("Terminal:") . "(.+?)[ ]+" . $this->t("Arrive:") . "#", $stext), '-'), true, true)
                ->date($this->normalizeDate($this->re("#" . $this->t("Arrive:") . "[ ]*(.+?)(?:" . $this->preg_implode($this->t("Stopovers:")) . "|\n)#", $stext)));

            $to = $this->re("#\s+" . $this->preg_implode($this->t("To:")) . "[ ]*(.+?)(?:" . $this->t("Terminal:") . "|" . $this->t("Arrive:") . ")#", $stext);

            if (preg_match("#(.+?)\s*\([A-Z]{3}\s*-\s*(.*?)\)?\s*$#", $to, $m)) {
                $s->arrival()
                    ->name(implode(', ', array_filter([trim($m[1]), trim($m[2])])));
            }

            // Extra
            $s->extra()
                ->aircraft(trim($this->re("#" . $this->preg_implode($this->t("Operated by")) . " (?:\S.+?)[ ]{2,}(\S.+?)[ ]{2,}\S.*\n#", $stext)), true, true)
                ->bookingCode($this->re("#\(([A-Z]{1,2})\)\s*\n\s*" . $this->preg_implode($this->t("From:")) . "#", $stext), true, true)
                ->cabin($this->re("#.+[ ]{2,}(.+?\S) ?\(([A-Z]{1,2})\)\s*\n\s*" . $this->preg_implode($this->t("From:")) . "#", $stext), true, true)
                ->status($this->re("#" . $this->preg_implode($this->t("Status:")) . "[ ]*(.+)#", $stext))
                ->stops($this->re("#" . $this->preg_implode($this->t("Stopovers:")) . "[ ]*(\d+)#", $stext), true, true)
                ->meal($this->re("#" . $this->preg_implode($this->t("Flight Meal:")) . "[ ]*(.+?)(?:[ ]{2,}|\n|$)#", $stext), true, true)
            ;

            if (preg_match("#\n *" . $this->preg_implode($this->t("Requested Seat:")) . "[ ]*(\d{1,3}[A-Z]\b.*\n( *\d{1,3}[A-Z]\b.*\n+)*)#", $stext, $m)
            ) {
                $s->extra()
                    ->seats($this->res("#^[ ]*(\d{1,3}[A-Z])\b#m", $m[1]));
            }
        }

        return $email;
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
        //	    $this->logger->alert($str);
//        $this->http->log('$str = '.print_r( $str,true));
        $in = [
            "#^\s*[^\d\s]+,\s*(\d{1,2})\s+([^\d\s]+)\s+(\d{4})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$#i", // Sat, 11 May 2019 , 08:00
        ];
        $out = [
            "$1 $2 $3, $4",
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

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function amount($price)
    {
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
            '€' => 'EUR',
            '$' => 'USD',
            'A$'=> 'AUD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
