<?php

namespace AwardWallet\Engine\lner\Email;

class Itinerary1 extends \TAccountCheckerExtended
{
    public $mailFiles = "lner/it-2568692.eml, lner/it-2616035.eml, lner/it-2623536.eml, lner/it-2651812.eml, lner/it-2653391.eml, lner/it-2707426.eml, lner/it-2733907.eml, lner/it-3355387.eml";
    public $reFrom = "/trainsfares\.co\.uk/";
    public $reFrom2 = "/virgintrainseastcoast\.com/";
    public $reBody = 'virgintrains.com';
    public $reBody2 = "Your FastTicket Reference";
    public $reBody3 = "Virgin Trains East Coast";
    public $reBody4 = "Outward Journey";
    public $reSubject = "Your Booking Confirmation";
    public $reSubject2 = "Your Virgin Trains East Coast booking";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // Parsing subject "it-2240390.eml"
            $this->reBody2 => function (&$itineraries) {
                $it = [];
                $it['Kind'] = 'T';

                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(., 'Your booking reference')]", null, true, "#Your\s+booking\s+reference\s+is\s+(\w+)#i");

                // TripNumber
                $it['TripNumber'] = $this->http->FindSingleNode("//text()[contains(., 'Your FastTicket')]", null, true, "#Your\s+FastTicket\s+Reference\s+is\s+(\w+)#i");

                // Passengers
                // AccountNumbers
                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = cost($this->http->FindSingleNode("//text()[normalize-space(.)='Total amount paid:']/following::text()[normalize-space(.)][1]"));

                // BaseFare
                // Currency
                $it['Currency'] = currency($this->http->FindSingleNode("//text()[normalize-space(.)='Total amount paid:']/following::text()[normalize-space(.)][1]"));

                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory
                $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

                // Segments roots
                $xpath = "//*[normalize-space(text())='Departs']/ancestor::tr[1]/following-sibling::tr";
                $segments = $this->http->XPath->query($xpath);

                if ($segments->length == 0) {
                    $this->http->Log("segments not found: $xpath", LOG_LEVEL_NORMAL);
                }

                // Parse segments
                foreach ($segments as $root) {
                    $date = strtotime($this->http->FindSingleNode("./preceding::*[normalize-space(text())='Travel'][1]/..", $root, true, "#Travel on \S+\s+(.+)$#ms"));

                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $itsegment['DepName'] = trim($this->http->FindSingleNode("./td[1]", $root, true, "#\d+:\d+\s+-\s+(.+)$#ms"));

                    // DepAddress
                    // DepDate
                    $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[1]", $root, true, "#(\d+:\d+)#ms"), $date);

                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $itsegment['ArrName'] = trim($this->http->FindSingleNode("./td[2]", $root, true, "#\d+:\d+\s+-\s+(.+)$#ms"));

                    // ArrAddress
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[2]", $root, true, "#(\d+:\d+)#ms"), $date);

                    // Type
                    $itsegment['Type'] = trim($this->http->FindSingleNode("./td[3]", $root, true, "#([^\(]+)#ms"));

                    // TraveledMiles
                    // Cabin
                    $itsegment['Cabin'] = $this->http->FindSingleNode("./td[4]", $root, true, "#Coach: (\S+)#");

                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    $seatsField = $this->http->FindSingleNode("./td[4]", $root);
                    $seatsField = explode("\n", $seatsField);
                    $seatsField = array_filter($seatsField);
                    $seats = [];

                    foreach ($seatsField as $seat) {
                        $seats[] = re("#Seat: (\d+)#", $seat);
                    }

                    $itsegment['Seats'] = implode(',', $seats);

                    // Duration
                    // Meal
                    // Smoking
                    // Stops
                    $it['TripSegments'][] = $itsegment;
                }
                $itineraries[] = $it;
            },
            // Parsing subject "it-2653391.eml"
            $this->reBody4 => function (&$itineraries) {
                $it = [];
                $it['Kind'] = 'T';
                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(),'Your confirmation number is')]/strong");

                // TripNumber
                // Passengers
                // AccountNumbers
                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = $this->http->FindSingleNode("//*[contains(text(),'has been charged')]", null, true, "#[^\s\d]+([0-9\.]+)$#ms");

                // BaseFare
                // Currency
                $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(),'has been charged')]", null, true, "#([^\s\d]+)[0-9\.]+$#ms");

                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory
                $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

                // Segments Roots
                $xpath = "//*[contains(text(),'Outward Journey')]/ancestor::td[1]/descendant::table[1]/ancestor::*[1]/table";
                $segments = $this->http->XPath->query($xpath);

                if ($segments->length == 0) {
                    $this->http->Log("segments not found: $xpath", LOG_LEVEL_NORMAL);
                }

                // Miles
                $miles = $this->http->FindNodes("//*[contains(text(),'Total Price')]/following-sibling::*[contains(text(),'miles')]", null, "#\d+#ms");

                if (count($miles) < 2) {
                    unset($miles);
                }

                // Destinations, Dates
                preg_match_all('/\s+Journey\s+(.*?)to(.*?)on\s+(\d+\s+\S+\s+\d+)/ms', $this->http->FindSingleNode("//*[contains(text(),'Outward Journey')]/ancestor::td[1]"), $journey, PREG_PATTERN_ORDER);

                if (empty($journey) || !isset($journey[3][0])) {
                    return false;
                }

                if (!re("#^\d{1,2}\s+\S+\s+\d{4}$#", $journey[3][0])) {
                    return false;
                }

                // Parse segments
                $i = 0;

                foreach ($segments as $root) {
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $itsegment['DepName'] = trim($journey[1][$i]);

                    // DepAddress
                    // DepDate
                    $itsegment['DepDate'] = strtotime($journey[3][$i] . ' ' . re("#(\d+:\d+)#ms", $this->getField('Departs', $root)));

                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $itsegment['ArrName'] = trim($journey[2][$i]);

                    // ArrAddress
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime($journey[3][$i] . ' ' . re("#(\d+:\d+)#ms", $this->getField('Arrives', $root)));

                    // Type
                    $itsegment['Type'] = 'TRAIN';

                    // TraveledMiles
                    if (isset($miles)) {
                        $itsegment['TraveledMiles'] = $miles[$i];
                    }

                    // Cabin
                    $itsegment['Cabin'] = re("#Coach: (\S+)#", $this->getField('Seats Reserved', $root));

                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    $seats = [];
                    $seats[] = re("#Seats\s*:\s*(\d+)#", $this->http->FindSingleNode(".", $root));

                    $itsegment['Seats'] = implode(',', $seats);

                    // Duration
                    // Meal
                    // Smoking
                    // Stops
                    $it['TripSegments'][] = $itsegment;
                    $i++;
                }
                $itineraries[] = $it;
            },
        ];
    }

    // public function detectEmailFromProvider($from) {
    // return preg_match($this->reFrom, $from) || preg_match($this->reFrom2, $from);
    // }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return (strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false)
                || (strpos($body, $this->reBody3) !== false && strpos($body, $this->reBody4) !== false);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false
               || strpos($headers["subject"], $this->reSubject2) !== false;
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
            $this->http->SetBody($body);
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re) !== false) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'  => 'Itinerary1',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public function getField($str, $root)
    {
        return $this->http->FindSingleNode(".//*[contains(text(), '{$str}')]/ancestor-or-self::td[1]/following-sibling::td[1]", $root);
    }
}
