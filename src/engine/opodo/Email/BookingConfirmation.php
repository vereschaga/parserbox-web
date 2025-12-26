<?php

namespace AwardWallet\Engine\opodo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "opodo/it-10019149.eml, opodo/it-17269334.eml, opodo/it-17269378.eml, opodo/it-19019781.eml, opodo/it-20835294.eml, opodo/it-6707599.eml, opodo/it-6707603.eml, opodo/it-6707614.eml";
    public static $detectCompany = [
        "edreams"   => "eDreams",
        "opodo"     => "Opodo",
        "tllink"    => "Travellink",
        "fcmtravel" => "FCM Travel Solutions",
    ];

    public static $dictionary = [
        "en" => [
            "Booking reference" => ["Booking reference", "Airline ticket reference", "Airline booking reference"],
            //			"Traveller:" => "",
            //			"Total price:" => "",
            //			"Operated by" => "",// need translate
        ],
        "pl" => [
            "Booking reference" => ["Numer rezerwacji linii lotniczej"],
            "Traveller:"        => "Podróżujący:",
            "Total price:"      => "Cena całkowita:",
            "Operated by"       => "Obslugiwany przez:",
        ],
        "da" => [
            "Booking reference" => ["Flyselskabets bookingreference", "Travellink booking reference"],
            "Traveller:"        => "Rejsende:",
            "Total price:"      => "I alt:",
            "Operated by"       => "Flyves af",
        ],
        "no" => [
            "Booking reference" => ["Flyselskapets bestillingsreferanse"],
            "Traveller:"        => "Reisende ",
            "Total price:"      => "Totalpris",
            "Operated by"       => "Stkningen trafikkeres av",
            'OUTBOUND'          => 'UTREISE',
        ],
        "de" => [
            "Booking reference" => ["Flug-Buchungsnummer", "Buchungsreferenz Fluggesellschaft:"],
            "Traveller:"        => "Reisende:",
            "Total price:"      => "Gesamtpreis:",
            "Operated by"       => "Ausgeführt durch",
        ],
        "sv" => [
            "Booking reference" => ["Bokningsreferens:"],
            "Traveller:"        => "Resenär:",
            "Total price:"      => "Totalpris:",
            "Operated by"       => "Ausgeführt durch",
            'OUTBOUND'          => 'UTRESA',
        ],
    ];
    private $detectFrom = [
        "edreams"   => ["edreams."],
        "opodo"     => ["opodo.", "opodocorporate."],
        "tllink"    => ["travellink."],
        "fcmtravel" => ["fcmtravel."],
    ];
    private $detectSubject = [
        "en" => "Booking confirmation from",
        "pl" => "Potwierdzenie Rezerwacji od",
        "da" => " - Bookingbekræftelse",
        "no" => " - Bestillingsbekreftelse",
        "de" => " - Buchungsbestätigung von",
    ];
    private $detectBody = [
        "da" => ["Bookingbekræftelse"],
        "en" => ["Booking confirmation"],
        "pl" => ["Potwierdzenie rezerwacji"],
        "no" => ["Bestillingsbekreftelse"],
        "de" => ["Buchungsbestätigung"],
        "sv" => ["Bokningsbekräftelse"],
    ];

    private $lang = "en";
    private $provider;

    public static function getEmailProviders()
    {
        return array_keys(self::$detectCompany);
    }

    public function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->starts($this->t("Booking reference"))}])[1]", null, true, "#{$this->opt($this->t("Booking reference"))}[:\s]+([A-Z\d]{5,})\b#"))
            ->travellers($this->http->FindNodes("//text()[{$this->starts($this->t("Traveller:"))}]", null, "#{$this->t("Traveller:")}\s*(.+)#"));

        $total = $this->nextText($this->t("Total price:"));

        if (!empty($total)) {
            $f->price()
                ->total(PriceHelper::parse($this->amount($total), $this->currency($total)))
                ->currency($this->currency($total));
        }

        $xpath = "//img[contains(@src, '/images/airlines/')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//text()[starts-with(translate(normalize-space(.), '0123456789', 'dddddddddd'), 'dd:dd ')]/ancestor::tr[1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }
        $date = 0;

        foreach ($nodes as $root) {
            if ($datestr = $this->http->FindSingleNode("./td[1]/div", $root, true, "#^[A-Z]+[\s:]+(.+)#")) {
                $date = strtotime($this->normalizeDate($datestr));
            } elseif ($datestr = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[{$this->starts($this->t('OUTBOUND'))}][1]", $root, true, "#^[A-Z]+[\s:]+(.+)#")) {
                $date = strtotime($this->normalizeDate($datestr));
            }

            if ($datestr = $this->http->FindSingleNode("./td[1]/div", $root, true, "#^\w+[\s:]+(.+)#u")) {
                $date = strtotime($this->normalizeDate($datestr));
            }

            $s = $f->addSegment();

            $s->airline()
                ->number($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\w{2}(\d+),#"))
                ->name($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\w{2})\d+,#"));

            $depName = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()-1]", $root, true, "#\d+:\d+\s+(.*?)(?:\s+Terminal|$)#");

            if (preg_match("#^(\w{2,4}) (.+)#u", $depName, $m) && WeekTranslate::number1($m[1]) !== null) { //man Sacheon(HIN), Jinju, Korea (Republic Of)
                $depName = $m[2];
            }

            $s->departure()
                ->name($depName)
                ->date(strtotime($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()-1]", $root, true, "#\d+:\d+#"), $date))
                ->code($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()-1]", $root, true, "#\(([A-Z]{3})\)#"));

            $depTerminal = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()-1]", $root, true, "#Terminal\s*(.+)#");

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $arrName = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#\d+:\d+\s+(.*?)(?:\s+Terminal|$)#");

            if (preg_match("#^(\w{2,4}) (.+)#u", $arrName, $m) && WeekTranslate::number1($m[1]) !== null) { //man Sacheon(HIN), Jinju, Korea (Republic Of)
                $arrName = $m[2];
            }

            $s->arrival()
                ->name($arrName)
                ->code($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#\(([A-Z]{3})\)#"))
                ->date(strtotime($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#\d+:\d+#"), $date));

            $arrTerminal = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#Terminal\s*(.+)#");

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $cabin = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][2]", $root);

            if (preg_match("#" . $this->opt($this->t("Operated by")) . "\s*(.+)#", $cabin, $m)) {
                $s->airline()
                    ->operator($m[1]);

                $cabin = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][3]", $root);
            }

            if (!empty($cabin)) {
                $s->setCabin($cabin);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $code => $dFroms) {
            foreach ($dFroms as $dFrom) {
                if (strpos($from, $dFrom) !== false) {
                    $this->provider = $code;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach (self::$detectCompany as $code => $dCompany) {
            if (strpos($headers["subject"], $dCompany) !== false) {
                $finded = true;
                $this->provider = $code;

                break;
            }
        }

        if ($finded == false) {
            foreach ($this->detectFrom as $code => $dFroms) {
                foreach ($dFroms as $dFrom) {
                    if (strpos($headers["from"], $dFrom) !== false) {
                        $this->provider = $code;
                        $finded = true;

                        break 2;
                    }
                }
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

        foreach (self::$detectCompany as $code => $dCompany) {
            if (strpos($body, $dCompany) !== false) {
                $finded = true;
                $this->provider = $code;

                break;
            }
        }

        if (!$finded) {
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

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($this->http->Response["body"], $dBody) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $this->parseHtml($email);

        if (!empty($this->provider)) {
            $email->setProviderCode($this->provider);
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^\s*(?:\d\s+)?[^\d\s]+\s+(\d+)[., ]+([^\d\s.,]+)[., ]+(\d{4})$#", //Mon 20 Apr 2015; man. 5. sep. 2016
            "#^\s*(?:\d\s+)?[^\d\s]+\s+(\d+)[., ]+([^\d\s.,]+)[., ]+(\d{2})$#", //Mi 19 September 18
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 20$3",
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
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#([\d\,\. ]+)#", $s)));
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

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains(normalize-space({$text}), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
