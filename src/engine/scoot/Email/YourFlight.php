<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\scoot\Email;

class YourFlight extends \TAccountChecker
{
    public $mailFiles = "scoot/it-10367344.eml, scoot/it-8556972.eml, scoot/it-8702858.eml, scoot/it-8708481.eml";

    private $detects = [
        'We thank you for your continued support. Now, let\'s Scoot!',
        'We thank you for your continued support and apologise for any inconvenience caused',
        'We apologise for any inconvenience caused',
        'We deeply apologise for the inconvenience caused',
        'Notwithstanding this inconvenience, thanks for choosing to fly with Scoot',
    ];

    private $reDepArr = '/([A-Z\-\s]+)\s*\W+\s*([A-Z\-\s]+)/i';

    private $from = '@flyscoot.com';

    private $lang = 'en';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        if (
        ($roots = $this->http->XPath->query("//tr[contains(., 'Route') and contains(., 'Flight No') and not(.//tr)]/following-sibling::tr[contains(., 'NOW')]"))
        && $roots->length > 0
        ) {
            $its[] = $this->parseEmail2($roots);
        } else {
            $its[] = $this->parseEmail();
        }

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'YourFlight' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 'Scoot') === false) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->from) !== false
            && isset($headers['subject']) && stripos($headers['subject'], 'Scoot and Tigerair Merger') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->getRecLoc();

        $psngs = $this->http->FindSingleNode("//span[contains(., 'Passenger')]/following-sibling::text()[1]");
        preg_match_all('/\d+\s*\.\s+([A-Z\s\-]+)/i', $psngs, $m);

        if (count($m[1]) > 0) {
            $it['Passengers'] = array_map("trim", $m[1]);
        } elseif (($node = $this->getName()) && count($m[1]) === 0) {
            $it['Passengers'][] = $node;
        }

        $xpath = "//span[contains(., 'Route') and not(following-sibling::del)]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info("Segments not found by xpath: {$xpath}");

            return [];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $this->getNode($root, 'Flight No'), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $seg['DepDate'] = $this->checkAndCorrectDate($this->getNode($root, 'Departure'));

            $seg['ArrDate'] = $this->checkAndCorrectDate($this->getNode($root, 'Arrival'));

            if (preg_match($this->reDepArr, $this->http->FindSingleNode('following-sibling::text()[1]', $root), $m)) {
                $seg['DepName'] = trim($m[1]);
                $seg['ArrName'] = trim($m[2]);
            }

            if (!empty($seg['FlightNumber']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    private function parseEmail2(\DOMNodeList $roots = null)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->getRecLoc();

        $it['Passengers'][] = $this->getName();

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            if (preg_match($this->reDepArr, $this->getSubNode($root), $m)) {
                $seg['DepName'] = trim(rtrim($m[1], '-'));
                $seg['ArrName'] = trim(rtrim($m[2], '-'));
            }

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $this->getSubNode($root, 3), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (!empty($this->http->FindSingleNode("./preceding-sibling::tr[starts-with(normalize-space(./td[4]), 'Date')]", $root))) {
                $date = strtotime($this->getSubNode($root, 4));
                $seg['DepDate'] = strtotime($this->getSubNode($root, 5, '/(\d+:\d+)/'), $date);
                $time = $this->getSubNode($root, 6);
                // 02:05+1 hrs
                if (preg_match('/(\d{1,2}:\d{1,2})(\+\d+)?/', $time, $m)) {
                    $seg['ArrDate'] = strtotime($m[1], $date);

                    if (isset($m[2])) {
                        $seg['ArrDate'] = strtotime($m[2] . 'day', $seg['ArrDate']);
                    }
                }
            } else {
                $seg['DepDate'] = strtotime($this->getSubNode($root, 4, '/(.+\d+:\d+)/'));
                $seg['ArrDate'] = strtotime($this->getSubNode($root, 5, '/(.+\d+:\d+)/'));
            }

            if (!empty($seg['FlightNumber']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    private function getSubNode(\DOMNode $root, $td = 2, $re = null)
    {
        return $this->http->FindSingleNode("td[{$td}]", $root, true, $re);
    }

    private function getName()
    {
        return $this->http->FindSingleNode("//text()[contains(., 'Dear')]", null, true, '/Dear\s+([A-Z\s\-]+)\s*[\,]*\s*/i');
    }

    private function getRecLoc()
    {
        return $this->http->FindSingleNode("//*[contains(text(), 'Booking Ref')]", null, true, '/:\s*([A-Z\d]{5,7})/');
    }

    /**
     * @param $str
     *
     * @return int|null
     */
    private function checkAndCorrectDate($str)
    {
        $res = null;

        if ($res = strtotime($str)) {
            return $res;
        } elseif (preg_match('/(.+\s+(?:0|00):\d+)([AP]M)/', $str, $m)) { // 02-Oct-2017, 00:00AM
            $res = strtotime($m[1]);
        }

        return $res;
    }

    private function getNode(\DOMNode $root, $str, $re = null)
    {
        return $this->http->FindSingleNode("(following-sibling::span[contains(., '{$str}')]/following-sibling::text()[1])[1]", $root, true, $re);
    }
}
