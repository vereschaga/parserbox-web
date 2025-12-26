<?php

namespace AwardWallet\Engine\riu\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "riu/it-21340930.eml, riu/it-29958909.eml, riu/it-33012457.eml, riu/it-33039506.eml, riu/it-801896302.eml";

    public $reFrom = ["@riu.com"];

    public $reSubject = [
        'Request Pending Confirmation',
        'RIU Hotels & Resorts. Reservation ID',
        'Reserva RIU Hotels & Resorts', // es
        'RIU Hotels & Resorts. Numero prenotazione', // it
    ];
    public $lang = '';
    public static $dictionary = [
        'es' => [
            'Request Pending Confirmation'   => ['Request Pending Confirmation', 'Solicitud Pendiente de Confirmación'],
            'Reservation ID'                 => ['Localizador', 'Localizador de la reserva'],
            'Hotel information'              => ['Datos del Hotel', 'Hotel'],
            'Address'                        => 'Dirección',
            'Phone'                          => 'Tel',
            'Fax'                            => 'Fax',
            'Date of reservation'            => ['Fecha de solicitud', 'Fecha reserva'],
            'Check-in'                       => 'Llegada',
            'Check-out'                      => 'Salida',
            'Adults'                         => 'Adultos',
            'Children'                       => 'Niños',
            'rooms'                          => 'habitaciones',
            'adult'                          => ['adult', 'adults'],
            'children'                       => ['children', 'niño'],
            'babies'                         => ['babies', 'baby'],
            'Night'                          => 'Noche',
            'Type of room'                   => 'Tipo de habitación',
            'Total'                          => ['Total', 'Importe'],
        ],
        'pt' => [
            //            'Request Pending Confirmation' => ['', ''],
            'Reservation ID'      => ['Identificador'],
            'Hotel information'   => 'Dados hotel',
            'Address'             => 'Endereço',
            'Phone'               => 'Tel.',
            'Fax'                 => 'Fax .',
            'Date of reservation' => ['Data reserva'],
            'Check-in'            => 'Data chegada',
            'Check-out'           => 'Data saída',
            'Adults'              => 'Adultos',
            //            'Children'                       => '',
            'rooms'               => 'quartos',
            'adult'               => ['adulto', 'adultos'],
            'children'            => 'criança',
            //            'babies' => ['babies', 'baby'],
            'Night'        => 'Noite',
            'Type of room' => 'Tipo de quarto',
            'Total'        => ['Total', 'Valor Total'],
        ],
        'de' => [
            //            'Request Pending Confirmation' => ['', ''],
            'Reservation ID'      => ['Reservierungsnummer'],
            'Hotel information'   => 'Daten des Hotels',
            'Address'             => 'Adresse',
            'Phone'               => 'Tel.',
            'Fax'                 => 'Fax.',
            'Date of reservation' => ['Datum Reservierung'],
            'Check-in'            => 'Ankunft',
            'Check-out'           => 'Abreise',
            'Adults'              => 'Erw.',
            'Children'            => 'Kinder',
            'rooms'               => 'Zimmer',
            'adult'               => ['Erwachsener', 'Erwachsene'],
            'children'            => 'Kinder',
            //            'babies' => ['babies', 'baby'],
            'Night'        => 'Nächt',
            'Type of room' => 'Zimmertyp',
            'Total'        => ['Gesamtpreis'],
        ],
        'it' => [
            //            'Request Pending Confirmation' => ['', ''],
            'Reservation ID'      => ['Numero di prenotazione'],
            'Hotel information'   => "Informazione dell'hotel",
            'Address'             => 'Indirizzo',
            'Phone'               => 'Nº tel .',
            //            'Fax'                 => 'Fax.',
            //            'Date of reservation' => ['Datum Reservierung'],
            //            'Check-in'            => 'Ankunft',
            //            'Check-out'           => 'Abreise',
            'Adults'              => 'Adulti',
            'Children'            => 'Bambino',
            'rooms'               => 'camere',
            'adult'               => ['adult'],
            'children'            => 'bambino',
            'babies'              => ['bebè'],
            'Night'               => ['Notti', 'Notte'],
            'Type of room'        => 'Tipo di camera',
            'Total'               => ['Importo totale'],
        ],
        'en' => [
            // 'Request Pending Confirmation' => '',
            // 'Reservation ID' => '',
            'Hotel information'   => ['Hotel information', 'Hotel'],
            'Address'             => 'Address',
            // 'Phone' => '',
            // 'Fax' => '',
            'Date of reservation' => ['Date of reservation', 'Request Date'],
            // 'Check-in' => '',
            // 'Check-out' => '',
            // 'Adults' => '',
            // 'Children' => '',
            // 'rooms' => '',
            'adult'               => ['adult', 'adults'],
            'children'            => ['children', 'child', 'niño'],
            'babies'              => ['babies', 'baby', 'bebé'],
            // 'Night' => '',
            // 'Type of room' => '',
            'Total' => ['Total', 'Importe'],
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

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='RIU' or contains(@src,'.riu.com')] | //a[contains(@href,'.riu.com') or contains(@href,'.riuclass.com')]")->length > 0) {
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

        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider($headers['from']) !== true) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || strpos($headers['subject'], 'RIU') !== false)
                    && stripos($headers['subject'], $reSubject) !== false
                ) {
                    return true;
                }
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

    private function parseEmail(Email $email): void
    {
        $statusPending = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Request Pending Confirmation'))}])[1]",
            null, false, "/({$this->opt($this->t('Request Pending Confirmation'))})/");

        if (!empty($statusPending)) {
            $email->setIsJunk(true, 'Reservation not confirmed.');

            return;
        }

        $h = $email->add()->hotel();

        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Date of reservation'))}]/following::text()[normalize-space()!=''][1]"));

        if (!empty($date)) {
            $h->general()
                ->date($date);
        }

        $confNo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation ID'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, false, "/^[-A-Z\d]{4,30}$/");
        $h->general()
            ->confirmation($confNo);

        $travellers = $accounts = [];

        $travellersTexts = $this->http->FindNodes("//text()[{$this->eq($this->t('Adults'))} or {$this->eq($this->t('Children'))}]/ancestor::*[self::td or self::th or self::div][1]/descendant::text()[normalize-space()][position()>1]");

        foreach ($travellersTexts as $tText) {
            $travellerName = $this->re("/^(.+?)\s*,\s*RC\s*:/", $tText);

            if (!in_array($travellerName, $travellers)) {
                $travellers[] = $travellerName;
            }

            if (preg_match("/^.+,\s*(RC)\s*:\s*([-A-z\d]{2,})$/", $tText, $m)
                && !in_array($m[2], $accounts)
            ) {
                $h->program()->account($m[2], false, $travellerName, $m[1]);
                $accounts[] = $m[2];
            }
        }

        $h->general()->travellers($travellers, true);

        $root = $this->http->XPath->query("//text()[{$this->eq($this->t('Hotel information'), "translate(.,':','')")}]/ancestor::*[{$this->contains($this->t('Address'))}][1]");

        if ($root->length == 1) {
            $root = $root->item(0);
        } else {
            $this->logger->debug('other format');

            return;
        }
        $h->hotel()
            ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]", $root))
            ->address($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Address'), "translate(.,':','')")}]/ancestor::p[ preceding-sibling::node()[normalize-space()] ][1]", $root, true, "/^{$this->opt($this->t('Address'))}\s*[:]+\s*([^:]{3,190})$/"))
            ->phone(preg_replace(['/^\s*tba\s*$/i', '/^\s*(.{7,}\s*?)\/.{7,}/'], ['', '$1'], $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Phone'))}]/following::text()[normalize-space()!=''][1]",
                $root)), true, true)
            ->fax($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Fax'))}]/following::text()[normalize-space()!=''][1][not(contains(.,'E-mail') or contains(.,'@'))]",
                $root), false, true);

        $rooms = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Type of room'))}]/preceding::text()[normalize-space()!=''][1]",
            null, false, "/\((\d+)\s*{$this->opt($this->t('rooms'))}\)/");
        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Type of room'))}]/preceding::text()[normalize-space()!=''][2]");

        $kidsValues = [];

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('children'))}/iu", $node, $m)) {
            $kidsValues[] = (int) $m[1];
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('babies'))}/iu", $node, $m)) {
            $kidsValues[] = (int) $m[1];
        }

        $kids = count($kidsValues) > 0 ? array_sum($kidsValues) : null;
        $h->booked()
            ->guests($this->re("/\b(\d{1,3})\s*{$this->opt($this->t('adult'))}/iu", $node))
            ->kids($kids, false, true)
            ->rooms($rooms);

        $checkIn = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in'))}]/following::text()[normalize-space()!=''][1]"));
        $checkOut = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-out'))}]/following::text()[normalize-space()!=''][1]"));

        if (empty($checkIn) && empty($checkOut)) {
            $inOutText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Type of room'))}]/preceding::text()[{$this->contains($this->t("Night"))}]/ancestor::p[1]");

            if (preg_match("/\((\w+\s*\w+\s*\d{4})[\s\-]+(\w+\s*\w+\s*\d{4})\)/", $inOutText, $m)) {
                $checkIn = $this->normalizeDate($m[1]);
                $checkOut = $this->normalizeDate($m[2]);
            }
        }

        if (!empty($checkIn) && !empty($checkOut)) {
            $h->booked()
                ->checkIn($checkIn)
                ->checkOut($checkOut);
        }

        $roomRoot = $this->http->XPath->query("//text()[{$this->eq($this->t('Type of room'))}]/ancestor::table[1]/descendant::tr[normalize-space()][position()>1]");

        foreach ($roomRoot as $root) {
            $roomCount = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space()!=''][1]", $root, true, "/^\s*(\d+)/");

            for ($i = 1; $i <= $roomCount; $i++) {
                $room = $h->addRoom();
                $room
                    ->setType($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space()!=''][2]", $root)
                        ?? $this->http->FindSingleNode("./td[1]", $root, true, "/^\s*\d+\s+(.+)/"))
                    ->setDescription($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space()!=''][3]",
                        $root), false, true)
                    ->setRate($this->http->FindSingleNode("./td[3]", $root), true, true);
            }
        }

        $sum = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]")
            ?? $this->http->FindSingleNode("//tr[not(.//tr)][*[normalize-space()][last()][{$this->eq($this->t('Total'))}]]/following::tr[normalize-space()!=''][1]/*[normalize-space()][last()]");

        if ($sum === null && count($h->getRooms()) === 1 && $h->getRooms()[0]->getRate() !== null) {
            $sum = $h->getRooms()[0]->getRate();
        }

        $sum = $this->getTotalCurrency($sum);
        $h->price()
            ->total($sum['Total'])
            ->currency($sum['Currency']);

        $node = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Total price'))} or {$this->eq($this->t('Total'))}])[last()]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

        if ($node === null && count($h->getRooms()) === 1 && $h->getRooms()[0]->getRate() !== null) {
            $node = $h->getRooms()[0]->getRate();
        }

        if (preg_match("#\b(\d[\d ,.]*\s*(Points|Puntos))#", $node, $m)) {
            $h->price()
                ->spentAwards($m[1]);
        }
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug($date);

        $enDatesInverted = true;

        if (preg_match('/\b\d{1,2}\/(\d{1,2})\/\d{4}\b/', $date, $m) && (int) $m[1] > 12) {
            // 05/16/2019
            $enDatesInverted = false;
        }

        $in = [
            // 19/12/2018
            '#^(\d+)\/(\d+)\/(\d+)$#u',
            // 19/12/2018 14:15
            '#^(\d+)\/(\d+)\/(\d+)\s*(\d+:\d+(?:\s*[ap]m)?)$#ui',
            '#^(\d+) (\d+) (\d+)\s*$#ui',
        ];
        $out[0] = $enDatesInverted ? '$3-$2-$1' : '$3-$1-$2';
        $out[1] = $enDatesInverted ? '$3-$2-$1 $4' : '$3-$1-$2 $4';
        $out[2] = '$1.$2.$3';
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Hotel information']) || empty($phrases['Address'])) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->eq($phrases['Hotel information'], "translate(.,':','')")}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($phrases['Address'], "translate(.,':','')")}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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

    private function getTotalCurrency($node): array
    {
        // USD 1,480 (es)  |    GBP 885.78  (en) | USD 978.96 (en) |  345,64 EUR  (es)   | USD 966,09  (es)
        $tot = '';
        $cur = '';

        if ($v1 = preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || $v2 = preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::parse($m['t'], $cur);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
