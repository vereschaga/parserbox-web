<?php

namespace AwardWallet\Engine\regiojet\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TicketPdf extends \TAccountChecker
{
    public $mailFiles = "regiojet/it-492320238.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            // 'Platform' => '',
            // 'Passenger' => '',
            'Booking #:'        => 'Booking #:',
            'Departure station' => 'Departure station',
            // 'Arrival station' => '',
            // 'The bus' => '',
            // 'The train' => '',
            // 'Valid for' => '',
            // 'Platform' => '',
        ],
    ];

    private $detectFrom = "@regiojet.cz";
    private $detectSubject = [
        // en
        'Your reservation RegioJet:',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]@regiojet\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'RegioJet') === false
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
        // detect provider
        if ($this->containsText($text, ['RegioJet']) === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Booking #:'])
                && $this->containsText($text, $dict['Booking #:']) === true
                && !empty($dict['Departure station'])
                && $this->containsText($text, $dict['Departure station']) === true
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
        // Segments
        $segments = $this->split("/(?:^|\n)( *{$this->opt($this->t('Departure station'))}\n)/", $textPdf);

        foreach ($segments as $sText) {
            $tableText = $this->re("/^(\s*[\s\S]+)\n *{$this->opt($this->t('Valid for'))}/", $sText);
            $table = $this->createTable($tableText, $this->columnPositions($this->inOneRow($tableText)), false);

            if (count($table) == 3) {
                $table[0] = $this->unionColumns($table[0], $table[1]);
                $table[1] = $table[2];
                unset($table[2]);
            }

            if (count($table) !== 2) {
                $this->logger->debug('segment error');
                $email->add()->flight();

                break;
            }

            $confNo = $this->re("/\n\s*{$this->opt($this->t('Booking #:'))} *(\d{5,})\s+/u", $table[0]);
            $traveller = $this->re("/\n\s*{$this->opt($this->t('Passenger'))} +.+\n *(.+?) {2}/u", $table[0]);

            $type = null;
            $depStation = $this->re("/{$this->opt($this->t('Departure station'))}\n((?:.*\n){1,4}) *{$this->opt($this->t('The bus'))}/", $table[1]);
            $arrStation = $this->re("/{$this->opt($this->t('Arrival station'))}\n((?:.*\n){1,4}) *{$this->opt($this->t('The bus'))}/", $table[1]);

            if (!empty($depStation) || !empty($arrStation)) {
                $type = 'bus';
            } else {
                $type = 'train';
            }
            $depStation = $depStation ?? $this->re("/{$this->opt($this->t('Departure station'))}\n((?:.*\n){1,4}) *{$this->opt($this->t('The train'))}/", $table[1]);
            $arrStation = $arrStation ?? $this->re("/{$this->opt($this->t('Arrival station'))}\n((?:.*\n){1,4}) *{$this->opt($this->t('The train'))}/", $table[1]);

            if (empty($type)) {
                $this->logger->debug('segment error');
                $email->add()->flight();

                break;
            } elseif ($type == 'train') {
                if (!isset($trains)) {
                    $trains = $email->add()->train();
                }

                if (!in_array($confNo, array_column($trains->getConfirmationNumbers(), 0))) {
                    $trains->general()
                        ->confirmation($confNo);
                }

                if (!in_array($traveller, array_column($trains->getTravellers(), 0))) {
                    $trains->general()
                        ->traveller($traveller);
                }
                $s = $trains->addSegment();
            } elseif ($type == 'bus') {
                if (!isset($buses)) {
                    $buses = $email->add()->bus();
                }

                if (!in_array($confNo, array_column($buses->getConfirmationNumbers(), 0))) {
                    $buses->general()
                        ->confirmation($confNo);
                }

                if (!in_array($traveller, array_column($buses->getTravellers(), 0))) {
                    $buses->general()
                        ->traveller($traveller);
                }
                $s = $buses->addSegment();
            }

            $dateRelative = strtotime($this->re("/\n *{$this->opt($this->t('Purchase date:'))} *(.+)/", $table[0]));
            $date = $this->re("/\n *{$this->opt($this->t('Date'))} +{$this->opt($this->t('Time'))}.*\n+(.+ +\d{1,2}:\d{2}.+?)(?: {2,}|\n)/", $table[0]);
            // Departure
            $s->departure()
                ->name(preg_replace('/\s*\n\s*/', ', ', $depStation))
                ->geoTip('europe')
                ->date($dateRelative ? $this->normalizeDateRelative($date, $dateRelative) : null)
            ;

            // Arrival
            $s->arrival()
                ->name(preg_replace('/\s*\n\s*/', ', ', $arrStation))
                ->geoTip('europe')
                ->noDate()
            ;

            // Extra
            $s->extra()
                ->noNumber();
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

    private function createTable(?string $text, $pos = [], $isTrim = true): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                if ($isTrim === true) {
                    $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                } else {
                    $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
                }
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

    private function normalizeDateRelative($date, $relativeDate)
    {
        if (empty($relativeDate)) {
            return null;
        }
        $year = date("Y", $relativeDate);
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // Sep 24      10:48
            '/^\s*(\d+)\s*([[:alpha:]]+)\s+(\d{1,2}:\d{2}(?: *[ap]m)?)$/iu',
        ];
        $out = [
            '$1 $2 ' . $year . ', $3',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
        // $this->logger->debug('$date = '.print_r( $date,true));
        $date = EmailDateHelper::parseDateRelative($date, $relativeDate, true);

        return $date;
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

    private function unionColumns($col1, $col2)
    {
        $col1Rows = explode("\n", $col1);
        $col2Rows = explode("\n", $col2);
        $newCol = '';

        for ($c = 0; $c < max(count($col1Rows), count($col2Rows)); $c++) {
            $newCol .= ($col1Rows[$c] ?? '') . ' ' . ($col2Rows[$c] ?? '') . "\n";
        }

        return $newCol;
    }
}
