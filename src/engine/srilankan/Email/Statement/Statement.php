<?php

namespace AwardWallet\Engine\srilankan\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Statement extends \TAccountChecker
{
    public $mailFiles = "srilankan/statements/it-77326781.eml, srilankan/statements/it-77500234.eml";

    public $lang = '';

    public static $dict = [
        'en' => [],
    ];

    private $detectFrom = "flysmilesupdates@srilankan.com";

    private $detectSubject = [
        "FlySmiLes statement is here!",
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear ')]", null, true,
            "/^Dear ([[:alpha:]\- ]+)$/");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[" . $this->eq(['Your FlySmiLes Membership Number']) . "]/preceding::text()[normalize-space()][1][preceding::img[contains(@src, '/FS_HEADER.jpg')]]", null, true,
                "/^([[:alpha:]\- ]+)$/");
        }

        if (!empty($name)) {
            $st->addProperty("Name", $name);
        }

        $number = $this->http->FindSingleNode("//text()[" . $this->eq(['Your FlySmiLes Membership Number']) . "]/following::text()[normalize-space()][1]", null, true,
            "/^\s*(\d+)\s*$/");
        $st
            ->setNumber($number)
            ->setLogin($number)
            ->addProperty("CurrentTier", $this->http->FindSingleNode("//text()[" . $this->eq(['Current Tier']) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*([[:alpha:]]+)\s*$/"))
        ;

        $balance = $this->http->FindSingleNode("//td[" . $this->eq(['FlySmiLes Miles']) . "]/ancestor::tr[1]/following-sibling::tr[2]/td[1]", null, true,
            "/^\s*(\d[\d,. ]*)\s*$/");

        if (is_null($balance)) {
            $balance = $this->http->FindSingleNode("//img[contains(@src, '/newsletter/ACC_SUM.png')]/ancestor::a[contains(@href, 'srilankan.com/flysmiles/my-account/statement')]/preceding::text()[normalize-space()][1]", null, true,
                "/^\s*(\d[\d,. ]*)\s*$/");
        }

        if (!empty($balance)) {
            $balance = str_replace([',', '.', ' '], '', $balance);
        }
        $st->setBalance($balance);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) !== false
            && stripos($headers['subject'], 'statement') !== false
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
