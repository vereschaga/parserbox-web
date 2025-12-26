<?php

namespace AwardWallet\Engine\israel\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Membership extends \TAccountChecker
{
    public $mailFiles = "israel/statements/it-788558163.eml";

    public $subjects = [
        "Welcome to EL AL’s Matmid Frequent Flyer Club",
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@elal.co.il') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('It’s more than an airline, It’s Israel'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Welcome to the EL AL Matmid Frequent Flyer!'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Your membership number is:'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]elal\.co\.il$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Welcome to the EL AL Matmid Frequent Flyer!'))}]/ancestor::table[1]/descendant::tr[1]", null, true, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/");

        if ($name !== null) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your membership number is:'))}]/ancestor::td[1]", null, true, "/^Your\s*membership\s*number\s*is\:(\d+)$/");

        if ($number !== null) {
            $st->setNumber($number);
            $st->setLogin($number);
        }

        $st->setNoBalance(true);

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
