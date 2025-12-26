<?php

namespace AwardWallet\Engine\korean\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class StatementImagesOnly extends \TAccountChecker
{
    public $mailFiles = "korean/statements/it-69540004.eml";
    public $subjects = [
        '/^\[KOREAN AIR\] Earn [\d\,]+ SKYPASS Bonus Miles\!$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@market.koreanair.co.kr') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Korean Air SKYPASS members who currently subscribe to Korean Air e-mails')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Dear Skypass Member'))}]")->count() > 0
            && $this->http->XPath->query("//img[contains(@src, 'ums.koreanair.com')]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]market\.koreanair\.co\.kr$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $monthText = $this->http->FindSingleNode("//text()[normalize-space()='Dear Skypass Member']/preceding::text()[normalize-space()][1]");

        if (preg_match("/^\w+\s*\d{4}$/u", $monthText)) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return 0;
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
