<?php

namespace AwardWallet\Engine\ufly\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WelcomeTo extends \TAccountChecker
{
    public $mailFiles = "ufly/statements/it-64777558.eml, ufly/statements/it-76280333.eml";
    public $subjects = [
        "/^Welcome to Sun Country's Loyalty Program$/",
        '/(?:Password Changed|Forget Password Recovery|Rewards Point Expiration Notice)$/i',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Dear' => ['Dear', 'Hi'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && $this->detectEmailFromProvider($headers['from']) === true) {
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
        if ($this->http->XPath->query("//*[contains(normalize-space(),'Welcome to Sun Country Rewards') or contains(normalize-space(),'Thank you, Sun Country Rewards Team')]")->count() === 0) {
            return false;
        }

        return $this->isMembership()
            || $this->http->XPath->query("//text()[{$this->contains($this->t('This email was sent to:'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]suncountry\.(?:email|com)\b/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $login = null;

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->opt($this->t('Dear'))}\s+(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to:'))}]", null, true, "/{$this->opt($this->t('This email was sent to:'))}\s*(.+)/");

        if (!empty($login)) {
            $st->setLogin($login);
        }

        if ($name || $login) {
            $st->setNoBalance(true);
        } elseif ($this->isMembership()) {
            $st->setMembership(true);
        }

        return true;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(),'Your Sun Country Rewards password has been successfully changed') or contains(normalize-space(),'You recently requested to reset your password for your Sun Country Rewards account') or contains(normalize-space(),'We noticed you have Sun Country Rewards points due to expire in')]")->count() > 0;
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
