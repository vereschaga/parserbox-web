<?php

namespace AwardWallet\Engine\hiltongvc\Email;

// TODO: delete what not use
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Location:'       => 'Location:',
            'Confirmation #:' => 'Confirmation #:',
        ],
    ];

    private $detectFrom = "ContactUs@hgv.com";
    private $detectSubject = [
        // en
        'Reservation Confirmation for',
    ];
    private $detectBody = [
        'en' => [
            'please find confirmation of your accommodations',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->containsText($text, ['808-886-5900.']) === false
            && $this->http->XPath->query("//text()[{$this->contains('Hilton Grand Vacations')}]")->length == 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Location:'])
                && $this->containsText($text, $dict['Location:']) === true
                && !empty($dict['Confirmation #:'])
                && $this->containsText($text, $dict['Confirmation #:']) === true
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
        $tableText = $this->re("/\n\s*((?:{$this->opt($this->t('Location:'))}|{$this->opt($this->t('Arrival Date:'))})[\s\S]+?{$this->opt($this->t('No. of Guests:'))}.*\s)/", $textPdf);

        $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));
        $table[0] = $table[0] ?? '';
        $table[1] = $table[1] ?? '';
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->re("/{$this->opt($this->t('Confirmation #:'))} *(.+)\n/", $table[0]))
            ->traveller($this->re("/\n *{$this->opt($this->t('Dear'))} (\S.+?) *:\n/", $textPdf))
        ;

        // Hotel
        if (preg_match("/{$this->opt($this->t('Location:'))}\s+(?<name>.+)\n(?<address>[\s\S]+?)\n *(?:{$this->opt($this->t('Phone:'))}|\n\n)/", $table[0], $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address($m['address'])
            ;
        }
        $h->hotel()
            ->phone($this->re("/{$this->opt($this->t('Phone:'))}(.+)/", $table[0]));

        // Booked
        $ciDate = $this->normalizeDate($this->re("/{$this->opt($this->t('Arrival Date:'))}(.+)/", $table[1]));
        $ciTime = $this->re("/{$this->opt($this->t('Check-In Time:'))} *(\d{1,2}:\d{2}(?: *[apAP][mM])?) *\n/", $table[1]);

        if (!empty($ciDate) && !empty($ciTime)) {
            $h->booked()
                ->checkIn(strtotime($ciTime, $ciDate))
            ;
        }
        $coDate = $this->normalizeDate($this->re("/{$this->opt($this->t('Departure Date:'))}(.+)/", $table[1]));
        $coTime = $this->re("/{$this->opt($this->t('Check-Out Time:'))} *(\d{1,2}:\d{2}(?: *[apAP][mM])?) *\n/", $table[1]);

        if (!empty($coDate) && !empty($coTime)) {
            $h->booked()
                ->checkOut(strtotime($coTime, $coDate))
            ;
        }

        $h->booked()
            ->guests($this->re("/{$this->opt($this->t('No. of Guests:'))} *(\d+) *Adult/i", $table[1]))
            ->kids($this->re("/{$this->opt($this->t('No. of Guests:'))} *.*\b(\d+) *Child/i", $table[1]), true, true)
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->re("/{$this->opt($this->t('Unit Type:'))}(.+)/", $table[1]))
            ->setRate($this->re("/{$this->opt($this->t('Nightly Rates:'))}(.+)/", $table[1]))
        ;

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

    private function normalizeDate(?string $date)
    {
//        $this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            // 15-Sep-2023
            '/^\s*(\d+)\s*-\s*([[:alpha:]]+)\s*-\s*(\d{4})\s*$/ui',
        ];
        $out = [
            '$1 $2 $3',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

//        $this->logger->debug('date end = ' . print_r($date, true));

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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }
}
