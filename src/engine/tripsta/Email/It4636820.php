<?php

namespace AwardWallet\Engine\tripsta\Email;

class It4636820 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "tripsta/it-12232752.eml, tripsta/it-1748050.eml, tripsta/it-1842809.eml, tripsta/it-1842810.eml, tripsta/it-1842811.eml, tripsta/it-1842812.eml, tripsta/it-2275161.eml, tripsta/it-2691479.eml, tripsta/it-3000709.eml, tripsta/it-3327767.eml, tripsta/it-3931696.eml, tripsta/it-4636820.eml, tripsta/it-4714198.eml, tripsta/it-4829415.eml, tripsta/it-4829436.eml";

    public $reFrom = "@tripsta";
    public $reSubject = [
        "en"=> "Flight booking confirmation",
        "hu"=> "Repülőjegy foglalás visszaigazolása",
        "ru"=> "Подтверждение бронирования авиабилета",
        "pt"=> "Confirmação da reserva de voo",
        "pl"=> "Potwierdzenie dokonania rezerwacji",
        "de"=> "Flugbuchungsbestätigung",
        "fi"=> "Lennon varauksen varmennus",
    ];
    public $reBody = 'tripsta';
    public $reBody2 = [
        "en"=> "Flight information",
        "hu"=> "Járat információ",
        "ru"=> "Информация полёта",
        "pt"=> "Informação relativa ao voo",
        "pl"=> "Informacje o lotach",
        "de"=> "Fluginformationen",
        "fi"=> "Lennon tiedot",
    ];

    public static $dictionary = [
        "en" => [
            "CONF_NO"   => ["Booking code", "Reservation Number"],
            "PASSENGERS"=> ["Passenger", "Name"],
            "AIRCRAFT"  => ["Equipment name:", "Aircraft:"],
        ],
        "hu" => [
            "CONF_NO"                 => ["Foglalási kód"],
            "PASSENGERS"              => ["Utas"],
            "Total price:"            => "Összesen:",
            "From:"                   => "Honnan:",
            "Airline / Flight number:"=> "Légitársaság / Járat szám:",
            "AIRCRAFT"                => ["Repülőgép típusa:"],
            "Class:"                  => "Osztály:",
            "Flight duration:"        => "Repülés időtartalma:",
        ],
        "ru" => [
            "CONF_NO"                 => ["Код брони"],
            "PASSENGERS"              => ["Name"],
            "Total price:"            => "К оплате:",
            "From:"                   => "Откуда:",
            "Airline / Flight number:"=> "Авиакомпания / Номер рейса:",
            "AIRCRAFT"                => "Тип самолёта:",
            "Class:"                  => "Класс:",
            "Flight duration:"        => "NOTTRANSLATED",
        ],
        "pt" => [
            "CONF_NO"                 => ["Código de reserva"],
            "PASSENGERS"              => ["Nome"],
            "Total price:"            => "Preço total:",
            "From:"                   => "De:",
            "Airline / Flight number:"=> "Companhia aérea / Número do voo:",
            "AIRCRAFT"                => "Tipo de avião:",
            "Class:"                  => "Classe:",
            "Flight duration:"        => "Duração do voo:",
        ],
        "pl" => [
            "CONF_NO"                 => ["Kod rezerwacji"],
            "PASSENGERS"              => ["Pasażer", "Imię i nazwisko"],
            "Total price:"            => "Cena całkowita:",
            "From:"                   => "Wylot z:",
            "Airline / Flight number:"=> "Linia lotnicza / Numer lotu:",
            "AIRCRAFT"                => "Typ samolotu:",
            "Class:"                  => "Klasa:",
            "Flight duration:"        => "Czas trwania lotu:",
        ],
        "de" => [
            "CONF_NO"                 => ["tripsta.de Buchungsnummer", "Buchungsnummer"],
            "PASSENGERS"              => ["Freigepäck"],
            "Total price:"            => "Gesamtpreis:",
            "From:"                   => "Von:",
            "Airline / Flight number:"=> "Fluggesellschaft / Flugnummer:",
            "AIRCRAFT"                => "Flugzeugtyp:",
            "Class:"                  => "Klasse:",
            "Flight duration:"        => "Flugdauer:",
        ],
        "fi" => [
            "CONF_NO"                 => ["Varausnumero"],
            "PASSENGERS"              => ["Matkustaja"],
            "Total price:"            => "Hinta yhteensä:",
            "From:"                   => "-ltä:",
            "Airline / Flight number:"=> "Lentoyhtiö / Lennon numero:",
            "AIRCRAFT"                => "Lentokone:",
            "Class:"                  => "Luokka:",
            "Flight duration:"        => "Lennon kesto:",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['TripNumber'] = $this->nextText($this->t("CONF_NO"));
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        // Passengers
        $PASSENGERS = $this->t("PASSENGERS");
        $rule = implode(" or ", array_map(function ($s) { return "normalize-space(.)='{$s}'"; }, $PASSENGERS));
        $it['Passengers'] = array_map(function ($s) {return preg_replace('/^Mr\. |^Ms\. |^Mrs\. /', '', $s); }, array_unique($this->http->FindNodes("//text()[{$rule}]/ancestor::table[1]/tbody/tr/td[1] |
																//text()[{$rule}]/ancestor::tr[1]/following-sibling::tr/td[1]")));

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->cost($this->nextText($this->t("Total price:")));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t("Total price:")));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[normalize-space(.)='" . $this->t("From:") . "']/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $n = $this->http->XPath->query("./following::table[1]", $root);

            if ($n->length == 0) {
                return;
            }
            $root2 = $n->item(0);

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[2]/td[1]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#\w{2}(?:\)\s+/)?\s+(\d+)$#", $this->nextText($this->t("Airline / Flight number:"), $root2));

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]", $root, true, "#\(([A-Z]{3})\)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#(.*?)\s*\([A-Z]{3}\)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[2]", $root, true, "#\(([A-Z]{3})\)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][last()]", $root, true, "#(.*?)\s*\([A-Z]{3}\)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#(\w{2})(?:\)\s+/)?\s+\d+$#", $this->nextText($this->t("Airline / Flight number:"), $root2));

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $this->nextText($this->t("AIRCRAFT"), $root2);

            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = trim($this->re("#([^\(]+)#", $this->nextText($this->t("Class:"), $root2)));

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#\((\w)\)#", $this->nextText($this->t("Class:"), $root2));

            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment['Duration'] = $this->nextText($this->t("Flight duration:"), $root2);

            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers["from"]) && strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (isset($headers["subject"]) && strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if ($this->http->XPath->query("//*[contains(normalize-space(text()),  '{$re}')]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
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
        if (!is_array($field)) {
            $field = [$field];
        }
        $rule = implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), '{$s}')"; }, $field));

        return $this->http->FindSingleNode("(.//text()[{$rule}])[{$n}]/following::text()[normalize-space(.)][1]", $root);
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
        if ($this->isDigitalDate($str)) {
            $str = $this->normalizeDigitalDate($str);

            return $str;
        }

        $year = date("Y", $this->date);
        $in = [
            "#^(\d{4})\.(\d+)\.(\d+)\.$#",
        ];
        $out = [
            "$3.$2.$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function normalizeDigitalDate($str)
    {
        if (isset($this->dateFail)) {
            return null;
        }

        $str = re("#(\d+/\d+/\d+)#", $str);
        $arr = explode("/", $str);

        foreach ($arr as &$a) {
            $a = str_pad($a, 2, '0', STR_PAD_LEFT);
        }

        // if digital format setted
        if (isset($this->digitalDateFormat) && count($this->digitalDateFormat) == 3) {
            foreach ($this->digitalDateFormat as $key=>$seg) {
                $res[$key] = $arr[$seg];
            }

            // if can match >12
        }

        if ($arr[0] > 12 || $arr[1] > 12) {
            $res['year'] = $arr[2];

            if ($arr[0] > 12) {
                $res['day'] = $arr[0];
                $res['month'] = $arr[1];
                $this->digitalDateFormat['day'] = 0;
                $this->digitalDateFormat['month'] = 1;
                $this->digitalDateFormat['year'] = 2;
            } elseif ($arr[1] > 12) {
                $res['day'] = $arr[1];
                $res['month'] = $arr[0];
                $this->digitalDateFormat['day'] = 1;
                $this->digitalDateFormat['month'] = 0;
                $this->digitalDateFormat['year'] = 2;
            }
            // by next digital date
        } elseif ($this->isDigitalDate($nextDate = $this->http->FindSingleNode("(//text()[contains(., '{$str}')])[1]/following::text()[contains(translate(normalize-space(.), '1234567890', 'dddddddddd'), 'dd/dd/dddd') and not(contains(., '{$str}'))][1]"))) {
            $nextDate = re("#(\d+/\d+/\d+)#", $nextDate);
            $nextDate = explode("/", $nextDate);

            foreach ($nextDate as &$a) {
                $a = str_pad($a, 2, '0', STR_PAD_LEFT);
            }
            $diff = [];

            foreach ($arr as $k=>$a) {
                $diff[abs($nextDate[$k] - $a) . (2 - $k)] = $k;
            }
            krsort($diff, SORT_NUMERIC);

            $keys = ['day', 'month', 'year'];
            $i = 0;

            foreach ($diff as $a) {
                $this->digitalDateFormat[$keys[$i]] = $a;
                $res[$keys[$i]] = $arr[$a];
                $i++;
            }
        }

        if (count($res) == 3) {
            return $res['day'] . '.' . $res['month'] . '.' . $res['year'];
        } else {
            $this->dateFail = true;
        }

        return null;
    }

    private function isDigitalDate($str)
    {
        return preg_match("#\d+/\d+/\d+#", $str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
