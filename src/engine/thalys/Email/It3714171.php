<?php

namespace AwardWallet\Engine\thalys\Email;

class It3714171 extends \TAccountCheckerExtended
{
    public $reBody = 'Thank you for buying your Thalys tickets ';
    public $reSubject = "Confirmation of your Thalys booking";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = orval(
                    $this->http->FindSingleNode("//text()[contains(., 'Related reservation code')]/following::text()[normalize-space(.)][1]"),
                    $this->http->FindSingleNode("//text()[contains(., 'Your booking reference')]/following::text()[normalize-space(.)][1]")
                );
                // TripNumber
                // Passengers
                $it['Passengers'] = $this->http->FindNodes("//*[normalize-space(text())='Passengers']/ancestor::tr[2]/following-sibling::tr[2]//*[contains(text(), '•')]/ancestor::td[1]/following-sibling::td[1]");
                // AccountNumbers
                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = cost($this->http->FindSingleNode("//*[normalize-space(text())='Total']/ancestor::td[1]/following-sibling::td[1]"));

                // BaseFare
                // Currency
                $it['Currency'] = currency($this->http->FindSingleNode("//*[normalize-space(text())='Total']/ancestor::td[1]/following-sibling::td[1]"));

                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory
                $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

                $xpath = "//*[contains(text(), 'Departure :') or contains(text(), 'Return :')]/ancestor::tr[1]/following-sibling::tr[.//img[contains(@src, '/arivalIcon.gif')]]/descendant::tr[./td[2]][1]";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                foreach ($nodes as $root) {
                    $date = strtotime($this->http->FindSingleNode("./preceding::tr[contains(., 'Departure :') or contains(., 'Return :')][1]", $root, true, "#:\s*\w+\s+(\d+\s+\w+\s+\d{4})#"));

                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]", $root, true, "#(\d+)#");

                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $itsegment['DepName'] = $this->http->FindSingleNode("./td[1]//tr[1]/td[3]", $root);

                    // DepDate
                    $itsegment['DepDate'] = strtotime(ubertime($this->http->FindSingleNode("./td[1]", $root)), $date);

                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $itsegment['ArrName'] = $this->http->FindSingleNode("./td[1]//tr[3]/td[3]", $root);

                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(ubertime($this->http->FindSingleNode("./td[1]", $root), 2), $date);

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[2]//img/@src", $root, true, "#/(\w+)\.png#");

                    // Type
                    $itsegment['Type'] = "TRAIN";

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
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false;
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
            'emailType'  => 'Train',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }
}
