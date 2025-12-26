<?php

namespace AwardWallet\Engine\tripadvisor\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Statements extends \TAccountChecker
{
    public $mailFiles = "tripadvisor/statements/it-70626620.eml";
    public $subjects = [
        '/^\w+\, thanks for the new review $/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Hi' => ['Hi', 'Hello'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e.tripadvisor.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'TripAdvisor LLC. All rights reserved')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Thanks for your recent contribution'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('What will you review next in'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.tripadvisor\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^{$this->opt($this->t('Hi'))}\s+(\D+)$/");
        $st->addProperty('Name', trim($name, ','));

        $level = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Level'))} and {$this->contains($this->t('Contributor'))}]", null, true, "/{$this->opt($this->t('Level'))}\s*(\d+)/");
        $st->addProperty('Level', $level);

        $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your total score:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");
        $st->setBalance($balance);

        $nextLevel = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Only'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Only'))}\s*(\d+)/");
        $st->addProperty('ToNextLevel', $nextLevel);

        $badge = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Badge'))}]", null, true, "/^(\D+)\s{$this->opt($this->t('Badge'))}$/");
        $st->addProperty('BadgesEarned', $badge);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return 0;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }
}
