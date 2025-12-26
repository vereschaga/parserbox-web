<?php

namespace AwardWallet\Engine\ryanair\Email;

class PlainReceipt extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "ryanair/it-4051000.eml, ryanair/it-4051005.eml, ryanair/it-4066599.eml, ryanair/it-4084917.eml, ryanair/it-8132664.eml";

    public $reFrom = "administration@receipt.ryanair.com";
    public $reSubject = [
        "en"=> "Ryanair Receipt",
    ];
    public $reBody = 'Ryanair';
    public $reBody2 = [
        "en"=> "RECEIPT NO.",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $text = str_replace("\n>", "\n", $this->http->Response["body"]);

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->getField("Booking Ref");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->re("#Co\.Reg\.No\s+\d+\s+([^\n]+)#msi", $text);

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->cost($this->getField("Total Price"));

        // BaseFare
        $it['BaseFare'] = $this->cost($this->getField("Bilhetes:"));

        // Currency
        $it['Currency'] = $this->currency($this->getField("Total Price"));

        // Tax
        $it['Tax'] = $this->cost($this->getField("Taxas:"));

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        preg_match_all("#\n\s*(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s+(?<DepName>.*?)\s+to\s+(?<ArrName>.*?)\s+(?<Date>\d+[/-]\d+[/-]\d{4}|\d+[^\d\s]+\d{4})\s+(?<DepHours>\d{1,2})(?<DepMins>\d{2})\s+(?<ArrHours>\d{1,2})(?<ArrMins>\d{2})#", $text, $flights, PREG_SET_ORDER);

        foreach ($flights as $flight) {
            $date = strtotime($this->normalizeDate($flight['Date']));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $flight['FlightNumber'];

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $flight['DepName'];

            // DepDate
            $itsegment['DepDate'] = strtotime($flight['DepHours'] . ':' . $flight['DepMins'], $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $flight['ArrName'];

            // ArrDate
            $itsegment['ArrDate'] = strtotime($flight['ArrHours'] . ':' . $flight['ArrMins'], $date);

            // AirlineName
            $itsegment['AirlineName'] = $flight['AirlineName'];

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

        $this->http->SetEmailBody($parser->getPlainBody());

        $this->http->FilterHTML = false;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
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

    private function getField($field)
    {
        return $this->re("#{$field}\s*\(.*?\)\s*:?\s*([^\n]+)#", $this->http->Response["body"]);
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
            "#^(\d+)[/-](\d+)[/-](\d{4})$#",
            "#^(\d+)([^\d\s]+)(\d{4})$#",
        ];
        $out = [
            "$2/$1/$3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]+#", $str)) {
            $str = $this->dateStringToEnglish($str);
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
}
