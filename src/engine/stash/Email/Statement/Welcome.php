<?php

namespace AwardWallet\Engine\stash\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Welcome extends \TAccountChecker
{
    public $mailFiles = "stash/statements/it-76356031.eml";
    public $subjects = [
        '/^Welcome to Stash Hotel Rewards$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.stashrewards.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Stash Hotel Rewards')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('YOUR MEMBERSHIP ID:'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We keep it simple'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.stashrewards\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//text()[normalize-space()='YOUR MEMBERSHIP ID:']/following::text()[normalize-space()][1]");

        if (!empty($number)) {
            $st->setNumber($number)
                ->setLogin($number)
                ->setNoBalance(true);
        }

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Here we go')]", null, true, "/{$this->opt($this->t('Here we go'))}\s*(\D+)\!/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
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
