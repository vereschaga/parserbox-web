<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1558591 extends \TAccountChecker
{
    public $mailFiles = "hotels/it-1.eml, hotels/it-1558591.eml, hotels/it-1558925.eml, hotels/it-1564376.eml, hotels/it-1564978.eml, hotels/it-1566199.eml, hotels/it-1616016.eml, hotels/it-1829238.eml, hotels/it-1849775.eml, hotels/it-1908713.eml, hotels/it-2030344.eml";

    public static $dictionary = [
        "en" => [
            "Your reservation is now" => ["Your reservation is now", "Your order is"],
            //            "Your order is in progress" => "",
            //            "Confirmation Number is:" => "",
            //            "Check-in:" => "",
            //            "Check-out:" => "",
            //            "Number of rooms:" => "",
            //            "Room " => "",
            //            "Guest(s):" => "",
            //            "Room details:" => "",
            //            "Room charges" => "",
            //            "Number of guests:" => "",
            //            "Adult" => "",
            //            "Child" => "",
            //            "Price per room per night" => "",
            //            "Total" => "",
            "Taxes & fees" => ["Taxes & fees", "Taxes & service fees"],
            //            "Check-in time starts at" => "",
            //            "Check-out time is" => "",
            //            "Cancellation policy" => "",
        ],
        "no" => [
            "Your reservation is now" => ["Bestillingen er nå"],
            //            "Your order is in progress" => "",
            "Confirmation Number is:" => "Bekreftelsesnummeret ditt er:",
            "Check-in:"               => "Innsjekking:",
            "Check-out:"              => "Utsjekking:",
            "Number of rooms:"        => "Antall rom:",
            "Room "                   => "Rom ",
            "Guest(s):"               => "Gjest(er):",
            "Room details:"           => "Romdetaljer:",
            "Room charges"            => "Romavgifter",
            "Number of guests:"       => "Antall gjester:",
            "Adult"                   => ["voksne", "voksen"],
            //            "Child" => "",
            //            "Price per room per night" => "",
            "Total" => "Totalt",
            //            "Taxes & fees" => "",
            //            "Check-in time starts at" => "",
            //            "Check-out time is" => "",
            "Cancellation policy" => "Avbestillingsvilkår",
        ],
        "da" => [
            "Your reservation is now" => ["Din reservation er"],
            //            "Your order is in progress" => "",
            "Confirmation Number is:" => "Dit Hotels.com-bekræftelsesnummer er:",
            "Check-in:"               => "Indtjekning:",
            "Check-out:"              => "Udtjekning:",
            "Number of rooms:"        => "Antal værelser:",
            "Room "                   => "Værelse ",
            "Guest(s):"               => "Gæst(er):",
            //            "Room details:" => ":",
            "Room charges"      => "Værelsesudgifter",
            "Number of guests:" => "Antal gæster:",
            "Adult"             => ["Voksne", "voksen"],
            //            "Child" => "",
            "Price per room per night" => "Pris pr. værelse pr. overnatning",
            "Total"                    => "",
            //            "Taxes & fees" => "",
            "Check-in time starts at" => "Indtjekning starter kl.",
            "Check-out time is"       => "Udtjekning kl.",
            "Cancellation policy"     => "Afbestillingspolitik",
        ],
    ];

    private $detectFrom = "hotels.com";

    private $detectSubject = [
        "en" => "Booking Confirmation (Hotels.com Confirmation Number", // Booking Confirmation (Hotels.com Confirmation Number 105766120) - Vivaldi Apartments
        "en" => "Reservation confirmation (Hotels.com Confirmation Number )", // Booking Confirmation (Hotels.com Confirmation Number 105766120) - Vivaldi Apartments
        "no" => "Bestillingsbekreftelse (Hotels.com-bekreftelsesnummer", // Bestillingsbekreftelse (Hotels.com-bekreftelsesnummer 111284963435) - Radisson Blu Hotel Latvija
        "da" => "Reservationsbekræftelse (Hotels.com-bekræftelsesnummer", // Reservationsbekræftelse (Hotels.com-bekræftelsesnummer 122254634) - Mythos Suites Hotel
    ];
    private $detectCompany = "hotels.com";
    private $detectBody = [
        "en" => ["Reservation details"],
        "no" => ["Reservasjonsdetaljer"],
        "da" => ["Reservationsoplysninger"],
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->eq($detectBody) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHotel($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["subject"])) {
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
            if ($this->http->XPath->query("//*[" . $this->eq($detectBody) . "]")->length > 0) {
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

        $conf = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Confirmation Number is:")) . "])[1]",
            null, true, "#" . $this->preg_implode($this->t("Confirmation Number is:")) . "\s*([A-Z\d]{5,})[\s\.]+#");

        if (empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Your order is in progress")) . "])[1]"))) {
            $email->ota()
                ->confirmation($conf);
        }

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//text()[" . $this->eq($this->t("Guest(s):")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]/ancestor::td[1]"), true)
            ->status($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Your reservation is now")) . "])[1]", null, true, "#" . $this->preg_implode($this->t("Your reservation is now")) . "\s+(.+?)\s*(?:\.|$)#u"))
            ->cancellation(implode(". ", $this->http->FindNodes("//text()[" . $this->contains($this->t("Cancellation policy")) . "]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td/node()")), true, true)
        ;

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//img[contains(@src, 'hotels/') or (@width=64 and @height=64)]/ancestor-or-self::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()][1]/ancestor::a[1]"))
        ;
        $address = implode("\n", $this->http->FindNodes("//img[contains(@src, 'hotels/') or (@width=64 and @height=64)]/ancestor-or-self::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()][1]/ancestor::tr[1]/following-sibling::tr[2]//text()[normalize-space()]"));

        if (preg_match("#([\s\S]+)\n\s*([\d\+\- \(\)\.]{5,})\s*$#", $address, $m)) {
            $h->hotel()
                ->address(preg_replace(["#\s*\n+\s*#", "#[, ]+#"], ', ', trim($m[1])))
                ->phone($m[2])
            ;
        } else {
            $h->hotel()
                ->address(preg_replace(["#\s*\n+\s*#", "#[, ]+#"], ', ', trim($address)))
            ;
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->nextTd($this->t("Check-in:"))))
            ->checkOut($this->normalizeDate($this->nextTd($this->t("Check-out:"))))
            ->guests(array_sum($this->nextTds($this->t("Number of guests:"), "#(\d+)\s?" . $this->preg_implode($this->t("Adult")) . "#")), true, true)
            ->kids(array_sum($this->nextTds($this->t("Number of guests:"), "#(\d+)\s?" . $this->preg_implode($this->t("Child")) . "#")), true, true)
            ->rooms($this->nextTd($this->t("Number of rooms:"), "#^\s*(\d+)\D+#"))
        ;

        $timePattern = "\d{1,2}(?:\d{1,2})?\s*(?:[ap]m)?";
        $time = $this->normalizeTime($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Check-in time starts at")) . "]", null, true,
            "#" . $this->preg_implode($this->t("Check-in time starts at")) . "\s+(" . $timePattern . ")(?: |$|\.)#i"));

        if (!empty($time) && !empty($h->getCheckInDate())) {
            $h->booked()->checkIn(strtotime($time, $h->getCheckInDate()));
        }
        $time = $this->normalizeTime($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Check-out time is")) . "]", null, true,
            "#" . $this->preg_implode($this->t("Check-out time is")) . "\s+(" . $timePattern . ")(?: |$|\.)#i"));

        if (!empty($time) && !empty($h->getCheckOutDate())) {
            $h->booked()->checkOut(strtotime($time, $h->getCheckOutDate()));
        }

        // Rooms
        $types = array_values(array_filter($this->http->FindNodes("//*[" . $this->eq($this->t("Room charges")) . "]/following::tr[td[normalize-space()][1][" . $this->starts($this->t("Room ")) . "]]", null,
            "#" . $this->preg_implode($this->t("Room ")) . "\d ?:\s*(.+)#")));
        $desc = $this->nextTds($this->t("Room details:"));
        $rates = array_values(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Price per room per night")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null)));

        if (!empty($types) && $h->getRoomsCount() == count($types)) {
            if (empty($desc) || count($desc) !== $h->getRoomsCount()) {
                $desc = [];
            }

            if (empty($rates) || count($rates) !== $h->getRoomsCount()) {
                $rates = [];
            }

            foreach ($types as $i => $type) {
                $h->addRoom()
                    ->setType($type)
                    ->setDescription($desc[$i] ?? null, true, true)
                    ->setRate($rates[$i] ?? null, true, true)
                ;
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total")) . "]/ancestor::td[1][starts-with(., 'Total')]/following-sibling::td[1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        $tax = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Taxes & fees")) . "]/ancestor::td[1]/following-sibling::td[1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $tax, $m)) {
            $h->price()
                ->tax($this->amount($m['amount']))
            ;
        }

        $this->detectDeadLine($h);

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
               preg_match("#Free cancellation until (?<date>\d+/\d+/\d+)\.#ui", $cancellationText, $m)
            || preg_match("#Gratis avbestilling frem til (?<date>\d+\.\d+\.\d+)\.#ui", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m['date']));
        }
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
//        $this->logger->debug($date);
        $in = [
            "#^\s*[^\s\d]+[,\s]+([^\s\d]+)\s+(\d{1,2})[\s,]+(\d{4})\s*$#u", // Friday, January 4, 2013
            "#^\s*[^\d]+\s+(\d{1,2})[\s\.]+([^\s\d]+)\s+(\d{4})\s*$#u", // Søndag 27. januar 2013; Søndag d. 1. september 2013
            "#^\s*(\d{1,2})/(\d{2})/(\d{4})\s*$#", // 15/07/2013
        ];
        $out = [
            "$2 $1 $3",
            "$1 $2 $3",
            "$1.$2.$3",
        ];
        $date = preg_replace($in, $out, $date);
//        $this->logger->debug($date);
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
