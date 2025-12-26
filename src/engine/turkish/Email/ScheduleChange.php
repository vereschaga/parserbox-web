<?php

namespace AwardWallet\Engine\turkish\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ScheduleChange extends \TAccountChecker
{
    public $mailFiles = "turkish/it-34402748.eml, turkish/it-34442745.eml, turkish/it-48328341.eml, turkish/it-60849208.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            "statusVariants"    => ["cancelled", "canceled"],
            "cancelledVariants" => ["cancelled", "canceled"],
            //            "NAME AND SURNAME" => "",
            //            "RESERVATION CODE" => "",
            //            "TICKET NUMBER" => "",
            //            "NEW FLIGHT INFORMATION" => "",
            //            "FROM" => "",
            "DEPARTURE TIME" => ["DEPARTURE TIME", "DEPERTURE TIME"],
            //            "DEPARTURE DATE" => "",
            //            "FLIGHT NO" => "",
            //            "CABIN" => "",
            //            "CLASS" => "",
        ],
        "tr" => [
            //            "statusVariants" => "",
            //            "cancelledVariants" => "",
            "NAME AND SURNAME"       => "ÜNVAN / AD SOYAD",
            "RESERVATION CODE"       => "PNR KODU",
            "TICKET NUMBER"          => "BİLET NUMARASI",
            "NEW FLIGHT INFORMATION" => "YENİ UÇUŞ BİLGİSİ",
            "FROM"                   => "KALKIŞ HAVAALANI",
            "DEPARTURE TIME"         => "KALKIŞ SAATİ",
            "DEPARTURE DATE"         => "PLANLANAN UÇUŞ TARİHİ",
            "FLIGHT NO"              => "UÇUŞ KODU",
            "CABIN"                  => "KABİN SINIF BİLGİSİ",
            "CLASS"                  => "SINIF BİLGİSİ",
        ],
    ];

    private $detectFrom = "@thy.com";
    private $detectSubject = [
        "en" => "THY Schedule Change Information",
        "tr" => "THY Tarife Değişikliği Bilgisi",
    ];

    private $detectCompany = ['Turkish Airlines', 'thy.com'];

    private $detectBody = [
        "en" => "Your following flights have schedule changes", "Your following flights have been cancelled",
        "tr" => "Aşağıda belirtilen uçuşlarınızda tarife değişikliği olmuştur",
    ];

    private $detectLang = [
        "en" => "FLIGHT INFORMATION",
        "tr" => "UÇUŞ BİLGİSİ",
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectLang as $lang => $detectBody) {
            if (strpos($body, $detectBody) !== false) {
                $this->lang = $lang;

                break;
            }
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

//        if (strpos($headers["from"], $this->detectFrom)===false)
//            return false;

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains($this->detectCompany, '@href') . "] | //*[" . $this->contains($this->detectCompany, '@href') . "]")->length === 0) {
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

    private function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        // General
        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Your following flights have been"))}]", null, true, "/{$this->opt($this->t("Your following flights have been"))}\s+({$this->opt($this->t("statusVariants"))})(?:\s*[.;!?]|$)/");

        if ($status) {
            $f->general()->status($status);

            if (preg_match("/{$this->opt($this->t("cancelledVariants"))}/i", $status)) {
                // it-60849208.eml
                $f->general()->cancelled();
            }
        }
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("RESERVATION CODE")) . "]/ancestor::tr[1][count(td)=2]/following-sibling::tr[1]/td[2]"), $this->t("RESERVATION CODE"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("NAME AND SURNAME")) . "]/ancestor::tr[1][count(td)=2]/following-sibling::tr[1]/td[1]"))
        ;

        // Issued
        $ticket = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("TICKET NUMBER")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", null, true, "#^\s*(\d{9,})\s*$#");

        if (!empty($ticket)) {
            $f->issued()->ticket($ticket, false);
        }

        // Segments
        $xpath = "//text()[{$this->eq($this->t(
            $f->getCancelled() ? "OLD FLIGHT INFORMATION" : "NEW FLIGHT INFORMATION"
        ))}]/following::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            if ($this->http->XPath->query("tr/descendant::text()[{$this->eq($this->t("DEPARTURE DATE"))}]", $root)->length === 0) {
                $xpath2 = "following::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1]/";
            } else {
                $xpath2 = '';
            }
            // Airline
            $flight = $this->http->FindSingleNode($xpath2 . "tr[ descendant::text()[{$this->eq($this->t("FLIGHT NO"))}] ]/following-sibling::tr[normalize-space()][1]/td[2]", $root);

            if (preg_match('/^(?<name1>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number1>\d+)\s*\/\s*(?<name2>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number2>\d+)$/', $flight, $m)) {
                // TK8305 / AT0910
                $s->airline()
                    ->name($m['name1'])
                    ->number($m['number1'])
                    ->carrierName($m['name2'])
                    ->carrierNumber($m['number2']);
            } elseif (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                // TK8305
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $operator = $this->http->FindSingleNode($xpath2 . "tr[ descendant::text()[{$this->eq($this->t("FLIGHT NO"))}] ]/following-sibling::tr[normalize-space()][2]/td[2]", $root, true, "/{$this->opt($this->t("Operated by"))}\s*(.{2,})/");
            $s->airline()->operator($operator, false, true);

            $date = $this->normalizeDate($this->http->FindSingleNode($xpath2 . "tr[.//text()[" . $this->eq($this->t("DEPARTURE DATE")) . "]]/following-sibling::tr[1]/td[1]", $root));

            // Departure
            $s->departure()
                ->name($this->http->FindSingleNode("tr[.//text()[" . $this->eq($this->t("FROM")) . "]]/following-sibling::tr[1]/td[1]", $root) . ', '
                        . $this->http->FindSingleNode("tr[1]/td[1]", $root))
                ->code($this->http->FindSingleNode("tr[.//text()[" . $this->eq($this->t("FROM")) . "]]/following-sibling::tr[1]/td[1]", $root, true, "#\(([A-Z]{3})\)\s*$#"))
            ;

            $time = $this->http->FindSingleNode("tr[.//text()[" . $this->eq($this->t("DEPARTURE TIME")) . "]]/following-sibling::tr[1]/td[1]", $root);

            if (!empty($time) && !empty($date)) {
                $s->departure()->date(strtotime($time, $date));
            }

            // Arrival
            $s->arrival()
                ->name($this->http->FindSingleNode("tr[.//text()[" . $this->eq($this->t("FROM")) . "]]/following-sibling::tr[1]/td[2]", $root) . ', '
                        . $this->http->FindSingleNode("tr[1]/td[2]", $root))
                ->code($this->http->FindSingleNode("tr[.//text()[" . $this->eq($this->t("FROM")) . "]]/following-sibling::tr[1]/td[2]", $root, true, "#\(([A-Z]{3})\)\s*$#"))
            ;

            $time = $this->http->FindSingleNode("tr[.//text()[" . $this->eq($this->t("DEPARTURE TIME")) . "]]/following-sibling::tr[1]/td[2]", $root);

            if (!empty($time) && !empty($date)) {
                $s->arrival()->date(strtotime($time, $date));
            }

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode($xpath2 . "tr[.//text()[" . $this->eq($this->t("CABIN")) . "]]/following-sibling::tr[1]/td[1]", $root))
                ->bookingCode($this->http->FindSingleNode($xpath2 . "tr[.//text()[" . $this->eq($this->t("CLASS")) . "]]/following-sibling::tr[1]/td[2]", $root, true, "#^\s*([A-Z]{1,2})(?:$|\s+)#"))
            ;
        }
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
        $in = [
            "#^\s*(\d+)\s+([^\d\s]+)\s+(\d{4})[\s,]+[^\d\s]+\s*$#u", //09 May 2019, Thursday
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
