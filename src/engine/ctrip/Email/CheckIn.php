<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CheckIn extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-820393479.eml, ctrip/it-822226653.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Booking No.'               => 'Booking No.',
            'Passengers'                => ['Passengers', 'Passenger'],
            'Airline Booking Reference' => 'Airline Booking Reference',
            'ReferencePart'             => ' Reference Number:',
            'Scheduled'                 => 'Scheduled',
            'Flight No.'                => ['Flight No.', 'Flight'],
            'Departure'                 => ['Departure', 'Departure Flight'],
            'Arrival'                   => 'Arrival',
        ],
        'pt' => [
            'Booking No.'               => 'N.º da reserva',
            'Passengers'                => 'Passageiro',
            'Airline Booking Reference' => 'Referência de reserva da companhia aérea',
            // 'ReferencePart' => '',
            'Scheduled'                 => 'Programado',
            'Flight No.'                => 'N° do voo',
            'Departure'                 => 'Partida',
            'Arrival'                   => 'Chegada',
        ],
        'es' => [
            'Booking No.'               => 'N.º de reserva',
            'Passengers'                => 'Pasajeros',
            'Airline Booking Reference' => 'Localizador de la reserva',
            // 'ReferencePart' => '',
            'Scheduled'                 => 'Programado',
            'Flight No.'                => 'N.º de vuelo',
            'Departure'                 => 'Vuelo de ida',
            'Arrival'                   => 'Llegada',
        ],
        'it' => [
            'Booking No.'               => 'Prenotazione n.',
            'Passengers'                => 'Passeggero',
            'Airline Booking Reference' => 'Codice di prenotazione della compagnia aerea',
            'ReferencePart'             => 'Numero di riferimento ',
            'Scheduled'                 => 'In programma',
            'Flight No.'                => ['Volo n.', 'Volo'],
            'Departure'                 => 'Volo di andata',
            'Arrival'                   => 'Arrivo',
        ],
        'fr' => [
            'Booking No.'               => 'N° réservation',
            'Passengers'                => 'Passager',
            'Airline Booking Reference' => 'Référence du passager auprès de la compagnie aérienne',
            // 'ReferencePart' => '',
            'Scheduled'                 => 'Prévu',
            'Flight No.'                => 'Nº de vol',
            'Departure'                 => 'Vol aller',
            'Arrival'                   => 'Arrivée',
        ],
    ];

    private $detectFrom = "_noreply@trip.com";
    private $detectSubject = [
        // en
        'Check-in before you fly:',
        'Check-in for your flight to',
        // pt
        'Faça seu check-in: voo de',
        // es
        'Realiza el check-in para tu vuelo hacia',
        // it
        'Effettua il check-in per il volo',
        // fr
        'Enregistrez-vous avant de prendre l\'avion : vol',
    ];
    private $detectBody = [
        'en' => [
            'Check-in Reminder',
        ],
        'pt' => [
            'Lembrete de check-in',
        ],
        'es' => [
            'Recordatorio de check-in',
        ],
        'it' => [
            'Promemoria per il check-in',
        ],
        'fr' => [
            'Rappel d\'enregistrement',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]trip\.com$/", $from) > 0;
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
        // detect Provider
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['.trip.com'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains([' Trip.com ', ' Trip.com. '])}]")->length === 0
        ) {
            return false;
        }
        // detect Format
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Booking No."]) && !empty($dict["Scheduled"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Booking No.'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Scheduled'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking No.'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{5,})\s*$/");
        $email->ota()
            ->confirmation($conf);

        // Flights
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
        ;
        $travellers = $this->http->FindNodes("//tr[*[1][{$this->eq($this->t('Passengers'))}]]/following-sibling::tr[normalize-space()][following::tr/descendant::text()[normalize-space()][1][{$this->eq($this->t('Scheduled'))}]]/*[1]");

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//tr[*[1][not({$this->eq($this->t('Passengers'))})]][descendant::text()[normalize-space()][1][{$this->eq($this->t('Passengers'))}]]"
                . "[following-sibling::tr[normalize-space()][1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Airline Booking Reference'))}]]]"
                . "[following-sibling::tr[normalize-space()][2][descendant::text()[normalize-space()][1][{$this->eq($this->t('Scheduled'))}]]]"
                . "//text()[normalize-space()][not({$this->eq($this->t('Passengers'))})]");
        }

        if (empty($travellers)
            && $this->http->XPath->query("//*[{$this->eq($this->t('Airline Booking Reference'))}]")->length === 0
        ) {
            $travellers = $this->http->FindNodes("//tr[*[1][not({$this->eq($this->t('Passengers'))})]][descendant::text()[normalize-space()][1][{$this->eq($this->t('Passengers'))}]]"
                . "[following-sibling::tr[normalize-space()][1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Scheduled'))}]]]"
                . "//text()[normalize-space()][not({$this->eq($this->t('Passengers'))})]");
        }
        $f->general()
            ->travellers($travellers, true)
        ;

        // Segments
        $s = $f->addSegment();

        // Airline
        $node = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('Flight No.'))}]/ancestor::td[1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Flight No.'))}]]/descendant::text()[normalize-space()][position() > 1]"));

        if (preg_match("/^\s*(?<name>.+?) (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,4})\s*$/", $node, $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn']);
            $airlineName = $m['name'];
        }
        $conf = $this->http->FindSingleNode("//tr[*[2][{$this->eq($this->t('Airline Booking Reference'))}]]/following-sibling::tr[normalize-space()][1]/*[2]",
            null, true, "/^\s*[A-Z\d]{5,7}\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//*[{$this->eq($this->t('Airline Booking Reference'))}]/ancestor::*[{$this->starts($this->t('Airline Booking Reference'))}][last()]/descendant::text()[normalize-space()][2]",
                null, true, "/^\s*[A-Z\d]{5,7}\s*$/");
        }

        if (empty($conf) && !empty($airlineName)) {
            $title = array_merge(preg_replace("/(.+)/", '$1' . $airlineName . ':', (array) $this->t('ReferencePart')),
                preg_replace("/(.+)/", $airlineName . '$1', (array) $this->t('ReferencePart')));
            $conf = $this->http->FindSingleNode("//tr[*[1][{$this->eq($title)}]]/following-sibling::tr[normalize-space()][1]/*[1]",
                null, true, "/^\s*[A-Z\d]{5,7}\s*$/");

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode("//node()[{$this->eq($title)}]/ancestor::*[{$this->starts($title)}][last()]/descendant::text()[normalize-space()][2]",
                    null, true, "/^\s*[A-Z\d]{5,7}\s*$/");
            }
        }

        $s->airline()
            ->confirmation($conf);

        // Departure
        $s->departure()
            ->noCode()
            ->name(implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('Departure'))}]/ancestor::td[1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Departure'))}]]/descendant::text()[normalize-space()][position() > 1]")))
            ->date($this->normalizeDate(implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('Scheduled'))}]/ancestor::td[1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Scheduled'))}]]/descendant::text()[normalize-space()][position() > 1]"))))
        ;

        // Arrival
        $s->arrival()
            ->noCode()
            ->name(implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('Arrival'))}]/ancestor::td[1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Arrival'))}]]/descendant::text()[normalize-space()][position() > 1]")))
            ->noDate()
        ;

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods
    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
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

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // 17:30, October 1, 2024
            '/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*[,\s]\s*([[:alpha:]]+)\s+(\d{1,2})\s*[,\s]\s*(\d{4})\s*$/ui',
            // 22:10 16 декабря 2024 г.
            '/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*[,\s]\s*(\d{1,2})\s+([[:alpha:]]+)\s*[,\s]\s*(\d{4})(?:\s*г\.)?\s*$/ui',
            // 18 de diciembre de 2024, 11:25
            // 30 April, 2023, 21:10
            // 18 janvier 2025, 16 h 50
            '/^\s*(\d{1,2})\s+(?:de\s+)?([[:alpha:]]+)\s*(?:,|\s+|\s+de\s+)?\s*(\d{4})\s*[,\s]\s*(\d{1,2})(?::| h )(\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$3 $2 $4, $1',
            '$2 $3 $4, $1',
            '$1 $2 $3, $4:5',
        ];
        // $this->logger->debug('date replace = ' . print_r( $date, true));

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
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
