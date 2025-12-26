<?php

namespace AwardWallet\Engine\wagonlit\Email;

use AwardWallet\Engine\MonthTranslate;

class TripItineraryFor extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-11267156.eml, wagonlit/it-18650947.eml, wagonlit/it-18705152.eml, wagonlit/it-20165478.eml, wagonlit/it-4851384.eml, wagonlit/it-4851385.eml, wagonlit/it-4851386.eml, wagonlit/it-4851387.eml, wagonlit/it-6257802.eml, wagonlit/it-9065025.eml, wagonlit/it-9082685.eml";
    public $reFrom = 'info@reservation.carlsonwagonlit.co.uk';
    public $reSubject = [
        'es' => 'Documento de viaje',
        'sv' => 'Resedokument för',
        'fr' => 'Document de voyage',
        'en' => 'Trip itinerary for',
    ];
    public $reBody = '@contactcwt.com';
    public $reBody2 = [
        'es' => 'INFORMACIÓN GENERAL',
        'sv' => 'GENERELL INFORMATION',
        'fr' => 'INFORMATIONS GÉNÉRALES',
        'en' => 'GENERAL INFORMATION',
    ];

    public static $dictionary = [
        'es' => [
            "Trip locator:"      => "Localizador:",
            "Confirmation"       => "Confirmación",
            "Hotel "             => "Hotel ",
            "Departure date"     => "Fecha de salida",
            "LOCATION"           => "DIRECCIÓN",
            "Tel.:"              => "Tel.:",
            "Traveler"           => "Viajero",
            "Estimated rate"     => "NOTTRANSLATED",
            "Cancellation policy"=> "Política de Cancelación",
            "Room type"          => "Tipo de habitación",
            "Booking status"     => "Estado de la reserva",
            //			"Total Ticket:" => "",
            //			"Membership ID" => "",
            //			"Total amount" => "",
        ],
        'sv' => [
            "Trip locator:"      => "Bokningsnummer:",
            "Booking Reference:" => "Bokningsnummer:",
            "Confirmation"       => "Bekräftelse",
            "Hotel "             => "Hotell ",
            "Departure date"     => "AVRESEDATUM",
            "LOCATION"           => "ADRESS",
            "Tel.:"              => "Tel:",
            "Traveler"           => "Resenär",
            "Estimated rate"     => "Rumspris",
            "Cancellation policy"=> "Avbokningsvillkor",
            "Room type"          => "Rumstyp",
            "Booking status"     => "Bokningsstatus",
            //			"Total Ticket:" => "",
            //			"Membership ID" => "",
            "Total amount"   => "Totalpris",
            "Flight "        => "Flyg ",
            "DEP.:"          => "AVG:",
            "ARR.:"          => "ANK:",
            "E-Ticket"       => "Elektronisk biljett",
            "Class"          => "Bokningsklass",
            "Equipment"      => "Flygplanstyp",
            "Seat"           => "Plats",
            "Flight duration"=> "Flygtid",
        ],
        'fr' => [
            "Trip locator:"     => "Référence du dossier:",
            "Booking Reference:"=> "NOTTRANSLATED:",
            "Confirmation"      => "Confirmation",
            "Hotel "            => "Hotel ",
            //			"Departure date"=>"",
            //			"LOCATION"=>"",
            //			"Tel.:"=>"",
            "Traveler"=> "Voyageur",
            //			"Estimated rate"=>"",
            //			"Cancellation policy"=>"",
            //			"Room type"=>"",
            "Booking status"=> "Statut de la Réservation",
            //			"Total Ticket:" => "",
            //			"Membership ID" => "",
            //			"Total amount" => "",
            "Flight " => "Vol ",
            //            "DEP.:"=>"",
            //            "ARR.:"=>"",
            "E-Ticket" => "Billet électronique",
            "Class"    => "Classe",
            "Equipment"=> "Équipement",
            "Coach"    => "Voiture",
            "Seat"     => "Siège",
            //            "Flight duration"=>"",
            "Duration" => "Durée",
            "DEPARTURE"=> "DÉPART",
            "ARRIVAL"  => "ARRIVÉE",
        ],
        'en' => [],
    ];

    public $lang = '';

    public function parseHtml(&$itineraries)
    {
        $tripNum = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Trip locator:'))}]/following::text()[normalize-space(.)!=''][1])[1]");

        $xpath = "//img[contains(@src, '/default/picto_')]/ancestor::tr[2]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];
        $hotels = [];
        $trains = [];

        foreach ($nodes as $root) {
            $type = $this->http->FindSingleNode(".//img[contains(@src, '/default/picto_')]/@src", $root, true, "#/default/picto_([a-z]+)#");

            switch ($type) {
                case 'flight':
                    if (!$rl = $this->nextText($this->t("Booking Reference:"), $root, "#([A-Z\d]{5,})#")) {
                        if (!$rl = $this->nextText($this->t("Booking Reference:"), $root)) {
                            $this->logger->info("RL not matched");

                            return false;
                        } elseif (strpos($rl, '---') !== false) {
                            $rl = CONFNO_UNKNOWN;
                        }
                    }
                    $airs[$rl][] = $root;

                break;

                case 'hostel':
                    $hotels[] = $root;

                break;

                case 'train':
                    $trains[] = $root;

                break;

                default:
                    $this->logger->info("unknown type '{$type}'");

                    return false;
            }
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $tripNum;

            // Passengers
            $it['Passengers'] = [$this->nextText($this->t("Traveler"))];

            // AccountNumbers
            // Cancelled
            // BaseFare
            // TotalCharge
            // Currency
            if (count($airs) == 1) {
                $total = $this->nextText($this->t("Total Ticket:"));

                if (!empty($total)) {
                    $it['TotalCharge'] = $this->amount($total);
                    $it['Currency'] = $this->currency($total);
                }
            }
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory

            foreach ($roots as $root) {
                // TicketNumbers
                $it['TicketNumbers'][] = $this->nextText($this->t("E-Ticket"), $root);

                $rowsXpath = "./following-sibling::tr[position()<7]";
                $itsegment = [];

                // AirlineName
                // FlightNumber
                $flight = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Flight ")) . "]", $root);

                if (preg_match('/([A-Z\d]{2})(\d+)$/', $flight, $matches)) {
                    $itsegment['AirlineName'] = $matches[1];
                    $itsegment['FlightNumber'] = $matches[2];
                }

                $pattern1 = '/^[^:]+:\s*(?<name>.+?)\s*\(\s*(?<codeTerminal>[^)(]{3,}?\s*)\)\s*\|\s*(?<dateTime>.+)/'; // DEP.: Lyon Saint Exupery (LYS - Terminal 1B) | Fri 20 Apr 18 - 18:20
                $pattern2 = '/([A-Z]{3})\s*-\s*(.+\b)/'; // LYS - Terminal 1B

                // DepName
                // DepCode
                // DepartureTerminal
                // DepDate
                $departure = $this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("DEP.:")) . "]/ancestor::tr[1]", $root);

                if (preg_match($pattern1, $departure, $matches)) {
                    $itsegment['DepName'] = $matches['name'];

                    if (preg_match($pattern2, $matches['codeTerminal'], $m)) {
                        $itsegment['DepCode'] = $m[1];
                        $itsegment['DepartureTerminal'] = trim(str_ireplace('Terminal', '', $m[2]));
                    } else {
                        $itsegment['DepCode'] = $matches['codeTerminal'];
                    }

                    $itsegment['DepDate'] = strtotime($this->normalizeDate($matches['dateTime']));
                }

                // ArrCode
                // ArrName
                // ArrivalTerminal
                // ArrDate
                $arrival = $this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("ARR.:")) . "]/ancestor::tr[1]", $root);

                if (preg_match($pattern1, $arrival, $matches)) {
                    $itsegment['ArrName'] = $matches['name'];

                    if (preg_match($pattern2, $matches['codeTerminal'], $m)) {
                        $itsegment['ArrCode'] = $m[1];
                        $itsegment['ArrivalTerminal'] = trim(str_ireplace('Terminal', '', $m[2]));
                    } else {
                        $itsegment['ArrCode'] = $matches['codeTerminal'];
                    }

                    $itsegment['ArrDate'] = strtotime($this->normalizeDate($matches['dateTime']));
                }

                // Aircraft
                $itsegment['Aircraft'] = $this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("Equipment")) . "]/following::text()[normalize-space(.)][1]", $root);

                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("Class")) . "]/following::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\(\w\)#");

                // BookingClass
                $itsegment['BookingClass'] = $this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("Class")) . "]/following::text()[normalize-space(.)][1]", $root, true, "#\((\w)\)#");

                // Seats
                $seat = $this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("Seat")) . "]/following::text()[normalize-space(.)][1]", $root, true, '/\d{1,2}[A-Z]\b/');

                if ($seat) {
                    $itsegment['Seats'] = [$seat];
                }

                // Duration
                // Stops
                $flightDuration = $this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("Flight duration")) . "]/following::text()[normalize-space(.)][1]", $root);

                if (preg_match('/^(\d.*?)\s*\(([^)(]+)\)$/', $flightDuration, $matches)) { // 03:35 (non-stop)
                    $itsegment['Duration'] = $matches[1];

                    if (preg_match('/non[-\s]*stop/i', $matches[2])) {
                        $itsegment['Stops'] = 0;
                    } elseif (preg_match('/(\d+)/', $matches[2], $m)) {
                        $itsegment['Stops'] = $m[1];
                    }
                } elseif (preg_match('/^\d.*/', $flightDuration)) { // 03:35
                    $itsegment['Duration'] = $flightDuration;
                }

                $it['TripSegments'][] = $itsegment;
            }
            $it['TicketNumbers'] = array_unique(array_filter($it['TicketNumbers']));
            $itineraries[] = $it;
        }

        //################
        //##   TRAIN   ###
        //################
        foreach ($trains as $root) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $this->nextText($this->t("Confirmation"));

            if (strlen($it['RecordLocator']) < 3) {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }

            // TripNumber
            $it['TripNumber'] = $tripNum;

            // Passengers
            $it['Passengers'] = [$this->nextText($this->t("Traveler"))];
            // AccountNumbers
            $it['AccountNumbers'] = [$this->re("#^[^-]+$#", $this->nextText($this->t("Membership ID")))];

            if (count($airs) == 1) {
                $total = $this->nextText($this->t("Total Ticket:"));

                if (!empty($total)) {
                    $it['TotalCharge'] = $this->amount($total);
                    $it['Currency'] = $this->currency($total);
                }
            }
            // Status
            $it['Status'] = $this->nextText($this->t("Booking status"));
            // TripCategory
            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            // TicketNumbers
            $it['TicketNumbers'][] = $this->nextText($this->t("E-Ticket"), $root);

            $rowsXpath = "./following-sibling::tr[position()<7]";
            $itsegment = [];

            // FlightNumber
            $flight = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Train ")) . "]", $root);
            $itsegment['FlightNumber'] = $this->re("#(\d+)$#", $flight);
            $itsegment['Type'] = $this->re("#{$this->t("Train ")} *(.+?) *\d+$#u", $flight);

            $pattern1 = '/^\w+\s+(?<name>.+?)\s*(?<dateTime>\d+:\d+.+)/u'; // DEPARTURE	LBG 18:11 - 05 Jun 16

            // DepName
            // DepCode
            // DepDate
            $departure = $this->http->FindSingleNode("(" . $rowsXpath . "//text()[" . $this->eq($this->t("DEPARTURE")) . "])[1]/ancestor::tr[1]",
                $root);

            if (preg_match($pattern1, $departure, $matches)) {
                if (preg_match("#^[A-Z]{3}$#", $matches['name'])) {
                    $itsegment['DepCode'] = $itsegment['DepName'] = $matches['name'];
                } else {
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                    $itsegment['DepName'] = $matches['name'];
                }
                $itsegment['DepDate'] = strtotime($this->normalizeDate($matches['dateTime']));
            }

            // ArrCode
            // ArrName
            // ArrDate
            $arrival = $this->http->FindSingleNode("(" . $rowsXpath . "//text()[" . $this->eq($this->t("ARRIVAL")) . "])[1]/ancestor::tr[1]",
                $root);

            if (preg_match($pattern1, $arrival, $matches)) {
                if (preg_match("#^[A-Z]{3}$#", $matches['name'])) {
                    $itsegment['ArrCode'] = $itsegment['ArrName'] = $matches['name'];
                } else {
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                    $itsegment['ArrName'] = $matches['name'];
                }
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($matches['dateTime']));
            }

            // Vehicle
            $itsegment['Vehicle'] = $this->http->FindSingleNode("(" . $rowsXpath . "//text()[" . $this->eq($this->t("Equipment")) . "])[1]/following::text()[normalize-space(.)][1]",
                $root);

            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("(" . $rowsXpath . "//text()[" . $this->eq($this->t("Class")) . "])[1][1]/following::text()[normalize-space(.)][1]",
                $root);

            // Seats
            $seat = $this->http->FindSingleNode("(" . $rowsXpath . "//text()[" . $this->eq($this->t("Seat")) . "])[1]/following::text()[normalize-space(.)][1]",
                $root, true, '/^[^-]+$/');
            $coach = $this->http->FindSingleNode("(" . $rowsXpath . "//text()[" . $this->eq($this->t("Coach")) . "])[1]/following::text()[normalize-space(.)][1]",
                $root, true, '/^[^-]+$/');

            if ($seat && $coach && $seat != 'Not specified' && $coach != 'Not specified') {
                $itsegment['Seats'] = ["Coach: " . $coach . " Seat: " . $seat];
            }

            // Duration
            // Stops
            $flightDuration = $this->http->FindSingleNode("(" . $rowsXpath . "//text()[" . $this->eq($this->t("Duration")) . "])[1]/following::text()[normalize-space(.)][1]",
                $root);

            if (preg_match('/^(\d.*?)\s*\(([^)(]+)\)$/', $flightDuration, $matches)) { // 03:35 (non-stop)
                $itsegment['Duration'] = $matches[1];

                if (preg_match('/non[-\s]*stop/i', $matches[2])) {
                    $itsegment['Stops'] = 0;
                } elseif (preg_match('/(\d+)/', $matches[2], $m)) {
                    $itsegment['Stops'] = $m[1];
                }
            } elseif (preg_match('/^\d.*/', $flightDuration)) { // 03:35
                $itsegment['Duration'] = $flightDuration;
            }

            $it['TripSegments'][] = $itsegment;
            $it['TicketNumbers'] = array_unique(array_filter($it['TicketNumbers']));
            $itineraries[] = $it;
        }

        //#################
        //##   HOTELS   ###
        //#################
        foreach ($hotels as $root) {
            $rowsXpath = "./following-sibling::tr[position()<7]";
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->nextText($this->t("Confirmation"), $root);

            // TripNumber
            $it['TripNumber'] = $tripNum;

            // ConfirmationNumbers

            // Hotel Name
            $it['HotelName'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Hotel ")) . "]", $root, true, "#" . $this->t("Hotel ") . "(.+)#");

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Hotel ")) . "]/preceding::text()[normalize-space(.)][1]", $root)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("Departure date")) . "]/following::text()[normalize-space(.)][1]", $root)));

            // Address
            $it['Address'] = $this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("LOCATION")) . "]/following::text()[normalize-space(.)][1]", $root);

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("Tel.:")) . "]/following::text()[normalize-space(.)][1]", $root);

            // Fax
            // GuestNames
            $it['GuestNames'] = [$this->nextText($this->t("Traveler"))];

            // Guests
            // Kids
            // Rooms
            // Rate
            $it['Rate'] = $this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("Estimated rate")) . "]/following::text()[normalize-space(.)][1]", $root);

            // RateType
            // CancellationPolicy
            $it['CancellationPolicy'] = $this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("Cancellation policy")) . "]/following::text()[normalize-space(.)][1]", $root);

            // RoomType
            $it['RoomType'] = $this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("Room type")) . "]/following::text()[normalize-space(.)][1]", $root);

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            // Currency
            $total = $this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("Total amount")) . "]/following::text()[normalize-space(.)][1]", $root);

            if (!empty($total)) {
                $it['Total'] = $this->amount($total);
                $it['Currency'] = $this->currency($total);
            }
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            $account = trim($this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("Membership ID")) . "]/following::text()[normalize-space(.)][1]", $root), ' -');

            if (!empty($account)) {
                $it['AccountNumbers'][] = $account;
            }

            // Status
            $it['Status'] = $this->http->FindSingleNode($rowsXpath . "//text()[" . $this->eq($this->t("Booking status")) . "]/following::text()[normalize-space(.)][1]", $root);

            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
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
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        //$year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+ (\d+) ([^\s\d]+) (\d{2}) - (\d+:\d+)$#", //Tue 14 Feb 17 - 17:40
            "#^[^\s\d]+ (\d+) ([^\s\d]+), (\d{4})$#", //Tue 14 February, 2017
            "#^(\d+:\d+) - (\d+) (\w+) (\d{2})$#u", //18:11 - 05 Jun 16
        ];
        $out = [
            "$1 $2 20$3, $4",
            "$1 $2 $3",
            "$2 $3 20$4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null, $regexp = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root, true, $regexp);
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
