<?php

namespace AwardWallet\Engine\hotels\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class PasswordChanged extends \TAccountChecker
{
    public $mailFiles = "hotels/statements/it-107179425.eml, hotels/statements/it-107274451.eml, hotels/statements/it-107757216.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [ // it-107757216.eml
            'passwordChanged' => 'La contraseña de tu cuenta de Hoteles.com fue modificada.',
            'hi'              => '¡Hola',
            'provider'        => 'Hoteles.com',
        ],
        'pt' => [ // it-107274451.eml
            'passwordChanged' => 'A senha de sua conta da Hoteis.com foi alterada.',
            'hi'              => 'Olá',
            'provider'        => 'Hoteis.com',
        ],
        'en' => [ // it-107179425.eml
            'passwordChanged' => 'Your password for your Hotels.com account has now been changed.',
            'hi'              => 'Hi',
            'provider'        => 'Hotels.com',
        ],
    ];

    private $subjects = [
        'es' => ['Cambiaste tu contraseña'],
        'pt' => ['Senha alterada'],
        'en' => ['Password Changed'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@reply.mail.hotels.com') !== false;
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
        if ($this->assignLang() !== true) {
            return false;
        }

        return $this->http->XPath->query("//*[{$this->contains($this->t('provider'))}]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $name = null;

        $st = $email->add()->statement();

        $names = $this->http->FindNodes("//text()[{$this->starts($this->t('hi'))}]", null, "/^{$this->opt($this->t('hi'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u");

        if (count(array_unique($names)) === 1) {
            $name = array_shift($names);
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        if ($name) {
            $st->setNoBalance(true);
        }

        $email->setType('PasswordChanged' . ucfirst($this->lang));

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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['passwordChanged'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['passwordChanged'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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
}
