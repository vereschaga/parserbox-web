<?php

namespace AwardWallet\Engine\westjet\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Welcome extends \TAccountChecker
{
    public $mailFiles = "westjet/statements/it-115541333.eml, westjet/statements/it-116079220.eml, westjet/statements/it-116408801.eml, westjet/statements/it-116408818.eml, westjet/statements/it-116445279.eml, westjet/statements/it-143869705.eml, westjet/statements/it-65500595.eml";
    private $lang = '';
    private $reFrom = ['.westjet.com'];

    private $reSubject = [
        'Welcome to the WestJet',
    ];
    private $detectors = [
        'fr' => [
            'Dépenses reconnues:',
            'Dépenses reconnues :',
            'Dépenses reconnues de niveau :',
        ],
        'en' => [
            'Your account',
            'welcome to the WestJet RBC',
            'Welcome to WestJet Rewards',
            'Qualifying spend:', 'Qualifying spend :', 'Tier qualifying spend:',
            'WestJet Rewards ID:', 'WestJet Rewards ID :',
        ],
    ];
    private static $dictionary = [
        'fr' => [ // it-116079220.eml
            'account' => 'ID Récompenses WestJet',
            'Teal'    => 'Turquoise',
            // 'welcome' => '',
            // 'Book now' => '',
            'Tier qualifying spend:' => ['Dépenses reconnues de niveau :', 'Dépenses reconnues :'],
            'Year ending:'           => 'Année se terminant le :',
        ],
        'en' => [ // it-65500595.eml, it-115541333.eml
            'account' => ['WestJet Rewards ID', 'Your WestJet Rewards ID'],
            'welcome' => [
                "it's go time",
                'save on your flight',
                'we want to treat you to a better experience',
            ],
            'Tier qualifying spend:' => ['Qualifying spend:', 'Tier qualifying spend:'],
            // 'Year ending:' => '',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ﻿]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $this->assignLang();
        $email->setType('Welcome' . ucfirst($this->lang));

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//p[contains(@class,'small-header-name')]", null, true, "/^[\s﻿]*({$patterns['travellerName']})[\s﻿]*$/u")
            ?? $this->http->FindSingleNode("//*[ count(tr[normalize-space()])=2 and tr[normalize-space()][2][{$this->contains($this->t('Teal'))}] ]/tr[normalize-space()][1]", null, true, "/^[\s﻿]*({$patterns['travellerName']})[\s﻿]*$/u")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('welcome'))}]", null, true, "/^[\s﻿]*({$patterns['travellerName']})[\s﻿]*,[\s﻿]*{$this->opt($this->t('welcome'))}/u")
            ?? $this->http->FindSingleNode("//h3[contains(.,',') and not(preceding::a[{$this->contains($this->t('Book now'))}])]", null, true, "/^[\s﻿]*({$patterns['travellerName']})[\s﻿]*,/u")
        ;

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('account'))}]", null, true, "/[:\s﻿]*(\d{5,})[\s﻿]*$/")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('account'))}]/following::text()[normalize-space()][1]", null, true, "/^[\s﻿]*(\d{5,})[\s﻿]*$/")
        ;

        $balanceArr = array_filter($this->http->FindNodes("//text()[contains(.,'WSD')]", null, "/^[\s﻿]*(\d[,.\'\d ]*?)[\s﻿]*WSD[\s﻿]*$/"), function ($item) {
            return $item !== null;
        });

        if (count($balanceArr) !== 1) {
            // it-116408818.eml, it-116445279.eml
            $balanceArr = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('WestJet dollars'))}]/following::text()[normalize-space()][1]", null, "/^[\s﻿]*(?:[^\d)(]+)?[\s﻿]*(\d[,.\'\d ]*?)[\s﻿]*$/"), function ($item) {
                return $item !== null;
            });
        }
        $balance = count($balanceArr) === 1 ? array_shift($balanceArr) : null;

        if (!empty($name) && !empty($number)) {
            $st->setLogin($number);
            $st->setNumber($number);
            $st->addProperty('Name', $name);
            $status = $this->http->FindSingleNode("//text()[normalize-space() = '{$name}']/following::text()[normalize-space()][1]",
                null, true, "/^\s*({$this->opt(['Teal', 'Silver', 'Gold', 'Platinum', 'Turquoise', 'Argent', 'Or', 'Platine'])})\s*$/");

            if (!empty($status)) {
                $st->addProperty('Tier', str_replace(['Turquoise', 'Argent', 'Or', 'Platine'], ['Teal', 'Silver', 'Gold', 'Platinum'], $status), $status);
            }

            $spent = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Tier qualifying spend:'))}]",
                null, true, "/{$this->opt($this->t('Tier qualifying spend:'))}\s*(\d[\d,.]*)\s*\D{1,5}\s*$/u");
            $yearEnd = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Year ending:'))}]",
                null, true, "/{$this->opt($this->t('Year ending:'))}\s*(.+)\s*$/u"));

            if ($spent !== null && !empty($yearEnd)) {
                $st->addProperty('QualifyingSpend', $spent);
                $st->addProperty("QualifyingYear", date("M d, Y", strtotime("-1 year + 1day", $yearEnd)) . " - " . date("M d, Y", $yearEnd));
                // Qualifying spend this year
            }

            if ($balance !== null) {
                $st->setBalance($balance);
            } else {
                $st->setNoBalance(true);
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
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".westjet.com/") or contains(@href,".westjet.com%2F")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"WestJet. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        if ($this->http->XPath->query('//*[contains(normalize-space(),"Depart:")]')->length > 0) {
            // probably it's reservation, not statement
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            if ($this->http->XPath->query("//*[{$this->contains($phrases)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['account'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['account'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
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

    private function normalizeDate($date)
    {
//        $this->logger->debug("Date: {$date}");
        $in = [
            // 31 décembre 2022
            "#^\s*(\d{1,2})\s+(\w+})\s+(\d{4})\s\s*$#ui", //25/06/2019 11:55AM
        ];
        $out = [
            "$1 $2 $3",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
