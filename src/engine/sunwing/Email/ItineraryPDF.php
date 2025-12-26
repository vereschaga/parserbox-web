<?php

namespace AwardWallet\Engine\sunwing\Email;

use AwardWallet\Engine\MonthTranslate;

class ItineraryPDF extends \TAccountChecker
{
    public $mailFiles = "sunwing/it-10549595.eml, sunwing/it-10554834.eml, sunwing/it-10645250.eml, sunwing/it-10654345.eml, sunwing/it-10655325.eml, sunwing/it-10679599.eml, sunwing/it-683086788.eml, sunwing/it-710577763.eml";

    public $reFrom = "@sunwing.ca";
    public $reSubject = [
        "en"  => "Sunwing Vacations Booking Invoice",
        "en2" => "Sunwing Vacations eDocuments",
    ];
    public $reBody = 'sunwing.ca';
    public $reBody2 = [
        "en"   => "Travel Itinerary",
        "en2"  => "Trip Information",
        "fr"   => "Itinéraire de voyage",
        "fr2"  => 'Infos de voyage',
    ];

    public static $dictionary = [
        "en" => [
            //            "Base Fare" => "",
            //            "Payment Type" => "",
            //            "Grand Total Amount" => "",

            // Flight
            //            "Booking" => "",
            //            "Passenger Summary" => "",
            //            "Passenger(s)" => "",
            //            "Flight Itinerary" => "",
            //            "Seat(s)" => "",
            "Flight Summary\n"   => ["Flight Summary\n", "Flight Itinerary\n"],
            "flightsSectionEnds" => [
                "Products Summary\n",
                "Product Information\n",
                "Payment Summary\n",
                "Advice to International Passengers",
                "Travel Information Guide\n",
            ],
            "flightSegmentEnds" => ["Seat number(s) selected", "Airport Information ", "This flight is operated by"],
            //            "is operated by" => "",
            //            "Flight" => "",
            //            "From" => "",
            //            "To" => "",
            //            "Terminal" => "",
            //            "Aircraft" => "",
            //            "Class" => "",
            //            "Seat number(s) selected:" => "",

            // Hotel
            "Product Information\n" => ["Product Information\n", "Products Summary\n"],
            "hotelsSectionEnds"     => [
                "\nPlease note transfers are not included with your selected package",
                " Please note __transfers__",
                "Payment Summary\n",
                "Advice to International Passengers",
                "Travel Information Guide\n",
                "Airport Services",
            ],
            "productsStartSegments" => [
                "Hotel Name Check In ",
                "Additional Products Date",
                "Transfer",
                "Insurance Date",
                "Transfers Date",
                "Airport Services Date",
                "Supplementary Products Date",
                "Other Products Date",
            ],
            //            "Hotel Name" => "",
            //            "The following passenger" => "",
            //            "Check In" => "",
            //            "Check Out" => "",
            //            "Room Type" => "",
        ],
        "fr" => [
            "Base Fare"          => "Tarif de base",
            "Payment Type"       => "Récapitulatif du type de paiement",
            "Grand Total Amount" => "Montant total",

            // Flight
            "Booking"           => "Réservation",
            "Passenger Summary" => "Résumé des passager (s)",
            "Passenger(s)"      => "Passager(s)",
            "Flight Itinerary"  => ["Itinéraire de vol", "Résumé de vol"],
            //            "Seat(s)" => "",
            "Flight Summary\n"   => ["Itinéraire de vol\n", "Résumé de vol\n"],
            "flightsSectionEnds" => [
                "Information de produit\n",
                "Information d’assurance\n",
                "CONDITIONS OF CONTRACT\n",
                "Conseils aux passagers internationaux concernant les limites de responsabilité",
                'Résumé des produits',
            ],
            "flightSegmentEnds" => ["Pour connaitre les horaires de vol, composez le", "Pour etre au courant de toute information de vol",
                'Siège(s) sélectionné(s)', ],
            //            "is operated by" => "",
            "Flight" => "Vol",
            "From"   => "De",
            "To"     => ["Vers", "À"],
            //            "Terminal" => "",
            "Aircraft"                 => ["Appareil", "Avion"],
            "Class"                    => "Classe",
            "Seat number(s) selected:" => "Siège(s) sélectionné(s):",

            // Hotel
            "Product Information\n" => ["Information de produit\n", "Résumé des produits\n"],
            "hotelsSectionEnds"     => [
                "Information d’assurance\n",
                "CONDITIONS OF CONTRACT\n",
                "Conseils aux passagers internationaux concernant les limites de responsabilité",
                'Produits additionnels',
            ],
            "productsStartSegments" => [
                "Nom de l’hôtel Enregistrement ",
            ],
            "Hotel Name"              => ["Nom de l’hôtel", "Nom de l'hôtel"],
            "The following passenger" => "Les passagers suivants partagent",
            "Check In"                => "Enregistrement",
            "Check Out"               => ["Départ", "Sortie"],
            "Room Type"               => ["Type de chambre", "Chambre"],
        ],
    ];

    public $lang = "en";

    private $pdfName = '.*\.pdf';
    private $total = [];

    public function parsePdf(&$its, $text)
    {
        $text = preg_replace("#.* Page \d{1,2} of \d{1,2}[ ]*\n#", "", $text);

        $TripNumber = $this->re("#" . $this->preg_implode($this->t("Booking")) . "[ ]*:[ ]*([A-Z\d]{5,})#", $text);

        foreach ($its as $key => $value) {
            if ($value['TripNumber'] == $TripNumber) {
                return null;
            }
        }

        // Passengers
        $Passengers = [];
        $posStart = mb_strpos($text, $this->t("Passenger Summary") . "\n");

        if ($posStart !== false) {
            $posStart += mb_strlen($this->t("Passenger Summary") . "\n");
        } else {
            $posStart = mb_strpos($text, $this->t("Passenger(s)") . "\n");

            if (!empty($posStart)) {
                $posStart += mb_strlen($this->t("Passenger(s)" . "\n"));
            }
        }

        if (!empty($posStart)) {
            $posEnd = mb_strpos($text, "\n\n\n", $posStart);
        }

        if (!empty($posEnd) && !empty($posStart)) {
            $pass = mb_substr($text, $posStart, $posEnd - $posStart);
            $pos = null;

            if (is_array($this->t("Flight Itinerary"))) {
                foreach ($this->t("Flight Itinerary") as $t) {
                    $pos = mb_strpos($text, $t);

                    if (!empty($pos)) {
                        break;
                    }
                }
            } else {
                $pos = mb_strpos($text, $this->t("Flight Itinerary"));
            }

            if (!empty($pos)) {
                $pass = mb_substr($pass, 0, $pos);
            }
            $pass = preg_replace("#^\s*([ ]*\S)#", "$1", $pass);
            $table = $this->SplitCols($pass, 'left');

            if (preg_match_all("#^\s*\d+\.\s*(([\w\-\.]+ )+)(\s+-\s+|\s{3}|$)#um", $pass, $m)) {
                $Passengers = array_filter(array_map(function ($n) {return trim(explode(' - ', $n)[0]); }, $m[1]));
            }

            if (count($table) > 2) {
                foreach ($table as $value) {
                    if (stripos($value, $this->t('Seat(s)')) !== false && preg_match("#" . $this->preg_implode($this->t("Seat(s)")) . "\s*-\s*([A-Z\d]{2}\d{1,5})\b#", $value, $m1) && preg_match_all("#^\s*(\d{1,3}[A-Z])\b#m", $value, $m)) {
                        $Seats[$m1[1]] = $m[1];
                    }
                }
            }
        }

        /****** AIR SEGMENTS ******/

        $segments = [];

        if (is_array($this->t("Flight Summary\n"))) {
            foreach ($this->t("Flight Summary\n") as $t) {
                $posStart = strpos($text, $t);

                if (!empty($posStart)) {
                    break;
                }
            }
        } else {
            $posStart = strpos($text, $this->t("Flight Summary\n"));
        }

        if (!empty($posStart)) {
            if (is_array($this->t("flightsSectionEnds"))) {
                foreach ($this->t("flightsSectionEnds") as $value) {
                    $posEnd = strpos($text, $value);

                    if (!empty($posEnd)) {
                        break;
                    }
                }
            }

            if (!empty($posEnd)) {
                $flights = substr($text, $posStart, $posEnd - $posStart);
            } else {
                $flights = substr($text, $posStart);
            }

            if (preg_match_all("#(?:\n+|^)(\s*" . $this->t("Flight") . "\s+" . $this->t("From") . "\s+" . $this->preg_implode($this->t("To")) . "\s+.*)(?:\n\n\n|$)#su", $flights, $m)) {
                foreach ($m[1] as $value) {
                    $fls = preg_split("#\n{2,}#", $value);

                    foreach ($fls as $fl) {
                        if (preg_match("#(\s*" . $this->t("Flight") . "\s+" . $this->t("From") . "\s+" . $this->preg_implode($this->t("To")) . ".+)#", $fl, $mat)) {
                            $headerRow = $mat[1];
                            $segments[] = $fl;

                            continue;
                        }

                        if (preg_match("/(?:^|\s*\n)((?: {15,}[A-Z][A-Z \-\(\),.]+\n)?[ ]*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,5}\s{2,}.+$)/s", $fl, $mat)) {
                            $segments[] = $headerRow . "\n" . $mat[1];
                        }
                    }
                }
            }
        }

        if (!empty($segments)) {
            $it = [];
            $it['Kind'] = "T";
            // RecordLocator
            $it['RecordLocator'] = CONFNO_UNKNOWN;
            // TripNumber
            $it['TripNumber'] = $TripNumber;

            // Passengers
            $it['Passengers'] = $Passengers;
        }

        foreach ($segments as $segment) {
            if (empty(trim($segment)) || count(explode("\n", trim($segment))) < 2) {
                continue;
            }

            $seg = [];

            $tableText = $segment;

            if (preg_match("#is operated by[ ]+(.+)\.?#", $tableText, $m)) {
                $seg['Operator'] = trim($m[1]);
            }

            if (is_array($this->t("flightSegmentEnds"))) {
                foreach ($this->t("flightSegmentEnds") as $end) {
                    $pos = strpos($tableText, $end);

                    if (!empty($pos)) {
                        $tableText = substr($tableText, 0, $pos);
                    }
                }
            }
            //Added one space between columns
            $tableText = preg_replace("/(\([A-Z]{3}\)\s)([A-z]\D+\s\([A-Z]{3}\))/", "$1 $2", $tableText);

            $table = $this->SplitCols($tableText);

            if (count($table) < 4) {
                $this->logger->info("Flight table is not parsed");
            }

            if (preg_match("#" . $this->preg_implode($this->t("Flight")) . "\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,5})#", $table[0], $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];

                if (isset($Seats[$seg['AirlineName'] . $seg['FlightNumber']])) {
                    $seg['Seats'] = $Seats[$seg['AirlineName'] . $seg['FlightNumber']];
                }
            }

            if (preg_match("#" . $this->preg_implode($this->t("From")) . "\s+(.+)\s*\(([A-Z]{3})\).*\s+(\w+\.?\,.+\d+:\d+\s*(?:[APM]{2})?)(?:\s*-\s*" . $this->preg_implode($this->t("Terminal")) . "\s*(\S+))?#s", $table[1], $m)) {
                $seg['DepName'] = preg_replace('/\s+/', ' ', trim($m[1]));
                $seg['DepCode'] = $m[2];
                $seg['DepDate'] = $this->normalizeDate($m[3]);

                if (isset($m[4])) {
                    $seg['DepartureTerminal'] = trim($m[4]);
                }
            }

            if (preg_match("#" . $this->preg_implode($this->t("To")) . "\s+(.+)\s*\(([A-Z]{3})\)\s+(.+\d+:\d+\s*(?:[APM]{2})?)(?:\s*-\s*" . $this->preg_implode($this->t("Terminal")) . "\s*(.+))?#s", $table[2], $m)) {
                $seg['ArrName'] = preg_replace('/\s+/', ' ', trim($m[1]));
                $seg['ArrCode'] = $m[2];
                $seg['ArrDate'] = $this->normalizeDate($m[3]);

                if (isset($m[4])) {
                    $seg['ArrivalTerminal'] = trim($m[4]);
                }
            }

            if (isset($table[3]) && preg_match("#" . $this->preg_implode($this->t("Aircraft")) . "\s+(.+)#s", $table[3], $m)
                    || (isset($table[5]) && preg_match("#" . $this->preg_implode($this->t("Aircraft")) . "\s+(.+)#s", $table[4], $m))
                    || (isset($table[5]) && preg_match("#" . $this->preg_implode($this->t("Aircraft")) . "\s+(.+)#s", $table[5], $m))) {
                $seg['Aircraft'] = trim($m[1]);
            }

            if (isset($table[3]) && preg_match("#" . $this->preg_implode($this->t("Class")) . "\s+(.+)#s", $table[3], $m)
                    || (isset($table[4]) && preg_match("#" . $this->preg_implode($this->t("Class")) . "\s+(.+)#s", $table[4], $m))) {
                $seg['Cabin'] = trim(preg_replace("#\s+#", ' ', $m[1]));
            }

            if (preg_match("#" . $this->preg_implode($this->t("Seat number(s) selected:")) . "[ ]+(.+)#s", $segment, $m)) {
                $this->logger->error($m[1]);
                $seats = preg_split('/\s*,\s*/', trim($m[1]));
                $seatArray = [];
                $cabin = [];

                foreach ($seats as $seat) {
                    if (preg_match("/^(?<seat>\d+[A-Z])\s*\((?<cabin>.+)\)/u", $seat, $match)) {
                        $seatArray[] = $match['seat'];
                        $cabin[] = $match['cabin'];
                    } else {
                        $seg['Seats'][] = $seat;
                    }
                }

                if (count($cabin) > 0) {
                    $seg['Cabin'] = implode(', ', array_filter(array_unique($cabin)));
                }

                if (count($seatArray) > 0) {
                    $seg['Seats'] = array_filter(array_unique($seatArray));
                }
            } else {
                if (preg_match_all("/\s(\d+[A-Z])\s*\(/", $segment, $m)) {
                    $seg['Seats'] = $m[1];

                    $cabin = $this->re("/\s\d+[A-Z]\s*\((.+)\)/", $text);

                    if (!empty($cabin)) {
                        $seg['Cabin'] = $cabin;
                    }
                }
            }

            $it['TripSegments'][] = $seg;
        }

        if (isset($it)) {
            $its[] = $it;
        }

        /****** HOTEL SEGMENTS ******/

        $hotels = [];

        if (is_array($this->t("Product Information\n"))) {
            foreach ($this->t("Product Information\n") as $t) {
                $posStart = mb_strpos($text, $t);

                if (!empty($posStart)) {
                    break;
                }
            }
        } else {
            $posStart = mb_strpos($text, $this->t("Product Information\n"));
        }

        if (!empty($posStart)) {
            if (is_array($this->t("hotelsSectionEnds"))) {
                foreach ($this->t("hotelsSectionEnds") as $value) {
                    $posEnd = mb_strpos($text, $value);

                    if (!empty($posEnd)) {
                        break;
                    }
                }
            }

            if (!empty($posEnd)) {
                $products = mb_substr($text, $posStart, $posEnd - $posStart);
            } else {
                $products = mb_substr($text, $posStart);
            }
            $segments = $this->split("#^([ ]*" . str_replace(" ", "\s+", $this->preg_implode($this->t("productsStartSegments"))) . ")#m", $products);

            foreach ($segments as $segment) {
                if (preg_match("/^Transfer/", $segment)) {
                    continue;
                }

                if (preg_match("/{$this->preg_implode($this->t("Hotel Name"))}/u", $segment)) {
                    $segment = preg_replace(["/^\s*\n/", '/\n\s*$/'], '', $segment);
                    $parts = preg_split("/\n\n+/", $segment);
                    $head = $this->re("/^([\s\S]*?{$this->preg_implode($this->t('Hotel Name'))}.+\n)/u", $segment);

                    foreach ($parts as $part) {
                        if (preg_match_all("/20\d{2}/", $part, $m) && count($m[0]) > 1) {
                        } else {
                            $parts = [$segment];

                            break;
                        }
                    }

                    if (count($parts) > 1) {
                        foreach ($parts as $i => $part) {
                            if ($i !== 0) {
                                $parts[$i] = $head . $part;
                            }
                        }
                    }

                    $hotels = array_merge($hotels, $parts);
                }
            }
        }

        for ($i = 0; $i < count($hotels); $i++) {
            $segment = $hotels[$i];
            $pos = strpos($segment, $this->t("The following passenger"));

            if (!empty($pos)) {
                $tableText = substr($segment, 0, $pos);
            } else {
                $tableText = $segment;
            }

            $table = $this->SplitCols($tableText);

            if (count($table) < 4) {
                $table = $this->SplitCols($tableText, null, $this->TableHeadPos($this->inOneRow($tableText)));
            }

            if (count($table) < 4) {
                $this->logger->info("Hotel table is not parsed");
            }

            $seg = [];
            $seg['Kind'] = "R";
            $seg['ConfirmationNumber'] = CONFNO_UNKNOWN;
            $seg['TripNumber'] = $TripNumber;

            if (preg_match("#" . $this->preg_implode($this->t("Hotel Name")) . "\s+(.+)#us", $table[0], $m)) {
                if (strpos(trim($m[1]), "\n\n\n") !== false) {
                    $texts = array_filter(preg_split("#\n{2,}#", $tableText));

                    if (count($texts) > 1) {
                        $segment = $tableText = array_shift($texts);
                        unset($m);
                        $table = $this->SplitCols($tableText);
                        $header = '';

                        if (preg_match("#([ ]*" . $this->preg_implode($this->t("Hotel Name")) . ".+)#", $segment, $m)) {
                            $header = $m[1];
                        }
                        $hotels = array_merge($hotels, array_map(function ($n) use ($header) {return $header . "\n" . $n; }, $texts));

                        if (count($table) < 4) {
                            $this->logger->info("Hotel table is not parsed");
                        }

                        preg_match("#" . $this->preg_implode($this->t("Hotel Name")) . "\s+(.+)#s", $table[0], $m);
                    }
                }

                if (isset($m[1])) {
                    $seg['HotelName'] = trim(preg_replace("#\n+#", ' ', $m[1]));
                    $seg['Address'] = $seg['HotelName'];
                    $seg['Rooms'] = 1;
                }
            }

            if (preg_match("#" . $this->preg_implode($this->t("Check In")) . "\s+(.+\d+:\d+(?:\s*[APM]{2})?|.+\d{4})[\-]?[\s\-]*(?:\n|$)#s", $table[1], $m)) {
                $seg['CheckInDate'] = $this->normalizeDate($m[1]);
            }

            if (preg_match("#" . $this->preg_implode($this->t("Check Out")) . "\s+(.+\d+:\d+(?:\s*[APM]{2})?|.+\d{4})[\s\-]*(?:\n|$)#s", $table[2], $m)) {
                $seg['CheckOutDate'] = $this->normalizeDate($m[1]);
            }

            if (isset($table[3]) && preg_match("#" . $this->preg_implode($this->t("Room Type")) . "\s+(.+)#s", $table[3], $m)) {
                $seg['RoomType'] = trim(preg_replace("#\s+#", ' ', $m[1]));
            }

            if (isset($table[6]) && preg_match("#" . $this->preg_implode($this->t("Passenger(s)")) . "\s+(.+)#s", $table[6], $m)
                    || isset($table[5]) && preg_match("#" . $this->preg_implode($this->t("Passenger(s)")) . "\s+(.+)#s", $table[5], $m)) {
                $pass = array_map('trim', array_filter(explode(',', trim($m[1]))));
                $seg['Guests'] = count($pass);

                foreach ($pass as $key => $value) {
                    if (isset($Passengers[$value - 1])) {
                        $seg['GuestNames'][] = $Passengers[$value - 1];
                    }
                }
            }

            if (isset($seg['HotelName']) && preg_match("#\n\s*(" . $seg['HotelName'] . ".+)\.\s*Telephone:\s*([\d \+\-\(\)]{5,})#", $products, $m)) {
                $seg['Address'] = trim($m[1]);
                $seg['Phone'] = trim($m[2]);
            }

            if (isset($seg['HotelName']) && preg_match("#Hotel Information for (?:.+\n){1,3}\s*(" . $seg['HotelName'] . ".+)\.#", $products, $m)) {
                $seg['Address'] = trim($m[1]);
            }

            foreach ($its as $key => $value) {
                if ($value['Kind'] = "R" && isset($value['HotelName']) && isset($seg['HotelName']) && $value['HotelName'] == $seg['HotelName']
                        && isset($value['CheckInDate']) && isset($seg['CheckInDate']) && $value['CheckInDate'] == $seg['CheckInDate']
                        && isset($value['CheckOutDate']) && isset($seg['CheckOutDate']) && $value['CheckOutDate'] == $seg['CheckOutDate']) {
                    if (isset($seg['Rooms'])) {
                        $its[$key]['Rooms'] = isset($value['Rooms']) ? $value['Rooms'] + $seg['Rooms'] : $seg['Rooms'];
                    }

                    if (isset($seg['RoomType'])) {
                        $its[$key]['RoomType'] = isset($value['RoomType']) ? implode(",", array_unique([$value['RoomType'], $seg['RoomType']])) : $seg['RoomType'];
                    }

                    if (isset($seg['Guests'])) {
                        $its[$key]['Guests'] = isset($value['Guests']) ? $value['Guests'] + $seg['Guests'] : $seg['Guests'];
                    }

                    if (isset($seg['GuestNames'])) {
                        $its[$key]['GuestNames'] = isset($value['GuestNames']) ? array_unique(array_merge($value['GuestNames'], $seg['GuestNames'])) : $seg['GuestNames'];
                    }

                    continue 2;
                }
            }

            $its[] = $seg;
        }

        $posStart = mb_strpos($text, $this->t('Base Fare'));
        $posEnd = mb_strpos($text, $this->t("Payment Type"));

        if (!empty($posEnd) && !empty($posStart)) {
            $payment = mb_substr($text, $posStart, $posEnd - $posStart);

            if (preg_match("#" . $this->preg_implode($this->t("Base Fare")) . " +([\d ,.]+)#u", $payment, $m)) {
                $this->total['BaseFare'] = $this->normalizePrice($m[1]);
            }

            if (preg_match("#" . $this->preg_implode($this->t("Grand Total Amount")) . "\s+([^\n]+)#", $payment, $m)) {
                $this->total['TotalCharge'] = $this->normalizePrice($m[1]);
                $this->total['Currency'] = $this->currency($m[1]);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfName);
        $text = '';
        $pdfName = [];

        foreach ($pdfs as $pdf) {
            $name = $parser->getAttachmentHeader($pdf, "Content-Type");
            $pdfName[] = $name;

            if (($text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfName);
        $text = '';

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->reBody2 as $lang => $re) {
                if (strpos($text, $re) !== false) {
                    $this->lang = substr($lang, 0, 2);
                }
            }
            $this->parsePdf($its, $text);
        }

        $class = explode('\\', __CLASS__);
        $result = [
            'emailType' => end($class) . ucfirst($this->lang),
        ];

        if (!empty($this->total)) {
            if (count($its) == 1 && $its[0]['Kind'] == "T") {
                $its[0]['BaseFare'] = $this->total['BaseFare'] ?? null;
                $its[0]['TotalCharge'] = $this->total['TotalCharge'] ?? null;
                $its[0]['Currency'] = $this->total['Currency'] ?? null;
            }

            if (count($its) == 1 && $its[0]['Kind'] == "R") {
                $its[0]['Cost'] = $this->total['BaseFare'] ?? null;
                $its[0]['Total'] = $this->total['TotalCharge'] ?? null;
                $its[0]['Currency'] = $this->total['Currency'] ?? null;
            }

            if (count($its) > 1) {
                $result['parsedData']['TotalCharge'] = [
                    'Amount'   => $this->total['TotalCharge'] ?? null,
                    'Currency' => $this->total['Currency'] ?? null,
                ];
            }
        }

        $result['parsedData']['Itineraries'] = $its;

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2; // invoice, edocument
    }

    protected function normalizePrice($cost)
    {
        if (empty($cost)) {
            return 0.0;
        }
        $cost = preg_replace('/\s+/', '', $cost);			// 11 507.00	->	11507.00
        $cost = preg_replace('/[,.](\d{3})/', '$1', $cost);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $cost = preg_replace('/,(\d{2})$/', '.$1', $cost);	// 18800,00		->	18800.00

        return (float) $cost;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '£'=> 'GBP',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = preg_replace("#([,.\d ]+)#", '', $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function TableHeadPosCenter($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $posHead = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $posHead[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        if (empty($posHead)) {
            return [];
        }

        if (count($posHead) > 1) {
            $posHead[0] = round($posHead[1] / 3);
            $posBefore = -$posHead[0];
            array_unshift($head, '');

            foreach ($posHead as $key => $value) {
                $pos[] = $value - round(($value - strlen($head[$key]) - $posBefore) / 2);
                $posBefore = $value;
            }
        } else {
            $pos = $posHead;
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function SplitCols($text, $position = 'center', $pos = false)
    {
        $ds = 8;
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos && $position == 'center') {
            $pos = $this->TableHeadPosCenter($rows[0]);
        }

        if (!$pos && $position == 'left') {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                if ($k != 0 && (!empty(trim(mb_substr($row, $p - 1, 1))) || !empty(trim(mb_substr($row, $p + 1, 1))))) {
                    $str = mb_substr($row, $p - $ds, $ds, 'UTF-8');

                    if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m)) {
                        $cols[$k][] = trim($m[2] . mb_substr($row, $p, null, 'UTF-8'));
                        $row = mb_substr($row, 0, $p - strlen($m[2]) - 1, 'UTF-8');
                        $pos[$k] = $p - strlen($m[2]) - 1;

                        continue;
                    } else {
                        $str = mb_substr($row, $p, $ds, 'UTF-8');

                        if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m) || preg_match("#(\S*)(.*)$#", $str, $m)) {
                            $cols[$k][] = trim($m[2] . mb_substr($row, $p + $ds, null, 'UTF-8'));
                            $row = mb_substr($row, 0, $p, 'UTF-8') . $m[1];
                            $pos[$k] = $p + strlen($m[1]) + 1;

                            continue;
                        }
                    }
                }
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $this->http->log('$str = ' . print_r($str, true));
        $in = [
            "#^\s*[^\d\s]+,\s*(\d{1,2})\s+([^\d\s]+)\s+(\d{4})[\s\-]+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$#ui", // Sun, 16 Oct 2016    8:50 PM, ven., 25 jan 2019 12:00
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
