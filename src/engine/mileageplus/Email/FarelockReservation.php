<?php

namespace AwardWallet\Engine\mileageplus\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FarelockReservation extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'TOTAL (PAID TODAY)' => ['TOTAL (PAID TODAY)', 'Total (Paid Today)'],
            'TOTAL (PAY LATER)'  => ['TOTAL (PAY LATER)', 'Total (Pay Later)'],
        ],
    ];

    private $detectFrom = "notifications@united.com";
    private $detectSubject = [
        // en:  14-Day Farelock Reservation
        '-Day Farelock Reservation',
    ];
    private $detectBody = [
        'en' => [
            '-Day Farelock Reservation',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]united\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
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
        if (
            $this->http->XPath->query("//img[{$this->contains(['media.united.com'], '@src')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['United Airlines, Inc.'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('CONFIRMATION NUMBER:'))}]",
                null, true, "/:\s*([A-Z\d]{5,7})\s*$/"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Email Address:'))}]/preceding::text()[normalize-space()][1]"))
        ;

        // Segments
        $xpath = "//text()[{$this->eq($this->t('Duration'))}]/ancestor::tr[count(*[normalize-space()]) = 3][1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flightInfo = implode("\n", $this->http->FindNodes(".//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Flight'))}]]/following-sibling::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5})\n(?<duration>(?: ?\d+ ?[hm])+\n)?(?<cabin>[^\n\d]+)\s*\(\s*(?<code>[A-Z]{1,2})\s*\)\s*$/", $flightInfo, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                $s->extra()
                    // ->duration($m['duration'])// it's not duration, it's difference between departure and arrival time without time zone
                    ->cabin($m['cabin'])
                    ->bookingCode($m['code'])
                ;
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][1][count(*[normalize-space()]) = 3][*[normalize-space()][2][normalize-space()='→']]/*[normalize-space()][1]", $root))
                ->name($this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][2][count(*[normalize-space()]) = 2]/*[normalize-space()][1]", $root))
                ->date(strtotime($this->http->FindSingleNode("*[1]", $root)));

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][1][count(*[normalize-space()]) = 3][*[normalize-space()][2][normalize-space()='→']]/*[normalize-space()][3]", $root))
                ->name($this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][2][count(*[normalize-space()]) = 2]/*[normalize-space()][2]", $root))
                ->date(strtotime($this->http->FindSingleNode("*[2]", $root)));
        }

        // Price
        $fare = $this->getTotal($this->http->FindSingleNode("//td[not(.//td)][{$this->eq($this->t('Fare'))}]/following-sibling::td[normalize-space()][1]"));
        $f->price()
            ->cost($fare['amount'])
            ->currency($fare['currency'])
        ;
        $tax = $this->getTotal($this->http->FindSingleNode("//td[not(.//td)][{$this->eq($this->t('Taxes and fees'))}]/following-sibling::td[normalize-space()][1]"));
        $f->price()
            ->tax($tax['amount']);

        $totals = $this->http->FindNodes("//td[not(.//td)][.//text()[{$this->eq($this->t('TOTAL (PAID TODAY)'))}]][.//text()[{$this->eq($this->t('TOTAL (PAY LATER)'))}]]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]");
        $total = 0.0;

        foreach ($totals as $v) {
            $total += $this->getTotal($v)['amount'];
        }

        if (!empty($total)) {
            $f->price()
                ->total($total);
        } else {
            $f->price()
                ->total(null);
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

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            //            // Apr 09
            //            '/^\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1:43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$2 $1 %year%',
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        // $this->logger->debug('date end = ' . print_r( $date, true));

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

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount'], $m['currency']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
