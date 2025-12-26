<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\porter\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "porter/it-30828363.eml, porter/it-6931135.eml";

    private $detects = [
        'We apologize for this inconvenience and appreciate your',
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
        return stripos($from, 'flyporter.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'flyporter.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false && stripos($body, 'porter') !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->getNode2('Confirmation number', '/([A-Z\d]{5,7})/');

        $it['Status'] = $this->getNode2('Status');

        $it['Passengers'][] = $this->getNode2('Passenger name');

        $dep = trim(str_replace('Departs', '', $this->getNode('Departs')));
        preg_match_all('/([a-zA-Z\s]+\s*[A-Z]{3}\s*\d+:\d+)/', $dep, $m);

        if (isset($m[1])) {
            $dep = $m[1];
        }

        $arr = trim(str_replace('Arrives', '', $this->getNode('Arrives')));
        preg_match_all('/([a-zA-Z\s]+\s*[A-Z]{3}\s*\d+:\d+)/', $arr, $m);

        if (isset($m[1])) {
            $arr = $m[1];
        }

        $flights = str_replace('Flight number ', '', $this->getNode('Flight number'));

        preg_match_all('/([A-Z]{2}\s+\d+)/', $flights, $m);

        if (count($m[1]) === 0 || !is_array($dep) || !is_array($arr)) {
            return false;
        }
        $flights = $m[1];

        $date = $this->http->FindSingleNode("//tr[contains(., 'Your revised departure time') or contains(., 'Your new flight details')]/following-sibling::tr[1]/descendant::tr[1]/td[1]");

        foreach ($flights as $flight) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $depArr = ['Dep' => array_shift($dep), 'Arr' => array_shift($arr)];
            array_walk($depArr, function ($val, $key) use (&$seg, $date) {
                if (!empty($date) && preg_match('/([A-Za-z\s]+)\s*([A-Z]{3})\s*(\d{1,2}:\d{2})/', $val, $m)) {
                    $seg[$key . 'Name'] = trim($m[1]);
                    $seg[$key . 'Code'] = $m[2];
                    $seg[$key . 'Date'] = strtotime($date . ', ' . $m[3]);
                }
            });
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getNode($str)
    {
        return $this->http->FindSingleNode("(//table[contains(., '" . $str . "') and not(descendant::table)])[1]");
    }

    private function getNode2($str, $re = null, $onlyFirst = true)
    {
        if ($onlyFirst) {
            return $this->http->FindSingleNode("(//*[contains(normalize-space(text()), '" . $str . "')])[1]/following::node()[normalize-space(.)][1]",
                null, true, $re);
        } else {
            return $this->http->FindSingleNode("//*[contains(normalize-space(text()), '" . $str . "')]/following::node()[normalize-space(.)][1]",
                null, true, $re);
        }
    }
}
