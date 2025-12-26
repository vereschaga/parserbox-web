<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassDark extends \TAccountChecker
{
    public $mailFiles = "iberia/it-12234003.eml, iberia/it-184918190.eml, iberia/it-57208481.eml";

    public $lang = "";
    private $reFrom = "@iberiaexpress.com";
    private $reSubject = [
        "es"=> "Tarjeta de embarque vuelo",
        "en"=> "Boarding pass fligh",
    ];
    private $reBody = 'iberiaexpress.com';
    private $reBody2 = [
        "es" => ["Has completado el proceso de facturación para los siguientes vuelos:"],
        "en" => ["You have completed the billing process for the following flights:"],
    ];

    private static $dictionary = [
        "es" => [
            "Pasajero" => ["Pasajero", "Pasajeros"],
        ],
        "en" => [
            "Pasajero"                          => "Passengers",
            "Fecha"                             => "Date",
            "Salida"                            => "Outbound",
            "Vuelo"                             => "Flight",
            "Terminal"                          => "Terminal",
            "Llegada"                           => "Arrival",
            "Asiento"                           => "Seat",
            "Clase"                             => "Class",
            "Descarga las tarjetas de embarque" => "Download the boarding passes",
        ],
    ];
    private $date = null;

    public function parseHtml(Email $email)
    {
        $flight = $email->add()->flight();

        $travellers = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Pasajero")) . "]/ancestor::table[1]/descendant::text()[normalize-space()][not(" . $this->contains($this->t('Pasajero')) . ")][not(" . $this->contains($this->t('Asiento')) . ")]"));
        $flight->general()
            ->noConfirmation()
            ->travellers($travellers, true);

        $xpath = "//text()[{$this->eq($this->t('Salida'))}]/ancestor::table[./following-sibling::table][1]";
        $nodes = $this->http->XPath->query($xpath);

        $firstDepDate = '';
        $firstDepCode = '';
        $firstFlightNumber = '';

        foreach ($nodes as $root) {
            $date = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::table[1]//text()[" . $this->eq($this->t("Fecha")) . "]/ancestor::td[1]/*[normalize-space(.)][2]", $root));
            $depCode = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Salida")) . "]/ancestor::td[1]/*[normalize-space(.)][2]", $root);
            $flightNumber = $this->http->FindSingleNode("./following-sibling::table[3]//text()[" . $this->eq($this->t("Vuelo")) . "]/ancestor::td[1]/*[normalize-space(.)][2]", $root, true, "#^\D{1,2}\d?(\d{2,4})$#");

            if (empty($flightNumber)) {
                $flightNumber = $this->http->FindSingleNode("./following-sibling::table[3]//text()[" . $this->eq($this->t("Vuelo")) . "]/ancestor::td[1]/*[normalize-space(.)][2]", $root, true, "#^(\d{4})$#u");
            }

            if ($date == $firstDepDate & $depCode == $firstDepCode & $firstFlightNumber == $flightNumber) {
                continue;
            } else {
                $segment = $flight->addSegment();
            }

            $firstDepDate = $date;
            $firstDepCode = $depCode;
            $firstFlightNumber = $flightNumber;

            $airlineName = $this->http->FindSingleNode("./following-sibling::table[3]//text()[" . $this->eq($this->t("Vuelo")) . "]/ancestor::td[1]/*[normalize-space(.)][2]", $root, true, "#^([A-Z]{1,2}\d?)\d{2,4}$#");

            if (!empty($airlineName)) {
                $segment->airline()
                    ->name($airlineName);
            } else {
                $segment->airline()
                    ->noName();
            }

            $segment->airline()
                ->number($flightNumber);

            $segment->departure()
                ->name($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Salida")) . "]/ancestor::td[1]/*[normalize-space(.)][3]", $root))
                ->code($depCode)
                ->date($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Salida")) . "]/ancestor::td[1]/*[normalize-space(.)][4]", $root), $date));

            $depTerminal = $this->http->FindSingleNode("./following-sibling::table[3]//text()[" . $this->eq($this->t("Terminal")) . "]/ancestor::td[1]/*[normalize-space(.)][2]", $root);

            if (!empty($depTerminal)) {
                $segment->departure()
                    ->terminal($depTerminal);
            }

            $segment->arrival()
                ->name($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Llegada")) . "]/ancestor::td[1]/*[normalize-space(.)][3]", $root))
                ->code($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Llegada")) . "]/ancestor::td[1]/*[normalize-space(.)][2]", $root))
                ->date($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Llegada")) . "]/ancestor::td[1]/*[normalize-space(.)][4]", $root), $date));

            $segment->extra()
                ->cabin($this->http->FindSingleNode("./following::table[1]//text()[" . $this->eq($this->t("Clase")) . "]/ancestor::td[1]/*[normalize-space(.)][2]", $root))
                ->seats(array_filter($this->http->FindNodes("./following-sibling::table[2]//text()[" . $this->eq($this->t("Asiento")) . "]/ancestor::table[1]/descendant::td[2]/descendant::text()[normalize-space()]", $root, '/(\d{1,2}[A-Z])/')));

            $bpNodes = $this->http->XPath->query("//img[contains(@src, 'icon-down')]");

            foreach ($bpNodes as $bpRoot) {
                $bp = $email->add()->bpass();
                $bp->setFlightNumber($segment->getFlightNumber());
                $bp->setDepCode($segment->getDepCode());
                $bp->setDepDate($segment->getDepDate());
                $bp->setUrl($this->http->FindSingleNode("./ancestor::a/@href", $bpRoot));
                $bp->setTraveller($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $bpRoot));
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
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $lang => $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->assignLang();

        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $words) {
            foreach ($words as $word) {
                if (strpos($body, $word) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->date = EmailDateHelper::calculateOriginalDate($this, $parser);

        $this->parseHtml($email);
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

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }

        $in = [
            "#^[^\s\d]+ (\d+) ([^\s\d]+) (\d{4})$#", //sábado 04 febrero 2017
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
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
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function assignLang()
    {
        foreach ($this->reBody2 as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
