<?php

namespace AwardWallet\Engine\velocity\Email;

class CheckInConfirmation extends \TAccountChecker
{
    public $mailFiles = "velocity/it-7701385.eml";

    private $reBody = 'virginaustralia.com';
    private $reBody2 = [
        "Itinerary Details for",
    ];
    private $reSubject = "Virgin Australia-Check-in Confirmation";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $its[] = $this->parseEmail();

        return [
            'emailType'  => 'CheckInConfirmation',
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reBody) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $reBody2) {
            if (stripos($body, $reBody2) !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Booking Reference")]/following::text()[string-length(normalize-space(.))>4][1]', null, true, '/([A-Z\d]{5,6})/');

        $passenger = implode("\n", $this->http->FindNodes('//text()[starts-with(normalize-space(.),"Guest Names/s")]/ancestor::*[1]/following-sibling::*[string-length(normalize-space(.))>4]//text()'));

        if (preg_match("/(.+)(?:\s+[^#]+#(\d+))?/", $passenger, $m)) {
            $it['Passengers'][] = trim($m[1]);

            if (!empty($m[2])) {
                $it['AccountNumbers'][] = $m[2];
            }
        }

        $xpath = "//table[contains(.,'Flight ')]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $flight = $this->http->FindSingleNode(".//tr[1]", $root);

            if (preg_match("#Flight\s+([A-Z\d]{2})\s*(\d{1,5})\s*:\s*([^(]+)\(([A-Z]{3})\)\s*-\s*([^(]+)\(([A-Z]{3})\)(?:\s+Operated by\s+(.+))?#", $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['DepName'] = trim($m[3]);
                $seg['DepCode'] = $m[4];
                $seg['ArrName'] = trim($m[5]);
                $seg['ArrCode'] = $m[6];

                if (!empty($m[7])) {
                    $seg['Operator'] = $m[7];
                }
            }
            $flight = $this->http->FindSingleNode(".//tr[2]/td[1]", $root);

            if (preg_match("#DEPARTS\s*\w+\s+(\d{1,2}\s*\w+\s*\d{4})\s*-\s*(\d{2}:\d{2})\s+ARRIVES\s*\w+\s+(\d{1,2}\s*\w+\s*\d{4})\s*-\s*(\d{2}:\d{2})#", $flight, $m)) {
                $seg['DepDate'] = strtotime($m[1] . ' ' . $m[2]);
                $seg['ArrDate'] = strtotime($m[3] . ' ' . $m[4]);
            }
            $seg['DepartureTerminal'] = $this->http->FindSingleNode(".//tr[2]/td[2]", $root, true, "#TERMINAL\s+([s\S]+)#");
            $seg['Seats'][] = $this->http->FindSingleNode(".//tr[2]/td[3]", $root, true, "#SEAT\s+(\d+[A-Z])#");

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }
}
