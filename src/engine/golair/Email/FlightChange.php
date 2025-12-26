<?php

namespace AwardWallet\Engine\golair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightChange extends \TAccountChecker
{
    public $mailFiles = "golair/it-116218182.eml, golair/it-116218195.eml, golair/it-128322212.eml, golair/it-165370945.eml, golair/it-165663684.eml, golair/it-165726092.eml";

    private $detectFrom = "comunicacaovoegol@acomodacao.voegol.com.br";
    private $detectSubject = [
        // en
        "GOL Communication: your flight has been changed",
        // pt
        "Aviso GOL: seu voo foi alterado",
    ];
    private $detectBody = [
        "en" => ["Check the updated details of your reservation"],
        "pt" => ["Confira os dados atualizados da sua reserva ", "Caso o voo antigo e o voo novo estejam", "Seu voo foi cancelado por necessidades operacionais", "Seu voo sofreu uma alteração por necessidades operacionais",
            "Mantenha-se atualizado com relação aos seus voos",
            "Confira abaixo as informações atualizadas e considere",
            ", espero que esteja bem!",
        ],
    ];

    private $date;

    private $lang = "en";
    private static $dictionary = [
        "en" => [
            //            "Locator Code:" => "",
            //            "New flight" => "",
            //            "Flight Date" => "",
            //            "Destination" => "",
            //            "operado por"   => "",// to translate
            //            "Passengers" => "",
            //            "Name" => "",
        ],
        "pt" => [
            "Locator Code:" => ["Código de Reserva:", "Localizador:"],
            "New flight"    => ["Voo novo", 'Seu Voo'],
            "Old flight"    => ["Voo antigo"], //For canceled ones only
            "Flight Date"   => "Data",
            "Destination"   => "Destino",
            "operado por"   => "operado por",
            "Passengers"    => ["Passageiros", "Clientes"],
            "Name"          => "Nome",
            "Flight"        => "Voo",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if (preg_match("/^Fwd\:/", $parser->getSubject())) {
            $email->setIsJunk(true);

            return $email;
        } else {
            $this->date = strtotime("-7 day", strtotime($parser->getDate()));
        }

        if (empty($this->date)) {
            return $email;
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
        if ($this->http->XPath->query("//a[contains(@href,'.voegol.com.br')] | //*[contains(.,'.voegol.com.br')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
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
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Locator Code:')) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"));
        // Segment
        $xpath = "//text()[" . $this->eq($this->t("New flight")) . "]/following::tr[not(.//tr)][*[1][" . $this->eq($this->t("Flight Date")) . "]]/ancestor::*[.//tr/*[last()][" . $this->eq($this->t("Destination")) . "]][1]";
        $this->logger->debug('$xpath = ' . print_r($xpath, true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0 && $this->http->XPath->query("//text()[{$this->starts($this->t('Seu voo foi cancelado'))}]")->length > 0) {
            $xpath = "//text()[" . $this->eq($this->t("Old flight")) . "]/following::tr[not(.//tr)][*[1][" . $this->eq($this->t("Flight Date")) . "]]/ancestor::*[.//tr/*[last()][" . $this->eq($this->t("Destination")) . "]][1]";
            $nodes = $this->http->XPath->query($xpath);
            $f->general()
                ->cancelled();
        }

        $paxs = [];

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode(".//tr[not(.//tr)][*[" . $this->eq($this->t("Flight Date")) . "]]/following::tr[1]/*[1]", $root));
            $s->airline()
                ->name($this->http->FindSingleNode(".//tr[not(.//tr)][*[" . $this->eq($this->t("Flight Date")) . "]]/following::tr[1]/*[2]",
                    $root, true, "/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,5}\s*(?:$|{$this->preg_implode($this->t("operado por"))})/"))
                ->number($this->http->FindSingleNode(".//tr[not(.//tr)][*[" . $this->eq($this->t("Flight Date")) . "]]/following::tr[1]/*[2]",
                    $root, true, "/^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,5})\s*(?:$|{$this->preg_implode($this->t("operado por"))})/"))
            ;
            $operator = $this->http->FindSingleNode(".//tr[not(.//tr)][*[" . $this->eq($this->t("Flight Date")) . "]]/following::tr[1]/*[2]",
                $root, true, "/^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,5}\s*(?:$|{$this->preg_implode($this->t("operado por"))})\s*(.+)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            // Departure
            $s->departure()
                ->name($this->http->FindSingleNode(".//tr[not(.//tr)][*[" . $this->eq($this->t("Flight Date")) . "]]/following::tr[1]/*[3]",
                    $root, true, "/(.+)\([A-Z]{3}\)\s*$/"))
                ->code($this->http->FindSingleNode(".//tr[not(.//tr)][*[" . $this->eq($this->t("Flight Date")) . "]]/following::tr[1]/*[3]",
                    $root, true, "/.+\(([A-Z]{3})\)\s*$/"))
            ;
            $time = $this->http->FindSingleNode(".//tr[not(.//tr)][*[" . $this->eq($this->t("Destination")) . "]]/following::tr[1]/*[1]",
                $root, true, "/^\s*(\d{1,2}:\d{2})\s*$/");

            if (!empty($time) && !empty($date)) {
                $s->departure()->date(strtotime($time, $date));

                if ($s->getDepDate() < $this->date) {
                    $s->departure()->date(strtotime('+1 year', $s->getDepDate()));
                }
            }

            // Arrival
            $s->arrival()
                ->name($this->http->FindSingleNode(".//tr[not(.//tr)][*[" . $this->eq($this->t("Destination")) . "]]/following::tr[1]/*[3]",
                    $root, true, "/(.+)\([A-Z]{3}\)\s*$/"))
                ->code($this->http->FindSingleNode(".//tr[not(.//tr)][*[" . $this->eq($this->t("Destination")) . "]]/following::tr[1]/*[3]",
                    $root, true, "/.+\(([A-Z]{3})\)\s*$/"))
            ;
            $time = $this->http->FindSingleNode(".//tr[not(.//tr)][*[" . $this->eq($this->t("Destination")) . "]]/following::tr[1]/*[2]",
                $root, true, "/^\s*(\d{1,2}:\d{2})\s*$/");

            if (!empty($time) && !empty($date)) {
                $s->arrival()->date(strtotime($time, $date));

                if ($s->getArrDate() < $this->date) {
                    $s->arrival()->date(strtotime('+1 year', $s->getArrDate()));
                }
            }

            $pax = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t("Passengers"))}]/ancestor::*/following-sibling::*[normalize-space()][1][count(.//text()[normalize-space()])=2 and ({$this->starts($this->t('Name'))})]/following-sibling::*/descendant::text()[contains(normalize-space(),'" . $s->getAirlineName() . $s->getFlightNumber() . "')]/ancestor::table[1]/preceding::table[1]", null, "/^\s*" . $this->preg_implode($this->t("Name")) . "?\s*([[:alpha:] \-\']+)$/"));

            $paxs = array_merge($paxs, $pax);
        }

        if (empty($paxs)) {
            $paxs = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t("Passengers"))}]/following::tr[not({$this->starts($this->t('Name'))}) and not({$this->eq($this->t("Flight"))})][count(*[normalize-space()]) = 1][1]/ancestor::*[1]/*[normalize-space()][not({$this->starts($this->t('Name'))}) and not({$this->eq($this->t("Passengers"))})]", null, "/^\s*([A-Z][A-Z \-\']+)$/"));
        }

        $f->general()
            ->travellers(preg_replace("/^\s*(MR|MRS|MISS|MISTER|DR|MS)\s+/", '', array_unique($paxs)), true);

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
//        $this->logger->debug('$date = '.print_r( $str,true));
        $year = date("Y", $this->date);
        $in = [
            "#^\s*(\d{2})/(\d{2})\s*$#iu", // 23/12
        ];
        $out = [
            "$1.$2.{$year}",
        ];
        $str = preg_replace($in, $out, $str);

//        if (preg_match("#\s*\d+\s+([^\d\s]+)\s*$#", $str, $m)) {
//            $str = EmailDateHelper::calculateDateRelative($str, $this, $this->parser);
//
//            return $str;
//        }
        $str = strtotime($str);

        if ($str < strtotime("-6 month", $this->date) && $str > strtotime("-12 month", $this->date)) {
            $str = strtotime("+1 year", $str);
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
