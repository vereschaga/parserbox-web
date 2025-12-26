<?php

namespace AwardWallet\Engine\venetian\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Account extends \TAccountChecker
{
    public $mailFiles = "venetian/statements/it-108436631.eml";
    public $subjects = [
        'Welcome to the Suite Life',
        'Welcome to the Grazie Elite Tier',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Book Online' => ['Book Online', 'Sign-in'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.venetian.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Las Vegas Sands Corp')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Points to')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Book Online'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.venetian\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Book Online'))}]/preceding::text()[contains(normalize-space(), 'Tier Points')][1]/ancestor::tr[1]/descendant::text()[normalize-space()][1]");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Book Online'))}]/preceding::text()[contains(normalize-space(), 'Tier Points')][1]/following::text()[normalize-space()][2]", null, true, "/[#](\d+)/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $tier = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Book Online'))}]/preceding::text()[contains(normalize-space(), 'Grazie')][not(contains(normalize-space(), 'to'))][1]");

        if (!empty($tier)) {
            $st->addProperty('Level', $tier);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Book Online'))}]/preceding::text()[contains(normalize-space(), 'Points')][not(contains(normalize-space(), 'to'))][1]/preceding::text()[normalize-space()][1]", null, true, "/^([\d\,]+)$/");
        $st->setBalance(str_replace(',', '', $balance));

        $nextLevel = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Points to')]");

        if (!empty($nextLevel)) {
            $st->addProperty('ToNextLevel', $nextLevel);
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
