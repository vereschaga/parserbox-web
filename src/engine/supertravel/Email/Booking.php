<?php

namespace AwardWallet\Engine\supertravel\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "supertravel/it-301414254-it.eml, supertravel/it-304488112-de.eml, supertravel/it-307018889-es.eml, supertravel/it-318953555-fr.eml, supertravel/it-71058893.eml, supertravel/it-720964949.eml, supertravel/it-73015859.eml, supertravel/it-73410788.eml";
    public $subjects = [
        'La tua conferma di prenotazione SuperTravel per', // it
        'Ihre SuperTravel Buchungsbestätigung für', // de
        'Su SuperTravel Confirmación de reserva para', // es
        'Votre SuperTravel confirmation de réservation pour', // fr
        'Your SuperTravel Booking Confirmation for', // en
        'Your SuperTravel Booking Cancellation for', // en

        // + legacy formats
        'La tua conferma di prenotazione SnapTravel per', // it
        'Ihre SnapTravel Buchungsbestätigung für', // de
        'Su SnapTravel Confirmación de reserva para', // es
        'Votre SnapTravel confirmation de réservation pour', // fr
        'Your SnapTravel Booking Confirmation for', // en
        'Your SnapTravel Booking Cancellation for', // en
    ];

    public $lang = '';

    public static $dictionary = [
        "zh" => [
            'Confirmation Number:' => '确认号码:',
            'Guest Name:'          => "客人姓名:",
            'Cancellation Policy'  => "取消政策",
            'Primary Guest Name'   => "主要来宾姓名:",
            'Your booking at'      => '确认您的',
            'is'                   => '预',
            // 'has been' => '',
            'Address:'   => '地址:',
            'Check-In:'  => '报到:',
            'Check-Out:' => '退房:',
            'Room Type:' => '房型:',

            // Body Detect
            'Booking Details' => '订房详情',
            'Guest Details'   => "其他详情",
        ],
        "it" => [
            'Confirmation Number:' => 'Numero di conferma:',
            'Guest Name:'          => 'Nome ospite:',
            'Cancellation Policy'  => 'Politica di cancellazione',
            'Primary Guest Name'   => 'Nome ospite principale:',
            'Your booking at'      => 'La prenotazione a',
            'is'                   => 'è',
            // 'has been' => '',
            'Address:'   => 'Indirizzo:',
            'Check-In:'  => 'Registrare:',
            'Check-Out:' => 'Check-out:',
            'Room Type:' => 'Tipo di stanza:',

            // Body Detect
            'Booking Details' => 'Dettagli della prenotazione',
            'Guest Details'   => 'Dettagli ospite',
        ],
        "de" => [
            'Confirmation Number:' => 'Bestätigungsnummer:',
            'Guest Name:'          => 'Gastname:',
            'Cancellation Policy'  => 'Stornierungsbedingungen',
            'Primary Guest Name'   => 'Primärer Gastname',
            'Your booking at'      => 'Ihre Buchung bei',
            'is'                   => 'ist',
            // 'has been' => '',
            'Address:'   => 'Adresse:',
            // 'Check-In:' => '',
            'Check-Out:' => 'Auschecken:',
            'Room Type:' => 'Zimmertyp:',

            // Body Detect
            'Booking Details' => 'Buchungsdetails',
            'Guest Details'   => 'Gäste Details',
        ],
        "es" => [
            'Confirmation Number:' => 'Número de confirmación:',
            'Guest Name:'          => 'Nombre del invitado:',
            'Cancellation Policy'  => 'Detalles de cancelación',
            'Primary Guest Name'   => 'Nombre del huésped principal:',
            'Your booking at'      => 'Su reserva en',
            'is'                   => 'está',
            // 'has been' => '',
            'Address:'   => 'Dirección:',
            'Check-In:'  => 'Check-in:',
            'Check-Out:' => 'Salida:',
            'Room Type:' => 'Tipo de habitación:',

            // Body Detect
            'Booking Details' => 'Detalles de reserva',
            'Guest Details'   => 'Detalles del huésped',
        ],
        "fr" => [
            'Confirmation Number:' => 'Numéro de confirmation:',
            'Guest Name:'          => "Nom de l'invité:",
            'Cancellation Policy'  => "Politique d'annulation",
            'Primary Guest Name'   => "Nom d'invité principal",
            'Your booking at'      => 'Votre réservation à',
            'is'                   => 'est',
            // 'has been' => '',
            'Address:'   => 'Adresse:',
            'Check-In:'  => 'Enregistrement:',
            'Check-Out:' => 'Check-out:',
            'Room Type:' => 'Type de chambre:',

            // Body Detect
            'Booking Details' => 'Les détails de réservation',
            'Guest Details'   => "Détails de l'invité",
        ],
        "en" => [
            'Guest Name:' => ['Guest Name:', 'Guest Name'],

            // Body Detect
            'Booking Details' => 'Booking Details',
            'Guest Details'   => 'Guest Details',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers['subject'], 'SuperTravel') === false
            && stripos($headers['subject'], 'SnapTravel') === false
        ) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".super.com/") or contains(@href,".livesuper.com/") or contains(@href,".snaptravel.com/") or contains(normalize-space(),"Super.com profile")]')->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@super.com') !== false || stripos($from, '@livesuper.com') !== false || stripos($from, '@gosnaptravel.com') !== false;
    }

    public function ParseHtml(Email $email): void
    {
        $this->assignLang();

        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number:'))}]");

        if (preg_match("/({$this->opt($this->t('Confirmation Number:'))})\s*([_A-Z\d]+)$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy'))}]/following::text()[normalize-space()][not({$this->contains($this->t('Refund Policy Flexibility:'))})][1]");

        if (!empty($cancellationPolicy)) {
            $h->general()
                ->cancellation($cancellationPolicy);
        }

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name:'))}]/following::text()[normalize-space()][1]"), true);

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Primary Guest Name'))}]/preceding::text()[{$this->starts($this->t('Your booking at'))}][1]", null, true, "/{$this->opt($this->t('is'))}\s*(\D+)$/u");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Details'))}]/preceding::text()[{$this->starts($this->t('Your booking at'))}][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('has been'))}\s*(\D+)$/u");
        }

        if (!empty($status) && $status == 'cancelled') {
            $h->general()
                ->status($status);

            $cancellationNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Number:'))}]", null, true, "/{$this->opt($this->t('Cancellation Number:'))}\s*([A-Z\d\_]+)/");
            // in this format cancellationNumber == confirmationNumber
            if (!empty($cancellationNumber)) {
                $h->general()
                    ->confirmation($cancellationNumber);
            }
        }

        if (!empty($status)) {
            $h->general()
                ->status($status);
        }

        $addressText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->eq($this->t('Address:'))}]/ancestor::tr[1]"));

        if (preg_match("/^\s*{$this->opt($this->t('Address:'))}[ ]*\n+[ ]*(?<name>.{2,}?)[ ]*\n+[ ]*(?<address>.{3,}?)\s*$/", $addressText, $m)) {
            $h->hotel()->name($m['name'])->address($m['address']);
        }

        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-In:'))}]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-Out:'))}]/following::text()[normalize-space()][1]")));

        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type:'))}]/following::text()[normalize-space()][1]");
        $roomDescription = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type:'))}]/following::text()[normalize-space()][2]");

        if (!empty($roomType) && !empty($roomDescription)) {
            $room = $h->addRoom();
            $room->setType($roomType);
            $room->setDescription($roomDescription);
        }
        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHtml($email);
        $email->setType('Booking' . ucfirst($this->lang));

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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^Refundable \- This booking is fully refundable until (\w+\s*\d+\,\s*\d{4})/", $cancellationText, $m)
        || preg_match("/^Your booking is fully refundable until (\w+\s*\d+\,\s*\d{4})\./", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1]));
        }

        $h->booked()
            ->parseNonRefundable("/^Non rimborsabile/") // it
            ->parseNonRefundable("/^No reembolsable/") // es
            ->parseNonRefundable("/^Non remboursable/") // fr
            ->parseNonRefundable("/^Non Refundable/") // en
            ->parseNonRefundable("/^Your booking is 100% non-refundable/") // en
        ;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*\w+\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*\((\d+\s*A?P?M)\)$#", //Thu Dec 31, 2020 (3 PM)
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Booking Details']) || empty($phrases['Guest Details'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Booking Details'])}]")->length > 0
                || $this->http->XPath->query("//*[{$this->contains($phrases['Guest Details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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
