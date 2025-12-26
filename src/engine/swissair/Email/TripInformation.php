<?php

namespace AwardWallet\Engine\swissair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TripInformation extends \TAccountChecker
{
    public $mailFiles = "swissair/it-8679483.eml, swissair/it-8679490.eml, swissair/it-4995495.eml, swissair/it-5054727.eml, swissair/it-5065917.eml, swissair/it-3308558.eml, swissair/it-4567706.eml, swissair/it-4583761.eml, swissair/it-4687753.eml, swissair/it-4754703.eml, swissair/it-6589617.eml";

    public $reSubject = [
        'es' => 'Toda la información relacionada con su viaje del',
        'pt' => 'Todas as informações sobre sua viagem',
        'de' => 'Alle Informationen zu Ihrer Reise',
        'fr' => 'Toutes les informations concernant votre voyag',
        'en' => 'All the information on your trip',
    ];

    public $lang = '';

    public $detectors = [
        'es' => ['Información del vuelo'],
        'pt' => ['Informações de voos'],
        'de' => ['Fluginformationen'],
        'fr' => ['Informations sur le vol'],
        'en' => ['Flight information'],
    ];

    public static $dict = [
        'es' => [
            'Booking reference:' => 'Referencia de la reserva:',
            'Dear'               => 'Saludos',
            'title'              => ['Señora', 'Señor'],
            'Operated by'        => 'Operado por',
//             'On behalf of' => '',
        ],
        'pt' => [
            'Booking reference:'     => 'Referência da reserva:',
            'Dear'                   => 'Bom dia',
            'title'              => ['Sra', 'Sr'],
            // 'Operated by' => '',
            // 'On behalf of' => '',
        ],
        'de' => [
            'Booking reference:' => 'Buchungsreferenz:',
            'Dear'               => ['Grüezi',],
            'title'              => ['Herr', 'Frau'],
            'Operated by'        => 'Durchgeführt von',
             'On behalf of' => 'im Auftrag von',
        ],
        'fr' => [
            'Booking reference:' => 'Référence de réservation:',
            'Dear'               => 'Bonjour',
            'title'              => ['Monsieur', 'Madame'],
            'Operated by'        => 'Opéré par',
             'On behalf of' => 'au nom de',
        ],
        'en' => [
            'Booking reference:' => ['Booking reference:'],
            'Dear'               => ['Dear', 'Hello'],
            'title'               => ['Mr.', 'Ms.'],
            'Operated by'        => ['Operated by', 'Flight operated by'],
        ],
    ];

    public function parseHtml(Email $email): void
    {
        $f = $email->add()->flight();

        $f->general()->confirmation($this->http->FindSingleNode('//text()[' . $this->starts($this->t('Booking reference:')) . ']', null, true, '/:\s*([A-Z\d]{5,})$/'));

        // Passengers
        $passenger = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Dear')) . ']', null, true, '/' . $this->opt($this->t('Dear')) . '\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,:;!?]|$)/u');

        if ($passenger && !preg_match("/\b(?:Passenger|Passagier|cliente)\b/ui", $passenger)) {
            $passenger = preg_replace("/^\s*{$this->opt($this->t("title"))}[.]?\s+/i", '', $passenger);
            $f->general()->traveller($passenger);
        }

        $xpath = '//img[contains(@src,"/ico-09.png")]/ancestor::tr[./td[2] and ./ancestor::table[1]/preceding-sibling::table[1]][1]';
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug("Segments root not found: " . $xpath);
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('./ancestor::table[1]/preceding-sibling::table[1]/descendant::text()[normalize-space(.)][1]', $root);

            if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/', $flight, $matches)) {
                $s->airline()->name($matches[1])->number($matches[2]);
            }

            // DepCode
            $s->departure()->code($this->http->FindSingleNode('td[1]/descendant::text()[normalize-space()][2]', $root));

            // ArrCode
            $s->arrival()->code($this->http->FindSingleNode('td[2]/descendant::text()[normalize-space()][2]', $root));

            // DepDate
            // ArrDate
            $date = $this->http->FindSingleNode('./preceding::img[contains(@src,"/ico-08.png") or contains(@src,"/ico-10.png")][1]/ancestor::tr[1]', $root, true, '/,\s+(.+)/');
            $timeDep = $this->http->FindSingleNode('./td[1]/descendant::text()[normalize-space(.)][1]', $root);
            $timeArr = $this->http->FindSingleNode('./td[2]/descendant::text()[normalize-space(.)][1]', $root);

            if ($date && $timeDep && $timeArr) {
                $date = strtotime($this->normalizeDate($date));

                if ($date) {
                    $s->departure()->date(strtotime($timeDep, $date));

                    if ($timeArr === ':') {
                        // invalid time ":" see in it-4995495.eml and it-5054727.eml and it-5065917.eml
                        $s->arrival()->noDate();
                    } else {
                        $s->arrival()->date(strtotime($timeArr, $date));
                    }
                }
            }

            // DepartureTerminal
            $terminalDep = $this->http->FindSingleNode('td[1]/descendant::text()[contains(normalize-space(),"Terminal")][last()]', $root, true, '/Terminal\s+(\w[\w\s]*?)(?:\s*[,;]|$)/i');

            if ($terminalDep) {
                $s->departure()->terminal($terminalDep);
            }

            // Cabin
            $s->extra()->cabin($this->http->FindSingleNode('td[2]/descendant::text()[normalize-space()][last()]', $root));

            // Operator
            $operator = $this->http->FindSingleNode('ancestor::table[1]/preceding-sibling::table[normalize-space()][1]/descendant::text()[' . $this->starts($this->t('Operated by')) . ']', $root, true, '/' . $this->opt($this->t('Operated by')) . "\s+(.+?)(?:\s+{$this->opt($this->t('On behalf of'))}|$)/i");

            if ($operator) {
                $s->airline()->operator($operator);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Swiss International Air Lines') !== false
            || stripos($from, '@notifications.swiss.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'Swiss International Air Lines') === false && stripos($headers['from'], '@notifications.swiss.com') === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"//www.swiss.com") or contains(@href,"//www.cars-swiss.com") or contains(@href,"//www.hotels-swiss.com") or contains(@href,"//schedulechanges.swiss.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Swiss Choice") or contains(normalize-space(),"SWISS Choice")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $this->assignLang();

        $this->parseHtml($email);
        $email->setType('TripInformation' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dict, $this->lang)) {
            return false;
        }

        foreach (self::$dict as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Booking reference:'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Booking reference:'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
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
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function normalizeDate($string)
    {
        if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $string, $matches)) { // mié. 26.10.2016
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if ($day && $month && $year) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
    }
}
