<?php

namespace AwardWallet\Engine\japanair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class InfoGroup extends \TAccountChecker
{
    public $mailFiles = "japanair/statements/it-77214282.eml";

    public $lang = 'ja';

    public static $dictionary = [
        "ja" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Japan Airlines')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('お問い合わせ先'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('グループ説明文'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]jal\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $st->setNoBalance(true);

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), '「JALそらとも倶楽部」のグループ実績が確定いたしましたのでお知らせいたします。')]/preceding::text()[normalize-space()][1]");
        $st->addProperty('Name', $name);

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

    private function normalizeDate($str)
    {
        $this->logger->debug('IN-' . $str);
        $in = [
            '/^(\d{4})[年](\d+)[月](\d+)[日]$/u', // 2019年8月8日
        ];
        $out = [
            '$3.$2.$1',
        ];
        $str = preg_replace($in, $out, $str);
        $this->logger->debug('OUT-' . $str);

        return strtotime($str);
    }
}
