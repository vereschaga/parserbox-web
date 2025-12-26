<?php

namespace AwardWallet\Engine\shell\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "shell/statements/it-135543274-de.eml, shell/statements/it-140910052.eml, shell/statements/it-141132492.eml, shell/statements/it-680285757.eml, shell/statements/it-683843097.eml, shell/statements/it-684748332.eml";

    public $lang = '';

    public static $dictionary = [
        'de' => [
            'verificationCodeIs' => ['Ihr einmaliger Verifizierungscode lautet:', 'Ihr einmaliger Verifizierungscode lautet :'],
            'Hi'                 => 'Hallo',
        ],
        'en' => [
            'verificationCodeIs' => ['Your one-time verification code is:', 'Your one-time verification code is :'],
            'Hi'                 => ['Hi', 'Hello'],
        ],
        'sk' => [
            'verificationCodeIs' => ['Váš jednorazový overovací kód je:'],
            'Hi'                 => ['Hi', 'Dobrý deň'],
        ],
        'nl' => [
            'verificationCodeIs' => ['je eenmalige verificatiecode is:'],
            'Hi'                 => ['Hi', 'Hallo'],
        ],
        'fr' => [
            'verificationCodeIs' => ['Votre code de vérification à usage unique est:'],
            'Hi'                 => ['Hi', 'Bonjour'],
        ],
    ];

    private $subjects = [
        // 'de' => [''],
        'en' => ['Forgotten password'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@shell.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match('/(?:^|:\s*)Shell\s+\|\s+\S[^:]+:\s*\d{3}/', $headers['subject']) > 0) {
            return true;
        }

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
            && $this->http->XPath->query('//a[contains(@href,".shell.com/") or contains(@href,".shell.de/") or contains(@href,"support.shell.com") or contains(@href,"support.shell.de")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"support.shell.com") or contains(normalize-space(),"support.shell.de") or contains(normalize-space(),"ThanksThe Shell Go+ team") or contains(normalize-space(),"DankeDas Shell ClubSmart Team")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findRoot()->length === 1
            || $this->isMembership()
        ;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $this->assignLang();
        $email->setType('VerificationCode' . ucfirst($this->lang));

        $st = $email->add()->statement();

        $roots = $this->findRoot();

        if ($roots->length === 1) {
            // it-135543274-de.eml, it-140910052.eml
            $root = $roots->item(0);

            $name = null;

            $nameValues = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+({$patterns['travellerName']})(?:\s*[,:;!?]|$)/u"));

            if (count(array_unique($nameValues)) === 1) {
                $name = array_shift($nameValues);
                $st->addProperty('Name', $name);
            }

            if ($name) {
                $st->setNoBalance(true);
            }

            $verificationCode = $this->http->FindSingleNode("following-sibling::*[normalize-space()][1]", $root, null, "/^\d{3,}$/");

            if ($verificationCode) {
                $email->add()->oneTimeCode()->setCode($verificationCode);
            }
        } elseif ($this->isMembership()) {
            // it-141132492.eml
            $st->setMembership(true);
        }

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

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[{$this->eq($this->t('verificationCodeIs'))}]/ancestor::*[ following-sibling::*[normalize-space()] ][1]");
    }

    private function isMembership(): bool
    {
        $phrases = [
            "We've received a request to change your password for your Shell acount.", // en
        ];

        return $this->http->XPath->query("//*[{$this->contains($phrases)}]")->length > 0;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['verificationCodeIs'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['verificationCodeIs'])}]")->length > 0) {
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
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
