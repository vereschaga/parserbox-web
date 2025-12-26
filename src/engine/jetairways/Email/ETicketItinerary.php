<?php

namespace AwardWallet\Engine\jetairways\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: airindia/Pdf(it-61405045.eml)

class ETicketItinerary extends \TAccountChecker
{
    public $mailFiles = "jetairways/it-2485078.eml, jetairways/it-28036605.eml, jetairways/it-3.eml, jetairways/it-51135421.eml, jetairways/it-8865320.eml, jetairways/it-8912214.eml";

    public $reFrom = ["jetairways.com"];
    public $reBody = [
        'en' => ['eTicket Itinerary', 'Itinerary Details'],
    ];
    public $reSubject = [
        'Jet Airways eTicket Itinerary / Receipt',
        'Jet Airways Web Booking eTicket',
    ];

    public $lang = '';
    public $pdfNamePattern = ".*eti.*pdf";
    public static $dict = [
        'en' => [
            'Booking Reference (PNR)' => ['Booking Reference (PNR)', 'Booking Reference:'],
            'reservations'            => ['eTicket Duplicate Itinerary / Receipt', 'eTicket Itinerary / Receipt'],
            'endDetails'              => ['Fare Details', 'Redemption Details'],
        ],
    ];

    private $code;
    private static $providers = [
        'jetairways' => [
            'from' => ['jetairways.com'],
            'subj' => [
                'Jet Airways eTicket Itinerary / Receipt',
                'Jet Airways Web Booking eTicket',
            ],
            'body' => [
                'Jet Airways',
                '//*[contains(.,\'jetairways.com\')]',
            ],
            'keyword' => [
                'Jet Airways',
            ],
        ],
        'airindia' => [
            'from' => ['@airindia.in'],
            'subj' => [
                'Air India Web Booking eTicket',
            ],
            'body' => [
                'Air India',
                '//*[contains(.,\'airindia.in\')]',
            ],
            'keyword' => [
                'Air India',
            ],
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $arr) {
            $byFrom = $bySubj = $bySubjKeyword = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            foreach ($arr['keyword'] as $keyword) {
                if (stripos($headers['subject'], $keyword) !== false) {
                    $bySubjKeyword = true;
                }
            }

            if (($byFrom || $bySubjKeyword) && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    }

                    foreach (self::$providers as $prov => $arr) {
                        if ($this->stripos($text, $arr['keyword'])) {
                            $this->code = $prov;
                        }
                    }

                    if (!$this->parseEmailPdf($text, $email)) {
                        return null;
                    }
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        if (empty($this->code)) {
            $code = $this->getProvider($parser);
        } else {
            $code = $this->code;
        }

        if (!empty($code)) {
            $email->setProviderCode($code);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach (self::$providers as $arr) {
                if ($this->stripos($text, $arr['keyword']) && $this->assignLang($text)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmailPdf($textPDF, Email $email): bool
    {
        $textPDF = "forCorrectSplit\n" . $textPDF;
        $reservations = $this->splitter("/({$this->opt($this->t('reservations'))})/", $textPDF);

        foreach ($reservations as $reservation) {
            if (!empty($str = strstr($reservation, 'Important Notes', true))) {
                $reservation = $str;
            }
            $r = $email->add()->flight();

            $r->general()
                ->confirmation($this->re("/{$this->opt($this->t('Booking Reference (PNR)'))}\s+([A-Z\d]{5,})/",
                    $reservation))
                ->date($this->normalizeDate($this->re("/{$this->opt($this->t('Date of issue'))}\s*:\s*(.+)/i",
                    $reservation)));

            $paxText = $this->re("/( *{$this->opt($this->t('Passenger Name'))}.+?)\n *Date/s", $reservation);
            $table = $this->splitCols($paxText, $this->colsPos($paxText, 10));

            if (count($table) === 5 && strpos($table[3], 'eTicket #') !== false) {
                $table[3] = $this->mergeCols($table[3], $table[4]);
                unset($table[4]);
            }

            if (count($table) !== 3 && count($table) !== 4) {
                $this->logger->debug('other format paxTable');

                return false;
            }
            $pax = array_unique(array_filter(explode("\n",
                trim($this->re("/{$this->opt($this->t('Passenger Name'))}\s+(.+)/s", $table[0])))));
            $r->general()
                ->travellers($pax);

            if (count($table) === 3) {
                $accountStr = $table[1];
                $ticketStr = $table[2];
            } else {
                $accountStr = $table[2];
                $ticketStr = $table[3];
            }
            $accounts = array_unique(array_filter(explode("\n",
                trim($this->re("/{$this->opt($this->t('Frequent Flyer #'))}\s+(.+)/s", $accountStr)))));

            if (count($accounts) > 0) {
                $r->program()
                    ->accounts($accounts, false);
            }
            $tickets = array_unique(array_filter(explode("\n",
                trim($this->re("/{$this->opt($this->t('eTicket #'))}\s+(.+)/s", $ticketStr)))));
            $r->issued()
                ->tickets($tickets, false);

//          $itText = $this->re("/{$this->opt($this->t('Date'))} +{$this->opt($this->t('Dep Time'))}[^\n]+\n(.+?){$this->opt($this->t('Detailed Itinerary'))}/s", $reservation);
            $itExtText = $this->re("/{$this->opt($this->t('Detailed Itinerary'))}[^\n]*\n(.+?)\s+{$this->opt($this->t('endDetails'))}/s",
                $reservation);

            $segments = $this->splitter("/(.+[ ]{3,}(?:Economy|Première|business|business class|first class|[A-Z][ ]{2,}))/iu",
                $itExtText);

            foreach ($segments as $i => $segment) {
                $s = $r->addSegment();

                $text = $this->re("/(.+\n *(?:[A-Z\d][A-Z]|[A-Z][A-Z\d]) +\d+ {3,}(?:.+(?:\d+:\d+\s+hrs|\d{1,2} \w+ \d{2,4})[ ]*\n[ ]+\d+:\d+ hrs|[^\n]+))/s",
                    $segment);

                if (empty($text)) {
                    $text = $this->re("/(.+\n *\d+:\d+ hrs {3,}\d+:\d+ hrs\b[^\n]*)/s", $segment);
                }

                $table = $this->splitCols($text, $this->colsPos($text));

                if (count($table) < 4) {
                    $this->logger->debug("other format {$i}-segment");

                    return false;
                }

                if (preg_match("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s+(\d+)$/", trim($table[0]), $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }

                $re = '/(.+)\s*\(([A-Z]{3})\)(?:[A-Z][A-Z\d]{5,9}\/?)?(?:\s+(.+)\s+(\d+:\d+)\s*hrs)?$/';

                if (preg_match($re, trim($table[1]), $m)) {
                    $s->departure()
                        ->name($m[1])
                        ->code($m[2]);

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->departure()
                            ->date(strtotime($m[3] . ' ' . $m[4]));
                    }
                }

                if (preg_match($re, trim($table[2]), $m)) {
                    $s->arrival()
                        ->name($m[1])
                        ->code($m[2]);

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->arrival()
                            ->date(strtotime($m[3] . ' ' . $m[4]));
                    } else {
                        $s->arrival()->noDate();
                    }
                }

                if (preg_match("/(.+)\s+\(([A-Z]{1,2})\)/", trim($table[3]), $m)) {
                    $s->extra()
                        ->cabin($m[1])
                        ->bookingCode($m[2]);
                    $s->extra()->status($this->re("/{$m[1]}.+? ([A-z]{4,}) +(?:\d+h \d+m)? +(?:[A-Z]\w+ +)?\d+\w+ *\n/",
                        $text));
                }

                if (preg_match("/ +(\d+) stops/", $text, $m)) {
                    $s->extra()->stops($m[1]);
                }

                if (preg_match("/ (\d+h \d+m) /", $text, $m)) {
                    $s->extra()->duration($m[1]);
                }

                if (preg_match('/Operated by\s*(.+?)\s*(?:-|$)/', $segment, $m)) {
                    $s->airline()->operator(preg_replace('/\s*Operated by\s*/i', '', $m[1]));
                }

                if (preg_match('/Departure\s*:\s*TERMINAL\s+(.+?)\s*(?:\/|$)/i', $segment, $m)) {
                    $s->departure()->terminal($m[1]);
                }

                if (preg_match('/Arrival\s*:\s*TERMINAL\s+(.+?)\s*$/i', $segment, $m)) {
                    $s->arrival()->terminal($m[1]);
                }
            }

            $totalPrice = $this->getTotalCurrency($this->re('/^[ ]*TOTAL(?: TRIP COST)?\s+([A-Z]{3}[ ]*\d[,.\'\d ]*?)(?: \D|[ ]{2}|$)/m', $reservation));

            if ($totalPrice['Total'] !== null) {
                $r->price()->total($totalPrice['Total']);

                if (!empty($totalPrice['Currency'])) {
                    $r->price()->currency($totalPrice['Currency']);
                }
            }

            $fare = $this->getTotalCurrency($this->re('/^[ ]*FARE\s+([A-Z]{3}[ ]*\d[,.\'\d ]*?)(?: \D|[ ]{2}|$)/m', $reservation));

            if ($fare['Total'] !== null
                && ($fare['Currency'] === $totalPrice['Currency'] || empty($fare['Currency']) || empty($totalPrice['Currency']))
            ) {
                $r->price()->cost($fare['Total']);

                if (empty($totalPrice['Currency']) && !empty($fare['Currency'])) {
                    $r->price()->currency($fare['Currency']);
                }
            }

            $totalMiles = $this->getTotalCurrency($this->re('/^[ ]*Total JPMiles[ ]+(\d[,.\'\d ]*?)(?: \D|[ ]{2}|$)/m', $reservation));
            $r->price()->spentAwards($totalMiles['Total'], false, true);
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Wednesday, 11 Dec, 2013
            '#^(\w+),\s+(\d+)\s+(\w+),\s+(\d{4})$#u',
        ];
        $out = [
            '$2 $3 $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node): array
    {
        $tot = null;
        $cur = null;

        if (preg_match("/^(?<c>[A-Z]{3})?\s*(?<t>\d[,.\'\d\s]*)$/", $node, $m)
            || preg_match("/^(?<t>\d[,.\'\d\s]*)\s*(?<c>[A-Z]{3})?$/", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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

    private function rowColsPos($row)
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

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function mergeCols($col1, $col2)
    {
        $rows1 = explode("\n", $col1);
        $rows2 = explode("\n", $col2);
        $newRows = [];

        foreach ($rows1 as $i => $row) {
            if (isset($rows2[$i])) {
                $newRows[] = $row . $rows2[$i];
            } else {
                $newRows[] = $row;
            }
        }

        if (($i = count($rows1)) > count($rows2)) {
            for ($j = $i; $j < count($rows2); $j++) {
                $newRows[] = $rows2[$j];
            }
        }

        return implode("\n", $newRows);
    }

    private function stripos($haystack, $arrayNeedle)
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
