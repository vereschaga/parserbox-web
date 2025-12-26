<?php

namespace AwardWallet\Engine\tapportugal\Email;

class ReservationChange extends \TAccountCheckerExtended
{
    public $mailFiles = "tapportugal/it-10824064.eml, tapportugal/it-10939836.eml";

    public $reSubject = [
        'Reservation Change - Booking Ref',
    ];
    public $reFrom = 'no-reply@amadeus.com';
    public $reBody = ['YOUR NEW FLIGHT(S) INFORMATION'];

    public function parseHtml()
    {
        $it = ['Kind' => "T"];

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[" . $this->starts("Your booking reference") . "]/following::text()[normalize-space()][1])[1]", null, true, "#^\s*[A-Z\d]{5,7}\s*$#");

        // TripNumber
        // ConfirmationNumbers
        // Passengers
        $it['Passengers'][] = $this->http->FindSingleNode("(//text()[" . $this->starts("Dear Mr./Mrs.") . "])[1]", null, true, "#Dear Mr\./Mrs\.\s*(.+),#");

        // AccountNumbers
        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // Fees
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        // TicketNumbers
        // TripSegments
        $xpath = '//text()[normalize-space()="Your itinerary:"]/ancestor::tr[1]/following::tr[string-length(normalize-space())>5 and count(./td)>5]';
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $value = implode("  ", $this->http->FindNodes("./td", $root));

            if (stripos($value, 'Departure date') !== false) {
                continue;
            }
            $seg = [];
            $flight = array_values(array_filter(array_map('trim', explode("  ", $value))));

            if (count($flight) < 7) {
                return null;
            }
            // FlightNumber
            // AirlineName
            if (preg_match("#\b(?<al>[A-Z\d]{2})(?<fl>\d{1,5})\b#", $flight[0], $m)) {
                $seg['FlightNumber'] = $m['fl'];
                $seg['AirlineName'] = $m['al'];
            }
            // DepCode
            // DepName
            if (preg_match("#(?<dname>.+)\s*\((?<dcode>[A-Z]{3})\)#", $flight[1], $m)) {
                $seg['DepCode'] = $m['dcode'];
                $seg['DepName'] = trim($m['dname']);
            } else {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['DepName'] = trim($flight[1]);
            }
            // DepDate
            $seg['DepDate'] = strtotime($this->normalizeDate($flight[3] . ' ' . $flight[4]));

            // ArrCode
            // ArrName
            if (preg_match("#(?<aname>.+)\s*\((?<acode>[A-Z]{3})\)#", $flight[2], $m)) {
                $seg['ArrCode'] = $m['acode'];
                $seg['ArrName'] = trim($m['aname']);
            } else {
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrName'] = trim($flight[2]);
            }

            // ArrDate
            $seg['ArrDate'] = strtotime($this->normalizeDate($flight[5] . ' ' . $flight[6]));

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        $its = $this->parseHtml();

        $result = [
            'emailType'  => 'ReservationChange',
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[contains(text(), 'TAP Portugal') or contains(text(), 'TAP PORTUGAL')]")->length == 0
                || $this->http->XPath->query("//a[contains(@href, 'www.flytap.com')]")->length == 0) {
            return false;
        }
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //11JAN16   22:40
            '#^\s*(\d+)(\D+)(\d+)\s+(\d+:\d+)\s*$#i',
        ];
        $out = [
            '$1 $2 20$3 $4',
        ];
        $date = preg_replace($in, $out, $date);

        return $date;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
