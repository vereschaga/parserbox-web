<?php

namespace AwardWallet\Engine\lowes\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "lowes/statements/it-80193077.eml, lowes/statements/it-80209390.eml, lowes/statements/it-87842968.eml";

    public static $dictionary = [
        "en" => [
            'Forgot Your Password? Reset It Now' => ['Forgot Your Password? Reset It Now', 'By signing up for a MyLowe\'s account'],
            'RESET PASSWORD'                     => ['RESET PASSWORD', 'SIGN IN'],
        ],
    ];

    private $subjects = [
        'en' => ["Password Reset: You're Almost Done"],
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]lowes\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".lowes.com/") or contains(@href,"click.e.lowes.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $login = $this->http->FindSingleNode("descendant::text()[normalize-space()='This email is intended for']/following::text()[normalize-space()][1]", null, true, '/^(\S+@\S+\.\w+)[\s.]*$/');

        if ($login) {
            $st->setLogin($login);
        }

        $name = $this->re("/^{$this->opt($this->t('Hello'))}\s*(\w+)\,/", $parser->getSubject());

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        if ($login) {
            $st->setNoBalance(true);
        } elseif ($this->isMembership()) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        return $this->http->XPath->query("//*[{$this->contains($this->t('Forgot Your Password? Reset It Now'))}]")->length > 0
            && $this->http->XPath->query("//a[{$this->eq($this->t('RESET PASSWORD'))}]")->length > 0
            || $this->http->XPath->query("//tr//a[contains(@href,\".lowes.com/\") or contains(@href,\"click.e.lowes.com\")][ descendant::img[normalize-space(@alt)=\"MyLowe's Login\"] ]")->length === 1;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
