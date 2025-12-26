<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\maketrip\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-6748657.eml";

    private $from = 'makemytrip.com';

    private $detects = [
        'MakeMyTrip Booking ID',
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
            if (stripos($body, $detect) !== false && $this->http->XPath->query("//img[contains(@src, 'makemytrip.com/images/logo')]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail()
    {
        $its = [];

        $recLocs = $this->http->FindNodes("//td[contains(text(), 'PNR Number')]/following-sibling::td[1]");
        $recLocs = array_unique($recLocs);

        foreach ($recLocs as $i => $recLoc) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T'];

            $it['RecordLocator'] = $recLoc;

            $it['ReservationDate'] = strtotime($this->http->FindSingleNode("(//td[contains(normalize-space(.), 'Booking Date')])[" . (++$i) . "]", null, true, '/:\s+(.+)/'));

            $it['Passengers'] = $this->http->FindNodes("//tr[contains(., '" . $recLoc . "')]/following-sibling::tr[contains(., 'Passenger Name')]/td[2]");

            $it['TicketNumbers'] = array_unique($this->http->FindNodes("//div[contains(., 'From') and contains(normalize-space(.), 'Flight No.') and not(descendant::div)]/descendant::tr[count(td) > 5]/td[last()][not(contains(., 'E-Ticket'))]"));

            $xpath = "//div[contains(., 'From') and contains(normalize-space(.), 'Flight No.') and not(descendant::div)][" . (++$i) . "]/descendant::tr[count(td) > 5][not(contains(., 'From'))]";
            $roots = $this->http->XPath->query($xpath);

            if ($roots->length === 0) {
                $this->logger->info('Segments not found by xpath: ' . $xpath);

                return false;
            }

            foreach ($roots as $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];
                $re = '/([A-Z\d]{2})-(\d+)\s*([A-Z]{3})\s*([A-Z]{3})\s*(\d{1,2} \w+ \d{4})\s*(\d{1,2}:\d{2}\s*)\w+\s+(\d{1,2} \w+ \d{4})\s*(\d{1,2}:\d{2}\s*)\w+\s+/u';

                if (preg_match($re, $root->nodeValue, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                    $seg['DepCode'] = $m[3];
                    $seg['ArrCode'] = $m[4];
                    $seg['DepDate'] = strtotime($m[5] . ', ' . $m[6]);
                    $seg['ArrDate'] = strtotime($m[7] . ', ' . $m[8]);
                }

                $it['TripSegments'][] = $seg;
            }

            $its[] = $it;
        }

        return $its;
    }
}
