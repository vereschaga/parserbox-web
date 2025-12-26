<?php

namespace AwardWallet\Engine\plum\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class StatementData extends \TAccountChecker
{
    public $mailFiles = "plum/statements/it-78679745.eml, plum/statements/it-78679840.eml, plum/statements/it-78680017.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Indigo Books & Music Inc')]")->length > 0
            && (
                $this->http->XPath->query("//td[{$this->contains($this->t('plum® number:'))}]")->length > 0
                || $this->http->XPath->query("//td[{$this->contains($this->t('You are receiving this email at'))}]")->length > 0
            )
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Please do not reply to this email'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.indigo\.ca$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//tr[starts-with(normalize-space(), 'plum® number:')]", null, true, "/{$this->opt($this->t('plum® number:'))}\s*(\d+)$/u");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'your plum number')]/following::text()[normalize-space()][1]");
        }

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $login = $this->http->FindSingleNode("//tr[starts-with(normalize-space(), 'You are receiving this email at')]", null, true, "/{$this->opt($this->t('You are receiving this email at'))}\s*(\S+[@]\S+\.\S+)\s*/");

        if (!empty($login)) {
            $st->setLogin($login);
        }

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\w+)\,/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
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
