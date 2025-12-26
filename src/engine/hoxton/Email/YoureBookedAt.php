<?php

namespace AwardWallet\Engine\hoxton\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YoureBookedAt extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Your Stay - ' => ['Your Stay - '],
            'Total'        => ['Total', 'Total (incl. VAT)'],
        ],
    ];

    private $detectFrom = "@thehox.com";
    private $detectSubject = [
        // en
        'You’re booked at',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['from'], 'The Hoxton') === false
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
        if ($this->http->XPath->query("//*[{$this->contains(['@thehox.com'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your Stay - ']) && $this->http->XPath->query("//*[{$this->starts($dict['Your Stay - '])}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHtml($email);

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

    private function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Booking reference'))}])[1]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Stay - '))}]/preceding::text()[{$this->starts($this->t('Hey '))}][1]",
                null, true, "/{$this->opt($this->t('Hey '))}\s*([[:alpha:] \-]+)\s*$/"), false)
        ;

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Thanks for booking with us at'))}]",
                null, true, "/{$this->opt($this->t('Thanks for booking with us at'))}\s*([^.]+?)\./"))
            ->noAddress();

        $totalXpath = "//text()[{$this->starts($this->t('Your Stay - '))}]/ancestor::*[.//text()[normalize-space()][{$this->starts($this->t('Your Stay - '))}] and preceding-sibling::*[normalize-space()][not({$this->starts($this->t('Special Request'))})][2]/descendant::td[not(.//td)][normalize-space()][2][{$this->contains($this->t('Guest'))}]]";
        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate(
                $this->http->FindSingleNode($totalXpath . "/preceding-sibling::*[normalize-space()][not({$this->starts($this->t('Special Request'))})][1]/descendant::tr[count(*[normalize-space()]) = 2][1]/*[normalize-space()][1]")
            ))
            ->checkOut($this->normalizeDate(
                $this->http->FindSingleNode($totalXpath . "/preceding-sibling::*[normalize-space()][not({$this->starts($this->t('Special Request'))})][1]/descendant::tr[count(*[normalize-space()]) = 2][1]/*[normalize-space()][2]")
            ))
            ->guests(array_sum($this->http->FindNodes($totalXpath . "/preceding-sibling::*[normalize-space()][not({$this->starts($this->t('Special Request'))})][2]//td[not(.//td)][{$this->contains($this->t('Guest'))}]",
                null, "/^\s*(\d+)\s*{$this->opt($this->t('Guest'))}/")))
        ;
        // Rooms

        $types = $this->http->FindNodes($totalXpath . "/preceding-sibling::*[normalize-space()][not({$this->starts($this->t('Special Request'))})][2][//td[not(.//td)][normalize-space()][2][{$this->contains($this->t('Guest'))}]]//td[not(.//td)][normalize-space()][1]");

        foreach ($types as $type) {
            $room = $h->addRoom();
            $room->setType($type);
        }

        if (count($types) == 1) {
            $nights = 0;

            if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
                $nights = date_diff(
                    date_create('@' . strtotime('00:00', $h->getCheckOutDate())),
                    date_create('@' . strtotime('00:00', $h->getCheckInDate()))
                )->format('%a');
            }

            $rates = [];
            $rows = $this->http->XPath->query($totalXpath . "//tr[not(.//tr)]");

            foreach ($rows as $i => $r) {
                if ($i === 0) {
                    continue;
                }
                $name = $this->http->FindSingleNode("*[normalize-space()][1]", $r);
                $value = $this->http->FindSingleNode("*[normalize-space()][2]", $r);

                if (preg_match("/\b\d{4}\b/", $name)) {
                    $rates[] = $value;
                } else {
                    if (count($rates) == $nights) {
                        $room->setRates($rates);
                    }

                    break;
                }
            }
        }

        // Price
        $total = $this->getTotal($this->http->FindSingleNode("//td[{$this->eq($this->t('Total'))}]/following-sibling::td[normalize-space()][1]"));
        $h->price()
            ->total($total['amount'])
            ->currency($total['currency']);

        $tax = $this->getTotal($this->http->FindSingleNode("//td[{$this->eq($this->t('Taxes & Fees'))}]/following-sibling::td[normalize-space()][1]"));

        if (!empty($tax['amount'])) {
            $h->price()
                ->tax($tax['amount']);
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

    private function normalizeDate($date)
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // Saturday 29 July, 2023 after 2pm
            '/^\s*[[:alpha:]\-]+\s+(\d+)\s+([[:alpha:]]+)\s*,\s*(\d{4})\s+[[:alpha:]]+\s+(\d{1,2})\s*([ap]m)?\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4:00 $5',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date end = ' . print_r( $date, true));
        if (preg_match("/^\s*\d+\s+([[:alpha:]]+)\s+\d{4}\s*,\s*(\d{1,2}:\d{2}(?: +[ap]m)?)?\s*$/i", $date)) {
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
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

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
