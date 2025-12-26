<?php

namespace AwardWallet\Engine\myflights\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingHasChanged extends \TAccountChecker
{
    public $mailFiles = "myflights/it-16453315.eml, myflights/it-16453359.eml, myflights/it-16453607.eml, myflights/it-16453612.eml, myflights/it-16763283.eml, myflights/it-16763318.eml, opodo/it-15193286.eml, opodo/it-16672340.eml, opodo/it-5035083.eml, opodo/it-5902879.eml, opodo/it-8621942.eml";

    public static $dictionary = [
        "en" => [],
    ];
    private $detectFrom = '@myflightsapp.com';
    private $detectSubject = [
        "en" => "Your booking has changed",
    ];
    private $detectCompany = "myflightsapp.com";
    private $detectBody = [
        "en" => ["Your booking has changed", "been a change to your upcoming booking", "been a change to the following booking"],
    ];

    private $date;
    private $lang = "en";
    private $emailSubject = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $body = html_entity_decode($this->http->Response["body"]);

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->emailSubject = $parser->getSubject();

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->detectFrom) === false) {
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

        if (strpos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
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

    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("BOOKING REF")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#");

        if (empty($conf) && !empty($this->emailSubject) && preg_match("#[:\-]\s+([A-Z\d]{5,7})\s*$#", $this->emailSubject, $m)) {
            $conf = $m[1];
        }

        $f->general()
            ->travellers($this->http->FindNodes("//text()[" . $this->starts($this->t("TRAVELLER")) . "]/ancestor::td[1]/following-sibling::td[1]"), true)
            ->confirmation($conf);

        $tickets = $this->http->FindNodes("//text()[" . $this->eq($this->t("E-Ticket")) . "]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space() and not(ancestor::*[position()<3][contains(@style,'line-through')])]", null, "#\b([\d\-]{10,})\b#");
        $f->issued()
            ->tickets(array_filter($tickets), false);

        // Price
        $priceText = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Price Breakdown")) . "]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space()]"));

        if (preg_match_all("#(?:^|\n)\s*" . $this->preg_implode($this->t("Total")) . "s*\n\s*(\d[\d,.]+)\s*([A-Z]{3})\s*(?:\n|$)#", $priceText, $m)) {
            $total = 0.0;

            foreach ($m[1] as $value) {
                $total += $this->amount($value);
            }

            if (count(array_unique($m[2]))) {
                $f->price()
                    ->total($total)
                    ->currency($m[2][0]);
            }
        }

        if (preg_match_all("#(?:^|\n)\s*" . $this->preg_implode($this->t("Price Without Tax")) . "s*\n\s*(\d[\d,.]+)\s*(?:\n|$)#", $priceText, $m)) {
            $cost = 0.0;

            foreach ($m[1] as $value) {
                $cost += $this->amount($value);
            }
            $f->price()
                ->cost($cost);
        }

        if (preg_match_all("#(?:^|\n)\s*" . $this->preg_implode($this->t("Tax")) . "s*\n\s*(\d[\d,.]+)\s*(?:\n|$)#", $priceText, $m)) {
            $fee = 0.0;

            foreach ($m[1] as $value) {
                $fee += $this->amount($value);
            }
            $f->price()
                ->fee($this->t("Tax"), $fee);
        }

        if (preg_match_all("#(?:^|\n)\s*" . $this->preg_implode($this->t("Service Fee")) . "s*\n\s*(\d[\d,.]+)\s*(?:\n|$)#", $priceText, $m)) {
            $fee = 0.0;

            foreach ($m[1] as $value) {
                $fee += $this->amount($value);
            }
            $f->price()
                ->fee($this->t("Service Fee"), $fee);
        }

        // Segments
        $xpath = "//text()[contains(translate(normalize-space(), '0123456789', 'dddddddddd'),  'd:dd')]/ancestor::tr[2][count(./td)=5]/ancestor::tr[1]/ancestor::*[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if (empty(array_filter($this->http->FindNodes("./tr[1]//text()[not(ancestor-or-self::*[position()<3][contains(@style,'line-through')])]", $root)))) {
                continue;
            }

            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("(.//td[not(.//td)])[1]", $root));

            if (empty($date)) {
                $this->logger->info("date not detect");

                return $email;
            }

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./tr[1]//tr[count(./td) = 4]/td[3]", $root, true, "#^\s*([A-Z\d]{2})\s*\d{1,5}\s*$#"))
                ->number($this->http->FindSingleNode("./tr[1]//tr[count(./td) = 4]/td[3]", $root, true, "#^\s*[A-Z\d]{2}\s*(\d{1,5})\s*$#"))
                ->operator($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Operated by")) . "][1]", $root, true, "#" . $this->preg_implode($this->t("Operated by")) . "\s+(.+)#"), true, true);

            $pnr = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("AIRLINE REF")) . "]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space() and not(ancestor::*[position()<3][contains(@style,'line-through')])]", $root, true, "#^\s*([A-Z\d]{5,7})\s*$#");

            if (!empty($pnr)) {
                $s->airline()->confirmation($pnr);
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("./tr[2]//tr[count(./td) = 5][1]/td[1]", $root, true, "#^\s*([A-Z]{3})#"))
                ->name($this->http->FindSingleNode("(./tr[2]//tr[count(./td) = 5][1]/td[2]//text()[normalize-space(.)])[1]", $root))
                ->terminal($this->http->FindSingleNode("./tr[2]//tr[count(./td) = 5][1]/td[1]", $root, true, "#TERMINAL\s*(.+)#"), true, true);
            $time = $this->http->FindSingleNode("(./tr[2]//tr[count(./td) = 5][1]/td[2]//text()[normalize-space(.)])[2]", $root, true, "#\d+:\d+#");

            if (!empty($time)) {
                $s->departure()->date(strtotime($time, $date));
            }

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("./tr[2]//tr[count(./td) = 5][1]/td[4]", $root, true, "#^\s*([A-Z]{3})#"))
                ->name($this->http->FindSingleNode("(./tr[2]//tr[count(./td) = 5][1]/td[5]//text()[normalize-space(.)])[1]", $root))
                ->terminal($this->http->FindSingleNode("./tr[2]//tr[count(./td) = 5][1]/td[4]", $root, true, "#TERMINAL\s*(.+)#"), true, true);
            $time = $this->http->FindSingleNode("(./tr[2]//tr[count(./td) = 5][1]/td[5]//text()[normalize-space(.)])[2]", $root, true, "#.*\d+:\d+.*#");

            if (!empty($time) && preg_match("#^\s*(\d+:\d+)\s*(?:\+\s*(\d+)\s*\w+)?\s*$#u", $time, $m)) {
                $s->arrival()->date(strtotime($m[1], $date));

                if (!empty($m[2])) {
                    $s->arrival()->date(strtotime("+" . $m[2] . "day", $s->getArrDate()));
                }
            }

            // Extras
            $s->extra()
                ->duration($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("DURATION")) . "]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space() and not(ancestor::*[position()<3][contains(@style,'line-through')])]", $root), true, true)
                ->cabin($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("CLASS")) . "]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space() and not(ancestor::*[position()<3][contains(@style,'line-through')])]", $root))
                ->aircraft($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("AIRCRAFT")) . "]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space() and not(ancestor::*[position()<3][contains(@style,'line-through')])]", $root))
                ->meal($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("MEALS")) . "]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space() and not(ancestor::*[position()<3][contains(@style,'line-through')])]", $root), true, true);
            $seats = array_filter($this->http->FindNodes("./tr[3]//tr[count(./td) = 2]/td[2]//text()[not(ancestor-or-self::*[position()<3][contains(@style,'line-through')])]", $root, "#^\s*(\d{1,3}[A-Z])\s*($| \()#"));

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }
        }
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^\s*([^\s\d]+)\s+(\d+)[a-z]{2}\s+([^\s\d]+)\s*$#", //Thu 1st June
        ];
        $out = [
            "$1, $2 $3 $year",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^\s*([^\s\d]+),\s*(.+)\s+\d{4}#", $date, $m)) {
            $dayOfWeekInt = WeekTranslate::number1($m[1], $this->lang);

            return EmailDateHelper::parseDateUsingWeekDay($m[2], $dayOfWeekInt);
        }

        return strtotime($date);
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
