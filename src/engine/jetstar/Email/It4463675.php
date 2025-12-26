<?php

namespace AwardWallet\Engine\jetstar\Email;

class It4463675 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "jetstar/it-4463675.eml";

    public $reFrom = "no-reply@jetstar.com";
    public $reSubject = [
        "en"=> "Check-in confirmation for",
    ];
    public $reBody = 'Jetstar';
    public $reBody2 = [
        "en"=> "Just print the attached PDF",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText("Reservation Number:", $this->http);

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->nextText("Name:")];

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

        $date = strtotime($this->normalizeDate($this->nextText($this->nextText("Flight:"))));

        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->re("#^\w{2}(\d+)$#", $this->nextText("Flight:"));

        // DepCode
        $itsegment['DepCode'] = $this->pdf->FindSingleNode("//text()[contains(., 'From:')]", null, true, "#\s+([A-Z]{3})$#");

        // DepName
        // DepDate
        $itsegment['DepDate'] = strtotime($this->nextText("Depart:"), $date);

        // ArrCode
        $itsegment['ArrCode'] = $this->pdf->FindSingleNode("//text()[contains(., 'To:')]", null, true, "#\s+([A-Z]{3})$#");

        // ArrName
        // ArrDate
        $itsegment['ArrDate'] = strtotime($this->nextText("Arrive:"), $date);

        // AirlineName
        $itsegment['AirlineName'] = $this->re("#^(\w{2})\d+$#", $this->nextText("Flight:"));

        // Operator
        // Aircraft
        // TraveledMiles
        // Cabin
        // BookingClass
        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = $this->nextText("Seat:");

        // Duration
        // Meal
        // Smoking
        // Stops
        $it['TripSegments'][] = $itsegment;

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
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        $this->pdf = clone $this->http;
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return null;
        }
        $this->pdf->SetBody($html);

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

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $source = false)
    {
        if ($source === false) {
            $source = $this->pdf;
        }

        return $source->FindSingleNode("(//text()[starts-with(normalize-space(.), '{$field}')])[1]/following::text()[normalize-space(.)][1]");
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
            "#^(\d+)([^\d\s]+)(\d{2})$#",
        ];
        $out = [
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
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
