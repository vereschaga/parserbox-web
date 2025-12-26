<?php

namespace AwardWallet\Engine\flysmarter\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourETicketPdf extends \TAccountChecker
{
    public $mailFiles = "flysmarter/it-787414546.eml, flysmarter/it-797447696.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Your E - Ticket'     => 'Your E - Ticket',
            'Order number'        => 'Order number',
            'Passenger name'      => 'Passenger name',
            'DEPARTURE:'          => 'DEPARTURE:',
            'Airline reference:'  => 'Airline reference:',
            'Duration:'           => 'Duration:',
            'Cabin:'              => 'Cabin:',
            'Departing at:'       => 'Departing at:',
            'Arriving at:'        => 'Arriving at:',
            'BAGGAGE INFORMATION' => 'BAGGAGE INFORMATION',
        ],
    ];

    private $detectFrom = "support@flysmarter.";
    private $detectSubject = [
        // en
        'Travel documents',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]flysmarter\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'FlySmarter') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text)
    {
        if ($this->containsText($text, ['@flysmarter.', 'FlySmarter']) === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your E - Ticket'])
                && $this->containsText($text, $dict['Your E - Ticket']) === true
                && !empty($dict['Departing at:'])
                && $this->containsText($text, $dict['Departing at:']) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);
            }
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

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        // Travel Agency
        $email->obtainTravelAgency();
        $email->ota()
            ->confirmation($this->re("/{$this->opt($this->t('Order number'))} *(\d{5,})(?: {2,}|\n)/", $textPdf));

        // Flights
        $f = $email->add()->flight();

        // General
        $travellersTableText = $this->re("/{$this->opt($this->t('Passenger name'))} +.+\n([\s\S]+?)\n\s*{$this->opt($this->t('DEPARTURE:'))}/", $textPdf);
        $travellersTable = $this->createTable($travellersTableText, $this->rowColumnPositions($this->inOneRow($travellersTableText)));

        $confs = array_unique(array_filter(explode("\n", $travellersTable[2] ?? '')));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $travellersNames = preg_replace("/^[\s\W]*[A-Z]{3} ?\W ?[A-Z]{3}\s*,.+/", '', explode("\n", $travellersTable[0] ?? ''));
        $travellersNames = preg_replace(['/ (MR|MS|MISS|DR|MRS|MSTR)\s*$/', '/^\s*(.+?)\s*\\/\s*(.+?)\s*$/'], ['', '$2 $1'],
            array_filter($travellersNames));
        $tickets = array_filter(explode("\n", $travellersTable[1] ?? ''));

        $f->general()
            ->travellers($travellersNames);

        // Issued
        if (count($travellersNames) !== count($tickets)) {
            $travellersNames = [];
        }

        foreach ($tickets as $i => $ticket) {
            $f->issued()
                ->ticket($ticket, false, $travellersNames[$i] ?? null);
        }

        $days = $this->split("/\n *({$this->opt($this->t('DEPARTURE:'))})/", $textPdf);

        foreach ($days as $dayText) {
            $date = $this->re("/^\s*{$this->opt($this->t('DEPARTURE:'))} *(.+?)(?: {2,}|\n)/", $dayText);
            $segments = $this->split("/((?: {2}{$this->opt($this->t('Airline reference:'))} *[A-Z\d]{5,7}\n)?\s*.* {2,}[A-Z]{3} {4,}[A-Z]{3}\n)/", $dayText);
            $dConf = $this->re("/\s+{$this->opt($this->t('Airline reference:'))} *([A-Z\d]{5,7})\n/", $dayText);

            foreach ($segments as $sText) {
                $s = $f->addSegment();

                $conf = $this->re("/^\s*{$this->opt($this->t('Airline reference:'))} *([A-Z\d]{5,7})\n/", $sText);

                $sText = preg_replace("/^((?:.*\n){6,}?)\n\n\s*\S[\S\s]+$/", '$1', $sText);
                $sText = preg_replace("/^\s*{$this->opt($this->t('Airline reference:'))} *([A-Z\d]{5,7})\s*\n/", '', $sText);

                $table = $this->createTable($sText, $this->rowColumnPositions($this->inOneRow($sText)));

                // Airline
                if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,4})\s*$/m", $table[0] ?? '', $m)) {
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn']);
                }
                $s->airline()
                    ->confirmation($conf ?? $dConf);

                $re = "/^\s*(?<code>[A-Z]{3})\n(?<name>[\s\S]+?)\n\s*(?:{$this->opt($this->t('Departing at:'))}|{$this->opt($this->t('Arriving at:'))})\s*(?<time>\d+:\d+.*)(?<terminal>\n[\s\S]*{$this->opt($this->t('TERMINAL'))}[\s\S]*)?\s*$/";
                // Departure
                if (preg_match($re, $table[1] ?? '', $m)) {
                    $s->departure()
                        ->name(preg_replace('/\s+/', ' ', trim($m['name'])))
                        ->code($m['code'])
                        ->date(!empty($date) ? $this->normalizeDate($date . ', ' . $m['time']) : null)
                        ->terminal(trim(preg_replace("#\s*(?:terminal|{$this->opt($this->t('TERMINAL'))})\s*#i", ' ', $m['terminal'] ?? '')), true, true)
                    ;
                }
                // Arrival
                if (preg_match($re, $table[2] ?? '', $m)) {
                    $s->arrival()
                        ->name(preg_replace('/\s+/', ' ', trim($m['name'])))
                        ->code($m['code'])
                        ->date(!empty($date) ? $this->normalizeDate($date . ', ' . $m['time']) : null)
                        ->terminal(trim(preg_replace("#\s*(?:terminal|{$this->opt($this->t('TERMINAL'))})\s*#i", ' ', $m['terminal'] ?? '')), true, true)
                    ;
                }

                // Extra
                $s->extra()
                    ->duration($this->re("/\n *{$this->opt($this->t('Duration:'))} *(.+)/", $table[0] ?? ''))
                    ->cabin($this->re("/\n *{$this->opt($this->t('Cabin:'))} *(.+)/", $table[0] ?? ''));
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    // additional methods

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
            $pos = $this->rowColumnPositions($rows[0]);
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            //            // Apr 09
            //            '/^\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1:43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$2 $1 %year%',
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
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
