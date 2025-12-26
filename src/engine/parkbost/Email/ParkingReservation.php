<?php

namespace AwardWallet\Engine\parkbost\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers laxparking/YourParking, houston/ParkingHtml (in favor of laxparking/YourParking)

class ParkingReservation extends \TAccountChecker
{
    public $mailFiles = "parkbost/it-226233622.eml";
    public $subjects = [
        'Boston Logan Parking - Reservation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Parking garage:' => ['Parking garage:', 'Parking type:'],
            'endName'         => ['A summary', 'is coming up'],
            'startName'       => ['Thank you for booking your parking at', 'Your parking booking at'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@parking.massport.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Boston Logan International Airport'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('startName'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Booking'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Payment Details'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]parking\.massport\.com$/', $from) > 0;
    }

    public function ParseParking(Email $email)
    {
        $p = $email->add()->parking();

        $p->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^([A-Z\d]+)$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\s*(\D+)\,/"));

        $address = $this->http->FindSingleNode("//text()[{$this->starts($this->t('startName'))}]", null, true, "/{$this->opt($this->t('startName'))}\s*(.+)\s*{$this->opt($this->t('endName'))}/");

        $p->setLocation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Parking garage:'))}]/ancestor::tr[1]/descendant::td[2]"))
            ->setAddress($address)
            ->setPlate($this->http->FindSingleNode("//text()[{$this->eq($this->t('License plate:'))}]/ancestor::tr[1]/descendant::td[2]"))
            ->setStartDate($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Entry:'))}]/ancestor::tr[1]/descendant::td[2]")))
            ->setEndDate($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Exit:'))}]/ancestor::tr[1]/descendant::td[2]")));

        $priceText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total:'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^\s*(?<currency>\D)\s*(?<total>[\d\.\,]+)\s*$/", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $p->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['total'], $currency));
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
        $in = [
            "#^(\d+)\/(\d+)\/(\d{4})\s*at\s*([\d\:]+\s*A?P?M)$#u", //09/14/2022 at 07:00 AM
        ];
        $out = [
            "$2.$1.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
