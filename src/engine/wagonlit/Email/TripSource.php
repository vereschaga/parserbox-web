<?php

namespace AwardWallet\Engine\wagonlit\Email;

class TripSource extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-9987594.eml, wagonlit/it-11517021.eml, wagonlit/it-11802917.eml";

    public $reSubject = [
        'en' => ['TripSource: Oops…we’ve hit a snag', ' - Departure date', 'Trip itinerary for'],
    ];
    public $reBody = [
        'en' => ['Airline record locator', 'Booking Reference'],
    ];

    public $lang = '';
    public static $dict = [
        'en' => [
            'Locator:'          => ['Locator:', 'Locator :', 'Trip locator:', 'Trip locator :'],
            'Departure'         => ['Departure', 'DEPARTURE'],
            'Confirmation'      => ['Confirmation', 'Airline record locator', 'Booking Reference'],
            'Duration'          => ['Duration', 'Flight duration'],
            'Meal Service'      => ['Meal Service', 'Meal', 'Meal available'],
            'Frequent Flyer'    => ['Frequent Flyer', 'Frequent flyer card'],
            "Booking Reference" => ["Booking Reference", "Confirmation Number", "Confirmation", "Confirmation:", "Booking Reference (check-in)"],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $its = $this->parseEmail();
        $name = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($name) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"THANK YOU FOR CHOOSING CARLSON WAGONLIT") or contains(.,"@CONTACTCWT.COM") or contains(.,"@contactcwt.com") or contains(.,"www.carlsonwagonlit.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.carlsonwagonlit.com") or contains(@href,"//www.cwtsavvytraveler.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'CWT Service Center') !== false
            || stripos($from, '@contactcwt.com') !== false
            || stripos($from, '@reservation.carlsonwagonlit.') !== false
            || stripos($from, 'carlsonwagonlit.com') !== false
            || stripos($from, 'cwt.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $patterns = [
            'pnr'              => '[A-Z\d]{5,}', // 4TJB24
            'time'             => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?', // 19:30
            'date'             => '(\d{4}-\d{1,2}-\d{1,2}|\d{1,2}[ ]*[^,.\d\s]{3,}[ ]*\d{2,4})', // 2017-12-05    |    05 Feb 18    |    11Apr2018
            'nameCodeTerminal' => '/(.{2,})\(([A-Z]{3})[ ]+-[ ]+(.*terminal.*)\)$/i', // Istanbul (IST - Terminal INTERNATIONAL)
            'nameCode'         => '/(.{2,})\(([A-Z]{3})\)$/', // Wroclaw (WRO)
            'nameTerminal_1'   => '/(.{2,})\((.*terminal.*)\)$/i', // Geneva Geneve Cointrin (Terminal 1)
            'nameTerminal_2'   => '/(.{2,})[ ]+-[ ]+(.*terminal.*)$/i', // Shanghai Pu Dong Apt - Terminal 2
        ];

        $its = [];

        $tripNum = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Locator:')) . ']/following::text()[normalize-space(.)][1]', null, true, '/^(' . $patterns['pnr'] . ')$/');

        if (!$tripNum) {
            $tripNum = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Locator:')) . ']', null, true, '/(' . $patterns['pnr'] . ')$/');
        }

        $resDate = $this->http->FindSingleNode('//tr[starts-with(normalize-space(.),"Trip on")]/descendant::text()[contains(.,"Date")]', null, true, '/:\s*([^:]{4,})$/');

        if (!$resDate) {
            $resDate = $this->http->FindSingleNode('//tr[starts-with(normalize-space(.),"Trip on")]/descendant::text()[contains(.,"Date")]/following::text()[normalize-space(.)][1]');
        }

        $pax = $this->http->FindNodes("//text()[normalize-space(.)='Traveler']/following::text()[normalize-space(.)][1]");

        $passengers = [];
        $passengerRows = $this->http->XPath->query('//text()[contains(normalize-space(.),"Passenger Name")]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]');

        foreach ($passengerRows as $passengerRow) {
            if ($passenger = $this->http->FindSingleNode('./td[normalize-space(.)][1]', $passengerRow, true, '/^(\D{2,})$/')) {
                $passengers[] = $passenger;
            }
        }

        $xpath = '//text()[' . $this->eq($this->t('Departure')) . ']/ancestor::tr[1]';
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode('./preceding::tr[normalize-space(.)][2]/descendant::text()[' . $this->contains($this->t('Confirmation')) . ']/following::text()[normalize-space(.)][1]', $root, true, '/^(' . $patterns['pnr'] . ')$/');

            if (!$rl) {
                $rl = $this->http->FindSingleNode('./preceding::tr[normalize-space(.)][2]/descendant::text()[' . $this->contains($this->t('Confirmation')) . ']', $root, true, '/(' . $patterns['pnr'] . ')$/');
            }

            if ($rl) {
                $airs[$rl][] = $root;
            } elseif ($tripNum) {
                $airs[$tripNum][] = $root;
            }
        }

        foreach ($airs as $rl => $nodes) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $tripNum;

            if ($resDate) {
                $it['ReservationDate'] = strtotime($this->normalizeDate($resDate));
            }

            if (!empty($pax[0])) {
                $it['Passengers'] = $pax;
            } elseif (!empty($passengers[0])) {
                $it['Passengers'] = $passengers;
            }

            $ffNumbers = [];
            $ticketNumbers = [];

            foreach ($nodes as $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                // AirlineName
                // FlightNumber
                $flight = $this->http->FindSingleNode('./preceding::tr[normalize-space(.)][1]', $root);

                if (preg_match('/\b([A-Z\d]{2})\s*(\d+)\b/', $flight, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                // Operator
                if (preg_match('/\bOperated\s+by\s*(\w.+\w)/i', $flight, $m)) {
                    $seg['Operator'] = $m[1];
                }

                // Singapore Changi Intl Arpt (Terminal 3) 02:05 - 2017-12-05    |    Istanbul (IST - Terminal INTERNATIONAL) 19:30 - 18 Feb 18

                $xpathFragment1 = '/descendant::text()[normalize-space(.)]';
                $xpathFragment2 = './following-sibling::tr[normalize-space(.)][1][not(contains(.,"Please"))]';

                $departureTexts = $this->http->FindNodes('./*[normalize-space(.)][2]' . $xpathFragment1, $root);
                $departureText = implode(' ', $departureTexts);

                if (!preg_match('/' . $patterns['time'] . '/', $departureText)) { // it-11517021
                    $timeDepTexts = $this->http->FindNodes($xpathFragment2 . '/*[normalize-space(.)][1]' . $xpathFragment1, $root);
                    $departureText .= ' ' . implode(' ', $timeDepTexts);
                }

                $arrivalTexts = $this->http->FindNodes('./*[normalize-space(.)][4]' . $xpathFragment1, $root);
                $arrivalText = implode(' ', $arrivalTexts);

                if (!preg_match('/' . $patterns['time'] . '/', $arrivalText)) {
                    $timeArrTexts = $this->http->FindNodes($xpathFragment2 . '/*[normalize-space(.)][2]' . $xpathFragment1, $root);
                    $arrivalText .= ' ' . implode(' ', $timeArrTexts);
                }

                // DepDate
                $airportDep = '';

                if (preg_match('/(.+) (' . $patterns['time'] . ')[ ]+-[ ]+(' . $patterns['date'] . ')/', $departureText, $matches)) {
                    $airportDep = trim($matches[1]);
                    $seg['DepDate'] = strtotime($matches[3] . ', ' . $matches[2]);
                }

                // DepName
                // DepCode
                // DepartureTerminal
                if (preg_match($patterns['nameCodeTerminal'], $airportDep, $matches)) {
                    $seg['DepName'] = trim($matches[1]);
                    $seg['DepCode'] = $matches[2];
                    $seg['DepartureTerminal'] = str_ireplace('terminal', '', $matches[3]);
                } elseif (preg_match($patterns['nameCode'], $airportDep, $matches)) {
                    $seg['DepName'] = trim($matches[1]);
                    $seg['DepCode'] = $matches[2];
                } elseif (preg_match($patterns['nameTerminal_1'], $airportDep, $matches) || preg_match($patterns['nameTerminal_2'], $airportDep, $matches)) {
                    $seg['DepName'] = trim($matches[1]);
                    $seg['DepartureTerminal'] = str_ireplace('terminal', '', $matches[2]);
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                } elseif ($airportDep) {
                    $seg['DepName'] = $airportDep;
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                // ArrDate
                $airportArr = '';

                if (preg_match('/(.+) (' . $patterns['time'] . ')[ ]+-[ ]+(' . $patterns['date'] . ')/', $arrivalText, $matches)) {
                    $airportArr = trim($matches[1]);
                    $seg['ArrDate'] = strtotime($matches[3] . ', ' . $matches[2]);
                }

                // ArrName
                // ArrCode
                // ArrivalTerminal
                if (preg_match($patterns['nameCodeTerminal'], $airportArr, $matches)) {
                    $seg['ArrName'] = trim($matches[1]);
                    $seg['ArrCode'] = $matches[2];
                    $seg['ArrivalTerminal'] = str_ireplace('terminal', '', $matches[3]);
                } elseif (preg_match($patterns['nameCode'], $airportArr, $matches)) {
                    $seg['ArrName'] = trim($matches[1]);
                    $seg['ArrCode'] = $matches[2];
                } elseif (preg_match($patterns['nameTerminal_1'], $airportArr, $matches) || preg_match($patterns['nameTerminal_2'], $airportArr, $matches)) {
                    $seg['ArrName'] = trim($matches[1]);
                    $seg['ArrivalTerminal'] = str_ireplace('terminal', '', $matches[2]);
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                } elseif ($airportArr) {
                    $seg['ArrName'] = $airportArr;
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                // Cabin
                // BookingClass
                $class = $this->http->FindSingleNode("./following::table[1]/descendant::td[normalize-space(.)='Class']/following-sibling::td[1]", $root);

                if (preg_match('/(.{2,}?)(?:\s*\(([A-Z]{1,2})\))?$/', $class, $m)) { // Economy/Coach (H)
                    $seg['Cabin'] = $m[1];

                    if (!empty($m[2])) {
                        $seg['BookingClass'] = $m[2];
                    }
                }

                // Duration
                // Stops
                $duration = $this->http->FindSingleNode('./following::table[1]/descendant::td[' . $this->eq($this->t('Duration')) . ']/following-sibling::td[1]', $root);

                if (preg_match('/(.{2,}?)[-\s]+(?:\((.+)\))?/', $duration, $m)) {
                    $seg['Duration'] = $m[1];

                    if (!empty($m[2])) {
                        $seg['Stops'] = preg_match('/non[-\s]*stop/i', $m[2]) ? 0 : (preg_match('/(\d+)/', $m[2], $v) ? $v[1] : null);
                    }
                }

                if (empty($seg['Duration'])) {
                    $seg['Duration'] = $this->http->FindSingleNode("./following::table[1]/descendant::td[normalize-space(.)='Flight Duration']/following-sibling::td[1]", $root);
                }

                if (empty($seg['Stops'])) {
                    $stops = $this->http->FindSingleNode("./following::table[1]/descendant::td[normalize-space(.)='Stopover']/following-sibling::td[1]", $root);

                    if (preg_match('/Non[-\s]*Stop/i', $stops)) {
                        $seg['Stops'] = 0;
                    } elseif (preg_match('/^(\d+)/', $stops)) {
                        $seg['Stops'] = $stops;
                    }
                }

                // Aircraft
                $seg['Aircraft'] = $this->http->FindSingleNode("./following::table[1]/descendant::td[normalize-space(.)='Equipment']/following-sibling::td[1]", $root);

                // Seats
                $seat = $this->http->FindSingleNode("./following::table[ position()=1 or (position()=2 and ./descendant::text()[normalize-space(.)='Seat']) ]/descendant::td[normalize-space(.)='Seat']/following-sibling::td[1]", $root, true, '/^(\d{1,2}[A-Z])$/');

                if ($seat) {
                    $seg['Seats'] = [$seat];
                }

                // Meal
                $meal = $this->http->FindSingleNode('./following::table[1]/descendant::td[' . $this->eq($this->t('Meal Service')) . ']/following-sibling::td[1]', $root);

                if ($meal) {
                    $seg['Meal'] = $meal;
                }

                $ffNumber = $this->http->FindSingleNode('./following::table[ position()=1 or (position()=2 and ./descendant::text()[' . $this->eq($this->t('Frequent Flyer')) . ']) ]/descendant::td[' . $this->eq($this->t('Frequent Flyer')) . ']/following-sibling::td[1]', $root, true, '/^(.*\d.*)$/');

                if ($ffNumber) {
                    $ffNumbers[] = $ffNumber;
                }

                $ticketNumber = $this->http->FindSingleNode('./following::table[ position()=1 or (position()=2 and ./descendant::text()[' . $this->eq($this->t('Ticket No')) . ']) ]/descendant::td[' . $this->eq($this->t('Ticket No')) . ']/following-sibling::td[1]', $root, true, '/^(.*\d{5}.*)$/');

                if ($ticketNumber) {
                    $ticketNumbers[] = $ticketNumber;
                }

                $it['TripSegments'][] = $seg;
            }

            // AccountNumbers
            if (!empty($ffNumbers[0])) {
                $it['AccountNumbers'] = array_unique($ffNumbers);
            }

            // TicketNumbers
            if (!empty($ticketNumbers[0])) {
                $it['TicketNumbers'] = array_unique($ticketNumbers);
            }

            $its[] = $it;
        }

        $xpath = "//img[
			contains(@src,'/hotel.') or
			contains(@src,'/picto_hostel_confirmed_bg1.') or
			contains(@src,'/picto_hostel_notTicketed_bg1.') or
			contains(@src,'/picto_hostel_notConfirmed_bg1.')
            ]/ancestor::td[./following-sibling::td][1]/following-sibling::td/descendant::tr[1]/..";

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = "//img[contains(@src,'/default/spacer.')]/following-sibling::*[1][self::img]/ancestor::td[./following-sibling::td][1]/following-sibling::td/descendant::tr[1][{$this->starts($this->t('Hotel'))}]/..";
        }
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $misc = $this->http->XPath->query("./following::table[1]", $root)->item(0);
            $it = [];
            $it['Kind'] = 'R';

            if (isset($reservationDate)) {
                $it['ReservationDate'] = $reservationDate;
            }

            $it['Status'] = $this->nextText($this->t("Booking status"), $misc);

            // ConfirmationNumber
            if (!($it['ConfirmationNumber'] = $this->http->FindSingleNode("./ancestor::tr[1]/../preceding::table[1]/descendant::text()[" . $this->eq($this->t("Booking Reference")) . "]/following::text()[normalize-space(.)][1]", $root, true, "#^(\w+)[\s\*]*$#"))
                && $this->http->FindSingleNode("ancestor::tr[1]/descendant::img[contains(@src,'/picto_hostel_notConfirmed_bg1.')]/@src", $root)) {
                $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            }

            // TripNumber
            // ConfirmationNumbers

            // HotelName
            $it['HotelName'] = $this->http->FindSingleNode("./tr[1]/td[1]", $root, true, "#" . $this->opt($this->t("Hotel")) . "\s+(.+)#");

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[1]/../preceding::table[1]/preceding::text()[normalize-space(.)!=''][1]", $root)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Departure date"), $misc)));

            // Address
            $it['Address'] = $this->http->FindSingleNode("./tr[2]/descendant::text()[normalize-space(.)!=''][2]", $root, false, "#(.+?)(?:TXS NO CARTO|$)#");

            if (empty($it['ConfirmationNumber'])) {
                $it['ConfirmationNumber'] = $this->http->FindSingleNode("./tr[2]/descendant::text()[normalize-space(.)!=''][2]", $root, false, "#" . $this->opt($this->t("TXS NO CARTO")) . "\s+.+?-([A-Z\d]{5,})#");
            }

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->http->FindSingleNode(".//td[" . $this->starts($this->t("Tel.")) . "]", $root, true, "#" . $this->opt($this->t("Tel.")) . "\s+([-\d\s\/]+)$#");

            // Fax
            $it['Fax'] = $this->http->FindSingleNode(".//td[" . $this->starts($this->t("Fax")) . "]", $root, true, "#" . $this->opt($this->t("Fax")) . "\s+([-\d\s\/]+)$#");

            // GuestNames
            $it['GuestNames'] = array_filter([$this->nextText($this->t("Traveler"))]);

            // Guests
            // Kids
            // Rooms
            // Rate
            $it['Rate'] = $this->nextText($this->t("Estimated rate"), $misc);

            // RateType
            // CancellationPolicy
            $it['CancellationPolicy'] = $this->http->FindSingleNode('./descendant::td[' . $this->starts($this->t("Cancellation policy")) . ']/following-sibling::td[normalize-space(.)][1]', $misc);

            // RoomType
            $it['RoomType'] = $this->nextText($this->t("Room type"), $misc);

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            $it['Total'] = $this->nextText('Total amount', $misc, 2);
            // Currency
            $it['Currency'] = $this->nextText('Total amount', $misc);

            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            $it['AccountNumbers'] = array_filter([$this->nextText('Membership ID', $misc)]);

            // Status
            // Cancelled
            // ReservationDate
            // NoItineraries
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\w+)\s+(\d+),\s+(\d{4})\s*$#', // Jun 12, 2017
            '#^\s*(\d+:\d+(?:\s*[ap]m)?),\s+(\w+)\s+(\d+),\s+(\d+)\s*$#i', // 6:45 AM, Aug 16, 2017
            '#^(\d+),\s*(\d+)\s+(\d+)$#', // 27, 10 2017
            '/^(\d{1,2})([^-,.\d\s\/]{3,})(\d{4})$/', // 12Mar2018
        ];
        $out = [
            '$2 $1 $3',
            '$3 $2 $4 $1',
            '$1.$2.$3',
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][{$n}]", $root);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode('|', $field) . ')';
    }
}
