<?php

namespace AwardWallet\Engine\gha\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Membership extends \TAccountChecker
{
    public $mailFiles = "gha/statements/it-76634523.eml, gha/statements/it-76635168.eml, gha/statements/it-76635169.eml, gha/statements/it-76705906.eml, gha/statements/it-76793432.eml";
    public $lang = '';

    public $detectLang = [
        'en' => ['MEMBER'],
        'zh' => ['会员'],
    ];

    public static $dictionary = [
        "en" => [
            //'MEMBERSHIP BENEFITS' => '',
        ],
        "zh" => [
            'MEMBERSHIP BENEFITS'   => '会员优惠',
            ' MEMBER'               => '会员:',
            'Global Hotel Alliance' => ['全球酒店联盟', 'Global Hotel Alliance'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->assignLang() == true) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Global Hotel Alliance'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t(' MEMBER'))} or {$this->contains($this->t('MEMBERSHIP BENEFITS'))}]")->length > 0
                && $this->http->XPath->query("//img[contains(@src, 'dscvry')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.gha\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $info = $this->http->FindSingleNode("//text()[{$this->eq($this->t('MEMBERSHIP BENEFITS'))}]/ancestor::table[1]");

        if (!empty($info)) {
            //$this->logger->warning($info);
            if (preg_match("/^\s*(?<name>\D+)\s+[#](?<number>\d+)\s*(?<status>\D+)\|\s*{$this->opt($this->t('MEMBERSHIP BENEFITS'))}\s*$/", $info, $m)) {
                $st->addProperty('Name', $m['name']);
                $st->addProperty('Status', $m['status']);
                $st->setNumber($m['number']);
                $st->setNoBalance(true);
            } elseif (preg_match("/^[#](?<number>\d+)\s*(?<status>\D+)\|\s*{$this->opt($this->t('MEMBERSHIP BENEFITS'))}\s*$/", $info, $m)) {
                $st->addProperty('Status', $m['status']);
                $st->setNumber($m['number']);
                $st->setNoBalance(true);
            }
        }

        if (empty($info)) {
            $info = $this->http->FindSingleNode("//text()[{$this->contains($this->t(' MEMBER'))} and {$this->contains($this->t('#'))}]/ancestor::tr[1]");

            if (preg_match("/^\s*(?<name>\D+)\s+(?<status>\w+)\s*MEMBER\:\s*[#]\s*(?<number>\d+)\s*$/s", $info, $m)) {
                $st->addProperty('Name', $m['name']);
                $st->addProperty('Status', $m['status']);
                $st->setNumber($m['number']);
                $st->setNoBalance(true);
            } elseif (preg_match("/^(?<status>\D+)\:\s*[#]\s*(?<number>\d+)\s*$/", $info, $m)) {
                $st->addProperty('Status', $m['status']);
                $st->setNumber($m['number']);
                $st->setNoBalance(true);
            }
        }

        //it-76793432
        if (empty($info) && !empty($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Is this email')]/following::text()[normalize-space()='Welcome to DISCOVERY.']"))) {
            $infoArray = $this->http->FindNodes("//text()[{$this->contains($this->t(' MEMBER'))} and {$this->contains($this->t('|'))}]/ancestor::tr[2]/descendant::text()[normalize-space()]");
            $info = implode(" ", $infoArray);
            $this->logger->warning($info);

            if (preg_match("/^\s*(?<name>\D+)\s+(?<status>\w+)\s*MEMBER\s*\:?\|?\s*[#]\s*(?<number>\d+)\s*$/s", $info, $m)) {
                $st->addProperty('Name', $m['name']);
                $st->addProperty('Status', $m['status']);
                $st->setNumber($m['number']);
                $st->setNoBalance(true);
            }
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
