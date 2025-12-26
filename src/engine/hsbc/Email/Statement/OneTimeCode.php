<?php

namespace AwardWallet\Engine\hsbc\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OneTimeCode extends \TAccountChecker
{
    public $mailFiles = "hsbc/statements/it-103425666.eml, hsbc/statements/it-151570984.eml";
    public $subjects = [
        'HSBC One-Time Code For Log On',
        'Activation code for recognizing your browser',
    ];

    public $detectBody = [
        'en' => [
            'Your One-Time Code for Log On is',
            'Your activation code is :',
        ],
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
//            'Dear' => '',
            'Your One-Time Code for Log On is' => ['Your One-Time Code for Log On is', 'Your activation code is :'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && $this->detectEmailFromProvider($headers['from']) === true) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//text()[contains(., '©')]/following::text()[contains(normalize-space(), 'HSBC Bank')]")->length === 0
            && $this->http->XPath->query("//text()[contains(., '©') and contains(normalize-space(), 'HSBC Bank')]")->length === 0
            && $this->http->XPath->query("//a[contains(@href, '.hsbc.com')]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $dBody)
            if ($this->http->XPath->query("//text()[".$this->contains($dBody)."]")->length > 0) {
                return true;
            }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@.*\.hsbc\./', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $otс = $email->add()->oneTimeCode();
        $code = $this->http->FindSingleNode("//text()[".$this->starts($this->t("Your One-Time Code for Log On is"))."]", null, true, "/".$this->opt($this->t("Your One-Time Code for Log On is"))."\s*\(\d{2}\)\s*(\d{6})\./");

        if (!empty($code)) {
            $otс->setCode($code);

            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->opt($this->t('Dear'))}\s+(\D+)\,$/");
            if (!empty($name)) {
                $st = $email->add()->statement();
                $st
                    ->addProperty('Name', trim($name, ','))
                    ->setNoBalance(true);
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

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
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
            return preg_quote($s);
        }, $field)) . ')';
    }
}
