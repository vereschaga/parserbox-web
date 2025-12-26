<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1724844 extends \TAccountChecker
{
    public $mailFiles = "hotels/it-1724844.eml, hotels/it-2.eml, hotels/it-2210439.eml, hotels/it-8.eml";

    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "hotels.com";

    private $detectSubject = [
        "en" => "Your upcoming stay at",
    ];
    private $detectCompany = "hotels.com";
    private $detectBody = [
        "en" => ["You don't need to call to reconfirm"],
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHotel($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["subject"]) || empty($headers["from"])) {
            return false;
        }

        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (($this->http->XPath->query("//a[contains(@href,'" . $this->detectCompany . "')]")->length === 0)) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
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
        // Travel Agency
        $email->obtainTravelAgency();

        $conf = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Confirmation Number:")) . "])[1]",
            null, true, "#" . $this->preg_implode($this->t("Confirmation Number:")) . "\s*([A-Z\d]{5,})[\s\.]+#");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Confirmation Number:")) . "])[1]/following::text()[normalize-space()][1]",
                null, true, "#^\s*([\d]{5,})\s*(\.|$)#");
        }

        $email->ota()
            ->confirmation($conf);

        $account = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Membership Number:")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*(\d{5,})\s*$#");

        if (!empty($account)) {
            $email->ota()->account($account, false);
        }

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->status($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("your booking is confirmed")) . "])[1]", null, true, "#your booking is (confirmed)#u"))
        ;

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//img[contains(@src, 'stars')]/ancestor-or-self::td[1]//a[1]"))
            ->phone($this->http->FindSingleNode("//text()[" . $this->eq("Contact this hotel directly:") . "][1]/following::text()[normalize-space()][1]", null, true,
                "#^[\d \.\+\(\)\-]{5,}$#"), true, true)
        ;
        $address = implode("\n", $this->http->FindNodes("//text()[normalize-space() =  'Number of Guests:'][1]/preceding::text()[normalize-space()][1]/ancestor::table[1]//text()[normalize-space()]"));

        if (preg_match("#reviews?\s*\s\s*([\s\S]+)$#", $address, $m)) {
            $h->hotel()
                ->address(preg_replace(["#\s*\n+\s*#", "#[, ]+#"], ', ', trim($m[1])))
            ;
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq("Check-In Date:") . "][1]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq("Check-Out Date:") . "][1]/following::text()[normalize-space()][1]")))
            ->guests($this->http->FindSingleNode("//text()[" . $this->eq("Number of Guests:") . "][1]/following::text()[normalize-space()][1]", null, true, "#adults - (\d+)\b#"), true, true)
        ;

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total:")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
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

    private function normalizeDate($date)
    {
        $this->logger->debug($date);
        $in = [
            "#^\s*(\d{1,2})/(\d{2})/(\d{4})\s*$#", // 07/22/2013
        ];
        $out = [
            "$2.$1.$3",
        ];
        $date = preg_replace($in, $out, $date);
        $this->logger->debug($date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function normalizeTime($time)
    {
        // $this->http->log($str);
        $in = [
            "#^\s*(\d{1,2})\s*([ap]m)\s*$#i", //2 PM
        ];
        $out = [
            "$1:00 $2",
        ];
        $time = preg_replace($in, $out, $time);

        if (!preg_match("#^\d{1,2}:\d{2}( [ap]m)?$#i", $time)) {
            return null;
        }

        return $time;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function nextTd($field, $regExp = null)
    {
        return $this->http->FindSingleNode("(//text()[{$this->eq($field)}])[1]/ancestor::*[self::td or self::th][1]/following-sibling::*[self::td or self::th][normalize-space()!=''][1]",
            null, false, $regExp);
    }

    private function nextTds($field, $regExp = null)
    {
        return $this->http->FindNodes("//text()[{$this->eq($field)}]/ancestor::*[self::td or self::th][1]/following-sibling::*[self::td or self::th][normalize-space()!=''][1]",
            null, $regExp);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
