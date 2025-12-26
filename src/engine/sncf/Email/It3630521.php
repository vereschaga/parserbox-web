<?php

namespace AwardWallet\Engine\sncf\Email;

class It3630521 extends \TAccountCheckerExtended
{
    public $reBody = 'voyages-sncf.com';
    public $reBody2 = "Tickets MUST be printed at the station before you board the train";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $text = text($this->http->Response["body"]);

                // Segments roots
                $xpath = "//*[normalize-space(text())='Departure:']/ancestor::tr[1]/..";
                $segments = $this->http->XPath->query($xpath);

                if ($segments->length == 0) {
                    $this->http->Log("segments not found: $xpath", LOG_LEVEL_NORMAL);
                }

                $trains = [];

                foreach ($segments as $segment) {
                    if ($pnr = $this->http->FindSingleNode("./following::*[contains(text(), 'PNR Reference')][1]/ancestor::td[1]", $segment, true, "#PNR Reference\s*:\s*(\w+)#")) {
                        $trains[$pnr][] = $segment;
                    } else {
                        $trains[re("#Booking Number\s+:\s+(\w+)#", $text)][] = $segment;
                    }
                }

                foreach ($trains as $pnr=>$roots) {
                    $it = [];
                    $it['Kind'] = 'T';

                    // RecordLocator
                    $it['RecordLocator'] = $pnr;

                    // TripNumber
                    // Passengers
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

                    // Parse segments
                    foreach ($roots as $root) {
                        $date = strtotime($this->http->FindSingleNode("./tr[2]", $root, true, "#Travel on \S+\s+(.+)$#ms"));

                        $itsegment = [];
                        // FlightNumber
                        $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

                        // DepCode
                        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                        // DepName
                        $itsegment['DepName'] = trim($this->http->FindSingleNode("./tr[2]", $root, true, "#Departure\s*:\s*(.*?)\s+on\s+\w+\s+\d+/\d+/\d+\s+at\s+\d+:\d+#ms"));

                        // DepAddress
                        // DepDate
                        $itsegment['DepDate'] = strtotime(str_replace("/", ".", $this->http->FindSingleNode("./tr[2]", $root, true, "#Departure\s*:\s*.*?\s+on\s+\w+\s+(\d+/\d+/\d+)\s+at\s+\d+:\d+#ms")) . ', ' . $this->http->FindSingleNode("./tr[2]", $root, true, "#Departure\s*:\s*.*?\s+on\s+\w+\s+\d+/\d+/\d+\s+at\s+(\d+:\d+)#ms"));

                        // ArrCode
                        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                        // ArrName
                        $itsegment['ArrName'] = trim($this->http->FindSingleNode("./tr[3]", $root, true, "#Arrival\s*:\s*(.*?)\s+on\s+\w+\s+\d+/\d+/\d+\s+at\s+\d+:\d+#ms"));

                        // ArrAddress
                        // ArrDate
                        $itsegment['ArrDate'] = strtotime(str_replace("/", ".", $this->http->FindSingleNode("./tr[3]", $root, true, "#Arrival\s*:\s*.*?\s+on\s+\w+\s+(\d+/\d+/\d+)\s+at\s+\d+:\d+#ms")) . ', ' . $this->http->FindSingleNode("./tr[3]", $root, true, "#Arrival\s*:\s*.*?\s+on\s+\w+\s+\d+/\d+/\d+\s+at\s+(\d+:\d+)#ms"));

                        // Type
                        // TraveledMiles
                        // Cabin
                        $itsegment['Cabin'] = $this->http->FindSingleNode("./following::*[contains(text(), 'After-Sales Conditions:')][1]/ancestor::tr[1]/td[3]", $root);

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
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re) !== false) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'  => 'TrainTrip',
            'parsedData' => [
                'Itineraries' => $itineraries,
                'TotalCharge' => [
                    'Total'    => $this->http->FindSingleNode("//*[normalize-space(text())='Total']/ancestor::tr[1]/td[3]"),
                    "Currency" => $this->http->FindSingleNode("//*[normalize-space(text())='Total']/ancestor::tr[1]/td[2]"),
                ],
            ],
        ];

        return $result;
    }
}
