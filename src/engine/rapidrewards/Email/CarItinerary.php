<?php

namespace AwardWallet\Engine\rapidrewards\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarItinerary extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-33993283.eml, rapidrewards/it-34977116.eml, rezserver/it-33477944.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "southwest.com";
    private $detectSubject = [
        "en" => " car reservation", //William's 03/30 Tampa, FL - TPA car reservation (1051980675COUNT)
    ];

    private $detectCompany = 'Southwest Airlines';

    private $detectBody = [
        "en"  => "Thanks for letting us help you reserve a car with",
        "en2" => "Your cancelation request has been submitted",
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
        // Travel Agency
        $email->obtainTravelAgency();
        $account = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("RAPID REWARDS #")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*(\d{5,})\s*$#");

        if (!empty($account)) {
            $email->ota()->account($account, false);
        }

        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation #")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([\dA-Z]{5,})\s*$#"),
                    trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation #")) . "]"), ":"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("DRIVER NAME")) . "]/following::text()[normalize-space()][1]"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//td[" . $this->starts($this->t("Confirmation date:")) . "][1]", null, true, "#:\s*(.+)#")))
        ;

        if (!empty($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Your cancelation request has been submitted")) . "][1]"))) {
            $r->general()
                ->status('Cancelled')
                ->cancelled();
        }

        // Price
        $r->price()
            ->cost($this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total cost")) . "]/following::td[" . $this->eq($this->t("Base rate")) . "][1]/following-sibling::td[normalize-space()][2]", null, true, "#^\D*(\d[\d., ]*)\D*$#")))
            ->currency($this->currency($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total cost")) . "]/following::td[" . $this->eq($this->t("Base rate")) . "][1]/following-sibling::td[normalize-space()][1]", null, true, "#^(\D*)$#")))
        ;

        $totalPickup = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total cost")) . "]/following::td[" . $this->starts($this->t("Car total due at pickup")) . "][2]/following-sibling::td[2]", null, true, "#^\D*(\d[\d., ]*)\D*$#"));
        $totalNow = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total cost")) . "]/following::td[" . $this->starts($this->t("Car total due now")) . "][1]/following-sibling::td[2]", null, true, "#^\D*(\d[\d., ]*)\D*$#"));

        if (!empty($totalNow) && !empty($totalPickup)) {
            $r->price()
                ->total($totalNow + $totalPickup);
        } elseif (!empty($totalNow) && empty($totalPickup)) {
            $r->price()
                ->total($totalNow);
        } elseif (empty($totalNow) && !empty($totalPickup)) {
            $r->price()
                ->total($totalPickup);
        }

        $tax = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total cost")) . "]/following::td[" . $this->eq($this->t("Taxes/Fees")) . "][1]/following-sibling::td[normalize-space()][2]", null, true, "#^\D*(\d[\d., ]*)\D*$#"));

        if (!empty($tax)) {
            $r->price()->fee("Taxes/Fees", $tax);
        }
        $fee = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total cost")) . "]/following::td[" . $this->eq($this->t("Drop charge")) . "][1]/following-sibling::td[normalize-space()][2]", null, true, "#^\D*(\d[\d., ]*)\D*$#"));

        if (!empty($fee)) {
            $r->price()->fee("Drop charge ", $fee);
        }

        $regexp = "\s+(?<date>[\s\S]+?)\s+LOCATION\s+(?<location>.+)";
        // Pick Up
        $node = implode("\n", $this->http->FindNodes("//text()[normalize-space() = 'PICK-UP']/ancestor::td[1]//text()[normalize-space()][1]"));

        if (preg_match("#PICK-UP" . $regexp . "#", $node, $m)) {
            $r->pickup()
                ->date($this->normalizeDate($m['date']))
                ->location($m['location'])
            ;
        }

        // Drop Off
        // Pick Up
        $node = implode("\n", $this->http->FindNodes("//text()[normalize-space() = 'RETURN']/ancestor::td[1]//text()[normalize-space()][1]"));

        if (preg_match("#RETURN" . $regexp . "#", $node, $m)) {
            $r->dropoff()
                ->date($this->normalizeDate($m['date']))
                ->location($m['location'])
            ;
        }

        // Car
        $typeModel = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("VEHICLE DESCRIPTION")) . "]/following::text()[normalize-space()][1]");

        if (!empty($typeModel)) {
            if (preg_match('/(?:(^.+)\s-\s(.+)|(^.+[^-]))$/', $typeModel, $m)) {
                if (!empty($m[3])) {
                    $r->car()
                     ->type($m[3]);
                } else {
                    $r->car()
                     ->type($m[1])
                     ->model($m[2]);
                }
            }
        }

        // Extra
        $company = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("help you reserve a car with")) . "]", null, true,
                "#" . $this->preg_implode("help you reserve a car with") . "\s+([^.]+)\.#");

        if (empty($company)) {
            $company = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation #")) . "]/preceding::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1][count(tr)=3]/tr[2]//img[contains(@src, 'southwest.com/assets/images/car/email_') and contains(@src, '_logo.png') ]/@alt");
        }

        if (!empty($company)) {
            $r->extra()->company($company);
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
        $str = preg_replace("#\s+#", ' ', $str);
//        $this->http->log($str);
        $in = [
            "#^\s*([^\s\d]+)\s*(\d{1,2}),\s*(\d{4})\s*(\d+:\d+(\s*[ap]m)?)\s*$#iu", // March 30, 2019 09:00AM
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
