<?php

namespace AwardWallet\Engine\panorama\Email;

class BPass extends \TAccountChecker
{
    public $mailFiles = "panorama/it-11652212.eml, panorama/it-7156100.eml, panorama/it-7156387.eml, panorama/it-7319662.eml, panorama/it-7351331.eml";

    private $detects = [
        'Спасибо Вам за то, что выбираете авиакомпанию «Международные Авиалинии Украины» и нашу услугу онлайн-регистрации',
    ];

    /** @var \HttpBrowser */
    private $pdf;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0) {
            return [];
        }
        $body = \PDF::convertToHtml($parser->getAttachmentBody(array_shift($pdfs)), \PDF::MODE_COMPLEX);
        $pdfDetects = [
            'Operated by Ukraine International Airlines',
        ];
        $nbsp = chr(194) . chr(160);
        $body = str_replace([$nbsp, '&#160;'], [' ', ' '], $body);

        foreach ($pdfDetects as $detect) {
            if (stripos($body, $detect) !== false) {
                $this->pdf = clone $this->http;
                $this->pdf->SetEmailBody($body);
            } else {
                return [];
            }
        }

        if (preg_match('/name\=\"(.+\.pdf)\"/', $parser->getAttachmentHeader(0, 'Content-Type'), $m)) {
            $this->filename = $m[1];
        }

        return [
            'emailType'  => 'BoardingPassEn',
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'flyuia.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'flyuia.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) > 0) {
            $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

            if (stripos($body, 'Operated by Ukraine International Airlines') !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->getRecLoc();

        $it['Passengers'] = $this->getPassengers();

        $it['TicketNumbers'] = $this->getTicketNumbers();

        $xpath = "//p[normalize-space(text()) = 'Flight'][preceding-sibling::p[contains(., 'Departure Time')]]";
        $roots = $this->pdf->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);
//            return false;
        }

        $seats = [];

        foreach ($roots as $i => $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $dep = $this->pdf->FindSingleNode("following-sibling::p[contains(normalize-space(.), 'From')]/following-sibling::p[1]", $root);
            $arr = $this->pdf->FindSingleNode("(./following-sibling::p[normalize-space(.) = 'To']/following-sibling::p[1])[last()]", $root);
            $depArr = ['Dep' => $dep, 'Arr' => $arr];
            array_walk($depArr, function ($val, $key) use (&$seg) {
                if (preg_match('/(.+)\s*\(([A-Z]{3})\)/', $val, $m)) {
                    $seg[$key . 'Name'] = $m[1];
                    $seg[$key . 'Code'] = $m[2];
                }
            });

            $seg['DepDate'] = $this->getDate($root);

            $flight = $this->pdf->FindSingleNode('following-sibling::p[1]', $root);

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $seatsStr = $this->pdf->FindSingleNode('following-sibling::p[contains(normalize-space(.), "Seat")]/following-sibling::p[1]', $root);

            if (preg_match('#(\d{1,3}[A-Z])#', $seatsStr, $m)) {
                $seats[$seg['AirlineName'] . $seg['FlightNumber']][] = $m[1];
            }

            if (!empty($seg['FlightNumber']) && !empty($seg['DepCode']) && !empty($seg['ArrCode'])) {
                $seg['ArrDate'] = MISSING_DATE;
            }

            $seg['BookingClass'] = $this->pdf->FindSingleNode("following-sibling::p[contains(normalize-space(.), 'Class')]/following-sibling::p[1]", $root);

            $seg['DepartureTerminal'] = $this->pdf->FindSingleNode("preceding-sibling::p[contains(normalize-space(.), 'Terminal')]/following-sibling::p[6]", $root, true, '/\b([A-Z\d]{1,3})\b/');

            $it['TripSegments'][] = $seg;

            $it['TripSegments'] = array_map("unserialize", array_unique(array_map("serialize", $it['TripSegments'])));
        }

        if ($roots->length == 0) {
            $xpath = "//p[normalize-space(text()) = 'Flight']";
            $roots = $this->pdf->XPath->query($xpath);

            if ($roots->length === 0) {
                $this->logger->info('Segments not found by xpath2: ' . $xpath);

                return false;
            }
            $seats = [];

            foreach ($roots as $i => $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                $dep = $this->pdf->FindSingleNode("preceding-sibling::p[contains(normalize-space(.), 'From')]/following-sibling::p[1]", $root);
                $arr = $this->pdf->FindSingleNode("(./following-sibling::p[normalize-space(.) = 'To']/following-sibling::p[1])[1]", $root);
                $depArr = ['Dep' => $dep, 'Arr' => $arr];
                array_walk($depArr, function ($val, $key) use (&$seg) {
                    if (preg_match('/([A-Z]{3})\s*-\s*(.+)/', $val, $m)) {
                        $seg[$key . 'Code'] = $m[1];
                        $seg[$key . 'Name'] = $m[2];
                    }
                });

                $flight = $this->pdf->FindSingleNode('following-sibling::p[1]', $root);

                if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                $doptable = implode("\n", $this->pdf->FindNodes("./following-sibling::p[normalize-space(.) = 'Class']/following-sibling::p[position()<8]", $root));

                if (preg_match("#(?:^|\s+)(?<time>\d{1,2}:\d{2})\s+(?<day>\d+\s+[^\d\s]+)\s+(?<year>\d{2})\s+(?:---|(?<term>.+?))\s+(?<seat>(?:\d{1,3}[A-Z]|INF|SBY))\s+(?<class>[A-Z]{1,2})\s+#", $doptable, $m)) {
                    $seg['DepDate'] = strtotime($m['day'] . ' 20' . $m['year'] . ' ' . $m['time']);

                    if (!empty($m['term'])) {
                        $seg['DepartureTerminal'] = $m['term'];
                    }
                    $seats[$seg['AirlineName'] . $seg['FlightNumber']][] = $m['seat'];
                    $seg['BookingClass'] = $m['class'];
                }

                if (!empty($seg['FlightNumber']) && !empty($seg['DepCode']) && !empty($seg['ArrCode'])) {
                    $seg['ArrDate'] = MISSING_DATE;
                }

                $it['TripSegments'][] = $seg;

                $it['TripSegments'] = array_map("unserialize", array_unique(array_map("serialize", $it['TripSegments'])));
            }
        }

//        $count = count($it['TripSegments']);
        foreach ($it['TripSegments'] as $key => $TripSegment) {
//            if( $count >= 2 && isset($it['TripSegments'][$key+1]['DepCode']) && $TripSegment['DepCode'] !== $it['TripSegments'][$key+1]['DepCode'] )
//                $it['TripSegments'][$key]['Seats'] = array_shift($seats);
//            elseif( $count >= 2 && isset($it['TripSegments'][$key-1]['DepCode']) && $TripSegment['DepCode'] !== $it['TripSegments'][$key-1]['DepCode'] )
//                $it['TripSegments'][$key]['Seats'] = array_shift($seats);
            if (isset($seats[$TripSegment['AirlineName'] . $TripSegment['FlightNumber']])) {
                $it['TripSegments'][$key]['Seats'] = $seats[$TripSegment['AirlineName'] . $TripSegment['FlightNumber']];
            }
        }

        return [$it];
    }

    private function getDate(\DOMNode $root)
    {
        $date = $this->pdf->FindSingleNode("following-sibling::p[contains(normalize-space(.), 'Seat') and ( contains(normalize-space(.), 'Departure Date') or contains(normalize-space(.), 'DepartureDate'))]", $root);
        $depDate = '';

        if (preg_match('/(\d{1,2}\s+\D+\s+\d{2,4})\s*Departure Time\s+(\d{1,2}:\d{2})/i', $date, $m)) {
            $depDate = $m[1] . ', ' . $m[2];
        }

        if (empty($date)) {
            $time = $this->pdf->FindSingleNode("following-sibling::p[normalize-space(.) = 'Departure']/following-sibling::p[1]", $root, true, '/(\d+:\d+)/');

            if (empty($time)) {
                $time = $this->pdf->FindSingleNode("following-sibling::p[contains(normalize-space(.), 'Departure Time')]", $root, true, '/\s+(\d+:\d+)/');
            }

            if (empty($time)) {
                $time = $this->pdf->FindSingleNode("following-sibling::p[contains(normalize-space(.), 'Departure Time') or contains(normalize-space(.), 'DepartureTime')]/following-sibling::p[1]", $root, true, '/\b(\d+:\d+)/');
            }
            $date = $this->pdf->FindSingleNode("following-sibling::p[contains(normalize-space(.), 'Departure Date')]", $root, true, '/(\d{1,2}\s+\D+\s+\d{2,4})/');

            if (empty($date)) {
                $date = $this->pdf->FindSingleNode("following-sibling::p[normalize-space(.) = 'Date']/following-sibling::p[1]", $root, true, '/(\d{1,2}\s+\D+\s+\d{2,4})/');
            }

            if (empty($date)) {
                $date = $this->pdf->FindSingleNode("following-sibling::p[contains(normalize-space(.), 'Departure Date') or contains(normalize-space(.), 'DepartureDate')]/following-sibling::p[1]", $root, true, '/(\d{1,2}\s+\D+\s+\d{2,4})/');
            }
            $dateStr = $date . ' ' . $time;
        }

        if (!empty($dateStr) && preg_match('/(\d{1,2}\s+\D+\s+\d{2,4}\s+\d{1,2}:\d{2})/', $dateStr, $m)) {
            $depDate = $m[1];
        }

        return strtotime($depDate);
    }

    private function getRecLoc()
    {
        return CONFNO_UNKNOWN;
    }

    private function getPassengers()
    {
        return array_values(array_filter(array_unique($this->getNodes('Name'))));
    }

    private function getTicketNumbers()
    {
        return array_values(array_filter(array_unique($this->getNodes('Ticket Number', '/(\d{5,15})/'))));
    }

    private function getNodes($str, $re = null)
    {
        return $this->pdf->FindNodes("//p[contains(normalize-space(text()), '" . $str . "')][1]/following-sibling::p[1]", null, $re);
    }
}
