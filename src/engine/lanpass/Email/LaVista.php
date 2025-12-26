<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Schema\Parser\Email\Email;

class LaVista extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-275744178.eml, lanpass/it-279128763.eml, lanpass/it-465142346.eml, lanpass/it-629517569.eml";

    public $detectSubjects = [
        // es
        'a la vista...', //Vuelo a Santiago de Chile a la vista...
        // pt
        ' à vista... ', // Voo para São Paulo à vista...
        // en
        'Details for your upcoming flight to',
        // de
        'Du hast Deine Reise nach ',
    ];

    public $fNumber;
    public $aName;

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'Hola'                 => 'Hola',
            'Nº Orden:'            => 'Nº Orden:',
            'Información de vuelo' => 'Información de vuelo',
            'Conexión en'          => ['Conexión en', 'Escala en'],
        ],
        'pt' => [
            'Hola'                 => 'Olá,',
            'Nº Orden:'            => 'Nº Compra:',
            'Información de vuelo' => 'Informação de voo',
            'Conexión en'          => 'Conexão em',
        ],
        'en' => [
            'Hola'                 => ['Hi', 'Hi,'],
            'Nº Orden:'            => 'order number:',
            'Información de vuelo' => 'Flight information',
            'Conexión en'          => 'Connection in',
        ],
        'de' => [
            'Hola'                 => ['Hallo'],
            'Nº Orden:'            => 'Auftragsnummer:',
            'Información de vuelo' => 'Reiseroute',
            // 'Conexión en'          => 'Connection in',
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
        if ($this->http->XPath->query('//a[contains(@href,".latamairlines.com")]')->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Nº Orden:']) && !empty($dict['Información de vuelo'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Nº Orden:'])}]/following::text()[normalize-space()][position() < 5][{$this->eq($dict['Información de vuelo'])}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Información de vuelo'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Información de vuelo'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseFlight($email);

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

    private function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Nº Orden:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Nº Orden:'))}\s*(\w{5,})\s*\./u");
        $f->general()
            ->confirmation($conf);
        $traveller = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Información de vuelo'))}]/preceding::text()[{$this->eq($this->t('Hola'))}])[last()]/following::text()[normalize-space()][1]", null, true,
            "/^\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u");
        $f->general()
            ->traveller($traveller, false);

        $xpath = "//text()[{$this->eq($this->t('Información de vuelo'))}]/following::text()[normalize-space()][1]/ancestor::*[not(.//text()[{$this->eq($this->t('Información de vuelo'))}])][last()]//td[normalize-space()]";
        $rows = $this->http->XPath->query($xpath);

        $segments = [];

        foreach ($rows as $i => $root) {
            $segments[] = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]", $root));
        }
        $this->logger->debug('$segments = ' . print_r($segments, true));

        foreach ($segments as $i => $sText) {
            if ($i === count($segments) - 1) {
                break;
            }

            $s = $f->addSegment();

            // Airline
            if (preg_match("/\(\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{1,5})\s*\)/", $sText, $m)) {
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

            $reConnection = "/^\s*{$this->opt($this->t('Conexión en'))}\s+(?<name>.+?)\(/";
            $reConnection2 = "/^\s*{$this->opt($this->t('Conexión en'))}\s+(?<name>.+?)$/";
            $reAirport = "/^\s*(?<date>.*?\b\d{4}\b.* \d{1,2}:\d{2}\s*A?P?M?)\s+(?<name>.+?)\s*(?:\(|$)/";

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
            } elseif (preg_match($reConnection2, $sText, $m) && !empty($this->aName) && !empty($this->fNumber)) {
                $s->departure()
                    ->noCode()
                    ->noDate()
                    ->name(trim($m['name']));

                $s->airline()
                    ->name($this->aName)
                    ->number($this->fNumber);
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
            } elseif (preg_match($reConnection2, $segments[$i + 1] ?? '', $m)) {
                $s->arrival()
                    ->noCode()
                    ->noDate()
                    ->name(trim($m['name']));
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
        $in = [
            // 01 de marzo de 2023 08:47
            "/^\s*(\d+)\s+(?:de\s+)([[:alpha:]]+)\s+(?:de\s+)(\d{4})[\s\,]+(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/iu",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
