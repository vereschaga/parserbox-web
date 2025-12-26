<?php

namespace AwardWallet\Engine\nhhotels\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class DiscountStatement extends \TAccountChecker
{
    public $mailFiles = "nhhotels/statements/it-62832706.eml, nhhotels/statements/it-62832755.eml, nhhotels/statements/it-62833146.eml, nhhotels/statements/it-62833520.eml, nhhotels/statements/it-62833525.eml";
    private $lang = '';
    private $reFrom = ['nh-hotels.com'];
    private $reProvider = ['NH Rewards'];
    private $reSubject = [
        'de descuento extra en nuestros hoteles',
        'an extra 10% off our Secret Selection hotels',
        'sconto extra del 10% nei nostri hotel',
        'até 10% de desconto nos nossos hotéis',
        '10% extra korting op Secret Selection hotels',
    ];
    private $reBody = [
        'en' => ['This month we bring our most extraordinary hotels closer to you.'],
        'es' => ['Este mes te presentamos nuestros hoteles más extraordinarios.'],
        'nl' => ['Deze maand brengen wij onze meest bijzondere hotels binnen handbereik.'],
        'it' => ['Questo mese potrai conoscere da vicino i nostri hotel più straordinari.'],
        'pt' => ['Este mês damos-lhe a conhecer os nossos hotéis mais extraordinários.'],
    ];
    private static $dictionary = [
        'en' => [
        ],
        'es' => [
            'Member No.'        => 'Nº del titular:',
            'NH Rewards Points' => 'Puntos NH Rewards',
            'Category:'         => 'Categoría:',
        ],
        'nl' => [
            'Member No.'        => 'Lidmaatschap nr:',
            'NH Rewards Points' => 'NH Rewards Punten',
            'Category:'         => 'Categorie:',
        ],
        'it' => [
            'Member No.'        => 'Nº di titolare:',
            'NH Rewards Points' => 'Points NH Rewards',
            'Category:'         => 'Categoria:',
        ],
        'pt' => [
            'Member No.'        => 'Membro n.º',
            'NH Rewards Points' => 'Pontos NH Rewards',
            'Category:'         => 'Categoria:',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $number = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Member No.'))}])[1]");

        if ($number = $this->http->FindPreg('/[:.º]\s+([\w\-]+)/', false, $number)) {
            $st->setLogin($number);
            $st->setNumber($number);
            $st->setBalance($this->http->FindSingleNode("//text()[{$this->contains($this->t('NH Rewards Points'))}]/..", null,
                false, "/:\s*([\d\s.,]+)/"));
            $st->addProperty('Name', $this->http->FindSingleNode("//text()[{$this->contains($this->t('Member No.'))}]/../preceding-sibling::*[1]", null,
                false, "/[[:alpha:]\s]{5,}/"));
            $st->addProperty('Status', $this->http->FindSingleNode("//text()[{$this->contains($this->t('Category:'))}]", null,
                false, "/:\s*([[:upper:]\s]+)/"));
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
        foreach ($this->reBody as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
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
