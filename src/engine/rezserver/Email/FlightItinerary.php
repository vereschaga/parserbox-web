<?php

namespace AwardWallet\Engine\rezserver\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightItinerary extends \TAccountChecker
{
    public $mailFiles = "rezserver/it-12373066.eml, rezserver/it-33101533.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "@rezserver.com";
    private $detectSubject = [
        "en" => " Hotel Itinerary", //Your %Company% Flight Itinerary
    ];

    private $detectCompany = 'rezserver.com';

    private $detectBody = [
        "en" => "Your Trip from ",
    ];

    private $travelAgencies = [
        "aaatravel" => ["AAA"],
        "jetblue"   => ["jetBlue Airways"],
        "priceline" => ["Priceline.com"],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $detectBody) {
            if (strpos($body, $detectBody) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        // Travel Agency
        $email->obtainTravelAgency();
        $ta = $this->re("#Your (.+) Flight Itinerary#", $parser->getSubject());

        foreach ($this->travelAgencies as $code => $names) {
            foreach ($names as $name) {
                if (strcasecmp($ta, $name) === 0 || preg_match("#^\s*" . $name . "\s+#", $ta)) {
                    $email->ota()->code($code);

                    break 2;
                }
            }
        }
        $confs = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Trip Number:")) . "]/following::text()[normalize-space()][1]", null, "#^\s*(\d{5,})\s*(?:$|\()#"));

        foreach ($confs as $conf) {
            $email->ota()
                ->confirmation($conf, "Trip Number");
        }

        $this->parseFlight($email);

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

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//text()[" . $this->contains($this->t("Passenger and Ticket Information")) . "]", null, "#^\s*(.+?)\s*-\s*" . $this->preg_implode($this->t("Passenger and Ticket Information")) . "#"))
        ;
        $statuses = array_values(array_unique($this->http->FindNodes("//td[" . $this->eq($this->t("Booking Status:")) . "]/following-sibling::td[normalize-space()][1]")));

        if (count($statuses) === 1) {
            $f->general()->status($statuses[0]);
        } elseif (in_array('Cancelled', $statuses) === false) {
            $f->general()->status(implode("; ", $statuses));
        } elseif (!empty($statuses)) {
            // no exsample
            $f->general()->status($statuses);
        }

        if ($f->getStatus() == 'Cancelled') {
            $f->general()->cancelled();
        }

        // Issued
        $tickets = array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t("Ticket number:")) . "]", null, "#" . $this->preg_implode($this->t("Ticket number:")) . "\s*(\d{10,})\s*$#"));

        if (!empty($tickets)) {
            $f->issued()->tickets($tickets, false);
        }

        // Price
        $f->price()
            ->total($this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Summary of Charges")) . "]/following::td[" . $this->starts($this->t("Total Charges:")) . "][1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][2]", null, true, "#^\D*(\d[\d., ]*)\D*$#")))
            ->currency($this->currency($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Summary of Charges")) . "]/following::td[" . $this->starts($this->t("Total Charges:")) . "][1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", null, true, "#^\D+$#")))
        ;

        // Segment
        $xpath = "//text()[" . $this->eq($this->t("Departs")) . "]/ancestor::tr[1]/ancestor::*[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $dateText = $this->http->FindSingleNode("./preceding::tr[normalize-space()][1][" . $this->contains($this->t("Flight:")) . "]", $root, true, "#:\s*(.+?\s+\d{4})\b#");

            if (!empty($dateText)) {
                $date = $this->normalizeDate($dateText);
            }

            // Departure
            $s->departure()
                ->name($this->http->FindSingleNode("./tr[1]/td[4]", $root))
                ->code($this->http->FindSingleNode("./tr[1]/td[5]", $root))
            ;
            $time = $this->http->FindSingleNode("./tr[1]/td[3]", $root);

            if (!empty($time) && !empty($date)) {
                $s->departure()->date(strtotime($time, $date));
            }

            if (!empty($date) && !empty($this->http->FindSingleNode("(./*[" . $this->contains($this->t("arrives on the next day")) . "])[1]", $root))) {
                // position in code: before arrival
                $date = strtotime("+1day", $date);
            }

            // Arrival
            $s->arrival()
                ->name($this->http->FindSingleNode("./tr[2]/td[3]", $root))
                ->code($this->http->FindSingleNode("./tr[2]/td[4]", $root))
            ;
            $time = $this->http->FindSingleNode("./tr[2]/td[2]", $root);

            if (!empty($time) && !empty($date)) {
                $s->arrival()->date(strtotime($time, $date));
            }

            $info = $this->http->FindSingleNode("./tr[3]", $root);
            $regexp = "#^\s*(?<al>.+?)(?:\s*\(\s*Operated by (?<oper>.+?)\))? - Flight (?<fn>\d{1,5}), (?<aircraft>.+? - .+?) - (?<class>.*Class.*)#";

            if (preg_match($regexp, $info, $m)) {
                // Airline
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                    ->operator($m['oper'] ?? null, true, true)
                ;

                if (!empty($m['al'])) {
                    $s->airline()
                        ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Airline Contact Information")) . "]/following::text()[" . $this->eq($m['al']) . "]/following::text()[normalize-space()][position()<4][" . $this->starts($this->t("Confirmation:")) . "]", $root, true, "#" . $this->preg_implode($this->t("Confirmation:")) . "\s*([A-Z\d]{5,7})\s*$#"));
                }

                // Extra
                $s->extra()
                    ->aircraft($m['aircraft'])
                    ->cabin(trim(preg_replace("#\s*Class\s*#i", '', $m['class'])))
                ;
            }

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode("./tr[1]/td[6]", $root))
            ;

            if (!empty($s->getFlightNumber())) {
                $seats = array_filter($this->http->FindNodes("//*[(self::td or self::th) and " . $this->eq($this->t("Seat Preference")) . "]/ancestor::tr[1]/following-sibling::tr[td[1][normalize-space()='" . $s->getFlightNumber() . "']]/td[2]", null, "#^\s*(\d{1,3}[A-Z])\s*$#"));

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            if (!empty($date) && !empty($this->http->FindSingleNode("(./*[" . $this->contains($this->t("Overnight Connection")) . "])[1]", $root))) {
                // position in code: after departure and arrival
                $date = strtotime("+1day", $date);
            }
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
            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d{1,2})[a-z]{2}?,\s*(\d{4})\s*$#iu", // Friday, February 9th, 2018
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);
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
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
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
