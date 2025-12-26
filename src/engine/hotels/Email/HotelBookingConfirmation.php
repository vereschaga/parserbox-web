<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelBookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "hotels/it-2122510.eml, hotels/it-2143614.eml, hotels/it-2192123.eml, hotels/it-2192739.eml, hotels/it-2212833.eml, hotels/it-2252387.eml, hotels/it-2252408.eml";

    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "hotels.com";

    private $detectSubject = [
        "en" => "Hotel booking confirmation",
    ];
    private $detectCompany = "hotels.com";
    private $detectBody = [
        "en" => ["Your Reservation Has Been Booked!", "YOUR RESERVATION HAS BEEN BOOKED!"],
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        foreach($this->detectBody as $lang => $detectBody){
//            if ($this->http->XPath->query("//*[".$this->contains($detectBody)."]")->length > 0) {
//                $this->lang = $lang;
//                break;
//            }
//        }

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
        if ($this->http->XPath->query("//a[contains(@href,'" . $this->detectCompany . "')]")->length === 0
            && $this->http->XPath->query("//text()[contains(.,'Hotels.com Confirmation ')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "] | //img[" . $this->contains($detectBody, '@alt') . "]/@alt")->length > 0) {
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

        $conf = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Hotels.com Booking Numbers:")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]",
            null, true, "#^\s*([\d]{5,})\s*(\.|$)#");
        $traveller = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Guest:")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]",
            null, true, "#^\D+$#");

        if (empty($conf) && empty($traveller)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->eq(["Hotels.com Booking Number:", "Hotels.com Booking Numbers:", "Hotels.com Confirmation Number(s):"]) . "]/following::text()[normalize-space()][1]",
                null, true, "#^\s*([\d]{5,})\s*(\.|$|\s+)#");
            $traveller = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Guest:")) . "]/following::text()[normalize-space()][1]",
                null, true, "#^\D+$#");

            if (empty($traveller)) {
                $traveller = $this->http->FindSingleNode("//text()[" . $this->contains($this->t(" Guest: ")) . "]",
                    null, true, "#Guest:\s+(\D+)$#");
            }
        }

        $email->ota()
            ->confirmation($conf);

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($traveller)
            ->cancellation($this->http->FindSingleNode("//*[contains(text(), 'Cancellation Policy')]/following::font[1]"), true, true)
        ;

        // Hotel
        $hotel = implode("\n", $this->http->FindNodes("//*[contains(text(), 'Check-In:')]/preceding::text()[normalize-space()][1]/ancestor::td[1]//text()[normalize-space()]"));

        if (empty($hotel)) {
            $hotel = implode("\n",
                $this->http->FindNodes("(//img[contains(@src, 'star-ratings')])[1]/ancestor::td[1]//text()[normalize-space()]"));
        }

        if (preg_match("#^(?<name>.+?)(?:\n(?<phone>[\d- \(\)\+]{5,}))?\n(?<address>[\s\S]+?)(\s+View Map|$)#", $hotel, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(preg_replace("#\s*\n\s*#", ', ', trim($m['address'])))
                ->phone($m['phone'] ?? null, true, true)
            ;
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->eq(["Check-In:", "Check-in:"]) . "])[1]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->eq(["Check-Out:", "Check-out:"]) . "])[1]/following::text()[normalize-space()][1]")))
        ;

        $time = $this->normalizeTime($this->http->FindSingleNode("//text()[" . $this->contains(["check-in time:", "Check in time "]) . "]", null, true, "#" . $this->preg_implode(["check-in time:", "Check in time "]) . "\s*(.+?)\s*\)#"));

        if (!empty($time) && !empty($h->getCheckInDate())) {
            $h->booked()->checkIn(strtotime($time, $h->getCheckInDate()));
        }
        $time = $this->normalizeTime($this->http->FindSingleNode("//text()[" . $this->contains(["check-out time:", "Check out time "]) . "]", null, true, "#" . $this->preg_implode(["check-out time:", "Check out time "]) . "\s*(.+?)\s*\)#"));

        if (!empty($time) && !empty($h->getCheckOutDate())) {
            $h->booked()->checkOut(strtotime($time, $h->getCheckOutDate()));
        }

        $info = implode("\n", $this->http->FindNodes("//text()[" . $this->contains(" Children") . "]/ancestor::td[1]//text()[normalize-space()][1]"));

        if (preg_match("#\b(\d+)\s*Adults\s*,\s*\b(\d+)\s*Children\s*\n\s*(.+)#", $info, $m)) {
            $h->booked()
                ->guests($m[1])
                ->kids($m[2]);
            $h->addRoom()->setType($m[3]);
        } elseif (preg_match("#\b(\d+)\s*Adults\s*,\s*\b(\d+)\s*Children#", $info, $m)) {
            $h->booked()
                ->guests($m[1])
                ->kids($m[2]);
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Charges")) . "]/following::text()[normalize-space()][1]");

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
        $in = [
            "#^\s*(\d{1,2})\s*([ap]\.?m)\.?\s*$#i", //4 p.m.
            "#^\s*(\d{2})(\d{2})\s*$#i", //1200
        ];
        $out = [
            "$1:00 $2",
            "$1:$2",
        ];
        $time = str_replace('.', '', preg_replace($in, $out, $time));

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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains(" . $text . ", \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
