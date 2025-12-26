<?php

namespace AwardWallet\Engine\way\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourOrder extends \TAccountChecker
{
    public $mailFiles = "way/it-293917665.eml";
    public $subjects = [
        'Way.com - Review your order confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@way.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('support@way.com'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Order confirmation'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Please login/sign up to add license plate details from Order Summary'))}]")->length > 0
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

        $p->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\D+)\,/u"))
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Order confirmation']/following::text()[starts-with(normalize-space(), 'Confirmation No. #')][1]",
                null, true, "/{$this->opt($this->t('Confirmation No. #'))}\s*([A-Z\d]+)/"));

        $p->place()
            ->address($this->http->FindSingleNode("//text()[normalize-space()='View Directions']/preceding::text()[normalize-space()][1]"))
            ->location($this->http->FindSingleNode("//text()[normalize-space()='View Directions']/preceding::text()[normalize-space()][2]"));

        $p->booked()
            ->start(strtotime($this->http->FindSingleNode("//text()[normalize-space()='View Directions']/following::text()[normalize-space()='Check-In']/following::text()[normalize-space()][1]/ancestor::td[1]",
                null, true, "/{$this->opt($this->t('Check-In'))}\s*(.+)/su")))
            ->end(strtotime($this->http->FindSingleNode("//text()[normalize-space()='View Directions']/following::text()[normalize-space()='Check-Out']/following::text()[normalize-space()][1]/ancestor::td[1]",
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

        $phone = $this->http->FindSingleNode("//img[contains(@alt, 'phone')]/@alt/following::text()[normalize-space()][1]", null, true, "/^([+][\s\d\-\(\)]+)$/");

        if (!empty($phone)) {
            $p->setPhone($phone);
        }

        $earned = $this->http->FindSingleNode("//text()[normalize-space()='Earned Way Bucks']/following::text()[normalize-space()][1]", null, true, "/^([\d\.\,]+)$/");

        if (!empty($earned)) {
            $p->setEarnedAwards($earned);
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
