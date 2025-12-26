<?php

namespace AwardWallet\Engine\trenitalia\Email;

class It2834347 extends \TAccountCheckerExtended
{
    public $mailFiles = "trenitalia/it-2834347.eml";
    public $reFrom = "orders@italiarail.com";
    public $reBody = 'ItaliaRail.com';
    public $reBody2 = "Please retain this email for your records, it contains important information about your reservation and details you will need to provide the Conductor on your train.";
    public $reSubject = "icket Number (PNR):";
    protected $parser = null;

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $it = [];
                $it['Kind'] = 'T';

                // RecordLocator
                $it['RecordLocator'] = re("#Ticket\s+Number\s+\(PNR\)\s*:\s*(\S+)#", $this->parser->getHeader("subject"));

                // TripNumber
                $it['TripNumber'] = $this->http->FindSingleNode("//*[contains(text(), 'Order#')]", null, true, "#Order\#:\s*(\S+)#ms");

                // Passengers
                $it['Passengers'] = $this->http->FindNodes("//*[contains(text(), 'Passenger Name')]/following::*[1]");

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

                // Segments roots
                $xpath = "//*[contains(text(), 'Depart')]/..";
                $segments = $this->http->XPath->query($xpath);

                if ($segments->length == 0) {
                    $this->http->Log("segments not found: $xpath", LOG_LEVEL_NORMAL);
                }

                // Parse segments
                foreach ($segments as $root) {
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("./following-sibling::*[1]", $root, true, "#Train:.*?(\d+)#ms");

                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $itsegment['DepName'] = trim($this->http->FindSingleNode("./td[2]", $root, true, "#^(.*?)\d+#ms"));

                    // DepAddress
                    // DepDate
                    $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]", $root, true, "#^.*?(\d+.+)#ms"));

                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $itsegment['ArrName'] = trim($this->http->FindSingleNode("./td[4]", $root, true, "#^(.*?)\d+#ms"));

                    // ArrAddress
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[4]", $root, true, "#^.*?(\d+.+)#ms"));

                    // Type
                    $itsegment['Type'] = $this->http->FindSingleNode("./following-sibling::*[1]", $root, true, "#Train:.*?\d+#ms");

                    // TraveledMiles
                    // Cabin
                    $itsegment['Cabin'] = $this->http->FindSingleNode("./following-sibling::*[1]", $root, true, "#Class:\s*(\w+)#");

                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    $itsegment['Seats'] = $this->http->FindSingleNode("//td[contains(., 'Seat(s)')]", null, true, "#Seat\(s\):\s*([\d\w ,]+)#");

                    // Duration
                    // Meal
                    // Smoking
                    // Stops
                    $it['TripSegments'][] = $itsegment;
                }
                $itineraries[] = $it;
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
        $this->parser = $parser;

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
            ],
        ];

        return $result;
    }
}
