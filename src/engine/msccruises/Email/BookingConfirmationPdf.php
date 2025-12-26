<?php

namespace AwardWallet\Engine\msccruises\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Cruise;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "msccruises/it-153731887-pt.eml, msccruises/it-155094570.eml, msccruises/it-262417833-pt.eml, msccruises/it-265162861-it.eml, msccruises/it-267315596.eml, msccruises/it-267491008-nl.eml, msccruises/it-270364993-fr.eml, msccruises/it-350509990.eml, msccruises/it-702202905.eml, msccruises/it-756458967.eml, msccruises/it-758407644.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            'CRUISE TICKET'    => ['TICKET DO CRUZEIRO'],
            'confNumber'       => ['Número da Reserva', 'Número da reserva'],
            'Booking status'   => ['Status da reserva', 'Estado da Reserva'],
            'statusVariants'   => ['Agendado'],
            'Booking Date'     => 'Data da Reserva',
            'Ship'             => 'Navio',
            'Guest(s)'         => ['Hóspede', 'Hóspede(s)'],
            'Date of Birth'    => 'Data de Nascimento',
            'embarkationDate'  => 'Data de Embarque',
            'Stateroom'        => 'Cabine',
            // 'Category'         => '',
            'Day'              => 'Dia',
            'Port'             => 'Porto',
            'Arrival'          => 'Chegada',
            'departure'        => ['Saída', 'Partida'],
            'AT SEA'           => 'Navegando',
            'Deck'             => 'Andar',
            'totalAmount'      => 'Total devido em',
            'Payment Details'  => 'Detalhes dos Pagamentos',
            'Final Amount'     => 'Montante Final',
            // short format
            'Card Number'         => ['Número do cartão', 'Número do cartão MSC Club'],
            'Currency'            => 'Moeda',
            'Disembarkation Date' => 'Data de Desembarque',
            'Embarkation port'    => 'Porto de Embarque',
            'Disembarkation Port' => 'Porto de Desembarque',
        ],
        'it' => [
            // 'CRUISE TICKET' => '',
            'confNumber'       => ['Numero prenotazione'],
            'Booking status'   => 'Stato Prenotazione',
            'statusVariants'   => ['Confermato'],
            'Booking Date'     => 'Data di prenotazione',
            'Ship'             => 'Nave',
            'Guest(s)'         => 'Cliente/i',
            // 'Date of Birth' => '',
            'embarkationDate'  => 'Data di imbarco',
            'Stateroom'        => ['Numero di cabina', 'Numero di'],
            // 'Category'         => '',
            'Day'              => 'Giorno',
            'Port'             => 'Porto',
            'Arrival'          => 'Arr.',
            'departure'        => 'Part.',
            'AT SEA'           => 'Navigazione',
            // 'Deck'             => '',
            'totalAmount'      => 'Totale importo dovuto',
            'Payment Details'  => 'Dettaglio Pagamenti',
            // 'Final Amount' => '',
            // short format
            // 'Card Number' => ['', ' MSC Club'],
            // 'Currency' => '',
            // 'Disembarkation Date' => '',
            // 'Embarkation port' => '',
            // 'Disembarkation Port' => '',
        ],
        'nl' => [
            // 'CRUISE TICKET' => '',
            'confNumber'       => ['Reservatie', 'Boekingsnr.'],
            'Booking status'   => 'Status boeking',
            'statusVariants'   => ['Confirmed', 'Conﬁrmed'],
            // 'Booking Date' => '',
            'Ship'             => 'Schip',
            'Guest(s)'         => 'Klant(en)',
            // 'Date of Birth' => '',
            'embarkationDate'  => 'Inschepingsdatum',
            'Stateroom'        => 'Kajuit',
            // 'Category'         => '',
            'Day'              => 'Dag',
            'Port'             => 'Haven',
            'Arrival'          => 'Aank.',
            'departure'        => 'Vertr.',
            'AT SEA'           => 'OP ZEE',
            // 'Deck'             => '',
            'totalAmount'      => 'Totaal bedrag van de boeking verschuldig op',
            'Payment Details'  => 'Betalingsinformatie',
            'Final Amount'     => 'Eindsaldo',
            // short format
            'Card Number'         => ['Kaartnummer', 'Kaartnummer MSC Club'],
            'Currency'            => ['PRIJZEN', 'Valuta'],
            'Disembarkation Date' => 'Ontschepingsdatum',
            'Embarkation port'    => 'Inschepingshaven',
            'Disembarkation Port' => 'Ontschepingshaven',
        ],
        'fr' => [
            // 'CRUISE TICKET' => '',
            'confNumber'       => ['Numéro de réservation'],
            'Booking status'   => ['Statut de la réservation', 'Statut de la réservation`'],
            'statusVariants'   => ['Réservé'],
            'Booking Date'     => 'Date de réservation',
            'Ship'             => 'Navire',
            'Guest(s)'         => 'Client(s)',
            // 'Date of Birth' => '',
            'embarkationDate'  => "Date d'embarquement",
            'Stateroom'        => 'Cabine',
            // 'Category'         => '',
            'Day'              => 'Jour',
            // 'Port' => '',
            'Arrival'          => 'Arr.',
            'departure'        => 'Dép.',
            'AT SEA'           => 'EN MER',
            // 'Deck'             => '',
            'totalAmount'      => 'Montant total de la réservation en',
            'Payment Details'  => 'Détails de paiements',
            // 'Final Amount' => '',
            // short format
            'Card Number'      => ['Numéro de carte', 'Numéro de carte MSC Club'],
            // 'Currency' => '',
            // 'Disembarkation Date' => '',
            // 'Embarkation port' => '',
            // 'Disembarkation Port' => '',
        ],
        'de' => [
            // 'CRUISE TICKET' => '',
            'confNumber'       => ['Vorgangsnr'],
            'Booking status'   => 'Status',
            'statusVariants'   => ['Bestätigt'],
            'Booking Date'     => 'Vorgang erstellt am',
            'Ship'             => 'Schiff',
            'Guest(s)'         => 'Passagier/e',
            'Date of Birth'    => 'Geb',
            'embarkationDate'  => 'Abfahrtsdatum',
            'Stateroom'        => 'Kabine (vorläufig)',
            // 'Category'         => '',
            'Day'              => 'Tag',
            'Port'             => 'Hafen',
            'Arrival'          => 'Ankunft',
            'departure'        => 'Abfahrt',
            'AT SEA'           => 'Auf See',
            // 'Deck'             => '',
            'totalAmount'      => 'Gesamtbetrag in',
            'Payment Details'  => 'Zahlungsdetails',
            // 'Final Amount' => '',
            // short format
            // 'Card Number' => ['', ' MSC Club'],
            // 'Currency' => '',
            // 'Disembarkation Date' => '',
            // 'Embarkation port' => '',
            // 'Disembarkation Port' => '',
        ],
        'es' => [
            // 'CRUISE TICKET' => '',
            'confNumber'       => ['Número de Reserva', 'Número de Reserva / Presupuesto'],
            'Booking status'   => 'Estado de la reserva',
            'statusVariants'   => ['Estado de la reserva'],
            'Booking Date'     => 'Fecha de Reserva',
            'Ship'             => 'Barco',
            'Guest(s)'         => ['Pasajeros', 'Huéspedes'],
            'Date of Birth'    => ['DOB', 'fecha de nacimiento'],
            'embarkationDate'  => 'Fecha de Embarque',
            'Stateroom'        => 'Número de cabina',
            'Category'         => ['Categoría', 'Categoria'],
            'Day'              => 'Dia',
            'Port'             => 'Puerto',
            'Arrival'          => 'Llegada',
            'departure'        => 'Salida',
            'AT SEA'           => 'En Navegacion',
            // // 'Deck'             => '',
            'totalAmount'      => ['Importe total en'],
            'Payment Details'  => 'Detalles de Pago',
            'Final Amount'     => 'Cantidad Total',
            // short format
            // 'Card Number' => [''],
            'Currency'            => 'Fecha de Desembarque',
            'Disembarkation Date' => 'Fecha de Desembarque',
            'Embarkation port'    => 'Puerto de embarque',
            'Disembarkation Port' => 'Fecha de Desembarque',
        ],
        'en' => [ // always last!
            'confNumber'      => ['Booking number', 'Booking Number'],
            'statusVariants'  => ['Confirmed', 'Conﬁrmed'],
            'embarkationDate' => ['Embarkation Date'],
            'Stateroom'       => ['Stateroom', 'Cabin'],
            'departure'       => ['Departure', 'Departur'],
            'totalAmount'     => ['Total Booking amount due in', 'Total Booking amount'],
            // short format
            'Card Number' => ['Card Number', 'Card Number MSC Club'],
            // 'Currency' => '',
            // 'Disembarkation Date' => '',
            // 'Embarkation port' => '',
            // 'Disembarkation Port' => '',
        ],
    ];

    private $subjects = [
        'pt' => ['Confirmação de reserva Para'],
        'it' => ['Numero di prenotazione'],
        // 'nl' => [''],
        'fr' => ['Confirmation de réservation pour'],
        'de' => ['Buchungsbestätigung_ für'],
        'en' => ['Booking Confirmation For'],
        'es' => ['Confirmacion de tu reserva For'],
    ];

    private $enDatesInverted = null;

    private $patterns = [
        'date'          => '\d{1,2}\/\d{1,2}\/\d{2}', // 06/28/22
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'timeBlank'     => '[-−]{1,2}[ ]{0,2}[:]+[ ]{0,2}[-−]{1,2}', // --:--    |    -:-
        'travellerName' => '[[:alpha:]][-,.\'’[:alpha:]\s]*?[[:alpha:]]', // PIERANUNZI, Louis, JOHN
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@msccrociere.it') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = $this->detectEmailFromProvider($parser->getHeader('from')) === true;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($detectProvider === false && stripos($textPdf, 'w.msccruisesusa.com') === false && stripos($textPdf, 'w.msccruzeiros.com') === false && stripos($textPdf, 'www.msccruises.be') === false
                && strpos($textPdf, 'MSC Crociere S.p.A') === false
                && stripos($textPdf, 'w.msccruises.de') === false && stripos($textPdf, 'w.mscvoyagersclub.de') === false && stripos($textPdf, 'www.msccruises.com.au') === false  // de
                && strpos($textPdf, 'Membro do MSC Club:') === false && strpos($textPdf, 'Membro do MSC Club :') === false // pt
                && strpos($textPdf, 'MSC Club Member:') === false && strpos($textPdf, 'MSC Club Member :') === false && strpos($textPdf, 'Silver Member') === false// en
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf || !$this->assignLang($textPdf)) {
                continue;
            }

            if (in_array($this->lang, ['pt', 'it', 'nl', 'fr', 'de'])) {
                $this->enDatesInverted = true;
            } else {
                $this->enDatesInverted = false;
            }

            $this->patterns['segHeaders'] = "[ ]*{$this->opt($this->t('Day'))}[* ]+{$this->opt($this->t('Port'))}[* ]+{$this->opt($this->t('Arrival'))}[* ]+{$this->opt($this->t('departure'))}[* ]*";

            $segmentsText = $this->re("/\n{$this->patterns['segHeaders']}\n+([\s\S]+?)\n\n/", $textPdf);

            if (count(explode("\n", $segmentsText)) < 5) {
                $segmentsText = $this->re("/\n{$this->patterns['segHeaders']}\n+([\s\S]+?\n)\n\n/", $textPdf);
            }

            if ($segmentsText) {
                $this->parsePdf($email, $textPdf, $segmentsText);
            } else {
                $this->parsePdfShort($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('BookingConfirmationPdf' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parsePdf(Email $email, $text, ?string $segmentsText): void
    {
        $this->logger->debug(__METHOD__);

        $cr = $email->add()->cruise();

        // General
        $bookingDetails1 = $this->re("/^([\s\S]+?)\n+[ ]*{$this->opt($this->t('Guest(s)'))}(?:[ ]{2}|\n)/", $text);

        if (preg_match("/(?:^[ ]*|[ ]{2})({$this->opt($this->t('confNumber'))})[ ]*[:]+[ ]*([-A-Z\d]{5,})(?:[ ]{2}|$)/m", $bookingDetails1, $m)
        || preg_match("/^({$this->opt($this->t('confNumber'))})[\s\:]*([-A-Z\d]{5,})\n/m", $text, $m)) {
            $cr->general()->confirmation($m[2], $m[1]);
        }
        $status = $this->re("/^[ ]*{$this->opt($this->t('Booking status'))}[ ]*[:]+[ ]*({$this->opt($this->t('statusVariants'))})(?:[ ]{2}|$)/im", $bookingDetails1);

        if ($status) {
            $cr->general()->status($status);
        }

        $bookingDateVal = $this->re("/^[ ]*{$this->opt($this->t('Booking Date'))}[: ]{1,6}(\b.{3,}?)(?:[ ]{2}|$)/im", $bookingDetails1);

        if (preg_match('/\b(\d{1,2})\s*\/\s*\d{1,2}\s*\/\s*\d{4}\b/', $bookingDateVal, $m) && (int) $m[1] > 12) {
            $this->enDatesInverted = true;
        }

        $bookingDate = strtotime($this->normalizeDate($bookingDateVal));

        if ($bookingDate) {
            $cr->general()->date($bookingDate);
        }

        // Details
        if (preg_match("/^[ ]*{$this->opt($this->t('Ship'))}[: ]*(?<name>.+?)(?:[ ]{2}|\n|$)/im", $text, $match)) {
            $cr->setShip($match['name'], false, true);
        }

        $bookingDetails2 = $this->re("/\n[ ]*{$this->opt($this->t('Guest(s)'))}(?:[ ]{2}.+)?\n+([\s\S]+?)\n+{$this->patterns['segHeaders']}/", $text);

        $tablePos = [0];
        $pos2 = [];

        if (preg_match("/^(.{2,} ){$this->opt($this->t('embarkationDate'))}(?:.+|$)/m", $bookingDetails2, $matches)) {
            $pos2[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.{2,} ){$this->opt($this->t('Stateroom'))}(?:.+|$)/m", $bookingDetails2, $matches)) {
            $pos2[] = mb_strlen($matches[1]);
        }

        if (count($pos2) > 0) {
            sort($pos2);
            $tablePos[] = $pos2[0];
        }
        $table = $this->splitCols($bookingDetails2, $tablePos);

        if (count($table) === 2
            && preg_match_all("/^[ ]*({$this->patterns['travellerName']})\s*\(\s*(?:DOB|(?i){$this->opt($this->t('Date of Birth'))})/mu", $table[0], $travellerMatches)
        ) {
            $cr->general()->travellers(preg_replace('/\s+/', ' ', $travellerMatches[1]), true);
        } else {
            $paxText = $this->re("/^\s*{$this->opt($this->t('CRUISE TICKET'))}\n+((?:.+\n+){1,15}){$this->opt($this->t('Ship'))}/", $text);
            $paxText = preg_replace("/(?:\-Silver\s*Member|\-Welcome\s*Member|\-Classic\s*Member)/", "", $paxText);
            $paxText = preg_replace("/ *{$this->opt($this->t('Guest(s)'))}( +|\n)/", "", $paxText);

            if (!empty($paxText)) {
                $travellers = array_filter(explode("\n", $paxText));
                $cr->general()
                    ->travellers($travellers);
            }
        }

        $cr->details()
            ->room($this->re("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Stateroom'))}[: ]+(\d+)(?:[ ]{2}|[ ]+[-−]|$)/imu", $bookingDetails2), false, true)
            ->roomClass($this->re("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Category'))}[: ]+(.+)(?:[ ]{2}|[ ]+[-−]|$)/imu", $bookingDetails2), false, true)
            ->deck($this->re("/\n *{$this->opt($this->t('Deck'))}\s*(\d+)\s*{$this->opt($this->t('Stateroom'))}/", $text), true, true)
        ;

        if (preg_match_all('/\b(\d{1,2})\s*\/\s*\d{1,2}\s*\/\s*\d{2,4}\b/', $segmentsText, $dateMatches)) {
            foreach ($dateMatches[1] as $simpleDate) {
                if ($simpleDate > 12) {
                    $this->enDatesInverted = true;

                    break;
                }
            }
        }

        if (preg_match("/\n(.+ +(?:\d{1,2}:\d{2}.*?|--:--) +(\d{1,2}:\d{2}.*?|--:--))/u", $segmentsText, $m)) {
            $segmentsText = preg_replace("/^(.{" . (mb_strlen($m[1])) . ",}?) {5,}\S.+/m", '$1', $segmentsText);
            $segmentsText = preg_replace("/^ +$/m", "\n", $segmentsText);
            $segmentsText = preg_replace("/\n\n\n[\s\S]+/", '', $segmentsText);
        }

        $segments = $this->splitText($segmentsText, "/^([ ]*[[:alpha:]][-−[:alpha:]]+[ ]+{$this->patterns['date']}.*)$/mu", true);
        // $this->logger->debug('$segments = '.print_r( $segments,true));

        foreach ($segments as $sText) {
            if (!preg_match("/^(?<beforePort> *[[:alpha:]][-−[:alpha:]]+[ ]+(?<date>{$this->patterns['date']}))\s+(?<portName>.{2,}?)[ ]+(?<time1>{$this->patterns['time']}|{$this->patterns['timeBlank']})[ ]+(?<time2>{$this->patterns['time']}|{$this->patterns['timeBlank']})\s*(?:\n+(?<portName2>[ ]*\S[\s\S]*))?\s*$/u", $sText, $m)) {
                $this->logger->debug('Segments is wrong!');
                $cr->addSegment();

                break;
            }
            $pos = mb_strlen($m['beforePort']);

            if (!empty($m['portName2']) && $pos > 5) {
                $pos = $pos - 3;
                $m['portName2'] = preg_replace("/^[ [:alpha:]\-]{0,{$pos}}(?: {3,}|$)/mu", '', $m['portName2']);
            }
            $m['portName2'] = isset($m['portName2']) ? trim($m['portName2']) : '';

            if (preg_match("/^{$this->opt($this->t('AT SEA'))}\b/", $m['portName'])
                || preg_match("/{$this->patterns['timeBlank']}/u", $m['time1']) && preg_match("/{$this->patterns['timeBlank']}/u", $m['time2'])
            ) {
                continue;
            }

            $s = $cr->addSegment();

            $date = strtotime($this->normalizeDate($m['date']));

            if (!empty($m['portName2'])) {
                $m['portName'] .= ' ' . preg_replace('/\s+/', ' ', $m['portName2']);
            }

            $s->setName($m['portName']);

            if ($date && $m['time1'] && !preg_match("/{$this->patterns['timeBlank']}/u", $m['time1'])) {
                $s->setAshore(strtotime($m['time1'], $date));
            }

            if ($date && $m['time2'] && !preg_match("/{$this->patterns['timeBlank']}/u", $m['time2'])) {
                $s->setAboard(strtotime($m['time2'], $date));
            }
        }

        $this->parsePrice($cr, $text);
    }

    private function parsePdfShort(Email $email, $text): void
    {
        $this->logger->debug(__METHOD__);
        // examples: it-267315596.eml, it-262417833-pt.eml

        $cr = $email->add()->cruise();

        if (preg_match_all("/(?:^[ ]*|[ ]{2})({$this->opt($this->t('confNumber'))})[ ]*[:]+[ ]*([-A-Z\d]{5,})(?:[ ]{2}|$)/m", $text, $confNoMatches)
            && count(array_unique($confNoMatches[2])) === 1
        ) {
            $cr->general()->confirmation($confNoMatches[2][0], $confNoMatches[1][0]);
        }

        if (preg_match_all("/^[ ]*{$this->opt($this->t('Guest(s)'))}[: ]{1,6}((?:[ ]*\S.+\n+){1,20})[ ]*{$this->opt($this->t('Ship'))}/im", $text, $guestsMatches)) {
            $guestsText = implode("\n", $guestsMatches[1]);
        } else {
            $guestsText = null;
        }

        if (preg_match_all("/^[ ]*({$this->patterns['travellerName']})\s*\(\s*(?:DOB|(?i){$this->opt($this->t('Date of Birth'))})/mu", $guestsText, $travellerMatches)) {
            $cr->general()->travellers(array_unique(preg_replace('/\s+/', ' ', $travellerMatches[1])), true);
        }

        if (preg_match_all("/{$this->opt($this->t('Card Number'))}[:\s]+(\d{5,})$/m", $guestsText, $accountMatches)) {
            $cr->program()->accounts(array_unique($accountMatches[1]), false);
        }

        if (preg_match_all("/^[ ]*{$this->opt($this->t('Ship'))}[: ]{1,50}(\S.+?)(?:[ ]{2}|$)/im", $text, $shipMatches)
            && count(array_unique($shipMatches[1])) === 1
        ) {
            $cr->setShip($shipMatches[1][0]);
        }

        if (preg_match_all("/[ ]{2}{$this->opt($this->t('Booking status'))}[ ]*[:]+[ ]*(.{2,}?)\n/i", $text, $statusMatches)
            && count(array_unique($statusMatches[1])) === 1
            && preg_match("/^{$this->opt($this->t('statusVariants'))}$/i", $statusMatches[1][0])
        ) {
            $cr->general()->status($statusMatches[1][0]);
        }

        if (preg_match_all("/[ ]{2}{$this->opt($this->t('Stateroom'))}[: ]+(\d+)(?:[ ]+[-−]|\n)/iu", $text, $roomMatches)
            && count(array_unique($roomMatches[1])) === 1
        ) {
            $cr->setRoom($roomMatches[1][0]);
        }

        if (preg_match_all("/^[ ]*{$this->opt($this->t('Booking Date'))}[: ]{1,50}(\b.{3,}?)(?:[ ]{2}|$)/im", $text, $bookingDateMatches)
            && count(array_unique($bookingDateMatches[1])) === 1
        ) {
            if (preg_match('/\b(\d{1,2})\s*\/\s*\d{1,2}\s*\/\s*\d{4}\b/', $bookingDateMatches[1][0], $m) && (int) $m[1] > 12) {
                $this->enDatesInverted = true;
            }

            $bookingDate = strtotime($this->normalizeDate($bookingDateMatches[1][0]));

            if ($bookingDate) {
                $cr->general()->date($bookingDate);
            }
        }

        if (preg_match_all("/^[ ]*{$this->opt($this->t('embarkationDate'))}[: ]{1,50}(\b.{3,}?)(?:[ ]{2}|$)/im", $text, $embarkationDateMatches)
            && count(array_unique($embarkationDateMatches[1])) === 1
            && preg_match_all("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Disembarkation Date'))}[: ]{1,50}(\b.{3,}?)(?:[ ]{2}|$)/im", $text, $disembarkationDateMatches)
            && count(array_unique($disembarkationDateMatches[1])) === 1
            && preg_match_all("/[ ]{2}{$this->opt($this->t('Embarkation port'))}[: ]{1,50}(\S.{2,})\n/im", $text, $embarkationPortMatches)
            && count(array_unique($embarkationPortMatches[1])) === 1
            && preg_match_all("/[ ]{2}{$this->opt($this->t('Disembarkation Port'))}[: ]{1,50}(\S.{2,})\n/im", $text, $disembarkationPortMatches)
            && count(array_unique($disembarkationPortMatches[1])) === 1
        ) {
            if (preg_match_all('/\b(\d{1,2})\s*\/\s*\d{1,2}\s*\/\s*\d{4}\b/', $embarkationDateMatches[1][0] . "\n" . $disembarkationDateMatches[1][0], $m)
                && max($m[1]) > 12
            ) {
                $this->enDatesInverted = true;
            }

            $s = $cr->addSegment();

            $embarkationDate = strtotime($this->normalizeDate($embarkationDateMatches[1][0]));
            $disembarkationDate = strtotime($this->normalizeDate($disembarkationDateMatches[1][0]));

            if ($embarkationDate && $embarkationDate === $disembarkationDate) {
                $disembarkationDate = strtotime('23:59', $disembarkationDate);
            }

            $s->setAboard($embarkationDate)->setName($embarkationPortMatches[1][0]);

            $s = $cr->addSegment();

            $s->setAshore($disembarkationDate)->setName($disembarkationPortMatches[1][0]);
        }

        $this->parsePrice($cr, $text);
    }

    private function parsePrice(Cruise $cr, $text): void
    {
        if (preg_match("/^[ ]*{$this->opt($this->t('totalAmount'))}[: ]{1,6}(?:(?<currency>[A-Z]{3})[* ]+)?.*?\b(?<amount>\d[,.‘\'\d]*)$/mu", $text, $matches)) {
            // USD*        12620.72
            if (empty($matches['currency'])
                && preg_match("/^[ ]*{$this->opt($this->t('totalAmount'))}[: ]{1,6}.+\n+[ ]*(?<currency>[A-Z]{3})[* ]*$/mu", $text, $m)
            ) {
                $matches['currency'] = $m['currency'];
            } elseif (empty($matches['currency'])) {
                $matches['currency'] = $this->re("/{$this->opt($this->t('Currency'))}\s*([A-Z]{3})\s+/", $text);
            }
            $currencyCode = !empty($matches['currency']) && preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $cr->price()->currency($currencyCode)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            return;
        }

        if (preg_match_all("/[ ]{2}{$this->opt($this->t('Currency'))}[: ]+([^\-\n\d)(]+)\n/i", $text, $roomMatches)
            && count(array_unique($roomMatches[1])) === 1
        ) {
            $generalCurrency = $roomMatches[1][0];
        } else {
            $generalCurrency = null;
        }

        $paymentDetailsText = $this->re("/\n[ ]*{$this->opt($this->t('Payment Details'))}\n+([\s\S]+)/", $text);

        if (preg_match("/\n[ ]*{$this->opt($this->t('Final Amount'))}[ ]{2}.*[ ]{2}(?<amount>\d[,.‘\'\d]*)$/m", $paymentDetailsText, $matches)) {
            $currencyCode = !empty($generalCurrency) && preg_match('/^[A-Z]{3}$/', $generalCurrency) ? $generalCurrency : null;
            $cr->price()->currency($generalCurrency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['embarkationDate'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['confNumber']) !== false
                && $this->strposArray($text, $phrases['embarkationDate']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

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

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 06/28/22
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/',
        ];
        $out[0] = $this->enDatesInverted === true ? '$2/$1/$3' : '$1/$2/$3';

        return preg_replace($in, $out, $text);
    }
}
