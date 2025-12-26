<?php

namespace AwardWallet\Engine\tripact\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarItinerary extends \TAccountChecker
{
    public $mailFiles = "tripact/it-38207180.eml, tripact/it-38271043.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            "Pay at Kiosk:" => ["Pay at Kiosk:", "Direct bill:"],
        ],
    ];

    private $detectFrom = "@tripactions.com";
    private $detectSubject = [
        "en" => "Booking |", //Confirmed - Enterprise Booking | Christopher Shawn Maynor (KKPEDJ)
    ];

    private $detectCompany = 'tripactions.com';

    private $detectBody = [
        "en" => ["Driver Name:"],
    ];

    private $rentalProvider = [
        "rentacar"   => ["Enterprise"],
        "hertz"      => ["Hertz"],
        "ezrentacar" => ["E-Z"],
        "national"   => ["National"],
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
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts("Record locator") . "]", null, true, "#^\s*Record locator\s+([A-Z\d]{5,})\s*$#");

        if (empty($conf) && preg_match("#\s*\(\s?([A-Z\d]{5,})\s?\)\s*$#", $parser->getSubject(), $m)) {
            $conf = $m[1];
        }
        $email->ota()->confirmation($conf, "Record locator");
        $this->parseCar($email);

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

    private function parseCar(Email $email)
    {
        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Car Confirmation")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#"), $this->t("Car Confirmation"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Driver Name:")) . "]/following::text()[normalize-space()][1]"))
        ;

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("You're good to go!")) . "])[1]"))) {
            $r->general()->status("Confirmed");
        } elseif (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("You canceled your car")) . "])[1]"))) {
            $r->general()
                ->status("Canceled")
                ->cancelled();
        }

        // Price
        $total = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Pay at Kiosk:")) . "][1]/following::text()[normalize-space()][1]/ancestor::td[1]//text()[normalize-space()]"));

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $r->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }
        $tax = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Taxes:")) . "][1]/following::text()[normalize-space()][1]/ancestor::td[1]//text()[normalize-space()]"));

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $tax, $m)) {
            $currency = $this->currency($m['curr']);

            if ((!empty($r->getPrice()) && $r->getPrice()->getCurrencyCode() === $currency) || (empty($r->getPrice()))) {
                $r->price()
                    ->currency($this->currency($m['curr']))
                    ->fee("Taxes", $this->amount($m['amount']));
            }
        }
        $fXpath = "//text()[" . $this->eq(["Trip fee:"]) . "][1]";
        $feeNodes = $this->http->XPath->query($fXpath);

        foreach ($feeNodes as $fRoot) {
            $feeName = $this->http->FindSingleNode(".", $fRoot);
            $fee = implode("\n", $this->http->FindNodes("./following::text()[normalize-space()][1]/ancestor::td[1]//text()[normalize-space()]", $fRoot));

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $fee, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $fee, $m)) {
                $currency = $this->currency($m['curr']);

                if ((!empty($r->getPrice()) && $r->getPrice()->getCurrencyCode() === $currency) || (empty($r->getPrice()))) {
                    $r->price()
                        ->currency($this->currency($m['curr']))
                        ->fee(trim($feeName, ': '), $this->amount($m['amount']));
                }
            }
        }

        // Pick Up
        $r->pickup()
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Pick Up:")) . "]/following::text()[normalize-space()][1]")))
            ->location($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Pick Up:")) . "]/following::img[contains(@src, 'BookingCarPin')])[1]/ancestor::tr[1]"))
        ;

        // Drop Off
        if (empty($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Drop Off:")) . "]")) && $r->getCancelled()) {
            $r->dropoff()
                ->noDate()
                ->noLocation();
        } else {
            $r->dropoff()
                ->date($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Drop Off:")) . "]/following::text()[normalize-space()][1]")))
                ->location($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Drop Off:")) . "]/following::img[contains(@src, 'BookingCarPin')])[1]/ancestor::tr[1]"))
            ;
        }

        // Car
        $r->car()
            ->model($this->http->FindSingleNode("(//text()[{$this->eq($this->t("Pick Up:"))}]/following::img[contains(@alt, 'car')])[1]/ancestor::tr[1]/td[1]"))
            ->image($this->http->FindSingleNode("(//text()[{$this->eq($this->t("Pick Up:"))}]/following::img[contains(@alt, 'car')])[1]/@src"))
        ;

        // Program
        $provider = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Pick Up:")) . "]/following::img[contains(@src, 'BookingCarPin')])[1]/preceding::text()[normalize-space()][1]");
        $findedProvider = false;

        foreach ($this->rentalProvider as $code => $names) {
            foreach ($names as $name) {
                if (strcasecmp($provider, $name) === 0 || preg_match("#^\s*" . $name . "\s+#", $provider)) {
                    $r->program()->code($code);
                    $findedProvider = true;

                    break 2;
                }
            }
        }

        if ($findedProvider == false) {
            $r->extra()->company($provider);
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
