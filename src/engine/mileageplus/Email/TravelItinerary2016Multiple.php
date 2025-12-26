<?php

namespace AwardWallet\Engine\mileageplus\Email;

class TravelItinerary2016Multiple extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-12896246.eml, mileageplus/it-4736046.eml, mileageplus/it-4736048.eml";

    // Travel Itinerary sent from United Airlines, Inc with multiple record locators
    // 4736048,4736046

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "TravelItinerary2016Multiple",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'Travel Itinerary sent from United Airlines') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//a[contains(@href, 'www.united.com')]")->length > 0
        && $this->http->XPath->query("//text()[contains(normalize-space(.), 'requested that United Airlines send you this itinerary')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]united\./", $from) > 0;
    }

    protected function ParseEmail()
    {
        $result = [];

        foreach ($this->http->XPath->query('//ul[li[contains(., "Flight from")] and li[contains(., "Traveler")]]') as $root) {
            /* @var \DOMNode $root */
            $it = ['Kind' => 'T', 'Passengers' => [], 'TripSegments' => []];
            $root2 = $root;

            for ($i = 0; $i < 5 && !isset($it['RecordLocator']); $i++) {
                $it['RecordLocator'] = $this->http->FindSingleNode('preceding-sibling::*[contains(normalize-space(.), "Confirmation Number")]', $root2, true, '/Confirmation Number:\s*([A-Z\d]{6})/');
                $root2 = $this->http->XPath->query('parent::*', $root2);
            }
            $it['AccountNumbers'] = array_filter($this->http->FindNodes('.//*[contains(text(), "Frequent Flyer:")]/following-sibling::*[1]', $root));

            if (!isset($it['RecordLocator'])) {
                continue;
            }

            foreach ($this->http->XPath->query('li[contains(normalize-space(.), "Flight from")]', $root) as $li) {
                /* @var \DOMNode $li */
                $segment = [];
                $route = $this->http->FindSingleNode('.//*[contains(text(), "Flight from")]/following-sibling::*[1]', $li);

                if (isset($route) && preg_match('/^(.+) \(([A-Z]{3})[^)]*\) to (.+) \(([A-Z]{3})[^)]*\)$/', $route, $m)) {
                    $segment['DepName'] = $m[1];
                    $segment['DepCode'] = $m[2];
                    $segment['ArrName'] = $m[3];
                    $segment['ArrCode'] = $m[4];
                }
                $date = $this->http->FindSingleNode('.//*[contains(text(), "Depart:")]/following-sibling::*[1]', $li);

                if (isset($date) && preg_match('/^(\d{1,2}:\d{1,2} [ap]\.?m\.?).+(\w{3}\.?\s*\d{1,2}\,\s*\d{4})$/', $date, $m) && $unix = strtotime(sprintf('%s %s', $m[2], $m[1]))) {
                    $segment['DepDate'] = $unix;
                }
                $date = $this->http->FindSingleNode('.//*[contains(text(), "Arrive:")]/following-sibling::*[1]', $li);

                if (isset($date) && preg_match('/^(\d{1,2}:\d{1,2} [ap]\.?m\.?).+(\w{3}\.?\s*\d{1,2}\,\s*\d{4})$/', $date, $m) && $unix = strtotime(sprintf('%s %s', $m[2], $m[1]))) {
                    $segment['ArrDate'] = $unix;
                }
                $num = $this->http->FindSingleNode('.//*[contains(text(), "Flight Number:")]/following-sibling::*[1]', $li);

                if (isset($num) && preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,4})$/', $num, $m)) {
                    $segment['AirlineName'] = $m[1];
                    $segment['FlightNumber'] = $m[2];

                    if (count($segment) !== 8) {
                        $this->http->Log('segment not parsed, skipping trip');

                        continue 2;
                    }
                } elseif (isset($num) && preg_match('/^(\d{1,4})$/', $num, $m)) {
                    $segment['FlightNumber'] = $m[1];

                    if (count($segment) !== 7) {
                        $this->http->Log('segment not parsed, skipping trip');

                        continue 2;
                    }
                    $num = $this->http->FindSingleNode('.//*[contains(text(), "Frequent Flyer:")]/following-sibling::*[1]', $root);

                    if (isset($num) && preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])-/', $num, $m)) {
                        $segment['AirlineName'] = $m[1];
                    }
                }

                $data = $this->http->FindSingleNode('.//*[contains(text(), "Aircraft:")]/following-sibling::*[1]', $li);

                if (isset($data)) {
                    $segment['Aircraft'] = $data;
                }
                $data = $this->http->FindSingleNode('.//*[contains(text(), "Meal:")]/following-sibling::*[1]', $li);

                if (isset($data)) {
                    $segment['Meal'] = $data;
                }
                $data = $this->http->FindSingleNode('.//*[contains(text(), "Fare Class:")]/following-sibling::*[1]', $li);

                if (isset($data) && preg_match('/^([\w\s]+) \(([A-Z]+)\)$/', $data, $m)) {
                    $segment['Cabin'] = $m[1];
                    $segment['BookingClass'] = $m[2];
                }
                $segment['Seats'] = [];
                $it['TripSegments'][] = $segment;
            }
            $nodes = $this->http->XPath->query('li[contains(normalize-space(.), "Traveler")]', $root);

            if ($nodes->length > 0) {
                foreach ($this->http->XPath->query('.//*[contains(text(), "Traveler")]/parent::*//ul[li[contains(., "Seats") and not(.//li)]]', $nodes->item(0)) as $ul) {
                    /* @var \DOMNode $ul */
                    $it['Passengers'][] = $this->http->FindSingleNode('preceding-sibling::*[1]', $ul, true, '/^[A-Z ]+$/');
                    $seats = $this->http->FindSingleNode('.//*[contains(text(), "Seats")]/following-sibling::*[1]', $ul);

                    if (isset($seats)) {
                        $arr = explode('|', $seats);

                        if (count($arr) === count($it['TripSegments'])) {
                            foreach ($arr as $i => $seat) {
                                if (preg_match('/^\d+[A-Z]+$/', trim($seat)) > 0) {
                                    $it['TripSegments'][$i]['Seats'][] = trim($seat);
                                }
                            }
                        }
                    }
                }
            }
            $result[] = $it;
        }

        return $result;
    }
}
