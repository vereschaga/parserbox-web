<?php

namespace AwardWallet\Engine\legovip\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "legovip/statements/it-1.eml, legovip/statements/it-222714429.eml, legovip/statements/it-642703923.eml";
    public $subjects = [
        //        'two-factor sign-in code:',
        //        'Account two-factor code:',
        'code:',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Two-factor sign-in code' => [
                'Two-factor sign-in code',
                'Here’s your code!',
                'The code will work for the next 6 minutes.',
            ],
            'To complete your login, please use the following code:' => [
                'To complete your login, please use the following code:',
                'It’s great to have you back! Here’s the code you’ll need to log in:',
                "Here's the code you'll need to verify your account:",
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.identity.lego.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'LEGO')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Two-factor sign-in code'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('To complete your login, please use the following code:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.identity\.lego\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $c = $email->add()->oneTimeCode();

        $code = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Two-factor sign-in code'))}]/preceding::text()[normalize-space()][1]", null, true, "/^(\d{6})$/");

        if (empty($code)) {
            $code = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Two-factor sign-in code'))}]/preceding::text()[normalize-space()][1]", null, true, "/^(\d{6})$/");
        }
        $c->setCode($code);

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]", null, true, "/{$this->opt($this->t('Hi '))}(\S*[@]\S+\.\S+)\,/");

        if (!empty($login)) {
            $st = $email->add()->statement();
            $st->setLogin($login);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
