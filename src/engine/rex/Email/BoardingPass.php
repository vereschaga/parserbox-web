<?php

namespace AwardWallet\Engine\rex\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "rex/it-551187628.eml, rex/it-551966759.eml, rex/it-570145232.eml";
    public $detectSubjects = [
        'Rex Boarding Pass',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], '@apps.rex.com.au') === false
            && strpos($headers["subject"], 'Rex ') === false
        ) {
            return false;
        }

        foreach ($this->detectSubjects as $subject) {
            if (strpos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'Regional Express Holding') === false) {
                continue;
            }

            if (
                (strpos($textPdf, 'BOARDING PASS') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]rex\.com\.au$/', $from) > 0;
    }

    public function ParseEmail(Email $email, $text)
    {
        $f = $email->add()->flight();

        $bps = $this->split("/\n( *BOARDING PASS\n)/", $text);

        $confs = [];
        $travellers = [];

        foreach ($bps as $bp) {
            $table1 = $this->createTable($this->re("/\n( *PASSENGER NAME[\s\S]+?)\n *DEPARTING/", $bp));
            $table2 = $this->createTable($this->re("/\n( *DEPARTING[\s\S]+?)\n *TERMINAL/", $bp));
            $table3 = $this->createTable($this->re("/\n( *TERMINAL[\s\S]+?)\n\n/", $bp));

            if (preg_match("/^(\s*.+)\s*$/u", $table3[1], $m31)
                && preg_match("/^\s*.+\s*\n[A-Z\d\W]+( [A-Z][a-z]+.+)\s*$/u", $table3[0], $m30)
            ) {
                $table3[1] = $m31[1] . "\n" . trim($m30[1]);
                $table3[0] = preg_replace("/^(\s*.+\s*\n[A-Z\d\W]+) [A-Z][a-z]+.+\s*$/u", '$1', $table3[0]);
            }

            // $this->logger->debug('$table1 = ' . print_r($table1, true));
            // $this->logger->debug('$table2 = ' . print_r($table2, true));
            // $this->logger->debug('$table3 = ' . print_r($table3, true));

            $confs[] = $this->re("/PNR\s*([A-Z\d]{5,7})\s*$/u", $table3[3] ?? '');
            $travellers[] = $this->re("/^\s*.+\s*\n\s*(.+)\s*$/u", $table1[0] ?? '');

            $s = $f->addSegment();

            // Airline
            if (preg_match("/^\s*.+\s*\n\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5})\s*$/", $table1[1] ?? '', $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            }

            $date = $this->re("/^\s*.+\s*\n *(.+)\s*$/", $table1[3] ?? '');
            // Departure
            $s->departure()
                ->noCode()
                ->name($this->re("/^\s*.+\s*\n *([\s\S]+?)\s*\n *\d{1,2}:\d{2}/", $table2[0] ?? ''))
                ->date(strtotime($date . ', ' . $this->re("/\n *(\d{1,2}:\d{2}.*?)/", $table2[0] ?? '')))
            ;
            $terminal = $this->re("/^\s*TERMINAL\s*\n *(\S.+)\s*$/", $table3[0] ?? '');

            if (!empty($terminal)) {
                $s->departure()
                    ->terminal($terminal);
            }

            // Arrival
            $s->arrival()
                ->noCode()
                ->name($this->re("/^\s*.+\s*\n *([\s\S]+?)\s*\n *\d{1,2}:\d{2}/", $table2[1] ?? ''))
                ->date(strtotime($date . ', ' . $this->re("/\n *(\d{1,2}:\d{2}.*?)/", $table2[1] ?? '')))
            ;

            // Extra
            $s->extra()
                ->seat($this->re("/^\s*.+\s*\n *(.+)\s*$/", $table1[2] ?? ''))
                ->bookingCode($this->re("/^\s*.+\s*\n *(.+)\s*$/", $table2[3] ?? ''))
                ->cabin($this->re("/^\s*.+\s*\n *(.+)\s*$/", $table3[1] ?? ''))
            ;

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

        $confs = array_unique($confs);

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $f->general()
            ->travellers(array_unique($travellers), true);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        $allPdfText = '';

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'Regional Express Holding') === false) {
                continue;
            }

            if ((strpos($textPdf, 'BOARDING PASS') !== false)) {
                $allPdfText .= "\n\n\n" . $textPdf;
            }
        }
        $this->ParseEmail($email, $allPdfText);

        // $this->logger->debug('$allPdfText = '.print_r( $allPdfText,true));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function columnPositions($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($this->inOneRow($text));
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

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
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
}
