<?php

namespace AwardWallet\Engine\traveluro\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Voucher extends \TAccountChecker
{
    public $mailFiles = "traveluro/it-640219407.eml, traveluro/it-642974641-de.eml, traveluro/it-648661008-es.eml, traveluro/it-742626686-fr.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Trip Number:' => 'Trip Number:',
            'Full Name:'   => 'Full Name:',
            // 'Check-in:' => '',
            // 'Check-out:' => '',
            // 'Rooms:' => '',
            // 'Guests:' => '',
            // 'Room' => '',
            // 'Adult' => '',
            // 'Child' => '',
            // 'Cancellation Policy:' => '',
            // 'Hotel Details' => '',
            // 'View in map' => '',
        ],
        'de' => [
            'Trip Number:'         => 'Reisenummer :',
            'Full Name:'           => 'Vollständiger Name :',
            'Check-in:'            => 'Check-In:',
            'Check-out:'           => 'Auschecken :',
            'Rooms:'               => 'Zimmer :',
            'Guests:'              => 'Gäste :',
            'Room'                 => 'Room',
            'Adult'                => 'Adult',
            'Child'                => 'Child',
            'Cancellation Policy:' => 'Cancellation Policy:',
            'Hotel Details'        => 'Hotel details',
            'View in map'          => 'In Karte anzeigen',
        ],
        'es' => [
            'Trip Number:'         => ['Número de viaje:', 'Número de viaje :'],
            'Full Name:'           => ['Nombre completo:', 'Nombre completo :'],
            'Check-in:'            => ['Registrarse:', 'Registrarse :'],
            'Check-out:'           => ['Revisa:', 'Revisa :'],
            'Rooms:'               => ['Hab:', 'Hab :'],
            'Guests:'              => ['Huéspedes:', 'Huéspedes :'],
            'Room'                 => ['Room', 'Habitación'],
            'Adult'                => ['Adult'],
            'Child'                => ['Child', 'Niño'],
            'Cancellation Policy:' => ['Política de cancelación:', 'Política de cancelación :'],
            'Hotel Details'        => 'Detalles del hotel',
            'View in map'          => 'Ver en mapa',
        ],
        'fr' => [
            'Trip Number:'         => 'Numéro de voyage :',
            'Full Name:'           => 'Nom complet :',
            'Check-in:'            => 'Enregistrement :',
            'Check-out:'           => 'Check-out:',
            'Rooms:'               => 'Chambre :',
            'Guests:'              => 'Clients :',
            'Room'                 => 'Room',
            'Adult'                => 'Adult',
            'Child'                => 'Child',
            'Cancellation Policy:' => "Politique d'annulation :",
            'Hotel Details'        => "Détails de l'hôtel",
            'View in map'          => 'Voir sur la carte',
        ],
    ];

    private $detectFrom = "donotreply@traveluro.com";
    private $detectSubject = [
        // en, de
        ' voucher',
    ];
    private $detectBody = [
        'en' => [
            'Your Reservation Details',
        ],
        'de' => [
            'Ihre Reservierungsdetails',
        ],
        'es' => [
            'Detalles de su reserva',
        ],
        'fr' => [
            'Vos détails de réservation',
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]traveluro\.com\b/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['.traveluro.com/'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Team Traveluro'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Trip Number:"]) && !empty($dict["Full Name:"])
                && $this->http->XPath->query("//*[{$this->contains($dict['Trip Number:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Full Name:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email): void
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip Number:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{5,})\s*$/"));

        // HOTEL

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Full Name:'))}]/following::text()[normalize-space()][1]/ancestor::*[descendant::text()[{$this->eq($this->t('Full Name:'))}]][1]",
                null, true, "/^\s*{$this->opt($this->t('Full Name:'))}\s*([[:alpha:]][[:alpha:] \-]+)\s*$/"), true)
        ;
        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy:'))}]/following::text()[normalize-space()][1]/ancestor::*[descendant::text()[normalize-space()][1][{$this->eq($this->t('Cancellation Policy:'))}]][1]",
                null, true, "/^\s*{$this->opt($this->t('Cancellation Policy:'))}\s*(.+)\s*$/");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy:'))}]/following::text()[normalize-space()][1][following::text()[normalize-space()][1][ancestor::*[contains(@style, 'rgb(113, 129, 146);') or contains(@style, '#718192;')]]]");
        }
        $h->general()
            ->cancellation($cancellation);

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Details'))}]/following::text()[normalize-space()][1]/ancestor::h3[1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Details'))}]/following::*[not(ancestor-or-self::h3) and not(.//h3)][normalize-space()][1][following::text()[normalize-space()][1][{$this->eq($this->t('View in map'))}]]"));

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in:'))}]/following::text()[normalize-space()][1]/ancestor::*[descendant::text()[{$this->eq($this->t('Check-in:'))}]][1]",
                null, true, "/{$this->opt($this->t('Check-in:'))}\s*(.+\b\d{4}\b.*)$/"))))
            ->checkOut(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out:'))}]/following::text()[normalize-space()][1]/ancestor::*[descendant::text()[{$this->eq($this->t('Check-out:'))}]][1]",
                null, true, "/{$this->opt($this->t('Check-out:'))}\s*(.+\b\d{4}\b.*)$/"))))
            ->rooms($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guests:'))}]/following::text()[normalize-space()][1]/ancestor::*[descendant::text()[{$this->eq($this->t('Guests:'))}]][1]",
                null, true, "/{$this->opt($this->t('Guests:'))}.*\b(\d+)\s+{$this->opt($this->t('Room'))}/"))
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guests:'))}]/following::text()[normalize-space()][1]/ancestor::*[descendant::text()[{$this->eq($this->t('Guests:'))}]][1]",
                null, true, "/{$this->opt($this->t('Guests:'))}.*\b(\d+)\s+{$this->opt($this->t('Adult'))}/"))
            ->kids($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guests:'))}]/following::text()[normalize-space()][1]/ancestor::*[descendant::text()[{$this->eq($this->t('Guests:'))}]][1]",
                null, true, "/{$this->opt($this->t('Guests:'))}.*\b(\d+)\s+{$this->opt($this->t('Child'))}/"), true, true)
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->http->FindSingleNode("//text()[{$this->eq($this->t('Rooms:'))}]/following::text()[normalize-space()][1]/ancestor::*[descendant::text()[{$this->eq($this->t('Rooms:'))}]][1]",
                null, true, "/{$this->opt($this->t('Rooms:'))}\s*(.+)\s*$/"))
        ;

        $this->detectDeadLine($h);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^\s*Cancellations before (.+?) \(UTC\) are fully refundable\./i", $cancellationText, $m)
            || preg_match("/^\s*Free Cancellation before (.+?) \(UTC\)/i", $cancellationText, $m)
            || preg_match("/^\s*Free Cancell?ation by (.{4,}?\d{4}\b)[\s.;!]*$/i", $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($this->normalizeDate($m[1])));
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function normalizeDate(?string $date): ?string
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // févr. 07, 2023
            "/^\s*([[:alpha:]]+)[,.\s]+(\d{1,2})[,.\s]+(\d{4})\s*$/u",
            // 18 Apr 2024 Thursday, 16:00  |  01 Jul 2023 Saturday, from 15:00
            "/^\s*(\d{1,2})[,.\s]+([[:alpha:]]+)[,.\s]+(\d{4})[,.\s]+[-[:alpha:]]+\s*,\s*\D{0,15}\b({$this->patterns['time']}).*$/u",
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));
        if (preg_match("/^\s*(\d+\s+)([^\d\s]+)(\s+\d{4}.*)/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $date = $m[1] . $en . $m[3];
            }
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return $date;
    }

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
