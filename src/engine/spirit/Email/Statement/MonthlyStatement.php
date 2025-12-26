<?php

namespace AwardWallet\Engine\spirit\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MonthlyStatement extends \TAccountChecker
{
    public $mailFiles = "spirit/it-61971261.eml";
    public $headers = [
        '/^Account Notification: Free Spirit eStatement$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            //"Free Spirit Number:" => "",
            //"Monthly Miles Posted" => "",
            //"Miles Due To Expire" => "",
            //"Account Balance" => "",
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@save.spirit-airlines.com') !== false) {
            foreach ($this->headers as $header) {
                if (preg_match($header, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Spirit Airlines')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Monthly Statement'))}]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Account Balance')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@]save\.spirit\-airlines\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Free Spirit Number'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d{7,})$/");

        if (!empty($number)) {
            $st->addProperty('Number', $number);
        }

        $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Account Balance')]/following::text()[normalize-space()][1]");
        $balance = str_replace([',', '.'], '', $balance);
        $st->setBalance($balance);

        $dateOfBalance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Activity details through')]", null, true, "/^Activity\s+details\s+through\s+(\w+\s+\d+\,\s\d{4})$/");
        $st->setBalanceDate($this->normalizeDate($dateOfBalance));
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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\w+)\s+(\d+)\,\s+(\d{4})$#",
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }
}
