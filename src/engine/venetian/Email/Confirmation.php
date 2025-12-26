<?php

namespace AwardWallet\Engine\venetian\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "venetian/it-59657435.eml";

    private $lang = '';
    private $reFrom = ['venetian.com'];
    private $reProvider = ['The Venetian'];
    private $reSubject = [
        'Your Reservation Confirmation #',
    ];
    private $reBody = [
        'en' => [
            'we can’t wait to see you at The Venetian',
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
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
        $h->general()->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation Number'))}]/ancestor::tr[1]/following-sibling::tr[1]"));

        $h->hotel()->name($this->http->FindSingleNode("//text()[{$this->contains($this->t('we can’t wait to see you at'))}]/ancestor::td[1]", null, false,
            "/{$this->opt('we can’t wait to see you at')}(.+?)\./"));

        $h->hotel()->address($this->http->FindSingleNode("//span[{$this->eq($this->t('All Rights Reserved.'))}]/following-sibling::a"));

        $h->hotel()->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservations'))}]/ancestor::tr[1]/following-sibling::tr//a[contains(@href,'tel:')]"));

        $h->general()->traveller($this->http->FindSingleNode("//text()[{$this->contains($this->t('Guest name'))}]/ancestor::td[1]/following-sibling::td[last()]"));

        $h->booked()->checkIn2($this->http->FindSingleNode("//text()[{$this->contains($this->t('Check in'))}]/ancestor::td[1]/following-sibling::td[last()]"));
        $h->booked()->checkOut2($this->http->FindSingleNode("//text()[{$this->contains($this->t('Check out'))}]/ancestor::td[1]/following-sibling::td[last()]"));

        $h->booked()->guests($this->http->FindSingleNode("//text()[{$this->contains($this->t('Guests'))}]/ancestor::td[1]/following-sibling::td[last()]"));

        $r = $h->addRoom();
        $r->setType($this->http->FindSingleNode("//text()[{$this->contains($this->t('Suite type'))}]/ancestor::td[1]/following-sibling::td[last()]"));

        // $153.06
        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room total'))}]/ancestor::td[1]/following-sibling::td[last()]");

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
            $h->price()->cost($this->normalizeAmount($matches['amount']));
        }
        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Tax total'))}]/ancestor::td[1]/following-sibling::td[last()]");

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
            $h->price()->tax($this->normalizeAmount($matches['amount']));
        }
        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Grand total'))}]/ancestor::td[1]/following-sibling::td[last()]");

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
            $h->price()
                ->currency($this->normalizeCurrency($matches['currency']))
                ->total($this->normalizeAmount($matches['amount']));
        }
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $value) {
            $this->logger->error($this->http->XPath->query("//text()[{$this->contains($value)}]")->length);

            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
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
}
