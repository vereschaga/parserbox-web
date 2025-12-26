<?php

namespace AwardWallet\Engine\tport\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\ItineraryArrays\CruiseTrip;
use AwardWallet\ItineraryArrays\CruiseTripSegment;

class MyTripPdf extends \TAccountChecker
{
    public $mailFiles = "tport/it-10782100.eml, tport/it-10782105.eml, tport/it-12305200.eml, tport/it-12384811.eml, tport/it-12544792.eml, tport/it-12591053.eml, tport/it-17226793.eml, tport/it-36030470.eml, tport/it-36087370.eml, tport/it-36957695.eml, tport/it-36957782.eml, tport/it-37463526.eml, tport/it-40710017.eml, tport/it-7478359.eml, tport/it-7505774.eml, tport/it-8024889.eml, tport/it-8169194.eml, tport/it-8404014.eml, tport/it-8578024.eml, tport/it-8578295.eml, tport/it-8864822.eml, tport/it-8889206.eml, tport/it-95703968.eml";

    public $reFrom = '@travelport.com';

    public $lang = '';

    public static $langDetectors = [ // use in parser tport/It6098511
        'it' => ['Il mio viaggio'],
        'pt' => ['A minha viagem'],
        'sv' => ['Min resa'],
        'en' => ['My Trip', 'You can access Travelport ViewTrip on your desktop computer, tablet and mobile device'],
        'de' => ['Meine Reise'],
        'fr' => ['Mon voyage'],
        'es' => ['Mi viaje'],
    ];

    public $pdfPattern = '.*\.pdf';
    public $pdfPattern1 = '.*Itinerary.*\.pdf';

    public static $dictionary = [
        'it' => [ // it-12544792.eml
            "My Trip"               => "Il mio viaggio",
            "Your Reservation Code" => [" Codice di Prenotazione", "Il tuo Codice di prenotazione"],
            // Il Suo Codice di Prenotazione, Il vostro Codice di Prenotazione
            "Confirmation Number:" => "Numero di conferma:",
            "Name"                 => "Nome",
            "DEPART"               => "PARTENZA",
            "Class of Service"     => "Classe di servizio",
            "PASSENGERS"           => "PASSEGGERI",
            "to"                   => ["per", "a"],
            "Terminal"             => "Terminal",
            "AIRPORT INFO"         => "INFORMAZIONI AEROPORTO",
            "FLIGHT INFO"          => "INFORMAZIONI VOLO",
            "STOP"                 => "SCALO",
            "NON STOP"             => "DIRETTO",
            "eTicket Number"       => "Numero biglietto elettronico",
            "Seat"                 => "Posto",
            "Rewards Program"      => "Tessera fedeltà",
            "Operated by"          => "\*Operato da",
        ],
        'fr' => [ // it-36087370.eml
            "My Trip"               => "Mon voyage",
            "Your Reservation Code" => "Votre code de réservation",
            "Confirmation Number:"  => ["Numéro de confirmation:", "Votre numéro de réservation:"],
            "Name"                  => "Nom",
            "DEPART"                => "DÉPART",
            "Class of Service"      => "Classe (?:du|de) service",
            "PASSENGERS"            => "PASSAGERS",
            "to"                    => "à",
            "Terminal"              => "Terminal",
            "AIRPORT INFO"          => ["INFORMATIONS SUR L’AÉROPORT", "RENSEIGNEMENTS SUR L’AÉROPORT"],
            "FLIGHT INFO"           => ["INFORMATIONS SUR LE VOL", "RENSEIGNEMENTS SUR LE VOL"],
            //			"STOP" => "",
            "NON STOP"       => "(?:SANS ESCALE|\s+AUCUN\s+.+\s+ARRÊT\s+)",
            "eTicket Number" => ["Numéro de billet électronique", "Numéro du billet électronique"],
            //			"Seat" => "",
            "Rewards Program" => "Rewards Programme",
            "Operated by"     => "\*Opéré par",
            "Tour"            => ["Tour", "Visite guidée"],
        ],
        'pt' => [ // it-8889206.eml
            "My Trip"               => "A minha viagem",
            "Your Reservation Code" => "O seu código de reserva",
            "Confirmation Number:"  => "Número de confirmação:",
            "Name"                  => "Nome",
            "DEPART"                => "PARTIDA",
            "Class of Service"      => "Classe de serviço",
            "PASSENGERS"            => "PASSAGEIROS",
            "to"                    => ["para", "até"],
            "Terminal"              => "Terminal",
            "AIRPORT INFO"          => "INFORMAÇÕES DO AEROPORTO",
            "FLIGHT INFO"           => "INFORMAÇÕES SOBRE O VOO",
            "STOP"                  => "ESCALA",
            "NON STOP"              => "SEM.+ESCALA",
            "eTicket Number"        => "Número do bilhete eletrónico",
            "Seat"                  => "Lugar",
            "Rewards Program"       => "Programa de recompensas",
            "Operated by"           => "\*Operado por",
        ],
        'sv' => [ // it-95703968.eml
            "My Trip"               => "Min resa",
            "Your Reservation Code" => "Ditt bokningsnummer",
            "Confirmation Number:"  => "Bekräftelsenummer:",
            "Name"                  => "Namn",
            "DEPART"                => "AVRESA",
            "Class of Service"      => "Tjänsteklass",
            "PASSENGERS"            => "PASSAGERARE",
            "to"                    => "till",
            // "Terminal" => "",
            "AIRPORT INFO" => "FLYGPLATSINFORMATION",
            "FLIGHT INFO"  => "FLYGINFORMATION",
            // "STOP" => "",
            "NON STOP"       => "INGA.+UPPEHÅ.*LL",
            "eTicket Number" => "e-biljett-nummer",
            // "Seat" => "",
            // "Rewards Program" => "",
            // "Operated by" => "",
        ],
        'en' => [
            //			"My Trip" => "",
            //			"Your Reservation Code" => "",
            "Confirmation Number:" => "Con(?: |fi)rmation Number:",
            //			"Name" => "",
            //			"DEPART" => "",
            "Class of Service" => "Class (?:O|o)f Service",
            //			"PASSENGERS" => "",
            //			"to" => "",
            //			"Terminal" => "",
            //			"AIRPORT INFO" => "",
            //			"FLIGHT INFO" => "",
            //			"STOP" => "",
            "NON STOP" => "NON.+STOP",
            //			"eTicket Number" => "",
            //			"Seat" => "",
            //			"Rewards Program" => "",
            "Operated by" => "\*Operated by",

            //			"Phone" => "",
            //			"Fax" => "",
            // Car
            //			"PICK-UP AND DROP-OFF LOCATION" => "",
            //			"PICK-UP LOCATION" => "",
            //			"DROP-OFF LOCATION" => "",
            //			"CAR INFO" => "",
            //			"RESERVED FOR" => "",
            "Total Rate:" => ["Total Rate:", "Approximate Total:"],
            //Hotel
            //			"PROPERTY INFO" => "",
            //			"CONTACT INFO" => "",
            //			"GUESTS" => "",
            //			"Guest" => "",
            //			"Room" => "",
            //			"Rate:" => "",
        ],
        'de' => [
            "My Trip"               => "Meine Reise",
            "Your Reservation Code" => "Ihr Reservierungscode",
            "Confirmation Number:"  => "Bestätigungsnummer:",
            "Name"                  => "Name",
            "DEPART"                => "ABFLUG",
            "Class of Service"      => "Klasse",
            "PASSENGERS"            => "PASSAGIERE",
            "to"                    => "nach",
            "Terminal"              => "Terminal",
            "AIRPORT INFO"          => "INFORMATIONEN ZUM FLUGHAFEN",
            "FLIGHT INFO"           => "INFORMATIONEN ZUM FLUG",
            "STOP"                  => "STOPP",
            "NON STOP"              => "ZWISCHE+STOPP",
            "eTicket Number"        => ["E-Ticket-Nummer", "eTicket-Nummer"],
            "Seat"                  => "Sitz",
            "Rewards Program"       => "Bonusprogramm",
            "Operated by"           => "\*(?:durchgeführt|Betrieben) von",
        ],
        'es' => [
            "My Trip"               => "Mi viaje",
            "Your Reservation Code" => "Tu código de reserva",
            "Confirmation Number:"  => "Número de confirmación:",
            "Name"                  => "Nombre",
            "DEPART"                => "SALIDA",
            "Class of Service"      => "Clase del servicio",
            "PASSENGERS"            => "PASAJEROS",
            "to"                    => ["hasta"],
            "Terminal"              => "Terminal",
            "AIRPORT INFO"          => "NFORMACIÓN DEL AEROPUERTO",
            "FLIGHT INFO"           => "INFORMACIÓN DEL VUELO",
            "STOP"                  => "PARADA",
            "NON STOP"              => "SIN.+PARADA",
            "eTicket Number"        => "Número de billete electrónico",
            "Seat"                  => "Asiento",
            //            "Rewards Program" => "",
            //            "Operated by" => "",
        ],
    ];

    protected $text = '';

    private $code;
    private static $headers = [
        'tport' => [
            'from' => ['travelport.'],
            'subj' => [
                'it' => ['Visualizza il tuo itinerario'],
                'pt' => ['Visualizar o seu itinerário'],
                'sv' => ['Visa din resplan'],
                'de' => ['Reise nach'],
                'en' => ['Travelport', 'View Your Itinerary'],
            ],
        ],
        'flightcentre' => [
            'from' => ['@flightcentre.com.au'],
            'subj' => [
                'en' => ['Etickets'],
            ],
        ],
    ];
    private $bodies = [//TODO: don't set mta - provider before it reset ignoreTraxo
        'tport' => [
            '//a[contains(@href,\'travelport.com\')]',
        ],
        'flightcentre' => [
            '//a[contains(@href,\'fctgl.com\')]',
            '//a[contains(@href,\'flightcentre.com\')]',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                foreach ($subj as $s) {
                    if (stripos($headers['subject'], $s) !== false) {
                        $bySubj = true;
                    }
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern1);

        if (!isset($pdfs[0])) {
            $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

            if (!isset($pdfs[0])) {
                return false;
            }
        }

        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (stripos($text,
                'Travelport') === false && $this->http->XPath->query("//text()[contains(.,'Travelport') or contains(.,'travelport')]")->length == 0) {
            return false;
        }

        return $this->assignLang($text);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern1);

        if (!isset($pdfs[0])) {
            $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

            if (!isset($pdfs[0])) {
                return null;
            }
        }

        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        if ($this->assignLang($this->text) === false) {
            $this->logger->debug("can't determine a language");

            return null;
        }

        $itineraries = [];
        $this->parsePdf($itineraries);
        $result = [
            'emailType'  => 'MyTripPdf' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if ($code = $this->getProvider($parser)) {
            $result['providerCode'] = $code;
        }

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

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    protected function assignLang($text)
    {
        foreach (self::$langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'tport') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (!(stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        && !(stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        continue 2;
                    }
                }

                return $code;
            }
        }

        return null;
    }

    private function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $tripNum = $this->re('/' . $this->opt($this->t('Your Reservation Code')) . ':\s+([A-Z\d]{5,})/', $text);

        // remove headers and footers
        $text = preg_replace([
            "#(^|\n)\d+/\d+/\d{4}\s+Travelport Viewtrip - My Trip\n#",
            // 7/28/2017           Travelport Viewtrip - My Trip
            "#\s+\d+/\d+\n#",
            //                   1/3
            "#\nhttps://viewtrip.travelport.com/.*?\n#",
            //https://viewtrip.travelport.com/#!/itinerary?loc=DNHM2K&lName=LIM
        ], [
            "\n",
            "\n",
            "\n",
        ], $text);

        $pos = false;

        foreach ((array) $this->t('Your Reservation Code') as $value) {
            if (($p = mb_strpos($text, $value, 0, 'UTF-8')) !== false) {
                $pos = $p;

                break;
            }
        }
        $flights = "\n" . mb_substr(
                $text,
                $sp = mb_strpos($text, $this->t('My Trip'), 0, 'UTF-8') + mb_strlen($this->t('My Trip'), 'UTF-8'),
//                mb_strpos($text, $this->t('Your Reservation Code'), 0, 'UTF-8') - $sp,
                $pos - $sp,
                'UTF-8'
            );

        $patterns = [
            'dates' => [
                '[^,.\d\s]{2,}[, ]+[^,.\d ]{3,}[ ]*\d{1,2}[, ]+\d{4} - ', // SAT, OCT 14, 2017 -
                '[^,.\d\s]{2,}[ ]+\d{1,2}[ ]*[^,.\d ]{3,}[ ]+\d{4} - ', // THU 19 OCT 2017 -
            ],
        ];

        $segments = $this->split('/^[ ]*(' . implode('|', $patterns['dates']) . ')/m', $flights);

        $airs = [];
        $cars = [];
        $hotels = [];
        $trains = [];
        $cruises = [];

        foreach ($segments as $i => $stext) {
            $rl = $this->re("/{$this->opt($this->t('Confirmation Number:'))}\s+([A-Z\d]{5,})/u", $stext);

            if (empty($rl)) {
                $rl = $this->re("/{$this->opt($this->t('Confirmation Number:'))}\s+([A-Z\d]{5,})/u", $text);
            }

            if (strpos($stext, $this->t('Surface')) !== false) {
                //$this->re('/\n\s*('.$this->t('Surface').')\s*\n/', $stext) !== null
                //|| $this->re('/\b('.$this->t('Surface').')\b/', $stext) !== null )
                continue;
            }

            if (empty($rl)) {
                $rl = $this->re('/' . $this->t('Your Reservation Code') . ':\s+([A-Z\d]{5,})/', $text);
            }

            if (stripos($stext, $this->t('DEPART')) !== false
                && $this->striposAll($stext, $this->t('AIRPORT INFO')) !== false
            ) {
                $airs[$rl][] = $stext;

                continue;
            }

            if (stripos($stext, $this->t('TRAIN STATION INFO')) !== false
                || stripos($stext, $this->t('TRAIN INFO')) !== false) {
                $trains[$rl][] = $stext;

                continue;
            }

            if (stripos($stext, $this->t('PICK-UP')) !== false) {
                $cars[] = $stext;

                continue;
            }

            if (stripos($stext, $this->t('CHECK-IN')) !== false
                || stripos($stext, $this->t('ROOM INFO')) !== false) {
                $hotels[] = $stext;

                continue;
            }

            if (false !== stripos($stext,
                    'Cruise') && !preg_match('/Port[ ]+:[ ]+(.+)\s+Sail Date[ ]+:[ ]+(\d{1,2})[ ]*([a-z]+)[ ]*(\d{2,4}) Time[ ]+:[ ]+(\d{1,2})[ :]?(\d{2}[ap]m)\s+Return[ ]+:[ ]+(\d{1,2})[ ]*([a-z]+)/i',
                    $stext)) {
                continue;
            }

            if (false !== stripos($stext, 'Cruise')) {
                $cruises[] = $stext;

                continue;
            }

            $this->logger->warning($stext);

            if (preg_match("/^[ ]*{$this->preg_implode($this->t('Tour'))}(?:[ ]*\(|[ ]*\d+\b|$)/m", $stext)) {
                // it-12305200.eml
                continue;
            }

            if (false !== stripos($stext, 'Other')) {
                continue;
            }

            $this->logger->info("Segment {$i} type not detect");

            return [];
        }

        foreach ($airs as $rl => $segments) {
            $it = [];
            $it['Kind'] = 'T';

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $tripNum;

            // Passengers
            $passengers = [];

            // TicketNumbers
            $ticketNumbers = [];

            // AccountNumbers
            $accountNumbers = [];

            // Cancelled
            // TotalCharge
            // BaseFare
            // Currency
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory
            foreach ($segments as $i => $stext) {
                if (preg_match("/\(ZZF\)/", $stext)) { //always bad segment
                    continue;
                }
                $itsegment = [];
                $paxRows = $this->re("#" . $this->t('PASSENGERS') . "[^\n\S]*\n+(.*?)(?:" . $this->t('Operated by') . "|" . $this->t('Class of Service') . ":|\n[ ]*INFO|\n[^\n]*" . $this->t('DEPART') . ")#ms",
                    $stext);

                if (empty($paxRows)) {
                    $paxRows = $this->re("#" . $this->t('PASSENGERS') . "[^\n\S]*\n+(.*?)(?:\n\s*(?:[^\s\n\d]+ )*\S*\d)#ms",
                        $stext);
                } else {
                    // it-8024889.eml
                    $paxRows = $this->re("#(.+?)(?:\n\D+\s+\d+\s*$|$)#s", $paxRows);
                }

                if (empty($paxRows)) {
                    $paxRows = $this->re("#" . $this->t('PASSENGERS') . "[^\n\S]*\n+(.*?)\n\n#ms", $stext);
                }
                // FE: it-36957782.eml  ---- pax on two pages
                $parts = $this->split("#^([ ]*{$this->preg_implode($this->t('Name'))}[ ]+{$this->preg_implode($this->t('eTicket Number'))})#m",
                    $paxRows);

                foreach ($parts as $part) {
                    // try del garbage in paxRows  (FE: it-36030470.eml)
                    $rows = explode("\n", $part);
                    $pos = $this->TableHeadPos($rows[0]);
                    $newRows = $rows;

                    if (count($pos) > 0) {
                        $delta = str_pad('', $pos[0] + 1);

                        foreach ($rows as $j => $row) {
                            if (strpos($row, $delta) === 0) {
                                unset($newRows[$j]);
                            }
                        }
                        $paxRows = implode("\n", array_filter($newRows));
                    }

                    $passtable = $this->splitCols($paxRows);
                    /*
    Name                                   eTicket Number      Seat                     Special Services

    SCHOLLMEIER, SABRINA.MARIA.MS          2207195094988       16C - Conﬁrmed

    SCHOLLMEIER, EMILY.SKY.MS (Child)      2207195094989       16B - Conﬁrmed


    SCHOLLMEIER, FINN.HAWK.MR (Child)      2207195094990       15D - Conﬁrmed


    *Operated by United Airlines Inc
    Class Of Service: Economy
 **/

                    if (count($passtable) > 1) {
                        $columnName = [
                            'name'    => $this->t('Name'),
                            'eticket' => $this->t('eTicket Number'),
                            'seat'    => $this->t('Seat'),
                            'rewards' => $this->t('Rewards Program'),
                        ];
                        $filterColumns = array_merge($columnName, ['Ticket Number']);

                        foreach ($passtable as $column) {
                            foreach ($columnName as $key => $name) {
                                $column = preg_replace("#^\s*" . $this->preg_implode($name) . "[^\n]*\n#s", '', $column, -1, $count);

                                if ($count > 0) {
                                    $rows = array_map(function ($s) {
                                        return preg_replace('/\b\s{4,}.+$/', '', $s);
                                    }, explode("\n", $column));
                                    $rows = array_filter($rows, function ($s) use ($filterColumns) {
                                        return !in_array($s, $filterColumns);
                                    });

                                    switch ($key) {
                                        case 'name':
                                            $passengers = array_merge($passengers, $rows);

                                            break;

                                        case 'eticket':
                                            $rows = array_filter(array_filter($rows, function ($s) {
                                                if (preg_match("#^\s*\d{3}[\d\- ]+$#", $s)) {
                                                    return true;
                                                }

                                                return false;
                                            }));
                                            $ticketNumbers = array_merge($ticketNumbers, $rows);

                                            break;

                                        case 'seat':
                                            $seats = array_filter(array_map(function ($s) {
                                                if (preg_match("#\b\d{1,3}[A-Z]\b#", $s, $m)) {
                                                    return $m[0];
                                                }

                                                return null;
                                            }, $rows));

                                            if (!isset($itsegment['Seats'])) {
                                                $itsegment['Seats'] = $seats;
                                            } else {
                                                $itsegment['Seats'] = array_merge($itsegment['Seats'], $seats);
                                            }

                                            break;

                                        case 'rewards':
                                            $accountNumbers = array_merge($accountNumbers, $rows);

                                            break;
                                    }
                                    unset($columnName[$key]);

                                    break;
                                } else {
                                    continue;
                                }
                            }
                        }
                    } else {
                        $rows = explode("\n", $passtable[0]);
                        $passengers = array_merge($passengers, $rows);
                    }
                }

                $date = strtotime($this->normalizeDate(trim($this->re("#(.*?) - #", $stext))));

                $checkmarkIcon = "";
                $airplaneIcon = "";
                $arrorIcon = "";
                $arror2Icon = "";

                $tableText = "\n" . mb_substr(
                        $stext,
//                    $sp = mb_strpos($stext, $checkmarkIcon, 0, 'UTF-8') + mb_strlen($checkmarkIcon, 'UTF-8'),
                        $sp = 0,
                        mb_strpos($stext, $this->t('PASSENGERS'), 0, 'UTF-8') - $sp,
                        'UTF-8');

                $pos = [
                    0,
                    mb_strlen($this->re("#\n(.*?){$airplaneIcon}#", $tableText), "UTF-8"),
                    ($p = mb_strlen($this->re("#\n(.*?){$arrorIcon}#", $tableText),
                        "UTF-8")) ? $p - 2 : mb_strlen($this->re("#\n(.*?){$arror2Icon}#", $tableText), "UTF-8"),
                ];

                if (empty(array_filter($pos))) {
                    $pos = [
                        0,
                        mb_strlen($this->re("#\n(.*?)" . $this->t('DEPART') . "#", $tableText), "UTF-8"),
                        mb_strlen($this->re("#\n([^\n]*)\s\d{1,2}H\s+\d{1,2}M\b#", $tableText), "UTF-8"),
                    ];
                }
                $table = $this->splitCols($tableText, $pos);

                // AirlineName
                // FlightNumber
                if (preg_match('/\(([A-Z][A-Z\d]|[A-Z\d][A-Z])\)\s+(\d+) ?\*?(?:[ ]{2}|[ ]*$)/m', $table[0],
                    $matches)) {
                    $itsegment['AirlineName'] = $matches[1];
                    $itsegment['FlightNumber'] = $matches[2];
                }

                // DepDate
                $itsegment['DepDate'] = strtotime($this->re("#{$airplaneIcon}\s+(\d+:\d+)#",
                        $table[1]) . $this->re("#(?:\n|\s)([AP]M)\n#", $table[1]), $date);

                if (false === $itsegment['DepDate']) {
                    $itsegment['DepDate'] = strtotime($this->re('/\b(\d{1,2}:\d{2})\b/',
                            $table[1]) . $this->re("#(?:\n|\s)([AP]M)\n#", $table[1]), $date);
                }

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->re("#(?:{$arrorIcon}|{$arror2Icon})\s+(\d+:\d+)#",
                        $table[2]) . $this->re("#(?:\n|\s)([AP]M)\n#", $table[2]), $date);

                if (false === $itsegment['ArrDate']) {
                    $itsegment['ArrDate'] = strtotime($this->re('/\b(\d{1,2}:\d{2})\b/',
                            $table[2]) . $this->re("#(?:\n|\s)([AP]M)\n#", $table[2]), $date);
                }

                if (preg_match("#\s+-\s+(?<DepName>[^-]*?)\s*\((?<DepCode>[A-Z]{3})\)\s+" . $this->preg_implode($this->t('to')) . "\s+(?<ArrName>.*?)\s*\((?<ArrCode>[A-Z]{3})\)(?<status>.*)#",
                    $stext, $m)) {
                    // DepCode
                    $itsegment['DepCode'] = $m['DepCode'];
                    // ArrCode
                    $itsegment['ArrCode'] = $m['ArrCode'];

                    if ($m['DepName'] !== $m['ArrName']) {
                        // DepName
                        $itsegment['DepName'] = $m['DepName'];
                        // ArrName
                        $itsegment['ArrName'] = $m['ArrName'];
                    }

                    $anchor = false;

                    if (0 !== $i) {
                        $y = --$i;

                        while (0 < $i && isset($it['TripSegments'][$y]) && 20 > $i) { // 20 for to prevent endless loop
                            if (
                                $it['TripSegments'][$y]['DepName'] === $itsegment['DepName']
                                && $it['TripSegments'][$y]['ArrName'] === $itsegment['ArrName']
                                && $it['TripSegments'][$y]['DepDate'] === $itsegment['DepDate']
                                && $it['TripSegments'][$y]['ArrDate'] === $itsegment['ArrDate']
                            ) {
                                $anchor = true;
                            }
                            $y--;
                        }
                    }

                    if (empty(trim($m['status'])) && $anchor) { //flight not confirmed and no similar segments
                        continue; // segments are dublicated, in the first time differs from another, while one of segments is not confirmed. We need to be confirmed in this case
                    }
                }

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#" . $this->t('Terminal') . "[ ]*([A-z\d]+)#",
                    $this->re("#" . $this->opt($this->t('AIRPORT INFO')) . "(.*?)\n\s+" . $this->preg_implode($this->t('to')) . "\s+#ms",
                        $stext));

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->re("#" . $this->t('Terminal') . "[ ]*(.+)#",
                    $this->re("#" . $this->opt($this->t('AIRPORT INFO')) . ".*\n\s+" . $this->preg_implode($this->t('to')) . "\s+(.+)#ms",
                        $stext));

                // Operator
                $itsegment['Operator'] = $this->re("#" . $this->t('Operated by') . "[ ]*(.+)#", $stext);

                // Aircraft
                $itsegment['Aircraft'] = $this->re("#" . $this->opt($this->t('FLIGHT INFO')) . "\n\s*(.+)#", $stext);

                // Cabin
                $arr = explode("    ", trim($this->re("/" . $this->t('Class of Service') . "[: ]+(.+)/ui", $stext)));

                if (count($arr) > 0) {
                    $itsegment['Cabin'] = array_shift($arr);
                }

                // Duration
                $itsegment['Duration'] = $this->re('/\b(\d{1,2} ?H\s+\d{1,2} ?M)\b/', $stext);

                // Stops
                if ($stops = $this->re("/(\d+) " . $this->t('STOP') . "/", $table[2])) {
                    $itsegment['Stops'] = (int) $stops;
                } elseif ($stops = $this->re("/(" . $this->t('NON STOP') . ")/s", $table[2])) {
                    $itsegment['Stops'] = 0;
                }

                $it['TripSegments'][] = $itsegment;
            }

            $passengerValues = array_values(array_filter($passengers));

            if (!empty($passengerValues[0])) {
                $it['Passengers'] = array_unique($passengerValues);
                $it['Passengers'] = array_filter(array_map(function ($s) {
                    if (preg_match("#^\s*([[:alpha:]\s.,\-]+?)\s*(?:\.MS|\.MR|MR|MS|MSTR|MRS)?(?:\s*\((?:Child|Jeune enfant|Infant)\))?\s*$#us", $s, $m)) {
                        $s = $m[1];
                    }

                    return preg_replace('/^\s*.*((?:Carry|Bag|Applies|Aplica-se a|Refer to|Recherche de la liste des passagers).*)/u', '', $s);
                }, $it['Passengers']));
                $it['Passengers'] = array_filter($it['Passengers'], function ($s) {
                    if (preg_match("#^\s*([[:alpha:]\s.,\-\(\)]+)\s*$#us", $s, $m)) {
                        return true;
                    } else {
                        return false;
                    }
                });
                $it['Passengers'] = preg_replace("/^.*Recherche de la liste des passagers.*/s", '', $it['Passengers']);
                $it['Passengers'] = preg_replace("/^\s*([^,]+?)\s*,\s*([^,]+?)\s*$/", '$2 $1', $it['Passengers']);
                $it['Passengers'] = preg_replace("/ - {$this->opt($this->t('Rewards Program'))}: [\w ]+\s*$/", '', $it['Passengers']);
            }

            $ticketNumberValues = array_values(array_filter($ticketNumbers));

            if (!empty($ticketNumberValues[0])) {
                $it['TicketNumbers'] = array_filter(array_unique($ticketNumberValues), function ($p) {
                    return preg_replace(['/[t]?icket Number/', '/[\d\/a-z]+cm/', '/^(?:e|ance|wance)$/'], ['', '', ''],
                        $p);
                });
            }

            $accountNumbersValues = array_values(array_filter($accountNumbers));
            $accountNumbersValues = array_map(function ($el) {
                return $this->re('/([A-Z\d]+)/', $el);
            }, $accountNumbersValues);

            if (!empty($accountNumbersValues[0])) {
                $it['AccountNumbers'] = array_unique($accountNumbersValues);
            }

            $itineraries[] = $it;
        }

        foreach ($trains as $rl => $segments) {
            $it = [];
            $it['Kind'] = 'T';
            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $tripNum;

            // Passengers
            $passengers = [];

            // TicketNumbers
            $ticketNumbers = [];

            // AccountNumbers
            $accountNumbers = [];

            // Cancelled
            // TotalCharge
            // BaseFare
            // Currency
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory
            foreach ($segments as $stext) {
                $itsegment = [];
                $passtable = $this->splitCols($this->re("#" . $this->t('PASSENGERS') . "[^\n\S]*\n+(.*?)(?:" . $this->t('Class of Service') . ":|\n[ ]*INFO|\n\n)#ms",
                    $stext));

                if (count($passtable) > 1) {
                    $columnName = [
                        'name'    => $this->t('Name'),
                        'eticket' => $this->t('eTicket Number'),
                        'seat'    => $this->t('Seat'),
                        'rewards' => $this->t('Rewards Program'),
                    ];

                    foreach ($passtable as $column) {
                        foreach ($columnName as $key => $name) {
                            $column = preg_replace("#^\s*" . $this->preg_implode($name) . "[^\n]*\n#s", '', $column, -1, $count);

                            if ($count > 0) {
                                $rows = explode("\n", $column);

                                switch ($key) {
                                    case 'name':
                                        $passengers = array_merge($passengers, $rows);

                                        break;

                                    case 'eticket':
                                        $ticketNumbers = array_merge($ticketNumbers, $rows);

                                        break;

                                    case 'seat':
                                        $itsegment['Seats'] = array_filter(array_map(function ($s) {
                                            if (preg_match("#\d+[A-Z]#", $s, $m)) {
                                                return $m[0];
                                            }
                                        }, $rows));

                                        break;

                                    case 'rewards':
                                        $accountNumbers = array_merge($accountNumbers, $rows);

                                        break;
                                }
                                unset($columnName[$key]);

                                break;
                            } else {
                                continue;
                            }
                        }
                    }
                } else {
                    $rows = explode("\n", $passtable[0]);

                    $passengers = array_merge($passengers, $rows);
                }

                $date = strtotime($this->normalizeDate(trim($this->re("#(.*?) - #", $stext))));

                $checkmarkIcon = "";
                $trainIcon = "";
                $arrorIcon = "";
                $arror2Icon = "";

                $table = "\n" . mb_substr($stext,
                        $sp = mb_strpos($stext, $checkmarkIcon, 0, 'UTF-8') + mb_strlen($checkmarkIcon, 'UTF-8'),
                        mb_strpos($stext, $this->t('PASSENGERS'), 0, 'UTF-8') - $sp, 'UTF-8');

                $pos = [
                    0,
                    mb_strlen($this->re("#\n(.*?){$trainIcon}#", $table), "UTF-8"),
                    ($p = mb_strlen($this->re("#\n(.*?){$arrorIcon}#", $table),
                        "UTF-8")) ? $p : mb_strlen($this->re("#\n(.*?){$arror2Icon}#", $table), "UTF-8"),
                ];
                $table = $this->splitCols($table, $pos);

                // AirlineName
                // FlightNumber
                if (preg_match('/' . $this->preg_implode($this->t('to')) . '.+\s*\n[ ]*(.+?[ ]+(\d+))[ ]*\n/',
                    $table[0], $matches)) {
                    $itsegment['Type'] = $matches[1];
                    $itsegment['FlightNumber'] = $matches[2];
                }

                if (preg_match("#\s+-\s+(?<DepName>[^-]*?)\s+\((?<DepCode>[A-Z]{3})\)\s+" . $this->preg_implode($this->t('to')) . "\s+(?<ArrName>.*?)\s+\((?<ArrCode>[A-Z]{3})\)#",
                    $stext, $m)) {
                    // DepCode
                    $itsegment['DepCode'] = $m['DepCode'];
                    // DepName
                    $itsegment['DepName'] = $m['DepName'];

                    // ArrCode
                    $itsegment['ArrCode'] = $m['ArrCode'];
                    // ArrName
                    $itsegment['ArrName'] = $m['ArrName'];
                }

                // DepDate
                $itsegment['DepDate'] = strtotime($this->re("#{$trainIcon}\s+(\d+:\d+)#",
                        $table[1]) . $this->re("#(?:\n|\s)([AP]M)\n#", $table[1]), $date);

                if (false === $itsegment['DepDate']) {
                    $itsegment['DepDate'] = strtotime($this->re('/\b(\d{1,2}:\d{2})\b/', $table[1]), $date);
                }

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->re("#(?:{$arrorIcon}|{$arror2Icon})\s+(\d+:\d+)#",
                        $table[2]) . $this->re("#(?:\n|\s)([AP]M)\n#", $table[2]), $date);

                if (false === $itsegment['ArrDate']) {
                    $itsegment['ArrDate'] = strtotime($this->re('/\b(\d{1,2}:\d{2})\b/', $table[2]), $date);
                }

                // TraveledMiles
                // AwardMiles
                // Cabin
                $arr = explode("    ", trim($this->re("/" . $this->t('Class of Service') . "[: ]+(.+)/ui", $stext)));

                if (count($arr) > 0) {
                    $itsegment['Cabin'] = array_shift($arr);
                }

                // BookingClass
                // PendingUpgradeTo

                // Duration
                $itsegment['Duration'] = $this->re("#(\d+H\s+\d+M)#", $table[2]);

                if (empty($itsegment['Duration'])) {
                    $itsegment['Duration'] = $this->re("#\s+(\d+H\s+\d+M)\s+#", $stext);
                }

                // service?
                $node = $this->re("#" . $this->t('Operated by') . "[ ]*(.+?)(?:[ ]{3,}|\n)#", $stext);

                if (!isset($itsegment['Type'])) {
                    $itsegment['Type'] = $node;
                } else {
                    $itsegment['AirlineName'] = $node;
                }

                // Meal
                // Smoking
                // Stops
                if ($stops = $this->re("/(\d+) " . $this->t('STOP') . "/", $table[2])) {
                    $itsegment['Stops'] = (int) $stops;
                } elseif ($stops = $this->re("/(" . $this->t('NON STOP') . ")/s", $table[2])) {
                    $itsegment['Stops'] = 0;
                }

                $it['TripSegments'][] = $itsegment;
            }

            $passengerValues = array_values(array_filter($passengers));

            if (!empty($passengerValues[0])) {
                $passengerValues = preg_replace("#^\s*([[:alpha:]\s.,\-]+?)\s*(?:\.MS|\.MR|MR|MS|MSTR|MRS)?(?:\s*\((?:Child|Jeune enfant)\))?\s*$#us", '$1', $passengerValues);
                $passengerValues = preg_replace("/^\s*([^,]+?)\s*,\s*([^,]+?)\s*$/", '$2 $1', $passengerValues);

                $it['Passengers'] = array_unique($passengerValues);
            }

            $ticketNumberValues = array_values(array_filter($ticketNumbers));

            if (!empty($ticketNumberValues[0])) {
                $it['TicketNumbers'] = array_unique($ticketNumberValues);
            }

            $accountNumbersValues = array_values(array_filter($accountNumbers));

            if (!empty($accountNumbersValues[0])) {
                $it['AccountNumbers'] = array_unique($accountNumbersValues);
            }

            $itineraries[] = $it;
        }

        foreach ($cars as $segment) {
            $it = ["Kind" => "L"];
            // Number
            $it["Number"] = $this->re('/' . $this->t('Confirmation Number:') . '\s+([A-Z\d]{5,})/', $segment);
            // TripNumber
            $it['TripNumber'] = $tripNum;
            $date = [];
            $date[] = strtotime($this->normalizeDate(trim($this->re("#(.*?) - #", $segment))));
            $date[] = strtotime($this->normalizeDate(trim($this->re("#.*? - (.*?) - #", $segment))));
            // PickupDatetime
            // DropoffDatetime
            if (!empty($date[0]) && !empty($date[1]) && preg_match("#^[\s\S]*?" . $this->preg_implode($this->t("PICK-UP")) . ".+((?:.*\n){7})#",
                    $segment, $m)) {
                $dateText = $this->inOneRow($m[1]);

                if (preg_match("#^.{20,}\s+(\d+:\d+(?:[ ]*[APM]{2})?)\s+(\d+:\d+(?:[ ]*[APM]{2})?)\b#m", $dateText,
                    $mat)) {
                    $it['PickupDatetime'] = strtotime($mat[1], $date[0]);
                    $it['DropoffDatetime'] = strtotime($mat[2], $date[1]);
                }
            } elseif (!empty($date[0]) && empty($date[1]) && preg_match("#^[\s\S]*?" . $this->preg_implode($this->t("PICK-UP")) . ".+((?:.*\n){7})#",
                    $segment, $m)) {
                $dateText = $this->inOneRow($m[1]);

                if (preg_match("#^.{20,}\s+(\d+:\d+(?:[ ]*[APM]{2})?)\s+(\d+:\d+(?:[ ]*[APM]{2})?)\b#m", $dateText,
                    $mat)) {
                    $it['PickupDatetime'] = strtotime($mat[1], $date[0]);
                    $it['DropoffDatetime'] = strtotime($mat[2], $date[0]);

                    if ($it['DropoffDatetime'] < $it['PickupDatetime']) {
                        $this->logger->debug('other format DropoffDatetime');
                        unset($it['DropoffDatetime']);
                    }
                }
            }

            if (empty($it['PickupDatetime']) && preg_match('/[ ]*(\w+, \w+ \d{1,2}, \d{2,4})[ ]*\-[ ]*(\w+, \w+ \d{1,2}, \d{2,4})[ ]*\-[ ]*/',
                    $segment, $m)) {
                $it['PickupDatetime'] = strtotime(str_replace(',', '', $m[1]));
                $it['DropoffDatetime'] = strtotime(str_replace(',', '', $m[2]));
            }
            // PickupLocation
            // DropoffLocation
            // PickupPhone
            // DropoffPhone
            if (preg_match("#" . $this->t("PICK-UP AND DROP-OFF LOCATION") . "([\s\S]+?)" . $this->t("Phone") . ":(.+)#",
                $segment, $m)) {
                $it['PickupLocation'] = $it['DropoffLocation'] = preg_replace("#\s*\n\s*#", ", ", trim($m[1]));
                $it['PickupPhone'] = $it['DropoffPhone'] = trim($m[2]);
            } else {
                if (preg_match("#" . $this->t("PICK-UP LOCATION") . "([\s\S]+?)" . $this->t("Phone") . ":(.+)#",
                    $segment, $m)) {
                    $it['PickupLocation'] = preg_replace("#\s*\n\s*#", ", ", trim($m[1]));
                    $it['PickupPhone'] = trim($m[2]);
                } elseif (preg_match("#" . $this->t("PICK-UP LOCATION") . "([\s\S]+?)" . $this->t("DROP-OFF LOCATION") . "#",
                    $segment, $m)) {
                    $it['PickupLocation'] = preg_replace("#\s*\n\s*#", ", ", trim($m[1]));
                }

                if (preg_match("#" . $this->t("DROP-OFF LOCATION") . "([\s\S]+?)" . $this->t("Phone") . ":(.+)#",
                    $segment, $m)) {
                    $it['DropoffLocation'] = preg_replace("#\s*\n\s*#", ", ", trim($m[1]));
                    $it['DropoffPhone'] = trim($m[2]);
                } elseif (preg_match("#" . $this->t("DROP-OFF LOCATION") . "([\s\S]+?)" . $this->t("CAR INFO") . "#",
                    $segment, $m)) {
                    $it['DropoffLocation'] = preg_replace("#\s*\n\s*#", ", ", trim($m[1]));
                }
            }

            if (empty($it['PickupLocation']) && preg_match('/PICK-UP AND DROP-OFF LOCATION\s+(.+)\s+CAR INFO/s',
                    $segment, $m)) {
                $it['PickupLocation'] = $it['DropoffLocation'] = preg_replace('/\s+/', ' ', $m[1]);
            }

            if (preg_match("#\b\d+:\d+\s+[AP]M\b[^\n]*\s+([A-Z]{3})\b[^\n]*.+?\b\d+:\d+\s+[AP]M\b[^\n]*\s+([A-Z]{3})#s",
                    $segment, $m)
                || preg_match("#\b\d+:\d+\b.+?\b\d+:\d+\b.+?\b[AP]M\b.+?\b[AP]M\b.+?\b([A-Z]{3})\s+([A-Z]{3})\b#s",
                    $segment, $m)
            ) {
                if (empty($it['PickupLocation'])) {
                    $it['PickupLocation'] = $m[1];
                } else {
                    $it['PickupLocation'] = $it['PickupLocation'] . ' (' . $m[1] . ')';
                }

                if (empty($it['DropoffLocation'])) {
                    $it['DropoffLocation'] = $m[2];
                } else {
                    $it['DropoffLocation'] = $it['DropoffLocation'] . ' (' . $m[2] . ')';
                }
            }

            // PickupFax
            // PickupHours
            // DropoffHours
            // DropoffFax
            // RentalCompany
            if (preg_match("#\n\s*(\S.+)[\n\s]+" . $this->t("Confirmation Number:") . "#", $segment, $m)) {
                $it['RentalCompany'] = str_ireplace(' car', '', explode('   ', $m[1])[0]);
            }
            // CarType
            if (preg_match("#" . $this->t("CAR INFO") . "[\n\s]+(\S.+)#", $segment, $m)) {
                $it['CarType'] = preg_replace('/[A-Z]{3}[\d\.]+.+/', '', $m[1]);
            }
            // CarModel
            // CarImageUrl
            // RenterName
            if (preg_match("#" . $this->t("RESERVED FOR") . "[\n\s]+(\S.+)#", $segment, $m)) {
                $it['RenterName'] = $m[1];
            }
            // PromoCode
            // BaseFare
            // TotalCharge
            // Currency
            if (preg_match("#" . $this->preg_implode($this->t("Total Rate:")) . "[ ]*([\d\.]+)[ ]*([A-Z]{3})\s#",
                $segment, $m)) {
                $it['TotalCharge'] = (float) $m[1];
                $it['Currency'] = $m[2];
            }
            // TotalTaxAmount
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // ServiceLevel
            // Cancelled
            // PricedEquips
            // Discount
            // Discounts
            // Fees
            // PaymentMethod
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        foreach ($hotels as $segment) {
            $it = ["Kind" => "R"];

            // ConfirmationNumber
            $it["ConfirmationNumber"] = $this->re('/' . $this->t('Confirmation Number:') . '\s+([A-Z\d]{5,})/',
                $segment);

            if (empty($it['ConfirmationNumber'])) {
                unset($it);

                break;
            }
            // TripNumber
            $it['TripNumber'] = $tripNum;
            // ConfirmationNumbers
            // HotelName
            if (preg_match("#\n\s*(\S.+)[\n\s]+" . $this->t("Confirmation Number:") . "#", $segment, $m)) {
                $it['HotelName'] = explode('   ', $m[1])[0];
            }

            $date = [];
            $date[] = strtotime($this->normalizeDate(trim($this->re("#(.*?) - #", $segment))));
            $date[] = strtotime($this->normalizeDate(trim($this->re("#.*? - (.*?) - #", $segment))));
            // CheckInDate
            // CheckOutDate
            if (!empty($date[0]) && !empty($date[1]) && preg_match("#^(.+?){$this->t('GUESTS')}#s", $segment, $m)) {
                $m[1] = preg_replace('/^(.+)\bCXL[ ]*:.+/s', '$1', $m[1]);

                if (preg_match("#^.{20,}\s+(\d+:\d+)\s+(\d+:\d+)\s#m", $m[1], $mat)) {
                    $it['CheckInDate'] = strtotime($mat[1], $date[0]);
                    $it['CheckOutDate'] = strtotime($mat[2], $date[1]);
                } elseif (preg_match_all("#(\d+:\d+)\s.+?([AP]M)#msi", $m[1], $mat, PREG_SET_ORDER)
                    && (count($mat) === 2)
                ) {
                    $it['CheckInDate'] = strtotime($mat[0][1] . $mat[0][2], $date[0]);
                    $it['CheckOutDate'] = strtotime($mat[1][1] . $mat[1][2], $date[1]);
                } elseif (preg_match_all("#^.{20,}\s+(\d+:\d+)\s#m", $m[1], $timeMatches)
                    && count($timeMatches[1]) == 2
                ) {
                    $it['CheckInDate'] = strtotime($timeMatches[1][0], $date[0]);
                    $it['CheckOutDate'] = strtotime($timeMatches[1][1], $date[1]);
                } elseif (preg_match_all("#\b(\d+:\d+)\s {0,15}\b([AP]M)\b#msi", $m[1], $mat, PREG_SET_ORDER)
                    && (count($mat) === 1) && strpos($segment, $this->t('CHECK-IN')) !== false
                    && strpos($segment, $this->t('CHECK-OUT')) === false
                ) {
                    $it['CheckInDate'] = strtotime($mat[0][1] . $mat[0][2], $date[0]);
                    $it['CheckOutDate'] = $date[1];
                } elseif (strpos($segment, $this->t('CHECK-IN')) === false && strpos($segment, $this->t('CHECK-OUT')) === false) {
                    $it['CheckInDate'] = $date[0];
                    $it['CheckOutDate'] = $date[1];
                }
            }

            // Address
            if (preg_match("#" . $this->t("PROPERTY INFO") . "([\s\S]+?)" . $this->t("CONTACT INFO") . "#", $segment,
                $m)) {
                $it['Address'] = preg_replace("#\s*\n\s*#", ", ", trim($m[1]));
            } elseif (stripos($segment, $this->t('ROOM INFO')) !== false) {
                $it['Address'] = $it['HotelName'];

                if (preg_match("#{$this->t('ROOM DESCRIPTION')}\s+(.+)#", $segment, $m)) {
                    $it['RoomType'] = $m[1];
                }

                if (preg_match("#{$this->t('ROOM INFO')}\s+(\d+)\s+{$this->t("Guest")}#", $segment, $m)) {
                    $it['Guests'] = (int) $m[1];
                }
            }

            // DetailedAddress
            // Phone
            if (preg_match("#" . $this->t("Phone") . ":(.+)#", $segment, $m)) {
                $it['Phone'] = $m[1];
            }
            // Fax
            if (preg_match("#" . $this->t("Fax") . ":(.+)#", $segment, $m)) {
                if (stripos($m[1], 'FAX') === false) {
                    $it['Fax'] = $m[1];
                }
            }

            // GuestNames
            if (preg_match("#" . $this->t("GUESTS") . "([\s\S]+?)" . $this->t("PROPERTY INFO") . "#", $segment, $m)) {
                $passengers = array_map("trim", explode("\n", trim($m[1])));
                $passengers = preg_replace("/ - {$this->opt($this->t('Rewards Program'))}: [\w ]+\s*$/", '', $passengers);
                $passengers = preg_replace("#^\s*([[:alpha:]\s.,\-]+?)\s*(?:\.MS|\.MR|MR|MS|MSTR|MRS)?(?:\s*\((?:Child|Jeune enfant)\))?\s*$#us", '$1', $passengers);
                $passengers = preg_replace("/^\s*([^,]+?)\s*,\s*([^,]+?)\s*$/", '$2 $1', $passengers);
                $it['GuestNames'] = $passengers;
            }

            // Guests
            if (preg_match("#\n[ ]*(\d+)[ ]*" . $this->t("Guest") . "#", $segment, $m)) {
                $it['Guests'] = (int) $m[1];
            }

            // Kids
            // Rooms
            if (preg_match("#(?:\n|\/)[ ]*(\d+)[ ]*" . $this->t("Room") . "#", $segment, $m)) {
                $it['Rooms'] = (int) $m[1];
            }
            // Rate
            // RateType
            // CancellationPolicy
            // RoomType
            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            // Currency
            if (preg_match("#" . $this->t("Rate:") . "[ ]*([\d\.]+)[ ]*([A-Z]{3})\s#", $segment, $m)) {
                $it['Total'] = (float) $m[1];
                $it['Currency'] = $m[2];
            }

            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ReservationDate
            // NoItineraries

            $itineraries[] = $it;
        }

        foreach ($cruises as $cruise) {
            /** @var CruiseTrip $it */
            $it = ['Kind' => 'T', 'TripCategory' => TRIP_CATEGORY_CRUISE];

            $paxText = $this->cutText('PASSENGERS', 'INFO', $cruise);

            if (preg_match_all('/[A-Z, ]+/', $paxText, $m)) {
                foreach ($m[1] as $p) {
                    $it['Passengers'][] = $p;
                }
            }

            if (preg_match('/Voyage Ref[ ]+(\d+)/', $cruise, $m)) {
                $it['RecordLocator'] = $m[1];
            }

            /** @var CruiseTripSegment $seg */
            $seg = [];

            if (preg_match('/Port[ ]+:[ ]+(.+)\s+Sail Date[ ]+:[ ]+(\d{1,2})[ ]*([a-z]+)[ ]*(\d{2,4}) Time[ ]+:[ ]+(\d{1,2})[ :]?(\d{2}[ap]m)\s+Return[ ]+:[ ]+(\d{1,2})[ ]*([a-z]+).+\s+Port[ ]+:[ ]+(.+)/i',
                $cruise, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepDate'] = strtotime($m[2] . ' ' . $m[3] . ' ' . $m[4] . ', ' . $m[5] . ':' . $m[6]);
                $seg['ArrDate'] = strtotime($m[7] . ' ' . $m[8] . ' ' . $m[4]);
                $seg['ArrName'] = $m[9];
                $it['TripSegments'][] = $seg;
            }

            $itineraries[] = $it;
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#", // SAT, OCT 14, 2017
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#", // LUNEDÌ 27 FEBBRAIO 2017
        ];
        $out = [
            "$2 $1 $3",
            "$1 $2 $3",
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) === 1) {
            $ret[] = reset($r);
        }

        return $ret;
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map('preg_quote', $field)) . ')';
    }

    private function inOneRow($text)
    {
        $textRows = explode("\n", $text);
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                if (isset($row[$l]) && (trim($row[$l]) !== '')) {
                    $notspace = true;
                    $oneRow[$l] = $row[$l];
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function cutText($start, $end, $text)
    {
        if (empty($start) && empty($end) && empty($text)) {
            return false;
        }

        return stristr(stristr($text, $start), $end, true);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
