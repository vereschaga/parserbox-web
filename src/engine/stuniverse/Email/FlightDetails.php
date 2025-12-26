<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\stuniverse\Email;

class FlightDetails extends \TAccountChecker
{
    public $mailFiles = "stuniverse/it-10042049.eml, stuniverse/it-10190894.eml";

    private $from = '@studentuniverse.com';

    private $prov = 'StudentUniverse';

    private $subject = 'Your StudentUniverse Order';

    private $detects = [
        'You must contact StudentUniverse prior to your flight departure for changes or cancellations',
        'StudentUniverse flight purchase',
        'Your StudentUniverse Order',
    ];

    private $lang = 'en';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $class = explode('\\', __CLASS__);

        return [
            'emailType'  => end($class) . ucfirst($this->lang),
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
        return isset($headers['from']) && stripos($headers['from'], $this->from) !== false
            && isset($headers['subject']) && stripos($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->prov) === false) {
            return false;
        }

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

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'StudentUniverse Reservation Code') or contains(normalize-space(.), 'StudentUniverse reservation code') ]/following-sibling::*[1]");

        $it['Passengers'] = $this->http->FindNodes("//text()[contains(normalize-space(.), 'Traveler:') or contains(normalize-space(.), 'Traveller:')]/following-sibling::*[1]");

        if (empty($it['Passengers'])) {
            // delimiter is unknown
            $it['Passengers'][] = explode(',', $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Travelers:')]/following-sibling::*[1]"));
        }
        $total = $this->http->FindSingleNode("//table[contains(normalize-space(.), 'Total Cost') and not(.//table)]/ancestor::table[1]/following-sibling::table[1]");

        if (preg_match('/(\D)\s*([\d\.\,]+)/', $total, $m)) {
            $it['Currency'] = str_replace(['$'], ['USD'], $m[1]);
            $it['TotalCharge'] = (float) str_replace([','], [''], $m[2]);
        }

        $xpath = "//tr[(contains(normalize-space(.), 'Departure Flight') or contains(normalize-space(.), 'Return Flight')) and not(.//tr)]/following-sibling::tr[1]/descendant::table";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info("Segments not found by xpath: {$xpath}");

            return [];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $info = [
                'flight' => $this->http->FindSingleNode('descendant::td[2]', $root),
                'dep'    => $this->http->FindSingleNode('descendant::td[3]', $root),
                'arr'    => $this->http->FindSingleNode('descendant::td[4]', $root),
                'dur'    => $this->http->FindSingleNode('descendant::td[5]', $root),
            ];

            if (preg_match('/(.+)\s+Flight\s+(\d+)\s/i', $info['flight'], $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match('/Eqpt\.\s+type:\s+(.+)$/i', $info['flight'], $m)) {
                $seg['Aircraft'] = $m[1];
            }

            $re = '/(?:departs|arrives)\s+([A-Z]{3})\s+(\d+:\d+[ap]m)\s+\w+,\s+(\w+)\s+(\d{1,2}),\s+(\d{2,4})/i';

            if (preg_match($re, $info['dep'], $m)) {
                $seg['DepCode'] = $m[1];
                $seg['DepDate'] = strtotime($m[4] . ' ' . $m[3] . ' ' . $m[5] . ', ' . $m[2]);
            }

            if (preg_match($re, $info['arr'], $m)) {
                $seg['ArrCode'] = $m[1];
                $seg['ArrDate'] = strtotime($m[4] . ' ' . $m[3] . ' ' . $m[5] . ', ' . $m[2]);
            }

            $seg['Duration'] = $info['dur'];

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }
}
