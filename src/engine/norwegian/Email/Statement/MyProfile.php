<?php

namespace AwardWallet\Engine\norwegian\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MyProfile extends \TAccountChecker
{
    public $mailFiles = "norwegian/statements/it-63649665.eml, norwegian/statements/it-70074585.eml, norwegian/statements/it-70377180.eml";
    public $reFrom = '@news.norwegian.com';

    public $lang = 'no';

    public $detectLang = [
        'no' => ['Min profil'],
        'en' => ['My profile'],
        'es' => ['Mi perfil'],
    ];

    private static $dictionary = [
        'no' => [
            'Min profil' => ['Min profil', 'Mon profil'],
            //'Hei'
        ],
        'en' => [
            'Min profil' => 'My profile',
            //'Hei' => '',
        ],
        'es' => [
            'Min profil' => 'Mi perfil',
            //'Hei' => '',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }

        $st = $email->add()->statement();

        $name = $this->re("/{$this->opt($this->t('Hei'))}\,\s*(\w+)\!/", $parser->getSubject());

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $balance = $this->http->FindSingleNode("//a[{$this->starts($this->t('Min profil'))}]/ancestor::tr[1]/descendant::td[{$this->contains($this->t('CashPoints'))}]", null, true, "/(\d+)\s*{$this->opt($this->t('CashPoints'))}/");
        $st->setBalance($balance);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]news\.norwegian\.com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === true) {
            return $this->http->XPath->query("//text()[{$this->starts($this->t('Norwegian Air'))}]")->count() > 0
                && $this->http->XPath->query("//a[{$this->starts($this->t('Min profil'))}]")->count() > 0
                && $this->http->XPath->query("//a[{$this->starts($this->t('Min profil'))}]/ancestor::tr[1]/descendant::td[{$this->contains($this->t('CashPoints'))}]")->count() > 0;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
