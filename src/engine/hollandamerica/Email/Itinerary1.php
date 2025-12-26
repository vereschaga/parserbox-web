<?php

namespace AwardWallet\Engine\hollandamerica\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "hollandamerica/it-1.eml";
    private $_emails = [
        'noreplyexpressdocs@hollandamerica.com',
    ];
    private $_subjects = [
        'Your Holland America Line Express Docs are now available',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]hollandamerica\.com$/ims', $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        $from = $this->_checkInHeader($headers, 'from', $this->_emails);
        $subject = $this->_checkInHeader($headers, 'subject', $this->_subjects);

        return $from && $subject;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // If forwarded message
        $body = $parser->getPlainBody();
        $from = $this->_checkInBody($body, 'From:', $this->_emails);
        $subject = $this->_checkInBody($body, 'Subject:', $this->_subjects);

        return $from || $subject;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = preg_replace("/^\>?\s+/im", '', $parser->getBody());
        $start = stripos($body, "\nCONSTANCE DEEB");
        $start = stripos($body, "\n", $start + 1) + 1;
        $end = stripos($body, 'To access and issue your Express Docs', $start) - 1;
        $body = trim(substr($body, $start, $end - $start));

        $it = [];
        $it['Kind'] = 'T';
        $it['TripCategory'] = TRIP_CATEGORY_CRUISE;

        preg_match("/^Booking Number:\s+([\w\s\-']+)$/im", $body, $m);

        if (isset($m[1])) {
            $it['RecordLocator'] = trim($m[1]);
        }

        preg_match("/^Ship:\s+([\w\s\-']+)$/im", $body, $m);

        if (isset($m[1])) {
            $it['ShipName'] = trim($m[1]);
        }

        preg_match("/^Category:\s+([\w\s\-']+)$/im", $body, $m);

        if (isset($m[1])) {
            $it['ShipCode'] = trim($m[1]);
        }

        preg_match("/^Stateroom Number:\s+([\w\s\-']+)$/im", $body, $m);

        if (isset($m[1])) {
            $it['RoomNumber'] = trim($m[1]);
        }

        $it['TripSegments'] = [];
        $segments = preg_split("/^Departure\s/im", $body, -1, PREG_SPLIT_NO_EMPTY);
        $cruise = [];

        foreach ($segments as $segment) {
            // Departure
            $DepDate = null;
            $temp = [];
            preg_match("/^Embarkation Port:\s+([\w\s\-']+)$/im", $segment, $m);

            if (isset($m[1])) {
                $temp['Port'] = trim($m[1]);
            }
            preg_match("/^Date:\s+([\w\s\-']+)$/im", $segment, $m);

            if (isset($m[1])) {
                $temp['DepDate'] = strtotime(preg_replace("/[a-z]+/i", " $0 ", trim($m[1])));
                $DepDate = $temp['DepDate'];
            }

            if (!empty($temp)) {
                $cruise[] = $temp;
            }

            // Arrive
            $temp = [];
            preg_match("/^Disembarkation Port:\s+([\w\s\-']+)$/im", $segment, $m);

            if (isset($m[1])) {
                $temp['Port'] = trim($m[1]);
            }
            preg_match("/^Arrive Date:\s+([\w\s\-']+)$/im", $segment, $m);

            if (isset($m[1])) {
                $temp['ArrDate'] = strtotime(preg_replace("/[a-z]+/i", " $0 ", trim($m[1])));
            } elseif (!empty($DepDate)) {
                $temp['ArrDate'] = $DepDate;
            }

            if (!empty($temp)) {
                $cruise[] = $temp;
            }

            unset($DepDate);
        }

        $this->converter = new \CruiseSegmentsConverter();
        $it['TripSegments'] = $this->converter->Convert($cruise);

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    private function _checkInHeader(&$headers, $field, $source)
    {
        if (isset($headers[$field])) {
            foreach ($source as $key => $temp) {
                if (stripos($headers[$field], $temp) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function _checkInBody(&$body, $field, $source)
    {
        $end = 0;

        while ($start = strpos($body, $field, $end)) {
            $end = strpos($body, "\n", $start);

            if ($end === false) {
                $end = strlen($body);
            }
            $header = substr($body, $start, $end - $start);

            foreach ($source as $key => $temp) {
                if (stripos($header, $temp) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
