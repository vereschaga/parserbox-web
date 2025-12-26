<?php

namespace AwardWallet\Engine\qantas\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Notification extends \TAccountChecker
{
    public $mailFiles = "qantas/statements/it-72515124.eml, qantas/statements/it-87349862.eml";
    public $subjects = [
        '/^Qantas Frequent Flyer \- Unusual Login Activity$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'We noticed some unusual login activity on your account' => [
                'We noticed some unusual login activity on your account',
                'Qantas Points',
            ],

            'YES, THIS WAS ME' => ['YES, THIS WAS ME', 'explore with Qantas', 'flying with Qantas', 'Flight Reward'],

            'Dear' => ['Dear', 'Hi'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@qantas.com.au') !== false
            || isset($headers['from']) && stripos($headers['from'], '@e.qantas.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Qantas Frequent Flyer')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We noticed some unusual login activity on your account'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('YES, THIS WAS ME'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e?\.?qantas\.com\.?a?u?$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->opt($this->t('Dear'))}\s+(\D+)\,$/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was originally sent to'))}]", null, true, "/{$this->opt($this->t('This email was originally sent to'))}\s*(\S+[@]\S+\.\S+)\./u");

        if (empty($login)) {
            $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Account'))}]/following::text()[{$this->starts($this->t('This email was originally sent to'))}][1]", null, true, "/{$this->opt($this->t('This email was originally sent to'))}\s*(\S+[@]\S+\.\S+)\./u");
        }

        if (!empty($login)) {
            $st->setLogin($login);
        }

        $st->setNoBalance(true);

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
