<?php

namespace AwardWallet\Engine\qantas\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourFrequentFlyerNumber extends \TAccountChecker
{
    public $mailFiles = "qantas/statements/it-240643126.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = [
        "frequent_flyer@qantas.com.au"
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        if ($headers['subject'] === 'Your Qantas Frequent Flyer number') {
            return true;
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {

        $st = $email->add()->statement();

        // Number
        $number = $this->http->FindSingleNode("//text()[" . $this->starts("Your Qantas Frequent Flyer number is") . "][1]", null, true,
            "/^\s*Your Qantas Frequent Flyer number is\s+(\d{5,})\.\s*$/u");

        $st->setNumber($number);

        // Name
        $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true,
            "#^\s*" . $this->preg_implode($this->t("Dear ")) . "\s*([^\d\W]+(?: [^\d\W]+){0,4})\s*,\s*$#u");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        // Balance
        $st->setNoBalance(true);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
