<?php

namespace AwardWallet\Engine\opodo\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "opodo/it-15193286.eml, opodo/it-16672340.eml, opodo/it-16711587.eml, opodo/it-5035083.eml, opodo/it-5902879.eml, opodo/it-8621942.eml";

    public static $dictionary = [
        "en" => [
            //			"Confirmation Number:" => "",
            //			"Flight details" => "",
            //			"Name(s) of traveller(s)" => "",
            "Outbound" => ["Outbound", "Inbound"],
            //			"Operated by:" => "", // to check

            //			"Hotel details" => "",
            //			"Confirmed," => "",
            //			"Check-in" => "",
            //			"Check-out" => "",
            //			"Address" => "",
            //			"Number of rooms" => "",
            //			"Room type" => "",

            //			"Total" => "",
            //			"Mukana matkustava vauva" => "", // to translate
            //			"Kanta-asiakaskortti:" => "", // to translate
            //			"phone:" => "",
        ],
        "fi" => [
            "Confirmation Number:"    => ["Varausnumerosi", "Lentoyhtiön varausnumero lähtöselvitystä varten"],
            "Flight details"          => "Varatut lennot",
            "Name(s) of traveller(s)" => "Matkustajat",
            "Outbound"                => ["Meno", "Paluu"],
            "Operated by:"            => "Lennon operoi:",

            "Hotel details"   => "Varattu hotelli",
            "Confirmed,"      => "Vahvistettu,",
            "Check-in"        => "Sisäänkirjautuminen",
            "Check-out"       => "Uloskirjautuminen",
            "Address"         => "Osoite:",
            "Number of rooms" => "Huoneiden määrä",
            "Room type"       => "Huonetyyppi",

            "Total"                   => "Yhteensä",
            "Mukana matkustava vauva" => "Mukana matkustava vauva",
            "Kanta-asiakaskortti:"    => "Kanta-asiakaskortti:",
            "phone:"                  => "puhelin:",
        ],
    ];
    private $detectFrom = [
        "wizz"   => "wizzair.", // ?
        "opodo"  => "opodo.",
        "tllink" => "travellink.", // after wizz
    ];
    private $detectSubject = [
        "en" => "Booking confirmation",
        "fi" => "Vahvistus",
    ];
    private $detectCompany = [
        "wizz"   => "Wizz Tours",
        "opodo"  => "Opodo",
        "tllink" => "Travellink",
    ];
    private $detectBody = [
        "en" => "Flight details",
        "fi" => "Varatut lennot",
    ];

    private $lang = "en";
    private $provider;

    public static function getEmailProviders()
    {
        return ["wizz", "opodo", "tllink"];
    }

    public function flight(Email $email)
    {
        // Price
        $email->price()
            ->total($this->amount($this->nextText($this->t("Total"))))
            ->currency($this->currency($this->nextText($this->t("Total"))));

        //		$travellers = array_values(array_filter($this->http->FindNodes("//text()[".$this->eq($this->t("Name(s) of traveller(s)"))."]/ancestor::div[2]/descendant::text()[normalize-space(.)][position()>1]", null, "#(?:".$this->preg_implode($this->t("Mukana matkustava vauva"))."\s+)?(.*?)\s+\(#")));
        $travellers = array_values(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Name(s) of traveller(s)")) . "]/ancestor::div[not(" . $this->eq($this->t("Name(s) of traveller(s)")) . ")][1]/descendant::text()[normalize-space(.)][position()>1]", null, "#(?:" . $this->preg_implode($this->t("Mukana matkustava vauva")) . "\s+)?(.*?)\s+\(#")));

        /*
         * FLIGHTS
         */
        if (!empty($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Flight details")) . "]"))) {
            $f = $email->add()->flight();

            // General
            $f->general()->travellers($travellers, true);

            $confsText = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Flight details")) . "]/ancestor::*[2]/descendant::text()[normalize-space(.)][1]", null, true, "#" . $this->preg_implode($this->t("Confirmed,")) . "\s+$#");

            if (empty($confsText)) {
                $confsText = $this->http->FindSingleNode("(.//text()[" . $this->contains($this->t("Confirmation Number:")) . "])[1]/following::text()[normalize-space(.)][1]");
            }

            if (!empty($confsText)) {
                $confs = array_filter(array_map('trim', explode(",", $confsText)),
                        function ($v) { if (preg_match("#^[A-Z\d]{5,}$#", $v)) {return true; }

return false; });

                foreach ($confs as $conf) {
                    $f->general()->confirmation($conf);
                }
            }

            // Segments
            $xpath = "//text()[" . $this->starts($this->t("Outbound")) . "]/ancestor::*[.//table][1]//tr[not(.//tr)]";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $s = $f->addSegment();

                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./ancestor::table[1]/preceding::text()[normalize-space(.)][1]", $root)));

                if (empty($date)) {
                    break;
                }

                // Airline
                $s->airline()
                    ->name($this->http->FindSingleNode("./td[4]/descendant::text()[not(" . $this->contains($this->t("Operated by:")) . ")][normalize-space(.)][2]", $root, true, "#^(\w{2})\s+\d+$#"))
                    ->number($this->http->FindSingleNode("./td[4]/descendant::text()[not(" . $this->contains($this->t("Operated by:")) . ")][normalize-space(.)][2]", $root, true, "#^\w{2}\s+(\d+)$#"))
                    ->operator($this->http->FindSingleNode("./td[4]/descendant::text()[" . $this->contains($this->t("Operated by:")) . "][1]", $root, true, "#:\s*(.+)#"), true, true);

                // Departure
                $s->departure()
                    ->code($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#\(([A-Z]{3})\)#"))
                    ->name($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s*\([A-Z]{3}\)#"));
                $time = $this->normalizeDate($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root));

                if (!empty($time)) {
                    $s->departure()->date(strtotime($time, $date));
                }

                // Arrival
                $s->arrival()
                    ->code($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][3]", $root, true, "#\(([A-Z]{3})\)#"))
                    ->name($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][3]", $root, true, "#(.*?)\s*\([A-Z]{3}\)#"));
                $time = $this->normalizeDate($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][2]", $root));

                if (!empty($time)) {
                    $s->arrival()->date(strtotime($time, $date));
                }

                // Extras
                $s->extra()
                    ->cabin($this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][last()]", $root));

                if (count($this->http->FindNodes("./ancestor::*[1]/*", $root)) == 1) {
                    $s->extra()->duration($this->http->FindSingleNode("./td[normalize-space()][last()]", $root, true, "#^\s*\d+.*#"));
                }
            }
        }

        /*
         * HOTEL
         */
        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Hotel details")) . "])[1]"))) {
            $nodes = $this->http->XPath->query("//text()[" . $this->eq($this->t("Hotel details")) . "]/ancestor::*[2]");

            foreach ($nodes as $root) {
                $h = $email->add()->hotel();

                // General
                $h->general()
                    ->confirmation($this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $root, true, "#" . $this->preg_implode($this->t("Confirmed,")) . "\s+(\d+)$#"))
                    ->travellers($travellers, true);

                // Hotel
                $h->hotel()
                    ->name($this->http->FindSingleNode("./descendant::text()[normalize-space(.)][3]", $root))
                    ->address(implode(" ", $this->http->FindNodes(".//text()[" . $this->eq($this->t("Address")) . "]/../descendant::text()[normalize-space(.)][position()>1]", $root)));

                // Booked
                $h->booked()
                    ->checkIn(strtotime($this->normalizeDate($this->http->FindSingleNode("(.//text()[" . $this->starts($this->t("Check-in")) . "])[1]/following::text()[normalize-space(.)][1]", $root))))
                    ->checkOut(strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Check-out")) . "]/following::text()[normalize-space(.)][1]", $root))))
                    ->rooms($this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Number of rooms")) . "]/following::text()[normalize-space(.)][1]", $root));

                $h->addRoom()->setType($this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Room type")) . "]/following::text()[normalize-space(.)][1]", $root));
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

        // Provider
        if (!empty($this->provider)) {
            $codeProvider = $this->provider;
        } else {
            $codeProvider = $this->getProvider();
        }

        if (!empty($codeProvider)) {
            $email->setProviderCode($codeProvider);
            $email->ota()->code($codeProvider);

            if (!empty($this->detectCompany[$codeProvider])) {
                $phone = $this->http->FindSingleNode("(//text()[" . $this->starts($this->detectCompany[$codeProvider]) . " and " . $this->contains($this->t("phone:")) . "])", null, true, "#" . $this->preg_implode($this->t("phone:")) . "\s*([\d\+ \(\)]{5,})\.#");

                if (strlen(preg_replace("#[^\d]+#", '', $phone)) > 7) {
                    $email->ota()->phone($phone);
                }
            }
            $accounts = array_unique(array_filter($this->http->FindNodes("(//text()[" . $this->starts($this->t("Kanta-asiakaskortti:")) . "])", null, "#" . $this->preg_implode($this->t("Kanta-asiakaskortti:")) . "\s*([\d]{5,})\b#")));

            if (!empty($accounts)) {
                $email->ota()->accounts($accounts, false);
            }
        }

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $code => $dFrom) {
            if (strpos($from, $dFrom) !== false) {
                $this->provider = $code;

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
            if (strpos($body, $dCompany) !== false && $this->http->XPath->query("//a[contains(@href,'" . $this->detectFrom[$code] . "')]")->length > 2) {
                $finded = true;
                $this->provider = $code;

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

    private function getProvider()
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectCompany as $code => $detectCompany) {
            foreach ($detectCompany as $dCompany) {
                if (stripos($body, $dCompany) !== false) {
                    return $code;
                }
            }
        }

        return null;
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
        $in = [
            "#^\s*[^\s\d]+ - [^\s\d]+ (\d+ [^\s\d]+ \d{4})$#", //Inbound - Thursday 15 October 2015
            "#^(\d+:\d+) [^\s\d]+$#", //21:45 Sun
            "#^[^\s\d]+ (\d+ [^\s\d]+ \d{4})$#", //Thursday 15 October 2015
        ];
        $out = [
            "$1",
            "$1",
            "$1",
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
