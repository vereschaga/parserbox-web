<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class LaVista2 extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-630735221.eml, lanpass/it-634565682.eml";
    public $detectSubjects = [
        // pt
        'Você já comprou sua viagem a ', // Voo para São Paulo à vista...
        //en
        'You purchased your trip to',
        //es
        'Ya compraste tu viaje a ',
        // de
        'Du hast Deine Reise nach ',
    ];

    public $lang = 'pt';

    public $detectLang = [
        'pt' => ['Itinerário da viagem', 'Voo de ida'],
        'en' => ['Travel itinerary', 'Outbound flight'],
        'es' => ['Itinerario de viaje', 'Vuelo de ida'],
        'de' => ['Reiseroute', 'Hinflug'],
    ];

    public static $dictionary = [
        'pt' => [
        ],
        'en' => [
            'Nº de compra:'        => 'Order Number:',
            'Itinerário da viagem' => 'Travel itinerary',
            'Código de reserva:'   => 'Reservation Code:',
            'Lista de passageiros' => 'Passenger list',
            'Total:'               => 'Total:',
            'Voo de ida'           => 'Outbound flight',
            'Voo de volta'         => 'Return flight',
            'Troca de avião em:'   => 'Change of aircraft in:',
        ],
        'es' => [
            'Nº de compra:'        => 'Nº de orden:',
            'Itinerário da viagem' => 'Itinerario de viaje',
            'Código de reserva:'   => 'Código de reserva:',
            'Lista de passageiros' => 'Lista de pasajeros',
            'Total:'               => 'Total:',
            'Voo de ida'           => 'Vuelo de ida',
            'Voo de volta'         => 'Vuelo de vuelta',
            'Troca de avião em:'   => 'Cambio de avión en:',
        ],
        'de' => [
            'Nº de compra:'        => 'Auftragsnummer:',
            'Itinerário da viagem' => 'Reiseroute',
            'Código de reserva:'   => 'Reservierungscode:',
            'Lista de passageiros' => 'Passagierliste',
            'Total:'               => 'Gesamt:',
            'Voo de ida'           => 'Hinflug',
            'Voo de volta'         => 'Rückflug',
            'Troca de avião em:'   => 'Wechsel des Flugzeuges in:',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'LATAM Airlines') !== false
            || stripos($from, '@info.latam.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubjects) {
            if (stripos($headers['subject'], $dSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        if ($this->http->XPath->query('//a[contains(@href,".latam.com")]')->length === 0
            && $this->http->XPath->query('//a[contains(@originalsrc,".latam.com")]')->length === 0
        ) {
            return false;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Nº de compra:'))}]/following::text()[normalize-space()][position() < 10][{$this->eq($this->t('Itinerário da viagem'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();

        $pdfs = $parser->searchAttachmentByName(".*\.pdf");

        if (count($pdfs) > 0) {
            $this->logger->debug('go to parse pdf (TicketInfoPDF)');

            return $email;
        }
        $this->parseFlight($email);
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

    public function AssignLang()
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }
    }

    private function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Nº de compra:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/u");

        if (!empty($conf)) {
            $f->general()
                ->confirmation($conf,
                    trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('Nº de compra:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Nº de compra:'))}/u"), ':'));
        }

        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Código de reserva:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Código de reserva:'))}\s*([A-Z]{5,})\s+/u");

        if (!empty($conf)) {
            $f->general()
                ->confirmation($conf,
                    trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('Código de reserva:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Código de reserva:'))}/u"), ':'));
        }

        if (empty($f->getConfirmationNumbers())) {
            $f->general()
                ->noConfirmation();
        }

        $travellers = $this->http->FindNodes("//text()[{$this->contains($this->t('Lista de passageiros'))}]/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space()]");

        if (count($travellers) > 0) {
            $f->general()
                ->travellers($travellers, true);
        }

        $price = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total:'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");

        if (preg_match("/(?<points>Pontos\s*[\d\.\,]+)?\s*[+]*\s*(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $price, $m)) {
            if (isset($m['points']) && !empty($m['points'])) {
                $f->price()
                    ->spentAwards($m['points']);
            }
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $segBlock = [];

        $xpath = "//text()[{$this->eq($this->t('Itinerário da viagem'))}]/following::text()[normalize-space()][1][contains(normalize-space(), '20')]/ancestor::table[1]/descendant::tr[normalize-space()]";
        $rows = $this->http->XPath->query($xpath);

        if ($rows->length === 0) {
            $xpath = "//text()[{$this->eq($this->t('Itinerário da viagem'))}]/following::text()[{$this->eq($this->t('Voo de ida'))}][1]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::tr[normalize-space()]";
            $rows = $this->http->XPath->query($xpath);
        }

        if ($rows->length > 0) {
            $segBlock[] = $rows;
        }
        $xpath = "//text()[{$this->eq($this->t('Itinerário da viagem'))}]/following::text()[{$this->eq($this->t('Voo de volta'))}][1]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::tr[normalize-space()]";
        $rows = $this->http->XPath->query($xpath);

        if ($rows->length > 0) {
            $segBlock[] = $rows;
        }

        foreach ($segBlock as $rows) {
            $segments = [];

            foreach ($rows as $i => $root) {
                $segments[] = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]", $root));
            }
            // $this->logger->debug('$segments = ' . print_r($segments, true));

            foreach ($segments as $i => $sText) {
                if ($i === count($segments) - 1) {
                    break;
                }

                $s = $f->addSegment();

                // Airline
                if (preg_match("/\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{1,5})\s*/", $sText, $m)) {
                    $s->airline()
                        ->name($m['name'])
                        ->number($m['number']);

                    $this->fNumber = $m['number'];
                    $this->aName = $m['name'];
                }

//            19 de agosto de 2022
                //16:55 Cusco (LA2024)
                // Conexión en
//            Conexión en Lima (LA2469)
                //Con cambio de avión

                $reConnection = "/^\s*{$this->opt($this->t('Troca de avião em:'))}\s+(?<name>.+?)\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{2,4}/";
                $reAirport = "/^\s*(?<date>.*?\b\d{4}\b.* \d{1,2}:\d{2}\s*([APap][Mm]|[ap]\. ?m\.)?)\s+(?<name>.+?)\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{2,4}/";
                $reAirport2 = "/^\s*(?<date>.*?\b\d{4}\b.* \d{1,2}:\d{2}\s*([APap][Mm]|[ap]\. ?m\.)?)\s+(?<name>.+?)$/";

                // Departure
                if (preg_match($reConnection, $sText, $m)) {
                    $s->departure()
                        ->noCode()
                        ->noDate()
                        ->name(trim($m['name']));
                } elseif (preg_match($reAirport, $sText, $m)) {
                    $s->departure()
                        ->noCode()
                        ->date($this->normalizeDate($m['date']))
                        ->name(trim($m['name']));
                } elseif (preg_match($reAirport2, $sText, $m)) {
                    $s->departure()
                        ->noCode()
                        ->date($this->normalizeDate($m['date']))
                        ->name(trim($m['name']));
                }

                // Arrival
                if (preg_match($reConnection, $segments[$i + 1] ?? '', $m)) {
                    $s->arrival()
                        ->noCode()
                        ->noDate()
                        ->name(trim($m['name']));
                } elseif (preg_match($reAirport, $segments[$i + 1] ?? '', $m)) {
                    $s->arrival()
                        ->noCode()
                        ->date($this->normalizeDate($m['date']))
                        ->name(trim($m['name']));
                } elseif (preg_match($reAirport2, $segments[$i + 1] ?? '', $m)) {
                    $s->arrival()
                        ->noCode()
                        ->date($this->normalizeDate($m['date']))
                        ->name(trim($m['name']));
                }
            }
        }
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 01 de marzo de 2023 08:47
            // 09 set. 2024 10:35
            // 27. Okt. 2024 12:50
            // 03 de mar de 2024 9:27 p. m.
            "/^\s*(\d+)\.?\s+(?:de\s+)?([[:alpha:]]+)\.?\s+(?:de\s+)?(\d{4})[\s\,]+(\d{1,2}:\d{2}(?:\s*(?:[AP]M|[ap]\. ?m\.)?)?)\s*$/iu",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);
        // 2:47 p. m.  ->  2:47 pm
        $date = preg_replace("/(\d:\d{2}\s*[ap])\. ?\m\.\s*$/i", '$1m', $date);
        // $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
