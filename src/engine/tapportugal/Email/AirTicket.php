<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

// parsers with similar PDF-formats: airtransat/BoardingPass, asiana/BoardingPassPdf, aviancataca/BoardingPass, aviancataca/TicketDetails, czech/BoardingPass, lotpair/BoardingPass, sata/BoardingPass, tamair/BoardingPassPDF(object), luxair/YourBoardingPassNonPdf, saudisrabianairlin/BoardingPass

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-11655972.eml, tapportugal/it-12590071.eml, tapportugal/it-1896134.eml, tapportugal/it-1897796.eml, tapportugal/it-22422933.eml, tapportugal/it-35789480.eml, tapportugal/it-4148903.eml, tapportugal/it-4710343.eml, tapportugal/it-4733695.eml, tapportugal/it-4845262.eml, tapportugal/it-4856280.eml, tapportugal/it-6234815.eml, tapportugal/it-6284581.eml, tapportugal/it-6337598.eml, tapportugal/it-6400464.eml, tapportugal/it-6883522.eml, tapportugal/it-6920468.eml, tapportugal/it-8319575.eml, tapportugal/it-8323867.eml, tapportugal/it-37806809.eml";

    public $reBody = [
        'en' => ['Booking Details', 'flight with the following details'],
        'pt' => ['Detalhes da Reserva'],
        'fr' => ['Votre réservation'],
        'it' => ['DETTAGLI DELLA PRENOTAZIONE'],
        'es' => ['DETALLES DE LA RESERVA', 'Detalles de la reserva'],
        'pl' => ['Szczegóły rezerwacji'],
    ];
    public $lang = 'es'; // it is in the English format
    public static $dict = [
        'en' => [
            'Booking Reference'    => ['Booking Reference', 'Booking reference'],
            'Latest Boarding Time' => [
                'Hora Limite de Embarque', // pt
                "Heure Limite d'Embarquement", // fr
                'Latest Boarding Time', 'Please report at the boarding gate at the latest by',
            ],
            'From' => [
                'Partida:',
                'Embarque:', // pt
                'From:',
            ],
            'To' => [
                'Chegada:', // pt
                'To:',
            ],
        ],
        'pt' => [
            'Booking Reference'    => 'Código da reserva:',
            'Passenger'            => 'Passageiro:',
            'Latest Boarding Time' => ['Hora Limite de Embarque', 'Horário de embarque'],
            'From'                 => ['De:', 'Partida:'],
            'To'                   => ['Para:', 'Chegada:'],
            'Flight'               => 'Voo:',
        ],
        'fr' => [ // it-6234815.eml
            'Booking Reference'    => ['Référence de votre réservation:', 'Numéro de Réservation:', 'Référence de réservation:'],
            'Passenger'            => ['Passager:', 'Passenger:'],
            'Latest Boarding Time' => ['Heure limite d’enregistrement', 'Heure d’embarquement'],
            'From'                 => 'De',
            'To'                   => ['Vers:', 'A:', 'À:'],
            'Flight'               => 'Vol',
        ],
        'it' => [
            'Booking Reference'    => 'Codice Prenotazione',
            'Passenger'            => 'Passeggero',
            'Latest Boarding Time' => "Tempo massimo per l'imbarco",
            'From'                 => 'Da:',
            'To'                   => 'A:',
            'Flight'               => 'Volo',
        ],
        'es' => [
            'Booking Reference'    => ['Referencia de la reserva', 'Booking Reference:'],
            'Passenger'            => 'Pasajero',
            'Latest Boarding Time' => 'Hora de embarque límite',
            'From'                 => ['De:', 'DESDE'],
            'To'                   => ['A:', 'A'],
            'Flight'               => 'Vuelo',
        ],
        'pl' => [
            'Booking Reference'    => 'Numer rezerwacji:',
            'Passenger'            => 'Pasażer:',
            'Latest Boarding Time' => 'Godzina zamknięcia boardingu',
            'From'                 => 'Od:',
            'To'                   => 'Do:',
            'Flight'               => 'Lot:',
        ],
    ];

    protected $code = null;

    protected $headers = [
        'algerie' => [
            'from' => ['@airalgerie.dz'],
            'subj' => [
                'Your Email Confirmation',
            ],
        ],
        'tapportugal' => [
            'from' => ['tap.pt', 'flytap.com'],
            'subj' => [
                'Your Boarding Pass Confirmation',
                'Confirmacao de cartao de embarque',
            ],
        ],
        'srilankan' => [
            'from' => ['@srilankan.com'],
            'subj' => [
                'Your Boarding Pass Confirmation',
            ],
        ],
        'aircaraibes' => [
            'from' => ['aircaraibes.com'],
            'subj' => [
                'Votre carte d’accès à bord avec Air Caraïbes',
            ],
        ],
        'kestrelflyer' => [
            'from' => ['airmauritius.com'],
            'subj' => [
                'Air Mauritius',
            ],
        ],
        'egyptair' => [
            'from' => [],
            'subj' => [
                'EGYPTAIR: Your Bagtag',
            ],
        ],
        'malaysia' => [
            'from' => ['malaysiaairlines.com'],
            'subj' => [
                'Boarding Pass Confirmation',
            ],
        ],
        'amadeus' => [
            'from' => ['noreply@amadeus.com'],
            'subj' => [
                'Boarding Pass Confirmation',
            ],
        ],
        'finnair' => [
            'from' => ['confirmation@finnair.com'],
            'subj' => [
                'Boarding Pass Confirmation',
            ],
        ],
        'boliviana' => [
            'from' => ['noreply@boa.bo'],
            'subj' => [
                'Your Boarding Pass Confirmation',
                'Confirmación de Pase a Bordo', // es
            ],
        ],
    ];

    protected static $bodies = [
        'algerie' => [
            '//a[contains(@href,"//airalgerie.dz/")]',
            "Merci d’avoir choisi de voyager avec Air Algérie",
        ],
        'tapportugal' => [
            '//a[contains(@href, "flytap.com")]',
            'Thank you for choosing TAP',
        ],
        'srilankan' => [
            '//a[contains(@href, "srilankan.com")]',
            'Thank you for choosing SriLankan AirLines',
        ],
        'aircaraibes' => [
            '//a[contains(@href, "aircaraibes.com")]',
            'AIR CARAIBES',
        ],
        'kestrelflyer' => [
            '//a[contains(@href, "airmauritius.com")]',
            'Air Mauritius',
        ],
        'egyptair' => [
            '//a[contains(@href, "//www.egyptair.com")]',
            'Thank you for choosing Egyptair',
        ],
        'malaysia' => [
            '//a[contains(@href, ".malaysiaairlines.com")]',
            'Thank you for choosing Malaysia Airlines',
        ],
        'finnair' => [
            '//a[contains(@href, "finnair.com")]',
            'Thank you for using Finnair online',
        ],
        'lotpair' => [
            '//a[contains(@href, "www.lot.com")]',
            '//node()[contains(normalize-space()," na stronie www.lot.com") or contains(normalize-space(),"proszę odwiedzić serwis lot.com")]',
            'Dziękujemy za wybranie Polskich Linii Lotniczych LOT',
        ],
        'boliviana' => [
            '//a[contains(@href, "www.boa.bo/")]',
            '//node()[contains(normalize-space(),"@boa.bo") or contains(normalize-space(),"Boliviana de Aviacion")]',
        ],
        'amadeus' => [ // this item always last!
            '//a[contains(@href, "www.wideroe.no")]',
            '//a[contains(@href, "amadeus.net")]',
            'Thank you for choosing Wideroe',
            'Thank for using Air Corsica Online',
        ],
    ];
    protected $bps = [];

    private $detectBody = [
        'Thank you for using LATAM online check-in service',
        'Please find enclosed a confirmation document of your journey',
        'We confirm you that you have been successfully checked-in',
        'Obrigado por utilizar o check-in online',
        'En el anexo se encuentra su tarjeta de embarque',
        'Veuillez trouver en pièce jointe votre carte d’embarquement afin de l’imprimer',
        'Thank for using Air Corsica Online',
        'In allegato troverà la carta di imbarco da stampare',
        'Thank you for choosing TAP, we wish you a pleasant journey',
        "Merci d'avoir choisi de voyager avec Air Algérie, nous vous souhaitons un agréable",
        'Merci d’avoir choisi de voyager avec Air Algérie, nous vous souhaitons un agréable',
        "Merci d'avoir choisi Air Mauritius, nous vous souhaitons un agréable",
        'Merci d’avoir choisi Air Mauritius, nous vous souhaitons un agréable',
        'Thank you for choosing Egyptair, we wish you a pleasant journey',
        'Dziękujemy za wybranie Polskich Linii Lotniczych LOT',
        'Thank you for choosing Malaysia Airlines, we wish you a pleasant journey',
        'Thank you for using Finnair online',
        'We confirm that you have been successfully checked-in',
        'Le confirmamos que usted realizó su checkin exitosamente', // es
    ];

    /** @var string */
    private $pdfText = '';

    public function detectEmailFromProvider($from)
    {
        foreach ($this->headers as $code => $arr) {
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

        foreach ($this->headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function findCutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('Boarding\s*Pass\.pdf');

        if (count($pdf) === 1) {
            $this->pdfText = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));
        }

        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }
        }

        $its = $this->parseEmail($parser);

        if ((empty($its) || empty($its[0]['TripSegments'])) && empty($pdf)) {//pdf - go to parse other parsers BPass*.php
            $text = $parser->getPlainBody();

            if (!empty($text)) {
                foreach ($this->reBody as $lang => $reBody) {
                    foreach ($reBody as $re) {
                        if (stripos($text, $re) !== false) {
                            $this->lang = $lang;

                            break 2;
                        }
                    }
                }
                $its = $this->parseEmailPlain($text, $parser);
            }
        }

        if (count($this->bps) > 0) {
            $res['parsedData']['BoardingPass'] = $this->bps;
        }
        $res['parsedData']['Itineraries'] = $its;
        $res['emailType'] = 'AirTicket' . ucfirst($this->lang);

        if ($code = $this->getProvider($parser)) {
            $res['providerCode'] = $code;
        }

        return $res;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detectBody as $detect) {
            if (is_string($detect) && stripos($body, $detect) !== false) {
                return true;
            }
        }
        $body = $parser->getPlainBody();

        foreach ($this->detectBody as $detect) {
            if (is_string($detect) && stripos($body, $detect) !== false) {
                return true;
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

    public static function getEmailProviders()
    {
        return array_keys(self::$bodies);
    }

    protected function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subj' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'tapportugal') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach (self::$bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (
                        (stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail(\PlancakeEmailParser $parser)
    {
        $patterns = [
            'dateTime' => '/(?<date>.{4,}\b)\s*-\s*(?<time>\d{1,2}:\d{2}(?::00)?(?:\s*[AaPp][Mm])?)$/', // 31/05/2015 - 11:50 | 04/04/2019 - 22:20:00
        ];

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode('/descendant::text()[' . $this->starts($this->t('Booking Reference')) . '][1]/following::text()[normalize-space(.)][1]', null, true, '/^([A-Z\d]{5,})$/');

        // Passengers
        $it['Passengers'] = array_unique($this->http->FindNodes('//text()[' . $this->starts($this->t('Passenger')) . ']/following::text()[normalize-space(.)][1]', null, '/^(\w[^:]+)$/u'));

        if (count($it['Passengers']) === 0) {
            $it['Passengers'] = array_unique($this->http->FindNodes("(//*[contains(text(), '" . $this->t('Flight') . "')]/preceding::*[normalize-space(.) and not(span)][1])[1]"));

            if (!$it['RecordLocator']) { // it-6883522.eml
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }
        }

        // TicketNumbers
        if (!empty($this->pdfText) && stripos($this->pdfText, 'Ticket') !== false && preg_match('/Ticket\s+(?:ETKT\s+)?([\d\s\-]+)/iu', $this->pdfText, $m)) {
            $it['TicketNumbers'][] = trim($m[1]);
        }

        // AccountNumbers
        if (!empty($this->pdfText) && stripos($this->pdfText, 'FREQUENT FLYER') !== false
            && preg_match('/(?i)FREQUENT FLYER\n+.+?[ ]{3,}(?-i)([A-Z]{2,}[ -]{0,1}\d{5,})$/m', $this->pdfText, $m)
        ) {
            // SKEBS126958909
            $it['AccountNumbers'] = [$m[1]];
        }

        $it['TripSegments'] = [];
        $xpath = '//td[ ' . $this->starts($this->t('From')) . ' and not(.//td) and not(contains(.,"Sent")) and not(contains(.,"Subject")) ]';
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->info("Segments not found: {$xpath}");
        }

        foreach ($segments as $root) {
            $seg = [];

            // DepName
            $seg['DepName'] = $this->http->FindSingleNode('./descendant::text()[normalize-space(.)][2]', $root);

            // DepartureTerminal
            $terminalDepVal = $this->http->FindSingleNode('descendant::text()[normalize-space()][3]', $root);

            if (preg_match('/Terminal\s*(.*)/i', $terminalDepVal, $math)
                || preg_match('/^([A-Z\d]{1,2})$/', $terminalDepVal, $math)
            ) {
                $seg['DepartureTerminal'] = $math[1];
            }

            // DepDate
            $dateTimeDep = $this->http->FindSingleNode('./descendant::text()[normalize-space(.)][last()]', $root);

            if (preg_match($patterns['dateTime'], $dateTimeDep, $matches)) {
                if ($dateDep = $this->normalizeDate($matches['date'])) {
                    if (!preg_match('/\d{4}$/', $dateDep)) {
                        $dateDep = EmailDateHelper::calculateDateRelative($dateDep, $this, $parser, '%D% %Y%');
                    } else {
                        $dateDep = strtotime($dateDep);
                    }
                    $seg['DepDate'] = strtotime($matches['time'], $dateDep);
                }
            }

            $xpathFragment1 = './following::td[' . $this->starts($this->t('To')) . ' and not(.//td)][1]';
            //			$xpathFragment1 = './ancestor::table[1]/descendant::td[' . $this->starts( $this->t('To') ) . ' and not(.//td)][1]';

            // ArrName
            $seg['ArrName'] = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[normalize-space(.)][2]', $root);

            // ArrivalTerminal
            $terminalArrVal = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[normalize-space()][3]', $root);

            if (preg_match('/Terminal\s*(.*)/i', $terminalArrVal, $matches)
                || preg_match('/^([A-Z\d]{1,2})$/', $terminalArrVal, $matches)
            ) {
                $seg['ArrivalTerminal'] = $matches[1];
            }

            // ArrDate
            $dateTimeArr = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[normalize-space(.)][last()]', $root);

            if (preg_match($patterns['dateTime'], $dateTimeArr, $matches)) {
                if ($dateArr = $this->normalizeDate($matches['date'])) {
                    if (!preg_match('/\d{4}$/', $dateArr)) {
                        $dateArr = EmailDateHelper::calculateDateRelative($dateArr, $this, $parser, '%D% %Y%');
                    } else {
                        $dateArr = strtotime($dateArr);
                    }
                    $seg['ArrDate'] = strtotime($matches['time'], $dateArr);
                }
            }

            //			$flightTexts = $this->http->FindNodes('./ancestor::*[ ./descendant::text()[' . $this->starts($this->t('Flight')) . '] ][1]/descendant::td[./descendant::text()[' . $this->starts($this->t('Flight')) . '] and not(.//td)][1]/descendant::text()[normalize-space(.)]', $root);
            $flightTexts = $this->http->FindNodes('./preceding::text()[' . $this->starts($this->t('Flight')) . '][1]/ancestor::td[1]/descendant::text()[normalize-space(.)]', $root);
            $flightText = implode(' ', $flightTexts);

            // AirlineName
            // FlightNumber
            if (preg_match('/' . $this->t('Flight') . '[ ]*:?[ ]*([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)\b/', $flightText, $matches)) { // Flight: MS748
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }

            // BookingClass
            // Cabin
            if (preg_match('/-[ ]*(\w[\w ]*?\b)[ ]*' . $this->opt($this->t('Latest Boarding Time')) . '/iu', $flightText, $matches)) { // - T    [OR]    - Economy
                if (strlen($matches[1]) < 3) {
                    $seg['BookingClass'] = $matches[1];
                } elseif (strlen($matches[1]) > 2) {
                    $seg['Cabin'] = $matches[1];
                }
            }

            // DepCode
            // ArrCode
            if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
                $depTime = date('H:i', $seg['DepDate']);
                $arrTime = date('H:i', $seg['ArrDate']);

                if (preg_match("#{$depTime}.+?\s+(?:Terminal[ ]+(\w+)\s+)?([A-Z]{3})\s+([A-Z]{3})\s+(?:Terminal[ ]+(\w+)\s+)?{$arrTime}\s+.+?\s+FLIGHT[^\n]+\s+{$seg['AirlineName']}{$seg['FlightNumber']}#s",
                    $this->pdfText, $m)) {
                    if (empty($seg['DepartureTerminal']) && isset($m[1]) && !empty($m[1])) {
                        $seg['DepartureTerminal'] = $m[1];
                    }
                    $seg['DepCode'] = $m[2];
                    $seg['ArrCode'] = $m[3];

                    if (empty($seg['ArrivalTerminal']) && isset($m[4]) && !empty($m[4])) {
                        $seg['DepartureTerminal'] = $m[4];
                    }
                } else {
                    $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }
            }

            // DepartureTerminal
            // ArrivalTerminal
            if (empty($seg['DepartureTerminal']) || empty($seg['ArrivalTerminal'])) {
                if (!empty($this->pdfText) && stripos($this->pdfText, 'Terminal') !== false && preg_match('/[A-Z\d]{2}\s*\d+\s+[A-Z\s\-]+(?:\(Terminal\s+([A-Z\d]{1,3})\))?\s+[A-Z\s\-]+(?:\(Terminal\s+([A-Z\d]{1,3})\))?/', $this->pdfText, $m)) {
                    if (!empty($m[1])) {
                        $seg['DepartureTerminal'] = $m[1];
                    }

                    if (!empty($m[2])) {
                        $seg['ArrivalTerminal'] = $m[2];
                    }
                }
            }

            // Seats
            if (!empty($this->pdfText) && preg_match("/{$seg['FlightNumber']}\s+(\d{1,2}[A-Z]\b)/", $this->pdfText, $m)) {
                $seg['Seats'][] = $m[1];
            }

            $it['TripSegments'][] = $seg;

            if (($url = $this->http->FindSingleNode("ancestor::tr[1]/following-sibling::tr[normalize-space(.)][position()<3]/descendant::a[contains(.,'mobile version of your boarding pass')]/@href", $root))) {
                $bp = [
                    'FlightNumber'       => $seg['FlightNumber'],
                    'DepDate'            => $seg['DepDate'],
                    'RecordLocator'      => $it['RecordLocator'],
                    'Passengers'         => $it['Passengers'],
                    'BoardingPassURL'    => $url,
                    'AttachmentFileName' => null,
                ];

                if ($seg['DepCode'] !== TRIP_CODE_UNKNOWN) {
                    $bp['DepCode'] = $seg['DepCode'];
                }
                $this->bps[] = $bp;
            }
        }

        return [$it];
    }

    private function parseEmailPlain($text, $parser)
    {
        $its = [];
        $text = preg_replace("#^>+ #m", '', $text);
        $segments = $this->split("#\n\s*(" . $this->opt($this->t('Passenger')) . ")#", $text);

        $patterns = [
            'dateTime' => '(?<date>.{4,})\s*-\s*(?<time>\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)', // 31/05/2015 - 11:50
        ];

        $it = [];
        $it['Kind'] = 'T';

        $it['TripSegments'] = [];

        foreach ($segments as $stext) {
            $seg = [];
            $reSegmentEnd = '\s*:?\s*(?<name>[\s\S]+?)(\s+(?<term>.*Terminal.*))?\s*\n' . $patterns['dateTime'];

            if (preg_match("#\n\s*" . $this->opt($this->t("From")) . $reSegmentEnd . "#", $stext, $m)) {
                // DepName
                $seg['DepName'] = $m['name'];
                // DepartureTerminal
                if (!empty($m['term'])) {
                    $seg['DepartureTerminal'] = trim(str_ireplace('Terminal', '', $m['term']));
                }
                // DepDate
                $date = $this->normalizeDate($m['date']);

                if (!preg_match('/\d{4}\s*$/', $date)) {
                    $date = EmailDateHelper::calculateDateRelative($date, $this, $parser, '%D% %Y%');
                } else {
                    $date = strtotime($date);
                }

                if (!empty($date)) {
                    $seg['DepDate'] = strtotime($m['time'], $date);
                }
            }

            if (preg_match("#\n\s*" . $this->opt($this->t("To")) . $reSegmentEnd . "#", $stext, $m)) {
                // ArrName
                $seg['ArrName'] = $m['name'];
                // ArrivalTerminal
                if (!empty($m['term'])) {
                    $seg['ArrivalTerminal'] = trim(str_ireplace('Terminal', '', $m['term']));
                }
                // ArrDate
                $date = $this->normalizeDate($m['date']);

                if (!preg_match('/\d{4}$/', $date)) {
                    $date = EmailDateHelper::calculateDateRelative($date, $this, $parser, '%D% %Y%');
                } else {
                    $date = strtotime($date);
                }

                if (!empty($date)) {
                    $seg['ArrDate'] = strtotime($m['time'], $date);
                }
            }

            // AirlineName
            // FlightNumber
            if (preg_match('#' . $this->t('Flight') . '[ ]*:?\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)\b#', $stext, $m)) { // Flight: TP401 - Economy
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            // BookingClass
            // Cabin
            if (preg_match('#' . $this->t('Flight') . '[ ]*:?\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+\b(?:$|\s*-[ ]*(?<class>[A-Z]{1,2})\s+|\s*-[ ]*(?<cabin>.+))#', $stext, $m)) { // - T    [OR]    - Economy
                if (!empty($m['class'])) {
                    $seg['BookingClass'] = $m['class'];
                }

                if (!empty($m['cabin'])) {
                    $seg['Cabin'] = $m['cabin'];
                }
            }

            // DepCode
            // ArrCode
            if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            // Seats
            if (!empty($this->pdfText) && preg_match("/{$seg['FlightNumber']}\s+(\d{1,2}[A-Z]\b)/", $this->pdfText, $m)) {
                $seg['Seats'][] = $m[1];
            }

            $it['TripSegments'][] = $seg;

            if (empty($seg['AirlineName']) && empty($seg['FlightNumber']) && empty($seg['DepDate'])) {
                return null;
            }

            // RecordLocator
            if (preg_match("#" . $this->opt($this->t('Booking Reference')) . ":?\s*([A-Z\d]{5,7})\s+#", $stext, $m)) {
                $RecordLocator = $m[1];
            } elseif (!preg_match("#" . $this->opt($this->t('Booking Reference')) . "#", $stext, $m)) {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }

            // Passengers
            if (preg_match("#" . $this->opt($this->t('Passenger')) . ":?\s*(.+)\s+#", $stext, $m)) {
                $Passengers = $m[1];
            }

            // TicketNumbers
            if (preg_match("#" . $this->opt($this->t('Passenger')) . ":?\s*(.+)\s+#", $stext, $m)) {
                $Passengers = $m[1];
            }

            if (!empty($this->pdfText) && stripos($this->pdfText, 'Ticket') !== false && preg_match('/Ticket\s+(?:ETKT\s+)?([\d\s\-]+)/iu', $this->pdfText, $m)) {
                $it['TicketNumbers'][] = trim($m[1]);
            }

            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    if (isset($Passengers)) {
                        $its[$key]['Passengers'][] = $Passengers;
                    }

                    if (isset($TicketNumbers)) {
                        $its[$key]['TicketNumbers'][] = $TicketNumbers;
                    }
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            $its[$key]['TripSegments'][$key2]['Seats'] = array_unique(array_filter(array_merge($value['Seats'], $seg['Seats'])));
                            $finded2 = true;
                        }
                    }

                    if ($finded2 == false) {
                        $its[$key]['TripSegments'][] = $seg;
                    }
                    $finded = true;
                }
            }

            unset($it);

            if ($finded == false) {
                $it['Kind'] = 'T';

                if (isset($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (isset($Passengers)) {
                    $it['Passengers'][] = $Passengers;
                }

                if (isset($TicketNumbers)) {
                    $it['TicketNumbers'][] = $TicketNumbers;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        return $its;
    }

    private function normalizeDate($string = '')
    {
        if (preg_match('/^\s*(\d{1,2})[ ]*\/[ ]*(\d{1,2})[ ]*\/[ ]*(\d{4})\s*$/', $string, $matches)) { // 30/05/2015
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^\s*(\d{1,2})[ ]*([^\-,.\d\s\/]{3,})[ ]*(\d{4})\s*$/', $string, $matches)) { // 26 Jul 2015
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^\s*(\d{1,2})[ ]*([^\-,.\d\s\/]{3,})\s*$/', $string, $matches)) { // 18NOV
            $day = $matches[1];
            $month = $matches[2];
            $year = '';
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    //	private function eq($field) {
    //		$field = (array)$field;
    //		if (count($field) === 0) return 'false';
    //		return '(' . implode(' or ', array_map(function($s){ return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    //	}

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    //	private function contains($field) {
    //		$field = (array)$field;
    //		if (count($field) === 0) return 'false';
    //		return '(' . implode(' or ', array_map(function($s){ return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    //	}

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
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
}
