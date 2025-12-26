<?php

namespace AwardWallet\Engine\kayak\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UpdatedDepartureTime extends \TAccountChecker
{
    public $mailFiles = "kayak/it-10024484.eml, kayak/it-38405042.eml, kayak/it-41973990.eml";
    public static $dictionary = [
        "en" => [
            //            "Delayed Trip Status" => "",
            //            "UPDATED DEPARTURE TIME" => "",
            //            "FLIGHT" => "",
            //            "NUMBER" => "",
            //            "TERMINAL" => "",
        ],
        "de" => [
            "Delayed Trip Status"    => "Tripstatus: Flugverspätung",
            "UPDATED DEPARTURE TIME" => "NEUE ABFLUGZEIT",
            "FLIGHT"                 => "FLUG",
            "NUMBER"                 => "NUMMER",
            "TERMINAL"               => "TERMINAL",
        ],
        "es" => [
            "Delayed Trip Status"    => "Vuelo con retraso",
            "UPDATED DEPARTURE TIME" => "HORARIO DE SALIDA ACTUALIZADO",
            "FLIGHT"                 => "VUELO",
            "NUMBER"                 => "N.º",
            "TERMINAL"               => "TERMINAL",
        ],
        "it" => [
            "Delayed Trip Status"    => "Volo in ritardo",
            "UPDATED DEPARTURE TIME" => "NUOVO ORARIO DI PARTENZA",
            "FLIGHT"                 => "VOLO",
            "NUMBER"                 => "NUMERO",
            "TERMINAL"               => "TERMINAL",
        ],
    ];

    private $reFrom = "noreply-trips@message.kayak.com";
    private $reSubject = [
        "en" => "Updated departure time for",
        "de" => "Neue Abflugzeit von",
        "es" => "Horario de salida actualizado para el vuelo",
        "it" => "Nuovo orario di partenza per",
    ];
    private $reBody = 'KAYAK';
    private $reBody2 = [
        "en" => "UPDATED DEPARTURE TIME",
        "de" => "NEUE ABFLUGZEIT",
        "es" => "HORARIO DE SALIDA ACTUALIZADO",
        "it" => "NUOVO ORARIO DI PARTENZA",
    ];

    private $lang = "en";

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
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
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

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        // Travel Agency
        $email->obtainTravelAgency();

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

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        if ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Delayed Trip Status")) . "]")) {
            $f->general()
                ->noConfirmation()
                ->status("Delayed");
        }

        $date = strtotime($this->normalizeDate($this->nextText($this->t("Delayed Trip Status"))));
        // Segments
        $s = $f->addSegment();

        // FlightNumber
        $s->airline()
            ->name($this->http->FindSingleNode("//text()[" . $this->eq($this->t("NUMBER")) . "]/ancestor::tr[1]/following-sibling::tr[1]", null, true,
                "#^\s*([A-Z\d]{2})[ ]?\d+\s*$#"))
            ->number($this->http->FindSingleNode("//text()[" . $this->eq($this->t("NUMBER")) . "]/ancestor::tr[1]/following-sibling::tr[1]", null, true,
                "#^\s*[A-Z\d]{2}[ ]?(\d+)\s*$#"))
        ;

        // Departure
        $s->departure()
            ->code($this->http->FindSingleNode("//text()[" . $this->eq($this->t("FLIGHT")) . "]/ancestor::tr[1]/following-sibling::tr[1]", null, true,
                "#^([A-Z]{3})-[A-Z]{3}$#"))
            ->terminal(trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t("TERMINAL")) . "]/ancestor::tr[1]/following-sibling::tr[1]"), '-'), true, true)
        ;
        $time = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("UPDATED DEPARTURE TIME")) . "]/ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[normalize-space(.)][2]", null, true,
            "#^\s*(\d+:\d+(?:\s*[ap]m)?)\s*$#i");

        if (!empty($date) && !empty($time)) {
            $s->departure()->date(strtotime($time, $date));
        }

        // Arrival
        $s->arrival()
            ->code($this->http->FindSingleNode("//text()[" . $this->eq($this->t("FLIGHT")) . "]/ancestor::tr[1]/following-sibling::tr[1]", null, true,
                "#^[A-Z]{3}-([A-Z]{3})$$#"))
            ->noDate();

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
        //		$this->logger->debug('normalizeDate $str = '.print_r( $str,true));
        $in = [
            "#^[^\s\d]+ ([^\s\d]+) (\d+) (\d{4})$#", //Wed Nov 22 2017
            "#^[^\s\d]+ (\d+)[.]? ([^\s\d\.]+)[.]? (\d{4})$#", //So. 2. Juni 2019; jue. 4 abr. 2019
        ];
        $out = [
            "$2 $1 $3",
            "$1 $2 $3",
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }
}
