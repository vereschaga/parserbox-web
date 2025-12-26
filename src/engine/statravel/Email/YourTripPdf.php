<?php

namespace AwardWallet\Engine\statravel\Email;

use AwardWallet\Engine\MonthTranslate;

class YourTripPdf extends \TAccountChecker
{
    public $mailFiles = "statravel/it-8128247.eml, statravel/it-8133735.eml, statravel/it-8475773.eml, statravel/it-8541232.eml, statravel/it-8542428.eml, statravel/it-8569255.eml, statravel/it-9023554.eml, statravel/it-9040620.eml";

    protected $subjects = [
        'de' => ['Bitte beachte die geänderten Flugzeiten'],
        'en' => ['Your Flight eTicket', 'FLIGHT RESERVATION'],
    ];

    protected $tripNumber = '';

    protected $lang = '';

    protected $langDetectors = [ // associated with $blacklistPdfDetectors in parser YourTripPdf
        'de' => ['Ankunft:'],
        'fr' => ['Heure:'],
        'en' => ['Arrival:', 'Check out:', 'Depart:'],
    ];

    protected static $dict = [
        'de' => [
            'Booking Number:' => 'Auftragsnummer:',
            'segmentSplitter' => '/^[ ]*\d{1,2}\.\d{1,2}\.\d{2,4}[ ]*$/m', // 31.10.17
            'Printed Date:'   => 'Druckdatum:',
            // FLIGHT
            'Flight:' => 'Flug:',
            //			'Class:' => '',
            'Aircraft:'        => 'Flugzeugtyp:',
            'Flight duration:' => 'Reisezeit:',
            //			'Operated by:' => '',
            'Departure:' => 'Abflug:',
            'Arrival:'   => 'Ankunft:',
            'Time:'      => 'Zeit:',
            //			'Terminal' => '',
            'LAST NAME/First Name' => 'NACHNAME/Vorname',
            'Your Ref'             => ['Ihr PNR', 'Dein PNR'],
            // HOTEL
            //			'Phone:' => '',
            //			'Rooms:' => '',
            //			'Status:' => '',
            //			'PNR Ref:' => '',
            //			'Check in:' => '',
            //			'Check out:' => '',
            //			'Room' => '',
            //			'For:' => '',
            // CAR
            //			'Depart:' => '',
            //			'Arrive:' => '',
        ],
        'fr' => [
            'Booking Number:' => 'Numéro De Réservation:',
            'segmentSplitter' => '/^[ ]*\d{1,2}\.\d{1,2}\.\d{2,4}[ ]*$/m', // 18.07.17
            'Printed Date:'   => "Date d'impression",
            // FLIGHT
            'Flight:' => 'Vol N°:',
            'Class:'  => 'Classe:',
            //			'Aircraft:' => '',
            //			'Flight duration:' => '',
            //			'Operated by:' => '',
            'Departure:' => 'Départ',
            'Arrival:'   => 'Arrivée',
            'Time:'      => 'Heure:',
            //			'Terminal' => '',
            'LAST NAME/First Name' => 'Nom',
            'Your Ref'             => 'Votre référence',
            // HOTEL
            //			'Phone:' => '',
            //			'Rooms:' => '',
            //			'Status:' => '',
            //			'PNR Ref:' => '',
            //			'Check in:' => '',
            //			'Check out:' => '',
            //			'Room' => '',
            //			'For:' => '',
            // CAR
            //			'Depart:' => '',
            //			'Arrive:' => '',
        ],
        'en' => [
            'segmentSplitter' => '/^[ ]*\d{1,2}-[^-,.\d ]{3,}-\d{2}[ ]*$/m', // 16-May-17
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'STA Travel') !== false
            || stripos($from, '@statravel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match('/STA\s+Travel.+(?:Booking\s+Confirmation|Itinerary)/i', $headers['subject'])) { // en
            return true;
        }

        if (stripos($headers['subject'], 'Confirmation de réservation de STA Travel') !== false) { // fr
            return true;
        }

        if (self::detectEmailFromProvider($headers['from']) === false) {
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

            if (stripos($textPdf, 'Your STA Travel') === false && stripos($textPdf, '@STATRAVEL.COM') === false && stripos($textPdf, '@STATRAVEL.AT') === false && stripos($textPdf, 'www.statravel.com') === false && stripos($textPdf, 'www.statravel.at') === false && stripos($textPdf, 'www.statravel.de') === false && stripos($textPdf, 'www.statravel.co.uk') === false && stripos($textPdf, 'www.checkmytrip.com/statravel') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($textPdf) === false) {
                continue;
            }

            if ($its = $this->parsePdf($textPdf)) {
                return [
                    'parsedData' => [
                        'Itineraries' => $its,
                    ],
                    'emailType' => 'YourTripPdf_' . $this->lang,
                ];
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parsePdf($textPdf)
    {
        $its = [];

        $text = $this->sliceText($textPdf, $this->t('Booking Number:'), 'Insurance Details');

        if (empty($text)) {
            $text = $this->sliceText($textPdf, $this->t('Booking Number:'), 'Born Free Foundation');
        }

        if (preg_match('/' . $this->t('Booking Number:') . '[ ]*([A-Z\d]{5,})/', $text, $matches)) {
            $this->tripNumber = $matches[1];
        }

        $travelSegments = $this->splitText($text, $this->t('segmentSplitter'));

        foreach ($travelSegments as $travelSegment) {
            if (
                strpos($travelSegment, '  ' . $this->t('Departure:') . ' ') !== false
                || strpos($travelSegment, ' ' . $this->t('Departure:') . '  ') !== false
            ) {
                $itFlights = $this->parseFlight($travelSegment);

                foreach ($itFlights as $itFlight) {
                    if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                        if (!empty($itFlight['Passengers'][0])) {
                            if (!empty($its[$key]['Passengers'][0])) {
                                $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                                $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                            } else {
                                $its[$key]['Passengers'] = $itFlight['Passengers'];
                            }
                        }

                        if (!empty($itFlight['TicketNumbers'][0])) {
                            if (!empty($its[$key]['TicketNumbers'][0])) {
                                $its[$key]['TicketNumbers'] = array_merge($its[$key]['TicketNumbers'], $itFlight['TicketNumbers']);
                                $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                            } else {
                                $its[$key]['TicketNumbers'] = $itFlight['TicketNumbers'];
                            }
                        }
                        $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
                    } else {
                        $its[] = $itFlight;
                    }
                }
            } elseif (strpos($travelSegment, '  ' . $this->t('Check in:') . ' ') !== false) {
                $its[] = $this->parseHotel($travelSegment);
            } elseif (preg_match('/Special Remarks\s*:\s*Car Rental/i', $travelSegment) && strpos($travelSegment, '  ' . $this->t('Depart:') . ' ') !== false) {
                $its[] = $this->parseCar($travelSegment);
            } elseif (preg_match('/Special Remarks\s*:\s*Tours/i', $travelSegment) && strpos($travelSegment, '  ' . $this->t('Depart:') . ' ') !== false) {
                $its[] = $this->parseEvent($travelSegment);
            }
        }

        if (empty($its[0]['RecordLocator']) && empty($its[0]['ConfirmationNumber']) && empty($its[0]['Number']) && empty($its[0]['ConfNo'])) {
            return false;
        }

        foreach ($its as $key => $it) {
            $its[$key] = $this->uniqueTripSegments($it);
        }

        return $its;
    }

    protected function parseFlight($text)
    {
        $its = [];

        $patterns = [
            'timeDate'     => '/(\d{1,2}:\d{2}(?:[ ]*[ap]m)?)[ ]+\(([^)(]+)\)/i',
            'nameTerminal' => '/(.+)[ ]*,[ ]*(' . $this->t('Terminal') . '[\w ]{2,}|[\w ]{2,}' . $this->t('Terminal') . ')$/u',
        ];

        $seg = [];

        // AirlineName
        // FlightNumber     // JQJQ830 or JQ830
        if (preg_match('/^[ ]*' . $this->t('Flight:') . '[ ]*(?:[A-Z\d]{2})?([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(\d+)/m', $text, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
        }

        // Cabin
        if (preg_match('/^[ ]*' . $this->t('Class:') . '[ ]*(.+)/m', $text, $matches)) {
            $classParts = preg_split('/[ ]{2,}/', $matches[1]);

            if (preg_match('/^[-\w ]+$/u', $classParts[0])) {
                $seg['Cabin'] = $classParts[0];
            }
        }

        // Aircraft
        if (preg_match('/[ ]{2,}' . $this->t('Aircraft:') . '[ ]*(.+)/', $text, $matches)) {
            $aircraftParts = preg_split('/[ ]{2,}/', $matches[1]);

            if (preg_match('/^[^:]+$/', $aircraftParts[0])) {
                $seg['Aircraft'] = $aircraftParts[0];
            }
        }

        // Duration
        if (preg_match('/[ ]{2,}' . $this->t('Flight duration:') . '[ ]*([:\d]{3,5}|[\d hm]{2,})$/mi', $text, $matches)) {
            $seg['Duration'] = $matches[1];
        }

        // Operator
        if (preg_match('/[ ]+' . $this->t('Operated by:') . '[ ]*(.+)/', $text, $matches)) {
            $operatorParts = preg_split('/[ ]{2,}/', $matches[1]);

            if (preg_match('/^[^:]+$/', $operatorParts[0])) {
                $seg['Operator'] = $operatorParts[0];
            }
        }

        // DepName
        // ArrName
        // DepartureTerminal
        // ArrivalTerminal
        if (preg_match('/' . $this->t('Departure:') . '[ ]+' . $this->t('Arrival:') . '$\s+^(.+)/m', $text, $matches)) {
            $airportsParts = preg_split('/[ ]{3,}/', $matches[1]);
            $airportDep = $airportsParts[count($airportsParts) - 2];
            $airportArr = $airportsParts[count($airportsParts) - 1];

            if (preg_match($patterns['nameTerminal'], $airportDep, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepartureTerminal'] = $m[2];
            } else {
                $seg['DepName'] = $airportDep;
            }

            if (preg_match($patterns['nameTerminal'], $airportArr, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrivalTerminal'] = $m[2];
            } else {
                $seg['ArrName'] = $airportArr;
            }
        }

        // DepDate
        // ArrDate
        if (preg_match('/' . $this->t('Time:') . '[ ]*(.+)' . $this->t('Time:') . '[ ]*(.+)/', $text, $matches)) {
            if (preg_match($patterns['timeDate'], $matches[1], $m)) {
                if ($dateDep = $this->normalizeDate($m[2])) {
                    $seg['DepDate'] = strtotime($dateDep . ', ' . $m[1]);
                }
            }

            if (preg_match($patterns['timeDate'], $matches[2], $m)) {
                if ($dateArr = $this->normalizeDate($m[2])) {
                    $seg['ArrDate'] = strtotime($dateArr . ', ' . $m[1]);
                }
            }
        }

        // ArrCode
        // DepCode
        $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;

        // Passengers
        // TicketNumbers
        // RecordLocator
        // TripSegments
        $passengersTexts = $this->splitText($text, '/^[ ]*' . preg_replace('/([.$*)|(\/])/', '\\\\$1', $this->t('LAST NAME/First Name')) . '.+' . $this->opt($this->t('Your Ref')) . '$/m');
        $passengersText = preg_replace('/\s*(.+)(?:This flight is not changeable with|' . $this->t('Printed Date:') . ').*/si', '$1', $passengersTexts[0]);
        preg_match_all('/^\s*(\w[\w\/ ]+\w[ ]+(?<ticketType>Electronic Ticket|-| )[ ]{2,}(?<ticketNimber>\d+[-\/ ]*\d{5,}|-| )[ ]{2,}(?<airlineRef>[a-z\d]{5,}|-| )[ ]{2,}(?<yourRef>[a-z\d]{5,}))$/mui', $passengersText, $passengerMatches);

        foreach ($passengerMatches[1] as $passengerRow) {
            $passengerParts = preg_split('/[ ]{2,}/', trim($passengerRow, '-'));
            $it = [];
            $it['Kind'] = 'T';

            if (!empty($this->tripNumber)) {
                $it['TripNumber'] = $this->tripNumber;
            }
            $it['Passengers'] = [$passengerParts[0]];

            foreach ($passengerParts as $passengerPart) {
                if (preg_match('/^(\d+[-\/ ]*\d{5,})$/', $passengerPart)) {
                    $it['TicketNumbers'] = [$passengerPart];

                    break;
                }
            }
            $it['RecordLocator'] = strtoupper($passengerParts[count($passengerParts) - 1]);
            $it['TripSegments'] = [$seg];
            $its[] = $it;
        }

        if (empty($passengerMatches[1])) {
            $it = ['Kind' => 'T'];

            if (preg_match('/Confirmation No\s*:\s*([\s\dA-Z]+)/', $text, $m)) {
                $it['RecordLocator'] = $m[1];
            }
            $it['RecordLocator'] = CONFNO_UNKNOWN;

            if (!empty($this->tripNumber)) {
                $it['TripNumber'] = $this->tripNumber;
            }

            if (preg_match('/Status\s+(\w+)/', $text, $m)) {
                $it['Status'] = $m[1];
            }
            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }

        return $its;
    }

    protected function parseHotel($text)
    {
        $it = [];
        $it['Kind'] = 'R';

        if (!empty($this->tripNumber)) {
            $it['TripNumber'] = $this->tripNumber;
        }

        // HotelName
        // Phone
        // Address
        if (preg_match('/^\s*(.+?)\s+' . $this->t('Status:') . '/s', $text, $matches)) {
            if (preg_match_all('/^[ ]*(.+)/m', $matches[1], $nameAddressMatches)) {
                $hotelNameParts = preg_split('/[ ]{2,}/', $nameAddressMatches[1][0]);
                $it['HotelName'] = $hotelNameParts[0];
                // BritBound: Hit The Ground Running, London (Phone:+1 427 398 4762)
                if (preg_match('/(.+)\(' . $this->t('Phone:') . '[ ]*([+\d][-.\d ]+\d)\)/', $nameAddressMatches[1][1], $m)) {
                    $it['Address'] = trim($m[1], ', ');
                    $it['Phone'] = $m[2];
                } elseif (!empty($nameAddressMatches[1][1])) {
                    $hotelAddressParts = preg_split('/[ ]{2,}/', $nameAddressMatches[1][1]);
                    $it['Address'] = trim($hotelAddressParts[0], ', ');
                }
            }
        }

        // Rooms
        if (preg_match('/[ ]{2,}' . $this->t('Rooms:') . '[ ]*(\d{1,3})$/m', $text, $matches)) {
            $it['Rooms'] = $matches[1];
        }

        // Status
        if (preg_match('/^[ ]*' . $this->t('Status:') . '[ ]*(.+)/m', $text, $matches)) {
            $statusParts = preg_split('/[ ]{2,}/', $matches[1]);

            if (preg_match('/^[^:]+$/', $statusParts[0])) {
                $it['Status'] = $statusParts[0];
            }
        }

        // ConfirmationNumber
        if (preg_match('/[ ]{2,}' . $this->t('PNR Ref:') . '[ ]*([A-Z\d]{5,})$/m', $text, $matches)) {
            $it['ConfirmationNumber'] = $matches[1];
        }

        // CheckInDate
        // CheckOutDate
        if (preg_match('/^[ ]*' . $this->t('Check in:') . '[ ]*(.+)/m', $text, $matches)) {
            $checkInParts = preg_split('/[ ]{2,}/', $matches[1]);

            if ($dateCheckIn = $this->normalizeDate($checkInParts[0])) {
                $it['CheckInDate'] = strtotime($dateCheckIn);
            }
        }

        if (preg_match('/^[ ]*' . $this->t('Check out:') . '[ ]*(.+)/m', $text, $matches)) {
            $checkOutParts = preg_split('/[ ]{2,}/', $matches[1]);

            if ($dateCheckOut = $this->normalizeDate($checkOutParts[0])) {
                $it['CheckOutDate'] = strtotime($dateCheckOut);
            }
        }

        // RoomType
        if (preg_match_all('/^[ ]*' . $this->t('Room') . '[ ]+\d{1,3}[ ]*:[ ]*(.+)/m', $text, $roomMatches)) {
            $rooms = [];

            foreach ($roomMatches[1] as $roomRow) {
                $roomParts = preg_split('/[ ]{2,}/', $roomRow);
                $rooms[] = preg_replace('/^(.+)\.$/', '$1', $roomParts[0]);
            }
            $it['RoomType'] = implode('; ', array_unique($rooms));
        }

        // GuestNames
        if (preg_match_all('/^[ ]*' . $this->t('For:') . '[ ]*(.+)/m', $text, $guestNameMatches)) {
            $guestNames = [];

            foreach ($guestNameMatches[1] as $guestNameRow) {
                $guestNameParts = preg_split('/[ ]{2,}/', $guestNameRow);
                // Mr Dar Porta (Age 33), Mrs Laura Porta (Age 32)
                $nameList = explode(',', $guestNameParts[0]);

                foreach ($nameList as $name) {
                    // Mr Dar Porta (Age 33)
                    if (preg_match('/^([^)(]+)/', $name, $m)) {
                        $guestNames[] = trim($m[1]);
                    }
                }
            }

            if (!empty($guestNames[0])) {
                $it['GuestNames'] = array_unique($guestNames);
            }
        }

        return $it;
    }

    protected function parseCar($text)
    {
        $patterns['time'] = '/' . $this->t('Time:') . '[ ]*(\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?)/';

        $it = [];
        $it['Kind'] = 'L';

        if (!empty($this->tripNumber)) {
            $it['TripNumber'] = $this->tripNumber;
        }

        // RentalCompany
        // PickupLocation
        // DropoffLocation
        if (preg_match('/^\s*(.+?)\s+' . $this->t('Status:') . '/s', $text, $matches)) {
            if (preg_match_all('/^[ ]*(.+)/m', $matches[1], $companyLocationMatches)) {
                $rentalCompanyParts = preg_split('/[ ]{2,}/', $companyLocationMatches[1][0]);
                $it['RentalCompany'] = $rentalCompanyParts[0];

                if (!empty($companyLocationMatches[1][1])) {
                    $pickupLocationParts = preg_split('/[ ]{2,}/', $companyLocationMatches[1][1]);
                    $it['DropoffLocation'] = $it['PickupLocation'] = $pickupLocationParts[0];
                }
            }
        }

        // Status
        if (preg_match('/^[ ]*' . $this->t('Status:') . '[ ]*(.+)/m', $text, $matches)) {
            $statusParts = preg_split('/[ ]{2,}/', $matches[1]);

            if (preg_match('/^[^:]+$/', $statusParts[0])) {
                $it['Status'] = $statusParts[0];
            }
        }

        // PickupDatetime
        // DropoffDatetime
        if (preg_match('/^[ ]*' . $this->t('Depart:') . '[ ]*(.+)/m', $text, $matches)) {
            if (preg_match($patterns['time'], $matches[1], $m)) {
                $timeDepart = $m[1];
            }

            $departParts = preg_split('/[ ]{2,}/', $matches[1]);

            if ($dateDepart = $this->normalizeDate($departParts[0])) {
                $it['PickupDatetime'] = strtotime($dateDepart . (!empty($timeDepart) ? ', ' . $timeDepart : ''));
            }
        }

        if (preg_match('/^[ ]*' . $this->t('Arrive:') . '[ ]*(.+)/m', $text, $matches)) {
            if (preg_match($patterns['time'], $matches[1], $m)) {
                $timeArrive = $m[1];
            }

            $arriveParts = preg_split('/[ ]{2,}/', $matches[1]);

            if ($dateCheckOut = $this->normalizeDate($arriveParts[0])) {
                $it['DropoffDatetime'] = strtotime($dateCheckOut . (!empty($timeArrive) ? ', ' . $timeArrive : ''));
            }
        }

        // Number
        if (preg_match('/[ ]{2,}' . $this->t('PNR Ref:') . '[ ]*([A-Z\d]{5,})$/m', $text, $matches)) {
            $it['Number'] = $matches[1];
        }

        return $it;
    }

    protected function parseEvent($text)
    {
        $patterns['time'] = '/' . $this->t('Time:') . '[ ]*(\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?)/';

        $it = [];
        $it['Kind'] = 'E';

        // EventType
        $it['EventType'] = EVENT_EVENT;

        // ConfNo
        if (preg_match('/[ ]{2,}' . $this->t('Confirmation No:') . '[ ]*([A-Z\d]{5,})$/m', $text, $matches)) {
            $it['ConfNo'] = $matches[1];
        }

        // TripNumber
        if (!empty($this->tripNumber)) {
            $it['TripNumber'] = $this->tripNumber;
        }

        // Name
        // Address
        if (preg_match('/\s+' . $this->t('G Adventures') . '.*\n[ ]*(.+?)(?:[ ]{2,}|\n)/', $text, $m)) {
            $it['Name'] = $it['Address'] = $m[1];
        }

        // StartDate
        if (preg_match('/^[ ]*' . $this->t('Depart:') . '[ ]*(\S.+?)(?:[ ]{2,}|$)/m', $text, $m)) {
            $it['StartDate'] = strtotime($m[1]);
        }

        // EndDate
        if (preg_match('/^[ ]*' . $this->t('Arrive:') . '[ ]*(\S.+?)(?:[ ]{2,}|$)/m', $text, $m)) {
            $it['EndDate'] = strtotime($m[1]);
        }

        // Phone
        // DinerName
        // Guests
        // TotalCharge
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        if (preg_match('/^[ ]*' . $this->t('Status:') . '[ ]*(.+)/m', $text, $matches)) {
            $statusParts = preg_split('/[ ]{2,}/', $matches[1]);

            if (preg_match('/^[^:]+$/', $statusParts[0])) {
                $it['Status'] = $statusParts[0];
            }
        }

        // Cancelled
        // ReservationDate
        // NoItineraries

        return $it;
    }

    protected function normalizeDate($string)
    {
        if (preg_match('/(\d{1,2})-([^-,.\d\s]{3,})-(\d{2})/', $string, $matches)) { // 20-May-17
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
        } elseif (preg_match('/(\d{1,2})\.(\d{2})\.(\d{4})/', $string, $matches)) { // 15.08.2017
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if ($day && $month && $year) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
    }

    protected function recordLocatorInArray($recordLocator, $array)
    {
        foreach ($array as $key => $value) {
            if ($value['Kind'] === 'T') {
                if ($value['RecordLocator'] === $recordLocator) {
                    return $key;
                }
            }
        }

        return false;
    }

    protected function uniqueTripSegments($it)
    {
        if ($it['Kind'] !== 'T') {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                if ($segment['FlightNumber'] === $uniqueSegment['FlightNumber'] && $segment['DepDate'] === $uniqueSegment['DepDate']) {
                    if (!empty($segment['Seats'][0])) {
                        if (!empty($uniqueSegments[$key]['Seats'][0])) {
                            $uniqueSegments[$key]['Seats'] = array_merge($uniqueSegments[$key]['Seats'], $segment['Seats']);
                            $uniqueSegments[$key]['Seats'] = array_unique($uniqueSegments[$key]['Seats']);
                        } else {
                            $uniqueSegments[$key]['Seats'] = $segment['Seats'];
                        }
                    }

                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
    }

    protected function sliceText($textSource = '', $textStart = '', $textEnd = '')
    {
        if (empty($textSource) || empty($textStart)) {
            return false;
        }
        $start = strpos($textSource, $textStart);

        if (empty($textEnd)) {
            return substr($textSource, $start);
        }
        $end = strpos($textSource, $textEnd, $start);

        if ($start === false || $end === false) {
            return false;
        }

        return substr($textSource, $start, $end - $start);
    }

    protected function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    protected function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    protected function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_replace('/([.$*)|(\/])/', '\\\\$1', $s); }, $field)) . ')';
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }
}
