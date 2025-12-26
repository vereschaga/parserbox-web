<?php

namespace AwardWallet\Engine\skyair\Email;

use AwardWallet\Engine\MonthTranslate;

class PurchaseReceipt extends \TAccountChecker
{
    public $mailFiles = "skyair/it-11352703.eml, skyair/it-8564486.eml";

    public $reFrom = "@skyairline.c"; //skyairline.com or @skyairline.cl
    public $reProvider = "@skyairline.c";
    public $reSubject = [
        "en" => "Purchase receipt from SKY Airline",
        "es" => "Comprobante de compra web en SKY Airline",
    ];

    public $reBody = 'www.skyairline.c';
    public $reBody2 = [
        "en" => "PURCHASE RECEIPT",
        "es" => "COMPROBANTE DE COMPRA",
    ];
    public static $dictionary = [
        "en" => [
            //			"Your reservation code is" => "",
            //			"Passenger" => "",
            "route" => ["DEPARTURE", "RETURN Information"],
            //			"Departure Time" => "",
            //			"Total Paid:" => "",
        ],
        "es" => [
            "Your reservation code is" => "Su codigo de reserva es",
            "Passenger"                => "Pasajero",
            "route"                    => ["IDA", "REGRESO"],
            "Departure Time"           => "Salida",
            "Total Paid:"              => "Total pagado:",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$its)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(),'" . $this->t("Your reservation code is") . "')]", null, true, "#" . $this->t("Your reservation code is") . ":?\s*([A-Z\d]{5,7})\b#");

        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space()='" . $this->t("Passenger") . "']/ancestor::tr[1]/following-sibling::tr/td[2]");

        // TicketNumbers
        $it['TicketNumbers'] = $this->http->FindNodes("//text()[normalize-space()='" . $this->t("Passenger") . "']/ancestor::tr[1]/following-sibling::tr/td[3]");

        // AccountNumbers
        // Cancelled

        // TotalCharge
        // Currency
        $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("//text()[contains(normalize-space(),'" . $this->t("Total Paid:") . "')]/ancestor::td[1]/following::td[2]"));
        $it['Currency'] = $this->currency($this->http->FindSingleNode("//text()[contains(normalize-space(),'" . $this->t("Total Paid:") . "')]/ancestor::td[1]/following::td[1]"));

        if ($it['TotalCharge'] > 2000 && $it['Currency'] === 'USD') {
            $it['Currency'] = 'CLP';
        }//actually it's always CLP

        // BaseFare
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status

        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[contains(normalize-space(),'" . $this->t("Departure Time") . "')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $seg = [];
            // FlightNumber
            // AirlineName
            $node = $this->http->FindSingleNode(".//tr[not(.//tr)][2]/td[1]", $root);

            if (preg_match("#(Sky|[A-Z]{2})\s*(\d{1,5})#", $node, $m)) {
                $seg['FlightNumber'] = $m[2];

                if ($m[1] == 'Sky') {
                    $seg['AirlineName'] = 'H2';
                } else {
                    $seg['AirlineName'] = $m[1];
                }
            }

            $date = $this->http->FindSingleNode(".//tr[not(.//tr)][2]/td[2]", $root);

            $route = $this->http->FindSingleNode("./preceding::td[1]", $root);

            if (preg_match("#(?:" . $this->preg_implode($this->t('route')) . ")\s+(.+)\s+\w+\s+\d{1,2}\s+#u", $route, $m)) {
                if (preg_match("#(.+) - (.+)#", $m[1], $mat)) {
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    $seg['DepName'] = trim($mat[1]);
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    $seg['ArrName'] = trim($mat[2]);
                } elseif (preg_match("#(.+?)\(([A-Z]{3})\)(.+?)\(([A-Z]{3})\)#", $m[1], $mat)) {
                    $seg['DepCode'] = $mat[2];
                    $seg['DepName'] = trim($mat[1]);
                    $seg['ArrCode'] = $mat[4];
                    $seg['ArrName'] = trim($mat[3]);
                }
            }

            // DepCode
            // DepName
            // DepartureTerminal
            // DepDate
            if (!empty($date)) {
                $seg['DepDate'] = strtotime($this->normalizeDate($date . ' ' . $this->http->FindSingleNode(".//tr[not(.//tr)][2]/td[5]", $root)));
            }
            // ArrCode
            // ArrName
            // ArrivalTerminal
            // ArrDate
            if (!empty($date)) {
                $seg['ArrDate'] = strtotime($this->normalizeDate($date . ' ' . $this->http->FindSingleNode(".//tr[not(.//tr)][2]/td[6]", $root)));
            }
            // Aircraft
            // TraveledMiles
            // Cabin
            // BookingClass
            $seg['BookingClass'] = $this->http->FindSingleNode(".//tr[not(.//tr)][2]/td[3]", $root);

            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops
            // Operator
            // Gate
            // ArrivalGate
            // BaggageClaim

            $it['TripSegments'][] = $seg;
        }

        $its[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reProvider) !== false;
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
        $body = $this->http->Response['body'];

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        $body = $this->http->Response['body'];

        foreach ($this->reBody2 as $lang => $re) {
            if (stripos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $this->parseHtml($its);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $its,
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
        return count(self::$dictionary) * 3;
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
            "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s+(\d+:\d+)\s*$#", //05/06/2015 12:40
        ];
        $out = [
            "$1.$2.$3 $4",
        ];
        $str = preg_replace($in, $out, $str);

        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map(function ($s) { return preg_quote($s); }, $field));
    }

    private function amount($s)
    {
        if (preg_match("#(.+)\.(\d{3})$#", $s, $m)) {
            return $this->normalizePrice(preg_replace("#\D#", '', $m[1]) . '.' . $m[2]);
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

    private function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return (float) $string;
    }
}
