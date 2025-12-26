<?php

namespace AwardWallet\Engine\tortilla\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Rewards extends \TAccountChecker
{
    public $mailFiles = "tortilla/statements/it-79984313.eml";
    public $subjects = [
        '/\w+\,\s*Your First Reward is HERE[!]\s*[?]$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tsg.pxsmail.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Corner Bakery Rewards')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Please enjoy this delicious welcome offer'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Expires'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tsg\.pxsmail\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->re("/(\D+)\,/", $parser->getSubject());

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to'))}]", null, true, "/{$this->opt($this->t('This email was sent to'))}\s*(\S+[@]\S+\.\S+)/");

        if (!empty($login)) {
            $st->setLogin($login);
        }

        $st->setExpirationDate(strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Expires'))}]", null, true, "/{$this->opt($this->t('Expires'))}\s*(\d+\-\d+\-\d{4})/")));

        $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('OFF'))}]", null, true, "/([\d\.]+)\s*{$this->opt($this->t('OFF'))}/");
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
