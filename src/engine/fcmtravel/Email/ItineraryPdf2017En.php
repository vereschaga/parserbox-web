<?php

namespace AwardWallet\Engine\fcmtravel\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryPdf2017En extends \TAccountChecker
{
    public $mailFiles = "fcmtravel/it-11485732.eml, fcmtravel/it-11499774.eml, fcmtravel/it-152205115.eml, fcmtravel/it-26939616.eml, fcmtravel/it-29136702.eml, fcmtravel/it-29192743.eml, fcmtravel/it-40169983.eml, fcmtravel/it-6113463.eml, fcmtravel/it-7153420.eml, fcmtravel/it-7153662.eml, fcmtravel/it-7153686.eml";

    public static $detectHeaders = [
        'fcmtravel' => [
            'froms'   => ['fcmtravel'],
            'subject' => ['Itinerary for Booking'],
        ],
        'campustr' => [
            'froms'   => ['campustravel'],
            'subject' => ['Itinerary for Booking'],
        ],
        'ctraveller' => [
            'froms'   => ['corporatetraveller'],
            'subject' => ['Itinerary for Booking'],
        ],
        'flightcentre' => [
            'froms'   => ['flightcentre'],
            'subject' => ['Itinerary for Booking'],
        ],
    ];
    private $detectCompany = [
        'flightcentre' => [
            'Thank you for booking with Flight Centre',
            'Your Flight Centre Business Travel office ',
            'please visit https://www.flightcentrebusinesstravel.com.au/',
            'viewed online at https://www.flightcentrebusinesstravel.com.au/',
        ],
        'ctraveller' => [
            'corporatetraveller.',
        ],
        'campustr' => [
            'CAMPUS TRAVEL', 'campustravel',
        ],
        'fcmtravel' => [ // always last!
            'FCM TRAVEL', 'fcmtravel',
        ],
    ];
    private $detectBody = [
        'Booking Terms and Conditions', 'Please check the booking terms',
    ];

    private $patterns = [
        'travellerName' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+', // EIFFE / NAOMI LORNA MRS
    ];

    private $pdfPattern = '.*\.pdf';
    private $flightItCount = 0;

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $providerCodes = [];
        $email->obtainTravelAgency();
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$this->detectBody($text)) {
                continue;
            }

            $this->parsePdf($email, $text);
            $email->ota()->confirmation($this->match('/\bBooking \\#:[ ]*(\d+)\n/', $text));

            $providerCode = $this->detectProvider($parser->getCleanFrom(), $text);

            if ($providerCode) {
                $providerCodes[] = $providerCode;
            }
        }

        if (count(array_unique($providerCodes)) === 1) {
            $email->setProviderCode($providerCodes[0]);
        } elseif (count($providerCodes) > 1) {
            $this->logger->debug('providerCode not defined!');

            $email->setProviderCode(null);
        }

        $email->setType('ItineraryPdf2017' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$detectHeaders as $detectHeaders) {
            if (empty($detectHeaders['froms']) || empty($detectHeaders['subject'])) {
                continue;
            }
            $foundFrom = false;

            foreach ($detectHeaders['froms'] as $pFrom) {
                if (stripos($headers['from'], $pFrom) !== false) {
                    $foundFrom = true;

                    break;
                }
            }

            if ($foundFrom == false) {
                continue;
            }

            foreach ($detectHeaders['subject'] as $pSubject) {
                if (stripos($headers['subject'], $pSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            foreach ($this->detectCompany as $detectCompany) {
                $foundCompany = false;

                foreach ($detectCompany as $dCompany) {
                    if (stripos($text, $dCompany) !== false) {
                        $foundCompany = true;

                        break 2;
                    }
                }
            }

            if ($foundCompany === false) {
                continue;
            }

            if ($this->detectBody($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectHeaders as $provider) {
            if (!empty($provider['froms'])) {
                foreach ($provider['froms'] as $pFrom) {
                    if (stripos($from, $pFrom) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectHeaders);
    }

    private function findFlight(Email $email, array $tickets): ?Flight
    {
        if (count($tickets) === 0) {
            foreach ($email->getItineraries() as $it) {
                if ($it->getType() === 'flight' && empty($it->getTicketNumbers())) {
                    /** @var Flight $f */
                    $f = $it;
                }
            }
        } else {
            foreach ($email->getItineraries() as $it) {
                if ($it->getType() === 'flight' && !empty($iTickets = array_column($it->getTicketNumbers(), 0)) && strncasecmp($tickets[0], $iTickets[0], 3) === 0) {
                    /** @var Flight $f */
                    $f = $it;
                    $f->issued()->tickets(array_diff($tickets, $iTickets), false);
                }
            }
        }

        return isset($f) ? $f : null;
    }

    private function parseFlightSegment(Flight $f, $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $s = $f->addSegment();

        $dateDep = 0;

        // Airline
        if (preg_match('/[[:alpha:]] (?<date>\d{1,2} [[:alpha:]]+ \d{4}) at \d+\s+[\w\s]+\s+\(\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d+)\s*\)(?:.*?Operated by\s*(?<operator>\S.+))?/u', $text, $matches)) {
            $dateDep = strtotime($matches['date']);
            $s->airline()
                ->name($matches['al'])
                ->number($matches['fn'])
                ->operator(empty($matches['operator']) ? null : trim($matches['operator']), false, true);
        }
        $conf = $this->match('/Airline Reference:\s*([A-Z\d]{5,6})\b/', $text);

        if (!empty($conf)) {
            $s->airline()->confirmation($conf);
        }

        // Departure
        if (preg_match('/Departing:\s*(?<name>[A-Z][-,.A-Z\/ \)\(]+?)(?:\s+\(Terminal (?<term>\w+)\))? at (?<time>[\d:]+\s*(:?[ap\.]m\b)?)/i', $text, $matches)) {
            $s->departure()
                ->name($matches['name'])
                ->noCode()
                ->date(strtotime($matches['time'], $dateDep))
                ->terminal(empty($matches['term']) ? null : $matches['term'], false, true)
            ;
        }

        // Arrival
        if (preg_match('/Arriving:\s*(?<name>[A-Z][-,.A-Z\/ \(\)]+?)(?:\s+\(Terminal (?<term>\w+)\))?(?:,\s+[-[:alpha:]]+ (?<date>\d{1,2} [[:alpha:]]+ \d{4}))? at (?<time>[\d:]+\s*(:?[ap\.]m\b)?)/iu', $text, $matches)) {
            $dateArr = empty($matches['date']) ? $dateDep : strtotime($matches['date']);

            $s->arrival()
                ->name($matches['name'])
                ->noCode()
                ->date(strtotime($matches['time'], $dateArr))
                ->terminal(empty($matches['term']) ? null : $matches['term'], false, true)
            ;
        }

        // Extra
        $s->extra()
            ->aircraft($this->match('/Aircraft:[ ]*([\w\s-]+)\n/', $text), true, true)
            ->stops($this->match('/Number of Stops:[ ]*(\d{1,2})\n/', $text), true, true)
            ->duration($this->match('/Flight Time:[ ]*([\w\s]+)\n/', $text), true, true)
            ->cabin($this->match('/Class of Service:[ ]*[A-Z]{1,2} - ([\w ]+)(?:[ ]+\[|\n|$)/', $text), true, true)
            ->bookingCode($this->match('/Class of Service:[ ]*([A-Z]{1,2}) - [\w ]+(?:[ ]+\[|\n|$)/', $text), true, true)
            ->seat($this->match('/Seat\s+Requested[ ]+[A-Z\(\)\/ ]+?[ ]{2,}(\d{1,3}[A-Z])\b/', $text), true, true)
        ;
    }

    private function parseRail(Email $email, $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $t = $email->add()->train();

        // General
        $t->general()
            ->confirmation($this->re("#^[ ]*Confirmation No: +(.+)#m", $text))
            ->status($this->re("#^[ ]*Status: +(.+)#m", $text))
        ;

        // Segments
        $s = $t->addSegment();

        // Departure
        if (preg_match("#Board: +(.+?)\n\s*+Alight: +#s", $text, $m)
                && preg_match("#(?<city>.+), \w+ (?<date>\d+ \w+ \d{4}) at (?<time>\d+:\d+)\s+(?<place>.+)#s", $m[1], $matches)) {
            $s->departure()
                //on saved examples with city google return wrong geo location
                ->name(trim(preg_replace("#\s+#", ' ', trim($matches['place'])), ' .'))
                ->date(strtotime($matches['date'] . ', ' . $matches['time']))
            ;
        }

        // Arrival
        if (preg_match("#\n\s*Alight: +(.+?)\s+(?:Service|Status)#s", $text, $m)
                && preg_match("#(?<city>.+), \w+ (?<date>\d+ \w+ \d{4}) at (?<time>\d+:\d+)\s+(?<place>.+)#s", $m[1], $matches)) {
            $s->arrival()
                //on saved examples with city google return wrong geo location
                ->name(trim(preg_replace("#\s+#", ' ', trim($matches['place'])), ' .'))
                ->date(strtotime($matches['date'] . ', ' . $matches['time']))
            ;
        }

        // Extra
        $s->extra()
            ->noNumber();

        // Price
        $total = $this->match('/\n\s*Base Rate: +(.+)[ ]*per Service/', $text);

        if (preg_match("#^\s*(?<curr>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[A-Z]{3})\s*$#", $total, $m)) {
            $t->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }
    }

    private function parseLimo(Email $email, $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $t = $email->add()->transfer();

        // General
        $conf = $this->match('/^[ ]*Confirmation No: +(.+)/m', $text);

        if (!empty($conf)) {
            $t->general()->confirmation($conf);
        } elseif (empty($conf) && preg_match('/\n[ ]*.*Confirmation.*: /', $text)) {
            $t->general()->noConfirmation();
        }

        $t->general()
            ->status($this->match('/Status: +(.+)/', $text));

        // Segments
        $s = $t->addSegment();

        $regexp = "#(?<city>.+), \w+ (?<date>\d+ \w+ \d{4}) at (?<time>\d+:\d+)\s+(?<place>.+)#s";

        if (preg_match("#Board: +(.+)\s+Alight: +(.+?)\s+(?:Service|Status)#s", $text, $m)) {
            // Departure
            if (preg_match($regexp, $m[1], $matches)) {
                $s->departure()
                    //on saved examples with city google return wrong geo location
                    ->name(trim(preg_replace("#\s+#", ' ', trim($matches['place'])), ' .'))
                    ->date(strtotime($matches['date'] . ', ' . $matches['time']))
                ;
                $depTime = $matches['date'] . ', ' . $matches['time'];
            }

            // Arrival
            if (preg_match($regexp, $m[2], $matches)) {
                $s->arrival()
                    //on saved examples with city google return wrong geo location
                    ->name(trim(preg_replace("#\s+#", ' ', trim($matches['place'])), ' .'))
                ;

                if (isset($depTime) && strcmp($depTime, $matches['date'] . ', ' . $matches['time']) === 0) {
                    $s->arrival()->noDate();
                } else {
                    $s->arrival()->date(strtotime($matches['date'] . ', ' . $matches['time']));
                }
            }
        }

        if (!$t->getConfirmationNumbers() && $s->getDepName() && $s->getArrName() && preg_match('/^[ ]*Confirmation No:/m', $text) === 0) {
            $t->general()->noConfirmation();
        }

        // Extra
        $s->extra()
            ->type($this->re("#^ *Service: +(.+)#m", $text))
            ->adults($this->re("#^ *Number of Seats: +(.+)#m", $text))
        ;

        // Price
        $total = $this->match('/\n\s*Base Rate: +(.+)[ ]*per Service/', $text);

        if (preg_match("#^\s*(?<curr>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[A-Z]{3})\s*$#", $total, $m)) {
            $t->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }
    }

    private function parseCar(Email $email, $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $r = $email->add()->rental();

        // General
        if (preg_match('/^[ ]*(Confirmation No)[ ]*[:]+[ ]*([-A-Z\d]{4,})$/im', $text, $m)) {
            $r->general()->confirmation($m[2], $m[1]);
        } elseif (preg_match('/Confirmation No[ ]*[:]+(?:\n.*: |\s*$)/', $text)) {
            $r->general()->noConfirmation();
        }

        $membershipVal = $this->match('/^[ ]*Membership[ ]*[:]+[ ]*([^:\s].*)$/im', $text) ?? '';

        if (preg_match("/^[Xx]+\d{4}$/", $membershipVal)) {
            // XXXXXX2312
            $r->program()->account($membershipVal, true);
        } elseif (preg_match("/^[- A-z\d]{3,40}$/", $membershipVal)) {
            // ???
            $r->program()->account($membershipVal, false);
        }

        $r->general()
            ->status($this->match('/Status:\s+(.+)/', $text));

        // Pick Up
        if (preg_match('/Pickup:\s+(.+?),\s+(\w+ \d+ \w+ \d{4}) at (\d+:\d+)\s+(.*?)\s*(?:Ph:|Dropoff:)/s', $text, $m)) {
            $r->pickup()
                ->date(strtotime($m[2] . ' ' . $m[3]))
                ->location($m[1] . (!empty($m[4]) ? ', ' . preg_replace("/\s+/", ' ', $m[4]) : ''))
            ;
        }
        $r->pickup()->phone($this->match('/Pickup:\s+.+?Ph:\s+([\d\- ]{5,})\s+Dropoff:/s', $text), true, true);

        // Drop Off
        if (preg_match('/Dropoff:\s+(.+?),\s+(\w+ \d+ \w+ \d{4}) at (\d+:\d+)\s+(.*?)\s*(?:Ph:|Service:)/s', $text, $m)) {
            $r->dropoff()
                ->date(strtotime($m[2] . ' ' . $m[3]))
                ->location($m[1] . (!empty($m[4]) ? ', ' . preg_replace("/\s+/", ' ', $m[4]) : ''))
            ;
        }
        $r->dropoff()->phone($this->match('/Dropoff:\s+.+?Ph:\s+([\d\- ]{5,}+)\s+Service:/s', $text), true, true);

        // Car
        $r->car()->type($this->match('/Service:\s+(.+)/', $text));

        // Extra
        $r->extra()
            ->company($this->match('/\w+ \d+ \w+ \d{4} at \d+\s+([^\n]+)/', $text));

        // Price
        $total = $this->match('/Approx Total: +(\d[\d\.]+)/', $text);

        if (!empty($total) && is_numeric($total)) {
            $r->price()->total((float) $total);

            if (preg_match('/Base Rate:\s+([A-Z]{3}) +[\d\.]+/', $text, $m)) {
                $r->price()->currency($m[1]);
            }
        }
        $tax = $this->match('/Additional Charges: +(\d[\d\.]+)/', $text);

        if (!empty($tax) && is_numeric($tax)) {
            $r->price()->fee('Additional Charges', (float) $tax);
        }
    }

    private function parseHotel(Email $email, $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $h = $email->add()->hotel();

        // General
        $conf = $this->match('/Confirmation No:\s+([A-Za-z\d]{4,})/', $text);

        if (!empty($conf)) {
            $h->general()->confirmation($conf);
        } elseif (empty($conf) && preg_match('/Confirmation No:\s*(?:\n.*: |$)/', $text)) {
            $h->general()->noConfirmation();
        }

        $h->general()
            ->status($this->match('/Status:\s+(.+)/', $text));

        if (preg_match('/Cancellation Information:\s+(.+)\s+Remarks:/s', $text, $m)) {
            $h->general()->cancellation(trim($m[1]));
        }

        // Hotel
        $h->hotel()
            ->name($this->match('/^\s*\w+ \d+ \w+ \d{4}\s+(.+)/', $text))
            ->address(str_replace("\n", " ", $this->match('/^\s*\w+ \d+ \w+ \d{4}\s+.+?\n(.+?)\s+(?:Ph|Check)/s', $text)))
            ->phone($this->match('/Ph:\s+(.+)/', $text), true, true)
        ;

        // Booked
        if (preg_match('/Check In:\s+(\w+ \d+ \w+ \d{4}) at (\d+:\d+)/i', $text, $m)) {
            $h->booked()->checkIn(strtotime($m[1] . ' ' . $m[2]));
        }

        if (preg_match('/Check out:\s+(\w+ \d+ \w+ \d{4}) at (\d+:\d+)/i', $text, $m)) {
            $h->booked()->checkOut(strtotime($m[1] . ' ' . $m[2]));
        }
        $h->booked()
            ->rooms($this->match('/Number of Rooms:\s+(\d+)/', $text))
            ->guests($this->match('/Number of Guests:\s+(\d+)/', $text), true, true)
        ;

        // Rooms
        $roomType = $this->match('/^[ ]*Room Type[ ]*[:]+\s*([^:\s].*)/m', $text);
        $roomRateVal = $this->match('/^[ ]*Room Rate[ ]*[:]+\s*([^:\s].*)/m', $text) ?? '';

        if (preg_match("/^(.+?)\s+per Night(?:\s*\([^)(]*\))?$/i", $roomRateVal, $m)) {
            // AUD 109.00 per Night (incl GST)
            $roomRate = $m[1];
        } elseif ($roomRateVal === '') {
            $roomRate = null;
        } else {
            $roomRate = '';
        }

        $h->addRoom()
            ->setType($roomType)
            ->setRate($roomRate, false, true);
    }

    private function parseParking(Email $email, $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $p = $email->add()->parking();

        // General
        $p->general()
            ->noConfirmation()
            ->status($this->match('/Status: +(.+)/', $text));

        // Place
        $p->place()
            ->address($this->match('/^\s*\w+ \d+ \w+ \d+ .+? {3,}(.+)/m', $text))
            ->location($this->match('/^\s*\w+ \d+ \w+ \d+ .+? {3,}(.+)/m', $text));

        // Booked
        $p->booked()
            ->start(strtotime(preg_replace("#(.+) at (.+)#", '$1, $2', $this->match("#\n\s*Board:[ ]+.+, \w+ (\d+ \w+ \d{4} at \d+:\d+)\n#", $text))))
            ->end(strtotime(preg_replace("#(.+) at (.+)#", '$1, $2', $this->match("#\n\s*Alight:[ ]+.+, \w+ (\d+ \w+ \d{4} at \d+:\d+)\n#", $text))))
            ->rate($this->match("#\n\s*Base Rate:[ ]+(.+) per Day\n#", $text), true, true)
        ;

        // Price
        $total = $this->match('/\n\s*Base Rate: +(.+)[ ]*per Service/', $text);

        if (preg_match("#^\s*(?<curr>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[A-Z]{3})\s*$#", $total, $m)) {
            $p->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }
    }

    private function parsePdf(Email $email, $text): void
    {
        $flights = $trains = $transfers = $cars = $hotels = $parkings = [];
        $text = $this->findCutSection($text, null, ['Booking References', 'CHECK IN DETAILS', 'Booking Terms and Conditions']);

        foreach ($this->splitter('#^\s*(\w+ \d+ \w+ \d+ .+?\s+(?:Departing|Pickup|Check[\s\-]*In|Board))#ism', $text) as $t) {
            if (strpos($t, 'Pickup') !== false) {
                $cars[] = $t;
            } elseif (strpos($t, 'Check In:') != false) {
                $hotels[] = $t;
            } elseif (strpos($t, 'Board') != false) {
                if (strpos($t, 'Rail') != false) {
                    $trains[] = $t;
                } elseif (preg_match("#^\s*\w+ \d+ \w+ \d+ .+?[ ]{2,}.*\bparking\b.*#im", $t) != false) {
                    $parkings[] = $t;
                } elseif (preg_match("#\s+Service:[ ]+CONFERENCE\s+#i", $t) != false) {
                    continue;
                } else {
                    $transfers[] = $t;
                }
            } else {
                $flights[] = $t;
            }
        }

        foreach ($flights as $txt) {
            if (preg_match_all('/^[ ]*Ticket Number\b[^\d\n]+(\d{2}[\d ]+\d{2})$/m', $txt, $m)) {
                $tickets = str_replace(' ', '', $m[1]);
            } else {
                $tickets = [];
            }

            // $f = $this->findFlight($email, $tickets);

            if (!isset($f) || $f === null) {
                $f = $email->add()->flight();
                $this->flightItCount++;
    
                $f->general()->noConfirmation();
    
                if (count($tickets) > 0) {
                    $f->issued()->tickets($tickets, false);
                }
            } elseif (isset($f) && count($tickets) > 0 && count($f->getTicketNumbers()) > 0) {
                $f->issued()->tickets(array_diff($tickets, array_column($f->getTicketNumbers(), 0)), false);
            }
    
            $this->parseFlightSegment($f, $txt);
        }

        foreach ($trains as $txt) {
            $this->parseRail($email, $txt);
        }

        foreach ($transfers as $txt) {
            $this->parseLimo($email, $txt);
        }

        foreach ($cars as $txt) {
            $this->parseCar($email, $txt);
        }

        foreach ($hotels as $txt) {
            $this->parseHotel($email, $txt);
        }

        foreach ($parkings as $txt) {
            $this->parseParking($email, $txt);
        }

        if ($this->flightItCount === 1) {
            $total = $this->match('/\n\s*Total Cost of Flights +(.+)/', $text);

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
                $flightTotals = [
                    'total'    => $this->amount($m['amount']),
                    'currency' => $this->currency($m['curr']),
                ];
            }
        }

        $resDate = strtotime($this->match('/\bDate:\s*(.+?)\n/', $text), false);
        $travellers = $this->parsePassengers($text);

        foreach ($email->getItineraries() as $it) {
            if (!empty($resDate)) {
                $it->general()->date($resDate);
            }
            $it->general()->travellers($travellers, true);

            if ($this->flightItCount === 1 && $it->getType() === 'flight' && !empty($flightTotals)) {
                $it->price()
                    ->total($flightTotals['total'])
                    ->currency($flightTotals['currency'])
                ;
            }
        }
    }

    private function parsePassengers(?string $text): array
    {
        if (preg_match_all("/\n[ ]*Passenger\n+[ ]*({$this->patterns['travellerName']})(?:[ ]{2}.+|\n)/u", $text ?? '', $matches)) {
            return array_map(function ($item) {
                return $this->normalizeTraveller($item);
            }, $matches[1]);
        }

        return [];
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
        ], [
            '$1',
            '$1',
        ], $s);
    }

    private function detectProvider($from, $text): ?string
    {
        foreach (self::$detectHeaders as $providerCode => $detectHeaders) {
            if (empty($detectHeaders['froms'])) {
                continue;
            }

            foreach ($detectHeaders['froms'] as $pFrom) {
                if (stripos($from, $pFrom) !== false) {
                    return $providerCode;
                }
            }
        }

        foreach ($this->detectCompany as $providerCode => $detectCompany) {
            foreach ($detectCompany as $dCompany) {
                if (stripos($text, $dCompany) !== false) {
                    return $providerCode;
                }
            }
        }

        return null;
    }

    private function detectBody(?string $text): bool
    {
        if ( empty($text) || !isset($this->detectBody) ) {
            return false;
        }
        foreach ($this->detectBody as $phrase) {
            if ( !is_string($phrase) )
                continue;
            if (strpos($text, $phrase) !== false)
                return true;
        }
        return false;
    }

    private function match(string $pattern, ?string $text, bool $allMatches = false)
    {
        if (preg_match($pattern, $text ?? '', $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        }

        return null;
    }

    private function normalizeText($string)
    {
        return trim(preg_replace('/\s+/', ' ', $string));
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*?)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    private function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'   => 'EUR',
            'AU$' => 'AUD',
            'AUD$'=> 'AUD',
            'NZD$'=> 'NZD',
            'NZ$' => 'NZD',
            '$'   => 'USD',
            '£'   => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
