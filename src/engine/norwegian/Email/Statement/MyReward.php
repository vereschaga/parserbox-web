<?php

namespace AwardWallet\Engine\norwegian\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MyReward extends \TAccountChecker
{
    public $mailFiles = "norwegian/statements/it-66555000.eml, norwegian/statements/it-66609729.eml, norwegian/statements/it-66612574.eml, norwegian/statements/it-66612576.eml, norwegian/statements/it-66498625.eml";
    private $lang = '';
    private $reFrom = ['.norwegianreward.com', '@norwegianreward.com'];
    private $reProvider = ['Norwegian'];
    private $reSubject = [
    ];
    private $reBody = [
        'en' => [
            ['You are receiving this email at', 'because you are a member of Norwegian Reward'],
        ],
        'sv' => [
            ['Du får det här mailet till', 'eftersom du är medlem i Norwegian Reward'],
            ['Det här mailet skickades till', 'Du får det här mailet eftersom du är medlem i Norwegian Reward'],
        ],
        'no' => [
            ['Du mottar denne e-posten på', 'du er medlem av Norwegian Reward'],
        ],
        'fi' => [
            ['Sinulle on lähetetty tämä sähköposti osoitteeseen', 'koska olet Norwegian Reward'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'loginDetect' => ['You are receiving this email at'],
        ],
        'sv' => [
            'Hi'             => 'Hej',
            'Reward number:' => 'Reward-nummer:',
            'loginDetect'    => ['Du får det här mailet till', 'Det här mailet skickades till'],
        ],
        'no' => [
            'Hi'             => 'Hei',
            'Reward number:' => 'Reward-nummer:',
            'loginDetect'    => ['Denne e-posten ble sendt til', 'Du mottar denne e-posten på'],
        ],
        'fi' => [
            'Hi'             => 'Hei',
            'Reward number:' => 'Reward-numerosi:',
            'loginDetect'    => ['Sinulle on lähetetty tämä sähköposti osoitteeseen'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reward number:'))}]/ancestor::*[1]",
            null, true, "/^{$this->opt($this->t('Reward number:'))}\s+(\d+)$/");

        if ($number) {
            $st->setNumber($number);
        }

        $pattern = "/{$this->opt($this->t('loginDetect'))}\s+([_a-z0-9\-.]+@[_a-z0-9\-.]+)/i";
        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('loginDetect'))}]/ancestor::*[1]",
            null, true, $pattern);

        if (isset($login)) {
            $st->setLogin($login);
            $st->setMembership(true);
            $st->setNoBalance(true);

            if (preg_match("/{$this->opt($this->t('Hi'))},?\s+([[:alpha:]\s.\-]{3,})!/", $parser->getHeader('subject'), $m)) {
                $st->addProperty('Name', $m[1]);
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

        foreach ($this->reSubject as $subject) {
            if (preg_match($subject, $headers['subject'])) {
                return true;
            }
        }

        return false;
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
