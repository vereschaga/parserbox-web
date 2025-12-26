<?php

namespace AwardWallet\Engine\tripact\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Hotel extends \TAccountChecker
{
    public $mailFiles = "tripact/it-66901199.eml, tripact/it-82636175.eml";

    private $lang = '';
    private $reFrom = ['tripactions.com'];
    private $reProvider = ['TripActions'];
    private $reSubject = [
        '/Confirmed - .+? \| \w+/u',
    ];
    private $reBody = [
        'en' => [
            ['Confirmation details', 'For price details see attached estimate'],
            ['Confirmation details', 'Cancellation Policy'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Hotel confirmation' => ['Hotel confirmation', 'Hotel confirmation:'],
            'Check-in'           => ['Check-in', 'Check-in:'],
            'Check-out'          => ['Check-out', 'Check-out:'],
            'Guest'              => ['Guest', 'Guest:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Star Hotel')]")->length === 0) {
            $this->parseHotel($email);
        } else {
            $this->parseHotel2($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $subject) {
            if (preg_match($subject, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();
        $confirmations = explode(',',
            $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel confirmation'))}]/ancestor::td[1]/following-sibling::td[1]"));

        foreach ($confirmations as $confirmation) {
            $h->general()->confirmation($confirmation,
                $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel confirmation'))}]"));
        }

        $h->general()->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Guest'))}]/ancestor::td[1]/following-sibling::td[1]"));

        $h->hotel()->name($this->http->FindSingleNode("//img[{$this->contains($this->t('icons/black/map.png'), '@src')}]/ancestor::tr[3]/preceding-sibling::tr[normalize-space()][1]",
            null, false, '/^[[:alpha:]\s\,]{10,}$/u'));

        $h->hotel()->address($this->http->FindSingleNode("//img[{$this->contains($this->t('icons/black/map.png'), '@src')}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]"));
        $h->hotel()->phone($this->http->FindSingleNode("//img[{$this->contains($this->t('icons/black/phone.png'), '@src')}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]"));

        $h->booked()->checkIn2($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check in'))}]/ancestor::td[1]/following-sibling::td[1]")));
        $h->booked()->checkOut2($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check out'))}]/ancestor::td[1]/following-sibling::td[1]")));

        $h->booked()->rooms($this->http->FindSingleNode("//text()[{$this->contains($this->t('Number of rooms'))}]/ancestor::td[1]/following-sibling::td[last()]"));

        $r = $h->addRoom();
        $r->setType($this->http->FindSingleNode("//text()[{$this->contains($this->t('Room type'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]"));
        $description = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Room type'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][2]");

        if (!empty($description)) {
            $r->setDescription($description);
        }

        // $153.06
        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Subtotal'))}]/ancestor::td[1]/following-sibling::td[last()]");

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', $price, $matches)) {
            $h->price()->cost($this->normalizeAmount($matches['amount']));
        }
        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes and fees'))}]/ancestor::td[1]/following-sibling::td[last()]");

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', $price, $matches)) {
            $h->price()->tax($this->normalizeAmount($matches['amount']));
        }
        // $370.78 USD
        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::td[1]/following-sibling::td[last()]");

        if (preg_match('/^\D+\s*(?<amount>\d[,.\'\d]*)\s*(?<currency>\D+)/', $price, $matches)) {
            $h->price()
                ->currency($this->normalizeCurrency($matches['currency']))
                ->total($this->normalizeAmount($matches['amount']));
        }
    }

    private function parseHotel2(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel confirmation'))}]/following::text()[normalize-space()][1]"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest'))}]/following::text()[normalize-space()][1]"), true);

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()][1]");
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()][2]");
        $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Subtotal'))}]/following::text()[normalize-space()][1]");
        $taxes = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Resort fee'))}]/following::text()[normalize-space()][1]");
        $fee = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes'))}]/following::text()[normalize-space()][1]");

        if (!empty($total) && !empty($currency)) {
            $h->price()
                ->total(cost($total))
                ->currency($currency);
        }

        if (!empty($cost)) {
            $h->price()
                ->cost(cost($cost));
        }

        if (!empty($taxes)) {
            $h->price()
                ->tax(cost($taxes));
        }

        if (!empty($fee)) {
            $h->price()
                ->fee('Resort fee', cost($fee));
        }

        $phone = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Star Hotel')]/preceding::div[1]");
        $address = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Star Hotel')]/preceding::div[2]");

        if (stripos($address, 'Check-in') !== false) {
            $address = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Star Hotel')]/preceding::div[1]");
            $phone = '';
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Star Hotel')]/preceding::div[2]/preceding::span[1]"))
            ->address($address);

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of rooms'))}]/following::text()[normalize-space()][1]"))
            ->checkIn(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/following::text()[normalize-space()][1]"))))
            ->checkOut(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/following::text()[normalize-space()][1]"))));

        $roomNode = $this->http->FindNodes("//text()[{$this->eq($this->t('Your room'))}]/ancestor::tr[1]/descendant::text()[string-length()>3][not(contains(normalize-space(), 'Your room'))]");

        if (count($roomNode) == 2) {
            $room = $h->addRoom();

            $room->setType($roomNode[0]);
            $room->setDescription($roomNode[1]);
        }
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
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
        $field = (array) $this->t($field);

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'SGD' => ['SG$'],
            'ZAR' => ['R'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function normalizeDate($str)
    {
        $this->logger->error($str);
        $in = [
            // Wednesday September 30, 2020 at 3:00PM
            '#^(.+?\d+, \d{4}) at (\d+.+?)$#',
        ];
        $out = [
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
