<?php

namespace AwardWallet\Engine\genghisgrill\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Membership extends \TAccountChecker
{
    public $mailFiles = "genghisgrill/statements/it-77084025.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Pickup or Genghis Grill Delivery Services' => [
                'Pickup or Genghis Grill Delivery Services',
                'Genghis Grill respects your privacy',
            ],

            'GenghisGrill.com' => [
                'GenghisGrill.com',
                'Genghis Grill',
            ],

            'Code will work on' => [
                'Code will work on',
                'We use safe',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Pickup or Genghis Grill Delivery Services'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('GenghisGrill.com'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Code will work on'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]genghisgrill\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if ($this->detectEmailByBody($parser) == true) {
            $st->setMembership(true);
        }

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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
