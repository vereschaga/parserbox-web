<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\maketrip\Email;

class FTicket extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-7279764.eml";

    private $detects = [
        'Thank you for booking your flight tickets through our Travel Agency',
        'Thank you for booking your flight tickets throught our Travel Agency',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            'emailType'  => 'FlightTicketEn',
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'makemytrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'makemytrip.com') !== false
            && stripos($headers['subject'], 'MakeMyTrip E-Ticket for Booking ID') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail()
    {
        $xpath = "//tr[contains(., 'AirLine') and contains(., 'Departure') and contains(., 'Arrival')]/following-sibling::tr";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        $rls = array_filter(array_unique($this->http->FindNodes($xpath . '/descendant::td[7]', null, '/([A-Z\d]{5,7})/')));

        foreach ($rls as $rl) {
            $airline[$rl] = $rl;
        }

        $airs = [];

        foreach ($roots as $root) {
            if (($rl = $this->http->FindSingleNode('descendant::td[7]', $root, true, '/([A-Z\d]{5,8})/')) && !empty($airline[$rl])) {
                $airs[$airline[$rl]][] = $root;
            }
        }

        $its = [];

        foreach ($airs as $rl => $roots) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['Passengers'] = array_filter(array_unique($this->http->FindNodes("//tr[contains(., 'Passenger Name') and contains(., 'Ticket No.')]/following-sibling::tr/td[1]")));
            $it['TicketNumbers'] = array_filter(array_unique($this->http->FindNodes("//tr[contains(., 'Passenger Name') and contains(., 'Ticket No.')]/following-sibling::tr/td[last()]")));
            $it['TotalCharge'] = $this->http->FindSingleNode("//td[contains(., 'Total Cost')]/following-sibling::td[1]");

            foreach ($roots as $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];
                $flight = $this->getNode($root);

                if (preg_match('/\s*([A-Z\d]{2}|[A-Z\s]+)\s*[-\#]?\s*(\d+)\s*/i', $flight, $m)) {
                    $seg['AirlineName'] = trim($m[1]);
                    $seg['FlightNumber'] = $m[2];
                }
                $seg['Cabin'] = $this->getNode($root, 2);
                $dep = $this->getNode($root, 3);
                $arr = $this->getNode($root, 5);
                $depArr = ['Dep' => $dep, 'Arr' => $arr];
                array_walk($depArr, function ($val, $key) use (&$seg) {
                    if (preg_match('/(\D+)\s+\(([A-Z]{3})\)/', $val, $m)) {
                        $seg[$key . 'Name'] = $m[1];
                        $seg[$key . 'Code'] = $m[2];
                    }
                });
                $seg['DepDate'] = $this->normalizeDate($this->getNode($root, 4));
                $seg['ArrDate'] = $this->normalizeDate($this->getNode($root, 6));
                $seg['Stops'] = $this->getNode($root, 'last()-1', '/(\d{1,2})/');
                $seg['Duration'] = $this->getNode($root, 8);
                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($str)
    {
        $in = [
            '/(\D+)\s+(\d{1,2})\s*,\s*(\d{2,4})\s*,\s*(\d{1,2}:\d{2})\s*[A-Z]+/i',
            '/(\d{1,2}\s+\D+\s+\d{2,4})\s*,\s*(\d{1,2}:\d{2})\s*[A-Z]+/i',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$1, $2',
        ];

        return strtotime(preg_replace($in, $out, $str));
    }

    private function getNode($root, $td = 1, $re = null)
    {
        return $this->http->FindSingleNode('descendant::td[' . $td . ']', $root, true, $re);
    }
}
