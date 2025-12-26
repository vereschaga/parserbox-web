<?php

namespace AwardWallet\Engine\mabuhay\Email;

use PlancakeEmailParser;

class CheckInReminder extends \TAccountChecker
{
    public $mailFiles = "mabuhay/it-12129568.eml";

    private $detects = [
        'or any Philippine Airlines office in your area',
        'Sorry, your flight schedule has been changed',
    ];

    private $from = '/[@\.]philippineairlines\.com/i';

    private $prov = 'Philippine Airlines';

    private $lang = 'en';

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $class = explode('\\', __CLASS__);

        return [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Philippine Airlines') === false) {
            return false;
        }

        return stripos($headers['subject'], 'You Can Now Check-In For') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(): array
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $passengers = $this->http->FindNodes("//tr[starts-with(normalize-space(.), 'PASSENGER NAME/S') and not(.//tr)]/following-sibling::tr[string-length(normalize-space(.)) > 8]");
        $paxs = [];

        if (is_array($passengers)) {
            array_walk($passengers,
                function ($str) use (&$paxs) {
                    $ps = explode(', ', $str);

                    foreach ($ps as $p) {
                        $paxs[] = $p;
                    }
                });
        }

        if (0 < count($paxs)) {
            $it['Passengers'] = array_filter(array_unique($paxs));
        }

        $it['RecordLocator'] = $this->http->FindSingleNode("(//td[starts-with(normalize-space(.), 'BOOKING REFERENCE') and not(.//td)]/following-sibling::td[1])[1]");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("//tr[contains(normalize-space(.), 'BOOKING REFERENCE') and not(.//tr)][1]/preceding-sibling::tr[1]/td[normalize-space()][last()]", null, true, '/^[A-Z\d]{5,9}$/');
        }

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("//td[descendant::text()[normalize-space()][2][contains(normalize-space(),'BOOKING REFERENCE')] and not(.//td)]/descendant::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,9}$/');
        }

        if (0 < $this->http->XPath->query("//tr[(contains(normalize-space(.), 'NEW FLIGHT DETAILS') or contains(normalize-space(.), 'ORIGINAL DETAILS')) and not(.//tr)]")->length) {
            $xpath = "//tr[normalize-space(.)='NEW FLIGHT DETAILS'][1]/following-sibling::tr[contains(normalize-space(.), 'ROUTE') and contains(normalize-space(.), 'FLIGHT NUMBER') and not(.//tr)][1]";
        } else {
            $xpath = "//tr[contains(normalize-space(.), 'ROUTE') and contains(normalize-space(.), 'FLIGHT NUMBER') and not(.//tr)]";
        }
        $segments = $this->http->XPath->query($xpath);

        if (0 === $segments->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");

            return [];
        }

        foreach ($segments as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $seg['DepCode'] = $this->getNode($root);

            $seg['ArrCode'] = $this->getNode($root, 2, 3);

            if (preg_match('/([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/', $this->getNode($root, 2, 4), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $seg['DepName'] = $this->getNode($root, 3, 1);

            $seg['ArrName'] = $this->getNode($root, 3, 2);

            $depTime = $this->getNode($root, 7, 1);
            $arrTime = $this->getNode($root, 7, 2);

            $seg['DepDate'] = strtotime($this->getNode($root, 8, 1) . ', ' . $depTime);

            $seg['ArrDate'] = strtotime($this->getNode($root, 8, 2) . ', ' . $arrTime);

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getNode(\DOMNode $root, int $tr = 2, int $td = 1, string $re = null): ?string
    {
        return $this->http->FindSingleNode("following-sibling::tr[{$tr}]/td[normalize-space(.)][{$td}]", $root, true, $re);
    }
}
