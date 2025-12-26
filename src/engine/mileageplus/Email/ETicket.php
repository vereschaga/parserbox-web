<?php

namespace AwardWallet\Engine\mileageplus\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-3368794.eml, mileageplus/it-3368836.eml, mileageplus/it-3865726.eml, mileageplus/it-4.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'ETicket',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//a[contains(text(), "Flight Status") and contains(@href, "united.com")]')->length > 0
            || ($this->http->XPath->query('//a[contains(@href, "united.com")]')->length > 0
             && $this->http->XPath->query('(//th[contains(., "Departure City and Time")] | //td[contains(., "Departure City and Time") and not(.//td)])/parent::tr/following-sibling::tr')->length > 0);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'eTicket Itinerary and Receipt for Confirmation') !== false
            || isset($headers['from']) && stripos($headers['from'], 'unitedairlines@united.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'unitedairlines@united.com') !== false;
    }

    // Subject: (MileagePlus)? eTicket Itinerary and Receipt for Confirmation ABC123

    protected function ParseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => [], 'Passengers' => [], 'TicketNumbers' => [], 'AccountNumbers' => []];
        $root = $this->http->XPath->query('//*[text()[contains(., "Confirmation:")]]')->item(0);

        for ($i = 0; $i < 5; $i++) {
            $parent = $this->http->XPath->query('parent::*', $root)->item(0);

            if (isset($parent->nodeValue) && preg_match('/^Confirmation:\s*([A-Z\d]{6})/', CleanXMLValue($parent->nodeValue), $m)) {
                $it['RecordLocator'] = $m[1];

                break;
            }
            $root = $parent;
        }
        $rows = $this->http->XPath->query('(//th[contains(., "Departure City and Time")] | //td[contains(., "Departure City and Time") and not(.//td)])/parent::tr/following-sibling::tr');

        foreach ($rows as $row) {
            $tds = $this->http->FindNodes('td', $row);

            if (count($tds) === 7 && preg_match('/^\w{3}, (\d+[A-Z]{3}\d+)$/', $tds[0])) {
                $segment = [];
                $date = null;

                if (preg_match('/^\w{3}, (\d+[A-Z]{3}\d+)$/', $tds[0], $m)) {
                    $date = $m[1];
                }

                if (preg_match('/^([A-Z\d]{2})(\d+)$/', $tds[1], $m)) {
                    $segment['AirlineName'] = $m[1];
                    $segment['FlightNumber'] = $m[2];
                }

                if (strlen($tds[2]) < 3) {
                    $segment['BookingClass'] = $tds[2];
                }

                if (isset($date) && preg_match('/^(?<name>.+) \((?<code>[A-Z]{3})[^)]*\)\s*(?<time>\d+:\d+ [AP]M)/', $tds[3], $m)) {
                    $segment['DepCode'] = $m['code'];
                    $segment['DepName'] = $m['name'];
                    $segment['DepDate'] = strtotime($date . ' ' . $m['time']);
                } elseif (isset($date) && preg_match('/^(?<code>[A-Z]{3})\s*(?<time>\d+:\d+ [AP]M)$/', $tds[3], $m)) {
                    $segment['DepCode'] = $m['code'];
                    $segment['DepDate'] = strtotime($date . ' ' . $m['time']);
                } elseif (isset($date) && preg_match('/^(?<name>[A-Z\s,]+)\s*(?<time>\d+:\d+ [AP]M)$/', $tds[3], $m)) {
                    $segment['DepCode'] = TRIP_CODE_UNKNOWN;
                    $segment['DepName'] = $m['name'];
                    $segment['DepDate'] = strtotime($date . ' ' . $m['time']);
                }

                if (isset($date) && preg_match('/^(?<name>.+) \((?<code>[A-Z]{3})[^)]*\)\s*(?<time>\d+:\d+ [AP]M)/', $tds[4], $m)) {
                    $segment['ArrCode'] = $m['code'];
                    $segment['ArrName'] = $m['name'];
                    $segment['ArrDate'] = strtotime($date . ' ' . $m['time']);
                } elseif (isset($date) && preg_match('/^(?<code>[A-Z]{3})\s*(?<time>\d+:\d+ [AP]M)$/', $tds[4], $m)) {
                    $segment['ArrCode'] = $m['code'];
                    $segment['ArrDate'] = strtotime($date . ' ' . $m['time']);
                } elseif (isset($date) && preg_match('/^(?<name>[A-Z\s,]+)\s*(?<time>\d+:\d+ [AP]M)$/', $tds[4], $m)) {
                    $segment['ArrCode'] = TRIP_CODE_UNKNOWN;
                    $segment['ArrName'] = $m['name'];
                    $segment['ArrDate'] = strtotime($date . ' ' . $m['time']);
                }
                // DUBLIN, IRELAND (DUB) (29DEC)
                elseif (isset($date) && preg_match('/^(?<name>.+) \((?<code>[A-Z]{3})[^)]*\)\s*\(\d{1,2}[A-Z]{3}\)/', $tds[4], $m)) {
                    $segment['ArrCode'] = $m['code'];
                    $segment['ArrName'] = $m['name'];
                    $segment['ArrDate'] = MISSING_DATE;
                }

                if (!empty($tds[5])) {
                    $segment['Aircraft'] = $tds[5];
                }

                if (!empty($tds[6])) {
                    $segment['Meal'] = $tds[6];
                }
                $segment['Seats'] = [];
                $it['TripSegments'][] = $segment;
            }
        }
        $rows = $this->http->XPath->query('//tr[not(.//tr) and *[contains(normalize-space(.),"Traveler")] and *[contains(normalize-space(.),"eTicket Number")]]/following-sibling::tr');

        foreach ($rows as $row) {
            $tds = $this->http->FindNodes('td', $row);

            if (count($tds) === 4 && stripos($tds[0], '/') !== false) {
                $it['Passengers'][] = $tds[0];
                $it['TicketNumbers'][] = $tds[1];
                // A3-XXXXX32260
                if (preg_match_all('/\b[A-Z\d]{2}-[X]*\d+/', $tds[2], $m)) {
                    $it['AccountNumbers'][] = $m[0][0];
                }
                $seats = explode('/', $tds[3]);

                if (count($seats) === count($it['TripSegments'])) {
                    for ($i = 0; $i < count($seats); $i++) {
                        if (preg_match('/^\d{1,3}[A-Z]$/', $seats[$i])) {
                            $it['TripSegments'][$i]['Seats'][] = $seats[$i];
                        }
                    }
                }
            }
        }

        foreach ($it['TripSegments'] as &$segment) {
            if (count($segment['Seats']) > 0) {
                $segment['Seats'] = implode(',', $segment['Seats']);
            } else {
                unset($segment['Seats']);
            }
        }
        $root = $this->http->XPath->query('//*[text()[contains(normalize-space(.),"eTicket Total:")]]');

        for ($i = 0; $i < 5 && !isset($it['TotalCharge']) && $root->length > 0; $i++) {
            $total = $this->http->FindSingleNode('parent::*', $root->item(0), true);

            if (!empty($total) && preg_match('/eTicket Total:\s*([,.\d]+)\s*([A-Z]{2,})/', $total, $m)) {
                $it['TotalCharge'] = str_replace(',', '', $m[1]);
                $it['Currency'] = $m[2];
            }
            $root = $this->http->XPath->query('parent::*', $root->item(0));
        }
        $fees = $this->http->FindNodes('//td[contains(normalize-space(.),"Fare Breakdown") and not(.//td)]/descendant::ul/li');
        $it['Fees'] = [];
        $fee = false;

        for ($i = 0; $i < count($fees); $i++) {
            if (strpos($fees[$i], 'Total') !== false) {
                break;
            }

            if (strpos($fees[$i], 'Airfare:') !== false) {
                $fee = true;

                continue;
            }

            if ($fee && preg_match('/^([^:]+):\s*(\d[,.\d]*)$/', $fees[$i], $m)) {
                $it['Fees'][] = ['Name' => $m[1], 'Charge' => str_replace(',', '', $m[2])];
            }
        }
        $it['BaseFare'] = str_replace(',', '', $this->http->FindSingleNode('//*[contains(text(),"The airfare you paid on this itinerary totals")]', null, true, '/The airfare you paid on this itinerary totals:\s*(\d[,.\d]*)/'));
        $it['Tax'] = str_replace(',', '', $this->http->FindSingleNode('//*[contains(text(),"The taxes, fees, and surcharges paid total")]', null, true, '/The taxes, fees, and surcharges paid total:\s*(\d[,.\d]*)/'));
        $spentAwards = $this->http->FindSingleNode('//tr[normalize-space() = "MileagePlus Miles Debited/Award Used:" or normalize-space() = "MileagePlus Miles Debited/ Award Used:"]/following-sibling::tr[1]', null, true, "#^\s*([\d., ]+?)(/|$)#");

        if (!empty($spentAwards)) {
            $it['SpentAwards'] = $spentAwards . ' Miles';
        }

        return [$it];
    }
}
