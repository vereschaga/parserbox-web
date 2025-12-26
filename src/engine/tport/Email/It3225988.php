<?php

namespace AwardWallet\Engine\tport\Email;

class It3225988 extends \TAccountCheckerExtended
{
    public $mailFiles = "tport/it-3225988.eml";
    public $reBody = 'JOANNE PREVETT TRAVEL';
    public $reBody2 = "Flight information";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $text = text($this->http->Response['body']);
                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = re("#AIRLINE BOOKING REF\.\s*(\w{6})#", $text);
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

                $xpath = "//*[contains(text(),'Depart')]/ancestor-or-self::p[1]/preceding-sibling::p[2]";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                $year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");

                foreach ($nodes as $root) {
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = re("#\w{2}(\d+)#", preg_replace("#\s+#", "", $this->http->FindSingleNode(".", $root, true, "#(?:^|-)\s*.+#")));

                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $itsegment['DepName'] = $this->http->FindSingleNode("./following-sibling::p[contains(., 'Depart')][1]/following-sibling::p[3]", $root);

                    // DepDate
                    $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./following-sibling::p[contains(., 'Depart')][1]/following-sibling::p[1]", $root) . ', ' . $this->http->FindSingleNode("./following-sibling::p[contains(., 'Depart')][1]/following-sibling::p[2]", $root));

                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $itsegment['ArrName'] = $this->http->FindSingleNode("./following-sibling::p[contains(., 'Arrive')][1]/following-sibling::p[3]", $root);

                    // ArrDate
                    $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./following-sibling::p[contains(., 'Arrive')][1]/following-sibling::p[1]", $root) . ', ' . $this->http->FindSingleNode("./following-sibling::p[contains(., 'Arrive')][1]/following-sibling::p[2]", $root));

                    // AirlineName
                    $itsegment['AirlineName'] = re("#(\w{2})\d+#", preg_replace("#\s+#", "", $this->http->FindSingleNode(".", $root, true, "#(?:^|-)\s*.+#")));

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
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }

        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) === null) {
            return false;
        }

        return strpos($html, $this->reBody) !== false && strpos($html, $this->reBody2) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $pdf = $pdfs[0];
        $body = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
        $this->http->SetBody($body);

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
            ],
        ];

        return $result;
    }
}
