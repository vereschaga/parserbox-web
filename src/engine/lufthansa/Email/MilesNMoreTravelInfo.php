<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// This parser for PDF. For HTML see parser lufthansa/It5889106

class MilesNMoreTravelInfo extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-10143303.eml, lufthansa/it-10160093.eml, lufthansa/it-10561006.eml, lufthansa/it-10670401.eml, lufthansa/it-10717469.eml, lufthansa/it-30608119.eml"; // +2 bcdtravel(html+pdf)[de,fr]

    public $reSubject = [
        'de' => ['Ihre Flugdetails & Reiseinformationen', 'Buchungsdetails, Abflug am', 'Buchungsdetails |'],
        'it' => ['Dettagli della prenotazione, Partenza'],
        'fr' => ['Détail de votre réservation, Départ'],
        'en' => ['Your ticket details & travel information', 'Booking details, Departure'],
        'es' => ['Detalles de la reserva, Salida'],
        'nl' => ['Reisinformatie, '],
    ];

    public $lang = '';

    public $reBody = [
        'de' => ['Ihre Reiseroute', 'Ihr Reiseverlauf', 'Ihr Buchungscode'],
        'it' => ['Il suo itinerario'],
        'fr' => ['Déroulement de votre voyage'],
        'en' => ['Your itinerary'],
        'es' => ['Su itinerario'],
        'ru' => ['Ваш маршрут'],
        'nl' => ['Uw reisroute'],
    ];
    public $reBodyPdf = [
        'de' => ['Ihre Reiseroute', 'Ihr Reiseverlauf', 'Ihr Buchungscode'],
        'it' => ['Il suo itinerario', 'Informazioni sui passeggeri'],
        'fr' => ['Déroulement de votre voyage'],
        'en' => ['Your itinerary'],
        'es' => ['Su itinerario'],
        'nl' => ['Uw reisroute'],
    ];

    public $pdfNamePattern = '.*pdf';

    public static $dict = [
        'es' => [
            'Your booking code'             => ['reserva de Lufthansa:', 'Código de reserva:'],
            'Booking code not displayed'    => 'no le mostramos su código de reserva',
            'Go to your'                    => ['Mostrar/editar reserva', 'En su "Lista de reservas"'], //???
            'Passenger information'         => 'Información del pasajero',
            //            'child' => '',
            'Ticket no'                     => 'Nº de billete',
            'operated by'                   => 'operado por',
            'h'                             => 'h',
            'Status'                        => 'Estado',
            // 'statusVariants'                => [''],
            'Class of service/Fare'         => ['Clase de reserva', 'Clase/Tarifa'],
            'Your itinerary'                => 'Su itinerario',
            //            'itineraryEnd'             => '',
            // 'Here' => '',
            'Booking code not displayedPDF' => '\n\s*[^\n]*no le mostramos su código(\s*.*)?\n\s*de reserva',
            'Seats'                         => ['Asiento', 'Asientos'],
            'Class'                         => 'Buchungsklasse',
            //            'Total Price for all Passengers' => '',
        ],
        'ru' => [
            'Your booking code' => 'код бронирования',
            //            'Booking code not displayed' => '',
            //            'Go to your' => '',//???
            'Passenger information' => 'Данные пассажира',
            //            'child' => '',
            'Ticket no'             => 'Номер билета',
            'operated by'           => 'выполняется',
            'h'                     => 'ч.',
            'Status'                => 'Статус',
            // 'statusVariants'                => [''],
            'Class of service/Fare' => 'Класс',
            'Your itinerary'        => 'Ваш маршрут',
            //            'itineraryEnd'             => '',
            // 'Here' => '',
            //            'Booking code not displayedPDF' => '\n\s*[^\n]*no le mostramos su código(\s*.*)?\n\s*de reserva',
            'Seats' => 'Места',
            //            'Class' => '',
            //            'Total Price for all Passengers' => '',
        ],
        'de' => [ // it-10561006.eml, it-2213909.eml, it-60193382.eml
            'Your booking code'          => ['Ihr Buchungscode', 'Lufthansa Buchungscode:'],
            'Booking code not displayed' => 'Buchungscode nicht angezeigt',
            'Go to your'                 => ['In Ihrer persönlichen', 'Buchung anzeigen / bearbeiten'],
            'Passenger information'      => 'Passagierinformationen',
            'child'                      => 'Kind',
            'Ticket no'                  => ['Ticket Nr', 'Ticketnummer', 'Ticketnummern'],
            'operated by'                => 'durchgeführt von',
            'h'                          => 'Uhr',
            //            'Status' => '',
            'statusVariants'                => ['bestätigt'],
            'Class of service/Fare'         => 'Beförderungsklasse/Tarif',
            'Your itinerary'                => ['Ihre Reiseroute', 'Ihr Reiseverlauf'],
            //            'itineraryEnd'             => '',
            'Here'                           => 'Hier',
            'Booking code not displayedPDF'  => '\s+Buchungscode\s+nicht(\s+.+)?\n\s*angezeigt',
            'Seats'                          => ['Sitzplätze', 'Sitzplatz'],
            'Miles & More'                   => ['Miles & More', 'FTL', 'FTL-Nummer', 'Senator-Nummer:'],
            'Class'                          => ['Klasse', 'Klasse/Tarif', 'Beförderungsklasse/Tarif'],
            'Total Price for all Passengers' => 'Gesamtpreis für alle Reisenden',
            'Modification of your booking'   => ['Änderung Ihrer Buchung'],
        ],
        'it' => [ // it-10717469.eml
            'Your booking code' => ['Codice di prenotazione:', 'Codice di prenotazione Lufthansa:'],
            //            'Booking code not displayed' => '',
            'Go to your'                    => ['Nel  Riepilogo prenotazioni  personale', 'Visualizza / Modifica prenotazione'],
            'Passenger information'         => 'Informazioni sui passeggeri',
            //            'child' => '',
            'Ticket no'                     => 'Numero biglietto',
            'operated by'                   => 'operato da',
            'h'                             => 'Ore',
            'Status'                        => 'Situazione volo',
            'statusVariants'                => ['confermato'],
            'Class of service/Fare'         => 'Classe di prenotazione',
            'Your itinerary'                => 'Il suo itinerario',
            //            'itineraryEnd'             => '',
            'Here'                          => 'qui',
            'Booking code not displayedPDF' => 'codice\s+di\s+prenotazione\s+non(\s+.+)?\n\s*viene\s+visualizzato',
            'Seats'                         => 'Posto',
            'Class'                         => ['Classe di prenotazione', 'Classe'],
            'Important Notice'              => ["Avvertenza importante", 'Avvertenze importanti'],
            //            'Total Price for all Passengers' => '',
        ],
        'fr' => [
            'Your booking code' => ['Code de réservation:', 'Code de réservation Lufthansa:'],
            //            'Booking code not displayed' => '',
            'Go to your'            => ['Dans votre', 'Afficher / éditer la réservation'],
            'Passenger information' => 'Informations passager',
            //            'child' => '',
            'Ticket no'             => ['N° de billet', 'N  de billet', 'Numéro de billet'],
            'operated by'           => ['opéré par:', 'opéré par'],
            //            'h' => '',
            'Status'                => 'Statut',
            // 'statusVariants'                => [''],
            'Class of service/Fare' => ['Classe de réservation', 'Classe:'],
            'Your itinerary'        => 'Déroulement de votre voyage',
            //            'itineraryEnd'             => '',
            // 'Here' => '',
            //            'Booking code not displayedPDF' => '',
            'Seats'                          => 'Siège',
            'Class'                          => ['Classe de réservation', 'Classe/tarif'],
            'Total Price for all Passengers' => 'Prix total pour tous les passagers',
        ],
        'en' => [ // it-10143303.eml, it-10160093.eml, it-10670401.eml, it-30608119.eml
            'Your booking code'              => ['Your booking code', 'Lufthansa booking code:'],
            'Go to your'                     => ['Go to your', 'Display/edit booking'],
            'Ticket no'                      => ['Ticket no', 'Ticket number', 'Ticket numbers'],
            'h'                              => ['h', 'hr'],
            'statusVariants'                 => ['confirmed', 'cancelled'],
            'statusCancelled'                => ['cancelled'],
            'itineraryEnd'                   => ['Entry conditions US', 'Baggage Information', 'Flight information'],
            'Class'                          => ['Class', 'Class/Fare'],
            'Booking code not displayedPDF'  => 'booking\s+code\s+is\s+not\s+displayed',
            //            'Total Price for all Passengers' => '',
        ],
        'nl' => [
            'Your booking code' => 'Boekingscode:',
            //'Booking code not displayed' => '',
            'Go to your'                    => 'In uw persoonlijke', //???
            'Passenger information'         => 'Passenger Information',
            //            'child' => '',
            'Ticket no'                     => 'Ticket no',
            'operated by'                   => 'uitgevoerd door:',
            'h'                             => 'Tijd',
            'Status'                        => 'Status',
            // 'statusVariants'                => [''],
            'Class of service/Fare'         => 'Reisklasse/Tarief',
            'Your itinerary'                => 'Uw reisroute',
            //            'itineraryEnd'             => '',
            // 'Here' => '',
            'Booking code not displayedPDF' => '\n\s*[^\n]*no le mostramos su código(\s*.*)?\n\s*de reserva',
            //'Seats' => '',
            //            'Class' => '',
            'Miles & More' => 'Frequent Travellers:',
            //            'Total Price for all Passengers' => '',
        ],
    ];

    private $namePrefixes = ['MRDR', 'MRS', 'MR', 'DR', 'MRSDR'];

    // only uppercase (hard-code)
    private $trainOperators = [
        'DEUTSCHE BAHN AG',
        'AMTRAK TRAIN',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $type = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    $NBSP = chr(194) . chr(160);
                    $htmlPdf = str_replace([$NBSP, '–'], [' ', '-'], html_entity_decode($htmlPdf));

                    if ($this->assignLangPdf($htmlPdf) === false) {
                        continue;
                    }
                    $this->parseEmailPdf(text($htmlPdf), $email);
                    $type = 'pdf';
                } else {
                    continue;
                }
            }
        }

        if (empty($type)) {
            $body = $this->http->Response['body'];
            $html = str_ireplace(['&zwnj;', '&8204;', '‌'], '', html_entity_decode($body)); // Zero-width non-joiner
            $html = str_ireplace(['&zwnj;', '&8203;', '​'], '', $html); // Zero-width
            $this->http->SetEmailBody($html);
            $this->assignLang();
            $this->parseEmail($email);
            $type = 'html';
        }

        if ($this->http->XPath->query("//img[contains(@src, 'hotel')]/following::text()[normalize-space()='Address']/ancestor::tr[1][contains(normalize-space(), 'Arrival')]")->length > 0) {
            $this->parseHotel($email);
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($type) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (($this->http->XPath->query('//a[contains(@href,".lufthansa.com/") or contains(@href,".miles-and-more.com/") or contains(@href,".LH.com/") or contains(@href,"www.lufthansa.com") or contains(@href,"www.miles-and-more.com") or contains(@href,"www.LH.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Lufthansa Conditions") or contains(.,"@booking.lufthansa.com") or contains(.,"@booking-lufthansa.com")]')->length > 0)
            && $this->assignLang()
        ) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'Deutsche Lufthansa') === false
                && strpos($textPdf, 'Ihr Lufthansa Team') === false // de
                && stripos($textPdf, 'Miles & More Team') === false
                && stripos($textPdf, 'Team Miles & More') === false
                && stripos($textPdf, 'Miles & More GmbH') === false
            ) {
                continue;
            }
            $this->logger->warning('YES');

            if ($this->assignLangPdf($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'], $headers['subject'])) {
            return false;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        return strpos($from, 'Miles & More') !== false
            || stripos($from, '@booking-lufthansa.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2;
        $cnt = $types * count(self::$dict);

        return $cnt;
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

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    private function splitter($regular, $text): array
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parsePdfSegments1(Email $email, Flight $f, array $segments): void
    {
        foreach ($segments as $root) {
            $operator = $this->re("/{$this->opt($this->t('operated by'))}[\s:]+(.+)/iu", $root);

            if (!empty($operator) && in_array(strtoupper($operator), $this->trainOperators)) {
                // TRAIN
                $segmentType = 'train';

                if (!isset($t)) {
                    $t = $email->add()->train();

                    if ($f->getNoConfirmationNumber()) {
                        $t->general()->noConfirmation();
                    } else {
                        foreach ($f->getConfirmationNumbers() as $confNumber) {
                            $t->general()->confirmation($confNumber[0], $confNumber[1]);
                        }
                    }

                    foreach ($f->getTravellers() as $traveller) {
                        $t->addTraveller($traveller[0], $traveller[1]);
                    }

                    foreach ($f->getTicketNumbers() as $ticketNumber) {
                        $t->addTicketNumber($ticketNumber[0], $ticketNumber[1]);
                    }

                    foreach ($f->getAccountNumbers() as $ffNumber) {
                        $t->addAccountNumber($ffNumber[0], $ffNumber[1]);
                    }
                }
                $s = $t->addSegment();
            } else {
                // FLIGHT
                $segmentType = 'flight';
                $s = $f->addSegment();

                if (!empty($operator)) {
                    $s->airline()->operator($operator);
                }
            }

            if (preg_match("/\s*(?<nameDep>.+?)\s*-\s*(?<nameArr>.+)\s+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/", $root, $m)
                || preg_match("/\s*(?<nameDep>\w+)\s{2,}(?<nameArr>.+)\s+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/", $root, $m)
            ) {
                $s->departure()->name($m['nameDep']);
                $s->arrival()->name($m['nameArr']);

                if ($segmentType === 'train') {
                    $s->extra()->number($m['airline'] . $m['number']);
                } else {
                    $s->airline()
                        ->name($m['airline'])
                        ->number($m['number']);
                }
            }

            $date = strtotime($this->normalizeDate($this->re("/^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+\s+(?:.+\n){0,5}(.+\b\d{4}\b.*)/m", $root)));

            $pattern1 = "/(?<depTime>\d+:\d+(?:[ ]*[AaPp][Mm])?)\s*{$this->opt($this->t('h'))}\s*(?<DepName>.+)\s+\((?<DepCode>[A-Z]{3})\).+?(?:TERMINAL\s+(?<depTerminal>[^\n]+))?\s+(?<arrTime>\d+:\d+(?:[ ]*[AaPp][Mm])?)\s*{$this->opt($this->t('h'))}\s*(?<ArrName>.+)\s+\((?<ArrCode>[A-Z]{3})\).+?(?:TERMINAL\s+(?<arrTerminal>.+))?\s+{$this->opt($this->t('Status'))}[\s:]+(?<status>.+)\s*\|\s*{$this->opt($this->t('Class of service/Fare'))}[\s:]+(?<Cabin>[^\n]+)/s";
            $pattern2 = "/(?<depTime>\d+:\d+(?:[ ]*[AaPp][Mm])?)\s*{$this->opt($this->t('h'))}\s*(?<DepName>.+?)\n\s+(?<arrTime>\d+:\d+(?:[ ]*[AaPp][Mm])?)\s*{$this->opt($this->t('h'))}\s*(?<ArrName>.+?)\s*\n\s*{$this->opt($this->t('Class'))}[\s:]+(?<Cabin>[^\n]+)\s*\|\s*{$this->opt($this->t('Status'))}[\s:]+(?<status>[^\n]+)/s";
            $pattern3 = "/(?<depTime>\d+:\d+(?:[ ]*[AaPp][Mm])?)\s*{$this->opt($this->t('h'))}\s*(?<DepName>[\s\S]+)\s+(?<arrTime>\d+:\d+(?:[ ]*[AaPp][Mm])?)\s*{$this->opt($this->t('h'))}\s*(?<ArrName>[\s\S]+)\s+(?<extra>{$this->opt($this->t('Status'))}[\s:]+[\s\S]+)/i";

            if (preg_match($pattern1, $root, $m)) {
//                $this->logger->debug('$pattern1 = '.print_r( $pattern1,true));
                $s->departure()
                    ->date(strtotime($m['depTime'], $date))
                    ->name($this->nice($m['DepName']))
                    ->code($m['DepCode']);
                $s->arrival()
                    ->date(strtotime($m['arrTime'], $date))
                    ->name($this->nice($m['ArrName']))
                    ->code($m['ArrCode']);

                if (!empty($m['nextday']) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($m['nextday'] . " days", $s->getArrDate()));
                }

                if (isset($m['depTerminal']) && !empty($m['depTerminal'])) {
                    $s->departure()->terminal(trim($this->re("#^(.+)#", $m['depTerminal'])));
                }

                if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                    $s->arrival()->terminal(trim($this->re("#^(.+)#", $m['arrTerminal'])), true, true);
                }

                $s->setStatus(trim($m['status']));

                if (preg_match("/^\s*" . $this->opt($this->t('statusCancelled')) . "\s*$/u", $m['status'])) {
                    $s->extra()->cancelled();
                }

                if (preg_match("/^(.+)\s+\(\s*([A-Z]{1,2})\s*\)/", $m['Cabin'], $v)) {
                    // Economy Class (C)
                    $s->extra()
                        ->cabin($v[1])
                        ->bookingCode($v[2]);
                } elseif (preg_match("/^\(\s*([A-Z]{1,2})\s*\)/", $m['Cabin'], $v)) {
                    // (C)
                    $s->extra()->bookingCode($v[1]);
                } else {
                    // Economy Class
                    $s->extra()->cabin($m['Cabin']);
                }

                if (preg_match('/' . $this->opt($this->t('Seats')) . '\s*:\s*(.+)/', $root, $m)) {
                    $m[1] = preg_replace('/\s+/', '', $m[1]);
                    $s->extra()->seats(explode('/', $m[1]));
                }
            } elseif (preg_match($pattern2, $root, $m)) {
//                $this->logger->debug('$pattern2 = '.print_r( $pattern2,true));
                $s->departure()
                    ->date(strtotime($m['depTime'], $date))
                    ->name($this->nice($m['DepName']))
                    ->noCode();
                $s->arrival()
                    ->date(strtotime($m['arrTime'], $date))
                    ->name($this->nice($m['ArrName']))
                    ->noCode();

                $s->setStatus(trim($m['status']));

                if (preg_match("/^\s*" . $this->opt($this->t('statusCancelled')) . "\s*$/u", $m['status'])) {
                    $s->extra()->cancelled();
                }

                if (preg_match("/^(.+)\s+\(\s*([A-Z]{1,2})\s*\)/", $m['Cabin'], $v)) {
                    // Economy Class (C)
                    $s->extra()
                        ->cabin($v[1])
                        ->bookingCode($v[2]);
                } elseif (preg_match("/^\(\s*([A-Z]{1,2})\s*\)/", $m['Cabin'], $v)) {
                    // (C)
                    $s->extra()->bookingCode($v[1]);
                } else {
                    // Economy Class
                    $s->extra()->cabin($m['Cabin']);
                }

                if (preg_match('/' . $this->opt($this->t('Seats')) . '\s*:\s*(.+)/', $root, $m)) {
                    $m[1] = preg_replace('/\s+/', '', $m[1]);
                    $s->extra()->seats(explode('/', $m[1]));
                }
            } elseif (preg_match($pattern3, $root, $m)) {
//                $this->logger->debug('$pattern3 = '.print_r( $pattern3,true));

                if (preg_match("/(?:^|\n)\s*([-+]\d{1,3})\s*(?:\n|$)/", $m['ArrName'], $m2)) {
                    // Johannesburg O R Tambo Intl. Airport (JNB) +1 Terminal A
                    $m['nextday'] = $m2[1];
                    $m['ArrName'] = preg_replace("/(?:^|\n)\s*([-+]\d{1,3})\s*(?:\n|$)/", "\n", $m['ArrName']);
                }

                $m['DepName'] = trim($this->nice($m['DepName']));
                $m['ArrName'] = trim($this->nice($m['ArrName']));

                if (preg_match("/^(.{3,}?)\s*Terminal\s+(.+)$/i", $m['DepName'], $m2)) {
                    $m['DepName'] = $m2[1];
                    $s->departure()->terminal($m2[2]);
                }

                if (preg_match("/^(.{3,}?)\s*Terminal\s+(.+)$/i", $m['ArrName'], $m2)) {
                    $m['ArrName'] = $m2[1];
                    $s->arrival()->terminal($m2[2]);
                }

                if (preg_match("/^(.{3,}?)\s*\(\s*([A-Z]{3})\s*\)$/s", $m['DepName'], $m2)) {
                    $s->departure()
                        ->name($m2[1])
                        ->code($m2[2])
                    ;
                } else {
                    $s->departure()->name($m['DepName']);
                }

                if (preg_match("/^(.{3,}?)\s*\(\s*([A-Z]{3})\s*\)$/s", $m['ArrName'], $m2)) {
                    $s->arrival()
                        ->name($m2[1])
                        ->code($m2[2])
                    ;
                } else {
                    $s->arrival()->name($m['ArrName']);
                }

                $s->departure()->date(strtotime($m['depTime'], $date));
                $s->arrival()->date(strtotime($m['arrTime'], $date));

                if (!empty($m['nextday']) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($m['nextday'] . " days", $s->getArrDate()));
                }

                if (preg_match("/{$this->opt($this->t('Status'))}[\s:]+(?<status>.+?)\s*{$this->opt($this->t('Class of service/Fare'))}[\s:]+(?<Cabin>.+)/i", $m['extra'], $m2)) {
                    $s->setStatus($m2['status']);

                    if (preg_match("/^\s*" . $this->opt($this->t('statusCancelled')) . "\s*$/u", $m2['status'])) {
                        $s->extra()->cancelled();
                    }

                    if (preg_match("/^(.+)\s+\(\s*([A-Z]{1,2})\s*\)/", $m2['Cabin'], $v)) {
                        // Economy Class (C)
                        $s->extra()
                            ->cabin($v[1])
                            ->bookingCode($v[2]);
                    } elseif (preg_match("/^\(\s*([A-Z]{1,2})\s*\)/", $m2['Cabin'], $v)) {
                        // (C)
                        $s->extra()->bookingCode($v[1]);
                    } else {
                        // Economy Class
                        $s->extra()->cabin($m2['Cabin']);
                    }
                } elseif (preg_match("/{$this->opt($this->t('Status'))}[\s:]+({$this->opt($this->t('statusVariant'))})(?:\n|$)/i", $m['extra'], $m2)) {
                    // for TRAIN
                    $s->setStatus($m2[1]);

                    if (preg_match("/^\s*" . $this->opt($this->t('statusCancelled')) . "\s*$/u", $m2[1])) {
                        $s->extra()->cancelled();
                    }
                }

                if (preg_match('/' . $this->opt($this->t('Seats')) . '\s*:\s*(.+)/', $root, $m)) {
                    $m[1] = preg_replace('/\s+/', '', $m[1]);

                    if (strpos($m[1], '/') !== false) {
                        // 25H/25K/26H/26K/27K
                        $s->extra()->seats(preg_split('/\s*[\/]\s*/', $m[1]));
                    } elseif (strpos($m[1], ',') !== false) {
                        // 25H,25K,26H,26K,27K
                        $s->extra()->seats(preg_split('/\s*[,]+\s*/', $m[1]));
                    } elseif (preg_match("/^\d+[A-z]$/", $m[1])) {
                        // 25H
                        $s->extra()->seat($m[1]);
                    }
                }
            }
        }
    }

    private function parsePdfSegments2(Email $email, Flight $f, array $segments): void
    {
        foreach ($segments as $key => $root) {
            $pattern = "/"
                . "^[ ]*(?<depTime>\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?)[ ]*(?:{$this->opt($this->t('h'))})?$" // 17:45 h
                . "\s+^[ ]*(?<depName>[^\n]{3,}?)[ ]*(?:\([ ]*(?<depCode>[A-Z]{3})[ ]*\))?$" // FRANKFURT DE FRANKFURT INTL (FRA)
                . "\s+^[ ]*(?<depOther>.+?)$" // Flight + depTerminal
                . "\s+^[ ]*(?<arrTime>\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?)[ ]*(?:{$this->opt($this->t('h'))})?(?<overnight>[ ]*[+\-][ ]*\d{1,3})?" // 07:35 h +1
                . "\s+[ ]*(?<arrName>[^\n]{3,}?)[ ]*(?:\([ ]*(?<arrCode>[A-Z]{3})[ ]*\))?$" // PHILADELPHIA PA PHILADELPHIA INTL
                . "\s+^[ ]*(?<arrOther>.+)" // arrTerminal
                . "/ms";

            if (!preg_match($pattern, $root, $matches)) {
                $this->logger->debug("Segment-$key: wrong format!");
                $f->addSegment();

                continue;
            }

            $operator = preg_match("/^[ ]*{$this->opt($this->t('operated by'))}[ ]*[:]+[ ]*(?<operator>.{2,})$/mu", $root, $m) ? $m[1] : null;

            if (!empty($operator) && in_array(strtoupper($operator), $this->trainOperators)) {
                // TRAIN

                if (!isset($t)) {
                    $t = $email->add()->train();

                    if ($f->getNoConfirmationNumber()) {
                        $t->general()->noConfirmation();
                    } else {
                        foreach ($f->getConfirmationNumbers() as $confNumber) {
                            $t->general()->confirmation($confNumber[0], $confNumber[1]);
                        }
                    }

                    foreach ($f->getTravellers() as $traveller) {
                        $t->addTraveller($traveller[0], $traveller[1]);
                    }

                    foreach ($f->getTicketNumbers() as $ticketNumber) {
                        $t->addTicketNumber($ticketNumber[0], $ticketNumber[1]);
                    }

                    foreach ($f->getAccountNumbers() as $ffNumber) {
                        $t->addAccountNumber($ffNumber[0], $ffNumber[1]);
                    }
                }
                $s = $t->addSegment();

                if (preg_match('/^[ ]*((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+)$/m', $matches['depOther'], $m)) {
                    // UA2853
                    $s->extra()->number($m[1]);
                }
            } else {
                // FLIGHT

                $s = $f->addSegment();

                $s->airline()->operator($operator, false, true);

                if (preg_match('/^[ ]*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<flightNumber>\d+)$/m', $matches['depOther'], $m)) {
                    // UA9199
                    $s->airline()
                        ->name($m['airline'])
                        ->number($m['flightNumber'])
                    ;
                }

                if (empty($matches['depCode'])) {
                    $s->departure()->noCode();
                }

                if (empty($matches['arrCode'])) {
                    $s->arrival()->noCode();
                }

                // TERMINAL A, RESERVATION CONFIRMED
                // TERMINAL 1 - TRAIN STATION
                $patternTerminal = "/^[ ]*{$this->opt($this->t('TERMINAL'))}[ ]+([A-z\d]+)(?:[ ]*[-,].*|$)/im";

                if (preg_match($patternTerminal, $matches['depOther'], $m)) {
                    $s->departure()->terminal($m[1]);
                }

                if (preg_match($patternTerminal, $matches['arrOther'], $m)) {
                    $s->arrival()->terminal($m[1], true, true);
                }
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Status'))}[ ]*[:]+[ ]*([\w ]{2,})$/mu", $root, $m)) {
                $s->setStatus($m[1]);

                if (preg_match("/^\s*" . $this->opt($this->t('statusCancelled')) . "\s*$/u", $m[1])) {
                    $s->extra()->cancelled();
                }
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Seats'))}[ ]*[:]+[ ]*(\d[*\dA-Z,\/ ]*[*A-Z])$/m", $root, $m)) {
                $s->extra()->seats(preg_split('/[ ]*[,\/]+[ ]*/', str_replace('*', '', $m[1])));
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Class'))}[ ]*[:]+[ ]*(?<cabin>\w.+?)(?:[ ]*\/\n.+)?[ ]*\([ ]*(?<bookingCode>[A-Z]{1,2})[ ]*\)$/mu", $root, $m)) {
                // Class: ECONOMY (L)    |    Klasse/Tarif: Business Class/Business Saver (P)   |    Classe: Classe economica (T)
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode'])
                ;
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('Class'))}[ ]*[:]+[ ]*\([ ]*(?<bookingCode>[A-Z]{1,2})[ ]*\)/mu", $root, $m)) {
                // Class: (L)
                $s->extra()->bookingCode($m['bookingCode']);
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('Class'))}[ ]*[:]+[ ]*(?<cabin>\w.+?)[ \/]*$/mu", $root, $m)) {
                // Class: Economy
                $s->extra()->cabin($m['cabin']);
            }

            $date = $this->normalizeDate(preg_match("/^(.{6,}?)[ ]*:/", $root, $m) ? $m[1] : '');

            if ($date) {
                $s->departure()->date(strtotime($date . ' ' . $matches['depTime']));
                $s->arrival()->date(strtotime($date . ' ' . $matches['arrTime']));
            }

            if (!empty($s->getArrDate()) && !empty($matches['overnight'])) {
                $s->arrival()->date(strtotime(preg_replace('/\s+/', ' ', trim($matches['overnight'])) . " days", $s->getArrDate()));
            }

            $s->departure()->name($matches['depName']);
            $s->arrival()->name($matches['arrName']);

            if (!empty($matches['depCode'])) {
                $s->departure()->code($matches['depCode']);
            }

            if (!empty($matches['arrCode'])) {
                $s->arrival()->code($matches['arrCode']);
            }
        }
    }

    private function parseEmailPdf(string $textPDF, Email $email): void
    {
        $f = $email->add()->flight();

        $confirmationTitle = $bookingCodeText = null;

        if (preg_match("/(?<title>{$this->opt($this->t('Your booking code'))})\s*(?<text>[\s\S]+?)\s*{$this->opt($this->t('Go to your'))}/", $textPDF, $m)
            || preg_match("/^\s*(?<text>[\s\S]+?)\s*{$this->opt($this->t('Go to your'))}/", $textPDF, $m)
        ) {
            if (!empty($m['title'])) {
                $confirmationTitle = rtrim($m['title'], ': ');
            }
            $bookingCodeText = preg_replace("/^[ ]*(?:{$this->opt($this->t('Miles & More'))}.*|{$this->opt($this->t('Here'))}|{$this->opt($this->t('Phone'))}.*:.*|{$this->opt($this->t('URL'))}.*:.*)$/m", '', $m['text']);
        }

        if (preg_match("/^[ ]*([A-Z\d]{5,})$/m", $bookingCodeText, $m)) {
            $f->general()->confirmation($m[1], $confirmationTitle);
        } elseif (preg_match("/{$this->t('Booking code not displayedPDF')}/u", $bookingCodeText)) {
            $f->general()->noConfirmation();
        } elseif (preg_match("/{$this->t('Booking code not displayedPDF')}/u", substr($textPDF, 0, stripos($textPDF, $this->t('Passenger information'))))) {
            $f->general()->noConfirmation();
        } elseif (preg_match("/{$this->opt($this->t('Modification of your booking'))}/u", $textPDF)) {
            $f->general()->noConfirmation();
        } elseif (preg_match("/{$this->opt($this->t('Changes to your booking'))}/u", $textPDF)) {
            $f->general()->noConfirmation();
        }

        $passengersText = '';
        $passengersStart = stripos($textPDF, $this->t('Passenger information'));
        $passengersEnd = $this->strposAll($textPDF, $this->t('Your itinerary'), 'min');

        if ($passengersStart !== false && $passengersEnd !== false) {
            $passengersText = substr($textPDF, $passengersStart, $passengersEnd - $passengersStart);
        }

        // MICKENS / VALOIS MRS
        // TEPE/MAXIMILIAN (child, Date of birth: 14Aug1
        if (preg_match_all('/^\s*(\w[^:\n]+\/[^:\n]+)(?:\s*\([\w :,\s]+\))?\s*$/mu', $passengersText, $passengerMatches)) {
            $passengerMatches[1] = preg_replace("/^(.{2,}?)\s+(?:{$this->opt($this->namePrefixes)}|\({$this->opt($this->t('child'))}.*?\))$/", '$1', array_unique($passengerMatches[1]));
            $passengerMatches[1] = preg_replace("/^\s*(.+?)\s*\\/\s*(.+?)\s*$/", '$2 $1', $passengerMatches[1]);
            $f->general()->travellers($passengerMatches[1], true);
        }

        if (preg_match_all("/{$this->opt($this->t('Miles & More'))}.*?[\s\.:]+([A-Z\d]{5,}(?:\s\d+)?)/u", $passengersText, $accountMatches)
            || preg_match_all("/(?:^[ ]*|[ ]{2})(?:Frequent Travell?er|FTL)[: ]+(X{3,}\s{0,1}\d{4})\s+/mu", $passengersText, $accountMatches)
        ) {
            // Miles & More Teilnehmer: XXXXXXXXXXXXX6142    |    FTL XXXXXXXXXXX 3823
            $f->program()->accounts(array_map(function ($item) {
                return str_replace(' ', '', $item);
            }, array_unique($accountMatches[1])), true);
        }

        // Ticket number: 220 2457990098-99    |    220*******889
        preg_match_all("/{$this->opt($this->t('Ticket no'))}[.\s:]+(\d{3}(?: | ?- ?)?[*\d]{5,}(?: | ?- ?)?\d{1,3}(?:[ ,]+\d{3}[ \-]*\d{10}[\-\d ]*)*)(?:[ ]{2}|\n|$)/", $passengersText, $ticketMatches);

        foreach ($ticketMatches[1] as $ticketNumber) {
            $ticketNumber = preg_replace('/\s+/', '', $ticketNumber);
            $ticketNumbers = array_filter(explode(',', $ticketNumber));
            $tnMasked = strpos($ticketNumber, '*') !== false ? true : false;

            foreach ($ticketNumbers as $tn) {
                $f->issued()->ticket($tn, $tnMasked);
            }
        }

        $itineraryStart = $this->strposAll($textPDF, $this->t('Your itinerary'), 'min');

        if (empty($itineraryStart)) {
            $itineraryStart = 0;
        }
        $itineraryEnd = $this->strposAll($textPDF, $this->t('itineraryEnd'), 'min');
        $itineraryText = $textPDF;

        if (!empty($itineraryStart)) {
            $itineraryText = substr($itineraryText, $itineraryStart);
        }

        if (!empty($itineraryEnd)) {
            $itineraryText = substr($itineraryText, 0, $itineraryEnd - $itineraryStart);
        }

        if (count($segments = $this->splitter('/^[ ]*(.{6,}:[^\n-]{3,}?[ ]+-[ ]+[^\n-]{3,})$/m', $itineraryText))) {
            // it-30608119.eml
            $this->logger->debug('PDF segments: type 2');
            $this->parsePdfSegments2($email, $f, $segments);
        } elseif (count($segments = $this->splitter('/^(.{3,}?-.{3,}\s+(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d{1,5})\s*$/m', $itineraryText))
            || count($segments = $this->splitter('/^(.{3,}?.{3,}\s+(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d{1,5})\s*$/m', $itineraryText))
        ) {
            $this->logger->debug('PDF segments: type 1');
            $this->parsePdfSegments1($email, $f, $segments);
        }

        $totalPrice = $this->re("/\n *" . $this->opt($this->t("Total Price for all Passengers")) . "\s+(.+)/", $textPDF);
        // USD 710.42
        // 204200 HUF
        if (preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $m)
            || preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency']);
        }
    }

    private function parseEmail(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        $f = $email->add()->flight();

        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking code'))}]/following::text()[string-length(normalize-space(.))>2][1]",
            null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (empty($node) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $this->t('Booking code not displayed') . '")]')->length > 0) {
            $f->general()->noConfirmation();
        } else {
            $f->general()->confirmation($node);
        }

        $passengers = [];
        $ticketNumbers = [];
        $accountNumbers = [];
        $passengerNodes = $this->http->XPath->query('//text()[' . $this->starts($this->t('Ticket no')) . ']/ancestor::td[1]');

        foreach ($passengerNodes as $passengerNode) {
            $passengerName = $this->http->FindSingleNode('./descendant::text()[string-length(normalize-space(.))>2 and not(contains(.,":"))][1]',
                $passengerNode);

            if ($passengerName) {
                $passengers[] = $passengerName;
            }
            $ticketNumber = preg_replace("#\D#", '',
                $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t('Ticket no')) . ']',
                    $passengerNode, true, "/{$this->opt($this->t('Ticket no'))}[\s.:]+(\d.+\d)/"));

            if ($ticketNumber) {
                $ticketNumbers[] = $ticketNumber;
            }
            $accountNumber = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t('Miles & More')) . ']',
                $passengerNode, true, "/{$this->opt($this->t('Miles & More'))}.*?[\s.:]+([A-Z\d]{5,})/");

            if ($accountNumber) {
                $accountNumbers[] = $accountNumber;
            }
        }

        if (!empty($passengers[0])) {
            $f->general()->travellers(preg_replace("/^(.{2,}?)\s+{$this->opt($this->namePrefixes)}$/", '$1', array_unique($passengers)));
        }

        if (!empty($ticketNumbers[0])) {
            $tnMasked = false;

            foreach ($ticketNumbers as $t) {
                if (preg_match("#\*{5,}#", $t)) {
                    $tnMasked = true;
                }
            }
            $f->issued()->tickets($ticketNumbers, $tnMasked);
        }

        if (!empty($accountNumbers[0])) {
            $f->program()->accounts($accountNumbers, true);
        }

        $xpath = '//tr[ ./td[1][.//img and not(.//td)] and ./td[2][contains(.," - ")] and ./td[3][string-length(normalize-space(.))<2] ]';
        $segments = $this->http->XPath->query($xpath);

        if (0 === $segments->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();
            $date = null;
            $node = implode("\n", $this->http->FindNodes(".//text()[normalize-space(.)!='']", $root));

            if (preg_match("#(.+?)\s*\-\s*(.+?)[\s\-]*\n{1,2}([^\n]+\d{4})\:?\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)#s", $node, $m)) {
                $s->departure()->name(trim($this->nice($m[1])));
                $s->arrival()->name(trim($this->nice($m[2])));
                $s->airline()
                    ->name($m[4])
                    ->number($m[5]);

                if ($m[3] = $this->normalizeDate($m[3])) {
                    $date = $m[3];
                }
            }

            $node = $this->re("/{$this->opt($this->t('operated by'))}[\s:]+(.+)/iu", $node);

            if (!empty($node)) {
                $s->airline()->operator($node);
            }

            $node = implode("\n",
                $this->http->FindNodes("./following::tr[count(descendant::tr)=0 and contains(.,':')][position()=1]/ancestor::tr[1]//text()[string-length(normalize-space(.))>1]",
                    $root));

            if (preg_match('/(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?).*\n(.+)\s+\(([A-Z]{3})\)(?:\s+TERMINAL\s+(.+))?/', $node,
                $m)) {
                if ($date) {
                    $s->departure()->date(strtotime($date . ', ' . $m[1]));
                }
                $s->departure()
                    ->name($m[2]);

                if (!empty($m[3])) {
                    $s->departure()
                        ->code($m[3]);
                } else {
                    $s->departure()
                        ->noCode();
                }

                if (!empty($m[4])) {
                    $s->departure()->terminal(trim($m[4]));
                }
            } elseif (preg_match('/(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?).*\n(.+)[ ]*/', $node, $m)) {
                if ($date) {
                    $s->departure()->date(strtotime($date . ', ' . $m[1]));
                }
                $s->departure()
                    ->name($m[2])
                    ->noCode();
            }

            $node = implode("\n",
                $this->http->FindNodes("./following::tr[count(descendant::tr)=0 and contains(.,':')][position()=2]/ancestor::tr[1]//text()[string-length(normalize-space(.))>1]",
                    $root));

            if (preg_match('/(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?).*(?:\n[+]\s*(\d{1,3}))?\n(.+)\s+\(([A-Z]{3})\)(?:\s+TERMINAL\s+(.+))?/',
                $node, $m)) {
                if ($date) {
                    $s->arrival()->date(strtotime($date . ', ' . $m[1]));

                    if ($s->getArrDate() && !empty($m[2])) {
                        $s->arrival()->date(strtotime("+$m[2] days", $s->getArrDate()));
                    }
                }
                $s->arrival()
                    ->name($m[3])
                    ->code($m[4]);

                if (!empty($m[5])) {
                    $s->arrival()->terminal(trim($m[5]), true, true);
                }
            } elseif (preg_match('/(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?).*\n(.+)[ ]*/', $node, $m)) {
                if ($date) {
                    $s->arrival()->date(strtotime($date . ', ' . $m[1]));
                }
                $s->arrival()
                    ->name($m[2])
                    ->noCode();
            }

            $node = $this->http->FindSingleNode("./following::tr[count(descendant::tr)=0 and contains(.,':')][position()=3]/ancestor::tr[1]",
                $root);

            if (preg_match("/{$this->opt($this->t('Status'))}[\s:]+(?<status>.+)\s*\|\s*{$this->opt($this->t('Class of service/Fare'))}[\s:]+(?<Cabin>.+)/",
                $node, $m)) {
                $f->general()->status($m['status']);

                if (preg_match("#(.+)\s+\(([A-Z]{1,2})\)#", $m['Cabin'], $v)) {
                    $s->extra()
                        ->cabin($v[1])
                        ->bookingCode($v[2]);
                } else {
                    $s->extra()
                        ->cabin($m['Cabin']);
                }
            }
            $node = $this->http->FindSingleNode("./following::tr[count(descendant::tr)=0 and contains(.,':')][position()=5]/ancestor::tr[1]",
                $root);

            if (preg_match('/' . $this->opt($this->t('Seats')) . '\s*:\s*(.+)/', $node, $m)) {
                $m[1] = preg_replace('/\s+/', '', $m[1]);
                $s->extra()->seats(explode('/', $m[1]));
            }
        }
    }

    private function parseHotel(Email $email): void
    {
        $nodes = $this->http->XPath->query("//img[contains(@src, 'hotel')]/following::text()[normalize-space()='Address']/ancestor::tr[1][contains(normalize-space(), 'Arrival')]");

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->confirmation($this->http->FindSingleNode("./ancestor::table[normalize-space()][2]/descendant::text()[starts-with(normalize-space(), 'Confirmation no.:')]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Confirmation no.:'))}\s*([A-Z\d]+)/"))
                ->status($this->http->FindSingleNode("./ancestor::table[normalize-space()][2]/descendant::text()[contains(normalize-space(), 'Status:')][1]/ancestor::td[1]", $root, true, "/^{$this->opt($this->t('Status:'))}\s*(.+)/"));

            $h->hotel()
                ->name($this->http->FindSingleNode("./ancestor::table[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root, true, "/^\w+\.\s*\d+\s*\w+\s*\d{4}\:\s*(.+)/"))
                ->address(implode(", ", $this->http->FindNodes("./ancestor::table[normalize-space()][2]/descendant::text()[contains(normalize-space(), 'Address')][1]/ancestor::tr[1]/following-sibling::tr/td[2]", $root)));

            $phone = $this->http->FindSingleNode("./ancestor::table[normalize-space()][2]/descendant::text()[contains(normalize-space(), 'T ')][1]/ancestor::td[1]", $root, null, "/{$this->opt($this->t('T '))}\s*([+\s\d\-]+)/");

            if (!empty($phone)) {
                $h->hotel()
                    ->phone($phone);
            }

            $fax = $this->http->FindSingleNode("./ancestor::table[normalize-space()][2]/descendant::text()[contains(normalize-space(), 'F ')][1]/ancestor::td[1]", $root, null, "/{$this->opt($this->t('F '))}\s*([+\s\d\-]+)/");

            if (!empty($fax)) {
                $h->hotel()
                    ->fax($fax);
            }

            $h->booked()
                ->checkIn(strtotime($this->http->FindSingleNode("./ancestor::table[normalize-space()][2]/descendant::text()[contains(normalize-space(), 'Arrival')][1]/ancestor::td[1]", $root, true, "/^(.+)\s+{$this->opt($this->t('Arrival'))}/")))
                ->checkOut(strtotime($this->http->FindSingleNode("./ancestor::table[normalize-space()][2]/descendant::text()[contains(normalize-space(), 'Departure')][1]/ancestor::td[1]", $root, true, "/^(.+)\s+{$this->opt($this->t('Departure'))}/")));
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\S+\s+(\d{1,2})\.?\s+([^,.\d\s]{3,})\s+(\d{4}):?$#', // Di. 06. Februar 2018
        ];
        $out = [
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

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//*[contains(normalize-space(),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLangPdf($text): bool
    {
        foreach ($this->reBodyPdf as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s, '/') . ")";
        }, $field)) . ')';
    }

    private function nice($str)
    {
        return preg_replace("#\s+#", ' ', $str);
    }

    /**
     * @param $text
     * @param $needle
     * @param string $function 'first' - first finded, 'min' - min value of all needles, 'max' - max value of all needles
     *
     * @return bool
     */
    private function strposAll($text, $needle, $function = 'first')
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            $result = [];

            foreach ($needle as $n) {
                $v = strpos($text, $n);

                if ($function == 'first' && $v !== false) {
                    return $v;
                }

                if ($v !== false) {
                    $result[] = $v;
                }
            }
            $result = array_filter($result);

            if (empty($result)) {
                return false;
            }

            if ($function == 'min') {
                return min($result);
            }

            if ($function == 'max') {
                return max($result);
            }
        } elseif (is_string($needle)) {
            return strpos($text, $needle);
        }

        return false;
    }
}
