<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\mileageplus\Email;

class NotificationOfChange extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-7734043.eml";

    private $detects = [
        'A change in your upcoming flight schedule has occurred',
    ];

    private $subjs = [
        'FLIGHT SCHEDULE HAS BEEN UPDATED',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
            'emailType' => $a[count($a = explode('\\', __CLASS__)) - 1] . 'En',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'united.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'united.com') === false) {
            return false;
        }

        foreach ($this->subjs as $subj) {
            if (stripos($headers['subject'], $subj) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src, 'images.triseptsolutions.com/Edocs/UAV_header')]")->length === 0) {
            return false;
        }
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
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->getNode('Reservation Number');

        $it['ReservationDate'] = strtotime($this->getNode('Booking Date'));

        $it['Passengers'] = $this->getNode('Passenger', '/text()[normalize-space(.)]');

        $xpath = "//*[text() = 'New']/ancestor::table[1]/descendant::table[contains(., 'Flight') and contains(., 'Depart')]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return [];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $dep = $this->getNode2('Depart', $root);
            $re = '(?:Depart|Arrive)\s*[:]*\s*(.+?)\s+\(([A-Z]{3})\)\s+(\d+\/\d+\/\d+\s+\d+:\d+\s+[AMPamp]{2})/';

            if (preg_match('/(.+?)\s+' . $re, $dep, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['DepName'] = $m[2];
                $seg['DepCode'] = $m[3];
                $seg['DepDate'] = strtotime($m[4]);
            }

            $arr = $this->getNode2('Arrive', $root);

            if (preg_match('/Flight[:]\s*(\d+)\s+' . $re, $arr, $m)) {
                $seg['FlightNumber'] = $m[1];
                $seg['ArrName'] = $m[2];
                $seg['ArrCode'] = $m[3];
                $seg['ArrDate'] = strtotime($m[4]);
            }

            $node = $this->getNode2(['Seats', 'Class'], $root);

            if (preg_match('/Class:\s+([A-Z])\s+Seats:\s+([A-Z\d\,\s]*)/', $node, $m) || preg_match('/Class:\s+([A-Z])/', $node, $m)) {
                $seg['BookingClass'] = $m[1];

                if (!empty($m[2])) {
                    $seg['Seats'] = stripos($m[2], ',') !== false ? explode(', ', $m[2]) : $m[2];
                }
            }

            $seg['Operator'] = $this->http->FindSingleNode("following-sibling::table[1][contains(., 'Operated By')]", $root, true, '/Operated By\s+(.+?)\s+DBA/');

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    /**
     * @param $str
     * @param null $node
     *
     * @return array|string|null
     */
    private function getNode($str, $node = null)
    {
        if (empty($node)) {
            return $this->http->FindSingleNode("//td[contains(., '" . $str . "')]/following-sibling::td[1]");
        } else {
            return $this->http->FindNodes("//td[contains(., '" . $str . "')]/following-sibling::td[1]" . $node);
        }
    }

    private function getXpath($str, $node = 'normalize-space(.)')
    {
        $res = '';

        if (is_array($str)) {
            $contains = array_map(function ($str) use ($node) {
                return "contains(" . $node . ", '" . $str . "')";
            }, $str);
            $res = implode(' or ', $contains);
        } elseif (is_string($str)) {
            $res = "contains(" . $node . ", '" . $str . "')";
        }

        return $res;
    }

    private function getNode2($str, \DOMNode $root)
    {
        return $this->http->FindSingleNode("descendant::tr[" . $this->getXpath($str) . "]", $root);
    }
}
