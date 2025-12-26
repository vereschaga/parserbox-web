<?php

namespace AwardWallet\Engine\yes2you\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class CashAccount extends \TAccountChecker
{
    public $mailFiles = "yes2you/it-79233524.eml, yes2you/it-79280875.eml, yes2you/it-79451248.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $lang = 'en';

    private $detectBody = [
        'Kohl’s Cash® redeemable in store or online ',
        'KOHL\'S CASH NUMBER:',
        'REWARDS ID:',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.kohls.com') !== false || stripos($from, '@kohls.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'rewards@am.kohls.com') !== false && stripos($headers['from'], 'Kohls@t.kohls.com') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains(['.kohls.com'], '@href') . "]")->length === 0
            && $this->http->XPath->query("//*[" . $this->contains(['Kohl\'s Rewards <rewards@am.kohls.com>']) . "]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $st = $email->add()->statement();

        // Type 1
        //                      Being a Kohl’s Rewards® member really adds up!
        //   |||||||||||||||    Amount: $5
        //   |||||||||||||||    PIN: 1234
        //   262611088638507    Exp: 03/03/21

        $number = $this->http->FindSingleNode("//text()[" . $this->eq("Amount:") . "]/preceding::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{10,})\s*$/");

        if (!empty($number)) {
            $st
                // it's coupon not a account
                ->setMembership(true)
                ->setNoBalance(true)
            ;

            return $email;
        }

        // Type 2
        //
        // KOHL'S CASH VALUE: $14.99
        // KOHL'S CASH NUMBER: 225060562898857
        // PIN: 1234 VALID: 12/09/2020-12/29/2020
        $number = $this->http->FindSingleNode("//text()[" . $this->eq("KOHL'S CASH NUMBER:") . "]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{10,})\s*$/");

        $balance = $this->http->FindSingleNode("//text()[" . $this->eq("KOHL'S CASH VALUE:") . "]/following::text()[normalize-space()][1]",
            null, true, "/^\s*\\$(\d[\d\. ,]*)\s*$/");

        if (!empty($number) && !empty($balance)) {
            // it's coupon not a account
            $st
                ->setMembership(true)
                ->setNoBalance(true)
            ;

            return $email;
        }

        // Type 3
        //
        //                     ALEXI VERESCHAGA
        //              REWARDS ID: 83343979593
        //   CURRENT POINTS: 0 PTS AS OF: 05/28
        $number = $this->http->FindSingleNode("//text()[" . $this->eq("REWARDS ID:") . "]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{10,})\s*$/");

        if (!empty($number)) {
            $st
                ->setNumber($number)
                ->setNoBalance(true) // program changed, points transferred to $
            ;

            $st->addProperty("Name", $this->http->FindSingleNode("//text()[" . $this->eq("REWARDS ID:") . "]/preceding::text()[normalize-space()][1]",
                null, true, "/^\s*([[:alpha:]][[:alpha:] \-]+)\s*$/"));

            return $email;
        }

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
