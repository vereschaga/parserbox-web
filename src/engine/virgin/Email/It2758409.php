<?php

namespace AwardWallet\Engine\virgin\Email;

class It2758409 extends \TAccountCheckerExtended
{
    public $mailFiles = "virgin/it-1.eml, virgin/it-2.eml, virgin/it-2753264.eml, virgin/it-2758409.eml, virgin/it-3.eml, virgin/it-5168716.eml, virgin/it-5182512.eml, virgin/it-6895551.eml, virgin/it-6898700.eml, virgin/it-6898710.eml, virgin/it-9809913.eml";
    public $reBody = 'Virgin Atlantic Airways';
    public $reBody2 = "FLIGHT";
    public $reBody3 = "Passenger:";
    public $reSubject = "Virgin Atlantic Airways e-Ticket";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";
        // RecordLocator

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Reference:']/following::text()[normalize-space(.)][1]");
        // TripNumber
        // Passengers
        $it['Passengers'] = explode(', ', $this->http->FindSingleNode("//text()[normalize-space(.)='Passenger:']/following::text()[normalize-space(.)][1]"));
        // AccountNumbers
        // Cancelled
        // TotalCharge
        if (!$it['TotalCharge'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Amount Received' or normalize-space(.)='AMOUNT RECEIVED']/following::text()[normalize-space(.)][1]", null, true, "#^[A-Z]{3}\s*([\d,.]+)#")) {
            $it['TotalCharge'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Amount Received' or normalize-space(.)='AMOUNT RECEIVED']/following::text()[normalize-space(.)][2]", null, true, "#([\d\,\.]+)#");
        }

        // BaseFare
        // Currency
        $it['Currency'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Amount Received' or normalize-space(.)='AMOUNT RECEIVED']/following::text()[normalize-space(.)][1]", null, true, "#^[A-Z]{3}#");

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//*[contains(text(),'FLIGHT') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[2]//tr[1]/following-sibling::tr[normalize-space(.)]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        $year = $this->http->FindSingleNode("//text()[normalize-space(.)='Issue Date:']/following::text()[normalize-space(.)][1]", null, true, "#(\d{4}|\d{2})$#");

        if (strlen($year) == 2) {
            $year = '20' . $year;
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("(./td[normalize-space(.)])[1]", $root, true, "#(\d+)#");

            // DepCode
            if (!$itsegment['DepCode'] = $this->http->FindSingleNode("(./td[normalize-space(.)])[3]", $root, true, "#^[A-Z]{3}$#")) {
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("(./td[normalize-space(.)])[3]", $root);
            }
            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("(./td[normalize-space(.)])[2]", $root) . ' ' . $year . ', ' . $this->http->FindSingleNode("(./td[normalize-space(.)])[4]", $root), $this->date);

            // ArrCode
            if (!$itsegment['ArrCode'] = $this->http->FindSingleNode("(./td[normalize-space(.)])[5]", $root, true, "#^[A-Z]{3}$#")) {
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("(./td[normalize-space(.)])[5]", $root);
            }
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("(./td[normalize-space(.)])[2]", $root) . ' ' . $year . ', ' . $this->http->FindSingleNode("(./td[normalize-space(.)])[6]", $root), $this->date);

            if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("(./td[normalize-space(.)])[1]", $root, true, "#(\D+)#");

            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("//td[1][
				normalize-space(.)='" . $itsegment['AirlineName'] . str_pad($itsegment['FlightNumber'], 4, '0', STR_PAD_LEFT) . "' or
				normalize-space(.)='{$itsegment['AirlineName']} {$itsegment['FlightNumber']}'
			]/../td[3]//text()[contains(., 'Terminal')]", null, true, "#Terminal (\w+)#");
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("//td[1][
				normalize-space(.)='" . $itsegment['AirlineName'] . str_pad($itsegment['FlightNumber'], 4, '0', STR_PAD_LEFT) . "' or
				normalize-space(.)='{$itsegment['AirlineName']} {$itsegment['FlightNumber']}'
			]/../following-sibling::tr[1]/td[last()]//text()[contains(., 'Terminal')]", null, true, "#Terminal (\w+)#");

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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false && strpos($body, $this->reBody3) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
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
}
