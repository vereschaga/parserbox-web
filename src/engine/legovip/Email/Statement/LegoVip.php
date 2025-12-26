<?php

namespace AwardWallet\Engine\legovip\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class LegoVip extends \TAccountChecker
{
    public $mailFiles = "legovip/statements/it-104379012.eml";
    public $subjects = [
        '/Your Receipt from The LEGO/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@s.lego.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'LEGO Group')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Points will be credited to your loyalty'))}]")->length > 0
            && $this->http->XPath->query("//tr[contains(normalize-space(), 'Item ID') and contains(normalize-space(), 'Description') and contains(normalize-space(), 'Price')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]s\.lego\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Take survey')]/following::text()[contains(normalize-space(), 'Points will be credited to your loyalty')][1]/preceding::text()[normalize-space()][2]");

        if (!empty($name)) {
            $st->addProperty('Name', trim(str_replace(['(Ver)', '(Unv)'], '', $name), ','));
        }

        $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Take survey')]/following::text()[contains(normalize-space(), 'Points will be credited to your loyalty')][1]/preceding::text()[normalize-space()][3]", null, true, "/^([*\s]+\s*\d+)$/");

        if (!empty($number)) {
            $st->setNumber(str_replace("* **** ", "**", $number))->masked('left');
        }

        $login = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Take survey')]/following::text()[contains(normalize-space(), 'Points will be credited to your loyalty')][1]/preceding::text()[normalize-space()][1]");

        if (!empty($login)) {
            $st->setLogin($login);
        }

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
