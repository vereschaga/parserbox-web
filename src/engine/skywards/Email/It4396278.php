<?php

namespace AwardWallet\Engine\skywards\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class It4396278 extends \TAccountChecker
{
    public $mailFiles = "skywards/it-4242935.eml, skywards/it-4313787.eml, skywards/it-4396278.eml, skywards/it-4429779.eml, skywards/it-4444072.eml, skywards/it-4446195.eml, skywards/it-4688948.eml, skywards/it-4711224.eml, skywards/it-6258443.eml, skywards/it-7409252.eml, skywards/it-7436720.eml, skywards/it-7471430.eml";
    public $reSubject = [
        "Confirmation de la carte d'embarquement mobile d'Emirates",
        'Emirates mobile boarding pass confirmation',
        'Emirates Airline Mobile Boarding Pass Confirmation',
        'Confirmação do cartão de embarque mobile da Emirates',
        'Confirmación en el teléfono móvil de la tarjeta de embarque de Emirates',
        'Bestätigung der Emirates Airline Handy-Bordkarte',
        '阿聯酋航空手機電子登機證確認',
        "Conferma della carta d'imbarco mobile Emirates",
    ];
    public $reBody = [
        'fr' => [
            ['Référence de réservation', "Carte d'embarquement mobile"],
        ],
        'en' => [
            ['Booking Reference', 'You requested a mobile boarding pass'],
            ['Booking reference', 'You requested a mobile boarding pass'],
        ],
        'pt' => [
            ['Referência da Reserva', 'Cartão de embarque mobile'],
            ['Código da reserva', 'Cartão de embarque móvel'],
            ['Referência de reserva', 'Cartão de embarque para celular'],
        ],
        'es' => [
            ['Referencia de la reserva', 'Referencia de la reserva'],
            ['Referencia de la reserva', 'Tarjeta de embarque móvil'],
        ],
        'de' => [
            ['Buchungsnummer', 'Sie haben die Zusendung einer Handy-Bordkarte'],
            ['Buchungsnummer', 'Sie haben die Zusendung einer mobilen'],
        ],
        'zh' => [
            ['預訂代號', '電子登機證'],
        ],
        'it' => [
            ['Codice di prenotazione', "Carta d'imbarco mobile"],
        ],
    ];

    private $lang = '';
    private $reFrom = ['reply@emirates.com'];
    private $reProvider = ['Emirates', 'emirates.com'];

    private static $dictionary = [
        'fr' => [
            'CONFNO' => ['Référence de réservation', 'Référence de la réservation'],
        ],
        'en' => [
            'CONFNO'   => ['Booking reference', 'Booking Reference', 'Reservation reference'],
            'Passager' => 'Passenger',
            'Numéro'   => 'Number',
            'Aéroport' => 'Airport',
            'Date'     => 'Date',
            'Heure'    => 'Time',
            'Appareil' => 'Aircraft',
            'Classe'   => 'Class',
            'Durée'    => 'Duration',
        ],
        'pt' => [
            'CONFNO'   => ['Referência da Reserva', 'Código da reserva', 'Referência de reserva'],
            'Passager' => 'Passageiro',
            'Numéro'   => 'Número',
            'Aéroport' => 'Aeroporto',
            'Date'     => 'Data',
            'Heure'    => ['Part.', 'Hora', 'Horário'],
            'Appareil' => ['Aeronave', 'Avião'],
            'Classe'   => 'Classe',
            'Durée'    => 'Duração',
        ],
        'es' => [
            'CONFNO'   => 'Referencia de la reserva',
            'Passager' => 'Pasajero',
            'Numéro'   => 'Número',
            'Aéroport' => 'Aeropuerto',
            'Date'     => 'Fecha',
            'Heure'    => 'Hora',
            'Appareil' => 'Avión',
            'Classe'   => 'Clase',
            'Durée'    => 'Duración',
        ],
        'de' => [
            'CONFNO'   => 'Buchungsnummer',
            'Passager' => 'Passagier',
            'Numéro'   => 'Nummer',
            'Aéroport' => 'Flughafen',
            'Date'     => 'Datum',
            'Heure'    => 'Uhrzeit',
            'Appareil' => 'Flugzeugtyp',
            'Classe'   => 'Klasse',
            'Durée'    => 'Dauer',
        ],
        'zh' => [
            'CONFNO'   => '預訂代號',
            'Passager' => '乘客',
            'Numéro'   => '號碼',
            'Aéroport' => '機場',
            'Date'     => '日期',
            'Heure'    => '時間',
            'Appareil' => '機型',
            'Classe'   => '客艙',
            'Durée'    => '期間',
        ],
        'it' => [
            'CONFNO'   => ['Codice prenotazione', 'Codice di prenotazione'],
            'Passager' => 'Passeggero',
            'Numéro'   => 'Numero',
            'Aéroport' => 'Aeroporto',
            'Date'     => 'Data',
            'Heure'    => 'Ora',
            'Appareil' => 'Aereo',
            'Classe'   => 'Classe',
            'Durée'    => 'Durata',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
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

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0
            && $this->http->XPath->query("//a[{$this->contains($this->reProvider, '@href')}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    private function parseHtml(Email $email)
    {
        $patterns = [
            'code'     => '/\b([A-Z]{3})\b/',
            'terminal' => '/Terminal\s*([\d\w\s]+)$/i',
        ];

        $f = $email->add()->flight();
        $f->general()->confirmation($this->nextText($this->t('CONFNO')));
        $passenger = $this->nextText($this->t('Passager'));

        if ($passenger) {
            $f->general()->traveller($passenger);
        }

        $xpath = "//text()[{$this->eq($this->t('Numéro'))}]/ancestor::tr[1]/preceding::tr[1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug("segments root not found: {$xpath}");
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();
            $flight = $this->nextText($this->t('Numéro'), $root);

            if (preg_match('/^([A-Z\d]{2})\s*(\d+)$/', $flight, $matches)) {
                $s->airline()->name($matches[1]);
                $s->airline()->number($matches[2]);
            }
            $timeDep = $this->nextText($this->t('Heure'), $root, 1);
            $dateDep = $this->nextText($this->t('Date'), $root, 1);

            if ($timeDep && $dateDep) {
                $dateDep = $this->normalizeDate($dateDep);

                if ($dateDep) {
                    $s->departure()->date(strtotime($dateDep . ', ' . $timeDep));
                }
            }
            $timeArr = $this->nextText($this->t('Heure'), $root, 2);
            $dateArr = $this->nextText($this->t('Date'), $root, 2);

            if ($timeArr && $dateArr) {
                $dateArr = $this->normalizeDate($dateArr);

                if ($dateArr) {
                    $s->arrival()->date(strtotime($dateArr . ', ' . $timeArr));
                }
            }
            $s->departure()->code($this->re($patterns['code'], $this->nextText($this->t('Aéroport'), $root, 1)));
            $s->arrival()->code($this->re($patterns['code'], $this->nextText($this->t('Aéroport'), $root, 2)));
            $departure = $this->http->FindSingleNode("./following::td[{$this->eq($this->t('Aéroport'))} and not(.//td)][1]/following-sibling::td[1]", $root);

            if (preg_match($patterns['terminal'], $departure, $matches)) {
                $s->departure()->terminal($matches[1]);
            }
            $arrival = $this->http->FindSingleNode("./following::td[{$this->eq($this->t('Aéroport'))} and not(.//td)][2]/following-sibling::td[1]", $root);

            if (preg_match($patterns['terminal'], $arrival, $matches)) {
                $s->arrival()->terminal($matches[1]);
            }

            $duration = $this->nextText($this->t('Durée'), $root);

            if ($duration != '-') {
                $s->extra()->duration($duration);
            }
            $s->extra()->cabin($this->nextText($this->t('Classe'), $root));
            $s->extra()->aircraft($this->nextText($this->t('Appareil'), $root));
        }
    }

    private function nextText($field, $root = null, $n = 1)
    {
        if ($root === null) {
            $root = $this->http->XPath->query("./descendant::text()[1]")->item(0);
        }

        return $this->http->FindSingleNode("(./following::text()[" . $this->eq($field) . "])[{$n}]/following::text()[string-length(normalize-space(.))>0][1]", $root);
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $this->t($field);

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function normalizeDate($string)
    {
        $day = $month = $year = null;

        if (preg_match('/(\d{1,2})\s+([^\d\s]+)\s+(\d{2})$/u', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
        }

        if ($day && $month && $year) {
            if (preg_match('/^\s*\d{1,2}\s*$/', $month)) {
                return $day . '.' . $month . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return $string;
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
