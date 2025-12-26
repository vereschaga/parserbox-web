<?php

namespace AwardWallet\Engine\orange\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class LastYear extends \TAccountChecker
{
    public $mailFiles = "orange/statements/it-75085393.eml";
    public $subjects = [
        '/Take a peek at 2020 in the rearview mirror$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@gasbuddyemail.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'GasBuddy')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your activity this year'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Points earned'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]gasbuddyemail\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $balance = $this->http->FindSingleNode("//text()[normalize-space()='Points earned']/preceding::text()[normalize-space()][1]");
        $st->setBalance(str_replace(",", "", $balance));

        $dateOfBalance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Data updated as of')]", null, true, "/{$this->opt($this->t('Data updated as of'))}\s*([\d\/]+)./");

        if (!empty($dateOfBalance)) {
            $st->setBalanceDate($this->normalizeDate($dateOfBalance));
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

    private function normalizeDate($date)
    {
        $in = [
            //12/22/20
            '#^(\d{2})\/(\d{2})\/(\d{2})$#iu',
        ];
        $out = [
            '$2.$1.20$3',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }
}
