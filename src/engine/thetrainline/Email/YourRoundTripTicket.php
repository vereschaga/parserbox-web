<?php

namespace AwardWallet\Engine\thetrainline\Email;

use AwardWallet\Engine\MonthTranslate;

class YourRoundTripTicket extends \TAccountChecker
{
    public $mailFiles = "thetrainline/it-7086899.eml";
    public $reFrom = "@trainline.eu";
    public $reSubject = [
        "en"  => "Your round trip ticket for",
        "en2" => "Your ticket for",
        "fr"  => "Votre billet aller-retour",
        "nl"  => "Je treinkaartje naar",
        "de"  => "Ihre Tickets",
        "it"  => "I tuoi biglietti",
    ];
    public $reBody = 'Trainline';
    public $reBody2 = [
        "en" => "Receiving your ticket",
        "fr" => "Obtenir votre billet",
        "nl" => "Je treinkaartje ontvangen",
        "de" => "Ihr Ticket",
        "it" => "Il biglietto",
    ];

    public static $dictionary = [
        "en" => [
            "Passenger:"=> ["Passenger:", "Passengers:"],
        ],
        "fr" => [
            "Passenger:"        => ["Passager", "Passagers"],
            "seat"              => "place",
            'Booking reference' => 'Référence :',
        ],
        "nl" => [
            "Passenger:"        => ["Passagier:"],
            "seat"              => "zitplaats",
            'Booking reference' => 'Boekingsreferentie:',
        ],
        "de" => [
            "Passenger:"=> ["Fahrgäste:"],
            //			"seat"=>"",
            'Booking reference' => 'Auftragsnummer:',
        ],
        "it" => [
            "Passenger:"=> ["Passeggeri:"],
            //			"seat"=>"",
            'Booking reference' => 'Riferimento del tuo biglietto:',
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), '" . $this->t("Booking reference") . "')]/following::text()[normalize-space(.)][1]", null, true, "#[A-Z\d]+#");

        // TripNumber
        // Passengers
        $it['Passengers'] = [];

        foreach ($this->http->FindNodes("//text()[" . $this->starts($this->t("Passenger:")) . "]", null, "#" . $this->opt($this->t("Passenger:")) . "\s+(.+)#") as $passgs) {
            $it['Passengers'] = array_merge($it['Passengers'], explode(", ", $passgs));
        }

        $it['Passengers'] = array_map(function ($s) { return trim($s, ': '); }, array_unique($it['Passengers']));
        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        $xpath = "//text()[" . $this->starts($this->t("Passenger:")) . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $head = $this->http->XPath->query("./preceding::tr[1]", $root)->item(0);
            $dateStr = $this->http->FindSingleNode("./td[1]", $head, true, "#-\s+(.+)#");

            if (!empty($dateStr)) {
                $date = strtotime($this->normalizeDate($dateStr));
            }
            $total = $this->http->FindSingleNode("./td[normalize-space()][2]", $head);

            if ($total) {
                $amount = $this->amount($total);
                $it['TotalCharge'] = !empty($it['TotalCharge']) ? $it['TotalCharge'] + $amount : $amount;
                $it['Currency'] = $this->currency($total);
            }

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[1]/td[3]", $root, true, "#\s+(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./tr[1]/td[2]", $root);

            // DepAddress
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeTime($this->http->FindSingleNode("./tr[1]/td[1]", $root)), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[2]/td[2]", $root);

            // ArrAddress
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeTime($this->http->FindSingleNode("./tr[2]/td[1]", $root)), $date);

            // Type
            $itsegment['Type'] = $this->http->FindSingleNode("./tr[1]/td[3]", $root, true, "#^\s*(.+?)\s+\d+\s*$#");

            // Vehicle
            // TraveledMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = array_filter([$this->http->FindSingleNode(".//text()[" . $this->contains($this->t("seat")) . "]", $root)]);

            // Duration
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
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

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);
        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
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
            "#^[^\d\s]+[,]?\s+(\d+)[.]?\s+([^\d\s]+)\s+(\d{4})$#", //Wednesday, 10 May 2017; mardi 07 mars 2017; Donnerstag 18. April 2019
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
        // $this->http->log($str);
        return $str;
    }

    private function normalizeTime($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)h(\d+)$#", //07h43
        ];
        $out = [
            "$1:$2",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->http->log($str);
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
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.\s]+)#", $s);

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
