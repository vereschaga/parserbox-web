<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountCheckerExtended
{
    public $mailFiles = "tapportugal/it-11060095.eml, tapportugal/it-11147003.eml, tapportugal/it-11156252.eml, tapportugal/it-1788265.eml, tapportugal/it-1788287.eml, tapportugal/it-1882779.eml, tapportugal/it-1882831.eml, tapportugal/it-1920890.eml, tapportugal/it-2249796.eml, tapportugal/it-2966961.eml, tapportugal/it-3231475.eml, tapportugal/it-3231478.eml, tapportugal/it-4141110.eml, tapportugal/it-4219057.eml, tapportugal/it-4240662.eml, tapportugal/it-4259510.eml, tapportugal/it-4286266.eml, tapportugal/it-4632148.eml, tapportugal/it-5832882.eml, tapportugal/it-5879591.eml";

    public $from = 'tapportugal';
    public $subject = ['Booking - ', 'Réservation - ', 'Reserva - ', 'Reserva efetuada - ', 'Reservationsbekræftelse - ', 'Buchung - ', 'Prenotazione - ', 'Bokningsbekräftelse - ', 'Reservasjonsbekreftelse - ', 'Boeking - '];

    public $body = [
        "en" => ['Thank you for booking with TAP Portugal'],
        "pt" => ['Obrigado por preferir a TAP Portugal'],
        "fr" => ['Nous vous remercions d’avoir choisi TAP Portugal'],
        "es" => ['Gracias por hacer su reserva con TAP Portugal'],
        "da" => ['Tak for Deres reservation hos TAP Portugal'],
        "de" => ['Für weitere Informationen über Tarifbedingungen kontaktieren Sie bitte'],
        "sv" => ['För ytterligare information om dessa prisvillkor'],
        "it" => ['Se vuole sapere più informazioni sulle condizioni di prezzo si prega di contattare la'],
        "no" => ['Dersom du vil ha mer informasjon om prisvilkårene, kan du ta kontakt med'],
        "nl" => ['Voor meer informatie over de tariefvoorwaarden kunt u contact opnemen met'],
    ];

    public $lang;
    public $date;

    private static $dictionary = [
        'en' => [
            'Flight reference' => ['Flight reference', 'Booking Reference'],
            //'Date / Time of the Reservation' => '',
            //'Booking Details' => '',
            //'Title' => '',
            //'First name' => '',
            //'Ticket Number' => '',
            //'Victoria miles' => '',
            'AccountNumber' => 'FFP Number',
            //'Flight Information' => '',
            //'Departure' => '',
            //'Arrival' => '',
            //'Terminal' => '',
            //'Fare' => '',
            //'Total Amount:' => '',
            //'Total per Passenger Type' => '',
            //'Number of passengers' => '',
            //'Total Amount' => '',
            //'Note' => '',
            //'Miles' => '',
            //'Discount' => '',
        ],

        'fr' => [
            'Flight reference'               => ['Référence du vol', 'Référence de réservation'],
            'Date / Time of the Reservation' => 'Date / heure de Réservation',
            'Booking Details'                => 'Détails de la Réservation',
            'Title'                          => 'Titre',
            'First name'                     => 'Prénom',
            'Ticket Number'                  => 'Numéro du billet ',
            'Victoria miles'                 => 'Miles Victoria',
            'AccountNumber'                  => 'Numéro FFP',
            'Flight Information'             => 'Itinéraire',
            'Departure'                      => 'Départ',
            'Arrival'                        => 'Arrivée',
            'Terminal'                       => 'Terminal',
            'Price Information'              => 'Tarif',
            'Fare'                           => 'Tarif HT',
            'Total Amount:'                  => 'Tarif TTC:',
            'Total per Passenger Type'       => 'Tarif TTC par Profil de Passager',
            'Number of passengers'           => 'Nombre de passagers',
            'Total Amount'                   => 'Tarif TTC',
            'Note'                           => 'Note',
            //'Miles' => '',
            //'Discount' => '',
        ],

        'pt' => [
            'Flight reference'               => ['Referência do voo', 'Código de Reserva'],
            'Date / Time of the Reservation' => 'Data/Hora da Reserva',
            'Booking Details'                => 'Detalhes da Viagem',
            'Title'                          => 'Título',
            'First name'                     => 'Primeiro Nome',
            'Ticket Number'                  => 'Número de Bilhete',
            'Victoria miles'                 => 'Milhas Victoria',
            'AccountNumber'                  => 'Número FFP',
            'Flight Information'             => 'Informação sobre o(s) voo(s)',
            'Departure'                      => 'Partida',
            'Arrival'                        => 'Chegada',
            'Terminal'                       => 'Terminal',
            'Price Information'              => 'Detalhe de Pagamento',
            'Fare'                           => 'Tarifa',
            'Total Amount:'                  => 'Total:',
            'Total per Passenger Type'       => 'Total por Tipo de Passageiro',
            'Number of passengers'           => 'Número de Passageiros',
            'Total Amount'                   => 'Total',
            'Note'                           => 'Note',
            'Miles'                          => 'Milhas',
            //'Discount' => '',
        ],

        'da' => [
            'Flight reference'               => 'Reservationsbekræftelse:',
            'Date / Time of the Reservation' => 'Dato / Tidspunkt for reservation',
            'Booking Details'                => 'Reservationsdetaljer',
            'Title'                          => 'Titel',
            'First name'                     => 'Fornavn',
            'Ticket Number'                  => 'Billetnummer',
            'Victoria miles'                 => 'Victoria mil',
            'AccountNumber'                  => 'FFP nummer',
            'Flight Information'             => 'Flyinformation',
            'Departure'                      => 'Afgang',
            'Arrival'                        => 'Ankomst',
            'Terminal'                       => ['terminal', 'Terminal'],
            'Price Information'              => 'Prisinformation',
            'Fare'                           => 'Takst',
            'Total Amount:'                  => 'Beløb i alt:',
            'Total per Passenger Type'       => 'I alt pr. passagertype',
            'Number of passengers'           => 'Antal passagerer',
            'Total Amount'                   => 'Beløb i alt',
            'Note'                           => 'Bemærk',
            //'Miles' => '',
            //'Discount' => '',
        ],

        'es' => [
            'Flight reference'               => 'Referencia del vuelo',
            'Date / Time of the Reservation' => 'Fecha / hora de la reserva',
            'Booking Details'                => 'Datos de la reserva',
            'Title'                          => 'Título',
            'First name'                     => 'Nombre',
            'Ticket Number'                  => 'Número de billete',
            'Victoria miles'                 => 'Millas Victoria',
            //'AccountNumber' => '',
            'Flight Information'       => 'Información del vuelo',
            'Departure'                => 'Salida',
            'Arrival'                  => 'Llegada',
            'Terminal'                 => 'terminal',
            'Price Information'        => 'Información sobre los precios',
            'Fare'                     => 'Tarifa',
            'Total Amount:'            => 'Importe Total:',
            'Total per Passenger Type' => 'Total por tipo de pasajero',
            'Number of passengers'     => 'Número de pasajeros',
            'Total Amount'             => 'Importe Total',
            'Note'                     => 'Nota',
            //'Miles' => '',
            //'Discount' => '',
        ],

        'de' => [
            'Flight reference'               => 'Buchungsreferenz',
            'Date / Time of the Reservation' => 'Datum/Uhrzeit der Reservierung',
            'Booking Details'                => 'Buchungsdetails',
            'Title'                          => 'Anrede',
            'First name'                     => 'Vorname',
            'Ticket Number'                  => 'Ticketnummer',
            'Victoria miles'                 => 'Victoria Meilen',
            //'AccountNumber' => '',
            'Flight Information'       => 'Flugdaten',
            'Departure'                => 'Abflug',
            'Arrival'                  => 'Ankunft',
            'Terminal'                 => 'Terminal',
            'Price Information'        => 'Preisinformationen',
            'Fare'                     => 'Tarif',
            'Total Amount:'            => 'Gesamtbetrag:',
            'Total per Passenger Type' => 'Gesamtbetrag pro Passagier Typ',
            'Number of passengers'     => 'Anzahl der Passagiere',
            'Total Amount'             => 'Gesamtbetrag',
            'Note'                     => 'Achtung',
            //'Miles' => '',
            'Discount' => 'Congress',
        ],

        'sv' => [
            'Flight reference'               => 'Bokningsreferens',
            'Date / Time of the Reservation' => 'Datum / Tid för Reservation',
            'Booking Details'                => 'Bokningsdetaljer',
            'Title'                          => 'Titel',
            'First name'                     => 'Förnamn',
            'Ticket Number'                  => 'Biljettnummer',
            'Victoria miles'                 => 'Victoria-miles',
            //'AccountNumber' => '',
            'Flight Information'       => 'Flightinformation',
            'Departure'                => 'Avgång',
            'Arrival'                  => 'Ankomst',
            'Terminal'                 => 'terminal',
            'Price Information'        => 'Prisinformation',
            'Fare'                     => 'Pris',
            'Total Amount:'            => 'Totalt belopp:',
            'Total per Passenger Type' => 'Totalt per passagerartyp',
            'Number of passengers'     => 'Antal passagerare',
            'Total Amount'             => 'Bokningsdetaljer',
            //'Note' => '',
            //'Miles' => '',
            //'Discount' => '',
        ],

        'it' => [
            'Flight reference'               => 'Codice della Prenotazione',
            'Date / Time of the Reservation' => 'Data/Ora della prenotazione',
            'Booking Details'                => 'Condizioni di prenotazione',
            'Title'                          => 'Titolo',
            'First name'                     => 'Nome',
            'Ticket Number'                  => 'Nº Biglietto',
            'Victoria miles'                 => 'Miglia Victoria',
            //'AccountNumber' => '',
            'Flight Information'       => 'Informazione Sui Voli Prenotati',
            'Departure'                => 'Partenza',
            'Arrival'                  => 'Arrivo',
            'Terminal'                 => 'Terminal',
            'Price Information'        => 'Informazione sul prezzo',
            'Fare'                     => 'Tariffa',
            'Total Amount:'            => 'Importo Complessivo:',
            'Total per Passenger Type' => 'Totale per Passeggero',
            'Number of passengers'     => 'Numero Passeggeri',
            'Total Amount'             => 'Importo Complessivo',
            'Note'                     => 'Nota',
            //'Miles' => '',
            //'Discount' => '',
        ],

        'no' => [
            'Flight reference'               => 'Reservasjonsreferanse',
            'Date / Time of the Reservation' => 'Dato / Tid for Reservasjonen',
            'Booking Details'                => 'Reservasjonsdetaljer',
            'Title'                          => 'Tittel',
            'First name'                     => 'Fornavn',
            'Ticket Number'                  => 'Billettnummer',
            'Victoria miles'                 => 'Victoria-miles',
            //'AccountNumber' => '',
            'Flight Information'       => 'Opplysninger om flyavgangen',
            'Departure'                => 'Avreise',
            'Arrival'                  => 'Ankomst',
            'Terminal'                 => 'Terminal',
            'Price Information'        => 'Prisinformasjon',
            'Fare'                     => 'Billettkostnad',
            'Total Amount:'            => 'Totalbeløp:',
            'Total per Passenger Type' => 'Totalbeløp per Passasjertype',
            'Number of passengers'     => 'Antall passasjerer',
            'Total Amount'             => 'Totalbeløp',
            'Note'                     => 'Merk',
            //'Miles' => '',
            //'Discount' => '',
        ],

        'nl' => [
            'Flight reference'               => 'Reserveringsnummer',
            'Date / Time of the Reservation' => 'Reserveringsdatum en tijd',
            'Booking Details'                => 'Boeking details',
            'Title'                          => 'Titel',
            'First name'                     => 'Voornaam',
            'Ticket Number'                  => 'Ticketnummer ',
            'Victoria miles'                 => 'Victoria mijlen',
            //'AccountNumber' => '',
            'Flight Information'       => 'Vlucht informatie',
            'Departure'                => 'Vertrek',
            'Arrival'                  => 'Aankomst',
            'Terminal'                 => 'Terminal',
            'Price Information'        => 'Prijs Informatie',
            'Fare'                     => 'Het tarief',
            'Total Amount:'            => 'Totaal Bedrag:',
            'Total per Passenger Type' => 'Totaal per passagier',
            'Number of passengers'     => 'Aantal passagiers',
            'Total Amount'             => 'Totaal Bedrag',
            'Note'                     => 'Nota',
            //'Miles' => '',
            //'Discount' => '',
        ],
    ];

    public function parseFlight(Email $email)
    {
        $flight = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Flight reference'))}]/following::text()[normalize-space()][1]", null, true, "/([A-Z\d]{6})/");
        $confDesc = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Flight reference'))}]");

        if (!empty($conf)) {
            $flight->general()
                ->confirmation($conf, $confDesc);
        }

        $dateReserv = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Date / Time of the Reservation'))}]", null, true, "/\:\s(.+)\s+GMT/");

        if (!empty($dateReserv)) {
            $flight->general()
                ->date($this->normalizeDate($dateReserv));
        }

        $travellers = $this->http->FindNodes("//text()[{$this->starts($this->t('Booking Details'))}]/following::tr[{$this->starts($this->t('Title'))} and {$this->contains($this->t('First name'))}][1]/following-sibling::tr", null, "/^\s*(\D+)/");

        if (count($travellers) > 0) {
            $flight->general()
                ->travellers($travellers, true);
        }

        $tickets = $this->http->FindNodes("//text()[{$this->starts($this->t('Booking Details'))}]/following::tr[{$this->starts($this->t('Title'))} and {$this->contains($this->t('First name'))} and {$this->contains($this->t('Ticket Number'))}][1]/following-sibling::tr/descendant::td[4]");

        if (count($tickets) > 0) {
            $flight->issued()->tickets($tickets, false);
        }

        $miles = $this->http->FindNodes("//text()[{$this->starts($this->t('Booking Details'))}]/following::tr[{$this->starts($this->t('Title'))} and {$this->contains($this->t('First name'))} and {$this->contains($this->t('Victoria miles'))}][1]/following-sibling::tr/descendant::td[5]", null, "/^(\d+)$/");
        $earnedAwards = 0;

        foreach ($miles as $mile) {
            $earnedAwards += $mile;
            $flight->setEarnedAwards($earnedAwards);
        }

        $accountNumbers = $this->http->FindNodes("//text()[{$this->starts($this->t('Booking Details'))}]/following::tr[{$this->starts($this->t('Title'))} and {$this->contains($this->t('First name'))} and {$this->contains($this->t('AccountNumber'))}][1]/following-sibling::tr/descendant::td[last()]");

        if (count($accountNumbers) > 0) {
            $flight->ota()->accounts($accountNumbers, false);
        }

        $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Price Information'))}]/following::text()[{$this->starts($this->t('Fare'))}]/ancestor::tr[1]/descendant::td[3]");

        if (!empty($cost)) {
            $flight->price()
                ->cost($this->correctSum($cost));
        }

        $discount = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Price Information'))}]/following::text()[{$this->starts($this->t('Discount'))}]/ancestor::tr[1]/descendant::td[3]", null, true, "/([\d\.\,]+)/");

        if (!empty($discount)) {
            $flight->price()
                ->discount($this->correctSum($discount));
        }

        $xpath = "//text()[{$this->starts($this->t('Price Information'))}]/following::text()[{$this->starts($this->t('Fare'))}][1]/ancestor::tr[1]/following-sibling::tr[normalize-space()][not({$this->contains($this->t('Total per Passenger Type'))} or {$this->contains($this->t('Number of passengers'))} or {$this->contains($this->t('Total Amount'))} or {$this->contains($this->t('Note'))} or {$this->contains($this->t('Miles'))} or {$this->contains($this->t('Discount'))})]";

        if ($this->http->XPath->query($xpath)->count() > 0) {
            $node = $this->http->XPath->query($xpath);

            foreach ($node as $root) {
                $feeName = $this->http->FindSingleNode("./descendant::td[1]", $root);
                $feeSum = $this->http->FindSingleNode("./descendant::td[3]", $root);

                $flight->price()
                    ->fee($feeName, $this->correctSum($feeSum));
            }
        }

        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Price Information'))}]/following::text()[{$this->starts($this->t('Total Amount:'))}][1]", null, true, "/{$this->opt($this->t('Total Amount:'))}(?:\s*\d+\s*{$this->opt($this->t('Miles'))}\s*\+)?\s*([\d\.\,]+)\s*[A-Z]{3}/");
        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Price Information'))}]/following::text()[{$this->starts($this->t('Fare'))}]/ancestor::tr[1]/descendant::td[2]");

        if (!empty($total)) {
            $flight->price()
                ->total($this->correctSum($total))
                ->currency($currency);
        }

        $spentAwards = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Price Information'))}]/following::text()[{$this->starts($this->t('Miles'))}][1]/ancestor::tr[1]/descendant::td[3]");

        if (!empty($spentAwards)) {
            $flight->price()
                ->spentAwards($spentAwards);
        }

        $xpath = "//text()[{$this->starts($this->t('Flight Information'))}]/following::table[1]/descendant::tr[1]/following-sibling::tr";
        $node = $this->http->XPath->query($xpath);

        foreach ($node as $root) {
            if (empty($this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root, true, "/^([A-Z\d]{6})$/"))) {
                continue;
            }

            $seg = $flight->addSegment();

            $seg->airline()
                ->name($this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root, true, "/^([A-Z\d]{2})/"))
                ->number($this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root, true, "/^[A-Z\d]{2}(\d{2,4})$/"));

            $seg->departure()
                ->name($this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $root))
                ->noCode()
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::td[normalize-space()][4]", $root)));

            $terminalText = $this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root);

            if (preg_match("/^{$this->opt($this->t('Departure'))}\D+{$this->opt($this->t('Terminal'))}\s+(.+)\.\s*{$this->opt($this->t('Arrival'))}/", $terminalText, $m)) {
                $seg->departure()
                    ->terminal($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('Arrival'))}\D+{$this->opt($this->t('Terminal'))}\s+(.+)\./", $terminalText, $m)) {
                $seg->arrival()
                    ->terminal($m[1]);
            }

            $seg->arrival()
                ->name($this->http->FindSingleNode("./descendant::td[normalize-space()][3]", $root))
                ->noCode()
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::td[normalize-space()][5]", $root)));

            $seg->extra()
                ->cabin($this->http->FindSingleNode("./descendant::td[normalize-space()][6]", $root, true, "/^(\D+)\(/"))
                ->bookingCode($this->http->FindSingleNode("./descendant::td[normalize-space()][6]", $root, true, "/^\D+\((.+)\)/"));
        }

        return true;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('Booking' . ucfirst($this->lang));
        $this->parseFlight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (0 < $this->http->XPath->query("//*[contains(text(), 'TAP Portugal')  or contains(text(), 'TAP PORTUGAL')]")->count());

        return $this->assignLang();
    }

    private function assignLang()
    {
        foreach ($this->body as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function correctSum($str)
    {
        $str = preg_replace('/\s+/', '', $str);			// 11 507.00	->	11507.00
        $str = preg_replace('/[,.](\d{3})/', '$1', $str);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $str = preg_replace('/,(\d{2})$/', '.$1', $str);	// 18800,00		->	18800.00

        return $str;
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+) ([^\s\d.,]+)[.,]* (\d+)h(\d+)$#", //30 mars, 18h15
            "#^(\d+)\s+(\w+)\.\s+(\d{4}\,\s[\d\:]+)$#", //18 janv. 2018, 13:02
        ];
        $out = [
            "$1 $2 $year, $3:$4",
            "$1 $2 $3",
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
