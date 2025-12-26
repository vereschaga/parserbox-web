<?php

namespace AwardWallet\Engine\srilankan\Email;

class TicketReceipt extends \TAccountChecker
{
    public const DATETIME_FORMAT = 'j M Y g:ia';
    public $mailFiles = "";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from'])
                && isset($headers['subject'])
                && (stripos($headers['from'], 'no-reply@srilankan.com') !== false)
                && (preg_match('/TKT[0-9]{3}\-[0-9]{10}/', $headers['subject']));
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (($pdfs = $parser->searchAttachmentByName('.*pdf')) != null) {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    $http = $this->http;
                    $http->SetBody($html);

                    if (stripos($http->Response['body'], "Electronic Ticket Receipt") !== false
                            && stripos($http->Response['body'], "SriLankan Airlines") !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader("date"));
        $itineraries = [];
        $itineraries['Kind'] = 'T';

        if (($pdfs = $parser->searchAttachmentByName('.*pdf')) === null) {
            return null;
        }

        foreach ($pdfs as $pdf) {
            $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
            $this->http->SetBody($html);

            if (stripos($this->http->Response['body'], "Electronic Ticket Receipt") === false) {
                continue;
            }

            $itineraries['RecordLocator'] =
                $this->http->FindSingleNode("//b[contains(./text(), 'Booking Reference')]/following-sibling::b[1]");

            $itineraries['Passengers'] =
                    [$this->http->FindSingleNode("//b[contains(./text(), 'Passenger')]/
														ancestor::p[1]/following-sibling::p[2]/b[1]")];

            $s = $this->http->FindSingleNode("//b[contains(./text(), 'Total Amount')]/
													ancestor::p[1]/following-sibling::p[1]");

            if (preg_match('/: ([A-Z]{3}) ([0-9]+\.[0-9]{2})/', $s, $matches)) {
                $itineraries['TotalCharge'] = (float) $matches[2];
                $itineraries['Currency'] = $matches[1];
            }

            $s = $this->http->FindSingleNode("//b[contains(text(), 'Fare')]/text()[. = 'Fare']/
													ancestor::p[1]/following-sibling::p[1]",
                                                null,
                                                false,
                                                '/[0-9]+\.[0-9]{2}/');

            if ($s) {
                $fare = (float) $s;
                $itineraries['Tax'] = $itineraries['TotalCharge'] - $fare;
            }

            $tripSegmentNodes =
                    $this->http->XPath->query("//b[contains(./text(), 'Operated by')]/ancestor::p[1]/
																							preceding-sibling::p[13]");

            foreach ($tripSegmentNodes as $tripSegmentNode) {
                $flightInfo = $this->http->FindSingleNode("following-sibling::p[2]", $tripSegmentNode);

                if (preg_match('/([A-Z]{2})([0-9]{2,4})/', $flightInfo, $matches)) {
                    $tripSegment['FlightNumber'] = $matches[2];
                    $tripSegment['AirlineName'] = $matches[1];
                }

                $tripSegment['DepCode'] = TRIP_CODE_UNKNOWN;

                $nodes = $this->http->FindNodes("b/node()[position() >= 3]", $tripSegmentNode);
                $s = join(' ', $nodes);
                $tripSegment['DepName'] = preg_replace('/\s+/', ' ', $s);

                $nodes = $this->http->FindNodes("following-sibling::p[4]/node()", $tripSegmentNode);

                if (sizeof($nodes) > 1) {
                    [$depDateStr, $depTimeStr] = $nodes;
                } else {
                    return;
                }
                $depDateStr = date("Y", $this->date) . " " . preg_replace('/([0-9]{2})([A-Z][a-z]{2})/', '$1 $2', $depDateStr);
                $s = "$depDateStr $depTimeStr";
                $tripSegment['DepDate'] = $this->_buildDate(date_parse_from_format('Y d M H:i', $s));

                $tripSegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                $nodes = $this->http->FindNodes("following-sibling::p[1]/b/node()[position() >= 3]", $tripSegmentNode);
                $s = join(' ', $nodes);
                $tripSegment['ArrName'] = preg_replace('/\s+/', ' ', $s);

                $arrTimeStr = $this->http->FindSingleNode("following-sibling::p[5]/b", $tripSegmentNode) . "\n";
                $tripSegment['ArrDate'] =
                                    $this->_buildDate(date_parse_from_format('Y d M H:i', $depDateStr . $arrTimeStr));

                $tripSegment['BookingClass'] = $this->http->FindSingleNode("following-sibling::p[3]", $tripSegmentNode);

                $itineraries['TripSegments'][] = $tripSegment;
            }
        }

        return [
            'emailType'  => 'TicketReceipt',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]srilankan\.com/", $from);
    }

    private function _buildDate($parsedDate)
    {
        return mktime((isset($parsedDate['hour'])) ? $parsedDate['hour'] : 0, (isset($parsedDate['minute'])) ? $parsedDate['minute'] : 0, 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }
}
