<?php

namespace AwardWallet\Engine\opentable\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class ConfirmEmail extends \TAccountChecker
{
    public $mailFiles = "opentable/it-142023775.eml, opentable/it-142257512(1).eml, opentable/statements/it-127925951.eml";

    public static $dictionary = [
        'en' => [
            'simply copy and paste these number' => 'simply copy and paste these number',
            'Your verification code is' => 'Your verification code is',
            'hello' => ['Hello', 'Hi'],
        ],
        'es' => [
            'simply copy and paste these number' => 'copia y pega este código en',
            'Your verification code is' => 'Código de verificación:',
            'hello' => 'Hola',
        ],
        'ja' => [
            'simply copy and paste these number' => 'もしくは、これらの数字をコピーして',
            'Your verification code is' => '認証コード：',
            'hello' => '様',
        ],
        'it' => [
            'simply copy and paste these number' => 'copia e incolla questi numeri su',
            'Your verification code is' => ' Il tuo codice di verifica è',
            'hello' => 'Ciao',
        ],
    ];

    private $detectFrom = ["@opentable.", ".opentable."];
    private $detectSubjects = [
        // en
        "Confirm It's You",
        // es
        'Confirma que eres tú',
        // ja
        'ご自身であることを確認してください',
        // it
        'Conferma la tua identità',
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'OpenTable') === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubjects) {
            if (stripos($headers['subject'], $dSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"opentable.com")]')->length === 0) {
            return false;
        }
        if (!empty($this->http->FindSingleNode("//*[normalize-space(@id)='copy-code']", null, true, "/^\d{3,}$/"))) {
            return true;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (
                (!empty($dict['simply copy and paste these number']) && $this->http->XPath->query("//text()[{$this->contains($dict['simply copy and paste these number'])}]")->length > 0)
                || (!empty($dict['Your verification code is']) && $this->http->XPath->query("//text()[{$this->starts($dict['Your verification code is'])}]")->length > 0)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (
                (!empty($dict['simply copy and paste these number']) && $this->http->XPath->query("//text()[{$this->contains($dict['simply copy and paste these number'])}]")->length > 0)
                || (!empty($dict['Your verification code is']) && $this->http->XPath->query("//text()[{$this->starts($dict['Your verification code is'])}]")->length > 0)) {
                $this->lang = $lang;
            }
        }

        // 2FA
        $verificationCode = $this->http->FindSingleNode("//*[normalize-space(@id)='copy-code']", null, true, "/^\d{3,}$/")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('simply copy and paste these number'))}]/following::text()[normalize-space()][1]", null, true, "/^\d{3,}$/")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your verification code is'))}]", null, true, "/^{$this->preg_implode($this->t('Your verification code is'))}\s+(\d{3,})(?:\s*[,.;!]|$)/");

        if ($verificationCode !== null) {
            // it-127925951.eml
            $code = $email->add()->oneTimeCode();
            $code->setCode($verificationCode);

            $st = $email->add()->statement();
            $st
                ->setMembership(true)
                ->setNoBalance(true)
            ;

            if (in_array($this->lang, ['ja'])) {
                $regexp = "/^\s*([[:alpha:]][-.'’[:alpha:] ]*[[:alpha:]])\s+{$this->preg_implode($this->t('hello'))}(?:\s*[,:;!?]|$)/u";
                $nameNodes = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('hello'))}]", null, $regexp));
            } else {
                $regexp = "/^{$this->preg_implode($this->t('hello'))}[,\s]+([[:alpha:]][-.'’[:alpha:] ]*[[:alpha:]])(?:\s*[,:;!?]|$)/u";
                $nameNodes = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('hello'))}]",
                    null, $regexp));
            }
            if (count(array_unique($nameNodes)) === 1) {
                $st->addProperty('Name', array_shift($nameNodes));
            }
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
