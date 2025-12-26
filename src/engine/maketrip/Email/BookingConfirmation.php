<?php

namespace AwardWallet\Engine\maketrip\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-1.eml, maketrip/it-13861224.eml, maketrip/it-1723863.eml, maketrip/it-6190178.eml, maketrip/it-6341181.eml, maketrip/it-8558169.eml";

    private $detects = [
        'Booking Confirmation',
        'Flight Reschedule Confirmation',
        //		'Itinerary and Reservation Details',
    ];

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'makemytrip.com') !== false && (
            stripos($headers['subject'], 'MakeMyTrip Confirmation for Booking') !== false
            || stripos($headers['subject'], 'MakeMyTrip New Flight Timing for Booking ID') !== false);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'makemytrip.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $anchor = $this->http->XPath->query('//node()[contains(.,"reply@makemytrip.com") or contains(.,"booking from MakemyTrip.com")]')->length > 0;
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false && $anchor) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'BookingConfirmationEn',
        ];
    }

    protected function priceNormalize($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function ParseEmail()
    {
        $it = [];

        $it['Kind'] = 'T';

        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $xpathFragment = '//text()[normalize-space(.)="Booking Confirmation"]/ancestor::td[1]';
        $root = $this->http->XPath->query($xpathFragment);

        if ($root->length > 0) {
            $root = $root->item(0);
        } else {
            $root = null;
        }

        $it['TripNumber'] = $this->http->FindSingleNode('.//span[.//text()[starts-with(normalize-space(.),"MakeMyTrip Booking ID")] and .//*[name(.)="strong" or name(.)="b"]]', $root, true, '/(?:\s+-\s+|-)([A-Z\d]+)\s*$/');

        if (empty($it['TripNumber'])) {
            $it['TripNumber'] = $this->http->FindSingleNode('//span[.//text()[starts-with(normalize-space(.),"Confirmation ID")]]', null, true, '/(?:\s+-\s+|-)([A-Z\d]+)\s*$/', $root);
        }
        $it['ReservationDate'] = strtotime($this->http->FindSingleNode('.//span[./text()[starts-with(normalize-space(.),"Booking Date")]]', $root, true, '/(\d{1,2}\s+[^\d\s]+\s+\d{2,4})\s*$/'));

        $it['TicketNumbers'] = $this->http->FindNodes("//tr[contains(., 'Ticket No.')]/following-sibling::tr[1]/td[last()]", null, '/([A-Z\d]{6,})/');

        $confirmedBlocks = $this->http->XPath->query('//node()[contains(text(),"Your booking is confirmed")]');

        if ($confirmedBlocks->length > 0) {
            $it['Status'] = 'confirmed';
        }

        $it['Passengers'] = array_values(array_unique($this->http->FindNodes('//tr[./td[starts-with(normalize-space(.),"Passenger")] and ./td[normalize-space(.)="Type"] and not(.//tr)]/following-sibling::tr[not(./preceding-sibling::tr[.//hr]) and not(.//tr)]/td[string-length(normalize-space(.))>5][1]')));

        $it['TripSegments'] = [];
        $pattern1 = '/\s*(\S.+\S)\s*\(\s*([A-Z]{3})\s*\)/';
        $pattern2 = '/(\d{1,2}\s+[^,\d\s]{3,9}\s+\d{2,4}\s*[,\s]+\d{1,2}:\d{2})/';
        $pattern3 = '/[Tt]erminal\s*([)(A-z\d\s]+)/';
        $pattern4 = '/(\w+)\s+(\d{1,2})\,\s*(\d{2,4})\s*\,\s*(\d{1,2}:\d{2})/';

        $flightRows = $this->http->XPath->query('//text()[contains(.,"Departure")]/ancestor::tr[1][contains(.,"Arrival")]/ancestor::table[contains(., "Departure") and contains(., "Duration")][1]');

        foreach ($flightRows as $flightRow) {
            $seg = [];

            $stops = $this->http->FindSingleNode('.//tr[normalize-space(.)!=""][1]', $flightRow);

            if (preg_match('/Non(?:\s*-\s*|)Stop\s+Flight/i', $stops)) {
                $seg['Stops'] = 0;
            } elseif (($stops = $this->http->FindSingleNode("descendant::tr[contains(., 'Stops')]/following-sibling::tr[1]/td[last()-1]", $flightRow, true, '/(\d)/'))) {
                $seg['Stops'] = $stops;
            }

            $nameAndCode_dep = $this->http->FindSingleNode('(.//tr/td[contains(.,"(") and contains(.,")") and not(.//td)][1])[1]', $flightRow);

            if (preg_match($pattern1, $nameAndCode_dep, $matches)) {
                $seg['DepName'] = $matches[1];
                $seg['DepCode'] = str_replace(["ROM", "NYC"], [TRIP_CODE_UNKNOWN, TRIP_CODE_UNKNOWN], $matches[2]);
            }

            $nameAndCode_arr = $this->http->FindSingleNode('(.//tr/td[contains(.,"(") and contains(.,")") and not(.//td)][2])[1]', $flightRow);

            if (preg_match($pattern1, $nameAndCode_arr, $matches)) {
                $seg['ArrName'] = $matches[1];
                $seg['ArrCode'] = str_replace(["ROM", "NYC"], [TRIP_CODE_UNKNOWN, TRIP_CODE_UNKNOWN], $matches[2]);
            }

            $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";

            $leftCells = $this->http->XPath->query(".//tr/td[{$ruleTime} and not(.//td)][1]/preceding-sibling::td", $flightRow);

            if ($leftCells->length > 0) {
                $startingPoint = $leftCells->length + 1;
                $terminalDep = $this->http->FindSingleNode('.//tr[./td[starts-with(normalize-space(.),"Terminal") and not(.//td)]]/td[' . $startingPoint . ']', $flightRow);

                if (preg_match($pattern3, $terminalDep, $matches)) {
                    $seg['DepartureTerminal'] = $matches[1];
                }
                $terminalArr = $this->http->FindSingleNode('.//tr[./td[starts-with(normalize-space(.),"Terminal") and not(.//td)]]/td[' . ($startingPoint + 1) . ']', $flightRow);

                if (preg_match($pattern3, $terminalArr, $matches)) {
                    $seg['ArrivalTerminal'] = $matches[1];
                }
            }

            $dateAndTime_dep = $this->http->FindSingleNode(".//tr/td[{$ruleTime} and not(.//td)][1]", $flightRow);

            if (preg_match($pattern2, $dateAndTime_dep, $matches)) {
                $seg['DepDate'] = strtotime($matches[1]);
            } elseif (preg_match($pattern4, $dateAndTime_dep, $m)) {
                $seg['DepDate'] = strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3] . ', ' . $m[4]);
            }

            $dateAndTime_arr = $this->http->FindSingleNode(".//tr/td[{$ruleTime} and not(.//td)][2]", $flightRow);

            if (preg_match($pattern2, $dateAndTime_arr, $matches)) {
                $seg['ArrDate'] = strtotime($matches[1]);
            } elseif (preg_match($pattern4, $dateAndTime_arr, $m)) {
                $seg['ArrDate'] = strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3] . ', ' . $m[4]);
            }

            $duration = $this->http->FindSingleNode('.//tr/td[starts-with(normalize-space(.),"Duration") and contains(.,":") and not(.//td)]', $flightRow);

            if (empty($duration)) {
                $duration = $this->http->FindSingleNode("descendant::tr[contains(., 'Duration')]/following-sibling::tr[1]/td[last()]", $flightRow, true, '/(\d{1,2}\s*:\s*\d{2}\s*[hrs]{1,3})/i');
            }

            if (preg_match('/[Dd]uration\s*:\s*(\b[hrm\d\s]{2,8})/', $duration, $matches) || preg_match('/(\d{1,2}\s*:\s*\d{2}\s*[hrs]{1,3})/i', $duration, $matches)) {
                $seg['Duration'] = $matches[1];
            }

            $cabin = $this->http->FindSingleNode('.//tr/td[starts-with(normalize-space(.),"Cabin") and contains(.,":") and not(.//td)]', $flightRow);

            if (preg_match('/[Cc]abin\s*:\s*([\w\s]+)/', $cabin, $matches)) {
                $seg['Cabin'] = $matches[1];
            }

            $flight = implode(" ", $this->http->FindNodes('(.//tr/td[1][normalize-space(.)!=""])[position() > 1]', $flightRow));

            if (preg_match('/\s*([A-Z\d]{2})\s*[-\#]?\s*(\d+)\s*/', $flight, $matches)) {
                $seg['AirlineName'] = trim($matches[1]);
                $seg['FlightNumber'] = $matches[2];
            } elseif (preg_match('/\s*([A-Z\s]+)\s*[-\#]?\s*(\d+)\s*/', $flight, $matches)) {
                $seg['AirlineName'] = trim($matches[1]);
                $seg['FlightNumber'] = $matches[2];
            } else {
                $seg['AirlineName'] = $this->http->FindSingleNode('.//tr/td[starts-with(normalize-space(.),"Duration") and contains(.,":") and not(.//td)]/preceding-sibling::td[string-length(normalize-space(.))>2][last()]', $flightRow);
                $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        if ($payment = $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Total Amount Charged") and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1]')) {
            $it['Currency'] = $this->currency($payment);
            $it['TotalCharge'] = $this->amount($payment);
        }

        return $it;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        if (($s = $this->re("#(\d[\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
            '₹'  => 'INR',
            'Rs.'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
