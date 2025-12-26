<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelInformationPdf extends \TAccountChecker
{
    public $mailFiles = "iberia/it-641680239.eml, iberia/it-642224244-es.eml, iberia/it-839438271-es.eml, iberia/it-853885761-es.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $subjects = [
        // es
        'Iberia, itinerario e información de su viaje -',
        // en
        'Iberia, itinerary and travel information - ',
    ];
    public static $dictionary = [
        "es" => [
            "Reservation code"                    => "Código de reserva",
            "Passengers"                          => "Pasajeros",
            "Ticket number"                       => ["Numero de billete", "Numero de"],
            "accNumber"                           => ["Nº Pasajero Frecuente", "Pasajero Frecuente"],
            "Flight"                              => "Vuelo",
            "Notifications"                       => "Notificaciones",
            "Ownership"                           => "Compañia",
            "Departure"                           => "Salida",
            "Duration"                            => "Duracion",
            "Without stops"                       => "Sin paradas",
            "Reservation"                         => "Reserva",
            "Reserved seating"                    => "Asiento reservado",
            "for"                                 => "para",
            "Arrival"                             => "Llegada",
            "CONTRACTED FLIGHT AND SERVICES DATA" => "DATOS DE VUELOS Y SERVICIOS CONTRATADOS",
        ],
        "en" => [
            "Reservation code"                    => "Reservation code",
            "Passengers"                          => "Passengers",
            "Ticket number"                       => ["Ticket number", "Ticket"],
            "accNumber"                           => ["Nº Frequent passenger", "Frequent passenger"],
            // "Flight" => "",
            "Notifications"                       => "Notifications",
            "Ownership"                           => "Ownership",
            "Departure"                           => "Departure",
            "Duration"                            => "Duration",
            // "Without stops" => "",
            // "Reservation" => "",
            // "Reserved seating" => "",
            // "for" => "",
            "Arrival"                             => "Arrival",
            "CONTRACTED FLIGHT AND SERVICES DATA" => "CONTRACTED FLIGHT AND SERVICES DATA",
        ],
    ];

    public $detectLang = [
        "es" => ["Passengers\Pasajeros", "Pasajeros\Passengers"],
        "en" => ["Departure\Departure"],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@iberia.es') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            if (strpos($text, "Iberia Lineas") !== false
                && (strpos($text, 'YOUR ITINERARY AND RESERVE INFORMATION') !== false)
                && (strpos($text, 'Airline locator') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]iberia\.es$/', $from) > 0;
    }

    private function parseFlightPDF(Email $email, $text): void
    {
        $patterns = [
            'date' => '\d{1,2}[,. ]*[[:alpha:]]+', // 06 MAY
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName2' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+', // KOH / KIM LENG MR
            'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
        ];

        $f = $email->add()->flight();

        $confirmation = $this->re("/^[ ]*{$this->opt($this->tSpecial('Reservation code'))}\s*[:]+[ ]*([A-Z\d]{5,10})(?:[ ]{2}|$)/m", $text);
        $f->general()->confirmation($confirmation);

        $travellerText = $this->re("/(\s{$this->opt($this->tSpecial('Passengers'))}\s*[:]+[ ]*{$this->opt($this->tSpecial('Ticket number'))}.*)\n+[ ]*{$this->opt($this->tSpecial('CONTRACTED FLIGHT AND SERVICES DATA'))}/s", $text);

        // TODO: rewrote parse travellers, tickets and accounts using an $this->splitCols() (examples: it-839438271-es.eml)

        if (preg_match_all("/^[ ]{0,10}(?<traveller>{$patterns['travellerName2']})\b(?:[ ]*\(CHD\))?\n?[ ]{1,60}(?<ticket>{$patterns['eTicket']})(?:[ ]{2}|$)/mu", $travellerText, $paxMatches, PREG_SET_ORDER)) {
            foreach ($paxMatches as $m) {
                $passengerName = $this->normalizeTraveller($m['traveller']);
                $f->general()->traveller($passengerName, true);
                $f->issued()->ticket($m['ticket'], false, $passengerName);
            }
        }

        if (preg_match_all("/[\s·]*Frequent traveler points loyalty program[\s\-]*([A-Z\d\/]*)\s+/", $text, $m)) {
            $f->setAccountNumbers(array_unique($m[1]), false);
        }

        $segmentText = $this->re("/\n([ ]+{$this->opt($this->tPlusEn('Flight'))}\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5}\b.*)\n[ ]*{$this->opt($this->tSpecial('Notifications'))}\s*:/s", $text);
        $segments = $this->splitText($segmentText, "/^([ ]+{$this->opt($this->tPlusEn('Flight'))}\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5}\b.*\n)/m", true);

        foreach ($segments as $segment) {
            $year = $this->re("/\s(2\d{3})\s/", $segment) ?? '1972';

            $s = $f->addSegment();

            if (preg_match("/[ ]{$this->opt($this->tPlusEn('Flight'))}\s*(?<aName>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fNumber>\d{1,5}\b)/", $segment, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $operator = $this->re("/{$this->opt($this->tSpecial('Ownership'))}\s*[:]+[ ]*([^:\s].+)/", $segment);
                $s->airline()->operator($operator, false, true);
            }

            $codeDep = $codeArr = null;

            if (preg_match_all("/^[ ]*{$this->opt($this->tSpecial('Without stops'))}\W+([A-Z]{3})\W+([A-Z]{3})\W*$/m", $segment, $codeMatches)) {
                if (count(array_unique($codeMatches[1])) === 1) {
                    $codeDep = $codeMatches[1][0];
                }

                if (count(array_unique($codeMatches[2])) === 1) {
                    $codeArr = $codeMatches[2][0];
                }
            }

            if (preg_match("/{$this->opt($this->tSpecial('Departure'))}\s*[:]+[ ]*(?<airport>\D+\(\D*\))\s*(?<date>{$patterns['date']})[,. ]*(?<time>{$patterns['time']})/u", $segment, $m)) {
                // "New York, NYC(New York J F Kennedy International Apt)" NYC is city code, not iata airport code
                $airportDep = preg_replace('/^[^)(]{2,}\(\s*([^)(]{3,}?)\s*\)$/', '$1', $m['airport']);
                $dateDep = $this->normalizeDate($m['date'] . ' ' . $year);
                $s->departure()->name($airportDep)->date(strtotime($m['time'], $dateDep));

                if ($codeDep) {
                    $s->departure()->code($codeDep);
                } else {
                    $s->departure()->noCode();
                }
            }

            $duration = $this->re("/{$this->opt($this->tSpecial('Duration'))}\s*[:]+[ ]*(\d[:\d]+)/", $segment);
            $s->extra()->duration($duration, false, true);

            if (preg_match("/(?:^|\\\)[ ]*{$this->opt($this->tPlusEn('Reservation'))}[ ]*(?<status>[[:alpha:]]{2,30})[ ]*,[ ]*(?<cabin>\w+[ ]*\w*)\s*\(\s*(?<bookingCode>[A-Z]{1,2})\s*\)(?:[ ]*\\\|$)/mu", $segment, $m)) {
                $s->extra()
                    ->status($m['status'])
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode']);
            }

            if (preg_match_all("/{$this->opt($this->tPlusEn('Reserved seating'))}[ ]*[-]+[ ]*(?<seat>\d+[A-Z]\b)(?<extra>.*)/", $segment, $seatMatches, PREG_SET_ORDER)) {
                $seats = [];

                foreach ($seatMatches as $m) {
                    if (!in_array($m['seat'], $seats)) {
                        $passengerName = $this->normalizeTraveller($this->re("/\s{$this->opt($this->tPlusEn('for'))}\s+({$patterns['travellerName2']})$/u", $m['extra']));
                        $s->extra()->seat($m['seat'], false, false, $passengerName);
                        $seats[] = $m['seat'];
                    }
                }
            }

            if (preg_match("/{$this->opt($this->tSpecial('Arrival'))}\s*[:]+[ ]*(?<airport>\D+\(\D*\))\s*(?<date>{$patterns['date']})[,. ]*(?<time>{$patterns['time']})/u", $segment, $m)) {
                // "New York, NYC(New York J F Kennedy International Apt)" NYC is city code, not iata airport code
                $airportArr = preg_replace('/^[^)(]{2,}\(\s*([^)(]{3,}?)\s*\)$/', '$1', $m['airport']);
                $dateArr = $this->normalizeDate($m['date'] . ' ' . $year);
                $s->arrival()->name($airportArr)->date(strtotime($m['time'], $dateArr));

                if ($codeArr) {
                    $s->arrival()->code($codeArr);
                } else {
                    $s->arrival()->noCode();
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            $this->assignLang($text);
            $this->parseFlightPDF($email, $text);
        }

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function tPlusEn(string $s): array
    {
        return array_unique(array_merge((array) $this->t($s), (array) $this->t($s, 'en')));
    }

    private function tSpecial(string $s): array
    {
        $result = [];

        foreach ((array) $this->t($s, 'en') as $phrase1) {
            foreach ((array) $this->t($s) as $phrase2) {
                $result[] = $phrase1 . '\\' . $phrase2;
                $result[] = $phrase1 . '/' . $phrase2;
                $result[] = $phrase2 . '\\' . $phrase1;
                $result[] = $phrase2 . '/' . $phrase1;
            }
        }

        return array_unique($result);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "/^(\d{1,2})[,.\s]*([[:alpha:]]+)[,.\s]*(\d{4})$/u", // 15. APR 2024
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function assignLang($text): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MASTER|MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
            '/^([^\/]+?)(?:\s*[\/]+\s*)+([^\/]+)$/',
        ], [
            '$1',
            '$1',
            '$2 $1',
        ], $s);
    }
}
