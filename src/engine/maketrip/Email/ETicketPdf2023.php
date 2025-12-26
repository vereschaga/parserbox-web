<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketPdf2023 extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-413806548.eml, maketrip/it-414705772.eml, maketrip/it-414932518.eml, maketrip/it-415094414.eml, maketrip/it-415111527.eml, maketrip/it-415197513.eml";

    public $providerCode;
    public static $detectProvider = [
        'goibibo' => [
            'from'       => '@goibibo.com',
            'detectBody' => ['Goibibo'],
        ],
        'maketrip' => [
            'from'       => '@makemytrip.com',
            'detectBody' => ['MakeMyTrip'],
        ],
    ];

    public $detectBody = [
        'en' => [
            'Barcode(s) for your journey',
            'Your trip details',
            'Flight Ticket (',
        ],
    ];
    public $detectSubject = [
        // en
        "E-Ticket for Your Flight Booking ID :",
    ];

    public $pdfPattern = ".*\.pdf";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
        ],
    ];

    public function parsePdf(Email $email, $text): void
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->re("/{$this->opt($this->t('Booking ID:'))} ?([A-Z\d]{6,})\s*(?:,|\n)/", $text));

        // Price
        $total = $this->re("/\n *{$this->opt($this->t('You have paid'))} *(.+?) *({$this->opt($this->t('You saved'))}|\n)/", $text);

        if (preg_match('/^\s*(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d]*)\s*$/', $total, $m)
            || preg_match('/^\s*(?<amount>\d[,.\'\d]*) *(?<currency>[A-Z]{3})\s*$/', $total, $m)
        ) {
            // makemytrip
            $email->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
            ;
        }

        if (empty($total)) {
            $total = $this->re("/\n *{$this->opt($this->t('Total Price'))} +(\d[,.'\d]*)( {3,}|\n)/", $text);

            if (preg_match('/^\s*(?<amount>\d[,.\'\d]*)\s*$/', $total, $m)) {
                // goibibo
                $email->price()
                    ->total(PriceHelper::parse($m['amount']))
                ;
            }
        }

        // FLIGHT
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation()
        ;

        $travellers = [];
        $infants = [];
        $tickets = [];

        $routes = $this->split("/(\n.+ duration\n)/", $text);

        foreach ($routes as $route) {
            $date = $this->normalizeDate($this->re("/^\s*(.+?)•/", $route));
            $segments = $this->split("/(?:duration\n|\n\n)((?: {2,15}[A-Z\d\W ]+(?:    \S.+)?\n){1,2} {2,15}(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])-\d{1,5}(?: {5,}|\n))/", $route);

            foreach ($segments as $sText) {
                $s = $f->addSegment();

                $tableText = $this->re("/^((?:.*\n){1,3} +(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])-\d{1,5}.*(?:.*\n)+?)\n\n\n/", $sText);

                if (preg_match("/^(?<before>[\s\S]+\n {0,15}PNR +[A-Z\d]+( {3,}.*)?\n(?:.*\n+){0,5} {0,15})(?<pnr>PNR +[A-Z\d]+)(?<after>(?: {3,}.*)?\n[\s\S]*)$/", $tableText, $m)) {
                    $tableText = $m['before'] . str_pad('', strlen($m['pnr']), ' ') . $m['after'];
                }
                $tableText = preg_replace("/(\n *PNR +[\s\S]+?)\n {0,15}\S[\s\S]+/", '$1', $tableText);

                if (preg_match("/(?<start> {3})(?<duration>( \d{1,2} ?(?:h|m)){1,2})(?<end> {3}|\n)/", $tableText, $m)) {
                    $s->extra()
                        ->duration(trim($m['duration']));
                    $tableText = str_replace($m[0], $m['start'] . str_pad('', strlen($m['duration'])) . $m['end'], $tableText);
                }
                $pos = $this->tableHeadPos($this->inOneRow($tableText));

                if (isset($pos[1]) && $pos[1] < 15) {
                    unset($pos[1]);
                    $pos = array_values($pos);
                }
                $table = $this->splitCols($tableText, $pos);

                if (count($table) !== 3) {
                    $this->logger->debug('parsing table error');

                    return;
                }

                // Airline
                if (preg_match("/\n *(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])-(?<fn>\d{1,5})\n/", $table[0] ?? '', $m)) {
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn']);
                }

                if (preg_match("/\n *PNR +([A-Z\d]{5,7}) *\n/", $table[0], $m)) {
                    $s->airline()
                        ->confirmation($m[1]);
                }

                // Departure
                $re = "/^(?<city>[\s\S]+)\n *(?<code>[A-Z]{3}) (?<time>\d{1,2}:\d{2}) hrs\s*\n(?<date>.+)\n(?<airport>[\s\S]+)$/";

                if (preg_match($re, $table[1] ?? '', $m)) {
                    $m = preg_replace('/\s+/', ' ', $m);

                    if (preg_match("/^([\s\S]+)\s+Terminal (\S[\s\S]*)?\s*$/", $m['airport'], $mt)) {
                        $m['airport'] = $mt[1];
                        $m['terminal'] = $mt[2];
                    }
                    $m = array_map('trim', preg_replace('/\s+/', ' ', $m));
                    $s->departure()
                        ->code($m['code'])
                        ->name(trim($m['city']) . ', ' . ($m['airport']))
                        ->date(strtotime($m['time'], $this->normalizeDateRelative($m['date'], $date)))
                        ->terminal(trim($m['terminal'] ?? ''), true, true)
                    ;
                }

                // Arrival
                $re = "/^(?<city>[\s\S]+)\n *(?<time>\d{1,2}:\d{2}) hrs (?<code>[A-Z]{3})\s*\n(?<date>.+)\n(?<airport>[\s\S]+)$/";

                if (preg_match($re, $table[2] ?? '', $m)) {
                    if (preg_match("/^([\s\S]+)\s+Terminal (\S[\s\S]*)?\s*$/", $m['airport'], $mt)) {
                        $m['airport'] = $mt[1];
                        $m['terminal'] = $mt[2];
                    }
                    $m = array_map('trim', preg_replace('/\s+/', ' ', $m));
                    $s->arrival()
                       ->code($m['code'])
                       ->name(trim($m['city']) . ', ' . ($m['airport']))
                       ->date(strtotime($m['time'], $this->normalizeDateRelative($m['date'], $date)))
                       ->terminal(trim($m['terminal'] ?? ''), true, true)
                    ;
                }

                $travellerTableText = $this->re("/\n( *TRAVELLER {2,}.*\n+(.*(?:\n.*)?(?:Adult|Child|Infant).*\n+)+)/", $sText);
                $travellerTable = $this->splitCols($travellerTableText, $this->TableHeadPos($this->inOneRow($travellerTableText)));

                if (preg_match("/^ *SEAT/", $travellerTable[1] ?? '', $m)
                    && preg_match_all("/^ *(\d{1,3}[A-Z]) *$/m", $travellerTable[1] ?? '', $m)
                ) {
                    $s->extra()
                        ->seats($m[1]);
                }

                if (preg_match("/^ *E-TICKET NO/", $travellerTable[3] ?? '', $m)
                    && preg_match_all("/^ *(\d{1,3}\-?\d{5,}) *$/m", $travellerTable[3] ?? '', $m)
                ) {
                    $tickets = array_merge($tickets, $m[1]);
                }

                $travellersNames = preg_split("/(Adult(?: *\(Student\))?|Child|Infant)(?:\n|$)/", preg_replace("/^\s*.+\n/", '', $travellerTable[0] ?? ''), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

                foreach ($travellersNames as $i => $tn) {
                    if (preg_match("/\s*(?:Adult|Child)\s*/", $travellersNames[$i + 1] ?? '', $m)) {
                        $travellers[] = trim($tn);
                    }

                    if (preg_match("/\s*(?:Infant)\s*/", $travellersNames[$i + 1] ?? '', $m)) {
                        $infants[] = trim($tn);
                    }
                }
            }
        }

        $travellers = array_filter(preg_replace("/^\s*\w+\. /", '', $travellers));
        $travellers = array_filter(preg_replace("/\s+/", ' ', $travellers));
        $f->general()
            ->travellers(array_unique($travellers));

        $infants = array_filter(preg_replace("/^\s*\w+\. /", '', $infants));
        $infants = array_filter(preg_replace("/\s+/", ' ', $infants));

        if (!empty($infants)) {
            $f->general()
                ->infants(array_unique($infants));
        }

        if (!empty($tickets)) {
            $f->issued()
                ->tickets(array_unique($tickets), false);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, '@makemytrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $code => $params) {
            if (isset($params['from']) && !stripos($headers["from"], $params['from']) === false) {
                $this->providerCode = $code;

                break;
            }
        }

        if (empty($this->providerCode)) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return false;
            }

            $detects = ['value' => 0, 'codes' => []];

            foreach (self::$detectProvider as $code => $params) {
                if (!isset($params['detectBody'])) {
                    continue;
                }
                $count = 0;

                foreach ($params['detectBody'] as $dBody) {
                    $count += substr_count($text, $dBody);
                }

                if ($count > $detects['value']) {
                    $detects = ['value' => $count, 'codes' => [$code]];
                } elseif ($count === $detects['value']) {
                    $detects['codes'][] = $code;
                }
            }

            if ($detects['value'] > 0 && count($detects['codes']) === 1) {
                $this->providerCode = $detects['codes'][0];
            }

            if (!$this->providerCode) {
                continue;
            }

            foreach ($this->detectBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return false;
            }

            $detects = ['value' => 0, 'codes' => []];

            foreach (self::$detectProvider as $code => $params) {
                if (!isset($params['detectBody'])) {
                    continue;
                }
                $count = 0;

                foreach ($params['detectBody'] as $dBody) {
                    $count += substr_count($text, $dBody);
                }

                if ($count > $detects['value']) {
                    $detects = ['value' => $count, 'codes' => [$code]];
                } elseif ($count === $detects['value']) {
                    $detects['codes'][] = $code;
                }
            }

            if ($detects['value'] > 0 && count($detects['codes']) === 1) {
                $this->providerCode = $detects['codes'][0];
            }

            if (!$this->providerCode) {
                continue;
            }

            foreach ($this->detectBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        $this->parsePdf($email, $text);

                        continue 3;
                    }
                }
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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDateRelative($date, $relativeDate)
    {
        if (empty($relativeDate)) {
            return null;
        }
        $year = date('Y', $relativeDate);
        $in = [
            // Sun, Apr 09
            '#^\s*([[:alpha:]]+),\s*(\d+)\s+([[:alpha:]]+)\s*$#iu',
        ];
        $out = [
            '$1, $2 $3 ' . $year,
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>[[:alpha:]]+), (?<date>\d+ [[:alpha:]]+ \d{4})\s*$#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } else {
            $date = null;
        }

        return $date;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('normalizeDate = '.print_r( $date,true));
        $in = [
            // Mon, 20 Mar 2023
            "#^\s*[^\s\d]+,\s+(\d+\s+[^\s\d]+\s+\d{4})\s*$#",
        ];
        $out = [
            "$1",
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('normalizeDate 2 = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^\s*\d+\s+([[:alpha:]]+)\s+\d{4}$#", $date, $m)) {
            return strtotime($date);
        }

        return null;
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
