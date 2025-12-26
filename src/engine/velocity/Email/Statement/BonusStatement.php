<?php

namespace AwardWallet\Engine\velocity\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BonusStatement extends \TAccountChecker
{
    public $mailFiles = "velocity/statements/it-63750003.eml, velocity/statements/it-77715429.eml";
    private $lang = '';
    private $reFrom = ['@e.velocityfrequentflyer.com'];
    private $reProvider = ['Velocity Frequent Flyer'];
    private $reSubject = [
        'bonus Velocity Points are on now with American Express',
        'Have you got American Express Membership Rewards points?',
        'Velocity Points bonus with American Express',
        'Bonus Points offer for American Express Card Members',
        'The American Express transfer bonus that',
    ];
    private $reBody = [
        'en' => [
            [
                'You have received this message because you are a member of Velocity Frequent Flyer and are subscribed to Other Partner Offers communications.',
                'Membership no.',
            ],

            [
                'Velocity Frequent Flyer',
                'Member no.',
            ],
            [
                'Velocity Points',
                'Membership no.',
            ],
            [
                'Activate Now',
                'Membership no.',
            ],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Member no.' => ['Member no.', 'Membership no.'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $number = $this->http->FindNodes("//text()[{$this->contains($this->t('Velocity points'))}]/ancestor::table[3]/descendant::text()[normalize-space()]");

        if (empty($number)) {
            $number = $this->http->FindNodes("//text()[{$this->eq($this->t('Member no.'))}]/ancestor::table[3]/descendant::text()[normalize-space()]");
        }

        /*
        Mr Joshua Bretag
        Red
        Velocity points
        930
        (as at 29 Oct 2019)
        Membership no.
        1234567890
         */
        if (preg_match("/^(?<name>.+?)\s+(?<level>[[:alpha:]]{2,})\s+Velocity points\s*(?:(?<balance>[\d.,\s]+))?" .
            "\s*\(as at (?<dateBalance>.+?)\)\s+Membership no\.\s+(?<login>[\w\-]{5,})/", join("\n", $number), $m)) {
            $st->addProperty('Name', $m['name']);
            $st->addProperty('Level', $m['level']);
            $st->setBalance(str_replace(',', '', $m['balance']));
            $st->parseBalanceDate($m['dateBalance']);
            $st->setLogin($m['login']);
            $st->setNumber($m['login']);
        }

        /*
         *  Mr Antony Clark
            RED
            Member no.
            2102155173
         * */
        if (preg_match("/^(.+?)\s*\n(\w+)\s+{$this->opt($this->t('Member no.'))}\s+([\w\-]{5,})$/", join("\n", $number), $m)) {
            $st->addProperty('Name', $m[1]);
            $st->addProperty('Level', $m[2]);
            $st->setNoBalance(true);
            $st->setLogin($m[3]);
            $st->setNumber($m[3]);
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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . "),'" . $s . "')";
        }, $field)) . ')';
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
