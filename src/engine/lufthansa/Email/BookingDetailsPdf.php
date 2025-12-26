<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Common\Parser\Util\PriceHelper;

// TODO: We put it here with HTML -> It5889106

class BookingDetailsPdf extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-15425861.eml, lufthansa/it-15491189.eml, lufthansa/it-2153282.eml, lufthansa/it-2197413.eml, lufthansa/it-2213232.eml, lufthansa/it-2425242.eml, lufthansa/it-2503178.eml, lufthansa/it-254514033.eml, lufthansa/it-2994331.eml, lufthansa/it-3142328.eml, lufthansa/it-3313085.eml, lufthansa/it-3503464.eml, lufthansa/it-3504583.eml, lufthansa/it-3848526.eml, lufthansa/it-3923204.eml, lufthansa/it-7388835.eml, lufthansa/it-8007488.eml";

    public $reFrom = 'lufthansa.com';

    public $reSubject = [
        'de' => ['Buchungsdetails'],
        'it' => ['Dettagli della prenotazione'],
        'en' => ['Booking details'],
    ];

    public $langDetectors = [
        'pt' => ['Preço total'],
        'de' => ['Buchungsdetails'],
        'it' => ['Ha prenotato un servizio supplementare', "Ha prenotato un servizio\nsupplementare", 'Ha prenotato un serviziosupplementare', 'Dettagli della prenotazione', 'Dettagli del biglietto e conferma della'],
        'en' => ['Booking details', 'Changes to your booking', 'Ticket details & travel information', 'Your itinerary'],
        'ja' => ['お客様のご旅程'],
    ];

    //	public $pdfPattern = '\d+_[A-Z]+_[A-Z\d]+(?: \(\d+\))?\.pdf';
    public $pdfPattern = '.*pdf';
    public $text = '';

    public static $dictionary = [
        'pt' => [
            'Lufthansa booking code:'                      => 'Código da reserva da Lufthansa:',
            //'Your booking codes:'                          => 'Ihre Buchungscodes:',
            'Passenger information'                        => 'Informação do passageiro',
            'Ticket no.:'                                  => ['Número do bilhete:'],
            'Your itinerary'                               => 'O seu itinerário',
            'operated by:'                                 => 'operado por:',
            "Status"                                       => "Estatuto:",
            'Class:'                                       => ['Classe/Tarifa:'],
            //'Seat:'                                        => ['Sitzplatz:', 'Sitzplätze:'],
            //'Meal:'                                        => 'Verpflegung:',
            'Total Price for all Passengers'               => 'Preço total para todos os passageiros ',
            //'your\s+booking\s+code\s+is\s+not\s+displayed' => 'Buchungscode\s+nicht\s+angezeigt',
        ],
        'de' => [
            'Lufthansa booking code:'                      => 'Lufthansa Buchungscode:',
            'Your booking codes:'                          => 'Ihre Buchungscodes:',
            'Passenger information'                        => 'Passagierinformationen',
            'Ticket no.:'                                  => ['Ticket Nr.:', 'Ticketnummer:'],
            'Your itinerary'                               => 'Ihr Reiseverlauf',
            'operated by:'                                 => 'durchgeführt von:',
            "Status"                                       => "Status",
            'Class:'                                       => ['Buchungsklasse:', 'Klasse/Tarif:'],
            'Seat:'                                        => ['Sitzplatz:', 'Sitzplätze:'],
            'Meal:'                                        => 'Verpflegung:',
            'Total Price for all Passengers'               => 'Gesamtpreis für alle Reisenden',
            'your\s+booking\s+code\s+is\s+not\s+displayed' => 'Buchungscode\s+nicht\s+angezeigt',
        ],
        'ja' => [
            'Lufthansa booking code:' => 'ルフトハンザの予約番号:',
            //            'Your booking codes:' => '',
            'Passenger information' => '搭乗者情報',
            'Ticket no.:'           => ['航空券番号:'],
            'Your itinerary'        => 'お客様のご旅程',
            'operated by:'          => '運航航空会社:',
            "Status"                => "状況:",
            'Class:'                => ['クラス/運賃:', '運賃賃:'],
            'Seat:'                 => ['座席:', '席:'],
            //            'Meal:' => '',
            'Total Price for all Passengers' => '合計金額（全員分）',
            //            'your\s+booking\s+code\s+is\s+not\s+displayed' => ''
        ],
        'it' => [
            'Lufthansa booking code:' => 'Codice di prenotazione Lufthansa:',
            //			'Your booking codes:' => '',
            'Passenger information' => 'Informazioni sui passeggeri',
            'Ticket no.:'           => 'Numero biglietto:',
            'Your itinerary'        => 'Il suo itinerario',
            'operated by:'          => 'operato da',
            "Status"                => "Situazione volo",
            'Class:'                => 'Classe di prenotazione:',
            'Seat:'                 => 'Posto:',
            //			'Meal:' => '',
            //			'Total Price for all Passengers' => '',
        ],
        'en' => [
            'Passenger information' => ['Passenger information', 'Passenger Information'],
            'Ticket no.:'           => ['Ticket no.:', 'Ticket number:'],
            'Class:'                => ['Class:', 'Class/Fare:'],
            'Seat:'                 => ['Seat:', 'Seats:'],
            //???'Your booking codes:' => '',
            //'your\s+booking\s+code\s+is\s+not\s+displayed' => '',
        ],
    ];

    public $lang = '';
    private $year;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($textPdf, 'Lufthansa Service Center') === false && strpos($textPdf, 'Lufthansa Online') === false && strpos($textPdf, 'Lufthansa passenger') === false && strpos($textPdf, 'Lufthansa German Airline') === false && strpos($textPdf, 'Deutsche Lufthansa AG') === false && false === stripos($textPdf, 'Lufthansa offers')) {
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
        $this->year = date('Y', strtotime($parser->getDate()));

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($textPdf)) {
                $this->text = $textPdf;

                break;
            } else {
                continue;
            }
        }

        if (empty($this->text)) {
//            $this->logger->alert('empty text pdf');
            return null;
        }

        $itineraries = [];
        $this->parsePdf($itineraries);
        $result = [
            'emailType' => 'BookingDetailsPdf' . ucfirst($this->lang),
        ];

        $tot = $this->getTotalCurrency($this->re('/' . $this->t('Total Price for all Passengers') . '\s+(.+)/i', $this->text));

        if ($tot['Total'] !== '') {
            if (count($itineraries) === 1) {
                $itineraries[0]['TotalCharge'] = $tot['Total'];
                $itineraries[0]['Currency'] = $tot['Currency'];
            }
            $result['parsedData'] = [
                'Itineraries' => $itineraries,
                'TotalCharge' => ['Amount' => $tot['Total'], 'Currency' => $tot['Currency']],
            ];
        } else {
            $result['parsedData'] = [
                'Itineraries' => $itineraries,
            ];
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

    private function parsePdf(&$itineraries): void
    {
        $patterns = [
            'time'         => '\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?', // 03:40 PM    |    16:15 Uhr
            'nameTerminal' => '/^(?<name>.+?)\s+\(\s*Terminal\s+(?<terminal>[A-Z\d][A-Z\d ]*)\s*\)/i', // Frankfurt Flughafen (Terminal 1)
        ];

        $text = $this->text;

        $passengers = [];
        $ticketNumbers = [];
        $passengersText = '';

        if (is_array($this->t('Passenger information'))) {
            foreach ($this->t('Passenger information') as $value) {
                $passengersStart = stripos($text, $value);

                if (!empty($passengersStart)) {
                    break;
                }
            }
        } else {
            $passengersStart = stripos($text, $this->t('Passenger information'));
        }
        $passengersEnd = strpos($text, $this->t('Your itinerary'));

        if ($passengersStart !== false && $passengersEnd !== false) {
            $passengersText = preg_replace('/[ ]{2,}/', "\n", substr($text, $passengersStart, $passengersEnd - $passengersStart));
        }

        // MICKENS / VALOIS MRS
        if (preg_match_all('/^\s*(\w[^:\n\)]+\/[^:\n\)]+)\s*$/mu', $passengersText, $m)) {
            $passengers = array_unique($m[1]);
            $passengers = preg_replace("/ (MR|MRS|MRSDR|DR|MRSPROF)\s*$/", "", $passengers);
            $passengers = preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", "$2 $1", $passengers);
        }

        // Numero biglietto: 220 2394246579
        if (preg_match_all("/{$this->opt($this->t('Ticket no.:'))}[ ]+(\d.+\d)/m", $passengersText, $m)) {
            foreach ($m[1] as $value) {
                $ticketNumbers = array_unique(array_merge($ticketNumbers, array_map('trim', explode(",", $value))));
            }
        }

        $flights = substr(
            $text,
            $sp = strpos($text, $this->t('Your itinerary')) + strlen($this->t('Your itinerary')),
            strpos($text, 'Lufthansa Online Services') - $sp
        );

        $rails = [];
        $airs = [];

        $nodes = $this->splitText($flights, '/^[ ]*([^,.\d\s]{2,}\.[ ]+\d{1,2}\.?[ ]+[^,.\d\s]{3,}[ ]+\d{4}[ ]*:.+$)/m', true);

        if (empty($nodes)) {
            $nodes = preg_split('/^[ ]*(\w+\.[ ]+\d{1,2}[ ]+\d{1,2}[ ]*\w+\s+\w+\s+\d{4}[ ]*\:.+$)/mu', $flights, -1, PREG_SPLIT_DELIM_CAPTURE);

            if (empty($this->year) && false !== $nodes && preg_match('/^[ ]*\w+\.[ ]+\d{1,2}[ ]+\d{1,2}[ ]*\w+\s+\w+\s+(\d{4})[ ]*\:.+/mu', substr($flights, 0, 100), $m)) {
                $this->year = $m[1];
            }
        }

        if (!empty($rl = $this->re('/' . $this->t('Lufthansa booking code:') . '\s+([A-Z\d]{5,})/', $text))) {
            foreach ($nodes as $node) {
                if (stripos($node, 'Lufthansa Express Rail') !== false) {
                    $rails[$rl][] = preg_replace('/^\s*([^\n]+)$.+?^\s*(\d{1,2}:\d{2}.+)/ms', "$1\n$2", $node);
                } else {
                    $airs[$rl][] = preg_replace('/^\s*([^\n]+)$.+?^\s*(\d{1,2}:\d{2}.+)/ms', "$1\n$2", $node);
                }
            }
        } else {
            $defaultRL = $this->re('/' . $this->t('Your booking codes:') . '\s+([A-Z\d]{5,})/', $text);

            if (empty($defaultRL) && !empty($this->re('/(' . $this->t('your\s+booking\s+code\s+is\s+not\s+displayed') . ')/', $text))) {
                $defaultRL = CONFNO_UNKNOWN;
            }

            foreach ($nodes as $node) {
                if (stripos($node, 'Lufthansa Express Rail') !== false) {
                    $node = preg_replace('/^\s*([^\n]+)$.+?^\s*(\d{1,2}:\d{2}.+)/ms', "$1\n$2", $node);
                    $operator = $this->re('/' . $this->t('operated by:') . '\s+(.+)/', $node);

                    if (!empty($rl = $this->re('/\s+([A-Z\d]{5,})\s+\(\s*' . $operator . '\s*\)/i', $text))) {
                        $rails[$rl][] = $node;
                    } else {
                        $rails[$defaultRL][] = $node;
                    }
                } else {
                    $node = preg_replace('/^\s*([^\n]+)$.+?^\s*(\d{1,2}:\d{2}.+)/ms', "$1\n$2", $node);
                    $operator = $this->re('/' . $this->t('operated by:') . '\s+(.+)/', $node);

                    if (!empty($rl = $this->re('/\s+([A-Z\d]{5,})\s+\(\s*' . $operator . '\s*\)/i', $text))) {
                        $airs[$rl][] = $node;
                    } else {
                        $airs[$defaultRL][] = $node;
                    }
                }
            }
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];
            $it['Kind'] = 'T';

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // Passengers
            if (!empty($passengers[0])) {
                $it['Passengers'] = $passengers;
            }

            // TicketNumbers
            if (!empty($ticketNumbers[0])) {
                $it['TicketNumbers'] = array_map(function ($s) { return str_replace("‑", '-', $s); }, $ticketNumbers);
            }

            foreach ($segments as $stext) {
                $rows = explode("\n", preg_split("#(" . $this->opt($this->t("Status")) . ":|\n\n\n)#", $stext)[0]);

                if (!isset($rows[1])) {
                    $this->logger->alert('incorrect table rows!');

                    return;
                }
                unset($rows[0]);

                $pos = $this->rowColsPos($this->inOneRow($rows));

                if (count($pos) == 2 && $pos[0] < 10 && $pos[1] > 75) {
                    $pos1 = $this->rowColsPos($rows[1]);

                    if (isset($pos1[1])) {
                        $pos[] = $pos1[1];
                        sort($pos);
                    }
                }
                unset($rows[0]);
                $table = $this->splitCols(implode("\n", $rows), $pos);

                if (count($table) < 3) {
                    $this->logger->alert('incorrect table parse!');

                    return;
                }

                $date = strtotime($this->normalizeDate($this->re("#(.*?):#", explode("\n", $stext)[0])));

                if (false === $date) {
                    $date = strtotime($this->normalizeDate($this->re("#(.+)#u", explode("\n", $stext)[0])));
                }

                $itsegment = [];

                // AirlineName
                // FlightNumber
                if (preg_match('/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$/m', $table[2], $matches)) {
                    $itsegment['AirlineName'] = $matches[1];
                    $itsegment['FlightNumber'] = $matches[2];
                }

                // SINGAPORE SG CHANGI (SIN) Terminal 2
                //kostyl
                $table[1] .= "\n";
                preg_match_all('/^[ ]*(?<Name>.*?)\((?<Code>[A-Z]{3})\)\s+(?:.*\b(?i)TERMINAL\b[ ]*:?[ ]*(?<Terminal>(?-i)[A-Z\d]+))?/m', $table[1], $airportMatches, PREG_SET_ORDER);

                if (count($airportMatches) !== 2) {
                    $this->logger->alert('incorrect airports parse!');

                    return;
                }

                // DepCode
                if (stripos($airportMatches[0]['Name'], 'BUS STATION') !== false) { // Strasbourg (Hbf), Boulevard de Metz BUS STATION (XER)
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                } else {
                    $itsegment['DepCode'] = $airportMatches[0]['Code'];
                }

                // ArrCode
                if (stripos($airportMatches[1]['Name'], 'BUS STATION') !== false) {
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                } else {
                    $itsegment['ArrCode'] = $airportMatches[1]['Code'];
                }

                // DepName
                // DepartureTerminal
                if (preg_match($patterns['nameTerminal'], $airportMatches[0]['Name'], $matches)) {
                    $itsegment['DepName'] = trim($matches['name']);
                    $itsegment['DepartureTerminal'] = $matches['terminal'];
                } else {
                    $itsegment['DepName'] = trim($airportMatches[0]['Name']);

                    if (!empty($airportMatches[0]['Terminal'])) {
                        $itsegment['DepartureTerminal'] = $airportMatches[0]['Terminal'];
                    }
                }

                // ArrName
                // ArrivalTerminal
                if (preg_match($patterns['nameTerminal'], $airportMatches[1]['Name'], $matches)) {
                    $itsegment['ArrName'] = trim($matches['name']);
                    $itsegment['ArrivalTerminal'] = $matches['terminal'];
                } else {
                    $itsegment['ArrName'] = trim($airportMatches[1]['Name']);

                    if (!empty($airportMatches[1]['Terminal'])) {
                        $itsegment['ArrivalTerminal'] = $airportMatches[1]['Terminal'];
                    } elseif (!empty($itsegment['ArrCode']) && $itsegment['ArrCode'] !== TRIP_CODE_UNKNOWN && preg_match("#\({$itsegment['ArrCode']}\)\s+((?!TERMINAL).*\s*)?(?:\bTERMINAL\b[ ]*:?[ ]*(?<Terminal>[A-Z\d]+))#", $table[1], $m)) {
                        $itsegment['ArrivalTerminal'] = $m['Terminal'];
                    }
                }

                // DepDate
                $itsegment['DepDate'] = strtotime($this->re('/^(' . $patterns['time'] . ')/', $table[0]), $date);

                // ArrDate
                if (
                    preg_match('/\n(' . $patterns['time'] . ').*(?:\+[ ]*(?<overnight>\d{1,3}))/', $table[0], $matches)
                    || preg_match('/\n(' . $patterns['time'] . ')/', $table[0], $matches)
                ) {
                    $itsegment['ArrDate'] = strtotime($matches[1], $date);

                    if (!empty($matches['overnight']) && !empty($itsegment['ArrDate'])) {
                        $itsegment['ArrDate'] = strtotime("+{$matches['overnight']} days", $itsegment['ArrDate']);
                    }
                }

                // Operator
                $itsegment['Operator'] = $this->re('/' . $this->t('operated by:') . '\s+(.+)/', $table[2]);

                // TODO: split bottom table on cells (Status|Seat|Class|Meal|Baggage) via $this->splitCols()

                // Cabin
                $itsegment['Cabin'] = $this->re('/' . $this->opt($this->t('Class:')) . '\s+([^:\n]*?)(?:[ ]{2}|$|\s*\([A-Z]{1,2}\))/m', $stext);

                // BookingClass
                if (!$itsegment['BookingClass'] = $this->re('/' . $this->opt($this->t('Class:')) . '\s+\(([A-Z]{1,2})\)/', $stext)) {
                    $itsegment['BookingClass'] = $this->re('/' . $this->opt($this->t('Class:')) . '[\s\S]*?\(([A-Z]{1,2})\)/', $stext);
                }

                // Seats
                $seatsText = $this->re('/' . $this->opt($this->t('Seat:')) . '\s+([A-Z\d\/ ,]+)/', $stext);
                $seatsTextParts = preg_split('/[ ]{2,}/', $seatsText);
                $seatTexts = explode('/', $seatsTextParts[0]);

                if (count($seatTexts) === 1) {
                    $seatTexts = explode(',', $seatTexts[0]);
                }
                $seatTexts = array_map("trim", $seatTexts);
                $seatValues = array_values(array_filter($seatTexts));

                if (!empty($seatValues[0])) {
                    $itsegment['Seats'] = $seatValues;
                }

                // Meal
                $meal = $this->re('/' . $this->opt($this->t('Meal:')) . '[ ]+(.+?)(?:[ ]{2,}|$)/m', $stext);

                if ($meal) {
                    $itsegment['Meal'] = $meal;
                }

                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }

        foreach ($rails as $rl=>$segments) {
            $it = [];
            $it['Kind'] = 'T';
            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            // RecordLocator
            $it['RecordLocator'] = CONFNO_UNKNOWN;

            // TripNumber
            $it['TripNumber'] = $rl;

            // Passengers
            if (!empty($passengers[0])) {
                $it['Passengers'] = $passengers;
            }

            foreach ($segments as $stext) {
                $rows = explode("\n", preg_split("#(" . $this->opt($this->t("Status")) . ":|\n\n\n)#", $stext)[0]);

                if (!isset($rows[1])) {
                    $this->logger->debug('incorrect table rows');

                    return;
                }
                unset($rows[0]);

                $pos = $this->rowColsPos($this->inOneRow($rows));

                if (count($pos) == 2 && $pos[0] < 10 && $pos[1] > 75) {
                    $pos1 = $this->rowColsPos($rows[1]);

                    if (isset($pos1[1])) {
                        $pos[] = $pos1[1];
                        sort($pos);
                    }
                }
                unset($rows[0]);
                $table = $this->splitCols(implode("\n", $rows), $pos);

                if (count($table) < 3) {
                    $this->logger->debug('incorrect table parse');

                    return;
                }

                $date = strtotime($this->normalizeDate($this->re("#(.*?):#", explode("\n", $stext)[0])));

                $itsegment = [];

                // AirlineName
                // FlightNumber
                if (preg_match('/^\s*([A-Z\d]{2})\s*(\d+)$/m', $table[2], $matches)) {
                    $itsegment['AirlineName'] = $matches[1];
                    $itsegment['FlightNumber'] = $matches[2];
                }

                preg_match_all('/^[ ]*(?<Name>[\w\- ]{5,}?)[ ]*$/m', $table[1], $airportMatches, PREG_SET_ORDER);

                if (count($airportMatches) !== 2) {
                    $this->logger->debug('incorrect rails parse');

                    return;
                }

                // DepName
                $itsegment['DepName'] = $airportMatches[0]['Name'];

                // ArrName
                $itsegment['ArrName'] = $airportMatches[1]['Name'];

                // DepDate
                $itsegment['DepDate'] = strtotime($this->re("#^(\d+:\d+)#", $table[0]), $date);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->re("#\n(\d+:\d+)#", $table[0]), $date);

                // Cabin
                $itsegment['Cabin'] = $this->re('/' . $this->opt($this->t('Class:')) . '\s+(.*?)\s*\(\w\)/', $stext);

                // BookingClass
                if (!$itsegment['BookingClass'] = $this->re('/' . $this->opt($this->t('Class:')) . '\s+.*?\s+\((\w)\)/', $stext)) {
                    $itsegment['BookingClass'] = $this->re('/' . $this->opt($this->t('Class:')) . '\s+\((\w)\)/', $stext);
                }

                // Seats
                $seatsText = $this->re('/' . $this->opt($this->t('Seat:')) . '\s+([A-Z\d\/ ]+)/', $stext);
                $seatsTextParts = preg_split('/[ ]{2,}/', $seatsText);
                $seatTexts = explode('/', $seatsTextParts[0]);
                $seatTexts = array_map(function ($s) {
                    return trim($s);
                }, $seatTexts);
                $seatValues = array_values(array_filter($seatTexts));

                if (!empty($seatValues[0])) {
                    $itsegment['Seats'] = $seatValues;
                }

                // DepCode
                // ArrCode
                if (!empty($itsegment['DepName']) && !empty($itsegment['ArrName']) && !empty($itsegment['DepDate']) && !empty($itsegment['ArrDate'])) {
                    $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str): string
    {
//        $this->logger->alert('Date: '.$str);
        $in = [
            "#^[^\d\s]+\.\s+(\d+)\.?\s+([^\d\s]+)\s+(\d{4})$#", // Fr. 25. August 2017
            '/^[^\d\s]+\.[ ]+(\d{1,2})[ ]+(\d{1,2})[ ]*[^\d\s]+$/u', // 月. 14 10月
        ];
        $out = [
            "$1 $2 $3",
            "{$this->year}-$2-$1",
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

    //	private function split($re, $text){
    //		$r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    //		$ret = [];
//
    //		if (count($r) > 1){
    //			array_shift($r);
    //			for($i=0; $i<count($r)-1; $i+=2){
    //				$ret[] = $r[$i].$r[$i+1];
    //			}
    //		} elseif (count($r) === 1){
    //			$ret[] = reset($r);
    //		}
    //		return $ret;
    //	}

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;
        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }
        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];
        if ($text === null)
            return $cols;
        $rows = explode("\n", $text);
        if ($pos === null || count($pos) === 0) $pos = $this->rowColsPos($rows[0]);
        arsort($pos);
        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);
        foreach ($cols as &$col) $col = implode("\n", $col);
        return $cols;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];
        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);
            for ($i=0; $i < count($textFragments)-1; $i+=2)
                $result[] = $textFragments[$i] . $textFragments[$i+1];
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }
        return $result;
    }

    private function opt($field, $delim = '/')
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) use ($delim) { return str_replace(' ', '\s+', preg_quote($s, $delim)); }, $field)) . ')';
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $matches)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $matches)
            || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $matches)
        ) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['c']) ? $matches['c'] : null;
            $tot = PriceHelper::parse($matches['t'], $currencyCode);
            $cur = $matches['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function inOneRow(array $textRows): string
    {
        //		$textRows = explode("\n", $text);
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
}
