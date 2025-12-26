<?php

namespace AwardWallet\Engine\riu\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class RequestRejected extends \TAccountChecker
{
    public $mailFiles = "riu/it-29965712.eml, riu/it-52586284.eml, riu/it-797808691.eml, riu/it-801233812.eml, riu/it-804854209.eml";

    public $reFrom = ["@riu.com"];
    public $reBody = [
        'es'  => ['Datos de la reserva', 'Habitaciones'],
        'es2' => ['Política de cancelación', 'adultos'],
        'es3' => ['Reserva', 'Noches:'],
        'pt'  => ['Dados da reserva', 'Quarto 1'],
        'en2' => ['Fiscal information RIU', 'Reservation information'],
        'en3' => ['RIU Tax info', 'Response to booking request'],
        'en4' => ['RIU Tax info', 'Request Rejected'],
        'en5' => ['Nights:', 'RESERVATION NUMBER'],
        'en6' => ['RIU Tax info', 'Proof of cancellation'],
        'en'  => ['Request Rejected', 'Reservation information'],
        'de'  => ['Steuerdaten RIU', 'Reservierungsdaten'],
        'fr'  => ['Données fiscales RIU', 'Données de la réservation'],
        'it'  => ['Dati fiscali RIU', 'Dati prenotazione'],
    ];
    public $reSubject = [
        'Request Rejected',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Proforma'                => ['Proforma', 'Proof of cancellation', 'RESERVATION NUMBER'],
            'Reservation information' => ['Reservation information', 'Reservation'],
            'adult'                   => ['adult', 'adults'],
            'children'                => ['children', 'child'],
            'Cancellation cost:'      => 'Total price (all taxes included):',
            'notConfirmed'            => ['Request Rejected', 'it has not been possible to confirm your request', 'Response to booking request'],
            'cancelled'               => ['Proof of cancellation', 'cancelled'],
            //            'Proforma' => '',
        ],
        'es' => [
            'Proforma'                => ['Proforma', 'Justificante cancelaciones', 'Justificante de cancelación', 'LOCALIZADOR'],
            'Reservation information' => ['Datos de la reserva', 'Reservation information', 'Reserva'],
            'Nights'                  => ['Noches', 'Nights'],
            'Guests'                  => ['Huéspedes', 'Guests'],
            'Board'                   => ['Régimen', 'Board'],
            'Rooms:'                  => ['Habitaciones:', 'Rooms:'],
            'notConfirmed'            => ['Solicitud rechazada'],
            'cancelled'               => ['Justificante cancelaciones', 'Justificante de cancelación'],
            'Room'                    => 'Habitación',
            'Guest information'       => ['Datos del cliente', 'Guest information'],
            'Address'                 => ['Dirección', 'Address'],
            'adult'                   => ['adulto', 'adultos', 'adult'],
            'children'                => ['children'],
            'Cancellation cost:'      => ['Importe total a pagar (impuestos incluidos):', 'Total price (all taxes included):', 'Cancellation cost:'],
            'Cancellation policy'     => ['Política de cancelación', 'Cancellation policy'],
        ],

        'pt' => [
            'Proforma'                => 'Pró-forma',
            'Reservation information' => 'Dados da reserva',
            'Nights'                  => 'Noites',
            'Guests'                  => 'Hóspedes',
            'Board'                   => 'Régimen',
            'Rooms:'                  => 'Quartos:',
            //            'notConfirmed' => ['', ''],
            //            'cancelled' => '',
            'Room'               => 'Quarto',
            //            'Guest information'  => '',
            'Address'            => 'Endereço',
            'adult'              => 'adultos',
            'children'           => 'crianças',
            'Cancellation cost:' => 'Total a pagar (Impostos incluídos):',
            //            'Cancellation policy' => ''
        ],
        'de' => [
            'Proforma'                => 'Pro-forma-Rechnung',
            'Reservation information' => 'Reservierungsdaten',
            'Nights'                  => 'Nächte',
            'Guests'                  => 'Gäste',
            'Board'                   => 'Verpflegung',
            'Rooms:'                  => 'Zimmer:',
            //            'notConfirmed' => ['', ''],
            //            'cancelled' => '',
            'Room'                => 'Zimmer',
            'Guest information'   => 'Kundendaten',
            'Address'             => 'Adresse',
            'adult'               => 'Erwachsener',
            'children'            => 'Kind',
            'Cancellation cost:'  => 'Zu zahlender Gesamtbetrag (Steuern inkl.):',
            'Cancellation policy' => 'Stornierungsbedingungen',
        ],
        'fr' => [
            'Proforma'                => 'NUMÉRO DE RESERVATION',
            'Reservation information' => 'Données de la réservation',
            'Nights'                  => 'Nuits',
            'Guests'                  => 'Clients',
            'Board'                   => 'Régime',
            'Rooms:'                  => 'Chambres:',
            //            'notConfirmed' => ['', ''],
            //            'cancelled' => '',
            'Room'               => 'Chambre',
            'Guest information'  => 'INFO FISCALES',
            'Address'            => 'Adresse',
            'adult'              => 'adulte',
            'children'           => 'enfants',
            //            'Cancellation cost:' => 'Zu zahlender Gesamtbetrag (Steuern inkl.):',
            //                        'Cancellation policy' => 'Stornierungsbedingungen'
        ],
        'it' => [
            'Proforma'                => ['Proforma', 'Ricevuta della cancellazione'],
            'Reservation information' => 'Dati prenotazione',
            'Nights'                  => 'Notti',
            'Guests'                  => 'Clienti',
            'Board'                   => 'Pensione',
            'Rooms:'                  => 'Camere:',
            //            'notConfirmed' => ['', ''],
            'cancelled'          => 'Ricevuta della cancellazione',
            'Room'               => 'Camera',
            'Guest information'  => 'Dati cliente',
            'Address'            => 'Indirizzo',
            'adult'              => 'adulti',
            'children'           => 'bambino',
            'Cancellation cost:' => 'Totale da pagare (imposte incluse):',
            //                        'Cancellation policy' => 'Stornierungsbedingungen'
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='RIU' or @alt='Image removed by sender. RIU' or contains(@src, 'www.riu.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], 'RIU') !== false
                ) {
                    return true;
                }
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

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function parseEmail(Email $email)
    {
        if (!empty($this->http->FindSingleNode("(//text()[{$this->contains($this->t('notConfirmed'))}])[1]"))) {
            $email->setIsJunk(true);

            return $email;
        }

        $h = $email->add()->hotel();

        $conf = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Proforma'))}]/following::text()[normalize-space()!=''][1])[1]",
            null, true, "/^\s*([A-Z\d]{5,})\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Proforma'))}])[1]",
                null, true, "/^\s*" . $this->opt($this->t('Proforma')) . "\s+([A-Z\d]{5,})\s*$/");
        }
        $h->general()
            ->confirmation($conf);

        if (!empty($this->http->FindSingleNode("(//text()[{$this->contains($this->t('cancelled'))}])[1]"))) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation information'))}]/following::text()[normalize-space()!=''][1][./following::text()[normalize-space()!=''][1][{$this->starts($this->t('Nights'))}]]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation information'))}]/following::text()[{$this->starts($this->t('Nights'))}]/ancestor::*[1]/preceding::*[1]/ancestor::*[1]");
        }
        $h->hotel()
            ->name(trim($hotelName, '*'))
            ->noAddress()
        ;

        $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Nights'))}]/following::text()[normalize-space()!=''][1][./following::text()[normalize-space()!=''][1][{$this->starts($this->t('Guests'))}]]");

        if (preg_match("/\((?:of|Del|Do|Vom|du|Da|from)\s+(.+?)\s+(?:to|al|ao|bis|au|fino a)\s+(.+?)\)/", $node, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]))
                ->checkOut($this->normalizeDate($m[2]));
        }

        $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Guests'))}]/following::text()[normalize-space()!=''][1][./following::text()[normalize-space()!=''][1][{$this->starts($this->t('Board'))}]]");

        $adult = $this->re("/(\d+)\s+{$this->opt($this->t('adult'))}/u", $node);
        $kids = $this->re("/(\d+)\s+{$this->opt($this->t('children'))}/u", $node);

        if (empty($adult) && empty($kids)) {
            if (preg_match("/^ein\s+{$this->opt($this->t('adult'))}/", $node)) {
                $adult = 1;
            }

            if (preg_match("/\+\s*ein\s+{$this->opt($this->t('children'))}/", $node)) {
                $kids = 1;
            }

            if (preg_match("/\+\s*(?:kein|não)\s+{$this->opt($this->t('children'))}/", $node)) {
                $kids = 0;
            }
        }

        if ($adult > 0) {
            $h->booked()
            ->guests($adult)
            ->kids($kids, true, true);
        }

        $roomNameCond = "starts-with(normalize-space(translate(.,'0123456789','dddddddddd')),'Room d') or starts-with(normalize-space(translate(.,'0123456789','dddddddddd')),'Roomd')";
        $roomRoot = $this->http->XPath->query("//text()[{$roomNameCond}]/ancestor::*[1]");

        if ($roomRoot->length % 2 !== 0) {
            $this->logger->debug('other format room description');

            return false;
        }

        $roomNode = $this->http->FindNodes("//text()[{$roomNameCond}]/ancestor::*[1]/descendant::text()[starts-with(normalize-space(translate(.,'0123456789','dddddddddd')),'Room d')]/following::text()[normalize-space()!=''][1]");

        if (count($roomNode) > 1 && ((count($roomNode) % 2) == 0)) {
            //$roomArray[0] - roomType; $roomArray[1] - travellers;
            $roomArray = array_chunk($roomNode, count($roomNode) / 2);
            $h->booked()
               ->rooms(count($roomArray[0]));

            foreach ($roomArray[0] as $i => $roomType) {
                $room = $h->addRoom();

                if (preg_match("/({$this->opt($this->t('adult'))}|{$this->opt($this->t('children'))})/", $node)) {
                    $roomType = $this->http->FindSingleNode("(//text()[{$roomNameCond}]/ancestor::*[1]/descendant::text()[{$roomNameCond}])[" . ($i + 1) . "]",
                        null, true, "/^\s*Room\s*\d+\s*:\s*(\S.+)/");

                    if (empty($roomType)) {
                        $roomType = $this->http->FindSingleNode("(//text()[{$roomNameCond}]/ancestor::*[1]/descendant::text()[normalize-space(translate(.,'0123456789','dddddddddd')) = 'Room d:' or normalize-space(translate(.,'0123456789','dddddddddd')) = 'Roomd:'])[" . ($i + 1) . "]/following::text()[normalize-space()][1]");
                    }
                }
                $room->setType($roomType);
            }
            $h->general()
               ->travellers(array_unique(array_map('trim', explode(',', implode(',', $roomArray[1])))));
        } else {
            $roomSegment = $this->http->FindNodes("//text()[{$this->eq($this->t('Reservation information'))}]/following::span[{$this->starts($this->t('Room'))}][not (" . $this->contains($this->t('adult')) . ")][not (" . $this->contains($this->t('Rooms:')) . ")]");

            if (count($roomSegment) > 0) {
                $h->booked()
                   ->rooms(count($roomSegment));
            }

            for ($i = 1; $i < count($roomSegment) + 1; $i++) {
                $room = $h->addRoom();
                $type = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rooms:'))}]/following::span[contains(normalize-space(), '" . $this->t('Room') . " " . $i . ":')][1]/ancestor::*[normalize-space()][1]/descendant::text()[normalize-space() = '" . $this->t('Room') . " " . $i . ":']/following::text()[1]");

                if (empty($type)) {
                    $type = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rooms:'))}]/following::span[contains(normalize-space(), '" . $this->t('Room') . $i . ":')][1]/ancestor::*[normalize-space()][1]/descendant::text()[normalize-space() = '" . $this->t('Room') . $i . ":']/following::text()[1]");
                }

                if (empty($type)) {
                    $type = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rooms:'))}]/following::span[contains(normalize-space(), '" . $this->t('Room') . " " . $i . ":')][1]/ancestor::*[normalize-space()][1]/descendant::text()[starts-with(normalize-space(), '" . $this->t('Room') . " " . $i . ":')]",
                        null, true, "/{$this->opt($this->t('Room'))}\s*\d:\s*(.+)/");
                }

                if (empty($type)) {
                    $type = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rooms:'))}]/following::span[contains(normalize-space(), '" . $this->t('Room') . $i . ":')][1]/ancestor::*[normalize-space()][1]/descendant::text()[starts-with(normalize-space(), '" . $this->t('Room') . $i . ":')]",
                        null, true, "/{$this->opt($this->t('Room'))}\s*\d:\s*(.+)/");
                }
                $room->setType($type);
                $travellersArray[] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation information'))}]/following::span[normalize-space()='" . $this->t('Room') . " " . $i . ":']/following::span[1]", null, true, '/([A-Za-z\s\,]+)/');
            }

            $travellers = array_map('trim', explode(',', implode(',', $travellersArray)));

            if (!empty($travellers)) {
                $h->general()
                   ->travellers(array_unique($travellers));
            } else {
                $h->general()
                   ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest information'))}]/following::text()[normalize-space()!=''][2][./following::text()[normalize-space()!=''][1][{$this->starts($this->t('Address'))}]]"));
            }
        }

        if ($cancel = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation policy'))}][1]", null, true, '/' . $this->opt($this->t('Cancellation policy')) . '[ ]*:[ ]*(.+)/')) {
            $h->general()
                ->cancellation($cancel);

            if (preg_match('/free cancellation until (\d{1,2}) days? prior to arrival \((\d{1,2}:\d{2}) local time/', $cancel, $m)
                || preg_match('/cancelación gratuita hasta (\d{1,2}) días? antes [(]de las (\d{1,2}:\d{2}) local en el hotel[)]/', $cancel, $m)
                || preg_match('/cancelación gratuita hasta (\d{1,2}) día? antes [(]de las (\d{1,2}:\d{2}) local en el hotel[)],/', $cancel, $m)
                || preg_match('/Stornierung kostenlos bis (\d{1,2}) Tags? vor (\d{1,2}:\d{2})Uhr \(Ortszeit im Hotel\),/', $cancel, $m)
            ) {
                $h->booked()
                    ->deadlineRelative($m[1] . ' days', $m[2]);
            }
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation cost:'))}]/following::*[1]", null, true, '/([0-9\,\.]+)/');
        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation cost:'))}]/following::*[1]", null, true, '/[0-9\,\.]+\s+(.+)/');

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation cost:'))}]/following::*[1]", null, true, '/[0-9\,\.]+(.+)/');
        }

        if (($total = $this->normalizePrice($price)) > 0 && !empty($currency)) {
            $h->price()
                ->total($total)
                ->currency($this->normalizeCurrency($currency));
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //15 december 2018
            '#^\s*(\d+)\s+(\w+)\s+(\d{4})\s*$#u',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            '$'   => ['$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
