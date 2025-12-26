<?php

namespace AwardWallet\Engine\tripact\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelOutOfPolicy extends \TAccountChecker
{
    public $mailFiles = "tripact/it-38246404.eml, tripact/it-38247635.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "@tripactions.com";
    private $detectSubject = [
        "en" => "out of policy hotel booking", //[Notice] Please review Art Brat's out of policy hotel booking
        "Your out-of-policy hotel booking has been denied", //[Notice] Your out-of-policy hotel booking has been denied - reservation canceled
    ];

    private $detectCompany = 'tripactions.com';

    private $detectBody = [
        "en" => ["Booking Details"],
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

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->eq("Record locator") . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");
        }
        $email->ota()->confirmation($conf, "Record locator");

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
                if (strpos($body, $dBody) !== false) {
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
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Traveler Name:")) . "]/following::text()[normalize-space()][1]"))
        ;

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("canceled your reservation below")) . "])[1]"))) {
            $h->general()
                ->status("Canceled")
                ->cancelled();
        }

        // Price
        $total = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Price Paid:")) . "][1]/following::text()[normalize-space()][1]/ancestor::td[1]//text()[normalize-space()]"));

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Location:")) . "]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "#^\s*([^,]+), #"))
            ->address($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Location:")) . "]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "#^\s*[^,]+, (.+)#"))
        ;

        $dates = $this->http->FindNodes("//text()[" . $this->eq($this->t("Travel Dates:")) . "]/following::text()[normalize-space()][1]/ancestor::td[1]//text()[normalize-space()]");

        if (count($dates) == 2) {
            $h->booked()
                ->checkIn(strtotime($dates[0]))
                ->checkOut(strtotime($dates[1]));
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
//            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d{1,2}),\s*(\d{4})\s*at\s*(\d+:\d+(\s*[ap]m)?)\s*$#iu",// Thu, Feb 14, 2019 at 12:00 pm
//        ];
//        $out = [
//            "$2 $1 $3, $4",
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
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
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
