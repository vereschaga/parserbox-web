<?php

namespace AwardWallet\Engine\hhonors\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FromLink extends \TAccountChecker
{
    public $mailFiles = "hhonors/it-469322334.eml, hhonors/statements/it-77600793.eml, hhonors/statements/it-77601160.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'This email was delivered to' => [
                'This email was delivered to',
                'This email advertisement was delivered to',
            ],
            'Hilton Honors' => ['Hilton Honors'],
        ],
        "ko" => [
            'Hilton Honors' => ['힐튼 Honors'],
        ],
        "ja" => [
            'Hilton Honors' => ['ヒルトン・オナーズ'],
        ],
    ];

    private $patterns = [
        'boundary' => '(?:[&"%\s]|$)',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (stripos($parser->getSubject(), 'statement') !== false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Hilton Honors'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Hilton Honors'])}] | //img[{$this->eq($dict['Hilton Honors'], '@alt')}]")->length > 0
                && $this->http->XPath->query("//img[contains(@src, 'hh_num=') or contains(@src, 'mi_name=')]/@src")->length > 0
                && $this->http->XPath->query("//img[contains(@src, 'mi_tier=')]/@src")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]h1\.hilton\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Hilton Honors'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Hilton Honors'])}] | //img[{$this->eq($dict['Hilton Honors'], '@alt')}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $info = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hilton Honors'))}]/preceding::img[contains(@src, 'mi_hhnum=') and contains(@src, 'mi_name=')][1]/@src");

        if (empty($info)) {
            $info = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hilton Honors'))}]/preceding::img[contains(@src, 'hh_num=') or contains(@src, 'mi_name=')][last()]/@src");
            $info .= $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hilton Honors'))}]/preceding::img[contains(@src, 'hh_num=') or contains(@src, 'mi_name=')][last()]/ancestor::a[1]/@href");
        }

        if (empty($info)) {
            $info = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hilton Honors'))}]/preceding::img[contains(@src, 'hh_num=') or contains(@src, 'mi_name=')][last()]/@src");
            $info .= $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hilton Honors'))}]/preceding::img[contains(@src, 'hh_num=') or contains(@src, 'mi_name=')][last()]/ancestor::a[1]/@href");
        }

        if (!empty($info)) {
            $st = $email->add()->statement();

            $name = $this->re("/[&]mi_f?name\=([A-Za-z\s\-]+)(?:[&]|$)/", $info);
            $name = $name . ' ' . $this->re("/[&]mi_lname\=([A-Za-z\s\-]+)[&]/", $info);
            $name = trim($name);

            if (!empty($name)) {
                $st->addProperty('Name', $name);
            }

            $number = $this->re("/(?:hh\_num|mi\_hhnum)\=(\d+)/", $info);

            if (empty($number)) {
                $number = $this->http->FindSingleNode("//text()[normalize-space()='Hilton Honors Account Number is']/following::text()[string-length()>5][1]", null, true, "/^(\d+)$/");
            }

            if (empty($number)) {
                $number = $this->http->FindSingleNode("//img[contains(@alt, 'Your Hilton Honors account number is')]/@alt", null, true, "/{$this->opt($this->t('Your Hilton Honors account number is'))}\s*(\d+)/");
            }

            if (!empty($number)) {
                $st->setNumber($number);
            }
            $st->setLogin($number);

            $balance = $this->re("/mi\_point(?:s|_balance)\=(\d+)/", $info);

            if ($balance !== null) {
                $st->setBalance($balance);
            } else {
                $st->setNoBalance(true);
            }

            /*if (preg_match("/[&]mi_name\=(\w+)[&].+[&]mi_lname\=(\w+).+[&]hh\_num\=(\d+)$/", $info, $m)) {
                $st->addProperty('Name', $m[1] . ' ' . $m[2]);
                $st->setNumber($m[3]);
                $st->setNoBalance(true);
            }

            if (preg_match("/[&]mi_name\=(\w+)[&]mi_lname\=(\w+).+[&]mi\_points\=(\d+)[&]mi\_tier\=(\w+)/", $info, $m)) {
                $st->addProperty('Name', $m[1] . ' ' . $m[2]);
                $st->setBalance($m[3]);
                $st->addProperty('Status', $m[4]);
            }*/

            if ($login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was delivered to'))}]/ancestor::*[1]", null, true, "/{$this->opt($this->t('This email was delivered to'))}\s*(\S+[@]\S+\.\S+)/")) {
                $email->setUserEmail(trim($login, '.'));
            }

            $tierLink = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hilton Honors'))}]/preceding::img[contains(@src, 'mi_tier=')][1]/@src");

            if (preg_match("/mi\_tier\=([A-Z]{1})/", $tierLink, $m)) {
                switch ($m[1]) {
                    case 'D':
                        $st->addProperty('Status', 'Diamond');

                        break;

                    case 'G':
                        $st->addProperty('Status', 'Gold');

                        break;

                    case 'S':
                        $st->addProperty('Status', 'Silver');

                        break;

                    case 'B':
                        $st->addProperty('Status', 'Member');
                }
            }
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

    private function eq($field, $node = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return $node . '="' . $s . '"';
        }, $field)) . ')';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
