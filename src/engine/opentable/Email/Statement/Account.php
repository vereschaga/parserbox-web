<?php

namespace AwardWallet\Engine\opentable\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Account extends \TAccountChecker
{
    public $mailFiles = "opentable/statements/it-63599266.eml, opentable/statements/it-92182793.eml";

    public static $dictionary = [
        'en' => [
            'hello' => ['Hello', 'Hi'],
        ],
    ];

    private $detectFrom = ["@opentable.", ".opentable."];
    private $detectSubjects = [
        "Your OpenTable account is ready",
        "Forgot your password?",
        "Your OpenTable Account",
        "Your OpenTable Account Status",
        "Annual dining points expiration reminder",
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
        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        if ($this->http->XPath->query("//text()[{$this->eq(["Here are your account details:", "Password reset", "Confirm it's you", "The OpenTable team", "The OpenTable Team"])}]")->length > 0) {
            $st->setMembership(true);
            $st->setNoBalance(true);

            // Name
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Your name:"))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");

            if (!$name) {
                $nameNodes = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('hello'))}]", null, "/^{$this->preg_implode($this->t('hello'))}[,\s]+({$patterns['travellerName']})(?:\s*[,:;!?]|$)/u"));

                if (count(array_unique($nameNodes)) === 1) {
                    $name = array_shift($nameNodes);
                }
            }

            if (!empty($name)) {
                $st->addProperty('Name', $name);
            }

            // Login
            $login = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Your email:"))}]/following::text()[normalize-space()][1]", null, true, "/^\S+@\S+$/");

            if ($login) {
                $st->setLogin($login);
            }
        }

        $email->setType('StatementAccount');

        return $email;
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
