<?php

namespace AwardWallet\Engine\panorama\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourMiles extends \TAccountChecker
{
    public $mailFiles = "panorama/statements/it-74424910.eml";
    public $subjects = [
        '/^Less than two months left to use your miles for new trips!$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@from.flyuia.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Panorama club')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('on your account'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Dear Member'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]from\.flyuia\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $info = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'on your account')]/ancestor::*[1]");

        if (preg_match("/\s(\d+)\s{$this->opt($this->t('miles'))}\D+{$this->opt($this->t('on your account'))}\s*(\d+)\s.+\s(\w+\s\d+\,\s*\d{4})\s*{$this->opt($this->t('will be automatically expired'))}\.$/", $info, $m)) {
            $st->setBalance($m[1])
                ->setLogin($m[2])
                ->setNumber($m[2])
                ->setExpirationDate(strtotime($m[3]));
        }

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear Member'))}]", null, true, "/^{$this->opt($this->t('Dear Member'))}\s+(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
}
