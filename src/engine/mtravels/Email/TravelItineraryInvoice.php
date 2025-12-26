<?php

namespace AwardWallet\Engine\mtravels\Email;

use AwardWallet\Engine\MonthTranslate;
//use AwardWallet\Common\Parser\Util\EmailDateHelper;
//use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelItineraryInvoice extends \TAccountChecker
{
    public $mailFiles = "mtravels/it-1916333.eml, mtravels/it-1916839.eml, mtravels/it-23815153.eml";
    public static $detectCompany = [
        'mtravels' => 'Mann Travels',
        'virtuoso' => 'VIRTUOSO AGENCY',
    ];
    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = [
        'mtravels' => '@manntravels.com',
        //		'virtuoso' => '',
    ];
    private $detectSubject = [
        "en" => "Travel Itinerary/Invoice for",
    ];
    private $detectBody = [
        "en" => ["Air Vendor:", "Hotel Vendor:"],
    ];

    private $providerCode;

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (empty($this->providerCode)) {
            $this->providerCode = $this->getProvider($this->http->Response['body']);
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        } else {
            $this->logger->debug("Provider not detected");

            return $email;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            foreach ($dBody as $value) {
                if (strpos($this->http->Response["body"], $value) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $email->ota()
            ->confirmation($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Agency Confirmation:")) . "])[1]/ancestor::td[1]/following-sibling::td[1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#"), $this->t("Agency Confirmation:"));

        $total = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Total:")) . "])[1]/ancestor::td[1]/following-sibling::td[1]");

        if (!empty($total) && (preg_match("#^\s*(?<cur>[^\d\s]{1,4})\s*(?<amount>\d[\d\.]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\.]*)\s*(?<cur>[^\d\s]{1,4})\s*$#", $total, $m))) {
            if (is_numeric($m['amount'])) {
                $email->price()
                    ->total((float) $m['amount'])
                    ->currency($this->currency($m['cur']));
            }
        }

        $this->parseSegments($email);

        return $email;
    }

    public function parseSegments(Email $email)
    {
        $passengers = array_filter([$this->nextTd($this->t("First Name:")) . ' ' . $this->nextTd($this->t("Last Name::"))]);

        //##################
        //##   FLIGHTS   ###
        //##################

        $xpath = "//table[" . $this->starts($this->t("FLIGHT")) . " and " . $this->contains($this->t("From:")) . " and not(.//table)]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $f = $email->add()->flight();

            if (!empty($passengers)) {
                $f->general()->travellers($passengers, true);
            }

            $f->general()->noConfirmation();

            foreach ($nodes as $root) {
                $s = $f->addSegment();

                // Airline
                $s->airline()
                    ->name($this->nextTd($this->t("Air Vendor:"), $root))
                    ->number($this->nextTd($this->t("Flight Number:"), $root))
                    ->operator($this->nextTd($this->t("Operated By:"), $root, "#(?:.*-)?(.+)#"))
                    ->confirmation($this->nextTd($this->t("Confirmation #:"), $root));

                if ($s->getOperatedBy() == $s->getAirlineName()) {
                    $s->airline()->operator(null, true, true);
                }
                // Departure
                $s->departure()
                    ->noCode()
                    ->name($this->nextTd($this->t("From:"), $root))
                    ->terminal(preg_replace("#\s*(terminal|term)\s*#i", '', $this->http->FindSingleNode(".//text()[contains(normalize-space(), 'DEPART:')][1]", $root, true, "#DEPART:\s*([^\|]*?(?:TERMINAL|TERM)[^\|]*?)(?:\||$)#")), true, true)
                    ->date($this->normalizeDate(
                            $this->http->FindSingleNode("(.//tr[starts-with(normalize-space(), 'FLIGHT')])[1]/following-sibling::tr[normalize-space()][1]", $root) . ', ' .
                            $this->nextTd($this->t("Departs:"), $root)
                    ));

                // arrival
                $date = $this->nextTd($this->t("Arrives:"), $root);

                if (preg_match("#^\s*(\d+:\d+)(\s*[ap]m)?\s*$#i", $date)) {
                    $date = $this->http->FindSingleNode("(.//tr[starts-with(normalize-space(), 'FLIGHT')])[1]/following-sibling::tr[normalize-space()][1]", $root) . ', ' . $date;
                }
                $s->arrival()
                    ->noCode()
                    ->name($this->nextTd($this->t("To:"), $root))
                    ->terminal(preg_replace("#\s*(terminal|term)\s*#i", '', $this->http->FindSingleNode(".//text()[contains(normalize-space(), 'ARRIVE:')][1]", $root, true, "#ARRIVE:\s*([^\|]*?(?:TERMINAL|TERM)[^\|]*?)(?:\||$)#")), true, true)
                    ->date($this->normalizeDate($date));

                $s->extra()
                    ->seat($this->nextTd($this->t("Seat:"), $root), true, true)
                    ->aircraft($this->nextTd($this->t("Aircraft:"), $root))
                    ->cabin($this->nextTd($this->t("Class of Service:"), $root))
                    ->duration($this->http->FindSingleNode(".//text()[contains(normalize-space(), 'FLIGHT TIME:')][1]", $root, true, "#FLIGHT TIME:\s*(.+?)(?:\||$)#"))
                    ->stops(!empty($this->nextTd($this->t("Flight Type:"), $root, "#NON-STOP#i")) ? 0 : null, true, true)
                ;
            }
        }

        //#################
        //##   HOTELS   ###
        //#################

        $xpath = "//table[" . $this->starts($this->t("HOTEL")) . " and " . $this->contains($this->t("Check-in Date:")) . " and not(.//table)]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            if (!empty($passengers)) {
                $h->general()->travellers($passengers, true);
            }

            $h->general()
                ->confirmation($this->nextTd($this->t("Confirmation #:"), $root))
                ->cancellation(trim($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("CANCEL ")) . "][1]", $root, true, "#(?:FONE|PHONE)\s*[\d \-\+\(\)]{5,}\s+(CANCEL[^\|]+)\s*(?:\||$)#"))); // | FONE 1-212-7444300 CANCEL 01 DAYS PRIOR TO ARRIVAL |

            $h->hotel()
                ->name($this->nextTd($this->t("Hotel Name:"), $root))
                ->address($this->nextTd($this->t("Hotel Address:"), $root, "#(.+?)(?:FONE|PHONE|FAX)#"))
                ->phone($this->nextTd($this->t("Hotel Address:"), $root, "#(?:FONE|PHONE)\s*([\d \-\+\(\)]{5,})\s*(?:FAX|$)#"), true, true)
                ->fax($this->nextTd($this->t("Hotel Address:"), $root, "#FAX\s*([\d \-\+\(\)]{5,})\s*$#"), true, true)
                ->chain($this->nextTd($this->t("Hotel Vendor:"), $root));

            $h->booked()
                ->checkIn($this->normalizeDate($this->nextTd($this->t("Check-in Date:"), $root)))
                ->checkOut($this->normalizeDate($this->nextTd($this->t("Check-out Date:"), $root)))
                ->rooms($this->nextTd($this->t("Number of Rooms:"), $root))
                ->guests($this->nextTd($this->t("Number of Persons:"), $root))
            ;

            $total = trim($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("APPROXIMATE TOTAL PRICE")) . "][1]", $root, true, "#([^\|]+)APPROXIMATE TOTAL PRICE\s*(?:\||$)#"));

            if (!empty($total) && (preg_match("#^\s*(?<cur>[^\d\s]{1,4})\s*(?<amount>\d[\d\.]*)\s*$#", $total, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\.]*)\s*(?<cur>[^\d\s]{1,4})\s*$#", $total, $m))) {
                if (is_numeric($m['amount'])) {
                    $h->price()
                        ->total((float) $m['amount'])
                        ->currency($this->currency($m['cur']));
                }
            }
        }

        //###############
        //##   CARS   ###
        //###############

        $xpath = "//table[" . $this->starts($this->t("CAR")) . " and " . $this->contains($this->t("Pick-up Date:")) . " and not(.//table)]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            if (!empty($passengers)) {
                $r->general()->travellers($passengers, true);
            }

            $r->general()
                ->confirmation($this->nextTd($this->t("Confirmation #:"), $root));

            $r->pickup()
                ->location($this->nextTd($this->t("Pick-Up City:"), $root))
                ->date($this->normalizeDate($this->nextTd($this->t("Pick-up Date:"), $root)));

            $r->dropoff()
                ->location($this->nextTd($this->t("Return City:"), $root) ?? $this->nextTd($this->t("Pick-Up City:"), $root))
                ->date($this->normalizeDate($this->nextTd($this->t("Return Date:"), $root)));

            $r->car()
                ->type($this->nextTd($this->t("Car Type:"), $root));

            $r->extra()
                ->company($this->nextTd($this->t("Car Vendor:"), $root));

            $total = $this->nextTd($this->t("Approx. Rental Cost:"), $root);

            if (!empty($total) && (preg_match("#^\s*(?<cur>[^\d\s]{1,4})\s*(?<amount>\d[\d\.]*)\s*$#", $total, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\.]*)\s*(?<cur>[^\d\s]{1,4})\s*$#", $total, $m))) {
                if (is_numeric($m['amount'])) {
                    $r->price()
                        ->total((float) $m['amount'])
                        ->currency($this->currency($m['cur']));
                }
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $code => $dFrom) {
            if (strpos($from, $dFrom) !== false) {
                $this->providerCode = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectFrom as $code => $dFrom) {
            if (strpos($headers["from"], $dFrom) !== false) {
                $this->providerCode = $code;

                return true;
            }
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
        $body = $parser->getHTMLBody();

        if ($this->getProvider($this->http->Response['body']) === false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            foreach ($dBody as $value) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$value}')]")->length > 0) {//stripos($body, $re) !== false
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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectCompany);
    }

    private function getProvider($body)
    {
        foreach (self::$detectCompany as $code => $dBody) {
            if (strpos($body, $dBody) !== false) {
                return $code;
            }
        }

        return false;
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
        $in = [
            "#^\s*([^\s\d]+) (\d+),\s*(\d{4})\s+(?:AT|BY)\s+(\d+:\d+\s*([AP]M)?)\s*$#", //SEPTEMBER 2, 2014 AT 03:43 PM
            "#^\s*(\d+:\d+\s*(?:[AP]M)?)\s+on\s+([^\s\d]+) (\d+)\s+(\d{4})\s*$#", //04:50 PM on Sep 7 2014
        ];
        $out = [
            "$2 $1 $3 $4",
            "$3 $2 $4 $1",
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

    private function nextTd($field, $root = null, $regexp = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/ancestor::td[1]/following-sibling::td[1]", $root, true, $regexp);
    }

    private function eq($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "normalize-space({$text})=\"{$s}\""; }, $field));
    }

    private function starts($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "starts-with(normalize-space({$text}), \"{$s}\")"; }, $field));
    }

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains(normalize-space({$text}), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
