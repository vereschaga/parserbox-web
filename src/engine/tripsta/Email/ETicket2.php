<?php

namespace AwardWallet\Engine\tripsta\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicket2 extends \TAccountChecker
{
    public $mailFiles = "tripsta/it-11056104.eml, tripsta/it-11211145.eml, tripsta/it-11231178.eml, tripsta/it-12232753.eml, tripsta/it-132058451.eml, tripsta/it-1855296.eml, tripsta/it-2275125.eml, tripsta/it-2691492.eml, tripsta/it-2934859.eml, tripsta/it-3645620.eml, tripsta/it-3664481.eml, tripsta/it-3759005.eml, tripsta/it-4829417.eml, tripsta/it-4923043.eml, tripsta/it-6170897.eml, tripsta/it-6179735.eml, tripsta/it-6441591.eml, tripsta/it-6632189.eml, tripsta/it-8821483.eml, tripsta/it-8894510.eml";

    protected $lang = '';

    protected $langDetectors = [
        "en" => ["This is your e-ticket"],
        "hu" => ["Ez az elektronikus repülőjegye"],
        "pt" => ["Este é o seu bilhete eletrónico"],
        "de" => ["Dies ist Ihre E-Ticket-Bestätigung"],
        "no" => ["Dette er din billettkvittering"],
        "it" => ["Questa è la sua ricevuta di biglietto elettronico"],
        "nl" => ["Dit is het ontvangstbewijs van uw e-ticket"],
        "es" => ["Este es su billete electrónico"],
        "pl" => ["To jest bilet elektroniczny"],
        "fr" => ["Ceci est votre billet électronique"],
        "fi" => ["Tämä on e-lippusi kuitti"],
        "ko" => ["귀하의 전자 티켓 영수증입니다"],
    ];

    protected static $dictionary = [
        "en" => [
            "Reservation number"     => ["Reservation number", "reservation number", "Reservation number:"],
            "Passengers information" => ["Passengers", "Passengers information"],
            "Flight information"     => ["Return", "Flight information", "Departure"],
        ],
        "hu" => [
            "Airline reservation No:" => "Légitársaság foglalási száma:",
            "Reservation number"      => "foglalási szám:",
            "Passengers"              => "Utas információk",
            "First Name"              => "Keresztnév",
            " to "                    => " - ",
            "Flight duration:"        => "Repülés időtartalma:",
            "Stops:"                  => "Átszállás:",
            "Equipment:"              => "Repülőgép típusa:",
            "Class:"                  => "Osztály:",
            "Operated by"             => "Operated by",
            "Flight information"      => "Járat információ",
            "Passengers information"  => "Utas információk",
        ],
        "pt" => [
            "Airline reservation No:" => "Número da reserva na companhia aérea:",
            "Reservation number"      => "número da reserva:",
            "Passengers"              => "Informação sobre os passageiros",
            "First Name"              => "Primeiro nome",
            " to "                    => " para ",
            "Flight duration:"        => "Duração do voo:",
            "Stops:"                  => "Escala:",
            "Equipment:"              => "Tipo de avião:",
            "Class:"                  => "Classe:",
            //			"Operated by" => "",
            "Flight information"     => "Informação relativa ao voo",
            "Passengers information" => "Informação sobre os passageiros",
        ],
        "de" => [
            "Airline reservation No:" => "Buchungscode der Fluggesellschaft:",
            "Reservation number"      => "Buchungsnummer:",
            "Passengers"              => "Passagierangaben",
            "First Name"              => "Vorname",
            " to "                    => " bis ",
            "Flight duration:"        => "Flugdauer:",
            "Stops:"                  => "Stopp(s):",
            "Equipment:"              => "Flugzeugtyp:",
            "Class:"                  => "Klasse:",
            //			"Operated by" => "",
            "Flight information"     => "Fluginformationen",
            "Passengers information" => "Passagierangaben",
        ],
        "no" => [
            "Airline reservation No:" => "Reservasjonsnummer :",
            "Reservation number"      => "tripsta.no Reservasjonsnummer:",
            "Passengers"              => "Passasjerinformasjon",
            "First Name"              => "Fornavn",
            " to "                    => " til ",
            "Flight duration:"        => "Flytid:",
            "Stops:"                  => "Stopp:",
            "Equipment:"              => "Utstyr:",
            "Class:"                  => "Klasse:",
            "Operated by"             => "Betjent av",
            "Flight information"      => "Flyinformasjon",
            "Passengers information"  => "Passasjerinformasjon",
        ],
        "it" => [
            "Airline reservation No:" => "Codice di volo della compagnia aerea:",
            "Reservation number"      => "Numero di conferma prenotazione:",
            "Passengers"              => "Informazioni dei Passeggeri",
            "First Name"              => "Nome",
            " to "                    => " - ",
            "Flight duration:"        => "Durata del volo:",
            "Stops:"                  => "Scali:",
            "Equipment:"              => "Tipo di aereo:",
            "Class:"                  => "Classe:",
            "Operated by"             => "Operato da",
            "Flight information"      => "Informazioni di volo",
            "Passengers information"  => "Informazioni dei Passeggeri",
        ],
        "nl" => [
            "Airline reservation No:" => "Vlucht-reserveringsnummer:",
            "Reservation number"      => "Reserveringsnummer",
            "Passengers"              => "Passagiersinformatie",
            "First Name"              => "Voornaam",
            " to "                    => " naar ",
            "Flight duration:"        => "Duur van de vlucht:",
            "Stops:"                  => "Tussenlandingen:",
            "Equipment:"              => "Apparatuur:",
            "Class:"                  => "Klasse:",
            "Operated by"             => "Bediend door",
            "Flight information"      => "Vluchtinformatie",
            "Passengers information"  => "Passagiersinformatie",
        ],
        "es" => [
            "Airline reservation No:" => "No. de la reserva de vuelo:",
            "Reservation number"      => "Número de reserva",
            "Passengers"              => "Pasajeros",
            "First Name"              => "Nombre",
            " to "                    => " a ",
            "Flight duration:"        => "Duración del vuelo:",
            "Stops:"                  => "Escalas:",
            "Equipment:"              => "Tipo del avión:",
            "Class:"                  => "Clase:",
            //			"Operated by" => "",
            "Flight information"     => "Ida",
            "Passengers information" => "Pasajeros",
        ],
        "pl" => [
            "Airline reservation No:" => "Numer rezerwacji linii lotniczych:",
            "Reservation number"      => "Numer rezerwacji:",
            "Passengers"              => ["Informacje o pasażerach", "Pasażerowie"],
            "First Name"              => "Imię",
            " to "                    => " do ",
            "Flight duration:"        => "Czas trwania lotu:",
            "Stops:"                  => "Przesiadek:",
            "Equipment:"              => "Typ samolotu:",
            "Class:"                  => "Klasa:",
            //			"Operated by" => "",
            "Flight information"     => "Informacje o lotach",
            "Passengers information" => "Informacje o pasażerach",
        ],
        "fr" => [
            "Airline reservation No:" => "N°. réservation ligne aérienne:",
            "Reservation number"      => "numéro de réservation",
            "Passengers"              => ["Passagers"],
            "First Name"              => "Prénom",
            " to "                    => " à ",
            "Flight duration:"        => "Durée du vol:",
            "Stops:"                  => "Escales:",
            "Equipment:"              => "Type d'avion:",
            "Class:"                  => "Classe:",
            //			"Operated by" => "",
            "Flight information"     => "Départ",
            "Passengers information" => "Passagers",
        ],
        "fi" => [
            "Airline reservation No:" => "Lentoyhtiön varausnumero:",
            "Reservation number"      => "Varausnumero",
            "Passengers"              => ["Matkustajien tiedot"],
            "First Name"              => "Etunimi",
            " to "                    => " -lle ",
            "Flight duration:"        => "Lennon kesto:",
            "Stops:"                  => "Pysähdykset:",
            "Equipment:"              => "Varusteet:",
            "Class:"                  => "Luokka:",
            //			"Operated by" => "",
            "Flight information"     => "Lennon tiedot",
            "Passengers information" => "Matkustajien tiedot",
            "Terminal"               => 'Terminaali',
        ],
        "ko" => [
            "Airline reservation No:" => "항공사 예약 번호:",
            "Flight information"      => "항공편 정보",
            "Reservation number"      => "예약 번호:",
            "Passengers"              => ["승객 정보"],
            "First Name"              => "이름",
            " to "                    => " 에서 ",
            "Flight duration:"        => "총 비행 시간:",
            "Stops:"                  => "경유:",
            "Equipment:"              => "장비:",
            "Class:"                  => "등급:",
            //			"Operated by" => "",
            "Terminal"               => "터미널",
            "Passengers information" => "승객 정보",
        ],
    ];

    protected $patterns = [
        'accountNumber' => '/^(\d{5,})$/',
        'ticketNumber'  => '/^(\d+[-\s]*\d{4,})$/',
        'date'          => '(\d{1,2}[.\/\-]\d{1,2}[.\/\-]\d{4}|\d{4}[.]\d{1,2}[.]\d{1,2})',
        'code'          => '/\(([A-Z]{3})\)/',
        'time'          => '(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)',
        'terminal'      => '/(?:Terminal|Terminaali|터미널)\s+([A-Z\d]+)/i',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tripsta.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'reply@tripsta.') === false && stripos($headers['from'], 'service@tripsta.') === false) {
            return false;
        }

        return stripos($headers['subject'], 'E-ticket') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $from = $parser->getHeader('from');
        $subject = $parser->getHeader('subject');

        $condition1 = $this->http->XPath->query('//node()[contains(.,"tripsta.")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.tripsta.")]')->length === 0;
        $condition3 = self::detectEmailFromProvider($from) || self::detectEmailByHeaders(['from' => $from, 'subject' => $subject]);

        if ($condition1 && $condition2 && $condition3 === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $this->date = strtotime($parser->getHeader('date'));

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    protected function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Passengers information'))}]/preceding::text()[{$this->contains($this->t('Reservation number'))}][1]/following::text()[normalize-space()][1]"))
            ->travellers(str_replace('N/A', '', $this->http->FindNodes("//text()[{$this->eq($this->t('First Name'))}]/ancestor::table[1]/descendant::tr[not({$this->contains($this->t('First Name'))})]", null, "/^(\D+)/")));

        $tickets = $this->http->FindNodes("//text()[{$this->eq($this->t('First Name'))}]/ancestor::table[1]/descendant::tr[not({$this->contains($this->t('First Name'))})]/descendant::td[normalize-space()][last()]");

        if (count($tickets) > 0) {
            $f->setTicketNumbers(array_unique($tickets), false);
        }

        $accounts = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('First Name'))}]/ancestor::table[1]/descendant::tr[not({$this->contains($this->t('First Name'))})]/descendant::td[normalize-space()][3]", null, "/^\s*([\d\-]+)$/"));

        if (count($accounts) > 0) {
            $f->setAccountNumbers(array_unique($accounts), false);
        }

        $mainXpath = "//text()[{$this->eq($this->t('Class:'))}]/ancestor::tr[contains(normalize-space(), '(') and contains(normalize-space(), ':')][1]";
        $mainNode = $this->http->XPath->query($mainXpath);

        foreach ($mainNode as $root) {
            $s = $f->addSegment();

            $dateDep = $this->normalizeDate($this->http->FindSingleNode("./preceding::td[{$this->contains($this->t(' to '))}][1]", $root, true, "/\s([\d\/\-\.]{6,})/"));

            $operator = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Equipment:'))}]/ancestor::table[1]/descendant::text()[{$this->eq($this->t('Operated by'))}]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $airlineInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Equipment:'))}]/ancestor::table[1]/descendant::text()[{$this->eq($this->t('Operated by'))}]/preceding::text()[normalize-space()][1]", $root);

            if (empty($airlineInfo)) {
                $airlineInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Equipment:'))}]/ancestor::table[1]/descendant::tr[normalize-space()][last()]", $root);
            }

            if (!empty($airlineInfo) && preg_match("/\([A-Z]{3}\)/", $airlineInfo)) {
                $airlineInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Equipment:'))}]/ancestor::table[1]/descendant::tr[normalize-space()][last()]/descendant::td[last()]/descendant::text()[normalize-space()][last()]", $root);
            }

            if (preg_match("/([A-Z\d]{2})\s*(\d{1,4})/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $depInfo = $this->http->FindSingleNode("./descendant::td[contains(normalize-space(), '(') and contains(normalize-space(), ':')][1]", $root);

            if (!empty($depInfo) && stripos($depInfo, $this->t('Equipment:')) !== false) {
                $depInfo = $this->http->FindSingleNode("./descendant::td[contains(normalize-space(), '(') and contains(normalize-space(), ':')][2]/descendant::td[1]", $root);
            }

            if (empty($depInfo)) {
                $depInfo = $this->http->FindSingleNode("./descendant::td[contains(normalize-space(), '(') and contains(normalize-space(), ':')][1]/descendant::td[2]", $root);
            }

            if (empty($depInfo)) {
                $depInfo = $this->http->FindSingleNode("./descendant::text()[(normalize-space(.)='Class:')]/ancestor::table[1]/ancestor::tr[1]/descendant::td[1]", $root);
            }

            if (preg_match("/\(([A-Z]{3})\)\s*(\d+\:\d+)/", $depInfo, $m)) {
                $s->departure()
                    ->code($m[1])
                    ->date(strtotime($dateDep . ', ' . $m[2]));

                $depTerminal = $this->re("/{$this->opt($this->t('Terminal'))}\s*(\S+)/u", $depInfo);

                if (!empty($depTerminal)) {
                    $s->departure()
                        ->terminal($depTerminal);
                }
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::td[contains(normalize-space(), '(') and contains(normalize-space(), ':')][2]", $root);

            if (!empty($arrInfo) && stripos($arrInfo, $this->t('Equipment:')) !== false) {
                $arrInfo = $this->http->FindSingleNode("./descendant::td[contains(normalize-space(), '(') and contains(normalize-space(), ':')][2]/descendant::td[2]", $root);
            }

            if (empty($arrInfo)) {
                $arrInfo = $this->http->FindSingleNode("./descendant::text()[(normalize-space(.)='Class:')]/ancestor::table[1]/ancestor::tr[1]/descendant::td[contains(normalize-space(), '(') and contains(normalize-space(), ':')][2]", $root);
            }

            if (preg_match("/\(([A-Z]{3})\)\s*(\d+\:\d+)/", $arrInfo, $m)) {
                $s->arrival()
                    ->code($m[1])
                    ->date(strtotime($dateDep . ', ' . $m[2]));

                $arrTerminal = $this->re("/{$this->opt($this->t('Terminal'))}\s*(\S+)/u", $arrInfo);

                if (!empty($arrTerminal)) {
                    $s->arrival()
                        ->terminal($arrTerminal);
                }
            }

            $duration = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight duration:'))}]/following::text()[normalize-space()][1]", $root);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $cabin = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Class:'))}]/following::text()[normalize-space()][1]", $root);

            if (!empty($cabin)) {
                if (preg_match("/^(\D+)\s*\(([A-Z])\)/", $cabin, $m)) {
                    $s->extra()
                        ->cabin($m[1])
                        ->bookingCode($m[2]);
                } else {
                    $s->extra()
                        ->cabin($cabin);
                }
            }

            $aircrat = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Equipment:'))}]/following::text()[normalize-space()][1]", $root);

            if (!empty($aircrat)) {
                $s->extra()
                    ->aircraft($aircrat);
            }
        }
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    protected function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    protected function getField($field, $root = null)
    {
        if (!is_array($field)) {
            $field = [$field];
        }
        $rule = implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));

        return $this->http->FindSingleNode("./descendant::text()[{$rule}]/following::text()[normalize-space(.)][1]", $root);
    }

    protected function normalizeDate($string)
    {
        $in = '/(.+)/';
        $out = '$1';

        switch ($this->lang) {
            case 'hu':
                $in = '/(\d{4})\.(\d{1,2})\.(\d{1,2})[.]?$/';
                $out = '$3.$2.$1';

                break;

            case 'en':
                $in = '/(\d{1,2})\/(\d{1,2})\/(\d{4})$/';
                preg_match($in, $string, $matches);

                if (isset($matches[2]) && (int) $matches[2] > 12) {
                    $out = '$2.$1.$3';
                } else {
                    $out = '$1.$2.$3';
                }

                break;

            case 'pt':
            case 'no':
            case 'it':
            case 'nl':
            case 'es':
            case 'fr':
            case 'ko':
            case 'fi':
                $in = '/(\d{1,2})\/(\d{1,2})\/(\d{4})$/';
                preg_match($in, $string, $matches);

                if (isset($matches[2]) && (int) $matches[2] > 12) {
                    $out = '$2.$1.$3';
                } else {
                    $out = '$1.$2.$3';
                }

                break;

            case 'pl':
                $in = '/(\d{1,2})-(\d{1,2})-(\d{4})$/';
                preg_match($in, $string, $matches);
                $out = '$1.$2.$3';

                break;
        }

        return preg_replace($in, $out, $string);
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    protected function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
