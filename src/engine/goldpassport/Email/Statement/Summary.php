<?php

namespace AwardWallet\Engine\goldpassport\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Summary extends \TAccountChecker
{
    public $mailFiles = "goldpassport/statements/it-66379200.eml";
    private $lang = '';
    private $reFrom = ['e.hyatt.com'];
    private $reProvider = ['Hyatt Corporation'];
    private $reSubject = [
        'Your Account Summary',
    ];
    private $reBody = [
        'en' => [
            ['Current Point Balance', 'Account Summary'],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();

        $balanceDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Activity as of'))}]/following-sibling::a");

        if (!$balanceDate) {
            return $email;
        }

        $st->parseBalanceDate($balanceDate);
        $st->addProperty('Name',
            $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Activity as of'))}]/ancestor::table[2]/following-sibling::table[1]//*[self::th or self::td])[1]",
                null, false, "/^[[:alpha:]\s.]{3,}$/u"));

        $text = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Activity as of'))}]/ancestor::table[2]/following-sibling::table[1]//*[self::th or self::td])[2]");

        if (preg_match('/([[:alpha:]\s]{4,}) \| ([\w\-]{5,})/', $text, $m)) {
            $st->addProperty('Tier', $m[1]);
            $st->setLogin($m[2]);
            $st->setNumber($m[2]);
        }

        $balance = str_replace(',', '',
            $this->http->FindSingleNode("//text()[{$this->starts($this->t('Current Point Balance'))}]/ancestor::tr[1]/preceding-sibling::tr[1]"));
        $st->setBalance($balance);

        $nightsYTD = str_replace(',', '',
            $this->http->FindSingleNode("//text()[{$this->starts($this->t('Eligible Nights YTD'))}]/ancestor::tr[1]/preceding-sibling::tr[1]",
                null, false, '#([\d.,]+)\s*#'));
        $st->addProperty('Nights', $nightsYTD);

        $pointsYTD = str_replace(',', '',
            $this->http->FindSingleNode("//text()[{$this->starts($this->t('Base Points YTD'))}]/ancestor::tr[1]/preceding-sibling::tr[1]",
                null, false, '#([\d.,]+)\s*/#'));
        $st->addProperty('BasePointsYTD', $pointsYTD);

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
