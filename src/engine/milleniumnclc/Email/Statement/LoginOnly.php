<?php

namespace AwardWallet\Engine\milleniumnclc\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class LoginOnly extends \TAccountChecker
{
    public $mailFiles = "milleniumnclc/statements/it-74124844.eml, milleniumnclc/statements/it-74462941.eml, milleniumnclc/statements/it-74648495.eml";
    public $subjects = [
        '/^Happy Holidays from Millennium Hotels and Resorts \?$/',
        '/^Welcome to your My Millennium membership \?$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Millennium Hotels and Resorts' => ['Millennium Hotels and Resorts', 'Millennium & Copthorne Hotels'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@global.millenniumhotels.com') !== false) {
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Millennium Hotels and Resorts'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('This email was sent to'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]global\.millenniumhotels\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('This email was sent to'))}\s(\S+[@]\S+)\s/");
        $st->setLogin($login);

        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Membership No:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Membership No:'))}\s*(\d+)$/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hello'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Hello'))}\s*(\D+)\,$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $balanceText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Points:')]/ancestor::tr[1]");

        if (preg_match("/^{$this->opt($this->t('Total Points:'))}\s*(\d+)\s*{$this->opt($this->t('as of'))}\s*([\d\/]+)$/", $balanceText, $m)) {
            $st->setBalance($m[1]);
            $st->setBalanceDate(strtotime(str_replace("/", ".", $m[2])));
        } else {
            $st->setNoBalance(true);
        }

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
