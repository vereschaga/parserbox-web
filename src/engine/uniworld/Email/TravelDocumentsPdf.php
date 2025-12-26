<?php

namespace AwardWallet\Engine\uniworld\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TravelDocumentsPdf extends \TAccountChecker
{
    public $mailFiles = "uniworld/it-865880937.eml, uniworld/it-865933111.eml";

    public $dateFormat = null;
    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Cruise Ticket'       => 'Cruise Ticket',
            'Disembarkation Port' => 'Disembarkation Port',
            'hotelTitles'         => ['Included Land', 'Pre-Cruise Extension', 'Extra Nights', 'Post-Cruise Extension'],
        ],
    ];

    private $detectFrom = "noreply@uniworld.com";
    private $detectSubject = [
        // en
        'Uniworld - Travel Documents for ',
    ];
    private $detectBody = [
        'en' => [
            'Cruise Ticket and',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]uniworld\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Uniworld - ') === false
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
        if ($this->containsText($text, ['www.uniworld.com', 'Uniworld ']) === false) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Cruise Ticket'])
                && $this->containsText($text, $dict['Cruise Ticket']) === true
                && !empty($dict['Disembarkation Port'])
                && $this->containsText($text, $dict['Disembarkation Port']) === true
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
        $tickets = $this->split("/\n( {0,10}{$this->opt($this->t('Cruise Ticket'))}(?:.*\n){1,3} *{$this->opt($this->t('Booking#'))})/", "\n\n" . $textPdf);

        foreach ($tickets as $tText) {
            $conf = $this->re("/\n *{$this->opt($this->t('Booking#'))} +(\d{5,})(?: {3,}|\n)/", $tText);

            $tableText = $this->re("/\n( *{$this->opt($this->t('Booking#'))}[\s\S]+?\n *{$this->opt($this->t('Itinerary'))} {3,}{$this->opt($this->t('Date(s)'))})\n/",
                $tText);
            $tableBooking = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));

            $description = $this->re("/^\s*(\S.+?)\s*\n\s*{$this->opt($this->t('Itinerary'))}\s*$/s", $tableBooking[2] ?? '');

            $traveller = $this->re("/\n {0,10}(\S(?: ?\S)+?) {3,}.*\n+ {0,10}{$this->opt($this->t('Passenger'))} {3,}{$this->opt($this->t('Client(s) of'))}/",
                $tText);

            unset($cruise);

            if (!empty($conf) && !empty($description)) {
                foreach ($email->getItineraries() as $it) {
                    if ($it->getType() === 'cruise' && in_array($conf, array_column($it->getConfirmationNumbers(), 0))
                        && $it->getDescription() === $description
                    ) {
                        $cruise = $it;
                        $cruise->general()
                            ->traveller($traveller);
                    }
                }
            }

            if (!isset($cruise)) {
                $cruise = $email->add()->cruise();

                $cruise->general()
                    ->confirmation($conf);

                // General
                $cruise->general()
                    ->traveller($traveller);

                // Details
                $cruise->details()
                    ->description($description);

                $tableText = $this->re("/\n {0,10}{$this->opt($this->t('Passenger'))} {3,}{$this->opt($this->t('Client(s) of'))}\n+([\s\S]+ *{$this->opt($this->t('Ship'))} {3,}.* {3,}{$this->opt($this->t('Stateroom'))}.*)\n/",
                    $tText);
                $tableShip = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));

                $cruise->details()
                    ->ship($this->re("/^\s*(.+?)\s*\n\s*{$this->opt($this->t('Ship'))}\s*$/s", $tableShip[0] ?? ''))
                    ->room($this->re("/^\s*(.+?)\s*\n\s*{$this->opt($this->t('Stateroom'))}\s*$/s",
                        $tableShip[2] ?? ''))
                    ->roomClass($this->re("/^\s*(.+?)\s*\n\s*{$this->opt($this->t('Category'))}\s*$/s",
                        $tableShip[3] ?? ''));

                $startDate = $this->re("/ {2,}{$this->opt($this->t('Embarkation Date'))} +([\d\/]{5,})\n/", $tText);
                $endDate = $this->re("/ {2,}{$this->opt($this->t('Disembarkation Date'))} +([\d\/]{5,})\n/", $tText);

                // detectF date format
                if (empty($this->dateFormat) && preg_match("/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})$/", $startDate, $m)
                    && ($m[1] > 12 || $m[2] > 12)
                ) {
                    if ($m[1] > 12) {
                        $this->dateFormat = 'dmy';
                    } elseif ($m[2] > 12) {
                        $this->dateFormat = 'mdy';
                    }
                }

                if (empty($this->dateFormat) && preg_match("/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})$/", $endDate, $m)
                    && ($m[1] > 12 || $m[2] > 12)
                ) {
                    if ($m[1] > 12) {
                        $this->dateFormat = 'dmy';
                    } elseif ($m[2] > 12) {
                        $this->dateFormat = 'mdy';
                    }
                }

                if (empty($this->dateFormat)
                    && preg_match("/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})$/", $startDate, $m1)
                    && preg_match("/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})$/", $endDate, $m2)
                ) {
                    $ds1 = strtotime($m1[1] . '.' . $m1[2] . '.' . $m1[3]);
                    $ds2 = strtotime($m1[2] . '.' . $m1[1] . '.' . $m1[3]);
                    $de1 = strtotime($m2[1] . '.' . $m2[2] . '.' . $m2[3]);
                    $de2 = strtotime($m2[2] . '.' . $m2[1] . '.' . $m2[3]);

                    if ($de1 > $ds1 && $de2 < $ds2) {
                        $this->dateFormat = 'dmy';
                    } elseif ($de2 > $ds2 && $de1 < $ds1) {
                        $this->dateFormat = 'mdy';
                    }

                    if (empty($this->dateFormat)) {
                        $tourStartDate = $this->normalizeDate($this->re("/^\s*(\S.+?) - \S.+\s*\n\s*{$this->opt($this->t('Date(s)'))}\s*$/s",
                            $tableBooking[3] ?? ''));

                        if (!empty($tourStartDate) && $ds1 !== $ds2 && abs($ds1 - $ds2) > 60 * 60 * 24 * 5) {
                            for ($i = $tourStartDate; $i < $tourStartDate + 60 * 60 * 24 * 5; $i += 60 * 60 * 24) {
                                if ($i === $ds1) {
                                    $this->dateFormat = 'dmy';

                                    break;
                                }

                                if ($i === $ds2) {
                                    $this->dateFormat = 'mdy';

                                    break;
                                }
                            }
                        }
                    }
                }

                $s = $cruise->addSegment();
                $s->setAboard($this->normalizeDate($startDate));

                if (preg_match("/\n {0,10}{$this->opt($this->t('Embarkation Port'))} +(.+) {2,}{$this->opt($this->t('Embarkation Date'))}.+\n\s*{$this->opt($this->t('Pier'))} +((.+\n+){1,3}) *{$this->opt($this->t('Address'))}/", $tText, $m)) {
                    $port = trim($m[1]);

                    if (stripos($m[2], 'www.uniworld.') === false) {
                        $port = $port . ', ' . preg_replace('/\s+/', ' ', trim($m[2]));
                    }
                    $s->setName($port);
                }

                $s = $cruise->addSegment();
                $s->setAshore($this->normalizeDate($endDate));

                if (preg_match("/\n {0,10}{$this->opt($this->t('Disembarkation Port'))} +(.+) {2,}{$this->opt($this->t('Disembarkation Date'))}.+\n\s*{$this->opt($this->t('Pier'))} +((?:.+\n+){1,3}) *{$this->opt($this->t('Address'))}/",
                    $tText, $m)) {
                    $port = trim($m[1]);

                    if (stripos($m[2], 'www.uniworld.') === false) {
                        $port = $port . ', ' . preg_replace('/\s+/', ' ', trim($m[2]));
                    }
                    $s->setName($port);
                }
            }

            $hotelsText = $this->re("/\n *{$this->opt($this->t('hotelTitles'))}\n([\s\S]+)\n *.* +{$this->opt($this->t('Dates'))} {2,}{$this->opt($this->t('Hotel Name'))} {2,}/", $tText);
            $hotelsText = preg_replace("/^ *{$this->opt($this->t('hotelTitles'))}\s*$/mu", '', $hotelsText);
            $hotelsText = preg_replace("/^ *.* +{$this->opt($this->t('Dates'))} {2,}{$this->opt($this->t('Hotel Name'))} {2,}.+\s*$/um", '', $hotelsText);

            $hotelSegmentsText = array_filter($this->split("/^( {0,10}\S+)/m", (!empty($hotelsText) ? "\n\n" . $hotelsText : '')));

            foreach ($hotelSegmentsText as $hText) {
                $table = $this->createTable($hText, $this->rowColumnPositions($this->inOneRow($hText)));

                $h = $email->add()->hotel();

                $h->general()
                    ->noConfirmation()
                    ->traveller($traveller)
                ;

                if (empty(trim($table[4] ?? '')) && preg_match("/^ *(\S.+) ([\d\W ]{6})(?:\n[\s\S]*)$/", $table[3] ?? '', $m)) {
                    $table[3] = $m[1] . ($m[3] ?? '');
                    $table[4] = $m[2];
                }

                $h->hotel()
                    ->name(trim(preg_replace("/\s+/", ' ', $table[2] ?? '')))
                    ->address(trim(preg_replace("/\s+/", ' ', $table[3] ?? '')))
                    ->phone(trim(preg_replace("/\s+/", ' ', $table[4] ?? '')))
                ;

                $h->booked()
                    ->checkIn($this->normalizeDate($this->re("/^\s*(.+) - .+\s*$/s", $table[1] ?? '')))
                    ->checkOut($this->normalizeDate($this->re("/^\s*.+ - (.+)\s*$/s", $table[1] ?? '')));

                foreach ($email->getItineraries() as $it) {
                    if ($it->getId() !== $h->getId() && $it->getType() === 'hotel') {
                        if (serialize(array_diff_key($it->toArray(),
                                ['travellers' => []])) === serialize(array_diff_key($h->toArray(), ['travellers' => []]))) {
                            $it->general()
                                ->traveller($traveller, true);

                            $email->removeItinerary($h);

                            break;
                        }
                    }
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
                if (mb_strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && mb_strpos($text, $needle) !== false) {
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        if (stripos($date, '/') !== false) {
            $date = trim($date);

            if (preg_match("/^\s*(\d{1,2})\/(\d{1,2})\/(\d{2,4})\s*$/", $date, $m)
                && !empty($this->dateFormat)
            ) {
                if (strlen($m[3]) == 2) {
                    $m[3] = '20' . $m[3];
                }

                if ($this->dateFormat === 'dmy') {
                    return strtotime($m[1] . '.' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '.' . $m[3]);
                } elseif ($this->dateFormat === 'mdy') {
                    return strtotime($m[2] . '.' . str_pad($m[1], 2, '0', STR_PAD_LEFT) . '.' . $m[3]);
                }

                return null;
            }
        } else {
            $in = [
                // Mar 29, 2025
                // '/^\s*([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})\s*$/iu',
            ];
            $out = [
                // '$2 $1 $3',
            ];

            $date = preg_replace($in, $out, $date);

            if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $date, $m)) {
                if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                    $date = str_replace($m[1], $en, $date);
                }
            }
            // $this->logger->debug('date replace = ' . print_r( $date, true));

            // $this->logger->debug('date end = ' . print_r($date, true));

            return strtotime($date);
        }

        return null;
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
}
