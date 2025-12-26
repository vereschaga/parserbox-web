<?php

namespace AwardWallet\Engine\jetairways\Email;

class Itinerary1 extends \TAccountCheckerExtended
{
    public $mailFiles = "jetairways/it-1.eml, jetairways/it-1569424.eml, jetairways/it-2.eml";

    private $subjects = [
        'en' => ['eTicket Itinerary / Receipt (', 'Web Booking eTicket ('],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Jet Airways') !== false
            || preg_match('/@(\w+\.)?jetairways\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Jet Airways') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (
                stripos($textPdf, 'Thank you for choosing Jet Airways') === false
                && stripos($textPdf, 'Jet Airways Toll Free Number') === false
                && stripos($textPdf, '@jetairways.com') === false
                && stripos($textPdf, 'www.jetairways.com') === false
                && stripos($textPdf, 'secure.jetairways.com') === false
            ) {
                continue;
            }

            if (strpos($textPdf, 'Itinerary Details') !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $type = '';
        $parsedData = [];

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
            $htmlPdf = str_replace(['&nbsp;', ' ', '&#160;', '  '], ' ', $htmlPdf);

            if (strpos($htmlPdf, 'Itinerary Details') === false) {
                continue;
            }

            $this->http->SetEmailBody($htmlPdf);
            \PDF::sortNodes($this->http, 3, true);

            $parsedData = $this->parseETicketPDF(); // it-1.eml, it-2.eml

            if (!empty($parsedData['Itineraries'][0]['TripSegments'][0]['FlightNumber'])) {
                $type = '1';

                break;
            }

            $parsedData = $this->parseETicketPDFWithRegexps(); // it-1569424.eml

            if (!empty($parsedData['Itineraries'][0]['TripSegments'][0]['FlightNumber'])) {
                $type = '2';

                break;
            }
        }

        return [
            'emailType'  => 'eTicketPDF' . $type,
            'parsedData' => $parsedData,
        ];
    }

    protected function parseETicketPDFWithRegexps()
    {
        $text = text($this->http->Response['body']);
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = re('#Booking Reference.*?\s+([-A-Z\d]{5,7})\b#i', $text);

        $subj = re("/{$this->opt(['Passenger Details', 'Passenger / Itinerary Details'])}.*{$this->opt(['Itinerary Details', 'Detailed Itinerary'])}/sU", $text);

        if (preg_match_all('#((?:ms|mr).*)\s*\n\s*([\d\w]+)\s+#i', $subj, $m)) {
            $it['Passengers'] = $m[1];
            $it['AccountNumbers'] = $m[2];
        }

        $subj = re('#Itinerary Details\s+Flight.*Allowance\s+(.*)\nNote\n#sU', $text);

        if (preg_match_all('#\d\w\d{3}.*Operated by.*\n#sU', $subj, $m)) {
            $tripSegmentTexts = $m[0];
            $tripSegmentData = [];

            foreach ($tripSegmentTexts as $tripSegmentText) {
                $regex = '#';
                $regex .= '\w+?(?P<FlightNumber>\d+)\s*';
                $regex .= '\n(?P<DepName>.*)\((?P<DepCode>\w{3})\)\s+';
                $regex .= '\w+, (?P<DepDate>\d+ \w+,\s+\d{4})\s+';
                $regex .= '(?P<DepTime>\d+:\d+)\s+hrs\s*';
                $regex .= '\n(?P<ArrName>.*)\((?P<ArrCode>\w{3})\)\s+';
                $regex .= '\w+, (?P<ArrDate>\d+ \w+,\s+\d{4})\s+';
                $regex .= '(?P<ArrTime>\d+:\d+)\s+hrs\s*';
                $regex .= '(?P<Duration>\d+h\s+\d+m)/(?P<Stops>\d+)\s+stops\s+';
                $regex .= '(?P<Cabin>\w+)\s+\((?P<BookingClass>\w)\)\s+';
                $regex .= '.*';
                $regex .= 'Operated by (?P<AirlineName>[^-]+) -\s+';
                $regex .= '(?:Departure:\s+(?P<DepTerminal>.*)\s+)?';
                $regex .= '(?:Arrival:\s+(?P<ArrTerminal>.*)\s+)?';
                $regex .= '#s';

                if (preg_match($regex, $tripSegmentText, $m)) {
                    foreach (['Dep', 'Arr'] as $pref) {
                        $s = str_replace(',', '', $m[$pref . 'Date']) . ', ' . $m[$pref . 'Time'];
                        $m[$pref . 'Date'] = strtotime($s);

                        if (isset($m[$pref . 'Terminal'])) {
                            $m[$pref . 'Name'] .= " ({$m[$pref . 'Terminal']})";
                        }
                    }
                    $keys = ['FlightNumber', 'DepCode', 'DepName', 'DepDate', 'ArrCode', 'ArrName', 'ArrDate',
                        'Duration', 'Stops', 'Cabin', 'BookingClass', 'AirlineName', ];
                    copyArrayValues($tripSegmentData, $m, $keys);
                    $tripSegmentData = array_map('trim', $tripSegmentData);
                    array_walk($tripSegmentData, function (&$value, $key) { $value = preg_replace('#\s+#', ' ', $value); });
                }
                $it['TripSegments'][] = $tripSegmentData;
            }
        }

        return [
            'Itineraries' => [$it],
        ];
    }

    protected function parseETicketPDF()
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $it = ['Kind' => 'T'];

        $locatorNodes = array_values(array_filter(array_merge(
            $http->FindNodes('//text()[contains(., "Booking Reference")]', null, '/Booking Reference[^:]*:\s*(\S+)/ims'),
            $http->FindNodes('(//*[b[contains(., "Booking Reference")]]/../following-sibling::line[1][*[1][b]])[1]')
        )));

        if (!empty($locatorNodes)) {
            $it['RecordLocator'] = $locatorNodes[0];
        }

        $passengers = [];
        $accountNumbers = [];
        $passengerNodeCount = $xpath->query('//*[contains(., "Passenger Name")]
            /following-sibling::*[contains(., "Frequent Flyer")]
            /following-sibling::*[contains(., "eTicket")]
            /ancestor::line[1]/p')->length;
        $passengerNodes = $xpath->query('//*[contains(., "Passenger Name")]
            /following-sibling::*[contains(., "Frequent Flyer")]
            /following-sibling::*[contains(., "eTicket")]
            /ancestor::line[1]
            /following-sibling::line[
                count(p) > 1 and
                (
                    count(following-sibling::line[contains(., "Itinerary Details")]) = 1 or
                    (
                        count(following-sibling::line[contains(., "Itinerary Details")]) = 0 and
                        count(following-sibling::line[
                            contains(., "Date") and
                            contains(., "Dep Time") and
                            contains(., "From")]) = 1
                    )
                )
            ]');

        foreach ($passengerNodes as $passengerNode) {
            $passengers[] = $http->FindSingleNode('./p[1]', $passengerNode);

            if ($xpath->query('./p', $passengerNode)->length == $passengerNodeCount) {
                $accountNumbers[] = $http->FindSingleNode('./p[2]', $passengerNode);
            }
        }

        if (!empty($passengers)) {
            $it['Passengers'] = $passengers;
        }

        if (!empty($accountNumbers)) {
            $it['AccountNumbers'] = $accountNumbers;
        }

        $segmentNodes = $xpath->query('//*[contains(., "Flight")]
            /following-sibling::*[contains(., "Depart")]
            /following-sibling::*[contains(., "Arrive")]
            /following-sibling::*[contains(., "Class")]
            /ancestor::line[1]
            /following-sibling::line[
                p[contains(., "(") and contains(., ")")]/following-sibling::p[contains(., "(") and contains(., ")")] and
                count(following-sibling::line[*[b[contains(text(), "Fare Details")]]]) = 1
        ]');

        foreach ($segmentNodes as $segmentNode) {
            $segment = [];
            $multiline = $xpath->query('./p[contains(., "(") and contains(., ")")][1]/br', $segmentNode)->length > 0;

            if ($multiline) {
                $depNode = $xpath->query('./p[contains(., "(") and contains(., ")")][1]/text()[1]', $segmentNode)->item(0);
            } else {
                $depNode = $xpath->query('./p[contains(., "(") and contains(., ")")][1]', $segmentNode)->item(0);
            }
            $leftBorder = (int) $http->FindSingleNode("./ancestor-or-self::p[1]/@left", $depNode);

            if ($depNode) {
                if ($multiline) {
                    $arrNode = $xpath->query('./ancestor::p[1]/following-sibling::p[contains(., "(") and contains(., ")")][1]/text()[1]', $depNode)->item(0);
                } else {
                    $arrNode = $xpath->query('./following-sibling::p[contains(., "(") and contains(., ")")][1]', $depNode)->item(0);
                }

                if ($arrNode) {
                    foreach ([[$depNode, 'Dep', 1], [$arrNode, 'Arr', 2]] as $point) {
                        [$node, $Dep, $dateNodeShift] = $point;

                        if (preg_match('/(.+?)\s*\((\S+)\)/ims', $node->nodeValue, $matches)) {
                            $segment["{$Dep}Name"] = $matches[1];
                            $segment["{$Dep}Code"] = $matches[2];
                        }

                        if ($multiline) {
                            $date = trim(str_ireplace('hrs', '', $http->FindSingleNode('./following-sibling::br[2]/preceding-sibling::text()[1]', $node) . ' ' . $http->FindSingleNode('./following-sibling::br[2]/following-sibling::text()[1]', $node)));
                        } else {
                            $date = trim(str_ireplace('hrs', '',
                                    $http->FindSingleNode("./ancestor::line[1]/following-sibling::line[1]/p[@left >= {$leftBorder} - 10][{$dateNodeShift}]", $depNode) .
                                    ' ' .
                                    $http->FindSingleNode("./ancestor::line[1]/following-sibling::line[2]/p[@left >= {$leftBorder} - 10][{$dateNodeShift}]", $depNode)
                            ));
                        }

                        if (!empty($date)) {
                            $segment["{$Dep}Date"] = strtotime($date);
                        } elseif ('Arr' === $Dep && isset($segment['DepDate'])) {
                            // DIRTY WORKAROUND
                            $segment["ArrDate"] = $segment['DepDate'];
                        }
                    }

                    if ($multiline) {
                        $segment['Cabin'] = $http->FindSingleNode('./ancestor::p[1]/following-sibling::p[1]', $arrNode);
                    } else {
                        $segment['Cabin'] = $http->FindSingleNode('./following-sibling::p[2]', $arrNode);
                    }
                }

                if ($multiline) {
                    $segment['FlightNumber'] = $http->FindSingleNode("(./following-sibling::line[count(p) = 1]/p[@left < {$leftBorder}])[1]", $segmentNode, true, '/(\d+)$/');
                    $segment['AirlineName'] = $http->FindSingleNode("(./following-sibling::line[count(p) = 1]/p[@left < {$leftBorder}])[1]", $segmentNode, true, '/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/');
                } else {
                    $segment['FlightNumber'] = $http->FindSingleNode("./preceding-sibling::p[last()]", $depNode, true, '/(\d+)$/');
                    $segment['AirlineName'] = $http->FindSingleNode("./preceding-sibling::p[last()]", $depNode, true, '/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/');
                }
                $it['TripSegments'][] = $segment;
            }
        }

        return [
            'Itineraries' => [$it],
        ];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
