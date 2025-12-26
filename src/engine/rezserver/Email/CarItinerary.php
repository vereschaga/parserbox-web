<?php

namespace AwardWallet\Engine\rezserver\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarItinerary extends \TAccountChecker
{
    public $mailFiles = "rezserver/it-33477944.eml, rezserver/it-59792751.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
        ],
    ];

    private $detectFrom = "@rezserver.com";
    private $detectSubject = [
        "en" => " Car Itinerary", //Your %Company% Car Itinerary
    ];

    private $detectCompany = 'rezserver.com';

    private $detectBody = [
        "en"  => "Your Rental Car Reservation",
        "en2" => "Rental Car Cancellation",
    ];

    private $travelAgencies = [
        "aaatravel" => ["AAA"],
        "autoslash" => ["AutoSlash.com"],
    ];

    private $rentalProvider = [
        "rentacar"     => ["Enterprise Rent-A-Car"],
        "hertz"        => ["Hertz Corporation"],
        "perfectdrive" => ["Budget Rent a Car"],
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
        $ta = $this->re("#Your (.+) Car Itinerary#", $parser->getSubject());

        if (empty($ta)) {
            $ta = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Trip #:")) . "]", null, true, "#^\s*(.+?)\s*" . $this->preg_implode($this->t("Trip #:")) . "\s*$#");
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
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Trip #:")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*(\d{5,})\s*$#"),
                    trim($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Trip #:")) . "]"), ":"));

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

    private function parseCar(Email $email)
    {
        $r = $email->add()->rental();

        // General
        $confirmation = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Confirmation #:")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([\dA-Z]{5,})\s*$#");
        $r->general()
            ->traveller($this->http->FindSingleNode("//td[" . $this->eq($this->t("Driver Name:")) . "]/following-sibling::td[normalize-space()][1]"))
            ->status($this->http->FindSingleNode("//td[" . $this->eq($this->t("Booking Status:")) . "]/following-sibling::td[normalize-space()][1]"));

        if (!empty($confirmation)) {
            $r->general()
                ->confirmation($confirmation, trim($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Confirmation #:")) . "]"), ":"));
        }

        if ($r->getStatus() == 'Cancelled') {
            $r->general()->cancelled();

            if (empty($confirmation)) {
                $r->general()
                    ->noConfirmation();
            }
        }

        // Price
        $cost = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Summary of Charges")) . "]/following::td[" . $this->eq($this->t("Subtotal:")) . "][1]/following-sibling::td[normalize-space()][1]", null, true, "#^\D*(\d[\d., ]*)\D*$#"));
        $currency = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Rental car prices are in")) . "][1]", null, true, "#" . $this->preg_implode($this->t("Rental car prices are in")) . "\s+([A-Z]{3})\b#");

        if (!empty($cost)) {
            $r->price()
                ->cost($cost)
                ->currency($currency);
        }

        $tax = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Summary of Charges")) . "]/following::td[" . $this->eq($this->t("Taxes and Fees:")) . "][1]/following-sibling::td[normalize-space()][1]", null, true, "#^\D*(\d[\d., ]*)\D*$#"));

        if (!empty($tax)) {
            $r->price()->fee("Taxes and Fees", $tax);
        }

        // Pick Up
        $r->pickup()
            ->date($this->normalizeDate($this->http->FindSingleNode("//td[" . $this->eq($this->t("Pick-up Details:")) . "]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]")))
            ->location($this->http->FindSingleNode("//td[" . $this->eq($this->t("Pick-up Details:")) . "]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][2]"))
        ;

        // Drop Off
        $r->dropoff()
            ->date($this->normalizeDate($this->http->FindSingleNode("//td[" . $this->eq($this->t("Drop-Off Details:")) . "]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]")))
            ->location($this->http->FindSingleNode("//td[" . $this->eq($this->t("Drop-Off Details:")) . "]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][2]"))
        ;

        // Car
        $image = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Car Reservation Details")) . "]/following::text()[normalize-space()][1]/ancestor::td[1]/preceding-sibling::td//img[1]/@src");

        if (!empty($image)) {
            $r->car()
                ->image($image);
        }

        $type = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Car Type:")) . "]/following-sibling::td[normalize-space()][1]");

        if (!empty($type)) {
            $r->car()
                ->type($type);
        }

        $model = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Car Reservation Details")) . "]/following::text()[" . $this->eq($this->t("Rental Partner:")) . "][1]/preceding::text()[normalize-space()][not(contains(normalize-space(), 'AAA'))][1]");

        if (!empty($model) && $model !== 'Car Reservation Details') {
            $r->car()
                ->model($model);
        }

        $this->logger->debug("//text()[" . $this->eq($this->t("Car Reservation Details")) . "]/following::text()[" . $this->eq($this->t("Rental Partner:")) . "][1]/preceding::text()[normalize-space()][not(contains(normalize-space(), 'AAA'))][1]");

        // Program
        $provider = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Rental Partner:")) . "]/following-sibling::td[normalize-space()][1]");
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

        /* Example:
         * Hertz Gold Plus Rewards: 66417992
         * Promotional Coupon (PC): 204409
         */
        $account = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Loyalty/Reward Details:")) . "]/following-sibling::td[normalize-space()][1]", null, true, "#Rewards:\s*([A-Z\d]{5,})#i");

        if (!empty($account)) {
            $r->program()->account($account, false);
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
        $in = [
            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d{1,2}),\s*(\d{4})\s*at\s*(\d+:\d+(\s*[ap]m)?)\s*$#iu", // Thu, Feb 14, 2019 at 12:00 pm
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
