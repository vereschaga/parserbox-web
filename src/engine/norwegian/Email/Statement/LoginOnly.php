<?php

namespace AwardWallet\Engine\norwegian\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class LoginOnly extends \TAccountChecker
{
    public $mailFiles = "norwegian/statements/it-70097661.eml, norwegian/statements/it-76605126.eml";
    public $lang = '';

    public $langDetect = [
        'en' => ['This email was sent to'],
        'no' => ['Denne e-posten ble sendt til'],
    ];

    public static $dictionary = [
        "en" => [
            'Reward Number:' => ['Reward Number:', 'Reward Number', 'Your Reward Number is:'],
        ],
        "no" => [
            'This email was sent to' => 'Denne e-posten ble sendt til',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->AssignLang() == true) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Norwegian Reward')]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('This email was sent to'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Current balance:'))}]")->count() == 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('registered flights'))}]")->count() == 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Reward Number:'))}]")->count() == 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Hi,'))}]")->count() == 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.norwegianreward\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();
        $st = $email->add()->statement();
        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('This email was sent to'))}]/ancestor::tr[1]", null, true, "/(\S+[@]\S+\.\S+)\./");

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

    private function AssignLang()
    {
        if (isset($this->langDetect)) {
            foreach ($this->langDetect as $lang => $reBody) {
                foreach ($reBody as $word) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$word}')]")->length > 0
                    ) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }
}
