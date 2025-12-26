<?php

namespace AwardWallet\Engine\rentalcars\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CarInsurance extends \TAccountChecker
{
    public $mailFiles = "rentalcars/it-107949595.eml, rentalcars/it-107956535.eml, rentalcars/it-108496933.eml, rentalcars/it-108760618.eml, rentalcars/it-109330688.eml, rentalcars/it-154054855.eml, rentalcars/it-211302395.eml, rentalcars/it-815258575-fr.eml";

    public $lang = '';
    public static $detectProvider = [
        'booking' => [
            'from' => 'email@cars.booking.com',
            // looked up in node(), a@href, img@src
            'body' => ['cars.booking.com', 'www.booking.com/cars'],
        ],
        'ryanair' => [
            'from' => 'mail@reservations.carhire.ryanair.com',
            // looked up in node(), a@href, img@src
            'body' => ['@reservations.carhire.ryanair.com', '.rentalcars.com/emailimages/trans/affiliate/ryanair.'],
        ],
        'kiwi' => [
            //            'from' => 'email@reservations.rentalcars.com',
            // looked up in node(), a@href, img@src
            'body' => ['.rentalcars.com/emailimages/trans/affiliate/skypicker.'],
        ],
        'wizz' => [
            //            'from' => 'email@reservations.rentalcars.com',
            // looked up in node(), a@href, img@src
            'body' => ['.rentalcars.com/emailimages/trans/affiliate/wizzair.'],
        ],
        // last
        'rentalcars' => [
            'from' => 'email@reservations.rentalcars.com',
            // looked up in node(), a@href, img@src
            'body' => [".rentalcars.com/", "Rentalcars.com", "@reservations.rentalcars.com"],
        ],
    ];

    public static $dictionary = [
        'fr' => [
            'bookingNumber' => ['Numéro de réservation:', 'Numéro de réservation :'],
            'suppliedBy'    => ['Société de location de voitures:', 'Société de location de voitures :'],
            'or similar'    => ['ou similaire'],
            'Address'       => 'Adresse',
            'Call '         => 'Appelez ',
        ],
        'sv' => [ // it-211302395.eml
            'bookingNumber' => ['Bokningsnummer:'],
            'suppliedBy'    => ['Levereras av'],
            'or similar'    => ['eller liknande'],
            'Address'       => 'Adress',
            'Call '         => 'Ring ',
        ],
        'nl' => [ // it-108496933.eml
            'bookingNumber' => ['Referentienummer:', 'Boekingsnummer:'],
            'suppliedBy'    => ['Verstrekt door', 'Autoverhuurbedrijf:'],
            'or similar'    => ['of soortgelijke', 'of soortgelijk'],
            //'Address' => '',
            //'Call ' => '',
        ],
        'ko' => [ // it-108496933.eml
            'bookingNumber' => ['예약번호:'],
            'suppliedBy'    => ['제공업체'],
            'or similar'    => '또는 동급',
            //'Address' => '',
            //'Call ' => '',
        ],
        'ro' => [ // it-108760618.eml
            'bookingNumber' => ['Numărul rezervării:'],
            'suppliedBy'    => ['Furnizat de'],
            'or similar'    => 'sau asemănător',
            //'Address' => '',
            //'Call ' => '',
        ],
        'es' => [ // it-109330688.eml
            'bookingNumber' => ['Número de reserva:'],
            'suppliedBy'    => ['Proveedor:', 'Empresa de alquiler de coches:'],
            'or similar'    => 'o similar',
            'Address'       => 'Dirección',
            'Call '         => 'Llama ',
        ],
        'en' => [ // it-107949595.eml, it-107956535.eml
            'bookingNumber' => ['Booking number:'],
            'suppliedBy'    => ['Supplied by', 'Car rental company:'],
        ],
        'pt' => [
            'bookingNumber' => ['Número da reserva:'],
            'suppliedBy'    => ['Providenciado por:', 'Empresa de aluguer de carros:'],
            'or similar'    => ['ou similar', 'ou semelhante'],
            'Address'       => 'Morada',
            'Call '         => 'Telefone para',
        ],
        'pl' => [
            'bookingNumber' => ['Numer rezerwacji:', 'Numer referencyjny rezerwacji:'],
            'suppliedBy'    => ['Wypożyczalnia samochodów:'],
            'or similar'    => 'lub podobny',
            'Address'       => 'Adres',
            'Call '         => 'Zadzwoń do',
        ],
        'it' => [
            'bookingNumber' => ['Numero di prenotazione:'],
            'suppliedBy'    => ['Compagnia di noleggio:'],
            'or similar'    => 'o simile',
            // 'Address' => 'Adres',
            // 'Call ' => 'Zadzwoń do',
        ],
        'zh' => [
            'bookingNumber' => ['訂單編號：'],
            'suppliedBy'    => ['租車公司：'],
            'or similar'    => '或相似車款',
            'Address'       => '地址',
            'Call '         => '致電',
        ],
        'de' => [
            'bookingNumber' => ['Buchungsnummer:', 'Buchungsreferenznummer:'],
            'suppliedBy'    => ['Mietwagenfirma:'],
            'or similar'    => 'oder ähnlich',
            'Address'       => 'Adresse',
            'Call '         => 'anrufen',
        ],
    ];

    private $providerCode;

    private $subjects = [
        'fr' => ['Avant de prendre votre voiture'],
        'sv' => ['Innan du hämtar din'],
        'nl' => ['U bent gedekt door de Volledige Bescherming', 'Je bent gedekt door de Volledige beschermingsverzekering'],
        'ko' => ['풀커버 보호상품에 가입하셨습니다'],
        'ro' => ['Ești acoperit de Protecţie Integrală'],
        'es' => ['Antes de retirar el coche', 'Tienes la cobertura Premium'],
        'pt' => ['Está protegido(a) pela', 'Antes de levantar o seu carro',
            'O seu aluguer está protegido pelo Seguro de Proteção Total', ],
        'en' => ["You're covered by Full Protection", 'Before you pick your car up', 'make sure you’re fully protected on your car rental'],
        'pl' => ['Jesteś objęty Pełnym Ubezpieczeniem', 'Przed odbiorem swojego samochodu'],
        'it' => ['Hai la Copertura assicurativa completa'],
        'zh' => ['在您取車之前。'],
        'de' => ['Sie sind vom Komplettschutz geschützt', 'Bevor Sie Ihren Mietwagen abholen'],
    ];

    private $detectors = [
        'fr' => ['Ce dont vous aurez besoin à la prise en charge du véhicule'],
        'sv' => ['Vad du behöver när du hämtar bilen'],
        'nl' => ['Volledige bescherming voor je huurauto in ', 'Volledige beschermingsverzekering voor je huurauto'],
        'ko' => ['렌터카 예약 풀커버 보험'],
        'ro' => ['Full Protection for your car rental in'],
        'es' => ['¡Prepárate para el viaje a', 'Cobertura Premium para tu coche de alquiler'],
        'pt' => ['Proteção Total para o seu carro de aluguer em', 'Prepare-se para sua viagem a',
            'Seguro de Proteção Total para o seu carro de aluguer', ],
        'en' => ['Full Protection for your car rental in', 'Get ready for your trip in', 'important information about your rental', 'Full Protection Insurance for your car rental'],
        'pl' => ['Pełne Ubezpieczenie na wynajem samochodu', 'Przygotuj się na podróż do miasta'],
        'it' => ['Copertura assicurativa completa per il tuo noleggio'],
        'zh' => ['之旅做好準備！'],
        'de' => ['Komplettschutz für Ihren Mietwagen in ', 'Machen Sie sich bereit für Ihre Reise nach'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Rentalcars.com') !== false
            || stripos($from, '@reservations.rentalcars.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $detectedFrom = false;

        foreach (self::$detectProvider as $code => $dp) {
            if (isset($dp['from']) && stripos($headers['from'], $dp['from']) !== false) {
                if ($code !== 'rentalcars') {
                    $this->providerCode = $code;
                }
                $detectedFrom = true;

                break;
            }
        }

        if ($detectedFrom === false) {
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
        $detectedProvider = false;

        foreach (self::$detectProvider as $code => $dp) {
            if (!empty($dp['from']) && stripos($parser->getCleanFrom(), $dp['from']) !== false
            ) {
                $this->providerCode = $code;
            }

            if (!empty($dp['body'])
                && ($this->http->XPath->query('//a[' . $this->contains($dp['body'], '@href') . ']')->length > 0
                    || $this->http->XPath->query('//node()[' . $this->contains($dp['body']) . ']')->length > 0
                    || $this->http->XPath->query('//img[' . $this->contains($dp['body'], '@src') . ']')->length > 0
                )
            ) {
                $detectedProvider = true;

                break;
            }
        }

        if ($detectedProvider === false) {
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
        $email->setType('CarInsurance' . ucfirst($this->lang));

        $this->parseCar($email);

        if (empty($this->providerCode)) {
            foreach (self::$detectProvider as $code => $dp) {
                if (!empty($dp['body']) && (
                    $this->http->XPath->query('//img[' . $this->contains($dp['body'], '@src') . ']')->length > 0
                    || $this->http->XPath->query('//a[' . $this->contains($dp['body'], '@href') . ']')->length > 0
                    || $this->http->XPath->query('//node()[' . $this->contains($dp['body']) . ']')->length > 0
                )) {
                    $this->providerCode = $code;

                    break;
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function parseCar(Email $email): void
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';

        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
        ];

        $car = $email->add()->rental();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('bookingNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('bookingNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $car->general()->confirmation($confirmation, $confirmationTitle);
        } elseif (preg_match("/^({$this->opt($this->t('bookingNumber'))})[:\s]*([-A-Z\d]{5,})$/", $this->http->FindSingleNode("//text()[{$this->starts($this->t('bookingNumber'))}]"), $m)) {
            $car->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        if (empty($car->getConfirmationNumbers())) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('bookingNumber'))}]", null, true, "/{$this->opt($this->t('bookingNumber'))}\s*(.+)/");

            if (preg_match("/\{RESPER\_NUM\}/", $confirmation)) {
                $car->general()
                    ->noConfirmation();
            }
        }

        $company = $this->http->FindSingleNode("//text()[{$this->eq($this->t('suppliedBy'))}]/following::text()[normalize-space()][1]");

        if (($code = $this->normalizeProvider($company))) {
            $car->program()->code($code);
        } else {
            $car->extra()->company($company);
        }

        $dots = $this->http->XPath->query("//tr[ *[2] and *[1][normalize-space()=''] ]/*[normalize-space()][last()]/descendant::*[ tr[normalize-space()][2] ]/tr[{$xpathTime}]");

        if ($dots->length !== 2) {
            $this->logger->debug('Wrong Pick-up or Drop-off info!');

            return;
        }

        $xpathCar = "ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ]/preceding-sibling::tr[normalize-space()][1]/descendant::tr[ *[2] and *[1][normalize-space()=''] ][1]";

        $carModel = $this->http->FindSingleNode($xpathCar . "/*[normalize-space()][last()]/descendant::tr[not(.//tr) and {$this->contains($this->t('or similar'))}]", $dots->item(0));
        $car->car()
            ->model($carModel)
        ;
        $carImage = $this->http->FindSingleNode($xpathCar . "/*[normalize-space()='']/descendant::img/@src", $dots->item(0));

        if (preg_match("/^\s*cid:/", $carImage)) {
        } else {
            $car->car()
                ->image($carImage)
            ;
        }

        $datePickUp = $this->http->FindSingleNode(".", $dots->item(0));

        if (preg_match("/^(?<date>.{6,}?)[- ]+(?<time>{$patterns['time']})$/", $datePickUp, $m)
            && ($datePickUpNormal = $this->normalizeDate($m['date']))
        ) {
            $car->pickup()->date2($datePickUpNormal . ' ' . $m['time']);
        }

        $dateDropOff = $this->http->FindSingleNode(".", $dots->item(1));

        if (preg_match("/^(?<date>.{6,}?)[- ]+(?<time>{$patterns['time']})$/", $dateDropOff, $m)
            && ($dateDropOffNormal = $this->normalizeDate($m['date']))
        ) {
            $car->dropoff()->date2($dateDropOffNormal . ' ' . $m['time']);
        }

        $car->pickup()->location($this->http->FindSingleNode("following-sibling::tr[normalize-space()][1][not({$xpathTime})]", $dots->item(0)));
        $car->dropoff()->location($this->http->FindSingleNode("following-sibling::tr[normalize-space()][1][not({$xpathTime})]", $dots->item(1)));

        $locationAddress = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/following::text()[normalize-space()][1]");
        $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Call '))}]/following::text()[normalize-space()][1]");

        if (!empty($locationAddress) && ($car->getPickUpLocation() === $car->getDropOffLocation())) {
            $car->pickup()
                ->location($car->getPickUpLocation() . ', ' . $locationAddress);

            $car->dropoff()
                ->location($car->getDropOffLocation() . ', ' . $locationAddress);

            if (!empty($phone) && empty($car->getPickUpPhone())) {
                $car->pickup()
                    ->phone($phone);

                $car->dropoff()
                    ->phone($phone);
            }
        } elseif (!empty($locationAddress)) {
            $car->pickup()
                ->location($car->getPickUpLocation() . ', ' . $locationAddress);

            if (!empty($phone) && empty($car->getPickUpPhone())) {
                $car->pickup()
                    ->phone($phone);
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

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['bookingNumber']) || empty($phrases['suppliedBy'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['bookingNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['suppliedBy'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})[.\s]+([[:alpha:]]+)[.\s]+(\d{4})$/u', $text, $m)) {
            // Fri, 03 Sep 2021    |    vineri, 03 septembrie 2021
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})$/u', $text, $m)) {
            // Aug 17, 2021
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/\b(\d{1,2})\s+(\d{1,2})\s*월\s*(\d{4})$/', $text, $m)) {
            // 목요일, 20 5월 2021
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
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
            'avis'         => ['Avis', 'RC - Avis'],
            'alamo'        => ['Alamo'],
            'perfectdrive' => ['Budget'],
            'dollar'       => ['Dollar'],
            'rentacar'     => ['Enterprise'],
            'europcar'     => ['Europcar'],
            'hertz'        => ['Hertz'],
            'national'     => ['National'],
            'sixt'         => ['Sixt', 'Sixt Italy'],
            'thrifty'      => ['Thrifty'],
            'payless'      => ['Payless', 'RC - Payless'],
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
}
