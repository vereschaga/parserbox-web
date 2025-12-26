<?php

namespace AwardWallet\Engine\perfectdrive\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalPDF extends \TAccountChecker
{
    public $mailFiles = "perfectdrive/it-68291770.eml";
    public $subjects = [
        '/Budget Rental Confirmation\:\s+\d+$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e.budget.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'RENTAL AGREEMENT NUMBER') !== false
                && strpos($text, 'RESERVATION NUMBER') !== false
                && strpos($text, 'Customer Name') !== false
                && strpos($text, 'Plate Number') !== false
                && strpos($text, 'Veh Description') !== false
                && strpos($text, 'Budget Car') !== false) {
                return true;
            } else {
                continue;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.budget\.com$/', $from) > 0;
    }

    public function ParsePDF(Email $email, $text)
    {
        $r = $email->add()->rental();

        if (preg_match("/RENTAL AGREEMENT NUMBER\s+(?<confirmationP>\d+)\s+RESERVATION NUMBER\s+(?<confirmation>[\d\-A-Z]+).*\nCustomer Name\s+\:\s+(?<traveller>[A-Z\,]+)\s+(?<companyName>.+)\s+[#]\s+\:\s+(?<companyConfirmation>[\d\s]+)\n/u", $text, $m)) {
            $r->general()
                ->confirmation($m['confirmation'], 'RESERVATION NUMBER')
                ->confirmation($m['confirmationP'], 'RENTAL AGREEMENT NUMBER', true)
                ->traveller($m['traveller']);
        }
        $r->car()
            ->model($this->re("/Veh Description\s+\:\s+(.+)\n/u", $text));

        $rentalDetails = $this->cutText('Fuel Gauge Reading', 'Additional Fees', $text);
        $rentalDetails = preg_replace("/^.+\n\s+\n/", "", $rentalDetails);

        $rentalTable = $this->SplitCols($rentalDetails);

        if (preg_match("/^Pickup Date\/Time\s+\:\s+(\w+)\s(\d+)\,(\d{4})[@]([\d\:]+\s*A?P?M)\s+Pickup Location\s+\:\s*(.+)/su", $rentalTable[0], $m)) {
            $r->pickup()
                ->date(strtotime($m[2] . ' ' . $m[1] . ' ' . $m[3] . ', ' . $m[4]))
                ->location(str_replace("\n", " ", $m[5]));
        }

        if (preg_match("/^Return Date\/Time\s+\:\s+(\w+)\s(\d+)\,(\d{4})[@]([\d\:]+\s*A?P?M)\s+Return Location\s+\:\s*(.+)/su", $rentalTable[1], $m)) {
            $r->dropoff()
                ->date(strtotime($m[2] . ' ' . $m[1] . ' ' . $m[3] . ', ' . $m[4]))
                ->location(str_replace("\n", " ", $m[5]));
        }

        $total = $this->re("/YOUR ESTIMATED TOTAL CHARGES\:\D+([\d\.]+)/", $text);

        if (!empty($total)) {
            $r->price()
                ->total($total);
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!empty($this->re("/(RENTAL AGREEMENT NUMBER)/u", $text))) {
                $this->ParsePDF($email, $text);
            } else {
                continue;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $txt = stristr(stristr($text, $start), $end, true);

            return substr($txt, strlen($start));
        }

        return false;
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
}
