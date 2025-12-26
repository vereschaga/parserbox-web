<?php

namespace AwardWallet\Engine\harrah\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Members extends \TAccountChecker
{
    public $mailFiles = "harrah/statements/it-65009704.eml, harrah/statements/it-65058231.eml, harrah/statements/it-65187601.eml, harrah/statements/it-65259333.eml, harrah/statements/it-65323992.eml";
    private $lang = '';
    private $reFrom = ['email@email.caesars-marketing.com'];
    private $reProvider = ['Harrah', 'Caesars Rewards'];
    private $reSubject = [
        'Rewards Unlock Savings in',
        'For All Caesars Rewards Members',
        'Your Caesars Rewards Account',
        'Your Caesars Rewards Statement',
    ];
    private $reBody = [
        'en' => [
            ['Caesars Rewards card indicates acceptance of the current', 'Exclusively For:'],
            ['Caesars Rewards card indicates acceptance of the current', 'Your user name is'],
            ['Caesars Rewards card indicates acceptance of the current', 'for your Caesars Rewards account'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'conf' => '· Riu Class no:',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();

        // it-65323992.eml
        // it-65187601.eml
        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Exclusively For:'))}]/following-sibling::b[1]");
        $this->logger->debug($text);

        if (isset($text)) {
            if (preg_match("/^#?([\w\-]+)$/u", $text, $m)) {
                $st->setNumber($m[1]);
                $st->setLogin($m[1]);
                $st->setMembership(true);
                $st->setNoBalance(true);
            }
            // Nathaniel Winstead #12204201404
            if (preg_match("/^([[:alpha:]\s]{2,})#?([\w\-]+)$/u", $text, $m)) {
                $st->addProperty('Name', $m[1]);
                $st->setNumber($m[2]);
                $st->setLogin($m[2]);
                $st->setMembership(true);
                $st->setNoBalance(true);
            }
        }

        // it-65259333.eml
        // it-65058231.eml
        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your user name is'))}]/following-sibling::*[1]");
        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]",
            null, true, "/{$this->opt($this->t('Dear '))}([[:alpha:]\s]{3,}),/u");

        if (isset($login) || isset($name)) {
            if (isset($login)) {
                $st->setNumber($login);
                $st->setLogin($login);
            }
            $st->addProperty('Name', $name);
            $st->setMembership(true);
            $st->setNoBalance(true);
        }

        // it-65009704.eml
        $prop = $this->http->FindSingleNode("//h3[{$this->contains($this->t('Reward Credit Balance'))}]/following-sibling::*[1]");

        if (isset($prop)) {
            $st->setBalance(str_replace(',', '', $prop));
            $st->setNoBalance(false);
            $prop = $this->http->FindSingleNode("//h3[{$this->contains($this->t('Tier Score'))}]/following-sibling::*[1]");
            $st->addProperty('TierScore', str_replace(',', '', $prop));
            $prop = $this->http->FindSingleNode("//h3[{$this->contains($this->t('Tier Status'))}]/following-sibling::*[1]");
            $st->addProperty('CurrentTier', $prop);
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
