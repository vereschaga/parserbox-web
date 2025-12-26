<?php

namespace AwardWallet\Engine\triprewards\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RewardsStatement extends \TAccountChecker
{
    public $mailFiles = "triprewards/statements/it-61332286.eml, triprewards/statements/it-62927342.eml, triprewards/statements/it-62894279.eml, triprewards/statements/it-62940081.eml";
    private $lang = '';
    private $reFrom = ['@emails.wyndhamhotels.com'];
    private $reProvider = ['Wyndham Rewards', 'Wyndham Hotels'];
    private $reSubject = [
        ' Wyndham Rewards Statement',
        'Bonus Points with No Annual Fee',
        'Get Excited: You’re a Wyndham Rewards Member',
        'Ihre Wyndham Rewards Monatsübersicht für',
    ];
    private $reBody = [
        'en' => [
            ['Member #', 'If you prefer, you may send your unsubscribe request to:'],
        ],
        'de' => [
            ['Mitglied #', 'Bitte antworten Sie nicht auf diese Nachricht. Diese E-Mail-Adresse wird nicht abgerufen.'],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
        'de' => [
            'Member #' => ['Mitglied #', 'Member #'],
            'points'   => 'Punkte',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $array = $this->http->FindNodes("//text()[{$this->contains($this->t('Member #'))}]/ancestor::table[1]//text()");

        if (!empty($array)) {
            $text = trim(join("\n", $array));
            $this->logger->debug($text);

            if (preg_match("/,\s+(?<name>.+?)\n+\s*{$this->opt($this->t('Member #'))}"
                . "\s*(?<number>[\w\-]+)\s*\n+(?<status>[\w\s]+)\s*\|(?:\s*MILITARY\s*\|)?"
                . "\s*(?<points>[\d.,\s*]+) {$this->opt($this->t('points'))}/u", $text, $m)) {
                $st->setBalance(str_replace(',', '', $m['points']));
                $st->addProperty('Name', $m['name']);
                $st->setLogin($m['number']);
                $st->setNumber($m['number']);
                $st->addProperty('Status', $m['status']);
            } // it-62927342.eml
            elseif (
                preg_match("/,\s+(?<name>.+?)\n+\s*(?<status>[\w\s]+)\s*{$this->opt($this->t('Member #'))}\s*(?<number>[\w\-]+)\s*$/u",
                    $text, $m)
                || preg_match("/^(?<name>[[:alpha:]\s]{3,})\n+\s*(?<status>[\w\s]+)\s*{$this->opt($this->t('Member #'))}\s*(?<number>[\w\-]+)\s*$/u",
                    $text, $m)) {
                $st->setNoBalance(true);
                $st->addProperty('Name', $m['name']);
                $st->addProperty('Status', $m['status']);
                $st->setLogin($m['number']);
                $st->setNumber($m['number']);
            }
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
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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
