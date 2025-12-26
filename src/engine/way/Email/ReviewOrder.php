<?php

namespace AwardWallet\Engine\way\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReviewOrder extends \TAccountChecker
{
    public $mailFiles = "way/it-1.eml, way/it-2.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Confirmation Number:' => ['Confirmation Number:', 'Cancelled Order Confirmation Number:'],
            'Parking Lot Name'     => ['Parking Lot Name', 'Parking Name'],
            'Parking Lot Address'  => ['Parking Lot Address', 'Address'],
            'Parking Lot Type'     => ['Parking Type', 'Parking Lot Type'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Way App'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Parking Lot Name'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Parking Lot Type'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Parking Lot Address'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]way\.com$/', $from) > 0;
    }

    public function ParseParking(Email $email)
    {
        $p = $email->add()->parking();

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\D+)/u");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\D+)\s+\,\s+/u");
        }

        $p->general()
            ->traveller($traveller)
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^([A-Z\d]+)$/"));

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Order Cancellation Notification'))}]")->length > 0) {
            $p->general()
                ->cancelled();
        }

        $p->place()
            ->location($this->http->FindSingleNode("//text()[{$this->eq($this->t('Parking Lot Name'))}]/ancestor::tr[1]/descendant::td[2]"));
        $addressText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Parking Lot Address'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<address>.+)\s+{$this->opt($this->t('Phone'))}[\s\:]*\D*(?<phone>[+][\s\d\-]+)/", $addressText, $m)) {
            $p->place()
                ->address($m['address'])
                ->phone($m['phone']);
        }

        $p->booked()
            ->start(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check-In']/ancestor::tr[1]/descendant::td[2]")))
            ->end(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check-Out']/ancestor::tr[1]/descendant::td[2]")));

        $priceText = $this->http->FindSingleNode("//text()[normalize-space()='Paid']/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<currency>\S)\s*(?<total>[\d\.\,]+)$/", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $p->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes+Fees']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\S\s*([\d\.\,]+)/");

            if (!empty($tax)) {
                $p->price()
                    ->tax(PriceHelper::parse($tax, $currency));
            }

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Sub Total']/ancestor::tr[1]/descendant::td[2]", null, true, "/^([\d\.\,]+)/");

            if (!empty($cost)) {
                $p->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $discount = $this->http->FindSingleNode("//text()[normalize-space()='Promocode Discount']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\-\S\s*([\d\.\,]+)/");

            if (!empty($discount)) {
                $p->price()
                    ->discount(PriceHelper::parse($discount, $currency));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseParking($email);

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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
