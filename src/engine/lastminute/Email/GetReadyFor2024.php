<?php

namespace AwardWallet\Engine\lastminute\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class GetReadyFor2024 extends \TAccountChecker
{
    public $mailFiles = "lastminute/it-777328116.eml, lastminute/it-793321804-es.eml, lastminute/it-797149774.eml, lastminute/it-812678538-fr.eml, lastminute/it-833044196-da.eml, lastminute/it-852775817.eml, lastminute/it-859515263.eml, lastminute/it-867174984.eml";
    public static $detectProvider = [
        'bravofly' => [
            'from'       => 'bravofly.',
            'logoImgSrc' => ['bravofly', 'logo-BF', 'BRAVOFLY'],
            'name'       => ['bravofly', 'Bravofly'],
        ],
        'rumbo' => [
            'from'       => 'rumbo.',
            'logoImgSrc' => ['rumbo', 'RUMBO'],
            'name'       => ['rumbo'],
        ],
        'volagratis' => [
            'from'       => 'volagratis.',
            'logoImgSrc' => ['logo-VG', 'volagratis', 'VOLAGRATIS'],
            'name'       => ['volagratis'],
        ],
        'lastminute' => [
            'from'       => '@lastminute.com',
            'logoImgSrc' => ['lastminute', 'LASTMINUTE'],
            'name'       => ['lastminute'],
        ],
    ];

    public $detectSubject = [
        // en
        ' booking for ',
        // de
        ' Flugbuchung ',
        // sv
        ' bokning av ',
        // da
        ' booking til ',
        // es
        'Reserva ',
        // no
        ' bestilling for ',
        // nl
        ' boeking van ',
        // it
        'Prenotazione ',
        // fr
        'Réservation ',
        // pt
        'Reserva ',
        // hu
        ' foglalás ',
    ];

    public $relativeDate;
    public $lang = '';
    public static $dictionary = [
        'en' => [
            'Booking ID'             => 'Booking ID',
            'Booking Status'         => 'Booking Status',
            'Dear '                  => 'Dear ',
            'Your trip details'      => ['Your trip details', 'Your flight', 'Your hotel', 'Car rental'],
            'Terminal:'              => 'Terminal:',
            'Operated by'            => 'Operated by',
            'E-Ticket:'              => 'E-Ticket:',
            'Seat'                   => 'Seat',
            'How to check in online' => 'How to check in online',
            'Airline PNR'            => 'Airline PNR',
            'Total price'            => 'Total price',

            // Hotel
            'Confirmation code:' => 'Confirmation code:',
            'Check in'           => 'Check in',
            'Check out'          => 'Check out',

            // Rental
            'PNR:'     => 'PNR:',
            'Pick up'  => 'Pick up',
            'Drop off' => 'Drop off',
        ],
        'de' => [
            'Booking ID'             => 'Booking ID',
            'Booking Status'         => 'Buchungsstatus',
            'Dear '                  => 'Hallo ',
            'Your trip details'      => ['Reiseinformationen', 'Ihr Flug'],
            'Terminal:'              => 'Terminal:',
            'Operated by'            => 'Durchgeführt von:',
            'E-Ticket:'              => ['Elektronisches Ticket:', 'E-Ticket:'],
            // 'Seat' => '',
            'How to check in online' => 'Bequem online einchecken',
            'Airline PNR'            => 'PNR der Fluggesellschaft',
            'Total price'            => 'Gesamtpreis',

            // Hotel
            'Confirmation code:' => 'Bestätigungscode:',
            'Check in'           => 'Check-in',
            'Check out'          => 'Abreise',

            // Rental
            // 'PNR:' => '',
            // 'Pick up' => '',
            // 'Drop off' => '',
        ],
        'sv' => [
            'Booking ID'        => 'Booking ID',
            'Booking Status'    => 'Bokningsstatus',
            'Dear '             => 'Bäste/a ',
            'Your trip details' => ['Information om din resa', 'Ditt flyg'],
            // 'Terminal:' => '',
            // 'Operated by' => '',
            // 'E-Ticket:' => '',
            // 'Seat' => '',
            'How to check in online' => 'Så checkar du in online',
            'Airline PNR'            => ['Flygbolagets PNR', 'Bekräftelsekod'],
            'Total price'            => 'Totalbelopp',

            // Hotel
            // 'Confirmation code:' => ':',
            // 'Check in' => '',
            // 'Check out' => '',

            // Rental
            // 'PNR:' => '',
            // 'Pick up' => '',
            // 'Drop off' => '',
        ],
        'da' => [
            'Booking ID'             => 'Booking ID',
            'Booking Status'         => 'Bookingstatus',
            'Dear '                  => 'Kære ',
            'Your trip details'      => 'Dit fly',
            'Terminal:'              => ['Terminal: :', 'Terminal::', 'Terminal:'],
            'Operated by'            => 'Betjent af',
            'E-Ticket:'              => ['E-billet:', 'E-Ticket:'],
            'Seat'                   => 'Seat',
            'How to check in online' => 'Sådan tjekker du ind online',
            'Airline PNR'            => 'Flyselskabets PNR',
            'Total price'            => 'Totalpris',

            // Hotel
            // 'Confirmation code:' => ':',
            // 'Check in' => '',
            // 'Check out' => '',

            // Rental
            // 'PNR:' => '',
            // 'Pick up' => '',
            // 'Drop off' => '',
        ],
        'es' => [
            'Booking ID'             => 'ID Booking',
            'Booking Status'         => 'Estado de tu reserva',
            'Dear '                  => 'Hola ',
            'Your trip details'      => 'Detalles del viaje',
            'Terminal:'              => 'Terminal:',
            'Operated by'            => 'Operado por',
            'E-Ticket:'              => 'E-Ticket:',
            // 'Seat' => '',
            'How to check in online' => 'Cómo facturar online',
            'Airline PNR'            => 'PNR de la aerolínea',
            'Total price'            => 'Precio total',

            // Hotel
            // 'Confirmation code:' => ':',
            // 'Check in' => '',
            // 'Check out' => '',

            // Rental
            // 'PNR:' => '',
            // 'Pick up' => '',
            // 'Drop off' => '',
        ],
        'no' => [
            'Booking ID'        => 'Booking ID',
            'Booking Status'    => 'Bestillingsstatus',
            'Dear '             => 'Kjære ',
            'Your trip details' => 'Detaljopplysninger om reisen',
            'Terminal:'         => 'Terminal:',
            // 'Operated by' => 'Durchgeführt von:',
            // 'E-Ticket:' => 'E-Ticket:',
            // 'Seat' => '',
            'How to check in online' => 'Slik sjekker du inn på nettet',
            'Airline PNR'            => 'Flyselskapets PNR',
            'Total price'            => 'Total pris',

            // Hotel
            // 'Confirmation code:' => ':',
            // 'Check in' => '',
            // 'Check out' => '',

            // Rental
            // 'PNR:' => '',
            // 'Pick up' => '',
            // 'Drop off' => '',
        ],
        'nl' => [
            'Booking ID'             => 'Booking ID',
            'Booking Status'         => 'Boekingsstatus',
            'Dear '                  => 'Beste ',
            'Your trip details'      => ['Je reisgegevens', 'Jouw vlucht'],
            'Terminal:'              => ['Terminal::', 'Terminal:'],
            'Operated by'            => 'Uitgevoerd door',
            'E-Ticket:'              => 'E-Ticket:',
            // 'Seat' => '',
            'How to check in online' => 'Online inchecken',
            'Airline PNR'            => 'PNR van luchtvaartmaatschappij',
            'Total price'            => 'Totaalprijs',

            // Hotel
            // 'Confirmation code:' => ':',
            // 'Check in' => '',
            // 'Check out' => '',

            // Rental
            // 'PNR:' => '',
            // 'Pick up' => '',
            // 'Drop off' => '',
        ],
        'it' => [
            'Booking ID'        => 'ID Booking',
            'Booking Status'    => 'Stato della prenotazione',
            'Dear '             => 'Ciao ',
            'Your trip details' => ['Dettagli del tuo viaggio', 'Il tuo volo'],
            'Terminal:'         => 'Terminal:',
            // 'Operated by' => 'Durchgeführt von:',
            'E-Ticket:'              => 'E-Ticket:',
            // 'Seat' => '',
            'How to check in online' => 'Come fare il check-in online',
            'Airline PNR'            => 'PNR della linea aerea',
            'Total price'            => 'Prezzo totale',

            // Hotel
            'Confirmation code:' => 'Codice di conferma:',
            'Check in'           => 'Check-in',
            'Check out'          => 'Check-out',

            // Rental
            // 'PNR:' => '',
            // 'Pick up' => '',
            // 'Drop off' => '',
        ],
        'fr' => [
            'Booking ID'        => 'ID Booking',
            'Booking Status'    => 'État de la réservation',
            'Dear '             => 'Bonjour ',
            'Your trip details' => 'Votre vol',
            // 'Terminal:' => '',
            // 'Operated by' => '',
            // 'E-Ticket:' => '',
            // 'Seat' => '',
            'How to check in online' => "Comment s'enregistrer en ligne",
            'Airline PNR'            => 'Code de réservation',
            'Total price'            => 'Prix total',

            // Hotel
            'Confirmation code:' => 'Confirmation code:',
            'Check in'           => 'Arrivée',
            'Check out'          => 'Départ',

            // Rental
            // 'PNR:' => '',
            // 'Pick up' => '',
            // 'Drop off' => '',
        ],
        'pt' => [
            'Booking ID'        => 'ID Booking',
            'Booking Status'    => 'Estado da tua reserva',
            'Dear '             => 'Caro(a) ',
            'Your trip details' => 'O seu hotel',
            // 'Terminal:' => '',
            // 'Operated by' => '',
            // 'E-Ticket:' => '',
            // 'Seat' => '',
            // 'How to check in online' => "Comment s'enregistrer en ligne",
            // 'Airline PNR'            => 'Code de réservation',
            'Total price'            => 'Preço total',

            // Hotel
            'Confirmation code:' => 'Confirmation code:',
            'Check in'           => 'Entrada',
            'Check out'          => 'Saída',

            // Rental
            // 'PNR:' => '',
            // 'Pick up' => '',
            // 'Drop off' => '',
        ],
        'hu' => [
            'Booking ID'        => 'ID Booking',
            'Booking Status'    => 'Foglalási státusz',
            'Dear '             => 'Kedves ',
            'Your trip details' => 'Az Ön járata',
            // 'Terminal:' => '',
            'Operated by' => 'Üzemeltető:',
            // 'E-Ticket:' => '',
            // 'Seat' => '',
            'How to check in online' => "Az online utasfelvétel elvégzésének módja",
            'Airline PNR'            => 'Foglalási szám',
            'Total price'            => 'Teljes foglalás',

            // Hotel
            // 'Confirmation code:' => ':',
            // 'Check in'           => '',
            // 'Check out'          => '',

            // Rental
            // 'PNR:' => '',
            // 'Pick up' => '',
            // 'Drop off' => '',
        ],
    ];

    private $patterns = [
        'date' => '\b\d{1,2}[-,. ]+[[:alpha:]]+[-,. ]+\d{4}\b', // Friday 22 November 2024
    ];

    private $codeProvider = null;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your trip details'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Your trip details'])}]")->length > 0
                && (
                    (!empty($dict['How to check in online']) && $this->http->XPath->query("//text()[{$this->eq($dict['How to check in online'])}]")->length > 0)
                    || (!empty($dict['Confirmation code:']) && $this->http->XPath->query("//text()[{$this->eq($dict['Confirmation code:'])}]")->length > 0)
                    || (!empty($dict['Drop off']) && $this->http->XPath->query("//text()[{$this->eq($dict['Drop off'])}]")->length > 0)
                )
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (empty($this->codeProvider)) {
            foreach (self::$detectProvider as $prov => $detects) {
                if (!empty($detects['from']) && preg_match("/{$this->preg_implode($detects['from'])}/", $parser->getCleanFrom())) {
                    $this->codeProvider = $prov;

                    break;
                }

                if (!empty($detects['name']) && (
                    preg_match("/{$this->preg_implode($detects['name'])}/i", $parser->getSubject())
                    || $this->http->XPath->query("//a/@href[{$this->contains($detects['name'])}]")->length > 0
                )) {
                    $this->codeProvider = $prov;

                    break;
                }

                if (!empty($detects['logoImgSrc']) && $this->http->XPath->query("//img/@src[contains(@src, 'logo')][{$this->contains($detects['logoImgSrc'])}]")->length > 0) {
                    $this->codeProvider = $prov;

                    break;
                }
            }
        }

        if (!empty($this->codeProvider)) {
            $email->setProviderCode($this->codeProvider);
        }

        // Travel Agency
        $email->obtainTravelAgency();

        $tripNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking ID'), "translate(.,':','')")}]/following::text()[normalize-space(.)][1]", null, true, "/^\s*([A-Z\d]{5,35})\s*$/");

        $email->ota()->confirmation($tripNumber);

        // Price
        $totalText = $this->http->FindSingleNode("//td[{$this->eq($this->t("Total price"))}]/following-sibling::*[normalize-space(.)][1]",
            null, true, "/^\s*.*\d.*\s*$/");

        if (!empty($totalText)) {
            $currency = $this->currency(trim(preg_replace('/^\s*(\D*?)\s*\d[\d,. ]+?\s*(\D*)\s*$/', '$1 $2', $totalText)));
            $totalCharge = $this->amount($this->re("/^\s*\D*(\d[\d,. ]+?)\D*\s*$/", $totalText), $currency);
            $email->price()->total($totalCharge);
            $email->price()->currency($currency);
        }

        $this->relativeDate = strtotime($parser->getDate());

        $this->flight($email);
        $this->hotel($email);
        $this->rental($email);

        $travellers = array_filter($this->http->FindNodes("//tr[not(.//tr)][.//img[contains(@src, '/person_') or @alt='person']][not(contains(., ':')) and not(contains(., '：'))]",
            null, "/^\s*([^\d\s]+.+)/"));

        if (empty($travellers)) {
            $travellers = array_filter($this->http->FindNodes("//tr[not(.//tr)][.//img[contains(@src, '/person_') or @alt='person']][contains(., ':') or contains(., '：')]",
                null, "/^\s*[[:alpha:] ]+[:：]\s*([[:alpha:]]\D+)/u"));
        }
        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Status'), "translate(.,':','')")}]/following::text()[normalize-space()][1]");

        foreach ($email->getItineraries() as $it) {
            $it->general()
                ->status($status)
                ->travellers($travellers, true);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectProvider as $prov => $detects) {
            if ((!empty($detects['from']) && strpos($from, $detects['from']) !== false)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $head = false;

        foreach (self::$detectProvider as $prov => $detects) {
            if (!empty($detects['from']) && strpos($headers["from"], $detects['from']) !== false) {
                $head = true;
                $this->codeProvider = $prov;

                break;
            }

            if (!empty($detects['name'])) {
                foreach ($detects['name'] as $dName) {
                    if (stripos($headers["subject"], $dName) !== false) {
                        $head = true;
                        $this->codeProvider = $prov;

                        break 2;
                    }
                }
            }
        }

        if ($head === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $head = false;

        foreach (self::$detectProvider as $prov => $detects) {
            if (!empty($detects['name'])
                && $this->http->XPath->query("//a/@href[{$this->contains($detects['name'])}] | //text()[{$this->contains($detects['name'])}]")->length > 0
            ) {
                $head = true;
                $this->codeProvider = $this->codeProvider ?? $prov;

                break;
            }

            if (!empty($detects['logoImgSrc']) && $this->http->XPath->query("//img/@src[contains(@src, 'logo')][{$this->contains($detects['logoImgSrc'])}]")->length > 0) {
                $head = true;
                $this->codeProvider = $this->codeProvider ?? $prov;

                break;
            }
        }

        if ($head === false) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your trip details'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Your trip details'])}]")->length > 0
                && (
                    (!empty($dict['How to check in online']) && $this->http->XPath->query("//text()[{$this->eq($dict['How to check in online'])}]")->length > 0)
                    || (!empty($dict['Confirmation code:']) && $this->http->XPath->query("//text()[{$this->eq($dict['Confirmation code:'])}]")->length > 0)
                    || (!empty($dict['Drop off']) && $this->http->XPath->query("//text()[{$this->eq($dict['Drop off'])}]")->length > 0)
                )
            ) {
                return true;
            }
        }

        return false;
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
        return array_keys(self::$detectProvider);
    }

    private function flight(Email $email): void
    {
        if ($this->http->XPath->query("//node()[normalize-space() = '●'] | //img[contains(@src, '/flight_right')]")->length === 0) {
            return;
        }

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        // Issued
        $ticketsNodes = $this->http->XPath->query("//tr[not(.//tr)][{$this->starts($this->t('E-Ticket:'))}]");
        $usedTicket = [];

        foreach ($ticketsNodes as $tRoot) {
            $ticket = $this->re("/{$this->preg_implode($this->t('E-Ticket:'))}\s*(\d{5,}.+)\s*$/", $tRoot->nodeValue);

            if (!empty($ticket) && !in_array($ticket, $usedTicket)) {
                $usedTicket[] = $ticket;
                $f->issued()
                    ->ticket($ticket, false, $this->http->FindSingleNode("preceding::tr[not(.//tr)][.//img[contains(@src, '/person_')]][1]", $tRoot));
            }
        }

        $allSeats = [];
        $seatsNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Seat'))}]/ancestor::tr[1][{$this->starts($this->t('Seat'))}]");

        foreach ($seatsNodes as $root) {
            $routes = explode(' - ', $this->http->FindSingleNode("preceding-sibling::*[normalize-space()][position() = last() and position() < 10]", $root));

            if (count($routes) === 2) {
                $seat = $this->http->FindSingleNode(".", $root, true, "/^\s*{$this->preg_implode($this->t('Seat'))}\s+(\d{1,3}[A-Z])\s*$/");
                $traveller = $this->http->FindSingleNode("ancestor::tr[3][count(.//img[contains(@src, '/person_') or @alt='person']) = 1]//img[contains(@src, '/person_') or @alt='person']/ancestor::tr[1]", $root);
                $allSeats[] = [
                    'dName'     => $routes[0],
                    'aName'     => $routes[1],
                    'seat'      => $seat,
                    'traveller' => $traveller,
                ];
            }
        }

        // Segments
        $xpath = "//tr[*[2][normalize-space() = '●']][following-sibling::tr]";
        // $this->logger->debug($xpath);
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $dateVal = $pnr = null;
            $preRoots = $this->http->XPath->query("preceding::tr[normalize-space() and count(*) = 2][1]", $root);
            $preRoot = $preRoots->length > 0 ? $preRoots->item(0) : null;

            while ($preRoot) {
                $td1 = $this->http->FindSingleNode("*[1]", $preRoot);
                $td2 = $this->http->FindSingleNode("*[2]", $preRoot);
                $findDate = preg_match("/{$this->patterns['date']}$/u", $td1) > 0;
                $findPNR = preg_match("/PNR\s*[:]+\s*([A-Z\d]{5,10})\s*$/", $td2, $m) > 0;

                if ($findDate || $findPNR) {
                    $dateVal = $td1;
                    $pnr = $findPNR ? $m[1] : null;

                    break;
                }

                $preRoots = $this->http->XPath->query("preceding::tr[normalize-space() and count(*)=2][1]", $preRoot);
                $preRoot = $preRoots->length > 0 ? $preRoots->item(0) : null;
            }

            $date = $this->normalizeDate($dateVal);

            if (!empty($date)) {
                $this->relativeDate = $date;
            }

            $td1 = implode("\n", $this->http->FindNodes("td[1]/descendant::tr[not(.//tr)][normalize-space()]", $root));
            $td3 = implode("\n", $this->http->FindNodes("td[3]/descendant::tr[not(.//tr)][normalize-space()]", $root));

            // Airline
            if (preg_match('/\n *(?<an>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,4})\s*•\s*(?<cabin>.+)\n(?<duration>.*\d.+)\s*$/', $td3, $m)) {
                // IT 231•Economy
                $s->airline()
                    ->name($m['an'])
                    ->number($m['fn']);

                // Extra
                $s->extra()
                    ->cabin($m['cabin'])
                    ->duration($m['duration'])
                ;
            }

            if (preg_match("/\n *{$this->preg_implode($this->t('Operated by'))} *(\S.+)/", $td3, $m)) {
                $s->airline()
                    ->operator($m[1]);
            }

            if (!empty($pnr)) {
                $s->airline()
                    ->confirmation($pnr);
            }

            // Departure
            if (preg_match("/^\s*\( *(?<code>[A-Z]{3}) *\) *(?<name>.+)(?:\n *{$this->preg_implode($this->t('Terminal:'))}[ :]*(?<terminal>.*))?/", $td3, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->terminal($m['terminal'] ?? null, true, true)
                ;
            }
            $s->departure()
                ->date($this->normalizeDate($td1, $date));

            // Arrival
            $td1a = implode("\n", $this->http->FindNodes("following-sibling::tr[1]/td[1]/descendant::tr[not(.//tr)][normalize-space()]", $root));
            $td3a = implode("\n", $this->http->FindNodes("following-sibling::tr[1]/td[3]/descendant::tr[not(.//tr)][normalize-space()]", $root));

            if (preg_match("/^\s*\( *(?<code>[A-Z]{3}) *\) *(?<name>.+)(?:\n *{$this->preg_implode($this->t('Terminal:'))}[ :]*(?<terminal>.*))?/", $td3a, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->terminal($m['terminal'] ?? null, true, true)
                ;
            }
            $s->arrival()
                ->date($this->normalizeDate($td1a, $date));

            if (!empty($allSeats) && !empty($s->getDepName()) && !empty($s->getArrName())) {
                foreach ($allSeats as $i => $value) {
                    if (strpos($s->getDepName(), $value['dName']) === 0 && strpos($s->getArrName(), $value['aName']) === 0) {
                        $s->extra()
                            ->seat($value['seat'], true, true, $value['traveller']);
                    }
                }
            }
        }
    }

    private function hotel(Email $email): void
    {
        $xpath = "//text()[" . $this->eq($this->t("Check in")) . "]/ancestor::*[.//text()[" . $this->eq($this->t("Check out")) . "] and .//img[contains(@src,'/bed_')]][1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0 && $this->http->XPath->query("//img[contains(@src,'/bed_')] | //text()[{$this->eq($this->t("Check out"))}]")->length > 0) {
            $email->add()->hotel();
        }

        foreach ($segments as $root) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->confirmation($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Confirmation code:'))}]/following::text()[normalize-space()][1]",
                    $root, true, "/^\s*[\dA-Z\-]{5,}\s*$/"));

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode(".//img[contains(@src, '/location_')]/preceding::tr[not(.//tr)][normalize-space()][1]", $root))
                ->address($this->http->FindSingleNode(".//img[contains(@src, '/location_')]/ancestor::tr[normalize-space()][1]", $root))
                ->phone($this->http->FindSingleNode(".//img[contains(@src, '/phone_')]/ancestor::tr[normalize-space()][1]", $root));

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Check in'))}]/following::text()[normalize-space()][1]", $root)))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Check out'))}]/following::text()[normalize-space()][1]", $root)))
                ->rooms($this->http->FindSingleNode(".//img[contains(@src, '/room_')]/following::text()[normalize-space()][1]",
                    $root, true, "/^\s*(\d+)\s+x\s+/"))
                ->guests($this->http->FindSingleNode(".//img[contains(@src, '/room_')]/ancestor::tr[1]/following::tr[normalize-space()][1]",
                    $root, true, "/^\s*(\d+)\s+[[:alpha:]]+\s*$/u"))
                ->kids($this->http->FindSingleNode(".//img[contains(@src, '/room_')]/ancestor::tr[1]/following::tr[normalize-space()][2]",
                    $root, true, "/^\s*(\d+)\s+[[:alpha:]]+\s*$/u"), true, true);

            $time = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Check in'))}]/ancestor::*[not({$this->eq($this->t('Check in'))})][1]/*[normalize-space()][3]",
                $root, null, "/^\D* (\d{1,2}:\d{2})\s*$/");

            if (!empty($time) && !empty($h->getCheckInDate())) {
                $h->booked()
                    ->checkIn(strtotime($time, $h->getCheckInDate()));
            }
            $time = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Check out'))}]/ancestor::*[not({$this->eq($this->t('Check out'))})][1]/*[normalize-space()][4]",
                $root, null, "/^\D* (\d{1,2}:\d{2})\s*$/");

            if (empty($time)) {
                $time = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Check out'))}]/ancestor::*[not({$this->eq($this->t('Check out'))})][1]/*[normalize-space()][3]",
                    $root, null, "/^\D* (\d{1,2}:\d{2})\s*$/");
            }

            if (!empty($time) && !empty($h->getCheckOutDate())) {
                $h->booked()
                    ->checkOut(strtotime($time, $h->getCheckOutDate()));
            }

            $h->addRoom()
                ->setType($this->http->FindSingleNode(".//img[contains(@src, '/room_')]/following::text()[normalize-space()][1]",
                    $root, true, "/^\s*\d+\s+x\s+(.+)/"));
        }
    }

    private function rental(Email $email): void
    {
        $xpath = "//text()[" . $this->eq($this->t("Pick up")) . "]/ancestor::*[.//text()[" . $this->eq($this->t("Drop off")) . "] and .//img[contains(@src,'/car_16')]][1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0 && $this->http->XPath->query("//img[contains(@src,'/car_16')] | //text()[{$this->eq($this->t("Drop off"))}]")->length > 0) {
            $email->add()->rental();
        }

        foreach ($segments as $root) {
            $r = $email->add()->rental();

            // General
            $r->general()
                ->confirmation($this->http->FindSingleNode(".//text()[{$this->eq($this->t('PNR:'))}]/following::text()[normalize-space()][1]",
                    $root, true, "/^\s*[\dA-Z\-]{5,}\s*$/")
                ?? $this->http->FindSingleNode(".//text()[{$this->eq(preg_replace('/\s*:\s*$/', '', $this->t('PNR:')))}]/following::text()[normalize-space()][1][{$this->eq(':')}]/following::text()[normalize-space()][1]",
                        $root, true, "/^\s*[\dA-Z\-]{5,}\s*$/"));

            // Pick Up
            $text = implode("\n", $this->http->FindNodes(".//text()[{$this->eq($this->t('Pick up'))}]/ancestor::*[not({$this->eq($this->t('Pick up'))})][1]/*", $root));

            if (preg_match("/^\s*{$this->preg_implode($this->t('Pick up'))}\n\s*(?<name>.+)\n\s*[[:alpha:]]+\s+(?<date>\d{1,2}:\d{2}.+)\s*$/u", $text, $m)) {
                $r->pickup()
                    ->location($m['name'])
                    ->date($this->normalizeDate($m['date']));
            }
            // Drop Off
            $text = implode("\n", $this->http->FindNodes(".//text()[{$this->eq($this->t('Drop off'))}]/ancestor::*[not({$this->eq($this->t('Drop off'))})][1]/*", $root));

            if (preg_match("/^\s*{$this->preg_implode($this->t('Drop off'))}\n\s*(?<name>.+)\n\s*[[:alpha:]]+\s+(?<date>\d{1,2}:\d{2}.+)\s*$/u", $text, $m)) {
                $r->dropoff()
                    ->location($m['name'])
                    ->date($this->normalizeDate($m['date']));
            }

            // Car
            $r->car()
                ->model($this->http->FindSingleNode(".//img[contains(@src, '/car_')]/ancestor::tr[normalize-space()][1]", $root));

            $r->extra()
                ->company($this->http->FindSingleNode(".//img[contains(@src, '/car_')]/preceding::tr[not(.//tr)][normalize-space()][1]", $root));
        }
    }

    private function normalizeDate($str, $relativeDate = null)
    {
        $year = date("Y", $relativeDate);

        if ($year < 2010) {
            $year = date("Y", $this->relativeDate);
        }
        $in = [
            // Montag 4 November 2024
            '/^\s*[[:alpha:]\-]+\s*[, ]\s*(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s*$/ui',
            // Fri, 28 Mar; dim., 23 févr.
            '/^\s*([[:alpha:]\-]+)\.?\s*[, ]\s*(\d+)\s+([[:alpha:]]+)\.?\s*$/ui',
            // 13:45, Sun, 14 Sep
            '/^\s*(\d+:\d+)\s*,\s*([[:alpha:]\-]+)\.?\s*[, ]\s*(\d+)\s+([[:alpha:]]+)\.?\s*$/ui',

            //07:00    22 Nov
            '/^\s*(\d+:\d+)\s*\n\s*(\d+)\s+([[:alpha:]]+)\.?\s*$/ui',
        ];
        $out = [
            '$1 $2 $3',
            '$1, $2 $3 ' . $year,
            '$2, $3 $4 ' . $year . ', $1',

            '$2 $3 %year%, $1',
        ];
        $date = preg_replace($in, $out, $str);
        // $this->logger->debug('$date = ' . print_r($date, true));

        if (preg_match("#\b\d{1,2}\s+([[:alpha:]]+)\s+(?:\d{4}\b|%year%)#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (!preg_match("/\b\d{4}\b/", $date)) {
            if (!empty($relativeDate) && $relativeDate > strtotime('01.01.2000') && strpos($date, '%year%') !== false && preg_match('/^\s*(?<date>\d+ \w+) %year%(?:\s*,\s*(?<time>\d{1,2}:\d{1,2}.*))?\s*$/', $date, $m)) {
                $date = EmailDateHelper::parseDateRelative($m['date'], $relativeDate);

                if (!empty($date) && !empty($m['time'])) {
                    $date = strtotime($m['time'], $date);
                }
            } else {
                $date = null;
            }
        } elseif (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $date, $m) && $year > 2010) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } else {
            $date = strtotime($date);
        }

        return $date;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }

    private function currency($s)
    {
        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        $sym = [
            '€'  => 'EUR',
            '£'  => 'GBP',
            'R$' => 'BRL',
            '$'  => 'USD',
            'SFr'=> 'CHF',
            'Ft' => 'HUF',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }
        //
        // if (mb_strpos($s, 'kr') !== false) {
        //     if ($this->lang = 'da') {
        //         return 'DDK';
        //     }
        //
        //     if ($this->lang = 'no') {
        //         return 'NOK';
        //     }
        //
        //     if ($this->lang = 'sv') {
        //         return 'SEK';
        //     }
        // }

        return $s;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($amount, $currency = null)
    {
        if (empty($amount)) {
            return null;
        }

        $amount = PriceHelper::parse($amount, $currency);

        if (is_numeric($amount)) {
            $amount = (float) $amount;
        } else {
            $amount = null;
        }

        return $amount;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
