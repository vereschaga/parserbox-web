<?php

namespace AwardWallet\Engine\lotpair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PrepareFlight extends \TAccountChecker
{
    public $mailFiles = "lotpair/it-575102596.eml, lotpair/it-575926102.eml, lotpair/it-576689144.eml, lotpair/it-582514238.eml, lotpair/it-583653631.eml, lotpair/it-584793484.eml";
    public $subjects = [
        'Prepare for your flight. Check reservation',
        'Préparez votre vol. Vérifiez votre réservation',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['Manage booking', 'Check'],
        'fr' => ['Vérifiez'],
    ];

    public $detectLangProv = [
        'en' => ['My booking', 'Check'],
        'fr' => ['Mes reservations'],
        'es' => ['Mis reservas'],
        'uk' => ['Мої бронювання'],
        'pl' => ['Moje rezerwacje'],
    ];

    public static $dictionary = [
        "en" => [
            'Departure:'                   => ['Departure:', 'First flight'],
            'Return:'                      => ['Return:', 'Second flight', 'Third flight'],
            'Manage booking'               => ['Manage booking', 'Check'],
            'prepare for your flight from' => ['prepare for your flight from', 'prepare for your travel from', 'Your check-in is already available'],
        ],
        "fr" => [
            'prepare for your flight from' => 'préparez votre voyage de',
            'Manage booking'               => 'Vérifiez',

            'Online check-in will be available' => 'L’enregistrement en ligne sera disponible',
            'Your booking reference:'           => 'Votre référence de réservation:',
            'My booking'                        => 'Mes reservations',
            'Adult'                             => ['Adulte', 'adulte'],
            //'Child' => '',
            //'Young' => '',
            'CHANGE DATA'  => 'CHANGER DES DONNÉES',
            'Ticket price' => 'Prix du billet',
            'Extras:'      => 'Extras:',
            'Departure:'   => 'Départ:',
            //'Return:' => '',
            'Seat selection'  => 'Sélection de sièges',
            //'Meal' => '',
            'Fare conditions' => 'Conditions tarifaires',
        ],
        "es" => [
            'prepare for your flight from' => 'prepare for your flight from',
            'Manage booking'               => 'Manage booking',

            //'Online check-in will be available' => '',
            'Your booking reference:'           => 'Your booking reference:',
            'My booking'                        => 'Mis reservas',
            'Adult'                             => ['Adulto'],
            //'Child' => '',
            //'Young' => '',
            'CHANGE DATA'  => 'CAMBIAR LOS DATOS',
            'Ticket price' => 'Precio del billete',
            //'Extras:'      => '',
            'Departure:'   => 'SALIDA',
            'Return:'      => 'REGRESO',
            //'Seat selection'  => '',
            //'Meal' => '',
            'Fare conditions' => 'Condiciones de Tarifa',
        ],
        "uk" => [
            'prepare for your flight from' => 'prepare for your flight from',
            'Manage booking'               => 'Manage booking',

            //'Online check-in will be available' => '',
            'Your booking reference:'           => 'Your booking reference:',
            'My booking'                        => 'Мої бронювання',
            'Adult'                             => ['дорослий'],
            //'Child' => '',
            //'Young' => '',
            'CHANGE DATA'  => 'ЗМІНИТИ ДАНІ',
            //'Ticket price' => '',
            //'Extras:'      => '',
            'Departure:'   => 'ВІДПРАВЛЕННЯ',
            //'Return:'      => '',
            //'Seat selection'  => '',
            //'Meal' => '',
            'Fare conditions' => 'Умови тарифу',
        ],
        "pl" => [
            'prepare for your flight from' => 'prepare for your flight from',
            'Manage booking'               => 'Manage booking',

            //'Online check-in will be available' => '',
            'Your booking reference:'           => 'Your booking reference:',
            'My booking'                        => 'Moje rezerwacje',
            'Adult'                             => ['Dorosły'],
            //'Child' => '',
            //'Young' => '',
            'CHANGE DATA'  => 'ZMIEŃ DANE',
            'Ticket price' => 'Cena biletu',
            //'Extras:'      => '',
            'Departure:'      => 'Wylot',
            'Return:'         => 'Powrót',
            'Seat selection'  => 'Wybrane miejsce',
            //'Meal' => '',
            'Fare conditions' => 'Warunki Taryfy',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@preflight.lot.com') !== false) {
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
        $this->AssignLang();

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'LOT Polish Airlines')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('prepare for your flight from'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Manage booking'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]preflight.lot.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $url = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking reference:'))}]/following::a[{$this->eq($this->t('Manage booking'))}][1]/@href");

        if (!empty($url)) {
            $http2 = clone $this->http;

            $http2->setRandomUserAgent();
            $http2->GetURL($url);

            foreach ($this->detectLangProv as $lang => $words) {
                foreach ($words as $word) {
                    if ($http2->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                        $this->lang = $lang;
                    }
                }
            }

            $f = $email->add()->flight();
            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking reference:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/");

            if (empty($confirmation)) {
                $confirmation = $http2->FindSingleNode("//text()[{$this->eq($this->t('My booking'))}]/following::text()[{$this->starts($this->t('Online check-in will be available'))}][1]/preceding::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/");
            }

            $f->general()
                ->confirmation($confirmation)
                ->travellers(array_unique($http2->FindNodes("//text()[{$this->eq($this->t('Adult'))} or {$this->eq($this->t('Child'))} or {$this->eq($this->t('Young'))}]/preceding::text()[normalize-space()][not({$this->contains($this->t('passenger'))})][1][string-length()>2]")));

            $tickets = $http2->FindNodes("//text()[{$this->eq($this->t('CHANGE DATA'))}]/preceding::text()[normalize-space()][1]", null, "/(?:^|\s)(\d{3}\-\d+)$/");

            if (count($tickets) > 0) {
                $f->setTicketNumbers(array_filter(array_unique($tickets)), false);
            }

            $price = $http2->FindSingleNode("//text()[{$this->eq($this->t('Ticket price'))}]/ancestor::div[1]");

            if (preg_match("/{$this->opt($this->t('Ticket price'))}\s+(?<cost>[\d\s\.\,]+)\s+(?<currency>[A-Z]{3}).*{$this->opt($this->t('Extras:'))}\s*(?<tax>[\d\s\,\.]+)\s+.*[=]\s+(?<total>[\d\s\.\,]+)\s+/", $price, $m)
            || preg_match("/{$this->opt($this->t('Ticket price'))}\s+(?<total>[\d\s\.\,]+)\s+(?<currency>[A-Z]{3})\s*\D*$/", $price, $m)) {
                $f->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);

                if (isset($m['tax']) && !empty($m['tax'])) {
                    $f->price()
                        ->tax(PriceHelper::parse($m['tax'], $m['currency']));
                }

                if (isset($m['cost']) && !empty($m['cost'])) {
                    $f->price()
                        ->cost(PriceHelper::parse($m['cost'], $m['currency']));
                }
            }

            $nodes = $http2->XPath->query("//text()[{$this->eq($this->t('Departure:'))} or {$this->eq($this->t('Return:'))}]");

            foreach ($nodes as $root) {
                $s = $f->addSegment();

                $seats = $http2->FindNodes("./ancestor::div[normalize-space()][2]/following::div[1]/descendant::text()[{$this->eq($this->t('Seat selection'))}]/following::text()[normalize-space()][1]", $root);

                if (count($seats) > 0) {
                    $s->setSeats($seats);
                }

                $meal = $http2->FindNodes("./ancestor::div[normalize-space()][2]/following::div[1]/descendant::text()[{$this->eq($this->t('Meal'))}]/following::text()[normalize-space()][1]", $root);

                if (count($meal) > 0) {
                    $s->setMeals(array_unique($meal));
                }

                $dateInfo = $http2->FindSingleNode("./following::text()[normalize-space()][1]/ancestor::div[2]", $root);
                $depDate = '';

                if (preg_match("/\w+\,\s*(?<depDate>\d+\s*\w+\s*\d{4})\s*.*\((?<depCode>[A-Z]{3})\)\-.*\((?<arrCode>[A-Z]{3})\)/u", $dateInfo, $m)
                || preg_match("/\w+\,\s*(?<depDate>\d+\s*\w+\s*\d{4})\s*\d+\:\d+\D*\((?<depCode>[A-Z]{3})\)\s*\d+\:\d+\D*\((?<arrCode>[A-Z]{3})\)/u", $dateInfo, $m)) {
                    $depDate = $m['depDate'];

                    $s->departure()
                        ->code($m['depCode']);

                    $s->arrival()
                        ->code($m['arrCode']);
                }

                $airline = $http2->FindSingleNode("./following::text()[{$this->eq($this->t('Fare conditions'))}][1]/preceding::text()[normalize-space()][1]/ancestor::div[1]", $root);

                if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})(?:\s+\/\s+(?<cabin>.+))?\s*{$this->opt($this->t('Fare conditions'))}/", $airline, $m)) {
                    $s->airline()
                        ->name($m['aName'])
                        ->number($m['fNumber']);

                    if (isset($m['cabin']) && !empty($m['cabin'])) {
                        $s->extra()
                            ->cabin($m['cabin']);
                    }
                }

                $flightInfo = $http2->FindSingleNode("./following::text()[contains(normalize-space(), ':')][1]/ancestor::div[2]", $root);

                if (preg_match("/^(?<depTime>[\d\:]+).*\s+(?<arrTime>[\d\:]+).*/", $flightInfo, $m)) {
                    $s->departure()
                        ->date($this->normalizeDate($depDate . ', ' . $m['depTime']));

                    $arrDate = $this->normalizeDate($depDate . ', ' . $m['arrTime']);

                    if ($arrDate > $s->getDepDate()) {
                        $s->arrival()
                            ->date($arrDate);
                    } elseif ($arrDate < $s->getDepDate()) {
                        $s->arrival()
                            ->date(strtotime('+1 day', $arrDate));
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();

        $this->ParseFlight($email);

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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function AssignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$date in = ' . print_r($str, true));

        $in = [
            // mercredi, 8 mars 2023
            "/^\s*[-[:alpha:]]+\s*,\s*(\d{1,2})\s+(?:de\s+)?([[:alpha:]]+)(?:\s+de)?\s+(\d{4})\s*$/iu",
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
}
