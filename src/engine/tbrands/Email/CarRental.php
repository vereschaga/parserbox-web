<?php

namespace AwardWallet\Engine\tbrands\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarRental extends \TAccountChecker
{
    public $mailFiles = "tbrands/it-772162251.eml";
    public $subjects = [
        'Cars by Travelbrands Car Rental Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@myautorental.ca') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Thank you for choosing Cars by Travelbrands')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Please reference the voucher number'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Rental Details:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]myautorental\.ca$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->CarParse($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function CarParse(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your voucher number is:')]", null, true, "/{$this->opt($this->t('Your voucher number is:'))}\s*(\d{4,})/"));

        $rentalInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Rental Details:')]");

        if (preg_match("/Rental Details:\s+(?<traveller>.+)\s+with\s+(?<company>.+)\s+in the\s+(?<carType>.+)\s+for pick up\s+(?<pickUpLocation>.+)\s+(?<pickUpDate>\d+\s*\w+\s*\d{4})\s+\/\s+(?<pickUpTime>\d+\:\d+)\s+and drop off\s+(?<dropOffLocation>.+)\s+(?<dropOffDate>\d+\s+\w+\s+\d{4})\s+\/\s+(?<dropOffTime>\d+\:\d+)\./", $rentalInfo, $m)) {
            $r->car()
                ->type($m['carType']);

            $r->general()
                ->traveller(preg_replace("/^(Mr|Ms|Mrs)\./", "", $m['traveller']));

            $r->setCompany($m['company']);

            $r->pickup()
                ->location($m['pickUpLocation'])
                ->date(strtotime($m['pickUpDate'] . ', ' . $m['pickUpTime']));

            $r->dropoff()
                ->location($m['dropOffLocation'])
                ->date(strtotime($m['dropOffDate'] . ', ' . $m['dropOffTime']));
        }

        $price = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'rental is')]", null, true, "/{$this->opt($this->t('rental is'))}\s*(\D{1,3}\s*[\d\.\,\']+)\./");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,\']+)$/u", $price, $m)) {
            $r->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
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
}
