<?php

namespace AwardWallet\Engine\outbox\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketsPdf extends \TAccountChecker
{
    public $mailFiles = "outbox/it-396387490.eml, outbox/it-402209340.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'ORDER NUMBER:' => ['ORDER NUMBER:', 'Order Number :'],
            'Showtime:'     => ['Showtime:', 'Showtime :'],
            'E-TICKET'      => 'E-TICKET',
        ],
    ];

    private $detectFrom = "@cirquedusoleil.com";
    private $detectSubject = [
        // en (Your E-Tickets are attached - 001-0231 5537)
        'Your E-Tickets are attached - ',
    ];
    private $detectBody = [
        //        'en' => [
        //            '',
        //        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]cirquedusoleil\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
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
        // detect provider
        if ($this->containsText($text, ['Outbox Technology CRB']) === false) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['ORDER NUMBER:'])
                && $this->containsText($text, $dict['ORDER NUMBER:']) === true
                && !empty($dict['E-TICKET'])
                && $this->containsText($text, $dict['E-TICKET']) === true
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
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf), false);

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
        $tickets = $this->split("/\n( {0,40}{$this->opt($this->t('ORDER NUMBER:'))})/", "\n\n" . $textPdf);

        foreach ($tickets as $tText) {
            $column = [];

            if (preg_match_all("/\n(.* )FOLD HERE - DO NOT DETACH/", $tText, $m)
                && count($m[0]) === 2
            ) {
                $column = [0, strlen($m[1][0]), strlen($m[1][1])];
                sort($column);
            }

            if (count($column) !== 3) {
                $this->logger->debug('error. event table ');
                $email->add()->event();

                continue;
            }
            $table = $this->createTable($tText, $column, false);
            $table = preg_replace("/\bFOLD HERE - DO NOT DETACH\b/", str_pad('', strlen('FOLD HERE - DO NOT DETACH')), $table);

            $event = null;

            $re = "/\n(?<date>.+)(?:\n\s*{$this->opt($this->t('Showtime:'))}| AT ) *(?<time>\d{1,2}:\d{2}.*)(\n\s*.+ *\d{1,2}:\d{2}.*)*(?:\n *TICKET *:.*)?\n\s*(?<address>\S.+\s*\n *\S[\s\S]+?)\n(?:\s*\n|.*SEAT)/";

            if (preg_match($re, $table[2], $m)) {
                $date = strtotime($m['date'] . ', ' . $m['time']);
                $address = preg_replace(["/\s*\n\s*/", "/\s+/"], [', ', ' '], trim($m['address']));

                foreach ($email->getItineraries() as $it) {
                    if ($it->getAddress() === $address
                        && $it->getStartDate() === $date
                    ) {
                        $event = $it;
                    }
                }

                if ($event === null) {
                    $event = $email->add()->event();

                    $event->type()
                        ->show();

                    $event->place()
                        ->address($address);
                    $event->booked()
                        ->start($date)
                        ->noEnd()
                    ;
                }
            }

            if ($event === null) {
                $event = $email->add()->event();

                $event->type()
                    ->show();
            }

            $orderNumber = preg_replace('/\s+/', '', $this->re("/{$this->opt($this->t('ORDER NUMBER:'))} {0,2}(\d(?:[- ]?\d){4,})(?: {2,}|\s*\n)/i", $table[0]));

            if (!in_array($orderNumber, array_column($event->getConfirmationNumbers(), 0))) {
                $event->general()
                    ->confirmation($orderNumber);
            }

            $seatTableText = $this->re("/\n\s*\n(.*\b{$this->opt($this->t('SEAT'))}\b.*\n(?:\s*\n)*.+)/", $table[1]);
            $seatTable = $this->createTable($seatTableText, $this->rowColumnPositions($this->inOneRow($seatTableText)));
            $seat = implode(', ', array_map('trim', preg_replace('/\s+/', ' ', $seatTable)));
            $event->booked()
                ->seat($seat);

            $eventName = $this->http->FindSingleNode("//tr[*[normalize-space()][1][normalize-space() = 'Event name :'] and count(*[normalize-space()]) = 2]/*[normalize-space()][2]");

            if (empty($eventName) && strpos($tText, 'Cirque du Soleil Inc') !== false) {
                $eventName = 'Cirque du Soleil';
            }

            if (empty($eventName) && strpos($tText, 'Treasure Island Inc') !== false) {
                $eventName = 'Treasure Island';
            }

            $event->place()
                ->name($eventName);

            // Price
            $cost = $this->getTotal($this->re("/\n *{$this->opt($this->t('TICKET PRICE'))} *(.+?)(?: {2,}|\n)/", $table[2]));
            $feesText = $this->re("/\n *{$this->opt($this->t('TICKET PRICE'))}.*(\n[\s\S]+)\n *{$this->opt(preg_replace('/\s*:\s*$/', '', $this->t('ORDER NUMBER:')))}/", $table[2]);

            $total = 0.0;

            if (preg_match_all("/\n *(.* fee) {2,}(.+?)(?: {2,}|\n|$)/i", $feesText, $m)) {
                foreach ($m[1] as $i => $row) {
                    $event->price()
                        ->fee($row, $this->getTotal($m[2][$i])['amount']);

                    $total += $this->getTotal($m[2][$i])['amount'];
                }
            }

            if (!$event->getPrice()) {
                $total += $cost['amount'];
                $event->price()
                    ->cost($cost['amount']);

                $event->price()
                    ->total($total)
                    ->currency($cost['currency'])
                ;
            } else {
                $total += $cost['amount'];
                $event->price()
                    ->cost($cost['amount'] + $event->getPrice()->getCost() ?? 0.0);

                $event->price()
                    ->total($total + $event->getPrice()->getTotal() ?? 0.0)
                    ->currency($cost['currency'])
                ;
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

    private function createTable(?string $text, $pos = [], $trim = true): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $s = mb_substr($row, $p, null, 'UTF-8');
                $cols[$k][] = ($trim === false) ? $s : trim($s);
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

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
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
}
