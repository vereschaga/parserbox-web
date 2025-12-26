<?php

namespace AwardWallet\Engine\reurope\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Confirm extends \TAccountChecker
{
    public $mailFiles = "reurope/statements/it-65059926.eml, reurope/statements/it-65098872.eml";
    private $lang = '';
    private $reFrom = ['hello@raileurope.co.uk', 'help@raileurope.com'];
    private $reProvider = ['Rail Europe'];
    private $reSubject = [
        // en
        'Rail Europe: Confirm your email',
        'Rail Europe: Change your password',
        // pt
        'Rail Europe: confirme seu e-mail',
        // fr
        'Rail Europe : Confirmez votre adresse e-mail',
        // it
        'Rail Europe: Conferma il tuo indirizzo e-mail',
    ];
    private $reBody = [
        'en' => [
            ['We need to verify your email to create your Rail Europe account.', 'Verify account'],
            ['We need to verify your email to update your Rail Europe account.', 'Verify account'],
            ['Welcome to Rail Europe, thank you for creating an account with us!', 'As a reminder, your login name is:'],
            ['A request has been made to change your Rail Europe password.', 'Reset password'],
        ],
        'pt' => [
            ['Precisamos verificar seu e-mail para criar sua conta da Rail Europe', 'Verificar conta'],
        ],
        'fr' => [
            ['Nous devons vérifier votre adresse e-mail pour pouvoir créer votre compte Rail Europe.', 'Vérifiez votre compte'],
        ],
        'it' => [
            ['Dobbiamo verificare il tuo indirizzo e-mail per creare il tuo account Rail Europe.', 'Verifica l’account'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'hello' => ['Dear', 'Hello'],
//            'As a reminder, your login name is:' => '',
        ],
        'pt' => [
            'hello' => ['Olá'],
//            'As a reminder, your login name is:' => '',
        ],
        'fr' => [
            'hello' => ['Bonjour'],
//            'As a reminder, your login name is:' => '',
        ],
        'it' => [
            'hello' => ['Ciao'],
//            'As a reminder, your login name is:' => '',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->info("Lang: {$this->lang}");
        $st = $email->add()->statement();

        // it-65098872.eml
        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('hello'))}]",
            null, true, "/{$this->opt($this->t('hello'))}([[:alpha:]\s]{3,})/u");

        if (isset($name)) {
            $st->setNoBalance(true);
            $st->addProperty('Name', $name);
        }

        // it-65059926.eml
        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('As a reminder, your login name is:'))}]/ancestor::*[1]/following-sibling::*[1]");

        if (isset($login)) {
            $st->setLogin($login);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
