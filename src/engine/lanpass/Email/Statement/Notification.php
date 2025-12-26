<?php

namespace AwardWallet\Engine\lanpass\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;
use TAccountChecker;

// Verification code email and nothing else
class Notification extends TAccountChecker
{
    public $mailFiles = "lanpass/statements/it-121808104.eml, lanpass/statements/it-131138194.eml, lanpass/statements/it-131063380.eml";
    public $lang = '';

    public $langDetect = [
        "en" => 'Verification code',
        "pt" => 'Código de verificação',
        'es' => 'Código de verificación',
    ];

    public static $dictionary = [
        "en" => [
            'code' => 'Your verification code to log in is',
        ],
        "pt" => [
            'code'  => 'Seu código de verificação para fazer login é',
            'Hello' => 'Olá',
        ],
        "es" => [
            'code'  => 'Tu código de verificación para iniciar sesión es',
            'Hello' => 'Hola',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers['subject']) || stripos($headers['from'], '.latam.') === false) {
            return false;
        }

        foreach ($this->langDetect as $line) {
            if (stripos($headers['subject'], $line) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        return !empty($this->lang)
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'LATAM Airlines')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), '" . $this->t('code') . "')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]latam\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\s*(\D+)\,/");

        if (!empty($name)) {
            $st = $email->add()->statement();
            $st->addProperty('Name', trim($name, ','));
            $st->setNoBalance(true);
        }

        $code = $this->http->FindSingleNode("//text()[{$this->eq($this->t('code'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (!empty($code)) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($code);
        }

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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

    private function AssignLang()
    {
        foreach ($this->langDetect as $lang => $word) {
            if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                $this->lang = $lang;
            }
        }

        return false;
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }
}
