<?php

namespace AwardWallet\Engine\zenhotels\Email;

use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmation extends \TAccountChecker
{
    public $mailFiles = "zenhotels/it-178287752.eml, zenhotels/it-180342653.eml, zenhotels/it-181671844.eml";
    public $subjects = [
        'Hotel booking confirmation',
        'Confirmação de reserva no hotel',
        'Hotel-Buchungsbestätigung',
        'Please note the change of booking number',
        // it
        'Conferma della prenotazione n°',
        // es
        'Confirmación de reserva de hotel:',
    ];

    public $lang = '';

    public $detectLang = [
        'pt' => ['Endereço'],
        'de' => ['Adresse'],
        'it' => ['Indirizzo'],
        'es' => ['Dirección'],
        'en' => ['Address'],
    ];

    public static $dictionary = [
        "en" => [
            //            'Print'           => '',
            //            'Address'             => '',
            //            'Telephone number'    => '',
            'Confirmation code' => ['Confirmation code', 'Booking no.'],
            //            'Room'                => '',
            //            'Check-in'            => '',
            //            'Check-out'           => '',
            //            'Hotel on map'    => '',
            //            'Booking details' => '',
            //            'Number of guests'    => '',
            //            'Guest names'         => '',
            //            'Cancellation policy' => '',
        ],

        "pt" => [
            'Print'               => 'Imprimir',
            'Address'             => 'Endereço',
            'Telephone number'    => 'Número de telefone',
            'Confirmation code'   => ['Código de confirmação', 'Reserva nº'],
            'Room'                => 'Quarto',
            'Check-in'            => 'Check-in',
            'Check-out'           => 'Check-out',
            'Hotel on map'        => 'Hotel no mapa',
            'Booking details'     => 'Detalhes da reserva',
            'Number of guests'    => 'Número de hóspedes',
            'Guest names'         => 'Nome do cliente',
            'Cancellation policy' => 'Política de Cancelamento',
        ],
        "de" => [
            'Print'               => 'Drucken',
            'Address'             => 'Adresse',
            'Telephone number'    => 'Telefonnummer',
            'Confirmation code'   => 'Bestätigungscode',
            'Room'                => 'Zimmer',
            'Check-in'            => 'Anreise',
            'Check-out'           => 'Abreise',
            'Hotel on map'        => 'Hotel auf der',
            'Booking details'     => 'Buchungsdetails',
            'Number of guests'    => 'Anzahl der Gäste',
            'Guest names'         => 'Name (Gast)',
            'Cancellation policy' => 'Stornierung',
        ],
        "it" => [
            'Print'               => 'Stampa',
            'Address'             => 'Indirizzo',
            'Telephone number'    => 'Numero di telefono',
            'Confirmation code'   => ['Prenotazione n°', 'Codice di conferma'],
            'Room'                => 'Camera',
            'Check-in'            => 'Check-in',
            'Check-out'           => 'Check-out',
            'Hotel on map'        => 'La struttura sulla mappa di',
            'Booking details'     => 'Dettagli della prenotazione',
            'Number of guests'    => 'Numero di ospiti',
            'Guest names'         => 'Nomi degli ospiti',
            'Cancellation policy' => 'Politica di cancellazione',
        ],
        "es" => [
            'Print'               => 'Imprimir',
            'Address'             => 'Dirección',
            'Telephone number'    => 'Número de teléfono',
            'Confirmation code'   => ['Reserva n.°'],
            'Room'                => 'Habitación',
            'Check-in'            => 'Check-in',
            'Check-out'           => 'Check-out',
            'Hotel on map'        => 'Hotel en el mapa',
            'Booking details'     => 'Detalles de la reserva',
            'Number of guests'    => 'Número de los huéspedes',
            'Guest names'         => 'Nombres de los huéspedes',
            'Cancellation policy' => 'Política de cancelaciones',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@news.zenhotels.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Zenhotels')]")->length > 0
        || $this->http->XPath->query("//text()[contains(normalize-space(), 'support@zenhotels.com')]")->length > 0) {
            $this->assignLang();

            return $this->http->XPath->query("//a[{$this->contains($this->t('Print'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking details'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Hotel on map'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]news\.zenhotels\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Confirmation code'))}])[1]/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^([A-Z\d\-\/]+)$/"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Guest names'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]/descendant::text()[normalize-space()]"), true)
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation policy'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]"));

        $this->detectDeadLine($h);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/preceding::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]"));

        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation code'))}]/preceding::text()[{$this->eq($this->t('Telephone number'))}][1]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $h->booked()
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of guests'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]"))
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/following::text()[{$this->eq($this->t('Check-in'))}][1]/ancestor::tr[1]/descendant::td[normalize-space()][2]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/following::text()[{$this->eq($this->t('Check-out'))}][1]/ancestor::tr[1]/descendant::td[normalize-space()][2]")));

        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^\D+\,\s*(\d+\s*\D+\s*\d{4})\D+([\d\:]+)$#u", //Thu, 25 August 2022 from 12:00
        ];
        $out = [
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/You may cancel your reservation without charge until\s*(\d+\s*\w+\s*\d{4}\s*[\d\:]+)\*/", $cancellationText, $m)
        || preg_match("/Pode cancelar a sua reserva sem custos até (\d+\s*\w+\s*\d{4}\s*[\d\:]+)\*/", $cancellationText, $m)
        || preg_match("/Sie können Ihre Buchung gebührenfrei stornieren vor dem (\d+\s*\w+\s*\d{4}\s*[\d\:]+)*/", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m[1]));
        }

        if (preg_match("/Full reservation cost is charged upon cancellation\./", $cancellationText, $m)
        || preg_match("/O custo total da reserva é cobrado após o cancelamento\./", $cancellationText, $m)
        || preg_match("/Der volle Reservierungspreis wird bei Stornierung belastet./", $cancellationText, $m)) {
            $h->booked()
                ->nonRefundable();
        }
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
