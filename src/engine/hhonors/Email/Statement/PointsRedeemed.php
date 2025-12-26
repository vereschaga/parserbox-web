<?php

namespace AwardWallet\Engine\hhonors\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PointsRedeemed extends \TAccountChecker
{
    public $mailFiles = "hhonors/statements/it-886806597.eml";
    public $subjects = [
        'Your Hilton Honors Points have been redeemed',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'This email was delivered to' => ['This email was delivered to', 'This email advertisement was delivered to'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@h6.hilton.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Hilton Reservations and Customer Care'))}]")->length === 0
            && $this->http->XPath->query("//img[contains(@alt, 'Hilton Honors')]")->length === 0) {
            return false;
        }

        //it-886806597.eml
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Honors #:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Points redemption is'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('This email was delivered to'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]h6\.hilton\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hilton Reservations and Customer Care'))}]/preceding::text()[{$this->starts($this->t('Honors #:'))}][1]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Honors #:'))}\s*([A-Z\d]{7,})$/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was delivered to'))}]/following::a[contains(normalize-space(), '@')][1]");
        $st->setLogin($login);

        $st->setNoBalance(true);

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
        return 0;
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
