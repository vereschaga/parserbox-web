<?php

namespace AwardWallet\Engine\thaiair\Email;

class BoardingPass extends \TAccountCheckerExtended
{
    public $mailFiles = "thaiair/it-2010347.eml, thaiair/it-2011750.eml, thaiair/it-78274610.eml, thaiair/it-7999205.eml";

    private $lang = '';

    private $detects = [
        'Thank you for choosing Thai Airways',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfSegments = [];
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'THAI/THAI Smile') !== false
                && stripos($textPdf, 'NAME OF PASSENGER') !== false
            ) {
                $pdfSegments = array_merge($pdfSegments, $this->parsePdfSegment($textPdf));
            }
        }

        return [
            'parsedData' => ['Itineraries' => $this->parseEmail($pdfSegments)],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'thaiairways.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'thaiairways.com') !== false;
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

    private function parseEmail($pdfSegments)
    {
        $rls = array_filter(array_unique($this->http->FindNodes("//span[contains(., 'Booking Reference')]/following-sibling::span[1]")));

        $passengers = $this->http->FindNodes("//span[contains(., 'Passenger')]/following-sibling::span[1]");

        $xpath = "//tr[descendant::hr and following-sibling::tr[contains(., 'Passenger')] and preceding-sibling::tr[contains(., 'Booking Details')]]/following-sibling::tr[following-sibling::tr[descendant::hr]][not(contains(., 'Baggage Information'))]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }
        $segments = [];
        $i = 0;

        foreach ($roots as $root) {
            $segments[$i][] = $root->nodeValue;

            if ($this->http->XPath->query('following-sibling::tr[1][descendant::hr]', $root)->length !== 0) {
                $i++;
            }
        }

        foreach ($segments as $i => $segment) {
            $segments[$i] = implode(' ', $segment);
        }

        $airs = [];

        foreach ($rls as $rl) {
            foreach ($segments as $segment) {
                if (stripos($segment, $rl) !== false) {
                    $airs[$rl][] = $segment;
                }
            }
        }

        $its = [];

        foreach ($airs as $rl => $segments) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T'];
            $it['RecordLocator'] = $rl;
            $it['Passengers'] = array_filter(array_unique($passengers));

            foreach ($segments as $segment) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                if (preg_match('#Flight\s*:\s*(\w{2})(\d+)\s+-\s+(Q|Business|Economy|H|V)#i', $segment, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];

                    if (strlen($m[3]) === 1) {
                        $seg['BookingClass'] = $m[3];
                    } else {
                        $seg['Cabin'] = $m[3];
                    }
                }

                foreach (['Departure' => 'From:', 'Arrival' => 'To:'] as $key => $value) {
                    //					$seg[substr($key,0,3) . 'Code'] = TRIP_CODE_UNKNOWN;

                    $regex = '#' . $value . '\s*(.*?)(?:terminal\s+([A-Z\d]{1,3}))?\s*(\d{2}\s+\w+\s+\d+)\s+-\s+(\d+:\d+)(?:\s*terminal\s+([A-Z\d]{1,3}))?#is';

                    if (preg_match($regex, $segment, $m)) {
                        $seg[substr($key, 0, 3) . 'Name'] = nice($m[1], ',');
                        $seg[$key . 'Terminal'] = $m[2] ? $m[2] : null;
                        $seg[substr($key, 0, 3) . 'Date'] = strtotime($m[3] . ', ' . $m[4]);
                    }
                }

                if (!empty($seg['AirlineName']) && !empty($seg['FlightNumber']) && !empty($seg['DepDate'])) {
                    foreach ($pdfSegments as $pSeg) {
                        if (!empty($pSeg['airlineName']) && !empty($pSeg['flightNumber']) && !empty($pSeg['day']) && !empty($pSeg['month'])
                            && $seg['AirlineName'] == $pSeg['airlineName'] && $seg['FlightNumber'] == $pSeg['flightNumber']
                            && strtotime('00:00', $seg['DepDate']) === strtotime($pSeg['day'] . ' ' . $pSeg['month'] . ' ' . date("Y", $seg['DepDate']))
                        ) {
                            $seg['DepCode'] = $pSeg['depCode'] ?? null;
                            $seg['ArrCode'] = $pSeg['arrCode'] ?? null;
                            $seg['Seats'][] = $pSeg['seat'] ?? null;
                            $it['AccountNumbers'][] = $pSeg['account'] ?? null;
                            $it['TicketNumbers'][] = $pSeg['ticket'] ?? null;
                        }
                    }
                }

                if (empty($seg['DepCode']) && !empty($seg['DepName'])) {
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                if (empty($seg['ArrCode']) && !empty($seg['ArrName'])) {
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                $it['TripSegments'][] = $seg;
            }
            $it['AccountNumbers'] = array_filter(array_unique($it['AccountNumbers']));
            $it['TicketNumbers'] = array_filter(array_unique($it['TicketNumbers']));

            $its[] = $it;
        }

        return $its;
    }

    private function parsePdfSegment($textPdf)
    {
        $result = [];

        $segments = $this->split("/(This is not a boarding pass|BOARDING PASS\n)/", $textPdf);

        foreach ($segments as $stext) {
            $seg = [];

            if (preg_match("/ {3,}FROM \/ *.*\/ *([A-Z]{3})\n/", $stext, $m)) {
                $seg['depCode'] = $m[1];
            }

            if (preg_match("/ {3,}TO \/ *.*\/ *([A-Z]{3})\n/", $stext, $m)) {
                $seg['arrCode'] = $m[1];
            }

            if (preg_match("/\n *NAME OF PASSENGER *\/.*[ ]{3,}FLIGHT *\/.*\n *(?<name>.+) {3,}(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<fn>\d{1,5}) *\/ *(?<day>\d{1,2})(?<month>[A-Z]+)\s*\n/u", $stext, $m)) {
                $seg['traveller'] = $m['name'];
                $seg['airlineName'] = $m['al'];
                $seg['flightNumber'] = $m['fn'];
                $seg['day'] = $m['day'];
                $seg['month'] = $m['month'];
            }

            if (preg_match("/\n *SEAT *\/ ?.*?(\d{1,3}[A-Z]) {3,}/", $stext, $m)) {
                $seg['seat'] = $m[1];
            }

            if (preg_match("/\n *ETKT(\d{10,})(?: {3,}|\n)/", $stext, $m)) {
                $seg['ticket'] = $m[1];
            }

            if (preg_match("/\n *SEAT *\/.*(?:\n+ {40,}.*){0,3}\n+ {0,40}TG\*[A-Z]* ([A-Z\d]{5,}) .*(?:\n+ {40,}.*){0,3}\n+ *ETKT(\d{10,})/", $stext, $m)) {
                $seg['account'] = $m[1];
            }
            $result[] = $seg;
        }

        return $result;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
