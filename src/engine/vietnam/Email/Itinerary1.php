<?php

namespace AwardWallet\Engine\vietnam\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATETIME_FORMAT = 'j M Y g:ia';
    public $mailFiles = "vietnam/it-1.eml, vietnam/it-1802247.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && (strcasecmp($headers["from"], "no-reply@vietnamairlines.com") == 0);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($this->http->Response['body'],
                        'Thank you for purchasing your ticket with Vietnam Airlines')
                    !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = $this->http->FindPreg('/Reservation code: ([A-Z]{6})/');

        $passengers = $this->http->XPath->query("//tr[contains(., 'Passenger(s)') and not(.//tr)]/following-sibling::tr");
        $passengersSeats = [];

        if ($passengers->length > 0) {
            $i = 0;

            foreach ($passengers as $passenger) {
                if ($name = $this->http->FindSingleNode("./td[1]", $passenger)) {
                    $itineraries['Passengers'][] = $name;

                    if ($seats = $this->http->FindSingleNode("./td[3]", $passenger)) {
                        $passengersSeats[$i] = explode(",", $seats);
                    }
                }
                $i++;
            }
        }

        $segments = $this->http->XPath->query("//tr[contains(., 'Date') and contains(., 'From')]/ancestor::tr/following-sibling::tr//tr[count(td) = 5 and string-length(normalize-space(.)) > 1]");

        if ($segments->length > 0) {
            $itineraries['TripSegments'] = [];
            $i = 0;

            foreach ($segments as $segment) {
                $flight = $this->http->FindSingleNode("./td[4]", $segment);
                $flightData = explode(' ', $flight);
                $tripSegment['AirlineName'] = $flightData[0];
                $tripSegment['FlightNumber'] = $flightData[1];

                $depArrDates = explode('-', $this->http->FindSingleNode("./td[1]", $segment));

                if (count($depArrDates) > 1) {
                    $dates['Dep'] = $depArrDates[0];
                    $dates['Arr'] = $depArrDates[1];
                } else {
                    $dates['Dep'] = $depArrDates[0];
                    $dates['Arr'] = $dates['Dep'];
                }

                foreach (['Dep' => 2, 'Arr' => 3] as $key => $value) {
                    $subj = implode(' ', $this->http->FindNodes('./td[' . $value . ']//text()', $segment));

                    if (preg_match('#^(.*)\s+(\d+:\d+)\s*(.+)?$#s', $subj, $m)) {
                        $tripSegment[$key . 'Code'] = TRIP_CODE_UNKNOWN;
                        $tripSegment[$key . 'Name'] = $m[1];
                        $tripSegment[$key . 'Date'] = strtotime($dates[$key] . ', ' . $m[2]);

                        if (isset($m[3])) {
                            if ($key == 'Dep') {
                                $tripSegment['DepartureTerminal'] = trim(preg_replace("#terminal#i", '', $m[3]));
                            } else {
                                $tripSegment['ArrivalTerminal'] = trim(preg_replace("#terminal#i", '', $m[3]));
                            }
                        }
                    }
                }

                $tripSegment['Cabin'] =
                    $this->http->FindSingleNode("td[5]/descendant::text()[normalize-space()][2]", $segment);

                $seats = "";

                for ($j = 0; $j < count($itineraries['Passengers']); $j++) {
                    $seats .= $passengersSeats[$j][$i] . ", ";
                }
                $tripSegment['Seats'] = trim($seats, ", ");
                $tripSegment['Seats'] = preg_replace("#N/A#", '', $tripSegment['Seats']);
                $tripSegment['Seats'] = array_filter(array_map('trim', explode(",", $tripSegment['Seats'])));

                $tripSegment['Status'] =
                    $this->http->FindSingleNode("td[5]//descendant::text()[normalize-space()][1]", $segment);

                $itineraries['TripSegments'][] = $tripSegment;
                $i++;
            }
        }

        return [
            'emailType'  => 'TravelReservation',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]vietnamairlines\.com/", $from);
    }
}
