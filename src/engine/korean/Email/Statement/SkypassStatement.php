<?php

namespace AwardWallet\Engine\korean\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SkypassStatement extends \TAccountChecker
{
    public $mailFiles = "korean/statements/it-65874417.eml, korean/statements/it-67926836.eml, korean/statements/it-69970211.eml";
    public $subjects = [
        '/^\[KOREAN AIR\] Your SKYPASS D+\,/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Summary for' => ['Summary for', 'Mileage Status for'],
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Korean Air')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('SKYPASS MILEAGE BALANCE'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Summary for'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]market\.koreanair\.co\.kr$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $nameText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Summary for'))}]/ancestor::*[1]");

        if (preg_match("/^{$this->opt($this->t('Summary for'))}\s+(\D+)\s+as\s*o?f?\s*(\w+\s*\d+\,\s+\d{4})$/", $nameText, $m)) {
            $st->addProperty('Name', trim($m[1], ','));
            $st->setBalanceDate(strtotime($m[2]));
        }

        $balance = str_replace(',', '', $this->http->FindSingleNode("//td[starts-with(normalize-space(), 'Total Miles') and contains(normalize-space(), 'Earned')]/descendant::text()[normalize-space()][last()]", null, true, "/^([\d\,]+)$/"));

        if (empty($balance)) {
            $balance = str_replace(',', '', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SKYPASS MILEAGE BALANCE')]/following::td[starts-with(normalize-space(), 'Total Miles') and contains(normalize-space(), 'Earned')][last()]/descendant::text()[normalize-space()][last()]", null, true, "/^([\d\,]+)$/"));
        }

        if ($balance == null) {
            $balance = str_replace(',', '', $this->http->FindSingleNode("//td[starts-with(normalize-space(), 'Miles Available')]/following::text()[normalize-space()][1]", null, true, "/^([\d\,]+)$/"));
        }
        $st->setBalance($balance);

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
