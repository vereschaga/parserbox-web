<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\mango\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "mango/it-6905483.eml, mango/it-8960336.eml";

    private $detects = [
        'Thank you for booking with Mango',
        'Thank you for choosing Mango as your airline of choice!',
        'For flight changes please go to Manage Travel  on',
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

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'flymango') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'flymango') !== false;
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
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("(//*[contains(., 'Reference Number') and not(descendant::td)])[1]", null, true, '/:\s*([A-Z\d]{5,7})/');

        $total = $this->http->FindSingleNode("//tr[contains(., 'TOTAL') and not(descendant::tr)]");

        if (preg_match('/([A-Z])\s*([\d\.]+)/', $total, $m)) {
            $it['Currency'] = str_replace('R', 'ZAR', $m[1]);
            $it['TotalCharge'] = $m[2];
        }

        $it['Passengers'] = $this->http->FindNodes("//text()[contains(., 'ADULT')]/following::text()[normalize-space()][1]");

        $it['Tax'] = $this->http->FindSingleNode("//td[contains(., 'Total Taxes')]/following-sibling::td[1]", null, true, '/([\d\.]+)/');

        $it['BaseFare'] = $this->http->FindSingleNode("//td[contains(., 'Airfare:') and not(.//td)]/following-sibling::td[1]", null, true, '/([\d\.]+)/');

        $it['Discount'] = $this->http->FindSingleNode("//td[contains(., 'Momentum discount:') and not(.//td)]/following-sibling::td[1]", null, true, '/([\d\.]+)/');

        $xpath = "//img[contains(@src, 'images/mail/depart_black') or contains(@src, 'images/mail/return_black') or contains(@src, 'Images/flight-from')  or contains(@src, 'Images/flight-to')]/ancestor::td[2]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $depArrNames = $this->http->FindSingleNode('descendant::tr[1]', $root);

            if (preg_match('/(.+)\s*-\s*(.+)/', $depArrNames, $m)) {
                $seg['DepName'] = trim($m[1]);
                $seg['ArrName'] = trim($m[2]);
            }

            $date = $this->http->FindSingleNode('descendant::tr[2]', $root);
            $nodes = $this->http->FindSingleNode('descendant::tr[contains(., "Flight") or descendant::img[contains(@src, "arrowRight")]]', $root);

            if (preg_match('/(\d{1,2}:\d{2})\s*.*\s*(\d{2}:\d{2})\s+.+\s+Flight\s+([A-Z\d]{2})\s*(\d+)/', $nodes, $m)) {
                $seg['DepDate'] = strtotime($date . ', ' . $m[1]);
                $seg['ArrDate'] = strtotime($date . ', ' . $m[2]);
                $seg['AirlineName'] = $m[3];
                $seg['FlightNumber'] = $m[4];
            } elseif (preg_match('/(\d{1,2}\s+\w+\s+\d{4})\s*(\d{1,2}:\d{2})\s*.*\s*(\d{2}:\d{2})\s+.+\s+Flight\s+([A-Z\d]{2})\s*(\d+)/', $root->nodeValue, $m)) { // 6905483.eml
                $date = $m[1];
                $seg['DepDate'] = strtotime($date . ', ' . $m[2]);
                $seg['ArrDate'] = strtotime($date . ', ' . $m[3]);
                $seg['AirlineName'] = $m[4];
                $seg['FlightNumber'] = $m[5];
            }

            if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }
}
