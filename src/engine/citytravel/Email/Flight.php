<?php

namespace AwardWallet\Engine\citytravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "citytravel/it-729955941.eml, citytravel/it-872407325.eml, citytravel/it-872478037.eml";
    public $lang = 'ru';

    public static $dictionary = [
        "ru" => [
            'Багаж не включен' => ['Багаж не включен', '1 место багажа'],
        ],

        "en" => [
            //'Во вложении к письму Вы найдете электронные билеты' => '',
            'Выберите удобный способ оплаты' => 'Choose a convenient payment method',
            'Пассажиры'                      => 'Passengers',
            'Авиабилеты'                     => 'Document number',

            'Ваш заказ'   => 'Your order',
            'Имя Фамилия' => 'First Name Last Name',
            //'на сумму' => '',
            'Оплатить'         => 'Pay',
            'успешно оформлен' => 'has been successfully booked',
            'Вылет'            => 'Departure flight',
            'Посадка'          => 'Return flight',
            'Багаж не включен' => 'Baggage is not included',
            //'место багажа' => '',
            'ч'                  => 'h',
            'мин'                => 'min',
            'в пути'             => 'flight time',
            'Ожидание пересадки' => 'Waiting for a transfer',
        ],
    ];

    public $detectLang = [
        'ru' => ['Ваш заказ', 'Имя Фамилия'],
        'en' => ['Your order', 'First Name Last Name'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'City.Travel')]")->length === 0) {
            return false;
        }

        if (($this->http->XPath->query("//text()[{$this->contains($this->t('Во вложении к письму Вы найдете электронные билеты'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('Выберите удобный способ оплаты'))}]")->length > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Пассажиры'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Авиабилеты'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]city\.travel$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->Flight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Ваш заказ'))}]/ancestor::tr[1]", null, true, "/[№]*\s*(\d{5,})/"));

        $travellerNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Имя Фамилия'))}]/ancestor::tr[1]/following-sibling::tr");

        foreach ($travellerNodes as $travellerRoot) {
            $traveller = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $travellerRoot);
            $ticket = $this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $travellerRoot, true, "/^([A-Z\d]+)$/");

            if (!empty($ticket)) {
                $f->addTicketNumber($ticket, false, $traveller);
            }

            $f->general()
                ->traveller($traveller);
        }

        $price = $this->http->FindSingleNode("//text()[{$this->contains($this->t('на сумму'))}]", null, true, "/{$this->opt($this->t('на сумму'))}\s+(.+)\s+{$this->opt($this->t('успешно оформлен'))}/");

        if (empty($price)) {
            $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Выберите удобный способ оплаты'))}]/following::tr[1]/descendant::text()[{$this->eq($this->t('Оплатить'))}]/preceding::text()[normalize-space()][1]", null, true, "/^(\S+\s+[\d\.\,\']+)$/");
        }

        if (preg_match("/^(?<currency>\D{1,3})\.?\s+(?<total>[\d\s\.\,\']+)$/u", $price, $m)
        || preg_match("/^(?<total>[\d\s\.\,\']+)\s+(?<currency>\D{1,3})\.?$/u", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $nodes = $this->http->XPath->query("//img[contains(@src, 'plane-up')]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $depInfo = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<depCode>[A-Z]{3})\n(?<depName>.+)\n(?<depTime>\d+\:\d+)\s*(?<depDate>\d+\s+\w+\s+\d{4})$/su", $depInfo, $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->name(str_replace("\n", " ", $m['depName']))
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']));
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./following::tr[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<arrCode>[A-Z]{3})\n(?<arrName>.+)\n(?<arrTime>\d+\:\d+)\s*(?<arrDate>\d+\s+\w+\s+\d{4})$/su", $arrInfo, $m)) {
                $s->arrival()
                    ->code($m['arrCode'])
                    ->name(str_replace("\n", " ", $m['arrName']))
                    ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']));
            }

            $flightInfo = implode("\n", $this->http->FindNodes("./preceding::tr[1]/descendant::text()[normalize-space()][not({$this->contains($this->t('Ожидание пересадки'))})]", $root));

            if (stripos($flightInfo, $this->t('Вылет')) !== false
                || stripos($flightInfo, $this->t('Посадка')) !== false
                || empty($flightInfo)) {
                $flightInfo = implode("\n", $this->http->FindNodes("./preceding::td[1]/descendant::text()[normalize-space()]", $root));
            }

            if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\-(?<fNumber>\d{1,4})\n(?<operator>.+)\n(?<aircfart>.+)?\n*(?:{$this->opt($this->t('Багаж не включен'))}|(?:\d+\s*{$this->opt($this->t('место багажа'))}))/u", $flightInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber'])
                    ->operator($m['operator']);

                if (isset($m['aircfart'])) {
                    $s->extra()
                        ->aircraft($m['aircfart']);
                }
            }

            $duration = $this->http->FindSingleNode("./preceding::tr[normalize-space()][2]", $root, true, "/^(?<duration>(?:\d+\s*{$this->opt($this->t('ч'))})\s*(?:\d+\s*{$this->opt($this->t('мин'))}))\s*{$this->opt($this->t('в пути'))}$/");

            if (!empty($duration) && $this->http->XPath->query("./ancestor::tr[2]/following::text()[normalize-space()][1][{$this->contains($this->t('Ожидание пересадки'))}]", $root)->length == 0) {
                $s->extra()
                    ->duration($duration);
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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$date in = ' . print_r($str, true));

        $in = [
            // mercredi, 8 mars 2023
            "/^\s*[-[:alpha:]]+\s*,\s*(\d{1,2})\s+(?:de\s+)?([[:alpha:]]+)(?:\s+de)?\s+(\d{4})\s*$/iu",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'           => 'EUR',
            'US dollars'  => 'USD',
            '£'           => 'GBP',
            '₹'           => 'INR',
            'руб'         => 'RUB',
        ];

        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return $s;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
