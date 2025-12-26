<?php

namespace AwardWallet\Engine\kayak\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightOnTime extends \TAccountChecker
{
    public $mailFiles = "kayak/it-10054670.eml, kayak/it-37772902.eml, kayak/it-38801645.eml, kayak/it-38812034.eml, kayak/it-41974819.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            //            "Trip Status" => "",
            "Flight " => ["Flight ", "Flight:"],
            //            "Operated by" => "",
            //            "Terminal:" => "",
            //            "Departs " => "",
            //            "Arrives " => "",
        ],
        "de" => [
            "Trip Status" => "Tripstatus",
            "Flight "     => ["Flug ", "Flug:"],
            //            "Operated by" => "",
            //            "Terminal:" => "",
            "Departs " => "Abflug ",
            "Arrives " => "Ankunft ",
        ],
        "ru" => [
            "Trip Status" => "Статус поездки",
            "Flight "     => ["Рейс ", "Рейс:"],
            //            "Operated by" => "",
            "Terminal:" => "Терминал:",
            "Departs "  => "Вылет ",
            "Arrives "  => "Прилет ",
        ],
        "fr" => [
            "Trip Status" => "Statut de votre voyage",
            "Flight "     => ["Vol :"],
            "Operated by" => "Exploité par",
            "Terminal:"   => "Terminal ",
            "Departs "    => "Départ ",
            "Arrives "    => "Arrivée ",
        ],
        "es" => [
            "Trip Status" => "Actualizaciones de viaje",
            "Flight "     => ["Vuelo:"],
            //            "Operated by" => "",
            "Terminal:" => "Terminal",
            "Departs "  => "Salida ",
            "Arrives "  => "Llega ",
        ],
        "pl" => [
            "Trip Status" => "Status podróży",
            "Flight "     => ["Lot:"],
            "Operated by" => "Obsługuje",
            //            "Terminal:" => "Terminal",
            "Departs "  => "Wylot ",
            "Arrives "  => "Przylot ",
        ],
    ];

    private $reFrom = "noreply-trips@message.kayak.com";
    private $reSubject = [
        "en" => "on time",
        "de" => "pünktlich",
        "ru" => "по расписанию",
        "fr" => "- à l’heure",
        "es" => ": en hora",
        "pl" => " - planow",
    ];
    private $reBody = 'KAYAK';
    private $reBody2 = [
        "en" => "Trip Status",
        "de" => "Tripstatus",
        "ru" => "Статус поездки",
        "fr" => "Statut de votre voyage",
        "es" => "Actualizaciones de viaje",
        "pl" => "Status podróży",
    ];

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
        if ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Trip Status")) . "]")) {
            $f->general()->noConfirmation();
        }

        // Segments

        $s = $f->addSegment();

        // Airline
        $s->airline()
            ->name($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Flight ")) . "]", null, true, "#^\s*" . $this->preg_implode($this->t("Flight ")) . "\s*(\w{2})\s*\d+$#u"))
            ->number($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Flight ")) . "]", null, true, "#\b" . $this->preg_implode($this->t("Flight ")) . "\s*\w{2}\s*(\d+)\b#u"))
        ;
        $operator = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Operated by")) . "]");

        if (preg_match("#.+ ([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]?(\d{1,5})\s*$#", $operator, $m)) {
            $s->airline()
                ->carrierName($m[1])
                ->carrierNumber($m[2]);
        } elseif (preg_match("#" . $this->preg_implode($this->t("Operated by")) . "\s+(\S.+)#", $operator, $m)) {
            $s->airline()->operator($m[1]);
        }

        // Departure
        $s->departure()
            ->code($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Departs ")) . "]", null, true, "#" . $this->preg_implode($this->t("Departs ")) . "([A-Z]{3})\s+#u"))
            ->terminal($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Terminal:")) . "]", null, true, "#" . $this->preg_implode($this->t("Terminal:")) . "\s*(\w+)#u"), true, true)
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Departs ")) . "]", null, true, "#" . $this->preg_implode($this->t("Departs ")) . "[A-Z]{3}\s+(.+)#u")))
        ;

        // Arrival
        $s->arrival()
            ->code($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Arrives ")) . "]", null, true, "#" . $this->preg_implode($this->t("Arrives ")) . "([A-Z]{3})\s+#u"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Arrives ")) . "]", null, true, "#" . $this->preg_implode($this->t("Arrives ")) . "[A-Z]{3}\s+(.+)#u")))
        ;

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
            "#^(?:.+ )?[^\s\d]+ ([^\s\d]+) (\d+) (\d{4}) (\d+:\d+(?: [aApP]\.?[mM])?)\.? [A-Z]+\s*$#u", //Wed Nov 22 2017 8:25 am MST
            '#^(?:.+ )?[^\s\d]+ (\d{1,2})[.]? (\w+)[\.]? (\d{4}) (\d{1,2}:\d{2}(?: [aApP]\.?[mM])?)\.? [A-Z]+\s*$#u', // (Montreal) Fri. 23 Nov. 2018 15:30 EST
        ];
        $out = [
            "$2 $1 $3, $4",
            '$1 $2 $3, $4',
        ];
        $str = preg_replace($in, $out, $str);
        $str = str_replace('.', '', $str);

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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
