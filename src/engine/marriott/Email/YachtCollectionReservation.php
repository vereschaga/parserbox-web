<?php

namespace AwardWallet\Engine\marriott\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YachtCollectionReservation extends \TAccountChecker
{
    public $mailFiles = "";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Voyage Name:'       => 'Voyage Name:',
            'Voyage Itinerary -' => 'Voyage Itinerary -',
        ],
    ];

    private $detectFrom = "@ritz-carltonyachtcollection.com";
    private $detectSubject = [
        // en
        'The Ritz-Carlton Yacht Collection - Your Guests\' Reservation is Confirmed - Res',
    ];
    private $detectBody = [
        //        'en' => [
        //            '',
        //        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && stripos($headers["subject"], 'The Ritz-Carlton Yacht Collection') === false
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
        if ($this->containsText($text, ['The Ritz-Carlton Yacht Collection', '@ritz-carltonyachtcollection.com']) === false) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Voyage Name:'])
                && $this->containsText($text, $dict['Voyage Name:']) === true
                && !empty($dict['Voyage Itinerary -'])
                && $this->containsText($text, $dict['Voyage Itinerary -']) === true
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

    private function parseEmailPdf(Email $email, string $text)
    {
        $c = $email->add()->cruise();

        // General
        $conf = $this->re("/Booking No\. *\\/ *[^:]+?:[ ]*(\d{5,}) *\\//", $text);

        foreach ($email->getItineraries() as $it) {
            if (in_array($conf, array_column($it->getConfirmationNumbers(), 0))) {
                $email->removeItinerary($c);

                return false;
            }
        }
        $c->general()
            ->confirmation($conf, "Booking No.");

        $travellersText = $this->re("/\n( *(?:Guests|Guest) {2,}Voyage Code {2,}.+\n[\s\S]+?)(?:\n\s*\n\s*|\n *Other Charges)/", $text);

        $travellersTablePositions = $this->rowColumnPositions($this->inOneRow($travellersText));
        $rows = $this->split("/\n( *[A-Z\W]+ {2,}\d{5,} {2,})/", $travellersText);
        $travellersTable = [];

        foreach ($rows as $row) {
            $travellersTable[] = preg_replace("/\s+/", ' ', array_map('trim', $this->createTable($row, $travellersTablePositions)));
        }

        $c->general()
            ->travellers(array_filter(preg_replace('/^\s*(ADULT|TEENAGER|CHILD)\s*$/i', '',
                array_unique(array_column($travellersTable, 0)))), true);

        // Program
        $accounts = array_unique(array_filter(array_column($travellersTable, 5)));

        if (!empty($accounts)) {
            $c->program()
                ->accounts($accounts, false);
        }
        // Price
        $currency = $this->re("/ {2,}Currency:?[ ]*.+?\(([A-Z]{3})\)\n/", $text);
        $payments = PriceHelper::parse($this->re("/\n +(?:Total )?Payments[ ]+[^\d\n]{0,7}(\d[\d\,\. ]*?)[^\d\n]{0,7}\n/", $text), $currency);
        $balanceDue = PriceHelper::parse($this->re("/\n +Balance Due[ ]+[^\d\n]{0,7}(\d[\d\,\. ]*?)[^\d\n]{0,7}\n/", $text), $currency);
        $c->price()
            ->total($payments + $balanceDue)
            ->currency($this->re("/ {2,}Currency:?[ ]*.+?\(([A-Z]{3})\)\n/", $text));
        $cost = 0.0;

        foreach (array_column($travellersTable, 6) as $row) {
            $cost += PriceHelper::parse($this->re("/^\s*[^\d\n]{0,7}\s*(\d[\d\,\. ]*?)\s*[^\d\n]{0,7}\s*$/", $row), $currency);
        }

        if (!empty($cost)) {
            $c->price()
                ->cost($cost);
        }

        if (preg_match_all("/\n {0,5}Marriott Bonvoy Points.*\n {0,5}Redeemed - (\d[\d, .]*) \[[A-Z]{3}\] -/", $text, $m)) {
            $points = array_sum(preg_replace('/\D+/', '', $m[1]));
            $c->price()
                ->spentAwards($points);
        }

        // Details
        $c->details()
            ->number($this->re("/\n *Voyage Code:[ ]*(\d{5,})\s+/", $text))
            ->description($this->re("/\n *Voyage Name:[ ]*(\S.+?)(?: {2,}|\n)/", $text))
            ->roomClass(implode(", ", array_unique(array_column($travellersTable, 3))))
            ->room(implode(", ", array_unique(array_column($travellersTable, 2))), true, true)
            ->ship($this->re("/\n *Yacht:[ ]*(\S.+?)(?: {2,}|\n)/", $text));

        if (preg_match("/\n([ ]*Date[ ]+Day[ ]+Port[ ]+Arrival[ ]+Departure.*)\s*\n((?:[ ]*[A-Z][a-z]{2,3} \d{1,2}[ ]{2,}.+(\n[ ]{30}.*)?\n+)+)/", $text, $m)) {
            $year = $this->re("/ {2}Embarkation: +\d{1,2}-[[:alpha:]]+-(\d{2}) {2,}/", $text);

            if (!empty($year)) {
                $year = '20' . $year;
            }
            $headPos = $this->rowColumnPositions($this->inOneRow($m[1]));

            $rows = $this->split("/\n([ ]*[A-Z][a-z]{2,3} \d{1,2}[ ]{2,})/", "\n\n" . $m[2]);

            foreach ($rows as $row) {
                $table = preg_replace("/\s+/", ' ', array_map('trim', $this->createTable($row, $headPos)));

                if (empty(trim($table[3])) && empty(trim($table[4]))) {
                    continue;
                }

                $name = $table[2];

                if (isset($seg) && $seg->getName() === $name && !$seg->getAboard() && $seg->getAshore()) {
                } else {
                    $seg = $c->addSegment();
                }
                $seg->setName($table[2]);

                if (!empty(trim($table[3]))) {
                    $seg->setAshore($this->normalizeDateWeek($table[0] . ' ' . $year . ', ' . $table[3], $table[1]));
                }

                if (!empty(trim($table[4]))) {
                    $seg->setAboard($this->normalizeDateWeek($table[0] . ' ' . $year . ', ' . $table[4], $table[1]));
                }
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

    private function normalizeDateWeek(?string $date, $week): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            //            // Jul 03, 2018 at 1 :43 PM
            '/^\s*([[:alpha:]]+)\s+(\d+)\s+(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
        $date = EmailDateHelper::parseDateUsingWeekDay($date, $weeknum);

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
}
