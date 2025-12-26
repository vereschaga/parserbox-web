<?php

namespace AwardWallet\Engine\rzd\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourTickets extends \TAccountChecker
{
    public $mailFiles = "rzd/it-129234531.eml";

    public $lang;
    public static $dictionary = [
        'ru' => [
            // html
            //            'Поезд №' => '',
            //            'Вагон №' => '',
            //            'Место(а) №' => '',
            //            'Продолжительность поездки:' => '',
            'Время отправления:'   => 'Время отправления:',
            'Станция отправления:' => 'Станция отправления:',
            //            'Время прибытия:' => '',
            //            'Станция прибытия:' => '',

            // pdf
            'ЭЛЕКТРОННЫЙ БИЛЕТ. КОНТРОЛЬНЫЙ КУПОН' => 'ЭЛЕКТРОННЫЙ БИЛЕТ. КОНТРОЛЬНЫЙ КУПОН',
        ],
    ];

    private $detectFrom = "@rzd.ru";
    private $detectSubject = [
        // en
        'Ваши билеты | RZD: ',
    ];

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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $body = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($body) || (mb_stripos($body, 'ОАО "РЖД"') === false && mb_stripos($body, 'РЖД Бонус') === false)
                && mb_stripos($body, 'АО "ФПК"') === false) {
                continue;
            }

            foreach (self::$dictionary as $lang => $dict) {
                if (!empty($dict['ЭЛЕКТРОННЫЙ БИЛЕТ. КОНТРОЛЬНЫЙ КУПОН']) && $this->containsText($body, $dict['ЭЛЕКТРОННЫЙ БИЛЕТ. КОНТРОЛЬНЫЙ КУПОН']) !== false) {
                    return true;
                }
            }
        }

        if ($this->http->XPath->query("//a[{$this->contains(['.rzd.ru'], '@href')}]")->length === 0) {
            return false;
        }

        if ($this->assignLang() == true) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = '';
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $body = \PDF::convertToText($parser->getAttachmentBody($pdf));
//            $this->logger->debug('$body = '.print_r($body,true));

            if (empty($body) || (mb_stripos($body, 'ОАО "РЖД"') === false && mb_stripos($body, 'РЖД Бонус') === false)) {
                continue;
            }

            $detected = false;

            foreach (self::$dictionary as $lang => $dict) {
                if (!empty($dict['ЭЛЕКТРОННЫЙ БИЛЕТ. КОНТРОЛЬНЫЙ КУПОН']) && $this->containsText($body, $dict['ЭЛЕКТРОННЫЙ БИЛЕТ. КОНТРОЛЬНЫЙ КУПОН']) === true) {
                    $this->lang = $lang;
                    $detected = true;

                    break;
                }
            }

            if ($detected == true) {
                $this->parsePdf($body, $email);

                if (count($email->getItineraries()) > 0) {
                    $type = 'Pdf';
                }
            }
        }

        if (empty($type)) {
            if (!$this->assignLang()) {
                $this->logger->debug("can't determine a language");

                return $email;
            }

            $type = 'Html';

            $this->parseHtml($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
    }

    public function inOneRow(string $text): string
    {
        $textRows = explode("\n", $text);
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                if (isset($row[$l]) && (trim($row[$l]) !== '')) {
                    $notspace = true;
                    $oneRow[$l] = $row[$l];
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function parsePdf(string $text, Email $email)
    {
        $this->logger->debug(__METHOD__);

        $section = mb_stristr($text, 'Тариф:', true);

        if (preg_match("/\n(.+)ПАСПОРТ РФ/", $section, $m)) {
            $pos = [0, mb_strlen($m[1])];
        } else {
            return false;
        }

        $table = $this->createTable($section, $pos, true);
        $table = preg_replace("/^ +$/m", '', $table);
//        $this->logger->debug('$table = '.print_r($table,true));

        if (!empty($email->getItineraries()[0])) {
            $t = $email->getItineraries()[0];
        } else {
            $t = $email->add()->train();
        }

        // General
        $conf = str_replace(' ', '', $this->re("/\n {0,5}{$this->opt($this->t("Заказ:"))}(?: {5,}.*)?\n {0,5}(\d(?: ?\d)+)(?: {5,}|\n|$)/u", $text));

        if (!empty($conf) && !in_array($conf, array_column($t->getConfirmationNumbers(), 0))) {
            $t->general()
                ->confirmation($conf);
        }

        // Ticket
        if (preg_match("/" . $this->opt($this->t("ЭЛЕКТРОННЫЙ БИЛЕТ. КОНТРОЛЬНЫЙ КУПОН")) . " *№([\d ]{10,})\n/", $text, $m)
            && !in_array($conf, array_column($t->getTicketNumbers(), 0))
        ) {
            $t->setTicketNumbers([$m[1]], false);
        }

        // Account
        if (preg_match("/\n {0,5}{$this->opt($this->t("РЖД Бонус:"))}(?: {5,}.*)?\n {0,5}(\d(?: ?\d)+)(?: {5,}|\n|$)/u", $text, $m)) {
            $t->program()
                ->account($m[1], false, null, 'РЖД Бонус');
        }

        // Segments
//        $this->logger->debug('$text = '.print_r( $text,true));

        $s = $t->addSegment();

        if (preg_match("/\n\n((?: *\S.*\n+){1,3}\s*Отправление[\s\S]+?)\n\n((?: *\S.*\n+){1,3}\s*Прибытие[\s\S]+?)\n\s*ВРЕМЯ В ПУТИ/", $table[0], $m)) {
            // Departure
            $depT = $this->createTable($m[1], $this->rowColumnPositions($this->inOneRow($m[1])));

            if (count($depT) == 2) {
                $s->departure()
                    ->name(preg_replace("/\s+/", ' ', trim($this->re("/^\s*[\d\.]+\s+\w{1,3}\s*([\p{Lu}+\W\d]+)\n/u", $depT[1]))))
                    ->date(strtotime($this->re("/^\s*([\d\.]+)\s+/u", $depT[1]) . ', ' . $this->re("/^\s*(\d{1,2}:\d{2})\s+/u", $depT[0])));

                $depAddress = preg_replace("/\s+/", ' ', trim($this->re("/^\s*[\d\.]+\s+\w{1,3}\s*[\p{Lu}+\W\d]+\n([\s\S]+)/u", $depT[1])));

                if (!empty($depAddress)) {
                    $s->departure()
                        ->address($depAddress);
                }
            }

            // Arrival
            $arrT = $this->createTable($m[2], [0, 30]);

            if (count($arrT) == 2) {
                $s->arrival()
                    ->name(preg_replace("/\s+/", ' ', trim($this->re("/^\s*[\d\.]+\s+\w{1,3}\s*([\p{Lu}+\W\d]+)\n/u", $arrT[1]))))
                    ->date(strtotime($this->re("/^\s*([\d\.]+)\s+/u", $arrT[1]) . ', ' . $this->re("/^\s*(\d{1,2}:\d{2})\s+/u", $arrT[0])));

                $arrAddress = preg_replace("/\s+/", ' ', trim($this->re("/^\s*[\d\.]+\s+\w{1,3}\s*[\p{Lu}+\W\d]+\n([\s\S]+)/u", $arrT[1])));

                if (!empty($arrAddress)) {
                    $s->arrival()
                        ->address($arrAddress);
                }
            }
        }
        // Extra
        if (preg_match("/\n {0,5}(\S( ?\S)+) {3,}" . $this->opt($this->t("Тариф:")) . "/u", $text, $m)) {
            $s->extra()
                ->cabin($m[1])
            ;
        }

        if (preg_match("/" . $this->opt($this->t("ПОЕЗД")) . " +" . $this->opt($this->t("ВАГОН")) . " +" . $this->opt($this->t("МЕСТО")) . "\n\s*\n *(\w+) +(\d+) +(\d+)\n/u", $table[0], $m)) {
            $s->extra()
                ->number($m[1])
                ->car($m[2])
                ->seat($m[3])
            ;
        }

        if (preg_match("/" . $this->opt($this->t("ВРЕМЯ В ПУТИ")) . " +.+\n\s*\n(\d+|—) +(\d+|—) +(\d+|—)($|\n)/u", $table[0], $m)) {
            $dur = [];

            if (is_numeric($m[1])) {
                $dur[] = $m[1] . ' д';
            }

            if (is_numeric($m[2])) {
                $dur[] = $m[1] . ' ч';
            }

            if (is_numeric($m[3])) {
                $dur[] = $m[1] . ' мин';
            }
            $s->extra()
                ->duration(implode(' ', $dur));
        }

        foreach ($t->getSegments() as $segment) {
            if ($segment->getId() !== $s->getId()) {
                if (($segment->getNumber() === $s->getNumber())
                    && ($segment->getDepDate() === $s->getDepDate())
                    && ($segment->getArrDate() === $s->getArrDate())
                ) {
                    if (!empty($s->getSeats())) {
                        $segment->extra()->seats(array_unique(array_merge($segment->getSeats(), $s->getSeats())));
                    }
                    $t->removeSegment($s);

                    break;
                }
            }
        }

        // Price

        if (preg_match("/" . $this->opt($this->t("Итого")) . "\n(?:.+\n{0,2})?.*\s+" . $this->opt($this->t("Вкл. НДС")) . " +(\d[\d ,\.]*) ([^\d]{1,5})\n/u", $text, $m)) {
            $currency = $this->currency($m[2]);
            $total = (float) PriceHelper::parse($m[1], $this->currency($m[2]));

            if (!empty($t->getPrice()) && !empty($t->getPrice()->getTotal()) && $t->getPrice()->getCurrencyCode() == $currency) {
                $t->price()->total($t->getPrice()->getTotal() + $total);
            } else {
                $t->price()
                    ->currency($currency)
                    ->total($total);
            }
        }

        return true;
    }

    private function parseHtml(Email $email)
    {
        $t = $email->add()->train();

        // General
        $t->general()
            ->noConfirmation();

        // Segments
        $text = implode("\n", $this->http->FindNodes("//text()[" . $this->starts($this->t("Время отправления:")) . "]/ancestor::*[" . $this->starts($this->t("Поезд №")) . "][1]//text()[normalize-space()]"));

        $s = $t->addSegment();

        // Departure
        $s->departure()
            ->name($this->re("/" . $this->opt($this->t("Станция отправления:")) . "\s*(.+)/u", $text))
            ->date($this->normalizeDate($this->re("/" . $this->opt($this->t("Время отправления:")) . "\s*(.+)/u", $text)));

        // Arrival
        $s->arrival()
            ->name($this->re("/" . $this->opt($this->t("Станция прибытия:")) . "\s*(.+)/u", $text))
            ->date($this->normalizeDate($this->re("/" . $this->opt($this->t("Время прибытия:")) . "\s*(.+)/u", $text)));

        // Extra
        $s->extra()
            ->number($this->re("/" . $this->opt($this->t("Поезд №")) . "\s*(.+)/u", $text))
            ->duration($this->re("/" . $this->opt($this->t("Продолжительность поездки:")) . "\s*(.+)/u", $text))
            ->car($this->re("/" . $this->opt($this->t("Вагон №")) . "\s*(.+)/u", $text))
            ->seats(explode(" ", $this->re("/" . $this->opt($this->t("Место(а) №")) . "\s*(.+)/u", $text)))
        ;

        return true;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (
                !empty($dict['Время отправления:']) && $this->http->XPath->query("//*[{$this->contains($dict['Время отправления:'])}]")->length > 0
                && !empty($dict['Станция отправления:']) && $this->http->XPath->query("//*[{$this->contains($dict['Станция отправления:'])}]")->length > 0
            ) {
                $this->lang = $lang;

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        //$this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // 10.01.2022 в 17:10(МСК)
            '/^\s*(\d{1,2}\.\d{1,2}\.\d{4})\s+в\s+(\d{1,2}:\d{2})\s*(?:\([[:alpha:]]{3,4}(?:[+-]*\d)?\))?$/ui',
        ];
        $out = [
            '$1, $2',
        ];

        $date = preg_replace($in, $out, $date);

        //$this->logger->debug('date end = ' . print_r( $date, true));

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

    private function createTable(?string $text, $pos = [], $correct = false): array
    {
        $ds = 5;
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                if ($correct == true) {
                    if ($k != 0 && (!empty(trim(mb_substr($row, $p - 1, 1))) || !empty(trim(mb_substr($row, $p + 1, 1))))) {
                        $str = mb_substr($row, $p - $ds, $ds, 'UTF-8');

                        if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m)) {
                            $cols[$k][] = trim($m[2] . mb_substr($row, $p, null, 'UTF-8'));
                            $row = mb_substr($row, 0, $p - strlen($m[2]) - 1, 'UTF-8');
                            $pos[$k] = $p - strlen($m[2]) - 1;

                            continue;
                        } else {
                            $str = mb_substr($row, $p, $ds, 'UTF-8');

                            if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m)) {
                                $cols[$k][] = trim($m[2] . mb_substr($row, $p + $ds, null, 'UTF-8'));
                                $row = mb_substr($row, 0, $p, 'UTF-8') . $m[1];
                                $pos[$k] = $p + strlen($m[1]) + 1;

                                continue;
                            } elseif (preg_match("#^\s+(.*)#", $str, $m)) {
                                $cols[$k][] = trim($m[1] . mb_substr($row, $p + $ds, null, 'UTF-8'));
                                $row = mb_substr($row, 0, $p, 'UTF-8');

                                continue;
                            } elseif (!empty($str)) {
                                $str = mb_substr($row, $p - $ds, $ds, 'UTF-8');

                                if (preg_match("#(\S*)\s+(\S*)$#", $str, $m)) {
                                    $cols[$k][] = trim($m[2] . mb_substr($row, $p, null, 'UTF-8'));
                                    $row = mb_substr($row, 0, $p - strlen($m[2]) - 1, 'UTF-8');
                                    $pos[$k] = $p - strlen($m[2]) - 1;

                                    continue;
                                }
                            }
                        }
                    }
                }

//                if ($trim === true) {
//                    $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
//                } else {
                $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
//                }
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

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '₽' => 'RUB',
            //            '€' => 'EUR',
            //            '$' => 'USD',
            //            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
