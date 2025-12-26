<?php

namespace AwardWallet\Engine\hengine\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $pdfNamePattern = ".*\.pdf";

    public $price = [
        'totals'   => [],
        'taxes'    => [],
        'costs'    => [],
        'currency' => null,
    ];
    public $lang;
    public static $dictionary = [
        'en' => [
            'HOTEL INFORMATION'  => 'HOTEL INFORMATION',
            'BOOKER INFORMATION' => 'BOOKER INFORMATION',
            'Guest Names'        => ['Guest Names', 'Guest Name'],
        ],
    ];

    private $detectFrom = "hotelengine.com";
    private $detectSubject = [
        // en
        //        ''
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]hotelengine\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'www.hotelengine.com') === false
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
        if ($this->containsText($text, ['.engine.com/', '@engine.com']) === false) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['HOTEL INFORMATION'])
                && $this->containsText($text, $dict['HOTEL INFORMATION']) === true
                && !empty($dict['BOOKER INFORMATION'])
                && $this->containsText($text, $dict['BOOKER INFORMATION']) === true
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

        if ($this->price === null) {
            $email->price()
                ->total(null);
        } else {
            $email->price()
                ->total(array_sum($this->price['totals']))
                ->cost(array_sum($this->price['costs']))
                ->tax(array_sum($this->price['taxes']))
                ->currency($this->price['currency'])
            ;
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
        $hotelsText = $this->split("/\n( *{$this->opt($this->t('HOTEL INFORMATION'))} {3,}{$this->opt($this->t('BOOKER INFORMATION'))}\n)/", $textPdf);

        foreach ($hotelsText as $htext) {
            $tableText = $this->re("/^([\s\S]+?)\n {0,10}{$this->opt($this->t('Guest Names'))}/", $htext);
            $tableInfo = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));

            $hotelName = $hotelAddress = $hotelPhone = null;

            if (count($tableInfo) === 2 && preg_match("/^.+\n(?<name>.+)\n(?<address>.+)\n(?<phone>[\d\W]{5,})(?:\s*$|\n *{$this->opt($this->t('ADDITIONAL INFORMATION'))})/", $tableInfo[0], $m)) {
                $hotelName = $m['name'];
                $hotelAddress = $m['address'];
                $hotelPhone = $m['phone'];
            }

            $roomsText = $this->re("/\n {0,10}{$this->opt($this->t('Guest Names'))} {2,}.+ {2,}{$this->opt($this->t('Start Date'))} +.+\n([\s\S]+?)\n {30,}{$this->opt($this->t('SUBTOTAL:'))}/", $htext);

            $roomsRows = $this->split("/\n( {0,5}\S.+ {3,}\S.*\n)/", "\n\n" . $roomsText);

            foreach ($roomsRows as $row) {
                $pos = $this->rowColumnPositions($this->inOneRow($row));
                unset($pos[count($pos) - 1]);
                $table = $this->createTable($row, $pos);

                if (count($pos) !== 7 && preg_match("/^\s*\d+\/\d+\/\d+\s*$/", $table[3] ?? '')
                    && preg_match("/^.+ [A-Z\d\-]{5,}(?:\n|$)/", $table[0] ?? '')
                ) {
                    $row = preg_replace("/^( *(?:\S ?)+) ([A-Z\d\-]{5,} {3,})/", '$1  $2', $row);
                    $pos = $this->rowColumnPositions($this->inOneRow($row));
                    unset($pos[count($pos) - 1]);
                    $table = $this->createTable($row, $pos);
                }

                if (count($pos) !== 7) {
                    $email->add()->hotel();

                    return $email;
                }

                $startDate = isset($table[3]) ? strtotime($table[3]) : null;
                $endDate = isset($table[4]) ? strtotime($table[4]) : null;

                unset($h);

                foreach ($email->getItineraries() as $it) {
                    if ($it->getHotelName() === $hotelName
                        && $it->getCheckInDate() === $startDate
                        && $it->getCheckOutDate() === $endDate
                    ) {
                        $h = $it;

                        break;
                    }
                }

                if (!isset($h)) {
                    $h = $email->add()->hotel();

                    // Hotel
                    $h->hotel()
                        ->name($hotelName)
                        ->address($hotelAddress)
                        ->phone($hotelPhone)
                    ;

                    // Booked
                    $h->booked()
                        ->checkIn($startDate)
                        ->checkOut($endDate);
                }

                $room = $h->addRoom();
                $room->setConfirmation(trim($table[1]));

                $h->general()
                    ->confirmation(trim($table[1]));

                $traveller = trim($table[0]);
                $traveller = preg_replace('/\s*\n\s*ID:[\s\S]+/', '', $traveller);
                $traveller = preg_replace('/\s+/', ' ', $traveller);

                if (!in_array($traveller, array_column($h->getTravellers(), 0))) {
                    $h->general()
                        ->traveller($traveller);
                }

                $rates = array_filter(explode("\n",
                    $this->re("/^([\s\S]+?)\n\s*{$this->opt($this->t('Subtotal:'))}/", $table[6] ?? '')));

                if (count($rates) == trim($table[5] ?? '')) {
                    $room->setRates(preg_replace("/^\s*[\d\/]{5,} +(\S.+)/", '$1', $rates));
                }
            }

            $totalText = $this->re("/\n +{$this->opt($this->t('TOTAL CHARGES:'))} *(.+)/", $htext);

            if ($this->price !== null) {
                if ((preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $totalText, $m)
                    || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $totalText, $m))
                    && ($this->price['currency'] === null || $this->price['currency'] === $m['currency'])
                ) {
                    $this->price['totals'][] = PriceHelper::parse($m['amount'], $m['currency']);
                    $this->price['currency'] = $m['currency'];
                } else {
                    $this->price == null;
                }
            }
            $costText = $this->re("/\n +{$this->opt($this->t('SUBTOTAL:'))} *(.+)/", $htext);

            if ($this->price !== null) {
                if ((preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $costText, $m)
                    || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $costText, $m))
                    && ($this->price['currency'] === null || $this->price['currency'] === $m['currency'])
                ) {
                    $this->price['costs'][] = PriceHelper::parse($m['amount'], $m['currency']);
                    $this->price['currency'] = $m['currency'];
                } else {
                    $this->price == null;
                }
            }
            $taxText = $this->re("/\n +{$this->opt($this->t('TAXES & FEES:'))} *(.+)/", $htext);

            if ($this->price !== null) {
                if ((preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $taxText, $m)
                    || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $taxText, $m))
                    && ($this->price['currency'] === null || $this->price['currency'] === $m['currency'])
                ) {
                    $this->price['taxes'][] = PriceHelper::parse($m['amount'], $m['currency']);
                    $this->price['currency'] = $m['currency'];
                } else {
                    $this->price == null;
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
        if (empty($date) || empty($this->date)) {
            return null;
        }

        $in = [
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1 :43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r( $date, true));

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
