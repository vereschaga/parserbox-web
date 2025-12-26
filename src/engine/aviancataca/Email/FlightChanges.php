<?php

namespace AwardWallet\Engine\aviancataca\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\FlightSegment;

class FlightChanges extends \TAccountChecker
{
    public $mailFiles = "aviancataca/it-832762811-es.eml, aviancataca/it-828679902-es.eml";

    private $subjects = [
        'es' => ['aquí están los detalles de tu reserva']
    ];

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'confNumber' => ['Referencia de la reserva'],
            'departure' => ['Vuelo de Salida'],
            'arrival' => ['Llegada'],
            'statusValues' => ['Confirmado', 'Cambio programado'],
        ]
    ];

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]avianca\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array)$phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false)
                    return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getCleanFrom()) !== true
            && $this->http->XPath->query('//a[contains(@href,".avianca.com/") or contains(@href,"www.avianca.com") or contains(@href,"cambiatuitinerario.avianca.com")]')->length === 0
            && $this->http->XPath->query('//*[normalize-space()="políticas de Avianca"]')->length === 0
        ) {
            return false;
        }
        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
            return $email;
        }
        $email->setType('FlightChanges' . ucfirst($this->lang));

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $travellers = $this->http->FindNodes("//*[ *[normalize-space()][1][{$this->eq($this->t('Lista de pasajeros'))}] ]/following-sibling::*[normalize-space()][1]/descendant::tr[ count(*)=2 and *[1][normalize-space()=''] ]/*[2][normalize-space()]", null, "/^({$this->patterns['travellerName']})(?:\s+-|$)/u");
        $f->general()->travellers($travellers, true);

        $segments = $this->http->XPath->query("//tr[ *[normalize-space()][1][{$this->starts($this->t('departure'))}] and *[normalize-space()][2][{$this->starts($this->t('arrival'))}] ]");

        foreach ($segments as $root) {
            $nameDep = $nameArr = null;

            $routeText = implode(' ', $this->http->FindNodes("preceding-sibling::tr[normalize-space()][1]/*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/(?:^|\s){$this->opt($this->t('Desde'))}[:\s]+(.{2,}?)[,\s]+{$this->opt($this->t('hacia'))}[:\s]+(.{2,})$/i", $routeText, $m)) {
                // Desde San Juan hacia Lima
                $nameDep = $m[1];
                $nameArr = $m[2];
            }

            $status = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/*[normalize-space()][2]", $root, true, "/^{$this->opt($this->t('statusValues'))}$/iu");

            $dateDep = $dateArr = null;

            $dateDepVal = $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/^{$this->opt($this->t('departure'))}[-:\s]*(.{6,60})$/");
            $dateArrVal = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, "/^{$this->opt($this->t('arrival'))}[-:\s]*(.{6,60})$/");
            
            // Miércoles, 20 Marzo 2024 17:45
            $patternDateTime = "/^(?<date>.{6,}?)[-,\s]+(?<time>{$this->patterns['time']}).*$/";
            
            if (preg_match($patternDateTime, $dateDepVal, $m)) {
                $dateDep = strtotime($m['time'], strtotime($this->normalizeDate($m['date'], $this->lang)));
            }
            
            if (preg_match($patternDateTime, $dateArrVal, $m)) {
                $dateArr = strtotime($m['time'], strtotime($this->normalizeDate($m['date'], $this->lang)));
            }

            $s = $f->addSegment();
            $s->extra()->status($status, false, true);

            $s->departure()->name($nameDep);
            $s->departure()->date($dateDep);

            if ($nameDep && $dateDep) {
                $s->airline()->noName()->noNumber();
            }

            $seatSections = $this->http->XPath->query("following::text()[string-length(normalize-space())>3][position()<7][{$this->eq($this->t('Selección de asiento'), "translate(.,':','')")}]/ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/../descendant::*[ count(tr[normalize-space()])>1 and tr[normalize-space()][1][{$this->eq($this->t('Selección de asiento'), "translate(.,':','')")}] ]", $root);

            if ($seatSections->length > 2) {
                $this->logger->debug('Wrong flight segment!');
                continue;
            } elseif ($seatSections->length === 2) { // it-828679902-es.eml
                $s->arrival()->noDate();

                $this->parseCodesAndSeats($s, $seatSections->item(0), $this->http, $this->eq($this->t('Selección de asiento'), "translate(.,':','')"), $this->patterns['travellerName']);

                $s = $f->addSegment();
                $s->extra()->status($status, false, true);
                $s->departure()->noDate();
                $s->arrival()->name($nameArr);
                $s->arrival()->date($dateArr);

                if ($nameArr && $dateArr) {
                    $s->airline()->noName()->noNumber();
                }

                $this->parseCodesAndSeats($s, $seatSections->item(1), $this->http, $this->eq($this->t('Selección de asiento'), "translate(.,':','')"), $this->patterns['travellerName']);
            } elseif ($seatSections->length === 1) {
                $s->arrival()->name($nameArr);
                $s->arrival()->date($dateArr);

                $this->parseCodesAndSeats($s, $seatSections->item(0), $this->http, $this->eq($this->t('Selección de asiento'), "translate(.,':','')"), $this->patterns['travellerName']);
            } elseif ($seatSections->length === 0) {
                $s->arrival()->name($nameArr);
                $s->arrival()->date($dateArr);

                $s->departure()->noCode();
                $s->arrival()->noCode();
            }
        }

        return $email;
    }

    public static function parseCodesAndSeats(FlightSegment $s, \DOMNode $seatSection, \HttpBrowser $http, string $xpathSeat, string $patternPax): void
    {
        // used in parser aviancataca/FlightReservation

        $codesRow = $http->FindSingleNode("tr[normalize-space()][2]", $seatSection);

        if (preg_match("/^([A-Z]{3})\s*[-]+\s*([A-Z]{3})$/", $codesRow, $m)) {
            $s->departure()->code($m[1]);
            $s->arrival()->code($m[2]);
        } else {
            $s->departure()->noCode();
            $s->arrival()->noCode();
        }

        $seatRows = $http->XPath->query("tr[normalize-space()][not({$xpathSeat})]/descendant-or-self::tr[not(.//tr[normalize-space()]) and normalize-space()]", $seatSection);

        foreach ($seatRows as $seatRow) {
            $seatVal = implode(' ', $http->FindNodes("descendant::text()[normalize-space()]", $seatRow));

            if (preg_match("/^(?<pax>{$patternPax})[(\s]+(?<seat>\d+[A-Z])[\s)]*$/u", $seatVal, $m)) {
                $s->extra()->seat($m['seat'], false, false, $m['pax']);
            }
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

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['departure']) || empty($phrases['arrival']) ) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->contains($phrases['departure'])}]/following::*[{$this->contains($phrases['arrival'])}]")->length > 0) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`
     * @param string|null $text Unformatted string with date
     * @return string|null
     */
    public static function normalizeDate(?string $text, string $lang): ?string
    {
        // used in parser aviancataca/FlightReservation
        
        if ( preg_match('/\b(\d{1,2})\s+(?:de\s+)?([[:alpha:]]+)\s+(?:de\s+)?(\d{4})$/u', $text, $m) ) {
            // Miércoles, 20 Marzo 2024  |  Segunda-feira 19 de agosto de 2019
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }
        if ( isset($day, $month, $year) ) {
            if ( preg_match('/^\s*(\d{1,2})\s*$/', $month, $m) )
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            if ( ($monthNew = MonthTranslate::translate($month, $lang)) !== false )
                $month = $monthNew;
            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }
        return null;
    }
}
