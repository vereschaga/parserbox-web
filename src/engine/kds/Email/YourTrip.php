<?php

namespace AwardWallet\Engine\kds\Email;

use AwardWallet\Engine\MonthTranslate;

class YourTrip extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "@kds.com";
    public $reSubject = [
        "fr"=> "La réservation de votre demande pour",
    ];
    public $reBody = 'mykds.com';
    public $reBody2 = [
        "fr"=> "Départ",
    ];

    public static $dictionary = [
        "fr" => [],
    ];

    public $lang = "fr";

    public function parsePlain(&$itineraries)
    {
        $text = $this->http->Response['body'];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        // Passengers
        preg_match_all("#\n\s*([^\n]+)\s*\n\s*Ventilation#", $text, $Passengers);
        $it['Passengers'] = array_unique($Passengers[1]);

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

        $segments = $this->split("#(Départ\s*:\s*.*?\s+\d+/\d+/\d{4}\s+\d+:\d+)#", $text);
        $test = preg_match_all("#Vol\s*:#", $text, $m, PREG_SET_ORDER);

        if (count($segments) != $test) {
            $this->http->Log("missing count segments", LOG_LEVEL_NORMAL);

            return;
        }

        foreach ($segments as $stext) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#Vol\s*:\s*.*?\s+\w{2}(\d+)\s#ms", $stext);

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->re("#Départ\s*:\s*(.*?)\s+\d+/\d+/\d{4}\s+\d+:\d+#", $stext);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#Départ\s*:\s*.*?\s+(\d+/\d+/\d{4}\s+\d+:\d+)#", $stext)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->re("#Arrivée\s*:\s*(.*?)\s+\d+/\d+/\d{4}\s+\d+:\d+#", $stext);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->re("#Arrivée\s*:\s*.*?\s+(\d+/\d+/\d{4}\s+\d+:\d+)#", $stext)));

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#Vol\s*:\s*.*?\s+(\w{2})\d+\s#ms", $stext);

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#Classe\s*:\s*(.+)#", $stext);

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

        //#################
        //##   HOTELS   ###
        //#################

        $segments = $this->split("#(Nom de l.hôtel)#", $text);

        foreach ($segments as $stext) {
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->re("#Numéro de confirmation\s*:\s*(.+)#", $stext) ? $this->re("#Numéro de confirmation\s*:\s*(.+)#", $stext) : CONFNO_UNKNOWN;

            // TripNumber
            // ConfirmationNumbers

            // Hotel Name
            $it['HotelName'] = $this->re("#Nom de l.hôtel\s*:\s*(.+)#", $stext);

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Arrivée\s*:\s*(\d+/\d+/\d{4})#", $stext)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Départ\s*:\s*(\d+/\d+/\d{4})#", $stext)));

            // Address
            $it['Address'] = $this->re("#Adresse\s*:\s*(.+)#", $stext);

            // DetailedAddress

            // Phone
            // Fax
            // GuestNames
            // Guests
            // Kids
            // Rooms
            // Rate
            // RateType
            // CancellationPolicy
            // RoomType
            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            // Currency
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        //###############
        //##   CARS   ###
        //###############

        $segments = $this->split("#(Nom du loueur)#", $text);

        foreach ($segments as $stext) {
            $it = [];

            $it['Kind'] = "L";

            // Number
            $it['Number'] = $this->re("#Numéro de confirmation\s*:\s*(.+)#", $stext) ? $this->re("#Numéro de confirmation\s*:\s*(.+)#", $stext) : CONFNO_UNKNOWN;

            // TripNumber
            // PickupDatetime
            $it['PickupDatetime'] = strtotime($this->normalizeDate($this->re("#Date de prise\s*:\s*(\d+/\d+/\d{4})#", $stext)));

            // PickupLocation
            $it['PickupLocation'] = $this->re("#Prise du véhicule\s*:\s*(.+)#", $stext);

            // DropoffDatetime
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->re("#Date de retour\s*:\s*(\d+/\d+/\d{4})#", $stext)));

            // DropoffLocation
            $it['DropoffLocation'] = $this->re("#Restitution du véhicule\s*:\s*(.+)#", $stext);

            // PickupPhone
            // PickupFax
            // PickupHours
            // DropoffPhone
            // DropoffHours
            // DropoffFax
            // RentalCompany
            $it['RentalCompany'] = $this->re("#Nom du loueur\s*:\s*(.+)#", $stext);

            // CarType
            $it['CarType'] = $this->re("#Type\s*:\s*(.+)#", $stext);

            // CarModel
            // CarImageUrl
            // RenterName
            // PromoCode
            // TotalCharge
            // Currency
            // TotalTaxAmount
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // ServiceLevel
            // Cancelled
            // PricedEquips
            // Discount
            // Discounts
            // Fees
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

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

        $this->http->setBody($parser->getPlainBody());

        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)/(\d+)/(\d{4})\s+(\d+:\d+)$#", //31/05/2017 23:20
            "#^(\d+)/(\d+)/(\d{4})$#", //31/05/2017
        ];
        $out = [
            "$1.$2.$3, $4",
            "$1.$2.$3",
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
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

        foreach ($sym as $f=>$r) {
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
