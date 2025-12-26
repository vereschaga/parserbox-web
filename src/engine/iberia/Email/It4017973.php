<?php

namespace AwardWallet\Engine\iberia\Email;

class It4017973 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "iberia/it-4004063.eml, iberia/it-4017973.eml, iberia/it-6720360.eml";

    public $reFrom = "@iberia.com";
    public $reSubject = [
        "es"    => ["Cancelación de reserva"],
        "esRL"  => ["Iberia Tarjeta de Embarque Móvil", "#Móvil\s+([A-Z\d]+)#"], //RL - sign that contains PNR in subject, "..RL"=>[Subject,RegExp]
        "esRL2" => ["Tarjeta de Embarque Iberia", "#Iberia\s+([A-Z\d]+)#"],
    ];
    public $reBody = 'Iberia';
    public $Subject;
    public $reBody2 = [
        "es" => "Vuelos:",
    ];

    public static $dictionary = [
        "es" => [],
    ];

    public $lang = "es";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(., 'Código de confirmación de la reserva')]", null, true, "#Código de confirmación de la reserva\s+(\w+)#");

        if (!$it['RecordLocator']) {//try get it from Subject
            foreach ($this->reSubject as $key => $value) {
                if (stripos($key, 'RL') !== false && stripos($this->Subject, $value[0]) !== false
                    && isset($value[1]) && preg_match($value[1], $this->Subject, $m)
                ) {
                    $it['RecordLocator'] = $m[1];

                    break;
                }
            }
        }

        if (!$it['RecordLocator'] && $this->http->FindSingleNode("(//text()[contains(., 'Número de vuelo:')])[1]")) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter(array_map(function ($s) {
            return preg_replace("#^\d+\.?\s+#", "", $s);
        }, $this->http->FindNodes("//text()[normalize-space(.)='Pasajeros:' or normalize-space(.)='PASAJEROS:']/ancestor::tr[1]/following-sibling::tr[count(descendant::tr)=0 and not(contains(.,'Vuelos') or contains(.,'Iberia')) and normalize-space(.) and not(.//a)]")));

        // AccountNumbers
        // Cancelled
        // Status
        if ($this->http->FindSingleNode("//text()[contains(., 'Cancelación de su reserva')]")) {
            $it['Cancelled'] = true;
            $it['Status'] = 'cancelled';
        }

        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards

        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[normalize-space(.)='Vuelos:']/ancestor::tr[1]/following-sibling::tr[contains(., 'De:')]//tr[not(.//tr)]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[contains(., 'Fecha:')]", $root, true, "#Fecha:\s*(.+)#")));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[contains(., 'Número de vuelo:')]", $root, true, "#Número de vuelo:\s*\w{2}(\d+)#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode(".//text()[contains(., 'De:')]", $root, true, "#De:\s*(.+)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode(".//text()[contains(., 'Salida:')]", $root, true, "#Salida:\s*(.+)#"), $date);

            if (!$itsegment['DepDate']) {
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[contains(., 'Salida:')]", $root, true, "#Salida:\s*(.+)#")), $date);
            }

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode(".//text()[contains(., 'A:')]", $root, true, "#A:\s*(.+)#");

            // ArrDate
            $arrTime = $this->http->FindSingleNode(".//text()[contains(., 'Llegada:')]", $root, true, "#Llegada:\s*(.+)#");

            if (preg_match("#^\s*(\d+:\d+(?:\s*[ap]m)?)\s*([\+\-]\d+)\s*$#i", $arrTime, $m)) {
                $itsegment['ArrDate'] = strtotime($m[1], strtotime($m[2] . " days", $date));
            } else {
                $itsegment['ArrDate'] = strtotime($arrTime, $date);

                if (!$itsegment['ArrDate']) {
                    $itsegment['ArrDate'] = strtotime($this->normalizeDate($arrTime), $date);
                }
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode(".//text()[contains(., 'Número de vuelo:')]", $root, true, "#Número de vuelo:\s*(\w{2})\d+#");

            // Operator
            // Aircraft
            // TraveledMiles
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
            if (strpos($headers["subject"], $re[0]) !== false) {
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
        $this->Subject = $parser->getSubject();

        $this->http->FilterHTML = false;
        $itineraries = [];

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'CancellationFlight',
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

    private function getField($field)
    {
        return $this->http->FindSingleNode("//td[not(.//td) and normalize-space(.)='{$field}']/following-sibling::td[1]");
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
        $in = [
            "#^[^\s\d]+,\s+(\d+)\s+([^\s\d]+)\s+(\d{4})$#",
            "#^[^\s\d]+\s+(\d+)\s+([^\d\s]+)\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $year, $3",
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $str));

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
}
