<?php

namespace AwardWallet\Engine\rentalcars\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourCar extends \TAccountChecker
{
    public $mailFiles = "rentalcars/it-108496932.eml, rentalcars/it-108753486.eml, rentalcars/it-149842531.eml, rentalcars/it-151551456.eml, rentalcars/it-50225525.eml, rentalcars/it-52034597.eml, rentalcars/it-52593112.eml, rentalcars/it-58006633.eml, rentalcars/it-62836333.eml, rentalcars/it-93751367.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [ // it-151551456.eml, rentalcars/it-149842531.eml
            'confNumber'                    => ['Número da reserva:'],
            'is'                            => 'está',
            'pickUp'                        => ['Levantar o carro', 'Retirando seu carro'],
            'your car hire for'             => ['o aluguer do seu', 'o aluguel do seu'],
            'Your car'                      => ['O seu carro', 'Seu carro'],
            'Class:'                        => ['Classe:', 'Categoria:'],
            'Supplied by:'                  => 'Fornecido por:',
            'Picking up your car'           => ['Levantar o carro', 'Retirando seu carro'],
            'Dropping off your car'         => ['Entregar o seu carro', 'Devolvendo seu carro'],
            'Date and time'                 => ['Data e hora', 'Data e horário'],
            'Address:'                      => ['Morada:', 'Endereço:'],
            'Phone:'                        => 'Telefone:',
            'Driver details'                => ['Informações do condutor', 'Dados do condutor'],
            'Name:'                         => 'Nome:',
            'Price breakdown'               => ['Preço detalhado', 'Detalhes do preço'],
            'Total'                         => 'Total',
            'Car Hire'                      => ['Aluguer de carros', 'Aluguel de carro'],
            "What you'll need to pay later" => 'O que irá precisar de pagar mais tarde',
            'Full Protection'               => 'Extra Cover',
        ],
        'nl' => [ // 1 bcdtravel(html)
            'confNumber'            => ['Referentienummer:', 'Referentienummer :'],
            'pickUp'                => ['Uw auto ophalen'],
            'your car hire for'     => 'uw huurauto voor',
            'Your car'              => 'Uw auto',
            'Class:'                => 'Categorie:',
            'Supplied by:'          => 'Aangeboden door:',
            'Picking up your car'   => 'Uw auto ophalen',
            'Dropping off your car' => 'Uw auto inleveren',
            'Date and time'         => 'Datum en tijd',
            'Address:'              => 'Adres:',
            'Phone:'                => 'Telefoon:',
            'Driver details'        => 'Bestuurder',
            'Name:'                 => 'Naam:',
            'Price breakdown'       => 'Prijsoverzicht',
            'Total'                 => 'Totaal',
            'Car Hire'              => 'Autoverhuur',
            //            "What you'll need to pay later" => '',
            'Full Protection' => 'Volledige Beschermingsverzekering',
        ],
        'es' => [ // it-52034597.eml
            'confNumber'            => ['Número de reserva:'],
            'is'                    => 'está',
            'pickUp'                => ['Recoge tu coche'],
            'your car hire for'     => 'tu coche de alquiler',
            'Your car'              => 'Tu coche',
            'Class:'                => 'Clase:',
            'Supplied by:'          => 'Proveedor:',
            'Picking up your car'   => 'Recoge tu coche',
            'Dropping off your car' => 'Cómo devolver el coche',
            'Date and time'         => 'Fecha y hora',
            'Address:'              => 'Dirección:',
            'Phone:'                => 'Teléfono:',
            'Driver details'        => 'Datos del conductor',
            'Name:'                 => 'Nombre:',
            'Price breakdown'       => 'Desglose del precio',
            'Total'                 => 'Total',
            'Car Hire'              => 'Alquiler de coches',
            //            "What you'll need to pay later" => '',
            //            'Full Protection' => 'Volledige Beschermingsverzekering',
        ],
        'de' => [ // it-58006633.eml
            'confNumber'            => ['Buchungsnummer:'],
            'is'                    => 'ist',
            'pickUp'                => ['Abholung Ihres Wagens'],
            'your car hire for'     => 'Ihr Mietwagen für',
            'Your car'              => 'Ihr Mietwagen',
            'Class:'                => 'Fahrzeugklasse:',
            'Supplied by:'          => 'Zur Verfügung gestellt von:',
            'Picking up your car'   => 'Abholung Ihres Wagens',
            'Dropping off your car' => 'Abgabe Ihres Wagens',
            'Date and time'         => 'Datum und Uhrzeit',
            'Address:'              => 'Adresse:',
            'Phone:'                => 'Telefon:',
            'Driver details'        => 'Angaben zum Fahrer',
            'Name:'                 => 'Name:',
            'Price breakdown'       => 'Preisübersicht',
            'Total'                 => 'Gesamt',
            'Car Hire'              => 'Autovermietung',
            //            "What you'll need to pay later" => '',
            //            'Full Protection' => '',
        ],
        'fr' => [ // it-52593112.eml
            'confNumber'            => ['Numéro de réservation :'],
            'is'                    => 'est',
            'pickUp'                => ['Prise en charge du véhicule'],
            'your car hire for'     => 'votre location de voiture pour',
            'Your car'              => 'Votre voiture',
            'Class:'                => 'Catégorie :',
            'Supplied by:'          => 'Fournie par :',
            'Picking up your car'   => 'Prise en charge du véhicule',
            'Dropping off your car' => 'Restitution du véhicule',
            'Date and time'         => 'Date et heure',
            'Address:'              => 'Adresse :',
            'Phone:'                => 'Téléphone :',
            'Driver details'        => 'Coordonnées du conducteur',
            'Name:'                 => 'Nom :',
            'Price breakdown'       => 'Détail du tarif',
            'Total'                 => 'Montant total',
            'Car Hire'              => 'Location de voiture',
            //            "What you'll need to pay later" => '',
            //            'Full Protection' => 'Volledige Beschermingsverzekering',
        ],
        'ru' => [ // it-62836333.eml
            'confNumber'            => ['Номер бронирования:'],
            'is'                    => 'автомобиля',
            'pickUp'                => ['Для получения автомобиля вам понадобится:'],
            'your car hire for'     => 'ваша аренда автомобиля',
            'Your car'              => 'Ваш автомобиль',
            'Class:'                => 'Класс:',
            'Supplied by:'          => 'Поставщик:',
            'Picking up your car'   => 'Получение автомобиля',
            'Dropping off your car' => 'Возврат автомобиля',
            'Date and time'         => 'Дата и время',
            'Address:'              => 'Адрес:',
            'Phone:'                => 'Телефон:',
            'Driver details'        => 'Данные водителя',
            'Name:'                 => 'Имя:',
            'Price breakdown'       => 'Разбивка цены',
            'Total'                 => 'Итого',
            'Car Hire'              => 'Аренда автомобиля',
            //            "What you'll need to pay later" => '',
            //            'Full Protection' => 'Volledige Beschermingsverzekering',
        ],
        'it' => [ // it-93751367.eml
            'confNumber'            => ['Numero di prenotazione:'],
            'is'                    => 'è',
            'pickUp'                => ["Ritiro dell'auto"],
            'your car hire for'     => "il noleggio dell'auto per",
            'Your car'              => 'La tua auto',
            'Class:'                => 'Categoria:',
            'Supplied by:'          => 'Fornitore:',
            'Picking up your car'   => "Ritiro dell'auto",
            'Dropping off your car' => "Consegna dell'auto",
            'Date and time'         => 'Data e orario',
            'Address:'              => 'Indirizzo:',
            'Phone:'                => 'Telefono:',
            'Driver details'        => 'Dati del conducente',
            'Name:'                 => 'Nome:',
            'Price breakdown'       => 'Dettagli del prezzo',
            'Total'                 => 'Totale',
            'Car Hire'              => 'Autonoleggio',
            // "What you'll need to pay later" => '',
            // 'Full Protection' => '',
        ],
        'ro' => [ // it-108753486.eml
            'confNumber'            => ['Numărul rezervării:'],
            'is'                    => 'este',
            'pickUp'                => ['Preluarea mașinii'],
            'your car hire for'     => 'mașina pe care ați închiriat-o pentru',
            'Your car'              => 'Mașina dvs.',
            'Class:'                => 'Clasa:',
            'Supplied by:'          => 'Oferită de:',
            'Picking up your car'   => 'Preluarea mașinii',
            'Dropping off your car' => 'Predarea mașinii',
            'Date and time'         => 'Data și ora',
            'Address:'              => 'Adresă:',
            'Phone:'                => 'Telefon:',
            'Driver details'        => 'Detalii șofer',
            'Name:'                 => 'Nume:',
            'Price breakdown'       => 'Detaliere preț',
            // 'Total'                 => '',
            'Car Hire'              => 'Închiriere mașină',
            // "What you'll need to pay later" => '',
            // 'Full Protection' => '',
        ],
        'ko' => [ // it-108496932.eml
            'confNumber'            => ['예약번호:'],
            'is'                    => '렌터카 예약이',
            'pickUp'                => ['픽업 정보'],
            'your car hire for'     => '렌터카 예약이',
            'Your car'              => '예약 차량',
            'Class:'                => '차량 등급:',
            'Supplied by:'          => '제공업체:',
            'Picking up your car'   => '픽업 정보',
            'Dropping off your car' => '반납 정보',
            'Date and time'         => '일시',
            'Address:'              => '주소:',
            'Phone:'                => '전화번호:',
            'Driver details'        => '운전자 정보',
            'Name:'                 => '이름:',
            'Price breakdown'       => '요금 상세 내역',
            'Total'                 => '합계',
            'Car Hire'              => '렌터카',
            // "What you'll need to pay later" => '',
            // 'Full Protection' => '',
        ],
        'en' => [ // it-50225525.eml
            'confNumber' => ['Booking number:', 'Booking number :'],
            'pickUp'     => ['Picking up your car'],
        ],
    ];

    private $subjects = [
        'pt' => ['O seu carro está confirmado', 'Seu carro está confirmado'],
        'nl' => ['Uw auto is bevestigd'],
        'es' => ['tu coche de alquiler'],
        'de' => ['Ihr Mietwagen ist bestätigt'],
        'fr' => ['Votre voiture est confirmée'],
        'ru' => ['Ваш автомобиль подтвержден:'],
        'it' => ['La tua auto è confermata:'],
        'ro' => ['Mașina este confirmată:'],
        'ko' => ['렌터카 예약이 확정되었습니다.'],
        'en' => ['Your car is confirmed'],
    ];

    private $detectors = [
        'pt' => ['O seu carro', 'Seu carro'],
        'nl' => ['Your car'],
        'es' => ['Tu coche está'],
        'de' => ['Ihr Mietwagen'],
        'fr' => ['Votre voiture'],
        'ru' => ['Вам нужно будет распечатать ваучер и взять его с собой.'],
        'it' => ['La tua auto'],
        'ro' => ['Mașina dvs.'],
        'ko' => ['예약 차량'],
        'en' => ['Uw auto'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]rentalcars\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".rentalcars.com/") or contains(@href,"reservations.rentalcars.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Rentalcars.com is part of Booking Holdings Inc") or contains(.,"@rentalcars.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseCar($email);
        $email->setType('YourCar' . ucfirst($this->lang));

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

    private function parseCar(Email $email): void
    {
        $car = $email->add()->rental();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $email->ota()->confirmation($confirmation, $confirmationTitle);
            $car->general()->noConfirmation();
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('your car hire for'))}]/ancestor::tr[1]", null, true, "/\s+{$this->opt($this->t('is'))}\s+([[:alpha:]\s]+)[.!?(]+/u");
        $car->general()->status($status);

        $xpathYouCar = "//tr[ preceding-sibling::tr[normalize-space()][1][{$this->eq($this->t('Your car'))}] ]";

        $imageCar = $this->http->FindSingleNode($xpathYouCar . "/descendant::td[{$this->contains($this->t('Class:'))}]/preceding-sibling::td/descendant::img[{$this->contains('image-car', '@class')} or {$this->contains('/car_images/', '@src')}]/@src");
        $carModel = $this->http->FindSingleNode($xpathYouCar . "/descendant::tr[{$this->starts($this->t('Class:'))}]/preceding-sibling::tr[normalize-space()][1]");
        $car->car()
            ->image($imageCar, false, true)
            ->model($carModel);

        $company = $this->http->FindSingleNode($xpathYouCar . "/descendant::tr[not(.//tr) and {$this->starts($this->t('Supplied by:'))}]", null, true, "/{$this->opt($this->t('Supplied by:'))}\s*(.+)/");

        if (($code = $this->normalizeProvider($company))) {
            $car->program()->code($code);
        } else {
            $car->extra()->company($company);
        }

        $xpathPicking = "//tr[ preceding-sibling::tr[normalize-space()][1][{$this->eq($this->t('Picking up your car'))}] ]";

        $datePickup = $this->http->FindSingleNode($xpathPicking . "/descendant::tr[{$this->starts($this->t('Date and time'))}]/following-sibling::tr[normalize-space()][1]");
        $addressPickup = $this->http->FindSingleNode($xpathPicking . "/descendant::tr[not(.//tr) and {$this->starts($this->t('Address:'))}]", null, true, "/{$this->opt($this->t('Address:'))}\s*(.{3,})/");
        $phonePickup = $this->http->FindSingleNode($xpathPicking . "/descendant::tr[not(.//tr) and {$this->starts($this->t('Phone:'))}]", null, true, "/{$this->opt($this->t('Phone:'))}\s*([+(\d][-. \d)(]{5,}[\d)])$/");
        $car->pickup()
            ->date2($datePickup)
            ->location($addressPickup)
            ->phone($phonePickup);

        $xpathDropping = "//tr[ preceding-sibling::tr[normalize-space()][1][{$this->eq($this->t('Dropping off your car'))}] ]";

        $dateDropoff = $this->http->FindSingleNode($xpathDropping . "/descendant::tr[{$this->starts($this->t('Date and time'))}]/following-sibling::tr[normalize-space()][1]");
        $addressDropoff = $this->http->FindSingleNode($xpathDropping . "/descendant::tr[not(.//tr) and {$this->starts($this->t('Address:'))}]", null, true, "/{$this->opt($this->t('Address:'))}\s*(.{3,})/");
        $phoneDropoff = $this->http->FindSingleNode($xpathDropping . "/descendant::tr[not(.//tr) and {$this->starts($this->t('Phone:'))}]", null, true, "/{$this->opt($this->t('Phone:'))}\s*([+(\d][-. \d)(]{5,}[\d)])$/");
        $car->dropoff()
            ->date2($dateDropoff)
            ->location($addressDropoff)
            ->phone($phoneDropoff);

        $xpathDriver = "//tr[ preceding-sibling::tr[normalize-space()][1][{$this->eq($this->t('Driver details'))}] ]";

        $namePrefixes = ['Mlle/Mme', 'Mlle', 'Mme', 'Господин', 'Frau', 'Sr', 'Sig', 'Dl', 'Mr', 'Ms'];
        $driverName = $this->http->FindSingleNode($xpathDriver . "/descendant::tr[not(.//tr) and {$this->starts($this->t('Name:'))}]", null, true, "/{$this->opt($this->t('Name:'))}\s*([[:alpha:]][-.\/'[:alpha:] ]*[[:alpha:]])$/u");
        $car->general()->traveller(preg_replace("/^{$this->opt($namePrefixes)}\s*[.]*\s+/iu", '', $driverName), true);

        $xpathPrice = "//tr[ preceding-sibling::tr[normalize-space()][1][{$this->eq($this->t('Price breakdown'))}] ]";
        $xpathNotPayLater = "not(preceding::tr[{$this->starts($this->t("What you'll need to pay later"))}])";

        $totalPrice = $this->http->FindSingleNode($xpathPrice . "/descendant::tr[not(.//tr) and {$this->starts($this->t('Total'))} and {$xpathNotPayLater}]", null, true, "/{$this->opt($this->t('Total'))}\s*(.+)/");

        if (preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)
            || preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $m)
        ) {
            // SGD702.71    |    203,28€
            $car->price()
                ->currency($this->currency($m['currency']))
                ->total($this->normalizeAmount($m['amount']));

            $m['currency'] = trim($m['currency']);

            $carHire = $this->http->FindSingleNode($xpathPrice . "/descendant::tr[not(.//tr) and {$this->starts($this->t('Car Hire'))} and {$xpathNotPayLater}]", null, true, "/{$this->opt($this->t('Car Hire'))}\s*(.+)/");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d]*)$/', $carHire, $matches)
                || preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?:' . preg_quote($m['currency'], '/') . ')?$/', $carHire, $matches)
            ) {
                $car->price()->cost($this->normalizeAmount($matches['amount']));
            }

            $fullProtection = $this->http->FindSingleNode($xpathPrice . "/descendant::td[not(.//td) and {$this->contains($this->t('Full Protection'))} and {$xpathNotPayLater}]/following-sibling::td[normalize-space()][1]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d]*)$/', $fullProtection, $matches)
                || preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?:' . preg_quote($m['currency'], '/') . ')?$/', $fullProtection, $matches)
            ) {
                $car->price()->tax($this->normalizeAmount($matches['amount']));
            }
        }
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

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['pickUp'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['pickUp'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'alamo'        => ['Alamo'],
            'avis'         => ['Avis'],
            'dollar'       => ['Dollar', 'Dollar RTA'],
            'europcar'     => ['Europcar'],
            'hertz'        => ['Hertz'],
            'localiza'     => ['Localiza'],
            'perfectdrive' => ['Budget'],
            'sixt'         => ['Sixt'],
            'thrifty'      => ['Thrifty'],
        ];

        foreach ($providers as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            'R$'   => 'BRL',
            '€'    => 'EUR',
            '£'    => 'GBP',
            'US$'  => 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
}
