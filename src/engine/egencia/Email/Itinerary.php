<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "egencia/it-12547118.eml, egencia/it-445402765.eml, egencia/it-458653800.eml, egencia/it-623948275.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'Contact the Egencia') !== false
                && stripos($text, 'Egencia reference #') !== false
                && stripos($text, 'Price details') !== false) {
                return true;
            }

            return false;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function ParseHotel(Email $email, string $hText, string $price)
    {
        $hotels = $this->splitText($hText, "/(.+\n\s*.*Hotel confirmation:)/", true);

        foreach ($hotels as $text) {
            $h = $email->add()->hotel();

            $h->general()
                ->confirmation($this->re("/{$this->opt($this->t('Hotel confirmation:'))}\s*(\d+)/", $text),
                    'Hotel confirmation')
                ->cancellation($this->re("/{$this->opt($this->t('Cancellation and Changes'))}\n+\s*(.+)\./", $text), true, true);

            $h->hotel()
                ->name($this->re("/^[\s\W]*(.+)/u", $text))
                ->address($this->re("/\n {0,10}Address\s*\n {0,10}(\S.+)\n/", $text));

            $nights = 0;

            if (preg_match("/{$this->opt($this->t('Check-in'))}\s+.+\n+\s*(?<checkIn>\w+\,\s*\w+\s*\d+\,\s*\d{4})\s*(?<checkOut>\w+\,\s*\w+\s*\d+\,\s*\d{4})\s+(?<nights>\d+) [[:alpha:]]/",
                $text, $m)) {
                $h->booked()
                    ->checkIn($this->normalizeDate($m['checkIn']))
                    ->checkOut($this->normalizeDate($m['checkOut']));

                $nights = $m['nights'];
            }

            $info = $this->re("/^(\s+Room\s*Phone\/Fax\s*.+)\n\s*Address/ms", $text);
            $infoTable = $this->splitCols($info);

            if (preg_match("/\nP: ?((?:\S ?)+)/", $infoTable[1], $m)) {
                $h->hotel()
                    ->phone($m[1]);
            }

            if (preg_match("/\nF: ?((?:\S ?)+)/", $infoTable[1], $m)) {
                $h->hotel()
                    ->fax($m[1]);
            }

            if (preg_match("/Room\n+(?<rooms>\d+)\s*rooms?\s*\,\s*(?<guests>\d+)\s*adults?\s*(?<roomType>.+)/s",
                $infoTable[0], $m)) {
                $h->booked()
                    ->rooms($m['rooms'])
                    ->guests($m['guests']);

                $room = $h->addRoom();
                $m['roomType'] = preg_replace("/\s+/", ' ', $m['roomType']);

                if (strlen($m['roomType']) > 250) {
                    $room->setDescription($m['roomType']);
                } else {
                    $room->setType($m['roomType']);
                }
            }

            if (!empty($h->getHotelName())) {
                if (preg_match("/\n *\W? *{$this->opt($h->getHotelName())}\n(?<info>(?:.*\n)+?) {8,}(?<currency>\D{1,3}) ?(?<total>\d[\d\.\,]*)\n {8,}[^\d\n]+/u", $price, $m)) {
                    $h->price()
                        ->total(PriceHelper::parse($m['total'], $m['currency']))
                        ->currency($m['currency']);

                    if (preg_match("/^\s*(?<rates>(?: *[[:alpha:]]+, ?\d+\/\d+ {2,}[^\d\n]{1,3} ?\d[\d,. ]*\n+)+)\s*(?<taxes>[\s\S]+)/", $m['info'], $mat)) {
                        // Mon, 16/9                 INR11,100.00
                        $rates = array_filter(explode("\n", $mat['rates']));

                        if (count($rates) === (int) $nights && isset($room)) {
                            $rates = preg_replace("/^\s*\S+.*? {2,}(\S.+)/", '$1', $rates);
                            $room->setRates($rates);
                        }
                        $feesRows = $rates = array_filter(explode("\n", $mat['taxes']));

                        foreach ($feesRows as $row) {
                            if (preg_match("/^ *(\S.+?) {3,}\D{1,3}\s*(\d[\d\.\,]*)\s*$/", $row, $rm)) {
                                $h->price()
                                    ->fee($rm[1], PriceHelper::parse($rm[2], $m['currency']));
                            }
                        }
                    }
                }
            }

            if (stripos($text, 'Loyalty Program') !== false) {
                $account = $this->re("/Loyalty Program\n.+\s\:\s+([A-Z\dx]+)/", $text);

                if (!empty($account)) {
                    $h->program()
                        ->account($account, true);
                }
            }

            $this->detectDeadLine($h);
        }
    }

    public function ParseFlight(Email $email, string $text, string $price)
    {
        $f = $email->add()->flight();

        $confs = [];

        if (preg_match_all("/{$this->opt($this->t('Flight confirmation:'))}\s*([\dA-Z]{5,})\s*\n/", $text, $m)) {
            $confs = $m[1];
        }
        $confs = array_unique($confs);

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf)
            ;
        }

        $ticket = $this->re("/Ticket\s*Total distance\n+\s*\d{10,}\s*(\d{10,})\s*/", $text);
        $f->addTicketNumber($ticket, false);

        $segments = $this->splitText($text, "/^(\s*.+CO[₂]\s+.+)\n/mu", true);
        //Friday, August 11, 2023 -
        $year = $this->re("/^\s*\w+\,\s*\w+\s*\d{1,2}\,\s*(\d{4})\s*\-/m", $text);

        if (stripos($text, 'Loyalty Program') !== false) {
            $account = $this->re("/Loyalty Program\n.+\s\:\s+([A-Z\dx]+)/", $text);

            if (!empty($account)) {
                $f->program()
                    ->account($account, true);
            }
        }

        foreach ($segments as $segment) {
            if (stripos($segment, 'Departure') !== false) {
                $s = $f->addSegment();

                if (preg_match("/\((?<airline>[A-Z\d]{2})\)\s*(?<number>\d{1,4})\s*.*[ ]{10,}(?<duration>\d+[hm\d\s]+)/", $segment, $m)) {
                    $s->airline()
                        ->name($m['airline'])
                        ->number($m['number']);

                    $s->extra()
                        ->duration($m['duration']);
                }

                if (preg_match("/\s*Departure\s*Arrival\s*(?:[+]\d)?\n\s*(?<depDate>\w+\,\s+\w+\s*\d+)\s*at\s*(?<depTime>[\d\:]+\s*a?p?m)\s*(?<arrDate>\w+\,\s*\w+\s*\d+)\s*at\s*(?<arrTime>[\d\:]+\s*a?p?m?)\s*.*\n.*\((?<depCode>[A-Z]{3})\-.+\((?<arrCode>[A-Z]{3})\-/su", $segment, $m)) {
                    $depDate = $m['depDate'] . ', ' . $m['depTime'];
                    $arrDate = $m['arrDate'] . ', ' . $m['arrTime'];

                    $s->departure()
                        ->code($m['depCode'])
                        ->date($this->normalizeDate($depDate, $year));

                    $s->arrival()
                        ->code($m['arrCode'])
                        ->date($this->normalizeDate($arrDate, $year));
                }

                if (preg_match("/^\s*(Terminal)\s*.*\n\s*(?<depTerminal>(?:\d+|\-|\D))[ ]{10,}(?<seat>\S+)[ ]{10,}(?<cabin>\w+)\s*\((?<bookingCode>[A-Z])\)/m", $segment, $m)) {
                    if (!preg_match("/\-/", $m['depTerminal'])) {
                        $s->departure()
                            ->terminal($m['depTerminal']);
                    }

                    if (!preg_match("/\-/", $m['seat'])) {
                        $s->extra()
                            ->seat($m['seat']);
                    }

                    $s->extra()
                        ->cabin($m['cabin'])
                        ->bookingCode($m['bookingCode']);
                }
            }
        }

        if (preg_match_all("/\n *\W? *([A-Z]{3} ?- ?[A-Z]{3})\s*\([^\)]+\)\s+Base +/", $price, $m)
            && count($m[1]) > 1
        ) {
        } elseif (
            preg_match("/\n *\W? *[A-Z]{3} ?- ?[A-Z]{3}\s*\([^\)]+\)\s+Base +(?<currencyC>\D{1,3}) ?(?<cost>\d[\d\.\,]*)\n(?<taxes>(?:.*\n)+?) {8,}(?<currency>\D{1,3}) ?(?<total>\d[\d\.\,]*)\n {8,}[^\d\n]+/u", $price, $m)
        ) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->cost(PriceHelper::parse($m['cost'], $m['currencyC']))
                ->currency($m['currency']);

            $feesRows = $rates = array_filter(explode("\n", $m['taxes']));

            foreach ($feesRows as $row) {
                if (preg_match("/^ *(\S.+?) {3,}\D{1,3}\s*(\d[\d\.\,]*)\s*$/", $row, $rm)) {
                    $f->price()
                        ->fee($rm[1], PriceHelper::parse($rm[2], $m['currency']));
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $email->ota()->confirmation($this->re("/{$this->opt($this->t('Egencia reference #'))}\s*(\d+)/", $text), 'Egencia reference #');

            $this->parseEmail($email, $text);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseEmail(Email $email, $text)
    {
        $text = preg_replace("/\n +Page \d+ of \d+ *\n/", "\n", $text);
        $pos = strpos($text, 'Traveller information');
        $itineraryText = $text;

        if (empty($pos)) {
            $pos = strpos($text, 'Traveler information');
        }

        if (!empty($pos)) {
            $itineraryText = substr($itineraryText, 0, $pos);
        }

        $itineraryText = preg_replace("/^[\s\S]+\n(.+ {3,}Price details)/", '$1', $itineraryText);

        $table = [];

        if (preg_match("/^(.* {3,})Price details/", $itineraryText, $m)) {
            $pos = [0, strlen($m[1])];

            $table = $this->splitCols($itineraryText, $pos, false);
            $table = preg_replace("/ +\n/", "\n", $table);
        }

        if (stripos($text, 'Hotel confirmation:') !== false) {
            $this->ParseHotel($email, $table[0] ?? '', $table[1] ?? '');
        }

        if (stripos($text, 'Flight confirmation:') !== false) {
            $this->ParseFlight($email, $table[0] ?? '', $table[1] ?? '');
        }

        $traveller = $this->re("/{$this->opt($this->t('Traveler'))}\s+{$this->opt($this->t('Phone number'))}.*\n+\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])/",
            $text);

        foreach ($email->getItineraries() as $it) {
            $it->general()
                ->traveller($traveller, true);
        }

        if (preg_match_all("/^\s*{$this->opt($this->t('Total'))} {2,}(?<currency>\D{1,3})\s*(?<total>\d[\d\.\,]*)\s*$/um", $table[1] ?? '', $m)
            && count(array_unique($m['currency'])) === 1
        ) {
            $total = 0.0;

            foreach ($m['total'] as $t) {
                $total += PriceHelper::parse($t, $m['currency'][0]);
            }
            $email->price()
                ->total($total)
                ->currency($m['currency'][0]);

            if (preg_match_all("/\n\s*(?<name>Air booking fee|Air booking fee \(negotiated\s+rate\))\s*(?<currency>\D{1,3}) ?(?<amount>\d[\d\.\,]*)\n/", $table[1] ?? '', $match)
                && count(array_unique($match['currency'])) === 1
            ) {
                foreach ($match[1] as $i => $v) {
                    $email->price()
                        ->fee($match['name'][$i], PriceHelper::parse($match['amount'][$i], $match['currency'][$i]));
                }
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
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

    private function normalizeDate($date, $year = null)
    {
        $in = [
            // Sun, Jan 28, 6:15 pm
            '/^([-[:alpha:]]+)\s*,\s*([[:alpha:]]+)[.]?\s+(\d{1,2})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/ui',
        ];
        $out = [
            '$1, $3 $2 ' . $year . ', $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>[\w\-]+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b20\d{2}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function detectDeadLine($h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/There is no Hotel penalty for cancellations made before\s*(?<time>\d+\:\d+\s*A?P?M?)\s*local hotel time on\s*(?<date>[\d\/]+\d{4})/", $cancellationText, $m)
        || preg_match("/Cancellations or changes made after\s*(?<time>[\d\:]+\s*A?P?M)\s*local hotel time on\s*(?<date>[\d\/]+)\s+/", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['date'] . ', ' . $m['time']));
        }
    }

    private function rowColsPos($row): array
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

    private function splitCols($text, $pos = false, $trim = true): array
    {
        $cols = [];
        $rows = explode("\n", $text);
        $r = array_values(array_filter($rows));

        if (!$pos) {
            $pos = $this->rowColsPos($r[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $value = mb_substr($row, $p, null, 'UTF-8');

                if ($trim === true) {
                    $cols[$k][] = trim($value);
                } else {
                    $cols[$k][] = $value;
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

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
