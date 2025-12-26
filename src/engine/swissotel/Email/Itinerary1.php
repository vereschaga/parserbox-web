<?php

namespace AwardWallet\Engine\swissotel\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "swissotel/it-1.eml, swissotel/it-33708456.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            "Confirmation #" => ["Confirmation #", "Confirmation Number"],
            "First Name"     => ["First Name", "Guest's First Name"],
            "Last Name"      => ["Last Name", "Guest's Last Name"],
            //            "R E S E R V A T I O N C O N F I R M A T I O N" => "",
            //            "Thank you for choosing Swissôtel" => "",
            "hotelNameRe" => "Thank you for choosing (.+?)\.",
            //            "Arrival Date" => "",
            //            "Departure Date" => "",
            "Number Of Adults"   => ["Number Of Adults", "Number of Adults"],
            "Number Of Children" => ["Number Of Children", "Number of Children"],
            //            "Room Type" => "",
            //            "Rate Per Room Per Night" => "",
            "Check in Time:"  => ["Check in Time:", "check in is at"],
            "Check out Time:" => ["Check out Time:", "check out is at"],
            //            "Cancellation Policy" => "",
            //            "Cancel Date To Avoid Fees" => "",
        ],
        "de" => [
            "Confirmation #"                                => "Bestätigungsnummer",
            "First Name"                                    => "Vorname",
            "Last Name"                                     => "Nachname",
            "R E S E R V A T I O N C O N F I R M A T I O N" => "R E S E R V I E R U N G S B E S T Ä T I G U N G",
            "Thank you for choosing Swissôtel"              => "vielen Dank dass Sie das",
            "hotelNameRe"                                   => "vielen Dank dass Sie das (.+?) gewählt haben.",
            "Arrival Date"                                  => "Anreisedatum",
            "Departure Date"                                => "Abreisedatum",
            "Number Of Adults"                              => "Anzahl der Erwachsenen",
            "Number Of Children"                            => "Anzahl der Kinder",
            "Room Type"                                     => "Zimmertyp",
            "Rate Per Room Per Night"                       => "Nächtliche Rate",
            //            "Check in Time:" => "",
            //            "Check out Time:" => "",
            "Cancellation Policy"       => "Stornierungsbedingung",
            "Cancel Date To Avoid Fees" => "Kostenfreie Stornierung bis",
        ],
    ];

    private $detectFrom = "swissotel.com";
    private $detectSubject = [
        "en" => "Confirmation for", // Confirmation for Mr Zeyd Seddik
        "de" => "Reservierungsbestätigung für",
    ];

    private $detectBody = [
        "en" => "Thank you for choosing Swissôtel",
        "de" => "vielen Dank dass Sie das Swissôtel",
    ];
    private $siblingTag = 'td';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $detectBody) {
            if (strpos($body, $detectBody) !== false || $this->http->XPath->query("//*[contains(.,'{$detectBody}')]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

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
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false || $this->http->XPath->query("//*[contains(.,'{$detectBody}')]")->length > 0) {
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

        if (empty($this->nextTd($this->t("Confirmation #"))) && !empty($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Confirmation #")) . "]/ancestor::div[1][" . $this->eq($this->t("Confirmation #")) . "]/following-sibling::div[normalize-space()][1]"))) {
            $this->siblingTag = 'div';
        }
        // General
        $h->general()
            ->confirmation($this->nextTd($this->t("Confirmation #")))
            ->traveller($this->nextTd($this->t("First Name")) . ' ' . $this->nextTd($this->t("Last Name")))
            ->cancellation($this->nextTd($this->t("Cancellation Policy")))
        ;

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("R E S E R V A T I O N C O N F I R M A T I O N")) . "])[1]"))) {
            $h->general()->status('Confirmed');
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Thank you for choosing Swissôtel")) . "][1]", null, true, "#" . $this->t("hotelNameRe") . "#"));

        $address = implode("\n", $this->http->FindNodes("//text()[normalize-space(.) = 'Tel']/preceding::text()[normalize-space()][1]/ancestor::td[1][starts-with(normalize-space(), 'Swissôtel ')]//text()[normalize-space()]"));

        if (empty($address)) {
            $address = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(.), 'Tel ')]/preceding::text()[normalize-space()][1]/ancestor::td[1][starts-with(normalize-space(), 'Swissôtel ')]//text()[normalize-space()]"));
        }

        if (preg_match("#.+\n([\s\S]+?)\n\s*Tel(?:\n| )#", $address, $m)) {
            $h->hotel()
                ->address(preg_replace("#\s*\n\s*#", ', ', $m[1]))
                ->phone($this->re("#\nTel(?:\n| )([\d\+\- \(\)]{5,})(?:\n|$)#", $address), true, true)
                ->fax($this->re("#\nFax(?:\n| )([\d\+\- \(\)]{5,})(?:\n|$)#", $address), true, true)
            ;
        } else {
            $h->hotel()->noAddress();
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->nextTd($this->t("Arrival Date"))))
            ->checkOut($this->normalizeDate($this->nextTd($this->t("Departure Date"))))
            ->guests($this->nextTd($this->t("Number Of Adults")))
            ->kids($this->nextTd($this->t("Number Of Children")), true, true)
        ;

        if (!empty($h->getCheckInDate())) {
            $inTime = $this->nextTd($this->t("Arrival Time"));

            if (empty($inTime)) {
                $inTime = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Check in Time:")) . "][1]", null, true, "#" . $this->preg_implode($this->t("Check in Time:")) . "\s*[^\W\d]*\s*(\d{1,2}(?:[.:]\d{2})?[ap]m)#i");
            }
            $inTime = $this->normalizeTime($inTime);

            if (!empty($inTime)) {
                $h->booked()->checkIn(strtotime($inTime, $h->getCheckInDate()));
            }
        }

        if (!empty($h->getCheckOutDate())) {
            $outTime = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Check out Time:")) . "][1]", null, true, "#" . $this->preg_implode($this->t("Check out Time:")) . "\s*\w+\s*(\d{1,2}(?:[.:]\d{2})?[ap]m)#i");
            $outTime = $this->normalizeTime($outTime);

            if (!empty($outTime)) {
                $h->booked()->checkOut(strtotime($outTime, $h->getCheckOutDate()));
            }
        }
        $deadline = $this->nextTd($this->t("Cancel Date To Avoid Fees"));

        if (!empty($deadline)) {
            $h->booked()->deadline($this->normalizeDate($deadline));
        }

        // Rooms
        $h->addRoom()
            ->setType($this->nextTd($this->t("Room Type")))
            ->setRate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Rate Per Room Per Night")) . "]/ancestor::" . $this->siblingTag . "[" . $this->eq($this->t("Rate Per Room Per Night")) . "][1]/following-sibling::" . $this->siblingTag . "[normalize-space()][1]/descendant::text()[normalize-space()][1]"), true)
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

    private function normalizeDate($date)
    {
//        $this->http->log($date);
        $in = [
            "#^[^\s\d]+,\s*(\d{1,2})[\s.\-]+([^\s\d,.]+)[\s,]+(\d{4})\s*$#iu", // Wednesday, 24 Apr, 2013; Sonntag, 17. Feb 2019; Saturday, 25-May, 2019
        ];
        $out = [
            "$1 $2 $3",
        ];
        $date = preg_replace($in, $out, $date);

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
            "#^\s*(\d+)\s*([ap]m)\s*$#iu", // 2pm
            "#^\s*(\d+)[.](\d{2})\s*([ap]m)\s*$#iu", // 2pm
        ];
        $out = [
            "$1:00$2",
            "$1:$2$3",
        ];
        $time = preg_replace($in, $out, $time);

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

    private function nextTd($field, $regexp = null, $root = null)
    {
        return $this->http->FindSingleNode(".//text()[" . $this->eq($field) . "]/ancestor::" . $this->siblingTag . "[" . $this->eq($field) . "][1]/following-sibling::" . $this->siblingTag . "[normalize-space()][1]", $root, true, $regexp);
    }
}
