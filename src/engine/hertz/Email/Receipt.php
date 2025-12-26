<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Receipt extends \TAccountChecker
{
    public $mailFiles = "hertz/it-129067443.eml";
    public $subjects = [
        'Hertz Receipt',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rentals.hertz.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'The Hertz Corporation')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Your Hertz Rental Car Receipt')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('We’re here to get you there'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]rentals.hertz.com$/', $from) > 0;
    }

    public function ParseCar(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='RES']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/"));

        $r->car()
            ->type($this->http->FindSingleNode("//text()[normalize-space()='VEHICLE:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('VEHICLE:'))}\s*(.+)/"));

        $r->pickup()
            ->location($this->http->FindSingleNode("//text()[normalize-space()='RENTED:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('RENTED:'))}\s*(.+)/"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='RENTAL:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('RENTAL:'))}\s*(.+)/")));

        $r->dropoff()
            ->location($this->http->FindSingleNode("//text()[normalize-space()='RETURNED:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('RETURNED:'))}\s*(.+)/"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='RETURN:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('RETURN:'))}\s*(.+)/")));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='TOTAL AMOUNT DUE']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('TOTAL AMOUNT DUE'))}\s*(.+)/");

        if (preg_match("/^(\D+)\s*([\d\.\,]+)$/", $price, $m)) {
            $r->price()
                ->total(PriceHelper::cost($m[2], ',', '.'))
                ->currency($m[1]);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseCar($email);

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

    private function normalizeDate($str)
    {
        $in = [
            // 12 / 13 / 21 09 : 25
            "#^(\d+)[\s\/]+(\d+)[\s\/]+(\d{2})\s*(\d+)[\s\:]+(\d+)$#i",
        ];
        $out = [
            "$2.$1.20$3, $4:$5",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
