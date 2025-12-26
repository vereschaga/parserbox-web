<?php

namespace AwardWallet\Engine\hipmunk\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "hipmunk/it-2160511.eml, hipmunk/it-2160622.eml, hipmunk/it-2160927.eml, hipmunk/it-2163424.eml, hipmunk/it-2163492.eml, hipmunk/it-2163493.eml, hipmunk/it-8624083.eml, hipmunk/it-8650778.eml, hipmunk/it-8671295.eml, hipmunk/it-8671307.eml, hipmunk/it-8727778.eml";

    private $detectBody = [
        'Check out this great flight I found on Hipmunk!',
        'Check Out This Flight',
    ];

    private $date = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());

        return [
            'emailType'  => 'AirTicketEn',
            'parsedData' => ['Itineraries' => $this->parseEmail()],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'hipmunk.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'hipmunk.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $body = preg_replace('/\s+/', ' ', $body);

        foreach ($this->detectBody as $dt) {
            if (stripos($body, $dt) !== false && stripos($body, 'Hipmunk') !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $total = $this->http->FindSingleNode("//div[contains(., 'Per Person') and not(descendant::div)]/preceding-sibling::div[1]");

        if (preg_match('/([\D]+)\s*([\d\.\,]+)/', $total, $m)) {
            $it['TotalCharge'] = $this->normalizePrice($m[2]);
            $it['Currency'] = trim(str_replace(['$', '£', '€'], ['USD', 'GBP', 'EUR'], $m[1]));
        }

        $xpath = "//table[(contains(., 'Departing Flight') or contains(., 'Return Flight')or contains(., 'Flight:')) and not(descendant::table)]/following-sibling::table[contains(., 'PM') or contains(., 'AM') and not(ancestor::*[contains(@style,'display:none;')])]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            if (!empty($this->http->FindSingleNode(".//text()[contains(.,'Layover:')]", $root))) {
                continue;
            }
            $dateNode = $this->http->FindSingleNode('preceding-sibling::*[name() = "div" or name() = "p"][contains(., "Depart")]', $root);
            $dateStr = '';

            if (preg_match('/(\w+)\s+(\d{1,2})/iu', $dateNode, $m) && !empty($this->date)) {
                $dateStr = $m[2] . ' ' . $m[1] . ' ' . date('Y', $this->date);
            }

            $dep = $this->http->FindSingleNode('descendant::td[1]', $root);
            $arr = $this->http->FindSingleNode('descendant::td[last()]', $root);
            $depArr = ['Dep' => $dep, 'Arr' => $arr];
            $re = '/([\w\s]+)\s+\(([A-Z]{3})\)\s*(\d{1,2}:\d{2}\s+[pma]{2})/iu';
            array_walk($depArr, function ($val, $key) use (&$seg, $re, $dateStr) {
                if (preg_match($re, $val, $m) && !empty($dateStr)) {
                    $seg[$key . 'Name'] = $m[1];
                    $seg[$key . 'Code'] = $m[2];
                    $seg[$key . 'Date'] = strtotime($dateStr . ', ' . $m[3]);

                    if ($seg[$key . 'Date'] < $this->date) {
                        $seg[$key . 'Date'] = strtotime("+1year", $seg[$key . 'Date']);
                    }
                } else {
                    $seg['seg'] = $val;
                }
            });

            $flight = $this->http->FindSingleNode('preceding-sibling::table[1]', $root);

            if (preg_match('/\(([A-Z\d]{2})\)\s*(\d+)/', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $aboutFlight = $this->http->FindSingleNode('descendant::td[2]', $root);

            if (preg_match('/(\d{1,2}\w\s+\d{1,2}\w)\s*.*\s*coach on (.+)/ui', $aboutFlight, $m)) {
                $seg['Duration'] = $m[1];
                $seg['Aircraft'] = $m[2];
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }
}
