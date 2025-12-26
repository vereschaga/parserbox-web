<?php

namespace AwardWallet\Engine\way\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourOrder2 extends \TAccountChecker
{
    public $mailFiles = "way/it-290047658.eml";
    public $lang = 'en';

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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your booking is confirmed with Way'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Show code at parking lot'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Please login/ sign up to add license plate details from Order Summary'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('View Reservation'))}]")->length > 0;
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

        $date = $this->http->FindSingleNode("//text()[normalize-space()='Order Date']/following::text()[normalize-space()][1]", null, true, "/^(.+A?P?M)/");

        if (!empty($date)) {
            $p->general()
                ->date(strtotime($date));
        }

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\D+)\,/u");

        if (!empty($traveller)) {
            $p->general()
                ->traveller($traveller);
        }
        $p->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation No. #')]",
                null, true, "/{$this->opt($this->t('Confirmation No. #'))}\s*([A-Z\d]+)/"));

        $p->place()
            ->address($this->http->FindSingleNode("//text()[normalize-space()='Parking Address']/ancestor::tr[1]/descendant::td[2]"))
            ->location($this->http->FindSingleNode("//text()[normalize-space()='Show code at parking lot']/preceding::text()[normalize-space()][1]"));

        $p->booked()
            ->start(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check-In']/following::text()[normalize-space()][1]/ancestor::td[1]",
                null, true, "/{$this->opt($this->t('Check-In'))}\s*(.+)/su")))
            ->end(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check-Out']/following::text()[normalize-space()][1]/ancestor::td[1]",
                null, true, "/{$this->opt($this->t('Check-Out'))}\s*(.+)/su")));

        $priceText = $this->http->FindSingleNode("//text()[normalize-space()='Total']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D)(?<total>[\d\.\,]+)$/", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $p->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes + Fees']/following::text()[normalize-space()][1]", null, true, "/^\D+([\d\.\,]+)/");

            if (!empty($tax)) {
                $p->price()
                    ->tax(PriceHelper::parse($tax, $currency));
            }

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Subtotal']/following::text()[normalize-space()][1]", null, true, "/^\D+([\d\.\,]+)/");

            if (!empty($cost)) {
                $p->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }
        }

        $phone = $this->http->FindSingleNode("//text()[normalize-space()='Parking Contact']/ancestor::tr[1]/descendant::td[2]", null, true, "/^([+][\s\d\-\(\)]+)$/");

        if (!empty($phone)) {
            $p->setPhone($phone);
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
