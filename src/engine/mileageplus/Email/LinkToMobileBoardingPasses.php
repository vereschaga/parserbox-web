<?php

namespace AwardWallet\Engine\mileageplus\Email;

class LinkToMobileBoardingPasses extends \TAccountChecker
{
    // 3401187 - old
    // 6251056,6229724 - recent
    public $mailFiles = "mileageplus/it-28562786.eml, mileageplus/it-3401187.eml, mileageplus/it-6229724.eml";

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getPlainBody(), 'https://mobile.united.com/CheckIn/MobileeBPCheckInShortCut?txtInput=') !== false
            || stripos($parser->getPlainBody(), 'https://www.united.com/travel/checkin/quickstart.aspx?txtInput=') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'unitedairlines@united.com') !== false
        && isset($headers['subject']) && stripos($headers['subject'], 'Link to mobile boarding pass(es) for confirmation') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'unitedairlines@united.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->detectEmailByBody($parser)) {
            return [];
        }
        $subject = $parser->getSubject();

        if (!empty($subject) && preg_match("#for\s+confirmation\s+([A-Z\d]{5,7})\s*$#", $subject, $m)) {
            $recordLocator = $m[1];
        }
        $body = $parser->getPlainBody();
        $lines = array_filter(explode("\n", $body));
        $result = [];
        $current = null;
        //		$urlSuccess = true;
        //		$its = [];
        foreach ($lines as $line) {
            if (preg_match('/Mobile boarding document for flight [A-Z\d]{2}(\d+) from .+? \(([A-Z]{3})[^)]*\) to .+?\(([A-Z]{3})[^)]*\)/', $line, $m)) {
                $current = ['FlightNumber' => $m[1], 'DepCode' => $m[2], 'ArrCode' => $m[3]];

                continue;
            }

            if (preg_match('/^For ([A-Z\s,]+)$/', $line, $m) > 0 && isset($current)) {
                $current['Passengers'] = explode(', ', trim($m[1]));

                continue;
            }

            if ((preg_match('/https:\/\/mobile\.united\.com\/CheckIn\/MobileeBPCheckInShortCut\?txtInput=.+/', $line, $m)
            || preg_match('/https:\/\/www\.united\.com\/travel\/checkin\/quickstart\.aspx\?txtInput=.+/', $line, $m))
                && isset($current)) {
                $current['BoardingPassURL'] = trim($m[0]);
                //don't parse by url!!!!
                //				if ($urlSuccess == true) {
                //					$urlSuccess = $this->parseUrl($current['BoardingPassURL'], $its, $current);
                //				}
                //				unset($current['ArrCode']);
                //				if (empty($current['RecordLocator']) && !empty($recordLocator)) {
                //					$current['RecordLocator'] = $recordLocator;
                //				}
                $result[] = $current;
                $current = null;
            }
        }
        $return = [
            'parsedData' => [
                'BoardingPass' => $result,
            ],
            'emailType' => 'boardingPass',
        ];
        //		if ($urlSuccess == true) {
        //			$return['parsedData']['Itineraries'] = $its;
        //		}
        return $return;
    }

    public function parseUrl($url, &$its, &$current)
    {
        //don't parse by url!!!!
        return false;
        $this->http->GetURL($url);

        if (empty($this->http->FindSingleNode("(//text()[contains(normalize-space(), 'Arrive')])[1]")) && empty($this->http->FindSingleNode("(//text()[contains(normalize-space(), 'Depart')])[1]"))) {
            return false;
        }
        $it = ['Kind' => 'T'];
        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[normalize-space()='Confirmation:']/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#");

        if (!empty($it['RecordLocator'])) {
            $current['RecordLocator'] = $it['RecordLocator'];
        }
        // Passengers
        $passengers = $this->http->FindSingleNode("//text()[normalize-space()='Boarding Group:']/preceding::text()[normalize-space()][1][contains(.,'/')]");

        if (preg_match("#(.+)/(.+)#", $passengers, $m)) {
            $it['Passengers'][] = trim($m[2]) . ' ' . trim($m[1]);
        }

        if (empty($it['Passengers']) && !empty($current['Passengers'])) {
            $it['Passengers'] = $current['Passengers'];
        }
        // AccountNumbers
        $account = $this->http->FindSingleNode("//text()[normalize-space()='Frequent Flyer:']/following::text()[normalize-space()][1]");
        /* exsample :
         * *****645 PREMIER SILVER / UA *S
         * ---
         */
        if (preg_match("#^\s*([A-Z\d\*]{5,}\b)#", $account, $m)) {
            $it['AccountNumbers'][] = trim($m[1]);
        }

        // TripSegments
        $seg = [];
        // FlightNumber
        $seg['FlightNumber'] = $this->http->FindSingleNode("//text()[normalize-space()='Flight:']/following::text()[normalize-space()][1]", null, true, "#^\s*[A-Z\d]{2}\s*(\d{1,5})\s*$#");
        // AirlineName
        $seg['AirlineName'] = $this->http->FindSingleNode("//text()[normalize-space()='Flight:']/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{2})\s*\d{1,5}\s*$#");

        // DepCode
        if (!empty($current['DepCode'])) {
            $seg['DepCode'] = $current['DepCode'];
        } else {
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        // DepName
        $seg['DepName'] = $this->http->FindSingleNode("//text()[normalize-space()='Departs:']/following::text()[normalize-space()][1]");

        // DepartureTerminal

        $date = $this->http->FindSingleNode("//text()[normalize-space()='Flight Date:']/following::text()[normalize-space()][1]");

        // DepDate
        $time = $this->http->FindSingleNode("//text()[normalize-space()='Depart Time:']/following::text()[normalize-space()][1]");

        if (!empty($date) && !empty($time)) {
            $seg['DepDate'] = strtotime($date . ' ' . $time);
            $current['DepDate'] = $seg['DepDate'];
        }

        // ArrCode
        if (!empty($current['ArrCode'])) {
            $seg['ArrCode'] = $current['ArrCode'];
        } else {
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        }

        // ArrName
        $seg['ArrName'] = $this->http->FindSingleNode("//text()[normalize-space()='Arrives:']/following::text()[normalize-space()][1]");

        // ArrivalTerminal
        // ArrDate
        $time = $this->http->FindSingleNode("//text()[normalize-space()='Arrive Time:']/following::text()[normalize-space()][1]");

        if (!empty($date) && !empty($time)) {
            $seg['ArrDate'] = strtotime($date . ' ' . $time);
        }

        // Aircraft
        // TraveledMiles
        // Cabin
        // BookingClass
        $cabin = $this->http->FindSingleNode("//text()[normalize-space()='Cabin:']/following::text()[normalize-space()][1]");

        if (preg_match("#(.+)\(([A-Z]{1,2})\)#", $cabin, $m)) {
            $seg['Cabin'] = trim($m[1]);
            $seg['BookingClass'] = $m[2];
        } else {
            if ($cabin !== '---') {
                $seg['Cabin'] = trim($cabin);
            }
        }

        // Seats
        $seg['Seats'][] = $this->http->FindSingleNode("//text()[normalize-space()='Seat:']/following::text()[normalize-space()][1]", null, true, "#^\s*(\d{1,3}[A-Z])\s*$#");

        // Duration
        // Meal
        // Smoking
        // Stops
        // Operator

        $finded = false;

        foreach ($its as $key => $itG) {
            if (isset($it['RecordLocator']) && $itG['RecordLocator'] == $it['RecordLocator']) {
                $finded2 = false;

                foreach ($itG['TripSegments'] as $key2 => $value) {
                    if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                            && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                            && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                        $its[$key]['TripSegments'][$key2]['Seats'] = array_unique(array_filter(array_merge($value['Seats'], $seg['Seats'])));
                        $finded2 = true;
                    }
                }

                if ($finded2 == false) {
                    $its[$key]['TripSegments'][] = $seg;
                }
                $finded = true;
            }
        }

        if ($finded == false) {
            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }

        return true;
    }
}
