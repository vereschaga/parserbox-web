<?php

namespace AwardWallet\Engine\norwegian\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WithoutBalance extends \TAccountChecker
{
    public $mailFiles = "norwegian/statements/it-70366194.eml, norwegian/statements/it-70369664.eml, norwegian/statements/it-76590017.eml, norwegian/statements/it-76875351.eml, norwegian/statements/it-77098036.eml, norwegian/statements/it-77310899.eml";
    public $lang = 'en';

    public $langDetect = [
        'fr' => ['Numéro Reward'],
        'en' => ['Your Reward Number is:', 'Reward Number'],
        'es' => ['Tu Número de Reward es:'],
        'da' => ['Betingelser'],
    ];

    public static $dictionary = [
        "en" => [
            'Your Reward Number is:' => [
                'Your Reward Number is:',
                'You can now start earning CashPoints with your Reward Number',
                'Enter your Reward Number',
            ],
        ],
        "es" => [
            'Your Reward Number is:' => 'Tu Número de Reward es:',
            'This email was sent to' => 'Recibes este correo electrónico en',
        ],
        "da" => [
            'Your Reward Number is:' => 'Husk at registrere dit Reward-nummer',
            'This email was sent to' => 'Du modtager denne e-mail til',
        ],
        "fr" => [
            'Your Reward Number is:' => 'Your Reward Number is',
            //'This email was sent to' => '',
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
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Reward Number is:'))}]")->count() > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('This email was sent to'))}]")->count() > 0;
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

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Reward Number is:'))}]", null, true, "/{$this->opt($this->t('Your Reward Number is:'))}\s*(\d+)/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Reward Number is:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");
        }

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Reward Number is:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");
        }

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('This email was sent to'))}]/following::text()[normalize-space()][1]", null, true, "/^\S+[@]\S+\.\S+/");

        if (empty($login)) {
            $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('This email was sent to'))}]", null, true, "/^\S+[@]\S+\.\S+/");
        }

        if (empty($login)) {
            $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('This email was sent to'))}]", null, true, "/\S+[@]\S+\.\S+/");
        }

        if (!empty($login)) {
            $st->setLogin($login);
        }

        if (preg_match("/{$this->opt($this->t('Hi'))},?\s+([[:alpha:]\s.\-]{3,})!?/", $parser->getHeader('subject'), $m)) {
            $st->addProperty('Name', $m[1]);
        } elseif (preg_match("/^([[:alpha:]\s.\-]{3,})\,/", $parser->getHeader('subject'), $m)) {
            $st->addProperty('Name', $m[1]);
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
