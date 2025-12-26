<?php

namespace AwardWallet\Engine\tripact\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UpcomingHotel extends \TAccountChecker
{
    public $mailFiles = "tripact/it-39237676.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "@tripactions.com";
    private $detectSubject = [
        "en" => "[Upcoming Trip] Hotel stay on", //[Upcoming Trip] Hotel stay on June 16
    ];

    private $detectCompany = 'tripactions.com';

    private $detectBody = [
        "en" => ['So we thought we would reach out to see if you wanted to round out your trip'],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        $body = $this->http->Response['body'];
//        foreach ($this->detectBody as $lang => $detectBody){
//            foreach ($detectBody as $dBody){
//                if (strpos($body, $dBody) !== false) {
//                    $this->lang = $lang;
//                    break;
//                }
//            }
//        }

        // Travel Agency
        $email->obtainTravelAgency();

        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'{$this->detectCompany}')] | //*[contains(.,'{$this->detectCompany}')]")->length === 0) {
            return false;
        }

        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false
                        && $this->http->XPath->query("//td[starts-with(normalize-space(), 'Your stay at') and contains(., 'starts on')]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHotel(Email $email)
    {
        $text = $this->http->FindSingleNode("//td[starts-with(normalize-space(), 'Your stay at ') and contains(., 'starts on')]");

        if (!empty($text)
                && preg_match("#^\s*Your stay at (?<name>.+?)(?: in (?<addr>.+?))? starts on \w+, (?<date>[^\W\d]+ \d{1,2}, \d{4})\.#", $text, $m)) {
            // Your stay at Club Quarters Hotel in San Francisco starts on Wednesday, June 12, 2019.
            // Your stay at The Chronicle by Supercity Aparthotels starts on Sunday, June 16, 2019.
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->noConfirmation()
                ->traveller($this->http->FindSingleNode("//td[not(.//td) and starts-with(normalize-space(), 'Hey ')]", null, true, "#Hey ([A-Za-z ]+),$#"), false)
            ;

            // Hotel
            $h->hotel()->name($m['name']);

            if (!empty($m['addr'])) {
                $h->hotel()->address($m['addr']);
            } else {
                $h->hotel()->noAddress();
            }

            // Booked
            $h->booked()
                ->checkIn(strtotime($m['date']))
                ->noCheckOut();
        }

        return $email;
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
//        $this->http->log($str);
//        $in = [
//            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d{1,2})[a-z]{2}?,\s*(\d{4})\s*$#iu",// Friday, February 9th, 2018
//        ];
//        $out = [
//            "$2 $1 $3",
//        ];
//        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        if (preg_match("#^\s*\d{1,3}(,\d{1,3})?\.\d{2}\s*$#", $price)) {
            $price = str_replace([',', ' '], '', $price);
        }

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
