<?php

namespace AwardWallet\Engine\parkingspot\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Points extends \TAccountChecker
{
    public $mailFiles = "parkingspot/statements/it-65638840.eml, parkingspot/statements/it-66057264.eml, parkingspot/statements/it-65887344.eml, parkingspot/statements/it-99132134.eml";
    private $lang = '';
    private $reFrom = ['theparkingspot.com'];
    private $reProvider = ['Parking Spot'];
    private $reSubject = [
        'Welcome to the Spot Club!',
        'Your Spot Club account has been updated',
        'Your points now go even further!',
    ];
    private $reBody = [
        'en' => [
            ['Welcome to the Spot Club!', 'Thank you for joining the Spot Club'],
            ['Your Spot Club account has been updated', 'Your Spot Club account has been assigned number'],
            [
                'to your Spot Club account today and redeem your points on your next reservation',
                'This email was sent to',
            ],
            ['As a Spot Club member', 'This email was sent to'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'dear' => ['Dear ', 'Mr '],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();

        $name = $login = $number = null;

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('dear'))}\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u");

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to'))}]", null, true, "/{$this->opt($this->t('This email was sent to'))}[:\s]+(\S+@\S+\.\S+)$/")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to'))}]/following::text()[normalize-space()][1]", null, true, "/^\S+@\S+\.\S+$/")
        ;

        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('been assigned number'))}]", null, true, "/{$this->opt($this->t('been assigned number'))}\s*(\w{5,})./u");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        if ($login) {
            $st->setLogin($login);
        }

        if ($number) {
            $st->setNumber($number);
        }

        if ($name || $login || $number) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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

    private function assignLang(): bool
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
