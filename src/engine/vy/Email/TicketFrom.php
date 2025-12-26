<?php

namespace AwardWallet\Engine\vy\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketFrom extends \TAccountChecker
{
    public $mailFiles = "vy/it-475910037.eml, vy/it-477686157.eml, vy/it-587756795.eml, vy/it-590261533.eml, vy/it-597523304.eml, vy/it-597793507.eml, vy/it-598541089.eml, vy/it-609007617.eml, vy/it-613976121.eml";
    public $subjects = [
        '/Ticket from Vy\:\s*\d+\s*\w+\s*\d{4}\s*\d+\:\d+\,\s*Ref\:\s*[A-Z\d]{3}\-[A-Z\d]{3}\-[A-Z\d]{3}/',
        // no
        '/Billett fra Vy\:\s*\d+\.\s*\w+\s*\d{4}\s+kl\.\s+\d+\:\d+\,\s*Ref\:\s*[A-Z\d]{3}\-[A-Z\d]{3}\-[A-Z\d]{3}/u',
    ];

    public $lang = '';
    public $detectLang = [
        "en" => ['Ticket', 'Receipt'],
        "no" => ['Billett', 'Kvittering'],
    ];

    public $pdfNamePattern = ".*pdf";

    public $emailDate = null;

    public static $dictionary = [
        "en" => [
            // 'Travelers with a discount must be able to produce ID' => '',
            // 'Reference code' => '',
            // 'Order-ID' => '',
            'Train with' => ['Train with', 'Tog med', 'Train %% with'], // Train 96 with -> Train %% with
            'Bus with'   => ['Bus with', 'Other with', 'Tram with', 'Metro with'],
            // 'to' => '',
            // 'Ticket' => '',
            // 'Car' => '',
            // 'Seat' => '',

            // 'This document does not confer any travel rights.' => '',
            // 'Receipt' => '',
            // 'Booking number' => '',
            // 'OrderID' => '',
            // 'Customer name' => '',
            // 'with' => '',
            // 'Product' => '',
            // 'till' => '',
            // 'OrdreID:' => '',
            // 'Total price' => '',
            // 'Vat rate' => '',
            // 'Basis' => '',
            // 'Vat' => '',
        ],
        "no" => [
            'Travelers with a discount must be able to produce ID' => 'Reisende med rabatt må kunne vise fram ID',
            'Reference code'                                       => 'Referansekode',
            // 'Order-ID' => '',
            'Train with'                                           => 'Tog med',
            'Bus with'                                             => ['Buss med', 'Annen med'],
            'to'                                                   => 'mot',
            'Ticket'                                               => 'Billett',
            'Car'                                                  => 'Vogn',
            'Seat'                                                 => 'sete',

            'This document does not confer any travel rights.' => 'Denne kvitteringen er ikke gyldig som reisebevis.',
            'Receipt'                                          => 'Kvittering',
            'Booking number'                                   => 'Referansekode',
            // 'OrderID' => '',
            'Customer name'                                    => 'Kundenavn',
            'with'                                             => 'med',
            'Product'                                          => 'Produkt',
            'till'                                             => 'til',
            'OrdreID:'                                         => 'OrdreID:',
            'Total price'                                      => 'Totalpris',
            'Vat rate'                                         => 'Mva-sats',
            'Basis'                                            => 'Grunnlag',
            'Vat'                                              => 'Mva',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && (stripos($headers['from'], '@vy.no') !== false)) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            if ((stripos($text, 'vy.no') !== false)
                && stripos($text, $this->t('Travelers with a discount must be able to produce ID')) !== false
                && stripos($text, $this->t('Reference code')) !== false) {
                return true;
            }

            if ((stripos($text, 'Vygruppen AS Org Nr.') !== false || stripos($text, 'Vy Tog AS Org Nr.') !== false)
                && stripos($text, $this->t('This document does not confer any travel rights.')) !== false
                && stripos($text, $this->t('Customer name')) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]vy\.no$/', $from) > 0;
    }

    public function ParseSegmentPDF($it, $text, $date): void
    {
        $s = $it->addSegment();

        $tableText = $this->re("/^((?:.*\n+){3,10}?)\n\n/", $text);
        $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));

        if (count($table) < 3) {
            return;
        }
        // $this->logger->debug('$table = '.print_r( $table,true));

        if (preg_match("/^\s*(?:{$this->opt($this->t('Bus with'))}|{$this->opt($this->t('Train with'))}) *.*\n(?:[\S ]*?\s)? *(?<number>[A-Z\d]{1,5})\s*{$this->opt($this->t('to'))}\s*.+/", $table[2], $m)) {
            $s->setNumber($m['number']);
        }

        if (preg_match("/^\s*(?<depTime>\d+\:\d+)\s*\n\s*(?<arrTime>\d+\:\d+)\s*$/", $table[0], $m)) {
            $s->departure()->date(strtotime($m['depTime'], $date));
            $s->arrival()->date(strtotime($m['arrTime'], $date));

            if (!empty($s->getArrDate()) && !empty($s->getDepDate())
                && $s->getArrDate() < $s->getDepDate()
                && strtotime('+ 1 day', $s->getArrDate()) > $s->getDepDate()
            ) {
                $s->arrival()
                    ->date(strtotime('+ 1 day', $s->getArrDate()));
            }
        }

        if (preg_match("/^\s*(?<depName>(.+\n)+)\n+(?<arrName>.+(?:\n.+)*)\s*$/", $table[1], $m)) {
            $s->departure()->name($this->normalizeNameStation($m['depName']))->geoTip('no');
            $s->arrival()->name($this->normalizeNameStation($m['arrName']))->geoTip('no');
        }

        if ($it->getType = 'train' && preg_match("/{$this->opt($this->t('Car'))}\s*(?<car>\d+)\s+\-\s+{$this->opt($this->t('Seat'))}\s*(?<seat>\d+)/", $text, $m)) {
            $s->setCarNumber($m['car']);
            $s->extra()
                ->seat($m['seat']);
        } elseif ($it->getType = 'bus' && preg_match("/\s+\-\s+{$this->opt($this->t('Seat'))}\s*(?<seat>\d+[A-Z]*)/", $table[2], $m)) {
            $s->extra()
                ->seat($m['seat']);
        }

        $segments = $it->getSegments();

        foreach ($segments as $segment) {
            if ($segment->getId() !== $s->getId()) {
                if (serialize(array_diff_key($segment->toArray(),
                        ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                    if (!empty($s->getSeats())) {
                        $segment->extra()->seats(array_unique(array_merge($segment->getSeats(),
                            $s->getSeats())));
                    }
                    $it->removeSegment($s);

                    break;
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->emailDate = $this->normalizeDate($this->re('/\bVy\s*:\s*(\d{1,2}[.,\s]+[[:alpha:]]+[.,\s]+\d{4}\b)/u', $parser->getSubject()), $parser);

        if (is_integer($this->emailDate)) {
            $this->emailDate = strtotime('-7 days', $this->emailDate);
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $itineraryPDFs = $receiptPDFs = [];
        $confNumbersAll = [];

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            if (preg_match("/{$this->opt($this->t('Receipt'))}/u", $text)) {
                $receiptPDFs[] = $pdf;

                if (preg_match("/{$this->opt($this->t('Total price'))} *(?<currency>[A-Z]{3}) *(?<total>\d[ \d\.\,]*)\n/u", $text, $m)) {
                    $email->price()
                        ->total(PriceHelper::parse($m['total'], $m['currency']))
                        ->currency($m['currency']);

                    if (preg_match("/\n+\s*{$this->opt($this->t('Vat rate'))}\s*{$this->opt($this->t('Basis'))}\s*{$this->opt($this->t('Vat'))}.*\n.*\n\s*\d+[%]\s*[A-Z]{3} *(?<cost>\d[\d\.\, ]*) *[A-Z]{3} *(?<tax>\s[\d\.\, ]*)/u", $text, $match)) {
                        $email->price()
                            ->cost(PriceHelper::parse($match['cost'], $m['currency']))
                            ->tax(PriceHelper::parse($match['tax'], $m['currency']));
                    }
                }
            } elseif (preg_match("/^\s*\n*{$this->opt($this->t('Ticket'))}/mu", $text)) {
                $itineraryPDFs[] = $pdf;
                $otaConfs = array_unique($this->res("/(?:\n| {3,}){$this->opt($this->t('Reference code'))}[ ]*[:]+[ ]*([A-Z\d\-]+)\n/", $text));

                foreach ($otaConfs as $conf) {
                    if (!in_array($conf, $confNumbersAll)) {
                        $email->ota()->confirmation($conf, $this->re("/(?:\n| {3,})({$this->opt($this->t('Reference code'))})[ ]*[:]+[ ]*[A-Z\d\-]+/", $text));
                        $confNumbersAll[] = $conf;
                    }
                }

                $otaConfs = array_unique($this->res("/(?:\n| {3,}){$this->opt($this->t('Order-ID'))}[ ]*[:]+[ ]*([A-Z\d]+)(?:\n|$)/", $text));

                foreach ($otaConfs as $conf) {
                    if (!in_array($conf, $confNumbersAll)) {
                        $email->ota()->confirmation($conf, $this->re("/(?:\n| {3,})({$this->opt($this->t('Order-ID'))})[ ]*[:]+\s*([A-Z\d]+)(?:\n|$)/", $text));
                        $confNumbersAll[] = $conf;
                    }
                }

                $travellers = array_unique($this->res("/{$this->opt($this->t('Ticket'))}\n([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])[\. ]{5,}{$this->opt($this->t('Reference code'))}[ ]*[:]+/u", $text));

                $tickets = $this->split("/^({$this->opt($this->t('Ticket'))})/mu", $text);

                foreach ($tickets as $ticket) {
                    $dateText = $this->re("/{$this->opt($this->t('Travelers with a discount must be able to produce ID'))}.*\n+([\s\S]+?)\n(?: *\d{1,2}:\d{2}\D+|\n)/", $ticket);
                    $table = $this->createTable($dateText, $this->columnPositions($this->inOneRow($dateText)));

                    $dateVal = preg_replace('/\n\d{7,}(?:\n|\s*$)/', '', trim($table[1] ?? ''));
                    $dateVal = preg_replace('/\s+/', ' ', $dateVal);
                    $date = $this->normalizeDate($dateVal, $parser);

                    $segments = $this->split("/(\n.* +(?:{$this->opt($this->t('Train with'))}|{$this->opt($this->t('Bus with'))}))/", $ticket);
                    // $this->logger->debug('$segments = ' . print_r($segments, true));

                    foreach ($segments as $stext) {
                        if (preg_match("/{$this->opt($this->t('Train with'))}/", $stext)) {
                            if (!isset($t)) {
                                $t = $email->add()->train();

                                $t->general()
                                    ->noConfirmation();

                                if (!empty($travellers)) {
                                    $t->general()
                                        ->travellers($travellers, true);
                                }
                            }
                            $this->ParseSegmentPDF($t, $stext, $date);
                        } elseif ($this->containsText($stext, $this->t('Bus with')) !== false) {
                            if (!isset($b)) {
                                $b = $email->add()->bus();

                                $b->general()
                                    ->noConfirmation();

                                if (!empty($travellers)) {
                                    $b->general()
                                        ->travellers($travellers, true);
                                }
                            }
                            $this->ParseSegmentPDF($b, $stext, $date);
                        }
                    }
                }
            }
        }

        if (count($itineraryPDFs) === 0 && count($receiptPDFs) === 1) {
            $text = \PDF::convertToText($parser->getAttachmentBody($receiptPDFs[0]));

            $email->ota()
                ->confirmation($this->re("/(?:^|\n| {3,}){$this->opt($this->t('Booking number'))} +.+\n +([A-Z\d\-]+) +\w+\n/", $text),
                    $this->re("/(?:^|\n| {3,})({$this->opt($this->t('Booking number'))}) +/", $text));

            $otaConfs = array_unique($this->res("/, *{$this->opt($this->t('OrderID'))}[ ]*[:]+[ ]*([A-Z\d]+)(?: {3,}|\n)/", $text));

            foreach ($otaConfs as $conf) {
                $email->ota()->confirmation($conf, trim($this->re("/, *({$this->opt($this->t('OrderID'))})[ ]*[:]+[ ]*[A-Z\d]+(?: {3,}|\n)/", $text), ':'));
            }

            $travellers = array_unique($this->res("/\n {20,}{$this->opt($this->t('Customer name'))} *\n {20,}([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\n/u", $text));

            $segments = splitter("/\n(.+–.+\n.*\d{4}.*\n.+{$this->opt($this->t('with'))}.+\n+{$this->opt($this->t('Product'))})/u", $text);

            foreach ($segments as $sText) {
                if (preg_match("/{$this->opt($this->t('Train with'))}/", $sText)) {
                    if (!isset($t)) {
                        $t = $email->add()->train();

                        $t->general()
                            ->noConfirmation();

                        if (!empty($travellers)) {
                            $t->general()
                                ->travellers($travellers, true);
                        }
                    }
                    $s = $t->addSegment();
                } else {
                    if (!isset($b)) {
                        $b = $email->add()->bus();

                        $b->general()
                            ->noConfirmation();

                        if (!empty($travellers)) {
                            $b->general()
                                ->travellers($travellers, true);
                        }
                    }
                    $s = $b->addSegment();
                }

                if (preg_match("/^\s*(.+?) *– *(.+)/", $sText, $m)) {
                    $s->departure()->name($this->normalizeNameStation($m[1]))->geoTip('no');
                    $s->arrival()->name($this->normalizeNameStation($m[2]))->geoTip('no');

                    $s->extra()
                        ->noNumber();
                }

                if (preg_match("/^\s*.+\n(?<date>.+?)(?:\s+kl\.)?\s+(?<dtime>\d{1,2}:\d{2}.{0,5}) *– *(?<atime>\d{1,2}:\d{2}.{0,5})\n/u", $sText, $m)) {
                    $date = $this->normalizeDate($m['date'], $parser);
                    $s->departure()->date(strtotime($m['dtime'], $date));
                    $s->arrival()->date(strtotime($m['atime'], $date));
                } elseif (preg_match("/^\s*.+\n(?<ddate>.+?)(?:\s+kl\.)? +(?<dtime>\d{1,2}:\d{2}.{0,5}) +{$this->opt($this->t('till'))} +(?<adate>.+?)(?:\s+kl\.)? +(?<atime>\d{1,2}:\d{2}.{0,5})\n/u", $sText, $m)) {
                    $s->departure()->date(strtotime($m['dtime'], $this->normalizeDate($m['ddate'], $parser)));
                    $s->arrival()->date(strtotime($m['atime'], $this->normalizeDate($m['adate'], $parser)));
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function res($re, $str, $c = 1): array
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return [];
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        $result = '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s);
        }, $field)) . ')';

        // for Train %% with -> Train 94 with
        $result = str_replace(' %% ', ' \d{2} ', $result);

        return $result;
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

    private function assignLang($text): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
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

    private function normalizeDate($date, $parser)
    {
        // $this->logger->debug('$date in = ' . print_r($date, true));
        $year = empty($this->emailDate) ? null : date('Y', $this->emailDate);

        $in = [
            //Thursday November 30th
            '/^\s*([-[:alpha:]]+)[,.\s]+([[:alpha:]]+)[,.\s]+(\d{1,2})[A-z]{2}\s*$/u',
            // Fredag 25. august
            '/^\s*([-[:alpha:]]+)[,.\s]+(\d{1,2})[,.\s]+([[:alpha:]]+)\s*$/u',
            // Lørdag 18. november 2023 kl. 10:39
            '/^\s*[-[:alpha:]]+[,.\s]+(\d{1,2})[,.\s]+([[:alpha:]]+)[,.\s]+(\d{4})\s*$/iu',
            // 1. desember 2023
            '/^\s*(\d{1,2})[,.\s]+([[:alpha:]]+)[,.\s]+(\d{4})\s*$/u',
        ];
        $out = [
            '$1, $3 $2 ' . $year,
            '$1, $2 $3 ' . $year,
            '$1 $2 $3',
            '$1 $2 $3',
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date out = ' . print_r($date, true));

        if (preg_match('/\b\d{1,2}\s+([[:alpha:]]+)(?:\s+\d{4}\b|\s+%year%|$)/u', $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match('/^\d{1,2} [[:alpha:]]+ \d{4}(?:\s*,\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/iu', $date)) {
            // $this->logger->debug('$date (year) = ' . print_r($date, true));
            return strtotime($date);
        } elseif ($year > 2000 && preg_match('/^(?<week>[-[:alpha:]]+), (?<date>\d{1,2} [[:alpha:]]+ \d{4}\b)/u', $date, $m)
            || !$year && preg_match('/^\s*(?<week>[-[:alpha:]]+), (?<date>\d{1,2} [[:alpha:]]+)\s*$/u', $date, $m)
        ) {
            // $this->logger->debug('$date (week no year) = ' . print_r($date, true));
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (!$year && preg_match('/^\s*(?:[-[:alpha:]]+, )?(?<date>\d{1,2} [[:alpha:]]+)\s*$/', $date, $m)) {
            // $this->logger->debug('$date (no week no year) = ' . print_r($date, true));
            return EmailDateHelper::calculateDateRelative($m['date'], $this, $parser, '%D% %Y%');
        }

        return null;
    }

    private function normalizeNameStation(?string $s): ?string
    {
        $s = preg_replace([
            '/\bBusstasjon\b/i',
            '/\bStasjon\b/i',
            '/\bLufthavn\b/i',
        ], [
            'Bus Station',
            'Station',
            'Airport',
        ], $s);

        return $s;
    }
}
