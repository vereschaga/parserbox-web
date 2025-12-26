<?php

namespace AwardWallet\Engine\golair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Cancellation extends \TAccountChecker
{
    public $mailFiles = "golair/it-70017595.eml";

    private $lang = '';
    private $reFrom = ['@drc.voegol.com.br'];
    private $reProvider = ['GOL Linhas'];
    private $reSubject = [
        'GOL | Informação sobre seu voo | Localizador ',
    ];
    private $reBody = [
        'pt' => [
            ['O seu voo foi cancelado.', 'Seu voo foi cancelado devido a ajustes necessários em nossa malha aérea.'],
            ['Informação importante para você!', 'está cancelado em decorrência de ajustes em nossa malha aérea'],
            ['Informação importante para você!', 'Seu voo foi cancelado devido a ajustes necessários em nossa malha aérea.'],
        ],
    ];
    private static $dictionary = [
        'pt' => [
            'Data:' => ['Data do Voo:', 'Data:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Localizador:'))}])[1]", null, true, '/:\s*([A-Z\d]{5,6})/');

        if (!$conf) {
            $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Localizad'))}]/following::*[normalize-space()][1]", null, true, '/\s+([A-Z\d]{5,6})$/');
        }

        $f->general()->confirmation(
            $conf,
            $this->http->FindSingleNode("//text()[{$this->contains($this->t('Localizador:'))}]", null, true, '/^(.+?:)\s*[A-Z\d]{5,6}/')
        );
        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('O seu voo foi'))}]", null, true, "/{$this->opt($this->t('O seu voo foi'))}\s*(\w+)/");

        if (!empty($status)) {
            $f->general()->status($status);
        }
        $f->general()->cancelled();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
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
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//*[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
