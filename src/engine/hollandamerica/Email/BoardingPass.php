<?php

namespace AwardWallet\Engine\hollandamerica\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "hollandamerica/it-374846267.eml, hollandamerica/it-377714072.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'boarding pass' => 'boarding pass',
            // 'GUEST:' => '',
            'SHIP NAME:' => 'SHIP NAME:',
            // 'CATEGORY/DECK:' => '',
            // 'STATEROOM' => '',
            // 'Mariner ID:' => '',
            // 'Booking/Party No:' => '',
            // 'Voyage No/Name:' => '',
            // 'Documents created on' => '',
            // 'your itinerary' => '',
            // 'DAY' => '',
            // 'DATE' => '',
            // 'PORT' => '',
        ],
    ];

    private $detectFrom = "no_reply@hollandamerica.com";
    private $detectSubject = [
        // en
        'Boarding Pass',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]hollandamerica\.com$/", $from) > 0;
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
        if ($this->containsText($text, ['www.hollandamerica.com', 'choosing Holland America Line']) === false) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['boarding pass'])
                && $this->containsText($text, $dict['boarding pass']) === true
                && !empty($dict['SHIP NAME:'])
                && $this->containsText($text, $dict['SHIP NAME:']) === true
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
        // Express Docs | boarding pass     www.hollandamerica.com   pg 2 of 12
        $textPdf = preg_replace("/\n *Express Docs *\| *.* {2,}.* {3,}pg \d+ of \d+\n/i", "\n", $textPdf);

        if (preg_match("/\n( *Look for 'Holland America Line')/", $textPdf, $m)) {
            $textPdf = preg_replace("/\n( *Look for 'Holland America Line')/", "\n" . str_pad('', strlen($m[1])), $textPdf);
        }

        $c = $email->add()->cruise();

        $headerPart = $this->re("/((?:\n|^) {0,15}{$this->opt($this->t('boarding pass'))}\b[\s\S]+{$this->opt($this->t('Documents created on'))}.+\n)/", $textPdf);

        // General
        $c->general()
            ->confirmation($this->re("/ {3,}{$this->opt($this->t('Booking/Party No:'))} +([A-Z\d]+) *\\//", $headerPart))
            ->traveller(preg_replace("/^\s*(.+?)\s*,\s*(.+?)\s*$/", '$2 $1', $this->re("/ {3,}{$this->opt($this->t('GUEST:'))} *(.+?)(?: {3,}|\n)/", $headerPart)))
        ;

        // Details
        $c->details()
            ->ship($this->re("/ {3,}{$this->opt($this->t('SHIP NAME:'))} *(.+?)(?: {3,}|\n)/", $headerPart))
            ->roomClass(
                $this->re("/ {3,}{$this->opt($this->t('CATEGORY/DECK:'))} +(.+?) *\//", $headerPart)
                ??
                $this->re("/ {3,}{$this->opt($this->t('CATEGORY:'))} +(.+?)(?: {3,}|\n)/", $headerPart))
            ->deck($this->re("/ {3,}{$this->opt($this->t('CATEGORY/DECK:'))} +.+? *\/ *(.+?)(?: {3,}|\n)/", $headerPart), true, true)
            ->room($this->re("/ {3,}{$this->opt($this->t('STATEROOM:'))} +(.+?)(?: {3,}|\n)/", $headerPart))
        ;
        $number = $this->re("/ {3,}{$this->opt($this->t('Voyage No/Name:'))} +(.+?) *\//", $headerPart);
        $description = $this->re("/ {3,}{$this->opt($this->t('Voyage No/Name:'))} *.+? *\/ *(.+?)(?: {3,}|\n)/", $headerPart);

        if (empty($description) && empty($number)
            && preg_match("/\n( *\S+.*? {3,}{$this->opt($this->t('Voyage No/Name:'))}(?:.*\n){4})/", $headerPart, $m)
        ) {
            $tableVoyage = implode("\n", $this->createTable($m[1], $this->rowColumnPositions($this->inOneRow($m[1]))));

            if (preg_match("/{$this->opt($this->t('Voyage No/Name:'))}\s*([A-Z\d]+?)\s*\\/\s*([A-Z_\d+\W\s]+)\s*(?:\n|$)/s", $tableVoyage, $mt)) {
                $number = $mt[1];
                $description = $mt[2];
            }
        }

        if (empty($description)) {
            $description = $this->re("/\n *{$this->opt($this->t('Cruisetour:'))} *(.+?)(?: {3,}|\n)/", $headerPart);
        }
        $c->details()
            ->number($number, true, true)
            ->description($description);

        // Program
        $account = $this->re("/ {3,}{$this->opt($this->t('Mariner ID:'))} +(.+?)(?: {3,}|\n)/", $headerPart);

        if (!empty($account)) {
            $c->program()
                ->account($account, false);
        }

        $dateRelative = $this->normalizeDate($this->re("/{$this->opt($this->t('Documents created on'))} *(.+)/",
            $textPdf));

        $itineraryPart = $this->re("/\n {0,15}{$this->opt($this->t('your itinerary'))}\s*(\n +{$this->opt($this->t('DAY'))} +{$this->opt($this->t('DATE'))} +{$this->opt($this->t('PORT'))}.*"
            . "[\s\S]+?)\n {0,15}{$this->opt($this->t('cancellation'))}.*(\n+ {30,}.*){0,4}\n {0,15}{$this->opt($this->t('protection plan'))}/", $textPdf);

        $itineraryPart = preg_replace("/^ {0,15}\S( ?\S){0,23}$/m", '', $itineraryPart);

        if (preg_match_all("/\n {0,15}\S.+? {3,}/", $itineraryPart, $m)) {
            $m[0] = array_unique($m[0]);

            foreach ($m[0] as $v) {
                $itineraryPart = str_replace($v, str_pad('', strlen($v), ' '));
            }
        }

        $parts = $this->split("/\n( +{$this->opt($this->t('DAY'))} +{$this->opt($this->t('DATE'))} +{$this->opt($this->t('PORT'))})/", "\n\n" . $itineraryPart);

        $containsHotel = false;

        foreach ($parts as $part) {
            $hearderPos = [];

            if (preg_match("/NIGHT STAY/", $part)) {
                $containsHotel = true;
            }

            if (preg_match("/^(((( *\w+ +)\w+ +)\w+ +)\w+ +)/", $part, $m)) {
                $hearderPos = [0, strlen($m[4]), strlen($m[3]), strlen($m[2]), strlen($m[1])];
            }

            if (count($hearderPos) != 5) {
                return false;
            }
            $rows = $this->split("/\n( {15,}[[:alpha:]]{3} +[[:alpha:]]+ \d{1,2} +)/", $part);

            if (count($rows) == 1 && !preg_match("/\n *[[:alpha:]]{3} +[[:alpha:]]+ \d{1,2} +/", $rows[0])) {
                $rows = [];
            }

            $nextFlight = false;

            foreach ($rows as $row) {
                if ($nextFlight == true) {
                    $nextFlight = false;

                    continue;
                }
                $table = $this->createTable($this->re("/^(.+)/", $row), $hearderPos);

                if (preg_match("/^ *([[:alpha:]]{3}) +(.+)/", $table[0], $m)) {
                    $table[0] = $m[1];
                    $table[1] = $m[2] . $table[1];
                }

                if (preg_match("/^((?:.*\D)+)(\d{1,2}:\d{2}(?: ?[ap]m)?)\s*$/", $table[2], $m)) {
                    $table[2] = $m[1];
                    $table[3] = $m[2];
                }

                if (!preg_match("/^\s*(\d{1,2}:\d{2}(?: ?[ap]m)?)\s*$/", $table[3])
                    && preg_match("/^((?:.*\D)+) *(\d{1,2}:\d{2}(?: ?[ap]m)?)\s*$/", $table[2] . $table[3], $m)
                ) {
                    $table[2] = $m[1];
                    $table[3] = $m[2];
                }

                if (preg_match("/^.+\n\s*In air/i", $row)) {
                    $nextFlight = true;
                    $email->add()->flight();

                    continue;
                }

                if (preg_match("/NIGHT STAY/", $row)) {
                    if (preg_match("/\n *(?<name>[A-Z\d][A-Z\d\W ]+?)\n(?<address>(?: *[A-Z\d][A-Z\d\W ]+?\n))(?: *PHONE:(?<phone>[\d\W ]{5,})\n)? *(?<nights>\d+) NIGHT STAY \\//", $row, $m)
                        && !preg_match("/\btransfer/i", $m['address'])
                    ) {
                        $hotel = $email->add()->hotel();

                        $hotel->general()
                            ->noConfirmation()
                            ->travellers(array_column($c->getTravellers(), 0));

                        $hotel->hotel()
                            ->name(trim($m['name']))
                            ->address(preg_replace('/\s+/', ' ', trim($m['address'])))
                            ->phone(trim($m['phone']), true);

                        $hotel->booked()
                            ->checkIn($this->normalizeDate($table[0] . ' ' . $table[1], $dateRelative))
                            ->checkOut($hotel->getCheckInDate() ? strtotime("+" . $m['nights'] . "day", $hotel->getCheckInDate()) : null);
                    }
                }

                if (empty(trim($table[3])) && empty(trim($table[4]))) {
                    continue;
                }

                $sDate = $this->normalizeDate($table[0] . ' ' . $table[1], $dateRelative);
                $sName = $table[2];

                $s = $c->addSegment();
                $s
                    ->setName($sName);

                if (!empty($table[3])) {
                    $s
                        ->setAshore($sDate ? strtotime($table[3], $sDate) : null);
                }

                if (!empty($table[4])) {
                    $s
                        ->setAboard($sDate ? strtotime($table[4], $sDate) : null);
                }
            }
        }

        if ($containsHotel === true && $this->checkHotels($email) === false) {
            $email->add()->hotel();
        }

        return $email;
    }

    private function checkHotels(Email $email)
    {
        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'hotel') {
                return true;
            }
        }

        return false;
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

    private function normalizeDate($str, $relativeDate = null)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        // $this->logger->debug('$relativeDate = '.print_r( $relativeDate,true));
        $year = date("Y", $relativeDate);

        $in = [
            // Apr 19 2023 at 12:48 AM
            "/^\s*([[:alpha:]]+)\s+(\d+)\s+(\d{4})\s+at\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu",
            // Tue May 23
            "/^(\w+)\s+(\w+)\s*(\d+)\s*$/iu",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1, $3 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str 2 = '.print_r( $str,true));

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            if (empty($relativeDate)) {
                $str = null;
            } else {
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
                $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
            }
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        // $this->logger->debug($str = '.print_r( $str,true));

        return $str;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
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

    private function striposArray($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
