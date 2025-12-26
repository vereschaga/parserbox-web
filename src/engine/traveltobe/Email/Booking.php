<?php

namespace AwardWallet\Engine\traveltobe\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "traveltobe/it-11680958.eml, traveltobe/it-12234268.eml, traveltobe/it-1913561.eml, traveltobe/it-2414754.eml, traveltobe/it-2484953.eml, traveltobe/it-30130126.eml, traveltobe/it-30909853.eml, traveltobe/it-31132311.eml, traveltobe/it-5259509.eml, traveltobe/it-5264542.eml, traveltobe/it-5325684.eml, traveltobe/it-5358507.eml, traveltobe/it-5397982.eml, traveltobe/it-5833755.eml, traveltobe/it-5877959.eml, traveltobe/it-5901913.eml, traveltobe/it-5903923.eml, traveltobe/it-5913424.eml, traveltobe/it-6024657.eml";
    public $lang = '';

    public static $dictionary = [
        'en' => [
            //            'Your booking code is' => '',
            //            'Departure:' => '',
            //            'Date:' => '',
            //            'Arrival' => '',
            //            'Duration' => '',
            //            'Operated by' => '',
            //            'Passenger' => '',
            //            'Name:' => '',
            //            'Surname:' => '',
            'Frequent Flyer Number:' => ['Card number:', 'Frequent Flyer Number:'],
            //            'Total amount:' => '',
        ],
        'es' => [
            'Your booking code is' => ['El localizador de tu reserva es', 'El localizador de tu reserva es'],
            'Departure:'           => ['Salida:', 'Partida.:'],
            'Date'                 => 'Fecha',
            'Arrival'              => 'Llegada',
            'Duration'             => 'Duración',
            'Operated by'          => 'Operado por',
            'Passenger'            => 'Pasajero',
            'Name:'                => 'Nombre:',
            'Surname:'             => 'Apellido:',
            //            'Frequent Flyer Number:' => '',
            'Total amount:' => 'Importe total:',
        ],
        'nl' => [
            'Your booking code is'   => 'Uwreserveringscode is',
            'Departure:'             => 'Vertrek:',
            'Date'                   => 'Datum',
            'Arrival'                => 'Aankomst',
            'Duration'               => ['Duur', 'Vluchtduur'],
            'Operated by'            => 'Beheerd door',
            'Passenger'              => 'Passagier',
            'Name:'                  => 'Naam:',
            'Surname:'               => 'Voornaam:',
            'Frequent Flyer Number:' => 'Kaartnummer:',
            'Total amount:'          => 'Totaalbedrag:',
        ],
        'pt' => [
            'Your booking code is'   => ['Seucódigo de reserva', 'Seu código de reserva'],
            'Departure:'             => 'Partida:',
            'Date'                   => 'Data',
            'Arrival'                => 'Chegada',
            'Duration'               => 'Duração',
            'Operated by'            => 'Operado por',
            'Passenger'              => 'Passageiro',
            'Name:'                  => 'Nome:',
            'Surname:'               => 'Sobrenome:',
            'Frequent Flyer Number:' => 'Número de Passageiro Frequente:',
            'Total amount:'          => 'Valor total:',
        ],
        'ja' => [
            'Your booking code is' => 'お客様の予約番号',
            'Departure:'           => '出発',
            'Date'                 => '日付',
            'Arrival'              => '到着',
            'Duration'             => '飛行時間',
            'Operated by'          => '運営',
            'Passenger'            => '搭乗者',
            'Name:'                => '名：',
            'Surname:'             => '姓：',
            //            'Frequent Flyer Number:' => '',
            'Total amount:' => '合計金額：',
        ],
        'it' => [
            'Your booking code is'   => 'Il codice della sua prenotazione è',
            'Departure:'             => 'Partenza:',
            'Date'                   => 'Data',
            'Arrival'                => 'Arrivo',
            'Duration'               => 'Durata',
            'Operated by'            => 'Operato da',
            'Passenger'              => 'Passeggero',
            'Name:'                  => 'Nome:',
            'Surname:'               => 'Cognome:',
            'Frequent Flyer Number:' => 'Numero Frequent Flyer:',
            'Total amount:'          => 'Importo totale:',
        ],
        'de' => [
            'Your booking code is'   => 'Der Buchungscode für Ihre Reservierung ist',
            'Departure:'             => 'Abflug:',
            'Date'                   => 'Datum',
            'Arrival'                => 'Ankunft',
            'Duration'               => 'Reisedauer',
            'Operated by'            => 'Betreiber:',
            'Passenger'              => 'Passagier',
            'Name:'                  => 'Name:',
            'Surname:'               => 'Nachname:',
            'Frequent Flyer Number:' => 'Vielfliegernr',
            'Total amount:'          => 'Gesamtbetrag:',
        ],
        'sv' => [
            'Your booking code is' => 'Din bokningskod är',
            'Departure:'           => 'Avgång:',
            'Date'                 => 'Datum',
            'Arrival'              => 'Ankomst',
            'Duration'             => 'Reslängd',
            //            'Operated by' => '',
            'Passenger' => 'Passagerare',
            'Name:'     => 'Namn:',
            'Surname:'  => 'Efternamn:',
            //            'Frequent Flyer Number:' => '',
            'Total amount:' => 'Total summa:',
        ],
        'fr' => [
            'Your booking code is' => 'Voici votre numéro de réservation :',
            'Departure:'           => 'Départ:',
            'Date'                 => 'Date',
            'Arrival'              => 'Arrivée',
            'Duration'             => 'Durée',
            'Operated by'          => 'Opéré par',
            'Passenger'            => 'Passager',
            'Name:'                => 'Prénom :',
            'Surname:'             => 'Nom :',
            //            'Frequent Flyer Number:' => '',
            'Total amount:' => 'Montant total :',
        ],
        'fi' => [
            'Your booking code is'   => 'Varauskoodisi on',
            'Departure:'             => 'Lähtö:',
            'Date'                   => 'Päivämäärä',
            'Arrival'                => 'Saapuminen',
            'Duration'               => 'Kesto',
            'Operated by'            => 'liikennöi',
            'Passenger'              => 'Matkustaja',
            'Name:'                  => 'Nimi:',
            'Surname:'               => 'Sukunimi:',
            'Frequent Flyer Number:' => 'Kortin numero:',
            'Total amount:'          => 'Kokonaissumma:',
        ],
    ];

    private $detectsFrom = [
        'traveltobe'  => 'travel2be.com',
        'travelgenio' => 'travelgenio.com',
        'tripmonster' => 'tripmonster.com',
    ];

    private $detectSubject = [
        "de"  => " - Reservierung",
        "es"  => "Tu reserva en",
        "es2" => "Su reserva en",
        "es3" => " - Reserva",
        "nl"  => " - Reservering",
        "fi"  => " - Varaus",
        "pt"  => " - Reserva",
        "pt2" => "Sua reserva na",
        "en"  => " - Booking", // +it, ja
        "fr"  => "Votre réservation sur ",
        "sv"  => "Din bokning hos ",
    ];

    private $detectCompany = [
        'traveltobe'  => ['Travel2Be', 'Travel2be'],
        'travelgenio' => ['TravelGenio', 'Travelgenio'],
        'tripmonster' => 'Tripmonster',
    ];

    private $detectBody = [
        "de" => ["Flugdetails", 'Vielen Dank für Ihre Buchung bei Travelgenio'],
        "es" => ["Llegada:"],
        "nl" => ["Vertrek:"],
        "fi" => ["Lähtö:"],
        "pt" => ["Chegada"],
        "en" => ["Departure:"],
        "it" => ["Partenza:"],
        "fr" => ["Départ:"],
        "sv" => ["Avgång:"],
        "ja" => ["出発:"],
    ];

    private $providerCode;
    private $dateUSFormat = false;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response['body']);

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        if (empty($this->providerCode)) {
            foreach ($this->detectCompany as $provider => $dCompany) {
                if ($this->containsText($body, $dCompany) !== false) {
                    $this->providerCode = $provider;

                    break;
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if ($this->lang == 'en') {
            if (!empty($this->http->FindSingleNode("//a[normalize-space() = 'Other contacts' and (contains(@href,'us.travel2be.com') or contains(@href,'travel2be.us'))]"))) {
                $this->dateUSFormat = true;
            }
        }

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = html_entity_decode($this->http->Response['body']);
        $foundCompany = false;

        foreach ($this->detectCompany as $dCompany) {
            if ($this->containsText($body, $dCompany) !== false) {
                $foundCompany = true;

                break;
            }
        }

        if ($foundCompany == false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["subject"])) {
            return false;
        }

        $foundCompany = false;

        if (!empty($headers["from"])) {
            foreach ($this->detectsFrom as $prov => $dfrom) {
                if (stripos($headers["from"], $dfrom) !== false) {
                    $foundCompany = true;
                    $this->providerCode = $prov;

                    break;
                }
            }
        }

        if ($foundCompany === false) {
            foreach ($this->detectCompany as $prov => $dCompany) {
                if ($this->containsText($headers["subject"], $dCompany) !== false) {
                    $foundCompany = true;
                    $this->providerCode = $prov;

                    break;
                }
            }
        }

        if ($foundCompany === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectsFrom as $dfrom) {
            if (stripos($from, $dfrom) !== false) {
                return true;
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

    public static function getEmailProviders()
    {
        return ['traveltobe', 'travelgenio'];
    }

    private function parseEmail(Email $email)
    {
        $confs = array_filter($this->http->FindNodes('//text()[' . $this->contains($this->t('Your booking code is')) . ']/ancestor::*[1]', null,
                '/' . $this->preg_implode($this->t('Your booking code is')) . '\s*([A-Z\d]{5,})/'));

        if (!empty($confs)) {
            foreach ($confs as $conf) {
                $email->ota()
                    ->confirmation($conf);
            }
        } else {
            $email->obtainTravelAgency();
        }

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();
        $travellerRows = $this->http->XPath->query('//tr[ ./td[normalize-space(.)][1][' . $this->contains($this->t('Name:')) . '] and ./td[' . $this->contains($this->t('Surname:')) . '] and not(.//tr) and ./preceding::text()[normalize-space(.)][1][' . $this->contains($this->t('Passenger')) . '] ]');

        foreach ($travellerRows as $row) {
            $name = $this->http->FindSingleNode('./td[' . $this->contains($this->t('Name:')) . ']/following-sibling::td[1]', $row, true, '/^(\w[^:：]+)$/u');
            $surname = $this->http->FindSingleNode('./td[' . $this->contains($this->t('Surname:')) . ']/following-sibling::td[1]', $row, true, '/^(\w[^:：]+)$/u');

            if (!empty($name)) {
                $f->general()->traveller(trim($name . ' ' . $surname));
            }
        }

        // Program
//        $accounts = array_filter($this->http->FindNodes('//*['.$this->contains($this->t("Frequent Flyer Number:"), 'text()').']/following-sibling::td[1]/text()', null, "#^\s*([A-Z\d]{5,})\s*$#"));
//        if (!empty($accounts)) {
//            $f->program()->accounts($accounts, false);
//        }

        // Price
        $chargeRows = $this->http->FindNodes('//td[' . $this->eq($this->t('Total amount:')) . ']/following-sibling::td[1]');

        if (count($chargeRows) > 0) {
            $totalCharge = null;

            if (count($chargeRows) > 1) {
                foreach ($chargeRows as $row) {
                    if (preg_match('/\b[A-Z]{3} \d[,.\'\d]*/', $row) > 0) {
                        $totalCharge = $row;

                        break;
                    }
                }
            } else {
                $totalCharge = array_shift($chargeRows);
            }

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})[ ]*(?<amount>\d[,.\'\d]*)\s*$#", $totalCharge, $m)
                || preg_match("#^\s*(?<amount>\d[,.\'\d]*)[ ]*(?<curr>[^\d\s]{1,5})\s*$#", $totalCharge, $m)
            ) {
                $f->price()
                    ->total($this->amount($m['amount']))
                    ->currency($this->currency($m['curr']));
            }
        }

        // Segments
        $xpath = "//text()[{$this->starts($this->t('Departure:'))}]/ancestor::td[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->getField("Date", $root);

            $flight = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Duration'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#\((.+)\)#");

            if (preg_match("#^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\s*$#", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }
            $operator = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Operated by'))}][1]", $root, true, "#{$this->preg_implode($this->t('Operated by'))}\s(.+)#");

            if (!empty($operator)) {
                $s->airline()->operator($operator);
            }

            // Departure
            $departure = $this->getField("Departure:", $root);

            if (preg_match("#^\s*(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*(.+)\s*$#", $departure, $m)) {
                $s->departure()
                    ->name($m[1] . $m[3])
                    ->code($m[2])
                    ->date($this->normalizeDate($date))
                ;
            }

            // Arrival
            $arrival = $this->getField("Arrival", $root);

            if (preg_match("#^\s*(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*(.+)\s*$#", $arrival, $m)) {
                $s->arrival()
                    ->name($m[1] . $m[3])
                    ->code($m[2])
                ;
            }
            $time = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Arrival'))}]/following::text()[normalize-space(.)!=''][1]", $root);

            if (!empty($date) && preg_match("#^(.+)[ \-]\d{1,2}:#", $date, $m)
                    && preg_match("#(.+?)(?:\(([\+\-]\s*\d)\s*\w+\))?$#u", $time, $mat)) {
                $s->arrival()
                    ->date($this->normalizeDate($m[1] . ' ' . $mat[1]));

                if (!empty($mat[2]) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($mat[2] . ' day', $s->getArrDate()));
                }
            }

            // Extra
            $s->extra()
                ->duration($this->getField("Duration", $root));
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function getField($field, $root = null)
    {
        return $this->http->FindSingleNode(".//text()[{$this->starts($this->t($field))}]", $root, true, "#{$this->preg_implode($this->t($field))}[:\s]*(.+)#");
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

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $str = str_replace([' - ', 'H', '. '], [' ', '', '.'], trim($str));

        if ($this->dateUSFormat == false) {
            $str = str_replace('/', '-', $str);
        }
        $date = strtotime($str);

        if (empty($date) && $this->dateUSFormat == false) {
            $str = strtotime(preg_replace("#^\s*(\d{1,2})\W(\d{1,2})\W(\d{4})(\\s+.+)?\s*$#", '$2.$1.$3', $str));

            if (!empty($str)) {
                $date = $str;
                $this->dateUSFormat = true;
            }
        }

        return $date;
    }

    private function amount($s)
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $s;
        }
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }
        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }
        return false;
    }
}
