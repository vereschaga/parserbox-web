<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Upgrade extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-73152689.eml, tapportugal/it-73490174.eml, tapportugal/it-73564703.eml, tapportugal/it-73781723.eml";
    public static $dictionary = [
        'pt' => [
            'Booking Code:' => ['Confirmação:', 'Confirmação :', 'Confirmação', 'Código de Reserva:', 'Código de Reserva', 'Código de reserva', 'Código de reserva:', 'Código de Reserva: {0}', 'Código de Reserva: {0}:', 'Código de reserva: {0}', 'Código de reserva: {0}:'],
            'Hi'            => 'Olá',
            'Flight'        => 'Voo',
            'Departure'     => 'Partida',
        ],
        'it' => [
            'Booking Code:' => ['Codice di prenotazione:', 'Codice di prenotazione', 'Codice di prenotazione: {0}', 'Codice di prenotazione: {0}:'],
            'Hi'            => ['Salve', 'Gentile'],
            'Flight'        => 'Volo',
            'Departure'     => 'Partenza',
        ],
        'de' => [
            'Booking Code:' => ['Buchungscode:', 'Buchungscode'],
            'Hi'            => 'Hallo',
            'Flight'        => 'Flug',
            'Departure'     => 'Von',
        ],
        'fr' => [
            'Booking Code:' => ['Code de réservation:', 'Code de réservation'],
            'Hi'            => ['Bonjour', 'Chère (chère)'],
            'Flight'        => 'Vol',
            'Departure'     => 'Au départ de',
        ],
        'es' => [
            'Booking Code:' => ['Localizador de reserva:', 'Localizador de reserva'],
            'Hi'            => ['Estimado/a', '¡Hola'],
            'Flight'        => 'Vuelo',
            'Departure'     => 'Desde',
        ],
        'en' => [ // always last!
            'Booking Code:' => ['Booking Code:', 'Booking Code', 'Confirmation'],
            'Hi'            => ['Hi', 'Dear'],
            'Flight'        => ['Flight'],
            'Departure'     => ['Departure'],
        ],
    ];

    private $detectFrom = 'flytap.com';
    private $detectSubject = [
        // en
        'Your flight to ',
        'Upgrade to Business on your next TAP flight',
        'Get upgraded on your TAP Air Portugal flight to',
        'Still interested in upgrading?',
        'Get Upgraded for ',
        // pt
        'Obtenha o seu upgrade para Classe Executiva',
        'Upgrade para Business no seu próximo voo da TAP',
        'Obtenha o seu upgrade por menos',
        'Obtenha o upgrade por menos',
        // it
        'Passa a Business sul tuo prossimo volo TAP',
        'Ottieni il tuo passaggio alla Classe',
        //de
        'Upgrade auf Business bei Ihrem nächsten TAP-Flug für mehr persönlichen Raum',
        'Erhalten Sie ein Upgrade für Ihren Flug mit TAP Air Portugal nach',
        // fr
        'Obtenez votre surclassement en classe affaire sur votre vol vers',
        // es
        'Su próximo vuelo a',
        'Obtenga su upgrade para clase ',
    ];

    /*
    private $detectBody = [
        'en' => [
            'current flight itinerary is eligible for an upgrade to executive class',
            'Complete your bid and enjoy your flight even more',
            'lowered the pricing for an upgrade on your upcoming flight(s):',
        ],
        'pt' => [
            'itinerário é elegível para upgrade para a classe executiva',
            'itinerário de voo atual é elegível para um upgrade para a nossa classe executiva',
            'Abaixamos o preço do upgrade no(s) seu(s) próximo(s) voo(s):',
            'Baixámos o preço do upgradeno(s) seu(s) próximo(s) voo(s):',
            'itinerario de voo é elegível para um upgrade para a nossa classe executiva',
        ],
        'it' => [
            'viaggio attuale è ammissibile per un upgrade sulla classe esecutiva',
            'possibilità di passare alla categoria executive per il tuo itinerario di volo',
        ],
        'de' => [
            'Ihr aktueller Flugplan kommt für ein Upgrade in Business Klasse in Frage',
            'dass für Ihren Flug die Möglichkeit eines Upgrades in die executive Class besteht'
        ],
        'fr' => ['Votre vol é éligible pour un surclassement en classe affaire'],
        'es' => ['itinerario actual de su vuelo es elegible para un upgradea la clase executive'],
    ];
    */

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseHtml($email);
        $email->setType('Upgrade' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || $this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query(
                "//a[contains(@href,'flytap.com/')]"
                . " | //text()[starts-with(normalize-space(),'©') and contains(normalize-space(),'TAP Air Portugal')]"
                . " | //img[contains(@src,'.plusgrade.com/') and contains(@src,'/logoTAP_STAR.png')]"
            )->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findSegments()->length > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function findSegments(): \DOMNodeList
    {
        $xpathHeader = "//tr[ *[1][{$this->eq($this->t('Flight'))}] and *[{$this->eq($this->t('Departure'))}] ]";

        $xpath = $xpathHeader . "/following-sibling::tr[normalize-space()]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = $xpathHeader . "/ancestor::table[1]/tbody/tr[normalize-space()]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $xpath = $xpathHeader . "/ancestor::thead[1]/following-sibling::tr[normalize-space()]";
            $nodes = $this->http->XPath->query($xpath);
        }

        // $this->logger->debug($xpath);
        return $nodes;
    }

    private function parseHtml(Email $email): void
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Code:")) . "]/following::text()[normalize-space()][not(normalize-space()=':')][1]", null, true,
                "/^\s*([A-Z\d]{5,7})\s*$/"),
                trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Code:")) . "]", null, true, '/^(.+?)\s*(?:\{\d*\}\s*|$)$/'), ':'))
        ;

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->preg_implode($this->t('Hi'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }

        if (!empty($traveller) && !preg_match("/^(?:Guests?|Passengers?|Travell?ers?)$/i", $traveller)) {
            $f->general()->traveller(preg_replace("/^\s*(\S*)(?:MR|MRS|MISS)( .*|)?$/", '$1$2', $traveller));
        }

        $segments = $this->findSegments();

        foreach ($segments as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./td[1]", $root, true, "/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d{1,5}\s*$/"))
                ->number($this->http->FindSingleNode("./td[1]", $root, true, "/^\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\s*$/"));

            // Departure
            $s->departure()
                ->name($this->http->FindSingleNode("./td[2]", $root, true, "/^\s*(.+?)\s*\([A-Z]{3}\)\s*$/u"))
                ->code($this->http->FindSingleNode("./td[2]", $root, true, "/^\s*.+?\s*\(([A-Z]{3})\)\s*$/u"))
            ;
            $date = $this->http->FindSingleNode("./td[4]", $root);

            if (preg_match("/\d{1,2}:\d{2}/", $date)) {
                $s->departure()->date($this->normalizeDate($date));
            } else {
                $s->departure()
                    ->noDate()
                    ->day($this->normalizeDate($date));
            }

            // Arrival
            $s->arrival()
                ->name($this->http->FindSingleNode("./td[3]", $root, true, "/^\s*(.+?)\s*\([A-Z]{3}\)\s*$/u"))
                ->code($this->http->FindSingleNode("./td[3]", $root, true, "/^\s*.+?\s*\(([A-Z]{3})\)\s*$/u"))
                ->noDate()
            ;
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Flight']) || empty($phrases['Departure'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr[ *[{$this->eq($phrases['Flight'])}] and *[{$this->eq($phrases['Departure'])}] ]")->length > 0) {
                $this->lang = $lang;

                return true;
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

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 13 Dec 2020, 18h30
            "/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*,\s*(\d{1,2})h(\d{2})\s*$/iu",
        ];
        $out = [
            "$1 $2 $3, $4:$5",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
