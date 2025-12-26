<?php

namespace AwardWallet\Engine\reurope\Email;

class It2755661 extends \TAccountCheckerExtended
{
    public $mailFiles = "reurope/it-2755661.eml";
    public $reFrom = "/trainsfares\.co\.uk/";
    public $reBody = 'Rail Europe';
    public $reBody2 = "This email";
    public $reBody3 = "IS NOT";
    public $reBody4 = "a valid document to travel";
    public $reSubject = "Booking Summary";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // Parsing subject "it-2755661.eml"
            $this->reBody4 => function (&$itineraries) {
                // echo $this->http->Response['body'];
                // die();
                $it = [];
                $it['Kind'] = 'T';
                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Booking number')]/span");

                // TripNumber
                // Passengers
                // AccountNumbers
                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = $this->http->FindSingleNode("//*[contains(text(), 'Total price')]/strong[2]");

                // BaseFare
                // Currency
                $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(), 'Total price')]/strong[1]");

                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory
                $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

                // Segments roots
                $xpath = "//*[contains(text(), 'Train ticket')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]";
                $segments = $this->http->XPath->query($xpath);

                if ($segments->length == 0) {
                    $this->http->Log("segments not found: $xpath", LOG_LEVEL_NORMAL);
                }

                // Parse segments
                foreach ($segments as $root) {
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//*[contains(text(), 'Arrival date')]/../following-sibling::td", $root, true, "#(\d+)#");

                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $itsegment['DepName'] = trim($this->http->FindSingleNode(".//*[contains(text(), 'Segment')]", $root, true, "#Origin:\s+([^>]+)>#ms"));

                    // DepAddress
                    // DepDate
                    $itsegment['DepDate'] = strtotime(
                        str_replace("/", '.', trim($this->http->FindSingleNode(".//*[contains(text(), 'Departure date')]", $root, true, "#Departure date:(.+)$#ms"))) . ' ' .
                        str_replace("/", '.', trim($this->http->FindSingleNode(".//*[contains(text(), 'Departure time')]", $root, true, "#Departure time:(.+)$#ms")))
                    );

                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $itsegment['ArrName'] = trim($this->http->FindSingleNode(".//*[contains(text(), 'Segment')]", $root, true, "#Destination:\s+(.+)$#ms"));

                    // ArrAddress
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(
                        str_replace("/", '.', trim($this->http->FindSingleNode(".//*[contains(text(), 'Arrival date')]", $root, true, "#Arrival date:(.+)$#ms"))) . ' ' .
                        str_replace("/", '.', trim($this->http->FindSingleNode(".//*[contains(text(), 'Arrival time')]", $root, true, "#Arrival time:(.+)$#ms")))
                    );

                    // Type
                    // TraveledMiles
                    // Cabin
                    $itsegment['Cabin'] = trim($this->http->FindSingleNode(".//*[contains(text(), 'Product')]/../following-sibling::tr[1]", $root, true, "#Cl\s+(.*?)\s+Adult#"));

                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    $seatsField = $this->http->FindSingleNode("//*[contains(text(), 'Seating')]/../following-sibling::tr[1]", $root);
                    preg_match_all("#Seat ([0-9A-Z]+)#", $seatsField, $Seats, PREG_PATTERN_ORDER);
                    $itsegment['Seats'] = implode(', ', $Seats[1]);

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

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false
                && strpos($body, $this->reBody3) !== false && strpos($body, $this->reBody4) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false
               && preg_match($this->reFrom, $headers['from']);
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
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
