<?php

namespace AwardWallet\Engine\viarail\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Account extends \TAccountChecker
{
    public $mailFiles = "viarail/statements/it-79479622.eml, viarail/statements/it-79559590.eml";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'VIA Préférence #' => 'VIA Préférence #',
            'points'           => 'points',
            //            'VIA Rail’s commercial electronic messages with the following address:' => '',
        ],
        'fr' => [
            'VIA Préférence #'                                                      => 'No VIA Préférence',
            'points'                                                                => 'points',
            'VIA Rail’s commercial electronic messages with the following address:' => 'VIA Rail avec l’adresse suivante:',
        ],
    ];

    private $detectFrom = 'viarail@message.viarail.ca';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->detectBody();

        $st = $email->add()->statement();

        $info = $this->http->FindSingleNode("//td[not(.//td) and {$this->contains($this->t('VIA Préférence #'))} and {$this->contains($this->t('points'))}]");

        if (preg_match("/" . $this->opt($this->t('VIA Préférence #')) . "\s*(?<account>\d{5,})\s*\|\s*(?<points>\d+)\s*" . $this->opt($this->t('points')) . "/u", $info, $m)) {
            // No VIA Préférence 9314947 | 0 points
            $st
                ->setLogin($m['account'])
                ->setNumber($m['account'])
            ;

            $st->setBalance($m['points']);
        }
        if (empty($info)) {
            $node = $this->http->XPath->query("descendant::img[1]/following::text()[normalize-space()][not(ancestor::style)][1]");
            if ($node->length == 1 && preg_match("/^\s*# *(?<account>\d{5,}) *\| *(?<points>\d+) *" . $this->opt($this->t('points')) . "\s*$/", $node->item(0)->nodeValue, $m)) {
                //# 8884885 | 974 points
                $st
                    ->setLogin($m['account'])
                    ->setNumber($m['account'])
                ;

                $st->setBalance($m['points']);
            }
        }

        $userEmail = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("VIA Rail’s commercial electronic messages with the following address:")) . "]", null, true,
            "/" . $this->opt($this->t("VIA Rail’s commercial electronic messages with the following address:")) . "\s*(\S+@\S+\.\w+)\./u"
        );

        if (!empty($userEmail)) {
            $email->setUserEmail($userEmail);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function detectBody(): bool
    {
        $node = $this->http->XPath->query("descendant::img[1]/following::text()[normalize-space()][not(ancestor::style)][1]");
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['VIA Préférence #']) && !empty($dict['points'])
                && $this->http->XPath->query("//td[not(.//td) and {$this->contains($dict['VIA Préférence #'])} and {$this->contains($dict['points'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
            if ($node->length == 1 and preg_match("/^\s*# *\d{5,} *\| *\d+ " . $this->opt($dict['points']) . "\s*$/", $node->item(0)->nodeValue)) {
                //# 8884885 | 974 points
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
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
}
