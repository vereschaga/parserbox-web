<?php

namespace AwardWallet\Engine\tortilla\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ScorePoints extends \TAccountChecker
{
    public $mailFiles = "tortilla/statements/it-84412142.eml";
    public $subjects = [
        '/Score BIG Points this Weekend/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'californiatortilla@c.pxsmail.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) === true) {
            if ($this->http->XPath->query("//text()[contains(normalize-space(), 'California Tortilla')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Burrito Elito'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Burrito Bucks Balance'))}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        if (preg_match('/californiatortilla[@.]c\.pxsmail\.com/', $from)) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Burrito Elito'))}]", null, true, "/{$this->opt($this->t('Burrito Elito'))}\s*[#]?[:]?\s*(\d+)/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $chargeDollar = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Burrito Bucks Balance:'))}]", null, true, "/{$this->opt($this->t('Burrito Bucks Balance:'))}\s*\S(\d+)/");
        $st->addProperty('ChargeDollars', $chargeDollar);

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Point Balance:'))}]", null, true, "/{$this->opt($this->t('Point Balance:'))}\s*(\d+)/");
        $st->setBalance($balance);

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
