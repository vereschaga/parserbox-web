<?php

namespace AwardWallet\Engine\triprewards\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReservation2 extends \TAccountChecker
{
    public $mailFiles = "triprewards/it-160963577.eml, triprewards/it-86364349.eml, triprewards/it-8678056.eml";
    public $reFrom = "@wyndham.com";
    public $reSubject = [
        "en"=> "Reservation Confirmation at Wyndham",
        "de"=> "By Wyndham",
    ];
    public $reBody = 'Wyndham Hotel';
    public $reBody2 = [
        "en"=> "Hotel Information:",
        "de"=> "Hotelinformationen:",
    ];

    public static $dictionary = [
        "en" => [
            "NAME:"                => ["NAME:", "Name"],
            "Confirmation Number:" => ["Confirmation Number:", "Confirmation #:"],
            "Adult"                => ["Adult", "Adults"],
            "Child"                => ["Child", "Chilldren"],
            "Total for Stay"       => ["Itinerary Total with tax", "Total for Stay"],
            "Best Available Rate"  => ["Best Available Rate", "Daily Rate:"],
        ],
        "de" => [
            "Confirmation Number:"=> "Bestätigungsnummer:",
            "Hotel Information:"  => "Hotelinformationen:",
            "Check-In:"           => "Check-in:",
            "Check-Out:"          => "Check-out:",
            "Occupancy:"          => "Belegung:",
            "Adult"               => "Erwachsene",
            "Child"               => "Kind",
            "Stay:"               => "Aufenthalt:",
            "Room"                => "Zimmer",
            "Best Available Rate" => "Niedrigster verfügbarer Tarif",
            "Cancellation Policy:"=> "NOTTRANSLATED",
            "Reservation:"        => "Reservierung:",
            "Total for Stay"      => "Gesamtpreis für den Aufenthalt",
            "NAME:"               => ["NAME:", "Name"],
        ],
    ];

    public $lang = "en";

    public function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        $cancellation = array_unique($this->http->FindNodes("//text()[" . $this->starts($this->t("Cancellation Policy:")) . "]", null, "#:\s*(.+)#"));

        if (!empty($cancellation[0])) {
            $h->general()
                ->cancellation($cancellation[0]);
        }

        $h->general()
            ->confirmation($this->nextText($this->t("Confirmation Number:")))
            ->travellers(array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('NAME:'))}]/following::text()[normalize-space()][1]")));

        $h->hotel()
            ->name($this->nextText($this->t("Hotel Information:")))
            ->address(implode(", ", array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Hotel Information:")) . "]/following::text()[normalize-space(.)][position()>=2 and position()<=4]"))))
            ->phone($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hotel Information:'))}]/ancestor::tr[1]/descendant::text()[{$this->contains($this->t('Website'))}]/preceding::text()[normalize-space()][1]"));

        $h->booked()
            ->checkIn(strtotime($this->normalizeDate($this->nextText($this->t("Check-In:")))))
            ->checkOut(strtotime($this->normalizeDate($this->nextText($this->t("Check-Out:")))))
            ->guests($this->re("#(\d+)\s+" . $this->opt($this->t("Adult")) . "#", $this->nextText($this->t("Occupancy:"))))
            ->rooms($this->re("#(\d+)\s+" . $this->t("Room") . "#", $this->nextText($this->t("Stay:"))));

        $kids = $this->re("#(\d+)\s+" . $this->opt($this->t("Child")) . "#", $this->nextText($this->t("Occupancy:")));

        if (!empty($kids)) {
            $h->booked()
                ->kids($kids);
        }

        $h->price()
            ->total($this->amount($this->nextText($this->t("Total for Stay"))))
            ->currency($this->currency($this->nextText($this->t("Total for Stay"))));

        $rate = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Best Available Rate")) . "]/ancestor::tr[1]");

        $roomType = $this->re("#(.*?)(?:,|$)#", $this->nextText($this->t("Reservation:")));

        $roomTypeDescription = $this->re("#,\s*(.+)#", $this->nextText($this->t("Reservation:")));

        if (!empty($rate) || !empty($roomType) || !empty($roomTypeDescription)) {
            $room = $h->addRoom();

            if (!empty($rate)) {
                $room->setRate($rate);
            }

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($roomTypeDescription)) {
                $room->setDescription($roomTypeDescription);
            }
        }

        return true;
    }

    public function parseHtml2(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Stay:'))}]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $cancellation = array_unique($this->http->FindNodes("//text()[" . $this->starts($this->t("Cancellation Policy:")) . "]", null, "#:\s*(.+)#"));

            if (!empty($cancellation[0])) {
                $h->general()
                    ->cancellation($cancellation[0]);
            }

            $h->general()
                ->confirmation($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Confirmation Number:'))}]/following::text()[normalize-space()][1]", $root))
                ->travellers(array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('NAME:'))}]/following::text()[normalize-space()][1]")));

            $h->hotel()
                ->name($this->nextText($this->t("Hotel Information:")))
                ->address(implode(", ", array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Hotel Information:")) . "]/following::text()[normalize-space(.)][position()>=2 and position()<=4]"))))
                ->phone($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hotel Information:'))}]/ancestor::tr[1]/descendant::text()[{$this->contains($this->t('Website'))}]/preceding::text()[normalize-space()][1]"));

            $h->booked()
                ->checkIn(strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Check-In:'))}]/following::text()[normalize-space()][1]", $root))))
                ->checkOut(strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Check-Out:'))}]/following::text()[normalize-space()][1]", $root))))
                ->guests($this->re("#{$this->opt($this->t("Adult"))}\s+(\d+)#", $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Occupancy:'))}]/following::text()[normalize-space()][1]", $root)))
                ->rooms($this->re("#(\d+)\s+" . $this->t("Room") . "#", $this->nextText($this->t("Stay:"))));

            $kids = $this->re("#{$this->opt($this->t("Child"))}\s+(\d+)#", $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Occupancy:'))}]/following::text()[normalize-space()][1]", $root));

            if ($kids !== null) {
                $h->booked()
                    ->kids($kids);
            }

            $email->price()
                ->total($this->amount($this->nextText($this->t("Total for Stay"))))
                ->currency($this->currency($this->nextText($this->t("Total for Stay"))));

            $rate = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t("Best Available Rate"))}]/following::text()[normalize-space()][1]/ancestor::td[1]", $root);

            $roomTypeDescription = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Room:'))}]/following::text()[normalize-space()][1]", $root);

            if ((bool) empty($roomType) || !empty($roomTypeDescription)) {
                $room = $h->addRoom();

                if (!empty($rate)) {
                    $room->setRate($rate);
                }

                if (!empty($roomTypeDescription)) {
                    $room->setDescription($roomTypeDescription);
                }
            }
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $segCount = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Confirmation Number:'))}]/following::text()[normalize-space()][1]")));

        if (count($segCount) > 1) {
            $this->parseHtml2($email);
        } else {
            $this->parseHtml($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+, ([^\s\d]+) (\d+), (\d{4}) (\d+:\d+) \(\d+:\d+ [AP]M\)$#", //Thursday, October 19, 2017 14:00 (2:00 PM)
            "#^[^\s\d]+, (\d+)\. ([^\s\d]+) (\d{4}) (\d+:\d+) \(\d+:\d+.*\)$#", //Samstag, 31. März 2018 15:00 (3:00 )
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
