<?php

namespace AwardWallet\Engine\officedepot\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ImageSrc extends \TAccountChecker
{
    public $mailFiles = "officedepot/it-67944385.eml, officedepot/it-68624092.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e.officedepot.com') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.officedepot\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $src = urldecode($this->http->FindSingleNode("(//img[contains(@src, '&mi_member_id=')]/@src)[1]"));

        if (!empty($src)) {
            if (preg_match("/&mi_member_id=(\d++)(?:&|$)/", $src, $m)) {
                $st->setNumber($m[1]);
            }

            if (preg_match("/&mi_name=([^&]+)(?:&|$)/", $src, $m)) {
                $st->addProperty("Name", $m[1]);
            }

            if (preg_match("/&mi_balance_date=(\d{2})(\d{2})(\d{2})(?:&|$)/", $src, $m)) {
                $st->setBalanceDate(strtotime($m[2] . '.' . $m[1] . '.20' . $m[3]));
            }

            if (preg_match("/&mi_rewards_available=(\d[\d.]*)(?:&|$)/", $src, $m)) {
                $st->setBalance($m[1]);
            }

            if (preg_match("/&mi_tier=([^&]+)(?:&|$)/", $src, $m)) {
                $st->addProperty("Status", $m[1]);
            }
        } elseif (stripos($parser->getCleanFrom(), 'rewards@e.officedepot.com') !== false) {
            $st->setMembership(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return [];
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
