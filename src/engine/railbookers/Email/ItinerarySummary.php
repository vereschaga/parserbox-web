<?php

namespace AwardWallet\Engine\railbookers\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItinerarySummary extends \TAccountChecker
{
    public $mailFiles = "railbookers/it-830530620.eml, railbookers/it-830619872.eml, railbookers/it-830628883.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $date;
    public $lang;
    public static $dictionary = [
        'en' => [
            'Your Itinerary' => ['Your Itinerary', 'Itinerary Summary'],
            'Passengers:'    => ['Passengers:', 'passengers:'],
            'Day '           => 'Day ',
        ],
    ];

    public $europeStation = [
        'Milan', 'Amsterdam Central', 'Antwerp', 'Galway', 'Zurich Airport Station', 'London St Pancras',
    ];

    public $europeRegion = false;

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]railbookers\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // if (strpos($headers["subject"], 'Railbookers') !== false) {
        //     return true;
        // }

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
        if ($this->containsText($text, ['Railbookers']) === false) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your Itinerary'])
                && $this->containsText($text, $dict['Your Itinerary']) === true
                && !empty($dict['Day '])
                && $this->containsText($text, $dict['Day ']) === true
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
        // Travel Agency
        $email->ota()
            ->confirmation($this->re("/\n\s*{$this->opt($this->t('BOOKING NUMBER:'))} *([A-Z\d]{5,})\n/", $textPdf));

        $total = $this->re("/\n\s*{$this->opt($this->t('Total Amount'))} *(.+)\n/", $textPdf);

        if (preg_match('/^\s*(?<currency>[A-Z]{3})[ ]*\$? *(?<amount>\d[,.\'\d]*)\s*$/', $total, $m)
            || preg_match('/^\s*(?<amount>\d[,.\'\d]*) *(?<currency>[A-Z]{3})\s*$/', $total, $m)
        ) {
            $email->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
            ;
        }

        $isParseTrain = false;
        $segmentsShortText = $this->re("/\n\s*{$this->opt($this->t('Your Itinerary'))}\n([\s\S]+?)\n\s*(?:{$this->opt($this->t('Detailed Itinerary'))}|{$this->opt($this->t('Pricing'))}|{$this->opt($this->t('Destination Information'))})\n/", $textPdf);

        if (preg_match_all("/\n.+\n\s*{$this->opt($this->t('Departure:'))}(.+\n.+\n.+)/", $segmentsShortText, $m)) {
            $segDate = $this->re("/\n(.*\b\d{4}\b.*?)( {3,}.*)?\n *{$this->opt($this->t('Day '))} ?\d+\n/", $segmentsShortText)
                ?? $this->re("/\n *{$this->opt($this->t('Day '))} ?\d+: {3,}.*\b\d{4}\b.*?)( {3,}.*)?\n/", $segmentsShortText);

            if (!empty($segDate)) {
                $this->date = $this->normalizeDate($segDate);
            }
            $re = "/^\s*(?<dName>.+?) - (?<aName>.+?)\n\s*{$this->opt($this->t('Departure:'))}(?<dDate>.+\d+:\d+.*)\n\s*[^\d\n]+?: *(?<aDate>.+\d+:\d+.*)\n(?:.+ - )?(?<class>.+)/u";
            $t = $email->add()->train();
            $isParseTrain = true;

            $t->general()
                ->noConfirmation();

            if (preg_match_all("/\n *{$this->opt($this->t('Passengers:'))} *(.+)/", $segmentsShortText, $mat)) {
                $travellers = preg_split('/\s*,\s*/', implode(', ', $mat[1]));
                $t->general()
                    ->travellers(array_unique($travellers));
            }

            foreach ($m[0] as $sText) {
                if (preg_match($re, $sText, $m)) {
                    $s = $t->addSegment();

                    // Departure
                    $s->departure()
                        ->date($this->normalizeDate($m['dDate']))
                        ->name($m['dName']);

                    if (in_array($s->getDepName(), $this->europeStation) || $this->europeRegion == true) {
                        $s->departure()
                            ->name($s->getDepName() . ', europe')
                            ->geoTip('europe');
                    }

                    if ($s->getDepGeoTip() === 'europe') {
                        $this->europeRegion = true;
                    }

                    // Arrival
                    $s->arrival()
                        ->date($this->normalizeDate($m['aDate']))
                        ->name($m['aName']);

                    if (in_array($s->getArrName(), $this->europeStation) || $this->europeRegion == true) {
                        $s->arrival()
                            ->name($s->getArrName() . ', europe')
                            ->geoTip('europe');
                    }

                    // Extra
                    $s->extra()
                        ->noNumber()
                        ->cabin($m['class']);

                    if ($s->getDepDate() === $s->getArrDate()) {
                        $t->removeSegment($s);
                    }
                }
            }
        }

        $segmentsFullText = $this->re("/\n\s*{$this->opt($this->t('Detailed Itinerary'))}(\n[\s\S]+?)\n\s*(?:{$this->opt($this->t('Destination Information'))}|{$this->opt($this->t('Pricing'))})\n/", $textPdf);
        $segmentsFull = $this->split("/\n *({$this->opt($this->t('DAY'))} ?\d+:)/", $segmentsFullText);

        foreach ($segmentsFull as $dayText) {
            $dayDate = $this->re("/^\s*{$this->opt($this->t('DAY'))} ?\d+: *(.+)/", $dayText);
            $segs = $this->split("/\n(.+\n *(?:.*\b{$this->opt($this->t('Car'))}\b.*|{$this->opt($this->t('Check-in date:'))}.*|{$this->opt($this->t('Your room booking:'))}|.*\b{$this->opt($this->t('Tour'))}\b.*\n+ *{$this->opt($this->t('Service Phone Number:'))}|.+\n+ *{$this->opt($this->t('Departure:'))}))\n/", $dayText);

            foreach ($segs as $sText) {
                if ($isParseTrain === false && preg_match("/^(.+\n+ *(?:{$this->opt($this->t('Departure:'))}))\n/", $sText)) {
                    /* Train*/
                    unset($t);

                    foreach ($email->getItineraries() as $it) {
                        if ($it->getType() === 'train') {
                            $t = $it;
                        }
                    }

                    if (empty($t)) {
                        $t = $email->add()->train();

                        $t->general()
                            ->noConfirmation();

                        if (preg_match("/\n *{$this->opt($this->t('Passengers:'))}\s+(.+)/", $sText, $mat)) {
                            $t->general()
                                ->travellers(preg_split('/\s*,\s*/', $mat[1]));
                        }
                    }

                    $s = $t->addSegment();

                    $date = $this->re("/{$this->opt($this->t('Departure:'))}\s*(.+)\n/", $sText);

                    if (preg_match("/^\s*\d+:\d+\D{0,5}$/", $date)) {
                        $date = $dayDate . ', ' . $date;
                    }
                    $s->departure()
                        ->date($this->normalizeDate($date));

                    if (preg_match("/^\s*(.+?) *- *(.+)\n\s*(.+)/", $sText, $m)) {
                        $s->departure()
                            ->name($m[1]);

                        if (in_array($s->getDepName(), $this->europeStation)) {
                            $s->departure()
                                ->geoTip('europe');
                        }
                        $s->arrival()
                            ->name($m[2]);

                        if (in_array($s->getArrName(), $this->europeStation)) {
                            $s->arrival()
                                ->geoTip('europe');
                        }

                        $s->extra()
                            ->noNumber()
                            ->cabin($m[3]);
                    }

                    $date = $this->re("/{$this->opt($this->t('Arrival:'))}\s*(.+)\n/", $sText);

                    if (preg_match("/^\s*\d+:\d+\D{0,5}$", $date)) {
                        $date = $dayDate . ', ' . $date;
                    }
                    $s->arrival()
                        ->date($this->normalizeDate($date));
                }

                if (preg_match("/^(.+\n *(?:{$this->opt($this->t('Car'))}))\n/", $sText)) {
                    /* TRANSFER*/
                    unset($t);
                    $conf = $this->re("/{$this->opt($this->t('Booking reference:'))}\s+([A-Z\d\-]{5,})\n/", $sText);

                    foreach ($email->getItineraries() as $it) {
                        if ($it->getType() === 'transfer' && in_array($conf, array_column($it->getConfirmationNumbers(), 0))) {
                            $t = $it;
                        }
                    }

                    if (empty($t)) {
                        $t = $email->add()->transfer();

                        $t->general()
                            ->confirmation($conf);

                        if (preg_match("/\n *{$this->opt($this->t('Passengers:'))}\s+(.+)/", $sText, $mat)) {
                            $t->general()
                                ->travellers(preg_split('/\s*,\s*/', $mat[1]));
                        }
                    }

                    $s = $t->addSegment();

                    $s->departure()
                        ->date($this->normalizeDate($this->re("/{$this->opt($this->t('Time From:'))}\s+(.+)\n/", $sText)))
                    ;
                    $address = preg_replace('/\s+/', ' ', $this->re("/{$this->opt($this->t('Pick Up Location:'))}\s+([\s\S]+?)\n\s*{$this->opt($this->t('Drop Off Location:'))}/", $sText));

                    if (preg_match("/^\s*([A-Z]{3})\s*-\s*[A-Z\d]{2} ?\d{1,4} {$this->opt($this->t('from'))} [A-Z]{3}\s*-\s*/", $address, $m)) {
                        $s->departure()
                            ->code($m[1]);
                    } elseif (preg_match("/^\s*(.+)\s*-\s*{$this->opt($this->t('Train Schedule:'))}/", $address, $m)) {
                        $s->departure()
                            ->name($m[1]);

                        if (in_array($s->getDepName(), $this->europeStation)) {
                            $s->departure()
                                ->geoTip('europe');
                        }
                    } elseif (preg_match("/{$this->opt($this->t('Hotel Address:'))}\s*(.+)/", $address, $m)) {
                        $s->departure()
                            ->address($m[1]);
                    } else {
                        $s->departure()
                            ->address($address);
                    }
                    $s->arrival()
                        ->noDate()
                    ;
                    $address = preg_replace('/\s+/', ' ', $this->re("/{$this->opt($this->t('Drop Off Location:'))}\s+([\s\S]+?)\n\s*{$this->opt($this->t('Booking reference:'))}/", $sText));

                    if (preg_match("/^\s*([A-Z]{3})\s*-\s*[A-Z\d]{2} ?\d{1,4} {$this->opt($this->t('to'))} [A-Z]{3}\s*-\s*/", $address, $m)) {
                        $s->arrival()
                            ->code($m[1]);
                    } elseif (preg_match("/^\s*(.+)\s*-\s*{$this->opt($this->t('Train Schedule:'))}/", $address, $m)) {
                        $s->arrival()
                            ->name($m[1]);

                        if (in_array($s->getArrName(), $this->europeStation)) {
                            $s->arrival()
                                ->geoTip('europe');
                        }
                    } elseif (preg_match("/{$this->opt($this->t('Hotel Address:'))}\s*(.+)/", $address, $m)) {
                        $s->arrival()
                            ->address($m[1]);
                    } else {
                        $s->arrival()
                            ->address($address);
                    }
                }

                if (preg_match("/^(.+)\n+ *{$this->opt($this->t('Check-in date:'))}/", $sText, $m)) {
                    /* HOTEL*/
                    unset($h);
                    $name = trim($m[1]);
                    $checkIn = $this->normalizeDate($this->re("/{$this->opt($this->t('Check-in date:'))}\s*(.+)\n/", $sText));
                    $checkOut = $this->normalizeDate($this->re("/{$this->opt($this->t('Check-out date:'))}\s*(.+)\n/", $sText));

                    foreach ($email->getItineraries() as $it) {
                        if ($it->getType() === 'hotel' && $it->getHotelName() === $name && $it->getCheckInDate() === $checkIn && $it->getCheckOutDate() === $checkOut) {
                            $h = $it;
                        }
                    }

                    if (empty($h)) {
                        $h = $email->add()->hotel();

                        $h->hotel()
                            ->name($name)
                            ->address(preg_replace('/\s*\n\s*/', ' ', $this->re("/{$this->opt($this->t('Address:'))}\s+((?:.+\n){1,3})\n/", $sText)))
                            ->phone($this->re("/{$this->opt($this->t('Service Phone Number:'))}\s+(.+)\n/", $sText), true, true)
                        ;

                        $h->booked()
                            ->checkIn($checkIn)
                            ->checkOut($checkOut);
                    }

                    if (preg_match("/\n *{$this->opt($this->t('Passengers:'))}\s+(.+)/", $sText, $mat)) {
                        $travellers = array_filter(array_diff(preg_split('/\s*,\s*/', $mat[1]), array_column($h->getTravellers(), 0)));

                        if (!empty($travellers)) {
                            $h->general()
                                ->travellers($travellers, true);
                        }
                    }
                    $conf = $this->re("/{$this->opt($this->t('Booking reference:'))}\s+([A-Z\d\-]{5,})\n/", $sText);

                    if (!empty($conf) && !in_array($conf, array_column($h->getConfirmationNumbers(), 0))) {
                        $h->general()
                            ->confirmation($conf);
                    }

                    $roomType = $this->re("/{$this->opt($this->t('Your room booking:'))}\s*\n *\d+ ?x ?(.+)\n/", $sText);
                    $h->addRoom()
                        ->setType($roomType)
                        ->setConfirmation($conf, true, true);
                }
            }

            foreach ($email->getItineraries() as $it) {
                if ($it->getType() === 'hotel' && empty($h->getConfirmationNumbers())) {
                    $it->general()
                        ->noConfirmation();
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
        $year = date("Y", $this->date);
        $in = [
            // ,11 Jan, 2025 - 14:44
            '/^\s*,?\s*(\d+)\s+([[:alpha:]]+)\s*,\s*(\d{4})\s*-\s*(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
            // 19 May, 2025
            '/^\s*(\d+)\s+([[:alpha:]]+)\s*,\s*(\d{4})\s*$/ui',
            // Tue, 01 Apr 11:01
            '/^\s*([[:alpha:]\-]+)\s*[, ]*\s*(\d+)\s+([[:alpha:]]+)\s*[-\s+]\s*(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$1 $2 $3',
            '$1, $2 $3 ' . $year . ', $4',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
        // $this->logger->debug('date end = ' . print_r( $date, true));

        if (preg_match('/\b20\d{2}\b/', $date)) {
            return strtotime($date);
        } elseif (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $date, $m) && !empty($this->date)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
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
