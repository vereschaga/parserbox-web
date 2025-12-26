<?php

namespace AwardWallet\Engine\rezserver\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelItinerary extends \TAccountChecker
{
    public $mailFiles = "rezserver/it-2431427.eml, rezserver/it-33826309.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "@rezserver.com";
    private $detectSubject = [
        "en" => " Hotel Itinerary", //Your %Company% Hotel Itinerary
    ];

    private $detectCompany = 'rezserver.com';

    private $detectBody = [
        "en" => "Hotel Information",
    ];

    private $travelAgencies = [
        "aaatravel" => ["AAA"],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        $body = $this->http->Response['body'];
//        foreach ($this->detectBody as $lang => $detectBody){
//            if (strpos($body, $detectBody) !== false) {
//                $this->lang = $lang;
//                break;
//            }
//        }

        // Travel Agency
        $email->obtainTravelAgency();
        $ta = $this->re("#Your (.+) Hotel Itinerary#", $parser->getSubject());

        if (empty($ta)) {
            $ta = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Thanks for booking with")) . "]", null, true, "#" . $this->preg_implode($this->t("Thanks for booking with")) . "\s*(.+?)\s*!\s*$#");
        }

        foreach ($this->travelAgencies as $code => $names) {
            foreach ($names as $name) {
                if (strcasecmp($ta, $name) === 0 || preg_match("#^\s*" . $name . "\s+#", $ta)) {
                    $email->ota()->code($code);

                    break 2;
                }
            }
        }
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Trip Number:")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*(\d{5,})\s*$#"),
                    "Trip Number");

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
            if (strpos($body, $detectBody) !== false) {
                return true;
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
            ->travellers(array_unique($this->http->FindNodes("//td[" . $this->eq($this->t("Guest Name:")) . "]/following-sibling::td[normalize-space()][1]")))
            ->status($this->http->FindSingleNode("//td[" . $this->eq($this->t("Booking Status:")) . "]/following-sibling::td[normalize-space()][1]"))
        ;
        $confs = array_unique($this->http->FindNodes("//td[" . $this->eq($this->t("Confirmation #:")) . "]/following-sibling::td[normalize-space()][1]",
            null, "#^\s*([\dA-Z]{5,})\s*$#"));

        foreach ($confs as $conf) {
            $h->general()->confirmation($conf);
        }

        if ($h->getStatus() == 'Cancelled') {
            $h->general()->cancelled();
        }

        // Price
        $currency = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("All prices are in")) . "][1]", null, true, "#" . $this->preg_implode($this->t("All prices are in")) . "\s+([A-Z]{3})\b#");

        if (empty($currency)) {
            $currency = $this->currency(preg_replace("#[., \d]*\d[., \d]*#", ' ', $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Summary of Charges")) . "]/following::td[" . $this->starts($this->t("Total Cost:")) . "][1]")));
        }
        $h->price()
            ->cost($this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Summary of Charges")) . "]/following::td[" . $this->eq($this->t("Room Subtotal:")) . "][1]/following-sibling::td[normalize-space()][1]", null, true, "#^\D*(\d[\d., ]*)\D*$#")))
            ->total($this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Summary of Charges")) . "]/following::td[" . $this->starts($this->t("Total Cost:")) . "][1]", null, true, "#:\D*(\d[\d., ]*)\D*$#")))
            ->currency($currency)
        ;

        $tax = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Summary of Charges")) . "]/following::td[" . $this->eq($this->t("Taxes and Fees:")) . "][1]/following-sibling::td[normalize-space()][1]", null, true, "#^\D*(\d[\d., ]*)\D*$#"));

        if (!empty($tax)) {
            $h->price()->fee("Taxes and Fees", $tax);
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotel Information")) . "]/following::td[" . $this->eq($this->t("Hotel Name:")) . "][1]/following-sibling::td[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Hotel Information")) . "]/following::td[" . $this->eq($this->t("Address:")) . "])[1]/following-sibling::td[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Hotel Information")) . "]/following::td[" . $this->eq($this->t("Phone:")) . "])[1]/following-sibling::td[normalize-space()][1]"))
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//td[" . $this->eq($this->t("Check In:")) . "]/following-sibling::td[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//td[" . $this->eq($this->t("Check Out:")) . "]/following-sibling::td[normalize-space()][1]")))
            ->rooms($this->http->FindSingleNode("//td[" . $this->eq($this->t("Rooms:")) . "]/following-sibling::td[normalize-space()][1]"))
            ->guests($this->http->FindSingleNode("//td[" . $this->eq($this->t("Guests:")) . "]/following-sibling::td[normalize-space()][1]"))
        ;

        // Rooms
        $h->addRoom()
            ->setDescription($this->http->FindSingleNode("//td[" . $this->eq($this->t("Room Type:")) . "]/following-sibling::td[normalize-space()][1]"))
            ->setRate($this->http->FindSingleNode("//td[" . $this->eq($this->t("Room Cost (per night):")) . "]/following-sibling::td[normalize-space()][1]"), true)
        ;

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
        $in = [
            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d{1,2}),\s*(\d{4})\s*(\d+:\d+(\s*[ap]m)?)\s*$#iu", // Saturday, April 12, 2014 15:00
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

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
        if ($code = $this->re("#\b([A-Z]{3})\b$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
            '₹' => 'INR',
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
