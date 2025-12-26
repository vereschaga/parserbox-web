<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\wagonlit\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-6687960.eml";

    private $detects = [
        'Traveller(s) Information',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            'emailType'  => 'AirTicketEn',
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'carlsonwagonlit.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'carlsonwagonlit.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false && $this->http->XPath->query("//img[contains(@src, 'printmytrip.amadeus.com/ascript')]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->getNode2('Booking Reference', '/:\s+([A-Z\d]{5,7})/');

        $it['ReservationDate'] = strtotime($this->getNode2('Issue Date', '/(\d+\s+\w+\s+\d{4})/'));

        $pasngrInfo = $this->http->FindNodes("//tr[contains(., 'Traveller(s) Information') and not(descendant::tr)]/following-sibling::tr[normalize-space(.) != '' and string-length(.) > 10]", null, '/(.+\s+m[irs])/i');
        $it['Passengers'] = $pasngrInfo;

        $xpath = "//img[contains(@src, 'FILE_UPLOAD/AirImage')]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return [$it];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $seg['DepDate'] = strtotime($this->getNode('Departure Date', $root) . ', ' . $this->getNode('Departure:', $root));

            $seg['ArrDate'] = strtotime($this->getNode('Arrival Date', $root) . ', ' . $this->getNode('Arrival:', $root));

            $dep = $this->getNode('Departure:', $root, null, 2);
            $arr = $this->getNode('Arrival:', $root, null, 2);
            $depArr = ['Departure' => $dep, 'Arrival' => $arr];
            array_walk($depArr, function ($val, $key) use (&$seg) {
                if (($v = explode('Terminal', $val)) && (count($v) === 2)) {
                    $seg[substr($key, 0, 3) . 'Name'] = $v[0];
                    $seg[$key . 'Terminal'] = $v[1];
                }
                $seg[substr($key, 0, 3) . 'Name'] = $val;
            });

            $flight = $this->getNode('Flight No', $root);

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $cabin = $this->getNode('Class', $root);

            if (preg_match('/([A-Z])\s*\/\s*(\w+)/', $cabin, $m)) {
                $seg['BookingClass'] = $m[1];
                $seg['Cabin'] = $m[2];
            }

            $seg['Duration'] = $this->getNode('Duration', $root);

            $seg['Aircraft'] = $this->getNode('Aircraft', $root);

            $seg['Seats'] = $this->http->FindSingleNode("descendant::tr[contains(., 'Seat Number')]/following-sibling::tr[1]", $root, true, '/\s+\b([A-Z\d]{1,3})\b\s+/');
            $it['Status'] = $this->http->FindSingleNode("(descendant::span[contains(., 'Flight')])[1]", $root, true, '/((?:confirmed|canceled))/i');

            if (!empty($seg['FlightNumber']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getNode2($str, $re = null)
    {
        return $this->http->FindSingleNode("//td[starts-with(normalize-space(.), '" . $str . "') and not(descendant::td)]", null, true, $re);
    }

    private function getNode($str, \DOMNode $root, $re = null, $td = 1)
    {
        return $this->http->FindSingleNode("descendant::td[contains(., '" . $str . "') and not(descendant::td)]/following-sibling::td[" . $td . "]", $root, true, $re);
    }
}
