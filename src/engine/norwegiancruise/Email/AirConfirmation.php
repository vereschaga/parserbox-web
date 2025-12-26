<?php

namespace AwardWallet\Engine\norwegiancruise\Email;

use AwardWallet\Schema\Parser\Email\Email;

class AirConfirmation extends \TAccountChecker
{
    public $mailFiles = "norwegiancruise/it-261040374.eml, norwegiancruise/it-268917991.eml, norwegiancruise/it-473673768.eml, norwegiancruise/it-558127543.eml, norwegiancruise/it-848839918.eml, norwegiancruise/it-856313915.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Reservation #:'         => ['Reservation Number:', 'Reservation #:'],
            'Passenger Information:' => ['Passenger Information:', 'New Flight Information:'],
            'Conf #'                 => ['Conf #', 'Ref Code'],
        ],
    ];

    private $detectSubject = [
        // en
        'Air Confirmation for Reservation',
    ];

    private $detectProvider = [
        'en' => [
            'NCL Air Flight Confirmation',
            'https://edocs.ncl.com/edocs',
            'Your Norwegian Cruise Line Team',
        ],
    ];
    private $detectBody = [
        'en' => [
            'NCL Air Flight Confirmation',
            'Flight Schedule Change Notification',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'donotreply@ncl.com') !== false;
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

    public function detectPdf($text)
    {
        $detectedProvider = false;

        foreach ($this->detectProvider as $lang => $detectProvider) {
            if ($this->containsText($text, $detectProvider) !== false) {
                $detectedProvider = true;

                break;
            }
        }

        if ($detectedProvider !== true) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->containsText($text, $detectBody) !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/\n *{$this->opt($this->t('Reservation #:'))} +([\dA-Z]{5,})\s{2,}/", $textPdf),
                trim($this->re("/\n *({$this->opt($this->t('Reservation #:'))}) +[\dA-Z]{5,}\s{2,}/", $textPdf), ':'));

        $confs = [];
        $confTitle = null;

        if (preg_match_all("/\n *({$this->opt($this->t('PNR Record Locator:'))}) *([A-Z\d]{5,7})/", $textPdf, $m)) {
            $confTitle = array_shift($m[1]);
            $confs = array_unique($m[2]);
        }

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf, trim($confTitle, ':'));
        }

        $travellerText = implode("\n", $this->res("/{$this->opt($this->t('Passenger Information:'))} +.+\n([\s\S]+?)\n\n/", $textPdf));

        if (preg_match_all("/^ {0,5}(?<names>[A-Z](?: ?[A-Z\W])+)(?: {2}.+?)?(?<tickets> {2,}\d[\d\- ,]{10,})?$/m", $travellerText, $m)) {
            $m['names'] = preg_replace("/^\s*(.+?)\s*,\s*(.+?)\s*$/", '$2 $1', $m['names']);
            $f->general()
                ->travellers(array_unique($m['names']), true);

            foreach ($m[0] as $k => $v) {
                $tickets = array_filter(preg_split("/\s*,\s*/", trim($m['tickets'][$k] ?? '')));

                foreach ($tickets as $ticket) {
                    $f->issued()
                        ->ticket($ticket, false, $m['names'][$k]);
                }
            }
        } else {
            $f->general()
                ->travellers([]);
        }

        $segments = [];

        if (preg_match_all("/\n {0,5}{$this->opt($this->t('Conf #'))} {2,}Airline +.+\s*((?:\n+ {0,5}[A-Z\d]{5,7} {2,}.*|\n+ {12,}.*)+)\n\n/", $textPdf, $m)) {
            foreach ($m[1] as $v) {
                $segments = array_merge($segments,
                    $this->split("/\n {0,5}([A-Z\d]{5,7} {2,})/", "\n\n" . $v));
            }
        }
        $segments = array_unique(array_filter($segments));

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            $table = $this->createTable($sText, $this->rowColumnPositions($this->inOneRow($sText)));

            if (count($table) !== 9) {
                continue;
            }

            $table = array_map('trim', preg_replace("/\s+/", ' ', $table));

            $s->airline()
                ->name($this->re("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])$/", $table[1]))
                ->number($this->re("/^(\d+)$/", $table[2]))
                ->confirmation($this->re("/^([A-Z\d]{5,7})$/", $table[0]))
            ;

            if ($table[4] !== $table[1]) {
                $s->airline()
                    ->operator($table[4]);
            }

            // Departure
            $s->departure()
                ->name($this->re("/^(.+?)\s*\( *[A-Z]{3} *\)$/", $table[5]))
                ->code($this->re("/^.+?\s*\( *([A-Z]{3}) *\)$/", $table[5]))
                ->date($this->normalizeDate($table[6]))
            ;

            // Arrival
            $s->arrival()
                ->name($this->re("/^(.+?)\s*\( *[A-Z]{3} *\)$/", $table[7]))
                ->code($this->re("/^.+?\s*\( *([A-Z]{3}) *\)$/", $table[7]))
                ->date($this->normalizeDate($table[8]))
            ;

            // Extra
            $s->extra()
                ->cabin($table[3]);
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

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

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

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            //            // 01/14/23 6:00am
            '/^\s*(\d{2})\\/(\d{2})\\/(\d{2})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu',
        ];
        $out = [
            '$2.$1.20$3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date end = ' . print_r( $date, true));

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
}
