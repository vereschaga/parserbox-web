<?php

namespace AwardWallet\Engine\sj\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WelcomeTo extends \TAccountChecker
{
    public $mailFiles = "sj/it-451669807.eml";
    public $subjects = [
        'SJ Prio-medlem, ',
    ];

    public $lang = 'sv';

    public static $dictionary = [
        "sv" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@kommunikation.sj.se') !== false) {
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Välkommen till SJ Prio'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Medlemsnummer'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Min sida'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]kommunikation\.sj\.se$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//img[contains(@src, 'avatar')]/ancestor::tr[1]/descendant::text()[normalize-space()]");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[normalize-space()='Medlemsnummer']/ancestor::tr[1]/descendant::td[2]", null, true, "/^([\d\s]{7,})$/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $tier = $this->http->FindSingleNode("//text()[normalize-space()='Medlemsnivå']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($tier)) {
            $st->addProperty('Tier', $tier);
        }

        $balance = $this->http->FindSingleNode("//text()[normalize-space()='Poäng att använda']/ancestor::tr[1]/descendant::td[2]");
        $st->setBalance(str_replace(' ', '', $balance));

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
