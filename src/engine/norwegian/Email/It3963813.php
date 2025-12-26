<?php

namespace AwardWallet\Engine\norwegian\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3963813 extends \TAccountChecker
{
    public $mailFiles = "norwegian/it-10993433.eml, norwegian/it-3963813.eml, norwegian/it-4391425.eml, norwegian/it-4433071.eml, norwegian/it-4447325.eml, norwegian/it-4449124.eml, norwegian/it-4777035.eml, norwegian/it-692328663.eml";

    public $reFrom = "noreply@fly.norwegian.com";
    public $reSubject = [
        "en" => "Travel information",
        "es" => "Informacion sobre tu viaje",
        "da" => "Rejseinformation",
        "no" => "Reiseinformasjon",
        "fi" => "Tietoa matkasta",
    ];
    public $reBody = 'Norwegian Air';
    public $reBody2 = [
        "en"  => "Thank you for choosing us",
        "en2" => "Thank you for travelling with",
        "es"  => "Gracias por elegir Norwegian",
        "da"  => "Din næste rejse",
        "no"  => "Din neste reise",
        "fi"  => "Kiitos että lennät kanssamme",
        'sv'  => 'Tack för att du valde oss',
    ];

    public static $dictionary = [
        "en" => [
            "Booking Reference:" => ["Booking Reference:", "Booking reference:"],
        ],
        "es" => [
            "Booking Reference:" => "Referencia de la Reserva:",
            "Passengers"         => "Pasajeros",
            "Outbound Flight"    => "Vuelo de ida",
            "Return flight"      => "Vuelo de vuelta",
            "Departure from"     => "Salida desde",
            //			"Transit stop in" => "",
        ],
        "da"=> [
            "Booking Reference:" => "Referencenummer:",
            "Passengers"         => "Passagerer",
            "Outbound Flight"    => "Udrejse",
            "Return flight"      => "Hjemrejse",
            "Departure from"     => "Afgang fra",
            "Transit stop in"    => "Mellemlanding i",
        ],
        "no"=> [
            "Booking Reference:" => "Referansenummer:",
            "Passengers"         => "Passasjerer",
            "Outbound Flight"    => "Utreise",
            "Return flight"      => "Retur",
            "Departure from"     => "Avreise fra",
            "Transit stop in"    => "Transfer",
        ],
        "fi"=> [
            "Booking Reference:" => "Varausnumero:",
            "Passengers"         => "Matkustajat",
            "Outbound Flight"    => "Lähtevä lento",
            "Return flight"      => "Paluulento",
            "Departure from"     => "Lähtöpaikka",
            //			"Transit stop in" => "",
        ],
        "sv"=> [
            "Booking Reference:" => "Bokningsreferens:",
            "Passengers"         => "Passagerare",
            "Outbound Flight"    => "Utresa",
            "Return flight"      => "Ankomst",
            "Departure from"     => "Avresa från",
            //			"Transit stop in" => "",
        ],
    ];

    public $lang = "en";

    private $date;

    public function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Reference:'))}]", null, true, "#{$this->opt($this->t("Booking Reference:"))}\s*(\w+)#"));

        $cnt1 = $this->http->XPath->query("//text()[normalize-space(.)='Passengers']/ancestor::tr[1]/preceding-sibling::tr")->length;
        $cnt2 = $this->http->XPath->query("//tr[contains(., 'View booking') or contains(., 'View reservation') and not(.//tr)]/ancestor::tr[preceding-sibling::tr][1]/preceding-sibling::tr")->length;

        if ($cnt1 > 0 && $cnt2 > 0) {
            $cnt = $cnt2 - $cnt1;
            $passengers = array_filter(array_unique($this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Passengers") . "']/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.)) > 2][position() < {$cnt}][not(contains(., 'View booking')) and not(contains(., 'View reservation'))]/descendant::text()")));
        }

        if (empty($passengers)) {
            $passengers = array_filter($this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Passengers") . "']/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.)) > 2]"));
        }

        if (count($passengers) > 0) {
            $f->general()
                ->travellers($passengers);
        }

        $xpath = "//text()[normalize-space(.)='" . $this->t("Departure from") . "' or normalize-space(.)='" . $this->t("Transit stop in") . "']/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->logger->info("segments root not found: $xpath");
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            // 17 lip 2017 - pl lang, but for detect body are no normal string
            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[contains(., '" . $this->t("Outbound Flight") . "') or contains(., '" . $this->t("Return flight") . "')][1]/following-sibling::tr[1]", $root));

            $s->airline()
                ->noName()
                ->noNumber();

            if (!empty($date)) {
                $depDate = $date . ' ' . $this->http->FindSingleNode("./following-sibling::tr[string-length(normalize-space(.)) > 2][2]", $root, true, "#\d+:\d+$#");
                $arrDate = $date . ' ' . $this->http->FindSingleNode("./following-sibling::tr[string-length(normalize-space(.)) > 2][5]", $root, true, "#^\d+:\d+#");
            }

            if (empty($depDate) && empty($arrDate)) {
                $depDate = $this->http->FindSingleNode("./following-sibling::tr[string-length(normalize-space(.)) > 2][2]", $root, true, "#\d{1,2}/\d{1,2}/\d{2,4}\s+\d+:\d+$#");
                $arrDate = $this->http->FindSingleNode("./following-sibling::tr[string-length(normalize-space(.)) > 2][5]", $root, true, "#\d{1,2}/\d{1,2}/\d{2,4}\s+\d+:\d+$#");

                if ($this->http->XPath->query("self::tr[contains(., 'Transit stop in')]", $root)->length > 0) {
                    $depDate = $this->http->FindSingleNode("./following-sibling::tr[string-length(normalize-space(.)) > 2][3]", $root, true, "#\d{1,2}/\d{1,2}/\d{2,4}\s+\d+:\d+$#");
                    $arrDate = $this->http->FindSingleNode("./following-sibling::tr[string-length(normalize-space(.)) > 2][6]", $root, true, "#\d{1,2}/\d{1,2}/\d{2,4}\s+\d+:\d+$#");
                }
            }

            $s->departure()
                ->date(strtotime($depDate))
                ->code($this->http->FindSingleNode("./following-sibling::tr[string-length(normalize-space(.)) > 2][1]", $root, true, "#\(([A-Z]{3})#"));

            $arrCode = $this->http->FindSingleNode("./following-sibling::tr[string-length(normalize-space(.)) > 2][4]", $root, true, "#\(([A-Z]{3})#");

            if ($this->http->XPath->query("self::tr[contains(., 'Transit stop in')]", $root)->length > 0) {
                $arrCode = $this->http->FindSingleNode("./following-sibling::tr[string-length(normalize-space(.)) > 2][5]", $root, true, "#\(([A-Z]{3})#");

                if (empty($arrCode)) {
                    $arrCode = $this->http->FindSingleNode("./following-sibling::tr[string-length(normalize-space(.)) > 2][4]", $root, true, "#\(([A-Z]{3})#");
                }
            }

            $s->arrival()
                ->date(strtotime($arrDate))
                ->code($arrCode);
        }
    }

    public function parseHtml2(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Reference:'))}]/ancestor::tr[1]", null, true, "#{$this->opt($this->t("Booking Reference:"))}\s*(\w+)#"));

        $travellers = $this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Passengers") . "']/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.)) > 2]");

        if (count($travellers) > 0) {
            $f->general()
                ->travellers($travellers);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Flight info']");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flightInfo = $this->http->FindSingleNode("./following::tr[string-length()>3][1]/descendant::td[string-length()>3][1]", $root);
            $date = '';

            if (preg_match("/^(?<aN>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fN>\d{1,4})\-(?<date>\d+\s+\w+\s*\d{4})$/", $flightInfo, $m)) {
                $s->airline()
                    ->name($m['aN'])
                    ->number($m['fN']);

                $date = $m['date'];
            }

            $pointInfo = implode("\n", $this->http->FindNodes("./following::tr[string-length()>3][1]/descendant::td[string-length()>3][2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<depTime>\d+\:\d+)\s+(?<depName>.+)\n(?<arrTime>\d+\:\d+)\s+(?<arrName>.+)$/", $pointInfo, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m['depName'])
                    ->date(strtotime($date . ', ' . $m['depTime']));

                $s->arrival()
                    ->noCode()
                    ->name($m['arrName'])
                    ->date(strtotime($date . ', ' . $m['arrTime']));
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false && strpos($body, ".norwegian.com") === false) {
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

        $this->http->FilterHTML = false;

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        if ($this->http->XPath->query("//text()[normalize-space()='Flight info']")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='From/To']")->length > 0) {
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        // TODO - it-4777035.eml
        $str = str_replace([' ta1i '], [' tammi '], $str);

        $in = [
            "#^\w+\s+-\s+\w+,\s+(\w+)\s+(\d+)$#",
            "#^\w+,\s+(\w+)\s+(\d+)\s+(\d+:\d+\s+[AP]M)$#",
            "#([^\d\s]+)\d+([^\d\s]+)#",
        ];
        $out = [
            "$2 $1 $year",
            "$2 $1 $year, $3",
            "$1$2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if (($en = MonthTranslate::translate($m[1], $this->lang)) || ($en = MonthTranslate::translate($m[1], 'pl'))) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
