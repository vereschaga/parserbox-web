<?php

namespace AwardWallet\Engine\tiger\Email;

class It3943834 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "";

    public $reFrom = "itinerary@tigerair.com";
    public $reSubject = [
        "en"=> "Tigerair - Confirmation of booking",
    ];
    public $reBody = 'Tiger Airways';
    public $reBody2 = [
        "en"=> "manage my booking",
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
        $it['RecordLocator'] = $this->getField("Booking reference");

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter($this->http->FindNodes("//text()[normalize-space(.)='Passenger(s) travelling:']/ancestor::tr[1]/following-sibling::tr", null, "#\d+\.\s+(.+)#"));

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

        $xpath = "//*[normalize-space(text())='Departing']/ancestor::td[contains(., 'Arriving')][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./table[1]//tr[2]/descendant::text()[normalize-space(.)][3]", $root, true, "#\d+$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./table[1]//tr[2]/descendant::text()[normalize-space(.)][2]", $root, true, "#\(([A-Z]{3})\)#");

            // DepName
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./table[1]//tr[2]/descendant::text()[normalize-space(.)][1]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./table[2]//tr[2]/descendant::text()[normalize-space(.)][2]", $root, true, "#\(([A-Z]{3})\)#");

            // ArrName
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./table[2]//tr[2]/descendant::text()[normalize-space(.)][1]", $root)));

            // AirlineName
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

        $result = [
            'emailType'  => 'Flight',
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
        return $this->http->FindSingleNode("//text()[normalize-space(.)='{$field}']/following::text()[normalize-space()][1]");
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
            "#^\w+,\s+(\d+)\s+(\w+)\s+(\d{4})\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
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
