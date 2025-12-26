<?php

namespace AwardWallet\Engine\tport\Email;

use AwardWallet\Engine\MonthTranslate;

class ItineraryDetailed extends \TAccountChecker
{
    public $mailFiles = "tport/it-1733942.eml, tport/it-2012256.eml, tport/it-2046563.eml, tport/it-2046571.eml, tport/it-2631074.eml, tport/it-3284982.eml, tport/it-5458161.eml, tport/it-5468502.eml, tport/it-5480439.eml, tport/it-5506409.eml, tport/it-5559303.eml, tport/it-5560916.eml, tport/it-5625875.eml, tport/it-7835252.eml, tport/it-7991162.eml, tport/it-8889347.eml, tport/it-8889394.eml, tport/it-8889563.eml, tport/it-8889714.eml";
    public $reFrom = "@travelport.com";
    public $reSubject = [
        "en"  => "Itinerary – Detailed",
        "it"  => "Itinerario – in dettaglio",
        "es"  => "Itinerario – detallado",
        "pt"  => "Itinerário – Detalhado",
        "pt2" => "Itinerário – Pormenorizado",
    ];
    public $reBody = 'Travelport';
    public $reBody2 = [
        "en"  => "Itinerary Information",
        "it"  => "Informazioni sull’itinerario",
        "es"  => "Información del itinerario",
        "pt"  => "Informações sobre o itinerário",
        "pt2" => "Informações de Itinerário",
    ];

    public static $dictionary = [
        "en" => [
            "OPERATEDBY" => "NOTTRANSLATED",
            'Arrive:'    => ['Arrive', 'Arrive:'],
        ],
        "it" => [
            //flight
            "Confirmation Number:" => "Numero di conferma:",
            "Reservation ID:"      => "ID prenotazione:",
            "Ticket Numbers"       => "Numeri biglietti",
            "Status"               => "Status",
            "Terminal "            => "Terminal ",
            "Depart:"              => "Partenza:",
            "Arrive:"              => "Arrivo:",
            "Equipment:"           => "Aeromobile:",
            "Class of Service:"    => "Classe di servizio:",
            "Seat"                 => "NOTTRANSLATED",
            "Flying Time:"         => "Tempo di volo:",
            "Meal Service:"        => "NOTTRANSLATED",
            "OPERATEDBY"           => "Volo operato da:",
        ],
        "es" => [
            //flight
            "Confirmation Number:" => "Número de confirmación:",
            "Reservation ID:"      => "Identificador de la reserva:",
            "Ticket Numbers"       => "Números de billete",
            "Status"               => "Estado",
            "Terminal "            => "Terminal ",
            "Depart:"              => "Salida:",
            "Arrive:"              => "Llegada:",
            "Equipment:"           => "Equipo:",
            "Class of Service:"    => "Clase de servicio:",
            "Seat"                 => "NOTTRANSLATED",
            "Flying Time:"         => "Hora del vuelo:",
            "Meal Service:"        => "Servicio de comidas:",
            "OPERATEDBY"           => "NOTTRANSLATED",
        ],
        "pt" => [
            "Confirmation Number:"=> ["Localizador da reserva:", "Número de Confirmação:"],
            "Reservation ID:"     => ["Localizador Galileo:", "Identificação da Reserva:"],
            //hotel
            "Hotel"                               => "Hotel",
            "Check in"                            => "Check-in",
            "Check In Time:"                      => "NOTTRANSLATED",
            "Check Out:"                          => "Check-out:",
            "Check Out Time:"                     => "NOTTRANSLATED",
            "Phone:"                              => "NOTTRANSLATED",
            "Fax:"                                => "NOTTRANSLATED",
            "Number of Guests:"                   => "NOTTRANSLATED",
            "Room"                                => "NOTTRANSLATED",
            "Estimated Hotel Rate*:"              => "NOTTRANSLATED",
            "ROOM TYPE"                           => "NOTTRANSLATED",
            "Approximate Total, including taxes:" => "Taxa de Hotel *:",
            //flight

            "Ticket Numbers"   => "Números de passagem",
            "Status"           => ["Status", "Estado"],
            "Terminal "        => "Terminal ",
            "Depart:"          => ["Saída:", "Partida:"],
            "Arrive:"          => "Chegada:",
            "Equipment:"       => "Equipamento:",
            "Class of Service:"=> ["Classe de serviço:", "Classe de Serviço:"],
            "Seat"             => ["Assento", "Lugar"],
            "Flying Time:"     => ["Tempo de vôo:", "Tempo de Voo:"],
            "Meal Service:"    => ["Serviço de refeição:", "Serviço de Refeições:"],
            "OPERATEDBY"       => "Vôo operado por:",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        //##############
        //##   CAR   ###
        //##############
        $xpath = "//img[contains(@src, '.wlCAR.gif')]/ancestor::table[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "L";

            // Number
            $it['Number'] = $this->nextText($this->t("Confirmation Number:"), $root);

            // TripNumber
            $it['TripNumber'] = $this->nextText($this->t("Reservation ID:"));

            // PickupDatetime
            $it['PickupDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq("Pick Up:") . "]/ancestor::td[1]/following-sibling::td[1]", $root)));

            // PickupLocation
            $it['PickupLocation'] = trim($this->http->FindSingleNode(".//img[contains(@src, '.wlCAR.gif')]/following::text()[normalize-space(.)][1]", $root, true, "#([^-]+)$#"));

            // DropoffDatetime
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq("Return:") . "]/ancestor::td[1]/following-sibling::td[1]", $root)));

            // DropoffLocation
            $it['DropoffLocation'] = $it['PickupLocation'];

            // PickupPhone
            $it['PickupPhone'] = $this->nextText("Phone:", $root);

            // PickupFax
            // PickupHours
            $it['PickupHours'] = $this->nextText("Location Hours:", $root);

            // DropoffPhone
            // DropoffHours
            // DropoffFax
            // RentalCompany
            $it['RentalCompany'] = $this->http->FindSingleNode(".//img[contains(@src, '.wlCAR.gif')]/following::text()[normalize-space(.)][1]", $root, true, "#Car\s+-\s+(.*?)\s+-\s+#");

            // CarType
            $it['CarType'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Confirmation Number:") . "]/preceding::text()[normalize-space(.)][1]", $root);

            // CarModel
            // CarImageUrl
            // RenterName
            $it['RenterName'] = $this->nextText($this->t("Traveler"));

            // PromoCode
            // TotalCharge
            $it['TotalCharge'] = $this->amount($this->nextText("Approximate Total, including taxes:", $root));

            // Currency
            $it['Currency'] = $this->currency($this->nextText("Approximate Total, including taxes:", $root));

            // TotalTaxAmount
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            $it['Status'] = $this->http->FindSingleNode("./following::text()[normalize-space(.)][1][" . $this->eq("Status") . "]/following::text()[normalize-space(.)][1]", $root);

            // Cancelled
            // ServiceLevel
            // PricedEquips
            // Discount
            // Discounts
            // Fees
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        //################
        //##   TRAIN   ###
        //################
        $xpath = "//img[contains(@src, '.wlTrain.gif')]/ancestor::table[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = CONFNO_UNKNOWN;

            // TripNumber
            $it['TripNumber'] = $this->nextText($this->t("Reservation ID:"));

            // Passengers
            $it['Passengers'] = $this->http->FindNodes("//img[contains(@src, '.wlPeople.gif')]/following::tr[1]/descendant::b[normalize-space(.)]");

            // TicketNumbers
            // AccountNumbers
            // Cancelled
            // TotalCharge
            // BaseFare
            // Currency
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            $it['Status'] = $this->http->FindSingleNode("./following::text()[normalize-space(.)][1][" . $this->eq("Status") . "]/following::text()[normalize-space(.)][1]", $root);

            // ReservationDate
            // NoItineraries
            // TripCategory
            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            // foreach($nodes as $root){
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode(".//img[contains(@src, '.wlTrain.gif')]/following::text()[normalize-space(.)][3]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[" . $this->contains("Train Number") . "]", $root, true, "#Train Number\s+(\d+)#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->nextText("Depart:", $root, 2);

            // DepAddress
            // DepDate
            $itsegment['DepDate'] = strtotime($this->nextText("Depart:", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->nextText("Arrive:", $root, 2);

            // ArrAddress
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->nextText("Arrive:", $root), $date);

            // Type
            $itsegment['Type'] = $this->http->FindSingleNode(".//img[contains(@src, '.wlTrain.gif')]/following::text()[normalize-space(.)][2]", $root);

            // Vehicle
            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#(.*?)\s+\(\w\)#", $this->nextText("Class of Service:", $root));

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#\((\w)\)#", $this->nextText("Class of Service:", $root));

            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops
            $itsegment['Stops'] = $this->http->FindSingleNode(".//text()[" . $this->contains("Train Number") . "]/following::text()[normalize-space(.)][1]", $root);

            $it['TripSegments'][] = $itsegment;
            // }
            $itineraries[] = $it;
        }

        //################
        //##   Hotel   ###
        //################
        $xpath = "//img[contains(@src, '.wlHTL.gif')]/ancestor::table[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->nextText($this->t("Confirmation Number:"), $root);

            // TripNumber
            $it['TripNumber'] = $this->nextText($this->t("Reservation ID:"));

            // ConfirmationNumbers

            // Hotel Name
            $it['HotelName'] = $this->http->FindSingleNode(".//img[contains(@src, '.wlHTL.gif')]/following::text()[normalize-space(.)][1]", $root, true, "#" . $this->t("Hotel") . "\s+-\s+(.+)#");

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Check in"), $root)));

            if ($time = $this->nextText($this->t("Check In Time:"), $root)) {
                $it['CheckInDate'] = strtotime($this->normalizeDate($time), $it['CheckInDate']);
            }

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Check Out:"), $root)));

            if ($time = $this->nextText($this->t("Check Out Time:"), $root)) {
                $it['CheckOutDate'] = strtotime($this->normalizeDate($time), $it['CheckInDate']);
            }

            // Address
            if (!$it['Address'] = implode(" ", $this->http->FindNodes(".//text()[" . $this->eq($this->t("Phone:")) . "]/ancestor::tr[1]/td[1]/descendant::text()[normalize-space(.)]", $root))) {
                $it['Address'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Confirmation Number:")) . "]/preceding::text()[normalize-space(.)][1]", $root);
            }

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->nextText($this->t("Phone:"), $root);

            // Fax
            $it['Fax'] = $this->nextText($this->t("Fax:"), $root);

            // GuestNames
            $it['GuestNames'] = $this->http->FindNodes("//img[contains(@src, '.wlPeople.gif')]/following::tr[1]/descendant::b[normalize-space(.)]");

            // Guests
            $it['Guests'] = $this->re("#(\d+)\s+Guest#", $this->nextText($this->t("Number of Guests:"), $root));

            // Kids
            // Rooms
            $it['Rooms'] = $this->re("#(\d+)\s+" . $this->t("Room") . "#", $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Number of Guests:")) . "]/preceding::text()[normalize-space(.)][1]", $root));

            // Rate
            $it['Rate'] = $this->nextText($this->t("Estimated Hotel Rate*:"), $root);

            // RateType
            // CancellationPolicy
            // RoomType
            $it['RoomType'] = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("ROOM TYPE")) . "]", $root, true, "#" . $this->t("ROOM TYPE") . "\s*:\s*(.+)#");

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            $it['Total'] = $this->amount($this->nextText($this->t("Approximate Total, including taxes:"), $root));

            // Currency
            $it['Currency'] = $this->currency($this->nextText($this->t("Approximate Total, including taxes:"), $root));

            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            $it['Status'] = $this->http->FindSingleNode("./following::text()[normalize-space(.)][1][" . $this->eq($this->t("Status")) . "]/following::text()[normalize-space(.)][1]", $root);

            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }
        //#################
        //##   Flight   ###
        //#################
        if (count($this->http->FindNodes("//img[contains(@src, '.wlAir3.gif')]/ancestor::table[2]")) > 0) {
            $xpath = "//img[contains(@src, '.wlAir3.gif')]/ancestor::table[2]";
            $nodes = $this->http->XPath->query($xpath);
            $airs = [];

            foreach ($nodes as $root) {
                if (!$rl = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Confirmation Number:")) . "]/following::text()[normalize-space(.)][1]", $root)) {
                    $rl = CONFNO_UNKNOWN;
                }
                $airs[$rl][] = $root;
            }

            foreach ($airs as $rl=>$roots) {
                $it = [];

                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = $rl;

                // TripNumber
                $it['TripNumber'] = $this->nextText($this->t("Reservation ID:"));

                // Passengers
                $it['Passengers'] = $this->http->FindNodes("//img[contains(@src, '.wlPeople.gif')]/following::tr[1]/descendant::b[normalize-space(.)]");

                // TicketNumbers
                $it['TicketNumbers'] = [];

                foreach ($roots as $root) {
                    $it['TicketNumbers'] = array_merge($it['TicketNumbers'], $this->http->FindNodes(".//text()[" . $this->contains($this->t("Ticket Numbers")) . "]/following::text()[normalize-space(.)][1]", $root));
                }
                $it['TicketNumbers'] = array_unique($it['TicketNumbers']);

                // AccountNumbers
                // Cancelled
                // TotalCharge
                // BaseFare
                // Currency
                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                $it['Status'] = $this->http->FindSingleNode("./following::text()[normalize-space(.)][1][" . $this->eq($this->t("Status")) . "]/following::text()[normalize-space(.)][1]", $roots[0]);

                // ReservationDate
                // NoItineraries
                // TripCategory
                foreach ($roots as $root) {
                    $date = strtotime($this->normalizeDate($this->http->FindSingleNode(".//img[contains(@src, '.wlAir3.gif')]/ancestor::tr[1]/td[2]", $root)));

                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//img[contains(@src, '.wlAir3.gif')]/ancestor::td[1]", $root, true, "#-\s+(\d+)#");

                    // DepCode
                    // DepName
                    // DepartureTerminal
                    // ArrCode
                    // ArrName
                    // ArrivalTerminal
                    if (preg_match("#^(?<DepName>[^(]*?)\s*\((?<DepCode>[A-Z]{3})\)(?:,\s+(?<DepartureTerminal>" . $this->t("Terminal ") . "\w+))?\s+" .
                                "(?<ArrName>[^(]*?)\s*\((?<ArrCode>[A-Z]{3})\)(?:,\s+(?<ArrivalTerminal>" . $this->t("Terminal ") . "\w+))?#", implode(" ", $this->http->FindNodes("./descendant::text()[normalize-space(.)][position()<15]", $root)), $m)) {
                        $keys = ["DepCode", "DepName", "DepartureTerminal", "ArrCode", "ArrName", "ArrivalTerminal"];

                        foreach ($keys as $key) {
                            if (isset($m[$key])) {
                                $itsegment[$key] = trim($m[$key]);
                            }
                        }
                    } else {
                        $itsegment['DepCode'] = $this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Depart:")) . "])[1]/ancestor::tr[1]/td[3]", $root, true, "#\(([A-Z]{3})\)#");
                        $itsegment['DepName'] = $this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Depart:")) . "])[1]/ancestor::tr[1]/td[3]", $root, true, "#(.*?)\s*\([A-Z]{3}\)#");
                        $itsegment['ArrCode'] = $this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Arrive:")) . "])[1]/ancestor::tr[1]/td[3]", $root, true, "#\(([A-Z]{3})\)#");
                        $itsegment['ArrName'] = $this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Arrive:")) . "])[1]/ancestor::tr[1]/td[3]", $root, true, "#(.*?)\s*\([A-Z]{3}\)#");
                    }

                    // DepDate
                    $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Depart:")) . "])[1]/ancestor::tr[1]/td[2]", $root)), $date);

                    // ArrDate
                    $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Arrive:")) . "])[1]/ancestor::tr[1]/td[2]", $root)), $date);

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode(".//img[contains(@src, '.wlAir3.gif')]/ancestor::td[1]", $root, true, "#\(([A-Z\d]{2})\)#");

                    // Operator
                    $itsegment['Operator'] = $this->nextText($this->t("OPERATEDBY"), $root);

                    // Aircraft
                    $itsegment['Aircraft'] = $this->nextText($this->t("Equipment:"), $root);

                    // TraveledMiles
                    // AwardMiles
                    // Cabin
                    $itsegment['Cabin'] = $this->re("#(.*?)(?:\s+\(\w\)|$)#", $this->nextText($this->t("Class of Service:"), $root));

                    // BookingClass
                    $itsegment['BookingClass'] = $this->re("#\((\w)\)#", $this->nextText($this->t("Class of Service:"), $root));

                    // PendingUpgradeTo
                    // Seats
                    $itsegment['Seats'] = $this->http->FindNodes(".//text()[" . $this->eq($this->t("Seat")) . "]/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space(.)][1]", $root, "#^\d+\w#");

                    // Duration
                    $itsegment['Duration'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Flying Time:")) . "]/following::text()[string-length(normalize-space(.))>1][1]", $root);

                    // Meal
                    $itsegment['Meal'] = $this->nextText($this->t("Meal Service:"), $root);

                    // Smoking
                    // Stops

                    $it['TripSegments'][] = $itsegment;
                }

                $itineraries[] = $it;
            }
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
        $body = $this->http->Response['body'];

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

        $this->http->FilterHTML = true;
        $this->http->setBody($parser->getHtmlBody());
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = trim($lang, '1234567890');

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'ItineraryDetailed' . ucfirst($this->lang),
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
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s*(\d+:\d+\s+[AP]M)$#", //Tuesday, May 16, 20179:00 AM
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#", //Saturday, August 08, 2015
            "#^12\s+Noon$#", //Friday 31 October 2014, 12 Noon
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#", //Friday 31 October 2014
            "#^(\d+:\d+\s+[AP]M)\s*[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#", //10:30 PM Saturday 28 June 2014
            "#^(\d+:\d+\s+[AP]M)\s*[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#", //8:50 PMThursday, May 21, 2015
            "#^[^\d\s]+,\s+(\d+)\s+de\s+([^\d\s]+)\s+de\s+(\d{4})$#", //Viernes, 30 de Diciembre de 2016
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+\s+[AP]M)$#", //Thursday, August 20, 2015, 3:00 PM
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4}),\s+12\s+Noon$#", //Thursday, August 20, 2015, 12 Noon
            "#^(\d+:\d+(?:\s+[AP]M)?)\s*[^\d\s]+,\s+(\d+)\s+de\s+([^\d\s]+)\s+de\s+(\d{4})$#", //4:45 PMSábado, 12 de Setembro de 2015
            "#^(\d+:\d+)\s+\w+\s+(\d+)\s+(\w+)\s+(\d+)$#", // 17:10 sabato 24 January 2015
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3",
            "12:00",
            "$1 $2 $3",
            "$2 $3 $4, $1",
            "$3 $2 $4, $1",
            "$1 $2 $3",
            "$2 $1 $3, $4",
            "$2 $1 $3, 12:00",
            "$2 $3 $4, $1",
            '$2 $3 $4, $1',
        ];
        $str = preg_replace($in, $out, $str);
        // $this->http->log($str);
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

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s|\d)#", $s)) {
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
