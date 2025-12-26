<?php

namespace AwardWallet\Engine\japanair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NoticeOfConfirmation extends \TAccountChecker
{
    public $mailFiles = "japanair/statements/it-77214216.eml";
    public $subjects = [
        '/JALそらとも倶楽部」搭乗実績反映のお知らせ/',
    ];

    public $lang = 'ja';

    public static $dictionary = [
        "ja" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@jal.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Japan Airlines')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('グループの合計搭乗回数'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('合計搭乗回数'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]jal\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), '合計搭乗回数')]/following::text()[normalize-space()][1]", null, true, "/^(\d+){$this->opt($this->t('回'))}/");
        $st->setBalance($balance);

        $name = $this->http->FindSingleNode("//text()[contains(normalize-space(), '搭乗実績反映をお知らせいたします。')]/preceding::text()[normalize-space()][1]");
        $st->addProperty('Name', $name);

        $date = $this->http->FindSingleNode("//text()[contains(normalize-space(), '搭乗実績反映をお知らせいたします。')]/preceding::text()[normalize-space()][2]");
        $st->setBalanceDate($this->normalizeDate($date));

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
