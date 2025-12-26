<?php

namespace AwardWallet\Engine\spirit\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EStatement extends \TAccountChecker
{
    public $mailFiles = "spirit/statements/it-187097814.eml, spirit/statements/it-187436861.eml, spirit/statements/it-187437793.eml";
    public $subjects = [
        'Your eStatement for',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@save.spirit-airlines.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Spirit Airlines')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Free Spirit #'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('E-Statement for'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]save\.spirit\-airlines\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hey'))}]", null, true, "/{$this->opt($this->t('Hey'))}\s*(\w+)(?:\,|$)/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Free Spirit #'))}]", null, true, "/[#](\d+)$/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('YOU HAVE'))}]/following::td[1][{$this->contains($this->t('POINTS'))}]", null, true, "/^\s*([\d\,]+)\s*{$this->opt($this->t('POINTS'))}/");

        if (!empty($balance)) {
            $st->setBalance(str_replace(',', '', $balance));
        } else {
            $st->setNoBalance(true);
        }

        $sqpPoints = $this->http->FindSingleNode("//img[contains(@src, 'mi_points')]/@src", null, true, "/mi_points[=](\d+)$/");

        if (!empty($sqpPoints)) {
            $st->addProperty('StatusQualifyingPoints', $sqpPoints);
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
