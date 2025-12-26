<?php

namespace AwardWallet\Engine\hawaiian\Email;

class It3919765 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $reFrom = "hawaiian"; // ?
    public $reSubject = [
        "en"=> "Reservation Price Confirmation",
    ];
    public $reBody = 'Hawaiian';
    public $reBody2 = [
        "en"=> "Thank you for booking with us",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        //##################
        //##   FLIGHTS   ###
        //##################

        if (count($this->http->FindNodes("//*[normalize-space(text())='Depart']/ancestor::tr[1]/..")) > 0) {
            $seats = [];
            $seatrows = $this->http->FindNodes("//*[normalize-space(text())='Traveler information']/ancestor::tr[1]/following-sibling::tr//*[normalize-space(text())='Seats requests are not guaranteed']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]");

            foreach ($seatrows as $row) {
                $seats[$this->re("#(\d+)\s*:\s*\d{2}\w#", $row)][] = $this->re("#\d+\s*:\s*(\d{2}\w)#", $row);
            }

            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $this->re("#(?:^|\s)(\w{6})$#", $this->getField("Flight:"));

            // TripNumber
            // Passengers
            $it['Passengers'] = $this->http->FindNodes("//*[normalize-space(text())='Traveler information']/ancestor::tr[1]/following-sibling::tr[./td[2]]/descendant::text()[string-length(normalize-space(.))>1][1]");

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

            $xpath = "//*[normalize-space(text())='Depart']/ancestor::tr[1]/..";
            $nodes = $this->http->XPath->query($xpath);

            if ($nodes->length == 0) {
                $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
            }

            foreach ($nodes as $root) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]", $root)));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[4]/td/span", $root, true, "#\d+$#");

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./tr[7]/td[2]", $root, true, "#\(([A-Z]{3})#");

                // DepName
                // DepDate
                $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./tr[7]/td[1]", $root), $date);

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./tr[10]/td[2]", $root, true, "#\(([A-Z]{3})#");

                // ArrName
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./tr[10]/td[1]", $root), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./tr[4]/td/span", $root, true, "#(.*?)\s+\d+$#");

                // Operator
                $itsegment['Operator'] = $this->http->FindSingleNode("./tr[4]/td", $root, true, "#Operated by\s+(.+)#");

                // Aircraft
                // TraveledMiles
                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode("./tr[last()]", $root, null, "#(\w+)\s+\|#");

                // BookingClass
                // PendingUpgradeTo
                // Seats
                if (isset($seats[$itsegment['FlightNumber']])) {
                    $itsegment['Seats'] = implode(',', $seats[$itsegment['FlightNumber']]);
                }

                // Duration
                $itsegment['Duration'] = $this->http->FindSingleNode("./tr[last()]", $root, null, "#Flight Time (\d+hr\s+\d+min)#");

                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }

        //##############
        //##   Car   ###
        //##############
        $xpath = "//*[normalize-space(text())='Pick-up']/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "L";

            // Number
            $it['Number'] = $this->getField("Car:");
            // TripNumber
            // PickupDatetime
            $it['PickupDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]/td[1]", $root)));

            // PickupLocation
            $it['PickupLocation'] = $this->http->FindSingleNode("./tr[2]/td[2]", $root);

            // DropoffDatetime
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[5]/td[1]", $root)));

            // DropoffLocation
            $it['DropoffLocation'] = $this->http->FindSingleNode("./tr[5]/td[2]", $root);

            // PickupPhone
            // PickupFax
            // PickupHours
            // DropoffPhone
            // DropoffHours
            // DropoffFax
            // RentalCompany
            // CarType
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
                'TotalCharge' => [
                    "Amount"   => $this->cost($this->getField("Total Price:")),
                    "Currency" => $this->currency($this->getField("Total Price:")),
                ],
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
            "#^\w+\s+-\s+\w+,\s+(\w+)\s+(\d+)$#",
            "#^\w+,\s+(\w+)\s+(\d+)\s+(\d+:\d+\s+[AP]M)$#",
        ];
        $out = [
            "$2 $1 $year",
            "$2 $1 $year, $3",
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
