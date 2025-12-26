<?php

namespace AwardWallet\Engine\walgreens\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OneTimeCode extends \TAccountChecker
{
    public $mailFiles = "walgreens/statements/it-214816661.eml";
    public $subjects = [
        'Your code: ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@ecs.walgreens.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Walgreen Co.')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('please verify your identity using the following code'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Walgreens Customer Care'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ecs\.walgreens\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) == true) {
            $st = $email->add()->statement();

            $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t(', please verify your identity using the following code'))}]/ancestor::tr[1]", null, true, "/^(\w+){$this->opt($this->t(', please verify your identity using the following code'))}/");

            if (!empty($name)) {
                $st->addProperty('Name', trim($name, ','));
            }
            $st->setNoBalance(true);
            $st->setMembership(true);

            $otc = $email->add()->oneTimeCode();
            $otc->setCode($this->http->FindSingleNode("//text()[{$this->contains($this->t(', please verify your identity using the following code'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/"));
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
        return count(self::$dictionary);
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
