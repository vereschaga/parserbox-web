<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ExciteItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-17032734.eml, mta/it-17227988.eml, mta/it-39761289.eml, mta/it-39990394.eml";

    public $reFrom = ["exciteholidays.com"];
    public $reBody = [
        'en'  => ['Page 1', 'Resort, amenities and facilities fees and city taxes may not be included'],
        'en2' => ['Page 1', 'Service vouchers will be issued upon receipt of full payment'],
    ];
    public $reSubject = [
        '#Itinerary - Booking \(bkg id: [A-Z\d]{5,}\) For#i',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    }

                    if (!$this->parseEmail($text, $email)) {
                        return null;
                    }
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((strpos(trim($text), 'In cooperation with') === 0)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (preg_match($reSubject, $headers["subject"])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
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

    private function parseEmail($text, Email $email)
    {
        if (
            empty($textPDF = strstr($text, $this->t('Resort, amenities and facilities fees and city taxes may not be included'), true))
            && empty($textPDF = strstr($text, " Total amount of unpaid items:", true))
        ) {
            $this->logger->debug('other pdf format');

            return false;
        }

        $email->ota()
            ->confirmation($this->re("#{$this->t('In cooperation with')}\s+.*?\s*([\d]{6,})#", $textPDF),
                $this->t('Booking ID'));

        $total = $this->re("#\n\s*Total amount of all items:[ ]*(.+)\n#", $text);

        if (!empty($total) && (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m))) {
            $email->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        $textPDF = str_replace("\n\n", "\n\n\n\n", $textPDF);
        $arr = $this->splitter("#\n( *\d+ \w+ \d{4}.+?\n\n)#s", $textPDF);

        foreach ($arr as $i=> $root) {
            $table = $this->splitCols($root, $this->colsPos($root, 10));

            if (count($table) < 5) {
                $this->logger->debug("other format table in {$i}-segment");

                return false;
            }

            if (preg_match("#^( *\d+ \w+ \d{4}[^\n]+\n *\d+ \w+ \d{4})#", $root)) {
                $this->parseHotel($root, $table, $email);

                continue;
            }

            if (strpos($table[1], $this->t('Tour')) !== false) {
                $this->parseEvent($root, $table, $email);

                continue;
            }

            if (strpos($table[1], $this->t('Ferry')) !== false) {
//                $this->parseFerry($root, $table, $email);
                continue;
            }
        }

        return true;
    }

    private function parseHotel($textPDF, $table, Email $email)
    {
        $h = $email->add()->hotel();
        $h->general()
            ->noConfirmation();

        if (count($arr = explode("\n", trim($table[0]))) === 2) {
            $h->booked()
                ->checkIn(strtotime($arr[0]))
                ->checkOut(strtotime($arr[1]));
        }
        $h->general()
            ->status(trim($table[5] ?? $table[4]));

        if (preg_match("#(.+?),\s+(.+)#s", preg_replace("#\s+Subject to cancellation.+#s", '', $table[1]), $m)) {
            $h->hotel()
                ->name($this->nice($m[1]))
                ->address($m[2]);
        }

        $r = $h->addRoom();
        $r->setType($this->re("#(.+)#", $table[2]));
        $r->setDescription($this->nice($this->re("#[^\n]+\n(.*)#s", $table[2])));

        $node = $this->re("#^ *{$this->opt($this->t('Rooms'))}:\s+(.+)#m", $textPDF);

        if (preg_match("#(\d+)\s+x\s+(.+?)\s+\-\s+(.+)#", $node, $m)) {
            $h->booked()
                ->rooms($m[1]);

            $h->general()
                ->travellers(array_map("trim", explode(",", $m[3])));
        }
        $node = $this->re("#^ *Arrival Date:\s+(.+)#m", $textPDF);

        if (preg_match("#(.+)\s+\-\s+(\d+)\s+{$this->opt($this->t('nights'))}#", $node, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1]))
                ->checkOut(strtotime("+ " . $m[2] . " days", strtotime($m[1])));
        }

        $pax = $this->parseTravellers($table[3], false);

        if (!empty($pax)) {
            $h->general()
                ->travellers($pax);
        }
        $kid = count($this->parseTravellers($table[3], true));

        if (!empty($kid)) {
            $h->booked()
                ->kids($kid, true, true);
        }

        if (!empty($table[5])) {
            $total = $table[4];

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
                $h->price()
                    ->total($this->amount($m['amount']))
                    ->currency($this->currency($m['curr']))
                ;
            }
        }

        return true;
    }

    private function parseEvent($textPDF, $table, Email $email)
    {
        $r = $email->add()->event();
        $r->general()
            ->noConfirmation()
            ->status(trim($table[5] ?? $table[4]));

        $r->booked()
                ->start(strtotime($table[0]))
                ->noEnd();

        if (preg_match("#(.+),\s+(.+)#s", preg_replace("#\s+Subject to cancellation.+#s", '', $table[1]), $m)) {
            $r->place()
                ->type(EVENT_EVENT)
                ->name($this->nice($m[1]))
                ->address($m[2]);
        }

        $pax = $this->parseTravellers($table[3]);

        if (!empty($pax)) {
            $r->general()
                ->travellers($pax);
        } elseif (!empty($pax = $this->parseTravellers($table[2])) && !preg_match("#\d#", implode(" ", $pax))) {
            if (!empty($table[4])) {
                $table[5] = $table[4];
            }
            $table[4] = $table[3];
            $table[3] = $table[2];
            $table[2] = '';
            $r->general()
                ->travellers($pax);
        }

        if (!empty(trim($table[2]))) {
            $r->place()->name($r->getName() . ', ' . $this->nice($table[2]));
        }

        if (!empty($table[5])) {
            $total = $table[4];

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
                $r->price()
                    ->total($this->amount($m['amount']))
                    ->currency($this->currency($m['curr']))
                ;
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
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
            return str_replace(' ', '\s+', preg_quote($s));
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

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }

    private function parseTravellers($str, $kids = false)
    {
        if (empty($str)) {
            return [];
        }
        $pax = preg_replace("#^[ ]*[MD][a-z]{1,3}\. #", '', array_filter(array_map([$this, 'nice'], $this->splitter("#(?:^|\n)([MD][a-z]{1,3}\. |child)#", "  \n" . $str))));

        return array_filter($pax, function ($v) use ($kids) {return (preg_match("#^\s*child#i", $v)) ? $kids : !$kids; });
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'  => 'EUR',
            'US$'=> 'USD',
            '$'  => 'USD',
            '£'  => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
