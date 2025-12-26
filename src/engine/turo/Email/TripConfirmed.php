<?php

namespace AwardWallet\Engine\turo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TripConfirmed extends \TAccountChecker
{
    public $mailFiles = "turo/it-103687174.eml, turo/it-104003715.eml, turo/it-104309668.eml, turo/it-608873795.eml, turo/it-91665973.eml, turo/it-92742602.eml, turo/it-95015216.eml";

    private $detectFrom = ["@mail.turo.com", "@turo.com"];

    private $detectSubject = [
        // en
        "Trip booked with ",
        " is starting soon",
        "You cancelled your trip with",
        "Change request confirmed",
        "has sent you a message about their",
    ];

    private $detectBody = [
        "en" => [
            "Please review the details of your trip below",
            "You cancelled your trip",
            "shared an upcoming Turo itinerary",
            "has confirmed your change request",
            "has sent you a message about their",
            "Your trip with Theavy’s",
            "Rules of the road",
        ],
    ];

    private $dateFormatDMY = false;

    private $lang = "en";

    private static $dictionary = [
        "en" => [
            "You cancelled your trip"        => ["You cancelled your trip", "Cancelled trip"],
            "Booked trip"                    => ["Booked trip", "Cancelled trip", "Pending trip", "BOOKED TRIP", "Trip", "TRIP"],
            "Picking up the car"             => ["Picking up the car", "PICKING UP THE CAR"],
            "Delivery"                       => ["Delivery", "DELIVERY"],
            "Location"                       => ["Location", "LOCATION"],
            "Trip start"                     => ["Trip start", "TRIP START"],
            "Returning the Car"              => ["Returning the Car", "RETURNING THE CAR"],
            "Trip end"                       => ["Trip end", "TRIP END"],
            "Total Paid"                     => ["Total Paid", "TOTAL PAID"],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        foreach ($this->detectBody as $lang => $detectBody){
//            if ($this->http->XPath->query("//text()[".$this->contains($detectBody)."]")->length > 0) {
//                $this->lang = $lang;
//                break;
//            }
//        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->striposAll($headers["from"], $this->detectFrom) === false) {
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
        if ($this->http->XPath->query("//a[contains(@href, 'turo.com')]")->length === 0
            && $this->http->XPath->query("//text()[normalize-space()='Download the Turo app']")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detectBody) . "]")->length > 0) {
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

    private function parseHtml(Email $email)
    {
        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Reservation ID #")) . "])[1]", null, true,
                "/" . $this->preg_implode($this->t("Reservation ID #")) . "\s*(\d{5,})\s*$/"), "Reservation ID")
        ;

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("You cancelled your trip")) . "])[1]"))) {
            $r->general()
                ->status("Cancelled")
                ->cancelled()
            ;
        }

        $pickUpDate = preg_replace("/\s+/", ' ', $this->re("/" . $this->preg_implode($this->t("Trip start")) . "\s*([\s\S]+?\d{1,2}:\d{2}\s*([ap]m)?)(?:\W.*|$)/i",
            implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Trip start")) . "]/following::text()[normalize-space()][1]/ancestor::*[.//text()[" . $this->eq($this->t("Trip start")) . "]][1]/*"))));

        $dropOffDate = preg_replace("/\s+/", ' ', $this->re("/" . $this->preg_implode($this->t("Trip end")) . "\s*([\s\S]+?\d{1,2}:\d{2}\s*([ap]m)?)(?:\W.*|$)/i",
            implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Trip end")) . "]/following::text()[normalize-space()][1]/ancestor::*[.//text()[" . $this->eq($this->t("Trip end")) . "]][1]/*"))));

        $this->detectDateFormat([$pickUpDate, $dropOffDate]);

        // Pick Up
        $puLocation = '';

        if ($this->http->XPath->query("//text()[" . $this->eq($this->t("Picking up the car")) . "]")->length > 0) {
            $puLocation = $this->re("/^\s*(?:Meet|Navigate to) [[:alpha:] \-\_’\d\']+? at\n(.+)/u",
                implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Picking up the car")) . "]/following::text()[normalize-space()][position() < 3]")));
        } else {
            $puLocation = implode(", ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Delivery")) . " or " . $this->eq($this->t("Location")) . "]/following::text()[normalize-space()][1]/ancestor::td[1]//text()[normalize-space()]"));
        }
        $r->pickup()
            ->location($puLocation)
            ->date($this->normalizeDate($pickUpDate))
        ;

        // Drop Off
        $doLocation = '';

        if ($this->http->XPath->query("//text()[" . $this->eq($this->t("Returning the Car")) . "]")->length > 0) {
            $doLocation = $this->re("/^\s*Please return the car to\n(.+)/",
                implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Returning the Car")) . "]/following::text()[normalize-space()][position() < 3]")));
        } else {
            $doLocation = implode(", ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Delivery")) . " or " . $this->eq($this->t("Location")) . "]/following::text()[normalize-space()][1]/ancestor::td[1]//text()[normalize-space()]"));
        }
        $r->dropoff()
            ->location($doLocation)
            ->date($this->normalizeDate($dropOffDate))
        ;

        // Car
        $r->car()
            ->model($this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Booked trip")) . "]/following::text()[normalize-space()][1]/ancestor::td[1]"))
            ->image($this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Booked trip")) . "]/following::img[1][contains(@src, '/vehicle/') and contains(@src, 'http')]/@src"), true, true)
        ;

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Paid")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $r->price()
                ->total(PriceHelper::cost($m["amount"]))
                ->currency($this->currency($m["currency"]));
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
        // $this->logger->debug('$date = ' . print_r($date, true));

        if ($this->dateFormatDMY === true) {
            $date = preg_replace("/\b(\d{1,2})\/(\d{1,2})(\/\d{2,4})\b/", '$2/$1$3', $date);
        }
        $in = [
            // 6/7/21 12:00 PM
            "/^\s*(\d{1,2})\/(\d{1,2})\/(\d{2,4})\s*(\d{1,2}:\d{2}(?: *[ap]m)?)\s*$/i",
            //22-1-16 上午10:00
            "/^(\d+)\-(\d+)\-(\d+)\s*上午([\d\:]+)$/i",
            //22-1-16 下午10:00
            "/^(\d+)\-(\d+)\-(\d+)\s*下午([\d\:]+)$/i",
        ];
        $out = [
            "$1/$2/20$3, $4",
            "$3.$2.20$1, $4 AM",
            "$3.$2.20$1, $4 PM",
        ];
        $date = preg_replace($in, $out, $date);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $date);
//        }

        return strtotime($date);
    }

    private function detectDateFormat($dates)
    {
        if (!preg_match("/^\s*(?<d>\d+)\/(?<m>\d+)\/(?<y>\d{2,4})\b.*$/u", $dates[0] ?? '', $m1)
            || !preg_match("/^\s*(?<d>\d+)\/(?<m>\d+)\/(?<y>\d{2,4})\b.*$/u", $dates[1] ?? '', $m2)
        ) {
            return false;
        }

        if (strlen($m1['y']) == 2) {
            $m1['y'] = '20' . $m1['y'];
        }

        if (strlen($m2['y']) == 2) {
            $m2['y'] = '20' . $m2['y'];
        }

        if ($m1['m'] > 12 && $m1['d'] < 12) {
            $this->dateFormatDMY = false;

            return true;
        } elseif ($m1['d'] > 12 && $m1['m'] < 12) {
            $this->dateFormatDMY = true;

            return true;
        }

        if ($m2['m'] > 12 && $m2['d'] < 12) {
            $this->dateFormatDMY = false;

            return true;
        } elseif ($m2['d'] > 12 && $m2['m'] < 12) {
            $this->dateFormatDMY = true;

            return true;
        }

        $date11 = strtotime($m1['m'] . '.' . $m1['d'] . '.' . $m1['y']);
        $date21 = strtotime($m2['m'] . '.' . $m2['d'] . '.' . $m2['y']);
        $d1 = $date21 - $date11;

        $date12 = strtotime($m1['d'] . '.' . $m1['m'] . '.' . $m1['y']);
        $date22 = strtotime($m2['d'] . '.' . $m2['m'] . '.' . $m2['y']);
        $d2 = $date22 - $date12;

        if ($d1 < 0 && $d2 > 0) {
            $this->dateFormatDMY = true;

            return true;
        } elseif ($d1 > 0 && $d2 < 0) {
            $this->dateFormatDMY = false;

            return true;
        }

        if ($this->http->XPath->query("//a[{$this->contains(['turo.com/ca/en/reservation', 'https://turo.com/ca/en/drivers', 'turo.com/gb/en/reservation', 'https://turo.com/gb/en/drivers'], '@href')}]")->length > 0) {
            $this->dateFormatDMY = true;
        }

        return true;
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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'CA$' => 'CAD',
            'US$' => 'USD',
            'A$'  => 'AUD',
            '$'   => 'USD',
            '€'   => 'EUR',
            '£'   => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
