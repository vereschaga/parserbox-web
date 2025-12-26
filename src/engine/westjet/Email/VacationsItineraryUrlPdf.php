<?php

namespace AwardWallet\Engine\westjet\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class VacationsItineraryUrlPdf extends \TAccountChecker
{
    public $mailFiles = "westjet/it-666866036.eml";
    // + pdfs westjet/VacationsItineraryUrlPdf-\d+.pdf

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'View WestJet Vacations itinerary' => 'View WestJet Vacations itinerary',
        ],
    ];

    private $detectFrom = "noreply@notifications.westjet.com";
    private $detectSubject = [
        // en
        'Here\'s your WestJet Vacations itinerary',
        'WestJet Vacations Confirmation',
    ];

    // Main Detects Methods
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
        if ($this->http->XPath->query("//a[contains(@href, '.westjet.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['View WestJet Vacations itinerary'])
                && $this->http->XPath->query("//a[{$this->contains($dict['View WestJet Vacations itinerary'])} and contains(@href, 'edocs.softvoyage.com/cgi-bin/sax-edocs/edoc.cgi')]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['View WestJet Vacations itinerary'])
                && $url = $this->http->FindSingleNode("//a[{$this->contains($dict['View WestJet Vacations itinerary'])} and contains(@href, 'edocs.softvoyage.com/cgi-bin/sax-edocs/edoc.cgi')]/@href")
            ) {
                $this->lang = $lang;

                break;
            }
        }

//        $this->logger->debug('$url = '.print_r( $url,true));
        if (!empty($url)) {
            $file = $this->http->DownloadFile($url);
            unlink($file);
            $text = \PDF::convertToText($this->http->Response['body']);

            $email->ota()
                ->confirmation($code = $this->re("/(?:^|\n)Booking number: +([A-Z\d]{5,})(?: {3,}|\n)/", $text));

            $accounts = array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t("WestJet Rewards ID:"))}]",
                null, "/:\s*(\d{5,})\s*$/")));

            if (!empty($accounts)) {
                foreach ($accounts as $account) {
                    $email->ota()
                        ->account($account, false);
                }
            }
            $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Total paid:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");
            $total .= ' ' . $this->http->FindSingleNode("//text()[{$this->eq($this->t("Total paid:"))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]/td[2]");

            if (preg_match("/^\s*\\$?\s*(?<amount>\d[\d\., ]*)(?<currency>[A-Z]{3})\s*$/", $total, $m)
                || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $total, $m)
                || preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $total, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $email->price()
                    ->total(PriceHelper::parse($m['amount'], $currency))
                    ->currency($currency)
                ;
            }

            $taxes = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Taxes and fees"))}]/following::text()[{$this->eq($this->t("Subtotal"))}][1]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

            if (preg_match("/^\s*\\$?\s*(?<amount>\d[\d\., ]*)(?<currency>[A-Z]{3})\s*$/", $taxes, $m)
                || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $taxes, $m)
                || preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $taxes, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $email->price()
                    ->tax(PriceHelper::parse($m['amount'], $currency))
                ;
            }

            $segments = $this->split("/(?:^|\n)(.+ {4,}Issue Date:.+\n)/", $text);

            foreach ($segments as $sText) {
                if (preg_match("/\n {0,5}Itinerary\n/", $sText)) {
                    $this->parsePdfFlight($email, $sText);
                } elseif (preg_match("/\n {0,5}Accommodation\n/", $sText)) {
                    $this->parsePdfHotel($email, $sText);
                } elseif (preg_match("/\n {0,5}Transfer\n/", $sText)) {
                } else {
                    $this->logger->debug('unknown segment');
                    $email->add()->flight();
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

    private function parsePdfFlight(Email $email, string $text)
    {
        $traveller = $this->re("/Guest(?: {3,}.*)?\n {0,5}(?:MS |MR |MISS |MRS |MSTR )?(.+?)(?:\(.*| {3,}.*)?\n/", $text);
        $code = $this->re("/Reservation code for check-in: +([A-Z\d]{5,7})\s+/", $text);

        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'flight') {
                $f = $it;

                if (!in_array($traveller, array_column($f->getTravellers(), 0))) {
                    $f->general()->traveller($traveller, true);
                }

                if (!in_array($code, array_column($f->getConfirmationNumbers(), 0))) {
                    $f->general()->confirmation($code);
                }
            }
        }

        if (!isset($f)) {
            $f = $email->add()->flight();

            $f->general()
                ->confirmation($code)
                ->traveller($traveller, true)
                ->date(strtotime($this->re("/Issue Date: +(.+)\n/", $text)))
            ;
        }

        $info = $text;

        if (preg_match("/Itinerary\n([\s\S]+?)\nInformation\n/", $text, $m)) {
            $info = $m[1];
        }

        $info = preg_replace("/(?:^|\n)(?:Departure|Return)\n/", "\n", $info);
        $segments = $this->split("/\n( *From +Terminal +)/", $info);
//        $this->logger->debug('$segments = '.print_r( $segments,true));
        foreach ($segments as $sText) {
            $s = $f->addSegment();

            $table = $this->createTable($sText, $this->rowColumnPositions($this->inOneRow($sText)));
//            $this->logger->debug('$table = '.print_r( $table,true));

            // Airline
            if (preg_match("/^\s*Flight\s*\n\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+)\n/", $table[4] ?? '', $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            if (preg_match("/Carrier\n\s*(\S.+)\nWeight/s", $table[3] ?? '', $m)) {
                $s->airline()
                    ->operator($m[1]);
            }

            // Departure, Arrival
            if (preg_match("/From\s+(?<d>.+)\nTo\n(?<a>.+)/s", $table[0] ?? '', $m)) {
                $s->departure()
                    ->noCode()
                    ->name(trim($m['d']));
                $s->arrival()
                    ->noCode()
                    ->name(trim($m['a']));
            }
            $dDate = $aDate = null;

            if (preg_match("/^\s*Date\n(?<d>.*\d{4}.*?)\s*\n\s*(?<a>.*\d{4}.*?)/s", $table[5] ?? '', $m)) {
                $dDate = trim($m['d']);
                $aDate = trim($m['a']);
            } else {
                $dDate = $aDate = trim($this->re("/^\s*Date\n(.+)/s", $table[5] ?? ''));
            }

            if (!empty($dDate) && preg_match("/^\s*Dep\n(.+)/s", $table[6] ?? '', $m)) {
                $s->departure()
                    ->date($this->normalizeDate($dDate . ',  ' . trim($m[1])));
            }

            if (!empty($aDate) && preg_match("/^\s*Arr\n(.+)/s", $table[7] ?? '', $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($aDate . ',  ' . trim($m[1])));
            }

            if (preg_match("/Terminal\s+(?<terminal>.*)\nSeat\n(?<seat>.*)/s", $table[1] ?? '', $m)) {
                if (!empty(trim($m['terminal']))) {
                    $s->departure()
                        ->terminal(trim($m['terminal']));
                }

                if (!empty(trim($m['seat']))) {
                    $s->extra()
                        ->seat(trim($m['seat']));
                }
            }

            if (preg_match("/Cabin Class\n\s*(.+)/s", $table[3] ?? '', $m)) {
                $s->extra()
                    ->cabin(trim($m[1]));
            }

            $segments = $f->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (serialize(array_diff_key($segment->toArray(),
                            ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                        if (!empty($s->getSeats())) {
                            $segment->extra()->seats(array_unique(array_merge($segment->getSeats(),
                                $s->getSeats())));
                        }
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
        }

        return true;
    }

    private function parsePdfHotel(Email $email, string $text)
    {
        $h = $email->add()->hotel();

        // General
        $confs = array_filter(preg_split("/\s*\|\s*/", preg_replace(["/(?:^| )I(?: |$)/", "/\s+/"], ["|", ''], $this->re("/Confirmation# +(.+)\n/", $text))));

        foreach ($confs as $conf) {
            $h->general()
                ->confirmation($conf);
        }

        if (empty($confs)) {
            $h->general()
                ->noConfirmation();
        }

        $h->general()
            ->travellers(array_filter(array_map('trim', preg_replace(["/(.{5,}?) {4,}.+/", "/(?:^|\n) {0,5}(?:MS|MR|MISS|MRS|MSTR) /", "/\s*\(.+/"], ['$1', '', ''],
                explode("\n", $this->re("/\n {0,5}Name(?: {4,}.*)?\n([\s\S]+?)\n {0,5}Accommodation\n/", $text))))))
            ->date(strtotime($this->re("/Issue Date: +(.+)\n/", $text)));

        $ttext = preg_replace("/ +Confirmation#.+/", '', $this->re("/\n( {0,5}Destination {4,}.*\n[\s\S]+?)\n {0,5}Check in +/", $text));
        $table = $this->createTable($ttext, $this->rowColumnPositions($this->inOneRow($ttext)));

        if (preg_match("/^\s*Hotel\n\s*(.+)/s", $table[1] ?? '', $m)) {
            $h->hotel()
                ->name($m[1]);
        }

        if (preg_match("/^\s*Category\n\s*(.+)/s", $table[2] ?? '', $m)) {
            $h->addRoom()
                ->setType($m[1]);
        }

        $ttext = $this->re("/\n( {0,5}Check in {4,}.*\n[\s\S]+?)\n {0,5}Address +/", $text);
        $table = $this->createTable($ttext, $this->rowColumnPositions($this->inOneRow($ttext)));

        if (preg_match("/^\s*Check in\n\s*(.+)/s", $table[0] ?? '', $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate(trim($m[1])));
        }

        if (preg_match("/^\s*Check out\n\s*(.+)/s", $table[1] ?? '', $m)) {
            $h->booked()
                ->checkOut($this->normalizeDate(trim($m[1])));
        }

        $ttext = $this->re("/\n( {0,5}Address {4,}.*\n[\s\S]+?)\n {0,5}Information\n/", $text);
        $table = $this->createTable($ttext, $this->rowColumnPositions($this->inOneRow($ttext)));

        if (preg_match("/^\s*Address\n\s*(.+?)(?:\s+Telephone\s*:\s*(.*))$/s", $table[0] ?? '', $m)) {
            $h->hotel()
                ->address(preg_replace('/\s*\n\s*/', ', ', trim($m[1])));

            if (!empty(trim($m[2]))) {
                $h->hotel()
                    ->phone(trim($m[2]));
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

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
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // 08-JAN-2023,  18:50
            '/^\s*(\d{1,2})\-(\w+)\-(\d{4})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
            // 08-JAN-2023
            '/^\s*(\d{1,2})\-(\w+)\-(\d{4})\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$1 $2 $3',
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
