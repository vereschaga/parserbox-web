<?php

namespace AwardWallet\Engine\wtravel\Email;

class It3194181 extends \TAccountCheckerExtended
{
    public $mailFiles = "wtravel/it-3194181.eml";
    public $reBody = 'World Travel';
    public $reBody2 = "2wt_triangle.jpg";
    public $reSubject = "TICKETED INVOICE for";
    public $reFrom = "ssiliquini@worldtravelinc.com";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $xpath = "//*[contains(text(), 'Depart:')]/ancestor::tr[1]/..";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }
                $airs = [];

                foreach ($nodes as $root) {
                    if ($rl = $this->http->FindSingleNode(".//*[contains(text(), 'Airline Booking Reference:')]", $root, true, "#Airline Booking Reference:\s*(\w+)#")) {
                        $airs[$rl][] = $root;
                    }
                }

                $year = 123;

                foreach ($airs as $rl=>$roots) {
                    $it = [];

                    $it['Kind'] = "T";
                    // RecordLocator
                    $it['RecordLocator'] = $rl;

                    // TripNumber
                    // Passengers
                    $it['Passengers'] = array_unique($this->http->FindNodes("//*[contains(text(), 'FF Number:')]/ancestor-or-self::td[1]/following-sibling::td[1]", null, "#\s+-\s+(.+)#"));

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

                    foreach ($roots as $root) {
                        $itsegment = [];
                        // FlightNumber
                        $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[1]", $root, true, "#Flight\s+\w{2}(\d+)#");

                        // DepCode
                        $itsegment['DepCode'] = $this->http->FindSingleNode(".//*[contains(text(), 'Depart:')]/ancestor-or-self::td[1]/following-sibling::td[1]/b[1]", $root);

                        // DepName
                        // DepDate
                        $itsegment['DepDate'] = strtotime(preg_replace("#^(\d+:\d+\s+[AP]M)\s+(.*?)$#", "$2, $1", $this->http->FindSingleNode(".//*[contains(text(), 'Depart:')]/ancestor::tr[1]/following-sibling::tr[1]", $root)));

                        // ArrCode
                        $itsegment['ArrCode'] = $this->http->FindSingleNode(".//*[contains(text(), 'Arrive:')]/ancestor-or-self::td[1]/following-sibling::td[1]/b[1]", $root);

                        // ArrName
                        // ArrDate
                        $itsegment['ArrDate'] = strtotime(preg_replace("#^(\d+:\d+\s+[AP]M)\s+(.*?)$#", "$2, $1", $this->http->FindSingleNode(".//*[contains(text(), 'Arrive:')]/ancestor::tr[1]/following-sibling::tr[1]", $root)));

                        // AirlineName
                        $itsegment['AirlineName'] = $this->http->FindSingleNode("./tr[1]", $root, true, "#Flight\s+(\w{2})\d+#");

                        // Aircraft
                        $itsegment['Aircraft'] = $this->http->FindSingleNode(".//*[contains(text(), 'Equipment:')]/ancestor-or-self::td[1]/following-sibling::td[1]", $root);

                        // TraveledMiles
                        // Cabin
                        // BookingClass
                        // PendingUpgradeTo
                        // Seats
                        // Duration
                        $itsegment['Duration'] = $this->http->FindSingleNode(".//*[contains(text(), 'Duration:')]/ancestor-or-self::td[1]/following-sibling::td[1]", $root, true, "#(\d+\s+\S+\s+and\s+\d+\s+\S+)#");

                        // Meal
                        $itsegment['Meal'] = $this->http->FindSingleNode(".//*[contains(text(), 'Meal:')]/ancestor-or-self::td[1]/following-sibling::td[1]", $root);

                        // Smoking
                        // Stops
                        $it['TripSegments'][] = $itsegment;
                    }
                    $itineraries[] = $it;
                }
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false && strpos($headers["from"], $this->reFrom) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'  => 'Flight',
            'parsedData' => [
                'Itineraries' => $itineraries,
                'TotalCharge' => total($this->http->FindSingleNode("//*[contains(text(), 'Total Charges')]/ancestor-or-self::td[1]/../td[last()]"), 'Amount'),
            ],
        ];

        return $result;
    }
}
