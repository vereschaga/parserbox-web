<?php

namespace AwardWallet\Engine\jetstar\Email;

class PDF extends \TAccountCheckerExtended
{
    public $mailFiles = "jetstar/it-1589156.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && $headers["from"] === "noreplyitineraries@jetstar.com";
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $attach = $parser->searchAttachmentByName('.*pdf');

        if (isset($attach[0])) {
            $pdf = $attach[0];
            $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
            $this->fullText = text($html);

            return preg_match('#Jetstar Airways Cheap Flights#', $this->fullText);
        } else {
            return false;
        }
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $attach = $parser->searchAttachmentByName('.*pdf');

        if (isset($attach[0])) {
            $pdf = $attach[0];
            $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
            $this->fullText = text($html);
        } else {
            return null;
        }

        $itineraries = [];
        $itineraries['Kind'] = 'T';

        $itineraries['RecordLocator'] = re('#Booking reference:\s+([\w\-]+)#i', $this->fullText);

        if (preg_match_all('#\d+\.\s+(.*)#', re('#BAGGAGE.*#s', $this->fullText), $m)) {
            $itineraries['Passengers'] = $m[1];
        }

        $tripSegments = splitter('#(Flight\s+\d:.*)#', re('#(Flight\s+\d+:.*)Your next flight is to#s', $this->fullText));

        $airportCodes = [];

        if (preg_match_all('#(\w{3})\s+-\s+\d+:\d+\s+(?:AM|PM)\s+(\w{3})\s+-\s+\d+:\d+\s+(?:AM|PM)#', re('#BOOKING SUMMARY.*#s', $this->fullText), $m)) {
            $airportCodes = array_slice($m, 1);
        }

        $i = 0;

        foreach ($tripSegments as $tripSegmentText) {
            $segmentData = [];
            $regex = '#';
            $regex .= 'Flight\s+\d+:\s+(?P<AirlineName>[A-Z]{2})\s+(?P<FlightNumber>\d+)\s+';
            $regex .= 'Departing:\s+(?P<DepTime>\d+:\d+)\s+(?:AM|PM),\s+(?P<DepDate>\d+\s+\w+,\s+\d{4})\s+';
            $regex .= '(?P<DepName>.*)\s+';
            $regex .= 'Arrival:\s+(?P<ArrTime>\d+:\d+)\s+(?:AM|PM),\s+(?P<ArrDate>\d+\s+\w+,\s+\d{4})\s+';
            $regex .= '(?P<ArrName>.*)\s+';
            $regex .= '#';

            if (preg_match($regex, $tripSegmentText, $m)) {
                foreach (['Dep' => 0, 'Arr' => 1] as $key => $value) {
                    $m[$key . 'Date'] = strtotime(str_replace(',', '', $m[$key . 'Date']) . ', ' . $m[$key . 'Time']);
                    $m[$key . 'Code'] = $airportCodes[$i][$value];
                }
                $keys = ['AirlineName', 'FlightNumber', 'DepCode', 'DepName', 'DepDate', 'ArrCode', 'ArrName', 'ArrDate'];
                $segmentData = array_merge($segmentData, array_intersect_key($m, array_flip($keys)));
            }
            $itineraries['TripSegments'][] = $segmentData;
            $i++;
        }

        $subj = re('#Total Price\s+(\w+\s+.*)#', $this->fullText);
        $itineraries['TotalCharge'] = cost($subj);
        $itineraries['Currency'] = currency($subj);

        return [
            'emailType'  => 'ReservationPDF',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]jetstar\.com/", $from);
    }
}
