<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingFlight extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-17099305.eml, tapportugal/it-17485571.eml, tapportugal/it-17839957.eml, tapportugal/it-17902505.eml, tapportugal/it-18572963.eml, tapportugal/it-18880446.eml, tapportugal/it-24456064.eml";
    private $langDetectors = [
        'fr' => ['Référence de la réservation:'],
        'pt' => ['Referência da Reserva:'],
        'de' => ['Buchungsreferenz:'],
        'en' => ['Booking reference:'],
        'es' => ['Referencia de la reserva:'],
        'it' => ['Codice prenotazione:'],
    ];
    private $lang = '';
    private $year;

    private static $dict = [
        'fr' => [
            'Booking reference:'              => 'Référence de la réservation:',
            'Passengers'                      => 'Passagers',
            'Passenger name'                  => 'Nom du passager',
            'Departure'                       => 'Départ',
            'Arrival'                         => 'Arrivée',
            'Seats'                           => 'Sièges',
            'Date'                            => 'Date',
            'Flight operated by'              => 'Vol opéré par',
            'Aircraft'                        => 'Type d\'avion',
            'Price Breakdown'                 => 'Détail du prix',
            'Total amount for all passengers' => 'Prix total pour tous les passagers',
            'All prices in this page are in'  => 'Tous les prix de cette page sont exprimés en',
            'Miles'                           => 'miles',
            'Date / Time of the Reservation:' => 'Date / heure de Réservation:',
            'statuses'                        => '/(Réservation terminée|Confirmation de la carte de crédit exigée|Carte de crédit non autorisée)/i',
            'feeHeaders'                      => ['Frais de transport aérien', 'Taxes, frais et autres charges'],
        ],
        'es' => [
            'Booking reference:'              => 'Referencia de la reserva:',
            'Passengers'                      => 'Pasajeros',
            'Passenger name'                  => 'Nombre del pasajero',
            'Departure'                       => 'Salida',
            'Arrival'                         => 'Llegada',
            'Seats'                           => 'Asientos',
            'Date'                            => 'Fecha',
            'Flight operated by'              => 'Vuelo operado por',
            'Aircraft'                        => 'Avión',
            'Price Breakdown'                 => 'Desglose del Precio',
            'Total amount for all passengers' => 'Precio total para todos los pasajeros',
            'All prices in this page are in'  => 'Todos los precios indicados en esta página están en',
            'Miles'                           => 'Millas',
            'Date / Time of the Reservation:' => 'Fecha / hora de la reserva:',
            'statuses'                        => '/(Reserva completa|Es necesaria la confirmación de la tarjeta de crédito)/i',
            'feeHeaders'                      => ['Tasas aéreas', 'Tasas, Impuestos y demás recargos'],
        ],
        'pt' => [
            'Booking reference:'              => 'Referência da Reserva:',
            'Passengers'                      => 'Passageiros',
            'Passenger name'                  => 'Nome do passageiro',
            'Departure'                       => 'Partida',
            'Arrival'                         => 'Chegada',
            'Seats'                           => 'Lugares',
            'Date'                            => 'Data',
            'Flight operated by'              => 'Voo operado por',
            'Aircraft'                        => 'Equipamento',
            'Price Breakdown'                 => 'Preço detalhado',
            'Total amount for all passengers' => 'Preço total para todos os passageiros',
            'All prices in this page are in'  => 'Todos os preços aqui indicados estão em',
            'Miles'                           => 'Milhas',
            'Date / Time of the Reservation:' => 'Data/Hora da Reserva:',
            'statuses'                        => '/(A aguardar pagamento|Reserva Concluída|Cartão de Crédito não Autorizado|Necessária confirmação do cartão de crédito|Reserva Concluída)/i',
            'feeHeaders'                      => ['Custos de transporte aéreo', 'Taxas, sobretaxas e outros encargos:'],
        ],
        'de' => [
            'Booking reference:' => 'Buchungsreferenz:',
            'Passengers'         => 'Fluggäste',
            'Passenger name'     => 'Name des Fluggasts',
            'Departure'          => 'Abflug',
            'Arrival'            => 'Ankunft',
            //            'Seats' => '',
            'Date'                            => 'Datum',
            'Flight operated by'              => 'Flug durchgeführt von',
            'Aircraft'                        => 'Flugzeug',
            'Price Breakdown'                 => 'Preis und Abgaben im Einzelnen',
            'Total amount for all passengers' => 'Gesamtbetrag für alle Fluggäste',
            'All prices in this page are in'  => 'Alle auf dieser Seite genannten Preise verstehen sich in',
            'Miles'                           => 'Meilen',
            'Date / Time of the Reservation:' => 'Datum/Uhrzeit der Reservierung:',
            'statuses'                        => '/(Buchung abgeschlossen)/i',
            'feeHeaders'                      => ['Luftbeförderungsgebühren', 'Steuern, Gebühren und sonstige Zuschläge'],
        ],
        'it' => [
            'Booking reference:' => 'Codice prenotazione:',
            'Passengers'         => 'Passeggeri',
            'Passenger name'     => 'Nome del passeggero',
            'Departure'          => 'Partenza',
            'Arrival'            => 'Arrivo',
            //            'Seats' => '',
            'Date'                            => 'Data',
            'Flight operated by'              => 'Volo operato da',
            'Aircraft'                        => 'Aereo',
            'Price Breakdown'                 => 'Dettaglio Tariffa',
            'Total amount for all passengers' => 'Importo totale per tutti i passeggeri',
            'All prices in this page are in'  => 'I prezzi indicati su questa pagina sono in',
            'Miles'                           => 'Miles',
            'Date / Time of the Reservation:' => 'Data/Ora della prenotazione:',
            'statuses'                        => '/Prenotazione (ultimata)/i',
            'feeHeaders'                      => ['Costi di trasporto aereo', 'Tasse, diritti e supplementi'],
        ],
        'en' => [
            'Passenger name'                  => ['Passenger name', 'Passenger Name'],
            'Date / Time of the Reservation:' => ['Date / Time of the Reservation:', 'Date / Time of the Reservation'],
            'statuses'                        => '/Booking (Completed)/i',
            'Stop'                            => 'Layover:',
            'feeHeaders'                      => ['Air Transportation charges', 'Taxes, fees and carrier charges'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'TAP Air Portugal') !== false
            || stripos($from, '@flytap.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Booking -') !== false
            || stripos($headers['subject'], 'Reserva efetuada -') !== false
            || stripos($headers['subject'], 'Buchung -') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"please contact TAP.") or contains(normalize-space(.)," TAP. All rights reserved") or contains(normalize-space(.)," TAP. Todos os direitos reservados")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.flytap.com") or contains(@href,"//receipts.flytap.com") or contains(normalize-space(.), "TAP")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->year = date('Y', strtotime($parser->getDate()));

        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('BookingFlight' . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $patterns = [
            'confNumber'    => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
            'accountNumber' => '/^((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?[A-Z\d]{5,})$/', // TP416630336    |    UAUBF32513
            'time'          => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
            'codeName'      => '/^(?<code>[A-Z]{3})\s+(?<name>.+)$/',
            'terminal'      => '/^[^:]+$/',
            'travellerName' => '[^\s\d]+[-.\'A-z ]*[A-z]', // Mr. Hao-Li Huang
            'totalPayment'  => '/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/',
        ];

        $f = $email->add()->flight();

        // reservationDate
        $dateStr = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Date / Time of the Reservation:')) . ']/following::text()[normalize-space(.)][1]');

        if (!empty($dateStr)) {
            $date = strtotime($this->normalizeDate($dateStr));

            if (!$date) {
                $f->general()->date2($dateStr);
            } else {
                $f->general()->date($date);
            }
        }

        $xpathFragment1 = '//text()[' . $this->eq($this->t('Booking reference:')) . ']';

        // status
        if ($st = $this->http->FindSingleNode($xpathFragment1 . '/preceding::text()[normalize-space(.)][1]', null, true, $this->t('statuses'))) {
            $f->general()->status($st);
        }

        // confirmationNumbers
        $confirmationCodeTitle = $this->http->FindSingleNode($xpathFragment1);
        $confirmationCode = $this->http->FindSingleNode($xpathFragment1 . '/following::text()[normalize-space(.)][1]', null, true, '/^(' . $patterns['confNumber'] . ')$/');

        if ($confirmationCode) {
            $f->general()->confirmation($confirmationCode, preg_replace('/\s*:$/', '', $confirmationCodeTitle));
        }

        // travellers
        // ticketNumbers
        // accountNumbers
        $passengerRows = $this->http->XPath->query('//text()[' . $this->eq($this->t('Passengers')) . ']/following::text()[normalize-space(.)][1][' . $this->eq($this->t('Passenger name')) . ']/ancestor::tr[ ./following-sibling::tr ][1]/following-sibling::tr[normalize-space(.)]');
//        $this->logger->alert('//text()[' . $this->eq($this->t('Passengers')) . ']/following::text()[normalize-space(.)][1][' . $this->eq($this->t('Passenger name')) . ']/ancestor::tr[ ./following-sibling::tr ][1]/following-sibling::tr[normalize-space(.)]');
        foreach ($passengerRows as $passengerRow) {
            if ($pax = $this->http->FindSingleNode('./*[1]', $passengerRow, true, '/^(' . $patterns['travellerName'] . ')$/u')) {
                $f->addTraveller($pax);
            }
            $ticketNumber = $this->http->FindSingleNode('./*[2]', $passengerRow, true, '/^(\d{8,})$/');

            if ($ticketNumber) {
                $f->addTicketNumber($ticketNumber, false);
            }
            $accountNumber = $this->http->FindSingleNode('./*[4]', $passengerRow, true, $patterns['accountNumber']);

            if (!$accountNumber) {
                $accountNumber = $this->http->FindSingleNode('./*[3]', $passengerRow, true, $patterns['accountNumber']);
            }

            if ($accountNumber) {
                $f->addAccountNumber($accountNumber, false);
            }
        }

        $segments = $this->http->XPath->query('//text()[' . $this->eq($this->t('Departure')) . ' and ./following::text()[normalize-space(.)][1][' . $this->eq($this->t('Arrival')) . '] ]/ancestor::tr[ ./following-sibling::tr and ./preceding-sibling::tr ][1]');

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode('./preceding-sibling::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)][last()]', $segment);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);
            }

            // depCode
            // depName
            $timeDep = '';
            $departure = $this->http->FindSingleNode('./preceding-sibling::tr[normalize-space(.)][1]/descendant::td[not(.//td) and string-length(normalize-space(.))>6][1]', $segment);

            if (preg_match('/^(?<time>' . $patterns['time'] . ')\s*(?<airport>.+)/', $departure, $matches)) {
                $timeDep = $matches['time'];

                if (preg_match($patterns['codeName'], $matches['airport'], $m)) {
                    $s->departure()
                        ->code($m['code'])
                        ->name($m['name']);
                } else {
                    $s->departure()
                        ->name($matches['airport'])
                        ->noCode();
                }
            }

            // arrCode
            // arrName
            $timeArr = '';
            $arrival = $this->http->FindSingleNode('./preceding-sibling::tr[normalize-space(.)][1]/descendant::td[not(.//td) and string-length(normalize-space(.))>6][2]', $segment);

            if (preg_match('/^(?<time>' . $patterns['time'] . ')\s*(?<airport>.+)/', $arrival, $matches)) {
                $timeArr = $matches['time'];

                if (preg_match($patterns['codeName'], $matches['airport'], $m)) {
                    $s->arrival()
                        ->code($m['code'])
                        ->name($m['name']);
                } else {
                    $s->arrival()
                        ->name($matches['airport'])
                        ->noCode();
                }
            }

            // seats
            if ($s->getDepName() && $s->getArrName()) {
                $seats = [];
                $seatRows = $this->http->XPath->query('//text()[' . $this->eq($this->t('Seats')) . ']/ancestor::tr[ ./following-sibling::tr ][1][ ./descendant::text()[' . $this->eq($this->t('Passenger name')) . '] ]/following-sibling::tr/descendant::tr[not(.//tr) and ' . $this->eq($s->getDepName() . ' ' . $s->getArrName()) . ']');

                foreach ($seatRows as $seatRow) {
                    $preRowsCount = $this->http->XPath->query('./preceding-sibling::tr[normalize-space(.)]', $seatRow)->length;
                    $seat = $this->http->FindSingleNode("./ancestor::td[ ./following-sibling::td ][1]/following-sibling::td[normalize-space(.)][1]/descendant::tr[not(.//tr)][{$preRowsCount}+1]", $seatRow, true, '/^\d+[A-Z]$/');

                    if ($seat) {
                        $seats[] = $seat;
                    }
                }

                if (count($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            // depDate
            $dateDep = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][1]/descendant::td[' . $this->eq($this->t('Date')) . '][1]/following-sibling::td[normalize-space(.)][1]', $segment);

            if ($f->getReservationDate() && $timeDep && $dateDep) {
                $dateDepNormal = $this->normalizeDate($dateDep);

                if ($dateDepNormal) {
                    $dateDep = EmailDateHelper::parseDateRelative($dateDepNormal . ' ' . $this->year . ', ' . $timeDep, $f->getReservationDate());
                    $s->departure()->date(strtotime($timeDep, $dateDep));
                }
            }

            // arrDate
            $dateArr = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][1]/descendant::td[' . $this->eq($this->t('Date')) . '][2]/following-sibling::td[normalize-space(.)][1]', $segment);

            if ($f->getReservationDate() && $timeArr && $dateArr) {
                $dateArrNormal = $this->normalizeDate($dateArr);

                if ($dateArrNormal) {
                    $dateArr = EmailDateHelper::parseDateRelative($dateArrNormal . ' ' . $this->year . ', ' . $timeArr, $f->getReservationDate());
                    $s->arrival()->date(strtotime($timeArr, $dateArr));
                }
            }

            // depTerminal
            $terminalDep = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][1]/descendant::td[' . $this->eq($this->t('Terminal')) . '][1]/following-sibling::td[1]', $segment, true, $patterns['terminal']);
            $s->departure()->terminal($terminalDep, false, true);

            // arrTerminal
            $terminalArr = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][1]/descendant::td[' . $this->eq($this->t('Terminal')) . '][2]/following-sibling::td[1]', $segment, true, $patterns['terminal']);
            $s->arrival()->terminal($terminalArr, false, true);

            // operatedBy
            $s->airline()->operator($this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][2]/descendant::td[' . $this->eq($this->t('Flight operated by')) . ']/following-sibling::td[normalize-space(.)][1]', $segment));

            // aircraft
            $s->extra()->aircraft($this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][2]/descendant::td[' . $this->eq($this->t('Aircraft')) . ']/following-sibling::td[normalize-space(.)][1]', $segment));

            //stop
            $stop = $this->http->FindSingleNode('./following-sibling::tr[' . $this->starts($this->t('Stop')) . '][1]', $segment);

            if (!empty($stop)) {
                $s->extra()->stops('1');
            }
        }

        $xpathFragmentPrice = '//text()[' . $this->eq($this->t('Price Breakdown')) . ']';

        $xpathFragmentTotal = $xpathFragmentPrice . '/preceding::text()[' . $this->eq($this->t('Total amount for all passengers')) . ']/ancestor::td[ ./following-sibling::td ][1]/following-sibling::td[normalize-space(.)][1]/descendant::tr[not(.//tr) and normalize-space(.)]';

        // p.total
        // p.currencyCode
        if ( // 2140.24 USD    |    3033,24 EUR
            preg_match($patterns['totalPayment'], $this->http->FindSingleNode($xpathFragmentTotal . '[2]'), $matches) // it-17902505.eml
            || preg_match($patterns['totalPayment'], $this->http->FindSingleNode($xpathFragmentTotal), $matches)
        ) {
            $f->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);

            // p.fees
            $allPricesCurrency = $this->http->FindSingleNode($xpathFragmentPrice . '/following::td[not(.//td) and ' . $this->starts($this->t('All prices in this page are in')) . ']', null, true, '/\s+([A-Z]{3})$/');

            if ($allPricesCurrency && $allPricesCurrency === $matches['currency']) {
                $feeRows = $this->http->XPath->query($xpathFragmentPrice . '/following::text()[' . $this->eq($this->t('feeHeaders')) . ']/ancestor::tr[1]/following-sibling::tr[ ./td[2][normalize-space(.)] ]');

                foreach ($feeRows as $feeRow) {
                    $feeName = $this->http->FindSingleNode('./td[1]', $feeRow);
                    $feeCharge = $this->http->FindSingleNode('./td[2]', $feeRow, true, '/^(\d[,.\'\d]*)$/');

                    if ($feeName && $feeCharge) {
                        $f->price()->fee($feeName, $this->normalizeAmount($feeCharge));
                    }
                }
            }
        }

        // p.spentAwards
        $miles = $this->http->FindSingleNode($xpathFragmentPrice . '/following::text()[' . $this->eq($this->t('Total amount for all passengers')) . ']/ancestor::td[ ./following-sibling::td ][1]/following-sibling::td[normalize-space(.)][1]/descendant::tr[not(.//tr) and ' . $this->contains($this->t('Miles')) . ']', null, true, '/.*\d\s*' . $this->opt($this->t('Miles')) . '.*/i');

        if ($miles) {
            $f->price()->spentAwards($miles);
        }
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})\s+([^\d\W]{3,})\.?$/u', $string, $matches)) { // 28 Feb
            $day = $matches[1];
            $month = $matches[2];
            $year = '';
        } elseif (preg_match('/^(\d{1,2})\s+([^\d\W]{3,})\.?\s+(\d{4}, \d+:\d+(?:\s*[ap]m)?).*$/ui', $string, $matches)) { // 28 Feb
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
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

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $string): string
    {
        $string = preg_replace('/\s+/', '', $string);             // 11 507.00  ->  11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00   ->  18800.00

        return $string;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
