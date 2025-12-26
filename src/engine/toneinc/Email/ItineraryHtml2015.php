<?php

namespace AwardWallet\Engine\toneinc\Email;

class ItineraryHtml2015 extends \TAccountChecker
{
    public $mailFiles = "toneinc/it-6332247.eml";

    private $lang = '';
    private $subject = ['/MESSER \d+[A-Z]+ /'];
    private $body = [
        'en' => ['Please review this itinerary for accuracy.'],
    ];

    private static $dict = [
        'en' => [],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && isset($headers['from'])
                && stripos($headers['from'], '@traveloneinc') !== false && $this->detectMatch($headers['subject'], $this->subject);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Travel One, Inc') !== false && $this->detect($parser->getHTMLBody(), $this->body);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'traveloneinc') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $this->lang = $this->detect($parser->getHTMLBody(), $this->body);

        if (empty($this->lang)) {
            return $its;
        }

        // Air
        if (stripos($parser->getHTMLBody(), 'Departure City:')) {
            $its[] = $this->parseAir();
        }
        // Car
        foreach ($this->http->XPath->query("//text()[contains(., 'Pick-up Date:')]/ancestor::table[3]") as $root) {
            $its[] = $this->parseCar($root);
        }

        $total = $this->http->FindSingleNode("//text()[contains(., 'Total Fare:')]/ancestor::td[1]");

        if (preg_match('/([A-Z]{3})\s*(.+)/', $total, $matches)) {
            $total = [
                'Amount'   => (float) preg_replace('/[^\d.]+/', '', $matches[2]),
                'Currency' => $matches[1],
            ];
        }

        $its = $this->groupBySegments($its);

        return [
            'emailType'  => 'ItineraryHtml2015' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its, 'TotalCharge' => $total],
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    //========================================
    // Auxiliary methods
    //========================================

    /**
     * TODO: Beta!
     *
     * @version v1.2
     *
     * @param type $reservations
     *
     * @return array
     */
    protected function groupBySegments($reservations)
    {
        $newReservations = [];

        foreach ($reservations as $reservation) {
            if ($reservation['Kind'] === 'T') {
                $newSegments = [];

                foreach ($reservation['TripSegments'] as $segment) {
                    if (empty($segment['RecordLocator']) && isset($reservation['TripNumber'])) {
                        // when there is no locator in the segment
                        $newSegments[$reservation['TripNumber']][] = $segment;
                    } elseif (!empty($segment['RecordLocator'])) {
                        $r = $segment['RecordLocator'];
                        unset($segment['RecordLocator']);
                        $newSegments[$r][] = $segment;
                    }
                }

                foreach ($newSegments as $key => $segment) {
                    $reservation['RecordLocator'] = $key;
                    $reservation['TripSegments'] = $segment;
                    $newReservations[] = $reservation;
                }
            } else {
                $newReservations[] = $reservation;
            }
        }

        return $newReservations;
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    /**
     * Are case sensitive. Example:
     * <pre>
     * var $reSubject = [
     * 'en' => ['Reservation Modify'],
     * ];
     * </pre>.
     *
     * @param type $haystack
     * @param type $arrayNeedle
     *
     * @return type
     */
    private function detect($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $lang => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $lang;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $lang;
            }
        }
    }

    private function detectMatch($subject, array $patterns)
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $subject)) {
                return true;
            }
        }
    }

    private function parseAir()
    {
        $result = ['Kind' => 'T'];
        $result['TripNumber'] = $this->http->FindSingleNode("//text()[normalize-space()='Passenger Names']/ancestor::tr[1]/preceding::text()[normalize-space()][position()<15][normalize-space()='Agency Booking Confirmation Number:']/ancestor::*[1]", null, false, '/:\s*([A-Z\d]{5,6})$/');

        $passengers = [];
        $passengerRows = $this->http->XPath->query("//text()[normalize-space()='Passenger Names']/ancestor::tr[1]/following::tr[string-length(normalize-space())>1]");

        foreach ($passengerRows as $row) {
            if (preg_match("/^\s*([[:upper:]][\/[:upper:] ]*?[[:upper:]])\s*(?i)(?:- SALES|$)/u", $row->nodeValue, $m)) {
                // MESSER/CHRISTOPHER CHARLES - SALES FBRW
                $passengers[] = $m[1];
            } else {
                break;
            }
        }

        if (count($passengers)) {
            $result['Passengers'] = array_unique($passengers);
        }

        $ticketNumbers = [];

        foreach ($this->http->XPath->query("//text()[contains(., ' Flight Number ')]/ancestor::table[2]") as $root) {
            $i = [];
            $flight = $this->http->FindSingleNode(".//text()[contains(., 'Flight Number')]/ancestor::tr[1]", $root);
            $i += $this->matchSubpattern('/(?<AirlineName>[\w\s]+) - Flight Number (?<FlightNumber>\d+)\s+Confirmation:\s*(?<RecordLocator>[A-Z\d]{5,6})/', $flight);

            if (!empty($i['AirlineName'])
                && count($tickets = array_filter($this->http->FindNodes("//text()[{$this->contains([$i['AirlineName'], strtoupper($i['AirlineName']), ucfirst(strtolower($i['AirlineName'])), ucwords(strtolower($i['AirlineName']))])} and {$this->contains('Ticket:')}]/following::text()[normalize-space()][1]", null, "/^\d{3}[- ]*\d{5,}[- ]*\d{1,2}$/")))
            ) {
                $ticketNumbers = array_merge($ticketNumbers, $tickets);
            }

            // Departure
            $i['DepDate'] = strtotime($this->xpathShort('Departure:', $root, '/:\s*(.+)/'), false);
            $i += $this->matchSubpattern('/:\s*(?<DepName>.+?)\s*\((?<DepCode>[A-Z]{3})\)/', $this->xpathShort('Departure City:', $root));

            // Arrival
            $i['ArrDate'] = strtotime($this->xpathShort('Arrival:', $root, '/:\s*(.+)/'), false);
            $i += $this->matchSubpattern('/:\s*(?<ArrName>.+?)\s*\((?<ArrCode>[A-Z]{3})\)/', $this->xpathShort('Arrival City:', $root));

            // Terminals
            $i['DepartureTerminal'] = $this->xpathShort('Departing Terminal:', $root, '/:\s*(\w+)/');
            $i['ArrivalTerminal'] = $this->xpathShort('Arrival Terminal:', $root, '/:\s*(\w+)/');

            $i += $this->matchSubpattern('/:\s*(?<BookingClass>[A-Z]) - (?<Cabin>\w+)/', $this->xpathShort('Class of Service:', $root));
            $i['Aircraft'] = $this->xpathShort('Equipment:', $root, '/:\s*(.+)/');
            $i['Duration'] = $this->xpathShort('Travel Time:', $root, '/:\s*(.+)/');
            $i['TraveledMiles'] = $this->xpathShort('Miles:', $root, '/:\s*(.+)/');

            $operator = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'Operated By')]", $root, true, "/Operated By\s*(\w[\w\s]+)$/");

            if ($operator) {
                $i['Operator'] = $operator;
            }

            $seats = $this->http->FindNodes("descendant::text()[contains(normalize-space(),'Seat Assignments:')]/ancestor::h1[1]/descendant::text()[normalize-space()]", $root, '/ - (\d{1,5}[A-Z])$/');
            $seats = array_filter($seats);

            if (count($seats)) {
                $i['Seats'] = $seats;
            }

            $result['TripSegments'][] = $i;
        }

        if (count($ticketNumbers)) {
            $result['TicketNumbers'] = array_unique($ticketNumbers);
        }

        $accountNumbers = [];
        $accountNumberRows = $this->http->XPath->query("//text()[normalize-space()='Frequent Flyer Info']/ancestor::tr[1]/following::tr[string-length(normalize-space())>1]");

        foreach ($accountNumberRows as $row) {
            if (preg_match("/^\s*[A-z][A-z ]*[A-z][ ]*(\d{7,})\s*$/", $row->nodeValue, $m)) {
                // DL DL 9355404949
                $accountNumbers[] = $m[1];
            } else {
                break;
            }
        }

        if (count($accountNumbers)) {
            $result['AccountNumbers'] = array_unique($accountNumbers);
        }

        return $result;
    }

    private function parseCar(\DOMElement $root)
    {
        $result = ['Kind' => 'L'];

        $car = $this->http->FindSingleNode(".//text()[contains(., 'Confirmation:')]/ancestor::tr[1]", $root);
        $result += $this->matchSubpattern('/(?<RentalCompany>[\w\s]+)\s+Confirmation:\s*(?<Number>[A-Z\d]+)\b/', $car);
        $result['PickupDatetime'] = strtotime($this->xpathShort('Pick-up Date:', $root, '/:\s*(.+)/'), false);
        $result['PickupLocation'] = $this->xpathShort('Pick-up City:', $root, '/:\s*(.+)/');
        $result['DropoffDatetime'] = strtotime($this->xpathShort('Drop-off Date:', $root, '/:\s*(.+)/'), false);
        $result['DropoffLocation'] = $this->xpathShort('Drop-off City:', $root, '/:\s*(.+)/');

        if (empty($result['DropoffLocation'])) {
            $result['DropoffLocation'] = $result['PickupLocation'];
        }

        $result['PickupPhone'] = $this->http->FindSingleNode(".//text()[contains(., 'LOCATION PHONE')]", $root, false, '/LOCATION PHONE\s+([\d\s()-]+)$/');
        $result['CarType'] = $this->xpathShort('Car Type:', $root, '/:\s*(.+)/');
        $result['Status'] = $this->xpathShort('Status:', $root, '/:\s*(.+)/');

        $total = $this->xpathShort('Approximate Total:', $root);

        if (preg_match('/:\s*(.+?)\s*([A-Z]{3})/', $total, $matches)) {
            $result['TotalCharge'] = (float) preg_replace('/[^\d.]+/', '', $matches[1]);
            $result['Currency'] = $matches[2];
        }

        return $result;
    }

    private function xpathShort($param, $root, $pattern = null)
    {
        if (empty($pattern)) {
            return $this->http->FindSingleNode(".//text()[contains(., '{$param}')]/ancestor::td[1]", $root);
        } else {
            return $this->http->FindSingleNode(".//text()[contains(., '{$param}')]/ancestor::td[1]", $root, false, $pattern);
        }
    }

    /**
     * TODO: The experimental method.
     * If several groupings need to be used
     * Named subpatterns not accept the syntax (?<Name>) and (?'Name').
     *
     * @version v0.1
     *
     * @param type $pattern
     * @param type $text
     *
     * @return type
     */
    private function matchSubpattern($pattern, $text)
    {
        if (preg_match($pattern, $text, $matches)) {
            foreach ($matches as $key => $value) {
                if (is_int($key)) {
                    unset($matches[$key]);
                }
            }

            if (!empty($matches)) {
                return array_map([$this, 'normalizeText'], $matches);
            }
        }

        return [];
    }

    private function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
