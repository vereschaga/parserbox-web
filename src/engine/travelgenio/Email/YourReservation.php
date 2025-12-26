<?php

namespace AwardWallet\Engine\travelgenio\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "travelgenio/it-111596979.eml, travelgenio/it-114348922.eml, travelgenio/it-12218841.eml, travelgenio/it-134079534.eml, travelgenio/it-141471729.eml, travelgenio/it-198615239.eml, travelgenio/it-45663424.eml, travelgenio/it-47092599.eml, travelgenio/it-71178502.eml";

    public $lang = '';
    public $currentDate;

    public static $dictionary = [
        'it' => [
            'confNumber'        => ['Richiesta di acquisto su Travelgenio'],
            'depart'            => ['Partenza'],
            'arrival'           => ['Arrivo'],
            'state'             => ['Stato'],
            'cabin'             => ['Classe'],
            'passenger#'        => ['Passeggero'],
            'passengerName'     => ['Nome:'],
            'passengerLastName' => ['Cognome:'],
            'wayPay'            => ['Modalità di pagamento'],
            'total'             => ['Importo totale:'],
            'FlightNumber'      => ['Numéro de vol:'],
            'duration'          => ['Durata del viaggio di andata:'],
        ],
        'fr' => [
            'confNumber'        => ['Voici votre numéro de réservation :'],
            'depart'            => ['Départ'],
            'arrival'           => ['Arrivée'],
            'state'             => ['Etat'],
            'cabin'             => ['Classe'],
            'passenger#'        => ['Passager'],
            'passengerName'     => ['NOM :'],
            'passengerLastName' => ['PRÉNOM :'],
            'wayPay'            => ['Mode de paiement'],
            'total'             => ['Montant total:'],
            'FlightNumber'      => ['Numéro de vol:'],
            //'duration' => ['Duração da viagem de regresso:', 'Duração da viagem de ida:'],
        ],
        'pt' => [
            'confNumber'        => ['Solicitação de compra em'],
            'depart'            => ['Partida'],
            'arrival'           => ['Chegada'],
            'state'             => ['Estado'],
            'cabin'             => ['Cabine'],
            'passenger#'        => ['Passageiro'],
            'passengerName'     => ['Nome:'],
            'passengerLastName' => ['Sobrenome:'],
            //'wayPay'            => [''],
            'total'             => ['Montante total:'],
            //'FlightNumber'      => [''],
            'duration' => ['Duração da viagem de regresso:', 'Duração da viagem de ida:'],
        ],
        'da' => [
            'confNumber'        => ['Din reservationskode er'],
            'depart'            => ['Afrejse', 'Afgår'],
            'arrival'           => ['Ankomst', 'Ankommer'],
            'state'             => ['Status'],
            'cabin'             => ['Kabineklasse'],
            'passenger#'        => ['Passager'],
            'passengerName'     => ['Fornavn:'],
            'passengerLastName' => ['Surname:'],
            'wayPay'            => ['Køber data'],
            'total'             => ['Det samlede beløb:'],
            'FlightNumber'      => ['Flynummer'],
        ],
        'es' => [
            'confNumber'        => ['El localizador de su reserva es', 'El localizador de solicitud de reserva es', 'Su localizador es:'],
            'depart'            => ['Salida'],
            'arrival'           => ['Llegada'],
            'state'             => ['Estado'],
            'cabin'             => ['Cabina'],
            'passenger#'        => ['Pasajero #'],
            'passengerName'     => ['Nombre:'],
            'passengerLastName' => ['Apellido:'],
            'wayPay'            => ['Forma de pago'],
            'total'             => ['Importe Total:'],
            'account'           => ['Atención al cliente Preferente'],
        ],
        'de' => [
            'confNumber'        => ['Der Buchungscode für Ihre Reservierung lautet'],
            'depart'            => ['Abflug'],
            'arrival'           => ['Ankunft'],
            'state'             => ['Estado', 'Status'],
            'cabin'             => ['Klasse', 'Kabinenklasse'],
            'passenger#'        => ['Passagier #'],
            'passengerName'     => ['Name:'],
            'passengerLastName' => ['Nachname:'],
            'wayPay'            => ['Zahlungsmodalität'],
            'total'             => ['Gesamtbetrag:'],
            'Change necessary'  => ['Umsteigen notwendig'],
        ],
        'en' => [
            'confNumber'        => ['Your Booking ID is', 'Your booking code is', 'Purchase request in Travelgenio', 'Booking ID in Travelgenio:'],
            'depart'            => ['Departure'],
            'arrival'           => ['Arrival'],
            'state'             => ['Status'],
            'cabin'             => ['Cabin Class'],
            'passenger#'        => ['Passenger'],
            'passengerName'     => ['Name:'],
            'passengerLastName' => ['Surname:'],
            'wayPay'            => ['Payment information'],
            'total'             => ['Total amount:'],
        ],
    ];
    private $providerCode = '';

    private $subjects = [
        'fr' => ['Votre réservation avec Travelgenio'],
        'pt' => ['Sua reserva na Travelgenio'],
        'da' => ['Din reservation med Travelgenio er bekræftet'],
        'es' => ['Solicitud de compra confirmada'],
        'de' => ['Ihre Buchung bei Travelgenio'],
        'en' => ['booking with Travelgenio'],
        'it' => ['La sua prenotazione su Travelgenio'],
    ];

    private $detectors = [
        'it' => ['La sua prenotazione su', 'Il pagamento con la carta'],
        'fr' => ['Votre réservation sur', 'Le paiement des billets a été effectué avec'],
        'pt' => ['Sua reserva na', 'Solicitação de compra em'],
        'da' => ['Din reservation med', 'Tillykke! Din reservation er bekræftet'],
        'es' => ['Su reserva en', 'Gracias por reservar en'],
        'de' => ['Ihre Buchung bei', 'Vielen Dank für Ihre Buchung bei'],
        'en' => ['Your booking with', 'Thank you for booking with'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Travelgenio - Do Not Reply') !== false
            || preg_match('/[-.@]travelgenio\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (
            stripos($headers['from'], 'travelgenio.com') === false
            && stripos($headers['from'], 'travel2be.com') === false
            && stripos($headers['from'], 'tripmonster.com') === false
        ) {
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
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseFlight($email);
        $email->setType('YourReservation' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

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

    public static function getEmailProviders()
    {
        return ['traveltobe', 'travelgenio', 'tripmonster'];
    }

    private function parseFlight(Email $email): void
    {
        $requestIdText = implode("\n", $this->http->FindNodes("//tr[not(.//tr) and {$this->starts($this->t('Solicitud de compra en Travelgenio'))}]/descendant::text()[normalize-space()]"));

        if (preg_match("/^({$this->opt($this->t('Solicitud de compra en Travelgenio'))})[:\s]+(\d{5,})(?:\s*[,.:;!?]|$)/", $requestIdText, $m)) {
            // it-71178502.eml
            $email->ota()->confirmation($m[2], $m[1]);
        }

        $f = $email->add()->flight();

        $account = $this->http->FindSingleNode("//text()[{$this->starts($this->t('account'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t(''))}\s*([A-Z\d]{6})\s*$/su");

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{6,}$/');
        $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/ancestor::tr[1]", null, true, '/\s*([A-Z\d]{6,})\.?(?:\s+\D+)?$/u');
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)\s+is/');
        }

        if ($confirmation) {
            $f->general()->confirmation($confirmation, $confirmationTitle);
        } elseif ($this->http->XPath->query("//*[{$this->contains($this->t('En las próximas horas recibirá un nuevo email con los billetes y el código de reserva de su vuelo'))}]")->length > 0) {
            // it-71178502.eml
            $f->general()->noConfirmation();
        }

        $xpathArrival = "following-sibling::*[ descendant::text()[normalize-space()][1][{$this->starts($this->t('arrival'))}] ]";
        $segments = $this->http->XPath->query("//*[descendant::text()[normalize-space()][1][{$this->starts($this->t('depart'))}] and {$xpathArrival}]");

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Change necessary'))}]")->length > 0) {
            $xpathArrival = "following-sibling::*[ descendant::text()[normalize-space()][1][{$this->eq($this->t('arrival'))}] ]";
            $segments = $this->http->XPath->query("//*[descendant::text()[normalize-space()][1][{$this->eq($this->t('depart'))}] and {$xpathArrival}]");
        }

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            /*
                Salida
                Martes, 1 De Octubre De 2019 | 18:20H
                Rodriguez Ballon (AQP), Arequipa, Peru
                LATAM Airlines (LA2090)
             */
            $patterns['point'] = '/'
                . '^[ ]*(?<date>.{6,})[ ]+\|[ ]+(?<time>\d{1,2}:\d{2})[Hh]?[ ]*$'
                . '\s+^[ ]*(?<name>.{3,}?)[ ]*\([ ]*(?<code>[A-Z]{3})[ ]*\)(?:[ ]*,+[ ]*(?<region>.{3,}?))?[ ]*$'
                . '(?:\s+^[ ]*.*\([ ]*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<flightNumber>\d+)[ ]*\).*[ ]*$)?'
                . '/m';

            $patterns['point2'] = '/'
                . '^\D+\s(?<code>[A-Z]{3})\s*(?<time>[\d\:]+)\s*$'
                . '/u';

            $departureHtml = $this->http->FindHTMLByXpath('.', null, $segment);
            $departure = $this->htmlToText($departureHtml);

            if (preg_match($patterns['point'], $departure, $m)) {
                $dateDepNormal = $this->normalizeDate($m['date']);

                if ($dateDepNormal) {
                    $this->currentDate = $dateDepNormal;
                    $s->departure()->date2($dateDepNormal . ' ' . $m['time']);
                }
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'] . (empty($m['region']) ? '' : ', ' . $m['region']));

                if (!empty($m['airline'])) {
                    $s->airline()
                        ->name($m['airline'])
                        ->number($m['flightNumber']);
                }
            } elseif (preg_match($patterns['point2'], $departure, $m)) {
                if ($this->currentDate) {
                    $s->departure()->date(strtotime($this->currentDate . ' ' . $m['time']));
                }
                $s->departure()
                    ->code($m['code']);

                $airlineText = $this->http->FindSingleNode("./following::text()[{$this->contains($this->t('FlightNumber'))}][1]", $segment);

                if (preg_match("/^(?<operator>\D+)\s*\-\s*{$this->opt($this->t('FlightNumber'))}\:?\s*(?<airline>[A-Z\d]{2})\s*(?<flightNumber>\d{2,4})\s*\-\s*(?<aircraft>.+)$/", $airlineText, $m)) {
                    $s->airline()
                        ->name($m['airline'])
                        ->number($m['flightNumber'])
                        ->operator($m['operator']);

                    $s->extra()
                        ->aircraft($m['aircraft']);
                }
            }

            $arrivalHtml = $this->http->FindHTMLByXpath($xpathArrival . '[1]', null, $segment);
            $arrival = $this->htmlToText($arrivalHtml);

            if (preg_match($patterns['point'], $arrival, $m)) {
                $dateArrNormal = $this->normalizeDate($m['date']);

                if ($dateArrNormal) {
                    $s->arrival()->date2($dateArrNormal . ' ' . $m['time']);
                }
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'] . (empty($m['region']) ? '' : ', ' . $m['region']));
            } elseif (preg_match($patterns['point2'], $arrival, $m)) {
                if ($this->currentDate) {
                    $s->arrival()->date(strtotime($this->currentDate . ' ' . $m['time']));
                }
                $s->arrival()
                    ->code($m['code']);
            }

            $xpathExtra = "following-sibling::*[position()<4][self::table]";
            $status = $this->http->FindSingleNode($xpathExtra . "/descendant::td[{$this->starts($this->t('state'))}]", $segment, true, "/{$this->opt($this->t('state'))}\s*(.{2,})/");
            $cabin = $this->http->FindSingleNode($xpathExtra . "/descendant::td[{$this->starts($this->t('cabin'))}]", $segment, true, "/{$this->opt($this->t('cabin'))}\s*(.{2,})/");
            $duration = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[{$this->starts($this->t('duration'))}]", $segment, true, "/{$this->opt($this->t('duration'))}\s*(.{2,})/");

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $s->extra()
                ->status($status, false, true)
                ->cabin($cabin, false, true);
        }

        $passengerData = $this->http->XPath->query("//tr[{$this->starts($this->t('passenger#'), 'translate(normalize-space(),"0123456789","##########")')}]/ancestor::table[1]/descendant::td[{$this->eq($this->t('passengerName'))}]");

        foreach ($passengerData as $pData) {
            $pName = $this->http->FindSingleNode("following-sibling::*[normalize-space()][1]", $pData, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
            $pLastName = $this->http->FindSingleNode("ancestor::tr[1]/following-sibling::*[normalize-space()][1]/*[{$this->eq($this->t('passengerLastName'))}]/following-sibling::*[normalize-space()][1]", $pData, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

            if ($pName && $pLastName) {
                $f->addTraveller($pName . ' ' . $pLastName, true);
            } elseif ($pName) {
                $f->addTraveller($pName, false);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('wayPay'))}]/following::td[{$this->eq($this->t('total'))}]/following-sibling::*[normalize-space()][1]");

        if (
            // 89,42 EUR
            preg_match('/^(?<amount>\d[,.\'\d]*) ?(?<currency>[A-Z]{3})\b/', $totalPrice, $m)
            // CHF671.02
            || preg_match('/^(?<currency>[A-Z]{3})\s?(?<amount>\d[,.\'\d]*)/', $totalPrice, $m)) {
            $f->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    $phrase = (array) $phrase;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignProvider($headers): bool
    {
        if (preg_match('/[-.@]travel2be\.com/i', $headers['from']) > 0
            || $this->http->XPath->query('//img[contains(@src,".travel2be.com/") and contains(@src,"/t2b-logo.")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Your booking with Travel2be") or contains(normalize-space(),"Thank you for booking with Travel2Be") or contains(normalize-space(),"Travel2be - Do Not Reply") or contains(.,"@mailer.travel2be.com")]')->length > 0
        ) {
            $this->providerCode = 'traveltobe';

            return true;
        }
        if (preg_match('/[-.@]tripmonster\.com/i', $headers['from']) > 0
            || $this->http->XPath->query('//img[contains(@src,".tripmonster.com/") and (contains(@src,"/logo.") or contains(@src,"/Ico_success.") or contains(@src,"/ico_flight_1."))]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Tripmonster") or contains(.,"@mailer.tripmonster.com")]')->length > 0
        ) {
            $this->providerCode = 'tripmonster';

            return true;
        }
        if (preg_match('/[-.@]travelgenio\.com/i', $headers['from']) > 0
            || $this->http->XPath->query('//img[contains(@src,".travelgenio.com/") and (contains(@src,"/logo.") or contains(@src,"/Ico_success.") or contains(@src,"/ico_flight_1."))]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Travelgenio - Do Not Reply") or contains(.,"@mailer.travelgenio.com")]')->length > 0
        ) {
            $this->providerCode = 'travelgenio';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['depart']) || empty($phrases['arrival'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['depart'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['arrival'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|bool
     */
    private function normalizeDate(string $text)
    {
        if (preg_match('/^\D+\,?\s*(\d{1,2})\.?(?:\s+De)?\s+([[:alpha:]]{3,})(?:\s+De)?\s+(\d{4})$/iu', $text, $m)) {
            // Martes, 1 De Octubre De 2019 - es
            // Montag, 16. September 2019 - de
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (preg_match('/^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})$/iu', $text, $m)) {
            // Wednesday, October 13, 2021
            $day = $m[2];
            $month = $m[1];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return $text;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
