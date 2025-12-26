<?php

namespace AwardWallet\Engine\wizz\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "wizz/it-17801747.eml, wizz/it-18047687.eml";

    public static $dictionary = [
        "en" => [],
    ];
    private $detectFrom = [
        "noreply@wizztours.com",
    ];
    private $detectSubject = [
        "en" => "Booking Confirmation - ",
    ];
    private $detectCompany = [
        "Wizz Tours",
    ];
    private $detectBody = [
        "en" => "Please check that your travel details",
    ];

    private $lang = "en";
    private $provider;

    public function parseEmail(Email $email)
    {
        // Price
        $total = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Paid in full')]/ancestor::*[local-name()='th' or local-name()='td'][(position() = 1 or position()=2) and following-sibling::*[1]]/following-sibling::*[normalize-space()][1]");

        if (!empty($total)) {
            $email->price()
                ->total($this->amount($total))
                ->currency($this->currency($total));
        }

        // Travel Agency
        $email->ota()->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Wizz Tours Booking Number')]/following::text()[normalize-space()][1]"), "Booking Number");

        $travellers = array_values(array_filter($this->http->FindNodes("//text()[normalize-space() = 'Travellers']/ancestor::*[1]/following-sibling::table", null, "#(.+?)\s+\(#")));

        /*
         * FLIGHTS
         */
        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("Flight Info")) . "])[1]"))) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Flight Confirmation Number(s)')]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]+)\s*$#"), "Flight Confirmation Number(s)")
                ->travellers($travellers, true);

            // Segments
            $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::table[following-sibling::table[contains(normalize-space(), 'Arrival')]]";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $s = $f->addSegment();

                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("(./preceding::th[string-length(normalize-space())>2][position()<5][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dddd')])[1]", $root)));

                if (empty($date)) {
                    break;
                }

                // Airline
                $s->airline()
                    ->name($this->http->FindSingleNode("(.//tr)[1]/th[3]", $root, true, "#^([A-Z\d]{2})\s*\d{1,5}\b#"))
                    ->number($this->http->FindSingleNode("(.//tr)[1]/th[3]", $root, true, "#^[A-Z\d]{2}\s*(\d{1,5})\b#"));

                // Departure
                $s->departure()
                    ->code($this->http->FindSingleNode("(.//tr)[1]/th[1]", $root, true, "#^\s*([A-Z]{3})\b#"))
                    ->name($this->http->FindSingleNode("(.//tr)[1]/th[1]", $root, true, "#^\s*[A-Z]{3}\b(.+)#"));
                $time = $this->http->FindSingleNode("(.//tr)[1]/th[2]", $root, true, "#\d+:\d+#");

                if (!empty($time)) {
                    $s->departure()->date(strtotime($time, $date));
                }

                // Arrival
                $s->arrival()
                    ->code($this->http->FindSingleNode("(./following-sibling::table[contains(normalize-space(), 'Arrival')]//tr)[1]/th[1]", $root, true, "#^\s*([A-Z]{3})\b#"))
                    ->name($this->http->FindSingleNode("(./following-sibling::table[contains(normalize-space(), 'Arrival')]//tr)[1]/th[1]", $root, true, "#^\s*[A-Z]{3}\b(.+)#"));
                $time = $this->http->FindSingleNode("(./following-sibling::table[contains(normalize-space(), 'Arrival')]//tr)[1]/th[2]", $root, true, "#\d+:\d+#");

                if (!empty($time)) {
                    $s->arrival()->date(strtotime($time, $date));
                }

                // Extras
                $s->extra()
                    ->duration($this->http->FindSingleNode("(./following-sibling::table[contains(normalize-space(), 'Arrival')]//tr)[1]/th[3]", $root, true, "#:\s*(.+)#"));
            }
        }

        /*
         * HOTEL
         */
        if (!empty($this->http->FindSingleNode("(//*[" . $this->eq($this->t("Accommodation Info")) . "])[1]"))) {
            $nodes = $this->http->XPath->query("//*[normalize-space() = 'Accommodation Info']/following::text()[string-length(normalize-space())>2][1]/ancestor::table[2]");

            foreach ($nodes as $root) {
                $h = $email->add()->hotel();

                // General
                $h->general()
                    ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Accommodation Booking Reference')]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d\-]+)\s*$#"), "Accommodation Booking Reference(s)")
                    ->travellers($travellers, true);

                // Hotel
                $h->hotel()
                    ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root))
                    ->address($this->http->FindSingleNode("./descendant::text()[normalize-space() and not(contains(., '☆'))][2]", $root))
                    ->phone($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Phone')]/following::text()[normalize-space()][1]", $root, true, "#^[\d\+\-\(\) ]{5,}$#"));

                // Booked
                $path = "/following-sibling::table//text()[normalize-space() = 'Room Type']/ancestor::table[position()<3 and contains(normalize-space(),'Check-in')]";
                $h->booked()
                    ->checkIn(strtotime($this->normalizeDate($this->http->FindSingleNode("(." . $path . "[1]/following-sibling::table[1]//tr)[1]/th[3]", $root))))
                    ->checkOut(strtotime($this->normalizeDate($this->http->FindSingleNode("(." . $path . "[1]/following-sibling::table[1]//tr)[1]/th[4]", $root))));

                $guest = array_filter($this->http->FindNodes("." . $path . "/following-sibling::table[1]//descendant::tr[1]/th[2]", $root, "#^\s*(\d+)\s*$#"));

                if (!empty($guest)) {
                    $h->booked()->guests(array_sum($guest));
                }

                $time = $this->http->FindSingleNode("./following-sibling::*[contains(normalize-space(), 'Check-in hour' )]", $root, true, "#Check-in hour\s+(\d+:\d+)#");

                if (!empty($time) && !empty($h->getCheckInDate())) {
                    $h->booked()->checkIn(strtotime($time, $h->getCheckInDate()));
                }

                $rooms = $this->http->FindNodes("." . $path . "/following-sibling::table[1]/descendant::tr[1]/th[1]", $root);

                foreach ($rooms as $room) {
                    $h->addRoom()->setType($room);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response["body"]);

        foreach ($this->detectBody as $lang => $dBody) {
            if (stripos($body, $dBody) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $code => $dFrom) {
            if (strpos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->detectFrom as $dFrom) {
            if (strpos($headers["from"], $dFrom) !== false) {
                $finded = true;

                break;
            }
        }

        if (!$finded) {
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
        $body = $parser->getHTMLBody();
        $finded = false;

        foreach ($this->detectCompany as $code => $dCompany) {
            if (strpos($body, $dCompany) !== false) {
                $finded = true;

                break;
            }
        }

        if (!$finded) {
            return false;
        }

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
        //		 $this->http->log($str);
        $in = [
            "#^\s*[^\s\d]+\s+[^\s\d]+ (\d+ [^\s\d]+?),\s+(\d{4})\s*$#", //Inbound Thursday 15 October 2015
            "#^\s*(\d{4})[.\s]+(\d{1,2})[.\s]+(\d{1,2})[.\s]*$#", //2018. 08. 23.
        ];
        $out = [
            "$1 $2",
            "$3.$2.$1",
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
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
