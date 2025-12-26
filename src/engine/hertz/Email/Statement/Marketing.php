<?php

namespace AwardWallet\Engine\hertz\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Marketing extends \TAccountChecker
{
    public $mailFiles = "hertz/statements/it-73465733.eml";

    public static $dictionary = [
        "en" => [
            'RESERVATIONS | DISCOUNTS & COUPONS' => 'RESERVATIONS | DISCOUNTS & COUPONS',
        ],
    ];

    private $detectFrom = "marketing@emails.hertz.com";

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["RESERVATIONS | DISCOUNTS & COUPONS"]) && $this->http->XPath->query("//td[not(normalize-space())][//img/ancestor::a[contains(@href, 'emails.hertz.com')]]/"
                    . "following-sibling::td[normalize-space()][" . $this->eq($dict["RESERVATIONS | DISCOUNTS & COUPONS"]) . "]")->length > 0) {
                $email->setIsJunk(true);

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!empty($headers["from"]) && stripos($headers["from"], $this->detectFrom) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug($date);
        $in = [
            "#^\s*(\w+) (\d{1,2}) (\d{2}) (\d{1,2}:\d{2} [ap]m)b?\s*$#i", // Mar 28 14 6:30 PM; Mar 09 14 11:50 PMb
        ];
        $out = [
            "$2 $1 20$3, $4",
        ];
        $date = preg_replace($in, $out, $date);
//        $this->logger->debug($date);
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
