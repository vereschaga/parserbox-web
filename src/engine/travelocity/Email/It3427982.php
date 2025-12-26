<?php

namespace AwardWallet\Engine\travelocity\Email;

class It3427982 extends \TAccountCheckerExtended
{
    public $mailFiles = "travelocity/it-3427982.eml, travelocity/it-3427993.eml";
    public $reBody = 'Travelocity';
    public $reBody2 = "Travel Confirmation";
    public $reSubject = "Travelocity travel confirmation";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                // FLIGHT
                if ($root = $this->http->XPath->query("//*[normalize-space(text())='Flight summary']/ancestor::tr[2]/following-sibling::tr")->item(0)) {
                    $it = [];

                    $it['Kind'] = "T";
                    // RecordLocator

                    $it['RecordLocator'] = CONFNO_UNKNOWN;
                    // TripNumber
                    // Passengers
                    $it['Passengers'] = $this->http->FindNodes("//*[normalize-space(text())='Traveler and cost summary']/ancestor::tr[1]/following-sibling::tr[contains(./td[2], 'Adult')]/td[1]");
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

                    $xpath = ".//text()[normalize-space(.)='Flight:']/ancestor::tr[1]";
                    $nodes = $this->http->XPath->query($xpath, $root);

                    if ($nodes->length == 0) {
                        $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                    }

                    $year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");

                    foreach ($nodes as $root) {
                        $date = strtotime($this->http->FindSingleNode("./preceding-sibling::tr[3]", $root));

                        $itsegment = [];
                        // FlightNumber
                        $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[5]", $root, true, "#Flight\s*:\s*(\d+)#");

                        // DepCode
                        $itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]", $root, true, "#\(([A-Z]{3})\)#");

                        // DepName
                        // DepDate
                        $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[1]", $root, true, "#(\d+:\d+\s*[ap]m)#"), $date);

                        // ArrCode
                        $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#\(([A-Z]{3})\)#");

                        // ArrName
                        // ArrDate
                        $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]", $root, true, "#(\d+:\d+\s*[ap]m)#"), $date);

                        if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                            $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
                        }

                        // AirlineName
                        $itsegment['AirlineName'] = $this->http->FindSingleNode("(./td[5]//text()[normalize-space(.)])[1]", $root);

                        // Aircraft
                        $itsegment['Aircraft'] = $this->http->FindSingleNode("(./following-sibling::tr[2]//*)[last()]", $root);

                        // TraveledMiles
                        $itsegment['TraveledMiles'] = $this->http->FindSingleNode("(./td[4]//text()[normalize-space(.)])[1]", $root);

                        // Cabin
                        $itsegment['Cabin'] = $this->http->FindSingleNode("(./following-sibling::tr[2]//b)[1]", $root, true, "#\w+#");

                        // BookingClass
                        // PendingUpgradeTo
                        // Seats
                        // Duration
                        $itsegment['Duration'] = $this->http->FindSingleNode("./td[4]", $root, true, "#Duration\s*:\s*(.+)#");

                        // Meal
                        // Smoking
                        // Stops
                        $it['TripSegments'][] = $itsegment;
                    }
                    $itineraries[] = $it;
                }

                // CAR
                if ($root = $this->http->XPath->query("//*[normalize-space(text())='Car rental summary']/ancestor::tr[2]/following-sibling::tr[contains(., 'Pick up:')]")->item(0)) {
                    $it = [];

                    $it['Kind'] = "L";

                    // Number
                    $it['Number'] = $this->http->FindSingleNode(".//*[contains(text(), 'Car confirmation number:')]", $root, true, "#(\w+)$#");
                    // TripNumber
                    // PickupDatetime
                    $it['PickupDatetime'] = strtotime($this->http->FindSingleNode(".//text()[normalize-space(.)='Pick up:']/following::text()[normalize-space(.)][1]", $root));

                    // PickupLocation
                    $it['PickupLocation'] = $this->http->FindSingleNode(".//*[normalize-space(text())='Location:']/following-sibling::*[1]", $root);

                    // DropoffDatetime
                    $it['DropoffDatetime'] = strtotime($this->http->FindSingleNode(".//text()[normalize-space(.)='Drop off:']/following::text()[normalize-space(.)][1]", $root));

                    // DropoffLocation
                    $it['DropoffLocation'] = $this->http->FindSingleNode(".//*[normalize-space(text())='Location:']/following-sibling::*[1]", $root);

                    // PickupPhone
                    // PickupFax
                    // PickupHours
                    $it['PickupHours'] = $this->http->FindSingleNode(".//*[normalize-space(text())='Hours of operation:']/following::text()[normalize-space(.)][1]", $root, true, "#^\d+/\d+/\d+\s*:\s*(\d+:\d+\s+[ap]m\s+-\s+\d+:\d+\s+[ap]m)#");

                    // DropoffPhone
                    // DropoffHours
                    $it['DropoffHours'] = $this->http->FindSingleNode(".//*[normalize-space(text())='Hours of operation:']/following::text()[normalize-space(.)][1]", $root, true, "#\d+/\d+/\d+\s*:\s*(\d+:\d+\s+[ap]m\s+-\s+\d+:\d+\s+[ap]m)$#");

                    // DropoffFax
                    // RentalCompany
                    $it['RentalCompany'] = $this->http->FindSingleNode("(.//b[normalize-space(.)]/*)[1]", $root);

                    // CarType
                    $it['CarType'] = trim($this->http->FindSingleNode("(.//b[normalize-space(.)]/*)[1]/following::text()[normalize-space(.)][1]", $root), " :");

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
            'emailType'  => 'Flight',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    private function getTypeRoot($text)
    {
        return $this->http->XPath->query("//*[normalize-space(text())='{$text}']/ancestor::tr[2]/following-sibling::tr")->item(0);
    }
}
