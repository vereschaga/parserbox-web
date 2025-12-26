<?php

namespace AwardWallet\Engine\maketrip\Email;

// TODO: merge with parsers maketrip/BusETicketBlue (in favor of maketrip/BusETicketBlue)

class ATicket extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-6787165.eml";

    private $from = 'makemytrip.com';

    private $detects = [
        'MakeMyTrip would not be able to process refunds for cancellations done directly with the bus operators',
        'Online Cancellation and Rules',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            'emailType'  => 'ATicketEn',
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (
                stripos($body, $detect) !== false
                && $this->http->XPath->query("//img[contains(@src, 'makemytrip/images/logo') or contains(@alt, 'Makemytrip')]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripCategory' => TRIP_CATEGORY_BUS];

        $it['RecordLocator'] = $this->http->FindSingleNode("(//td[contains(., 'PNR:') or contains(normalize-space(.), 'Ticket Number')]/following-sibling::td[1])[1]", null, true, '/\b[A-Z\d]{5,8}\b/');

        $it['Passengers'] = $this->getNode2();

        $xpath = "//table[contains(., 'Booking Details') and not(descendant::table)]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $seg['DepName'] = $this->getNode('From', $root);

            $seg['ArrName'] = $this->getNode('To:', $root);

            $seg['AirlineName'] = $this->getNode('Bus Operator', $root);

            $seg['Type'] = $this->getNode('Bus Type', $root);

            $seg['DepDate'] = strtotime(str_replace('-', ' ', $this->getNode('Journey Date', $root) . ' ' . $this->getNode('Boarding Date and Time', $root)));

            $seg['ArrDate'] = MISSING_DATE;

            $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

            $it['TotalCharge'] = $this->getNode('Total Fare', $root);

            $ticketNumber = $this->getNode('Ticket Number', $root);

            if (preg_match('/\b([\d\-A-Z]{5,})\b/', $ticketNumber, $m) && $m[1] !== $it['RecordLocator']) {
                $it['TicketNumbers'][] = $m[1];
            } elseif (preg_match('/operator pnr:\s+\w+\/\s*(\d+)\//i', $ticketNumber, $m)) {
                $it['TicketNumbers'][] = $m[1];
            }

            $seg['Seats'] = $this->getNode2(3);

            if (count($arr = $this->getNode2(4)) > 0) {
                $seg['Cabin'] = array_shift($arr);
            }

            $seg['DepAddress'] = $this->http->FindSingleNode("(//td[contains(., 'Location') or contains(., 'Address:')]/following-sibling::td[1])[1]");

            if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getNode($str, \DOMNode $root, $re = null)
    {
        return $this->http->FindSingleNode("descendant::td[contains(., '" . $str . "')]/following-sibling::td[1]", $root, true, $re);
    }

    private function getNode2($td = 2)
    {
        return $this->http->FindNodes("//tr[contains(., 'Name') and contains(., 'Seat')]/following-sibling::tr/td[" . $td . "]");
    }
}
