<?php

namespace AwardWallet\Engine\qdoba\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Member extends \TAccountChecker
{
    public $mailFiles = "qdoba/it-76803460.eml, qdoba/it-772128030.eml, qdoba/it-772586151.eml, qdoba/it-774049176.eml, qdoba/it-774157193.eml, qdoba/it-774327771.eml";

    public $lang = 'en';

    public static $dict = [
        'en' => [],
    ];

    private $detectFrom = "@rewards.qdoba.com";

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $info = $this->http->FindSingleNode("//text()[contains(., 'PROGRAM STATUS') and contains(., 'POINTS')]");

        if (preg_match("/^\s*PROGRAM STATUS:\s*(?<status>[^\|]+?)\s*\|\s*POINTS:\s*(?<points>\d+)\s*\|\s*ANNUAL VISITS:\s*(?<visits>\d+)\s*$/", $info, $m)) {
            $st->addProperty("Tier", $m['status']);
            $st->addProperty("AnnualVisits", $m['visits']);
            $st->setBalance($m['points']);
        } elseif (preg_match("/^\s*PROGRAM STATUS:\s*(?<status>[^\|]+?)\s*\|\s*POINTS:\s*(?<points>\d+)\s*\|\s*20\d{2} VISITS:\s*(?<visits>\d+)\s*$/", $info, $m)) {
            $st->addProperty("Tier", $m['status']);
            $st->setBalance($m['points']);
        }

        $info = $this->http->FindSingleNode("(//img/@src[contains(., 'Visits%20Status/progressbar_')])[1]");

        if (preg_match("/progressbar_(?<status>Foodie|Chef)_(?<visits>\d+)\./", $info, $m)) {
            $st->addProperty("Tier", $m['status']);
            $st->addProperty("AnnualVisits", $m['visits']);

            $st->setBalance($this->http->FindSingleNode("(//img/@src[contains(., 'Visits%20Status/progressbar_')])[1]/following::text()[normalize-space()][1][contains(., 'Points Earned')]",
                null, true, "/^\s*(\d+)\s*Points Earned/"));
        }

        $userEmail = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This email was sent to:')]",
            null, true, "/This email was sent to:\s*(\S+@\S+\.\w+)\s*$/");

        if (!empty($userEmail)) {
            $st
                ->setLogin($userEmail)
                ->setMembership(true)
            ;

            if (empty($st->getBalance())) {
                $st
                    ->setNoBalance(true);
            }
        }

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
        if (isset($headers['from']) && stripos($headers['from'], $this->detectFrom) !== false) {
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
