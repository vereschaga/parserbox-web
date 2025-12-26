<?php

namespace AwardWallet\Engine\wizz\Email;

use AwardWallet\Engine\MonthTranslate;

class NotificationOfChange extends \TAccountChecker
{
    public $mailFiles = "wizz/it-10259365.eml, wizz/it-12232058.eml, wizz/it-13855770.eml";
    public $reFrom = "notifications@notifications.wizzair.com";
    public $reSubject = [
        "es"=> "Notificación de cambio en el horario",
        "bg"=> "Съобщение за промяна в разписанието",
        "pt"=> "Schedule change notification",
        "de"=> "Benachrichtigung über Flugplanänderung",
    ];
    public $reBody = 'wizzair.com';
    public $reBody2 = [
        "es"  => "Estimado pasajero",
        "es2" => 'Thank you for your understanding',
        "bg"  => "Уважаеми пътници",
        "pt"  => "Caro passageiro",
        "de"  => "Lieber Fluggast",
    ];

    public static $dictionary = [
        "es" => [
            "sus vuelos bajo código de confirmación" => ["sus vuelos bajo código de confirmación", 'your flight(s) under confirmation code'],
        ],
        "bg" => [
            "sus vuelos bajo código de confirmación" => "Ви под код за потвърждение",
            " producido un cambio de horario"        => "че е въведена промяна на разписанието",
            "Terminal"                               => "Терминал",
        ],
        "pt" => [
            "sus vuelos bajo código de confirmación" => "o código de confirmação",
            " producido un cambio de horario"        => " producido un cambio de horario",
        ],
        "de" => [
            "sus vuelos bajo código de confirmación" => "Buchung mit dem Bestätigungscode",
            " producido un cambio de horario"        => " Flugplanänderung einen Flug",
        ],
    ];

    public $lang = "es";

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

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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

    private function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("sus vuelos bajo código de confirmación")) . "]/following::text()[normalize-space(.)][1]");

        // TripNumber
        // Passengers
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
        if ($this->http->FindSingleNode("//text()[" . $this->contains($this->t(" producido un cambio de horario")) . "]")) {
            $it['Status'] = 'changed';
        }

        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//img[contains(@src, '/arrow-img.jpg')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./preceding-sibling::tr[1]", $root, true, "#^\w{2} (\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]", $root, true, "#\(([A-Z]{3})\)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#(.*?)\s+[-\(]#");

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[1]", $root, true, "#{$this->opt($this->t('Terminal'))} (\w+)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#\(([A-Z]{3})\)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]", $root, true, "#(.*?)\s+[-\(]#");

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[3]", $root, true, "#{$this->opt($this->t('Terminal'))} (\w+)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root)));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./preceding-sibling::tr[1]", $root, true, "#^(\w{2}) \d+$#");

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
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
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+)/([^\s\d]+)/(\d{4}) (\d+:\d+)$#", //18/Nov/2016 20:45
        ];
        $out = [
            "$1 $2 $3, $4",
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

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }
}
