<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "iberia/it-209296941.eml, iberia/it-210566139.eml, iberia/it-654001421.eml, iberia/it-706100134.eml, iberia/it-706555190.eml";

    public $detectFrom = ["iberia@experienciaiberia.iberia.com", "iberia@comunicaciones.iberia.com", 'noreply@comunicaciones.iberia.com'];

    public $detectSubject = [
        // en
        ". Get ready for your trip to", // Booking SZIIPC. Get ready for your trip to Madrid
        '. Information about your upcoming trip to ',
        ": Get your boarding pass",
        'Boarding gate assigned for your flight',
        // es
        ". Prepara tu viaje a", // Reserva K5N3G. Prepara tu viaje a Madrid
        '. Información para tu próximo viaje a ',
        ": solicita la tarjeta de embarque",
        'Puerta de embarque asignada para tu vuelo',
        // pt
        ". Planeje a sua viagem para",
        '. Informações sobre sua viagem em breve para',
        'Portão de embarque atribuído para o seu voo',
        // de
        '. Bereiten Sie Ihre Reise nach',
        '. Informationen für Ihre nächste Reise nach',
        // fr
        ': demandez la carte d\'embarquement',
        '. Préparez votre voyage à',
        '. Informations pour votre prochain voyage à ',
        // it
        ': richiedi la carta d\'imbarco',
        '. Prepara il tuo viaggio a',
        '. Informazioni per il tuo prossimo viaggio a',
    ];

    public $detectBody = [
        "en" => [
            "be sure to make the most of the Iberia travel experience",
            "Check-in is now open for your flight",
            "tomorrow you are flying to",
            "take advantage now to include luggage in your booking",
            "make your upcoming trip just the way you like it",
            'Your boarding gate is',
        ],
        "es" => [
            "aprovecha al máximo la experiencia de viajar con Iberia",
            "aprovecha al mГЎximo la experiencia de viajar con Iberia",
            "Check-in disponible para tu vuelo a",
            "aprovecha ahora para incluir equipaje en tu reserva",
            "aprovecha ahora para añadir más equipaje",
            "mañana vuelas a",
            'prepara tu próximo viaje a tu gusto',
            'Tu puerta de embarque es',
        ],
        "pt" => [
            "aproveite ao máximo a experiência de viajar com a Iberia",
            "aproveite ao mГЎximo a experiГЄncia de viajar com a Iberia",
            'planeje a sua próxima viagem do seu jeito',
            'amanhã você tem um voo para',
            'Check-in disponível para o seu voo',
            'Seu portão de embarque é',
        ],
        "de" => [
            "machen Sie das Beste aus der Flugerfahrung mit Iberia",
            'bereiten Sie Ihre nächste Reise ganz nach Ihren Wünschen vor',
            'morgen fliegen Sie nach',
            'Check-in verfügbar für Ihren Flug',
        ],
        "fr" => [
            'Enregistrement disponible pour votre vol vers',
            'préparez votre prochain voyage comme il vous plaît',
            'demain vous vous envolez pour',
        ],
        "it" => [
            'Check-in disponibile per il tuo volo',
            'prepara il tuo prossimo viaggio a tuo piacimento',
            'domani voli a ',
        ],
    ];

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            "Booking reference"  => ["Booking code", 'Booking reference'],
            //            "Hello " => "",
            //            "Departure" => "",
            //            "Arrival" => "",
        ],
        "es" => [
            "Booking reference"  => ["CГіdigo de reserva", "Código de reserva"],
            "Hello "             => "Hola ",
            "Departure"          => "Salida",
            "Arrival"            => "Llegada",
        ],
        "pt" => [
            "Booking reference"  => ["CГіdigo de reserva", "Código de reserva", 'Código da reserva'],
            "Hello "             => ["OlГЎ ", "Olá ", 'Olá, '],
            "Departure"          => "Partida",
            "Arrival"            => "Chegada",
        ],
        "de" => [
            "Booking reference"  => ["Buchungsnummer", 'Buchungscode'],
            "Hello "             => "Hallo ",
            "Departure"          => "Abflug",
            "Arrival"            => "Ankunft",
        ],
        "fr" => [
            "Booking reference"  => "Code de réservation",
            "Hello "             => ["Bonjour,", "Bonjour "],
            "Departure"          => "Départ",
            "Arrival"            => "Arrivée",
        ],
        "it" => [
            "Booking reference"  => "Codice di prenotazione",
            "Hello "             => "Ciao ",
            "Departure"          => "Partenza",
            "Arrival"            => "Arrivo",
        ],
    ];

    public function parseHtml(Email $email): void
    {
        $xpathNoneDisplay = "not(ancestor::*[contains(translate(@style,' ',''), 'display:none')])";

        $patterns = [
            // 15-07-2024  |  15/07/24  |  15 Jul 2024
            'date' => '\b(?:\d{1,2}[-\/]\d{1,2}[-\/](?:2\d|2\d{3})|\d{1,2}[,. ]+[[:alpha:]]{3,25}[,. ]+(?:2\d|2\d{3}))\b',
            // 4:19PM  |  2:00 p. m.
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            // Mr. Hao-Li Huang
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*?[[:alpha:]]',
        ];

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t("Booking reference"), "translate(.,':','')")}][{$xpathNoneDisplay}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,10})\s*$/"));
        $traveller = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hello ")) . "][{$xpathNoneDisplay}]",
            null, true, "/^\s*{$this->opt($this->t("Hello "))}\s*({$patterns['travellerName']})(?: (?:Mr|Mrs))?[,!]\s*$/u");

        if (empty($traveller) || strlen($traveller) > 1) {
            $f->general()
                ->traveller($traveller, false);
        }

        $xpath = "//tr[count(*) = 3 and *[1][not(.//img) and descendant::text()[normalize-space()][1][" . $this->eq($this->t("Departure")) . "]] and *[2][.//img] and *[3][not(.//img) and descendant::text()[normalize-space()][1][" . $this->eq($this->t("Arrival")) . "]]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("*[2]", $root);
            
            if (empty($flight) && count($this->http->FindNodes("*[normalize-space()]", $root)) === 2) {
                $flight = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $root);
            }

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $departure = implode("\n", $this->http->FindNodes("*[1]/descendant::text()[normalize-space()]", $root));
            $arrival = implode("\n", $this->http->FindNodes("*[3]/descendant::text()[normalize-space()]", $root));

            /* airport code */

            if (preg_match("/^\s*{$this->opt($this->t("Departure"))}\s+(?-i)(?<code>[A-Z]{3})\s*(?<other>[\s\S]*)$/i", $departure, $m)) {
                $s->departure()->code($m['code']);
                $departure = $m['other'];
            }

            if (preg_match("/^\s*{$this->opt($this->t("Arrival"))}\s+(?-i)(?<code>[A-Z]{3})\s*(?<other>[\s\S]*)$/i", $arrival, $m)) {
                $s->arrival()->code($m['code']);
                $arrival = $m['other'];
            }

            /* date & time */

            $dateDep = $dateArr = $timeDep = $timeArr = null;
            $pattern1 = "/(?<other>[\s\S]*?)^(?<date>{$patterns['date']})(?:\s*·\s*(?<time>{$patterns['time']}))?\s*$/mu";

            if (preg_match($pattern1, $departure, $m)) {
                $departure = $m['other'];
                $dateDep = strtotime($this->normalizeDate($m['date']));
                $timeDep = empty($m['time']) ? '' : $m['time'];
            }

            if (preg_match($pattern1, $arrival, $m)) {
                $arrival = $m['other'];
                $dateArr = strtotime($this->normalizeDate($m['date']));
                $timeArr = empty($m['time']) ? '' : $m['time'];
            }

            if ($dateDep && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
            } elseif ($dateDep && $timeDep === '') {
                $s->departure()->day($dateDep)->noDate();
            }

            if ($dateArr && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            } elseif ($dateArr && $timeArr === '') {
                $s->arrival()->day($dateArr)->noDate();
            }

            /* airport name & terminal */

            $regexp1 = "/^(?<name>[[:alpha:]][-\/()[:alpha:] ]+?)?(?:\s*\n\s*Terminal[- ]+(?<terminal>\w+))?\s*$/iu";
            $regexp2 = "/^(?<name>[[:alpha:]][-\/()[:alpha:] ]+?)?\s*(?:\n\s*T(?<terminal>\w+)\s*\n\s*)?,\s*(?<country>[[:alpha:]][-\/()[:alpha:] ]+?)\s*$/u";

            if (preg_match($regexp1, $departure, $m) || preg_match($regexp2, $departure, $m)) {
                $name = implode(', ', array_filter([
                    empty($m['name']) ? '' : $m['name'],
                    empty($m['country']) ? '' : $m['country']
                ]));

                if (!empty($name)) {
                    $s->departure()
                        ->name($name);
                }

                if (!empty($m['terminal'])) {
                    $s->departure()
                        ->terminal($m['terminal']);
                }

                $departure = '';
            }

            if (preg_match($regexp1, $arrival, $m) || preg_match($regexp2, $arrival, $m)) {
                $name = implode(', ', array_filter([
                    empty($m['name']) ? '' : $m['name'],
                    empty($m['country']) ? '' : $m['country']
                ]));

                if (!empty($name)) {
                    $s->arrival()
                        ->name($name);
                }

                if (!empty($m['terminal'])) {
                    $s->arrival()
                        ->terminal($m['terminal']);
                }

                $arrival = '';
            }

            /* noDate */

            if ($dateDep === null && $timeDep === null && !empty($s->getDepCode()) && $departure === '') {
                $s->departure()->noDate();
            }

            if ($dateArr === null && $timeArr === null && !empty($s->getArrCode()) && $arrival === '') {
                $s->arrival()->noDate();
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dfrom) {
            if (strpos($from, $dfrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.iberia.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($dBody)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate(string $str): string
    {
        $in = [
            "/^\s*(\d{1,2})\/(\d{1,2})\/(\d{2})\s*$/", // 15/07/24
        ];
        $out = [
            '$1.$2.20$3',
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
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

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
