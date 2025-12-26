<?php

namespace AwardWallet\Engine\rentalcars\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BeforePickup extends \TAccountChecker
{
    public $mailFiles = "rentalcars/it-3076037.eml, rentalcars/it-3990243.eml, rentalcars/it-58500632.eml, rentalcars/it-94713010.eml, rentalcars/it-98648771.eml, rentalcars/it-99055423.eml";

    public static $detectProvider = [
        'rentalcars' => [
            'from' => 'email@reservations.rentalcars.com',
            'body' => [".rentalcars.com/", "Rentalcars.com", "@reservations.rentalcars.com"],
        ],
        'booking' => [
            'from' => 'email@cars.booking.com',
            'body' => ['cars.booking.com', 'www.booking.com/cars'],
        ],
    ];

    public $lang = '';
    public $providerCode;

    public static $dictionary = [
        'nl' => [
            'confNumber'       => ['Nombor Rujukan Tempahan'],
            'pickUp'           => 'Ophalen',
            'dropOff'          => ['Inleveren'],
            "Driver's Name"    => 'Naam bestuurder',
            'Car Hire Company' => 'Autoverhuurbedrijf',
            'or similar'       => ['of soortgelijke'],
        ],
        'pt' => [
            'confNumber'       => ['Nº de referência'],
            'pickUp'           => 'Retirada',
            'dropOff'          => ['Devolução::'],
            "Driver's Name"    => 'Nome do Condutor',
            'Car Hire Company' => 'Locadora',
            'or similar'       => ['or similar'],
        ],
        'it' => [
            'confNumber'       => ['Numero di Riferimento Prenotazione'],
            'pickUp'           => 'Ritiro',
            'dropOff'          => ['Riconsegna'],
            "Driver's Name"    => 'Nome del conducente',
            'Car Hire Company' => 'Compagnia di noleggio',
            'or similar'       => ['o similare', 'o simile'],
        ],
        'fr' => [
            'confNumber'       => ['Numéro de référence'],
            'pickUp'           => 'Prise en charge',
            'dropOff'          => ['Restitution'],
            "Driver's Name"    => 'Nom du conducteur',
            'Car Hire Company' => 'Nom de la société de location de voitures',
            'or similar'       => 'ou similaire',
        ],
        'en' => [
            'confNumber' => ['Booking Reference Number'],
            'pickUp'     => 'Pick-up',
            'dropOff'    => ['Drop-off', 'Drop-Off'],
        ],
    ];
    private $subjects = [
        'nl' => ['Voordat u uw auto ophaalt'],
        'pt' => ['Antes de você retirar o seu carro'],
        'it' => ['Prima di ritirare la tua auto'],
        'fr' => ['Avant de prendre votre voiture'],
        'en' => ['Before you pick your car up'],
    ];

    private $detectors = [
        'nl' => ['klaar voor uw huurperiode'],
        'pt' => ['Consultar reserva'],
        'it' => ['sei pronto per iniziare il tuo noleggio', 'tutto pronto per il noleggio'],
        'fr' => ['êtes-vous prêt'],
        'en' => ['are you ready for your rental', 'Before you go to pick your car up, please read these', 'Before you pick up your rental car,'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]rentalcars\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $detectedFrom = false;

        foreach (self::$detectProvider as $code => $dp) {
            if (isset($dp['from']) && stripos($headers['from'], $dp['from']) !== false) {
                $this->providerCode = $code;
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

        $this->parseCar($email);

        if (empty($this->providerCode)) {
            foreach (self::$detectProvider as $code => $dp) {
                if (!empty($dp['body'])
                    && $this->http->XPath->query('//a[' . $this->contains($dp['body'], '@href') . ']')->length > 0
                    && $this->http->XPath->query('//node()[' . $this->contains($dp['body']) . ']')->length > 0
                ) {
                    $this->providerCode = $code;

                    break;
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }
        $email->setType('BeforePickup' . ucfirst($this->lang));

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
        $xpathCell = '(self::td or self::th)';

        $car = $email->add()->rental();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/ancestor::*[{$xpathCell}][1]/following-sibling::*[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $car->general()->confirmation($confirmation, $confirmationTitle);
        }

        $driverName = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Driver's Name"))}]/ancestor::*[{$xpathCell}][1]/following-sibling::*[normalize-space()][1]", null, true, '/^(?:MR |DR |Miss |Sr |DHR )?([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/u');

        if (empty($driverName)) {
            $driverName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('are you ready to rent?'))}]", null, true, '/^\s*(\w+)\,/u');
        }
        $car->general()->traveller($driverName, true);

        $company = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Car Hire Company'))}]/ancestor::*[{$xpathCell}][1]/following-sibling::*[normalize-space()][1]");

        if (($code = $this->normalizeProvider($company))) {
            $car->program()->code($code);
        } else {
            $car->extra()->company($company);
        }

        // Lug 3 2016 - 21:00
        $patterns['dateTime'] = "/^(?<date>.{6,})[ ]+-[ ]+(?<time>\d{1,2}[:]+\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)$/";

        $pickupHtml = $this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('pickUp'))}]/ancestor::*[{$xpathCell}][1]/following-sibling::*[normalize-space()][1]");
        $pickupRows = preg_split('/[ ]*\n+[ ]*/', $this->htmlToText($pickupHtml));

        if (count($pickupRows) > 1) {
            $car->pickup()->location($pickupRows[0]);

            if (preg_match($patterns['dateTime'], $pickupRows[1], $m)
                && ($datePickup = $this->normalizeDate($m['date']))
            ) {
                $car->pickup()->date2($datePickup . ' ' . $m['time']);
            } elseif (preg_match($patterns['dateTime'], $pickupRows[2], $m)
                && ($datePickup = $this->normalizeDate($m['date']))
            ) {
                $car->pickup()->location(implode(', ', [$pickupRows[0], $pickupRows[1]]));
                $car->pickup()->date2($datePickup . ' ' . $m['time']);
            }
        }

        $dropoffHtml = $this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('dropOff'))}]/ancestor::*[{$xpathCell}][1]/following-sibling::*[normalize-space()][1]");
        $dropoffRows = preg_split('/[ ]*\n+[ ]*/', $this->htmlToText($dropoffHtml));

        if (count($dropoffRows) > 1) {
            if (count($dropoffRows) == 2) {
                $car->dropoff()->location($dropoffRows[0]);
            } elseif (count($dropoffRows) == 3) {
                $car->dropoff()->location(implode(', ', [$dropoffRows[0], $dropoffRows[1]]));
            }

            if (preg_match($patterns['dateTime'], $dropoffRows[1], $m)
                && ($dateDropoff = $this->normalizeDate($m['date']))
            ) {
                $car->dropoff()->date2($dateDropoff . ' ' . $m['time']);
            } elseif (preg_match($patterns['dateTime'], $dropoffRows[2], $m)
                && ($dateDropoff = $this->normalizeDate($m['date']))
            ) {
                $car->dropoff()->date2($dateDropoff . ' ' . $m['time']);
            }
        }

        $xpathLeftCol = "//text()[{$this->starts($this->t('Car Hire Company'))}]/ancestor::*[ {$xpathCell} and preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()]";

        $carImageUrl = $this->http->FindSingleNode($xpathLeftCol . '/descendant::img/@src');
        $carModel = $this->http->FindSingleNode($xpathLeftCol . "/descendant::tr[normalize-space() and preceding-sibling::tr//img][not({$this->contains($this->t('or similar'))}) and not(preceding-sibling::tr[{$this->contains($this->t('or similar'))}])]");
        $car->car()
            ->image($carImageUrl)
            ->model($carModel);
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
            'avis'         => ['Avis', 'Avis (RTA)'],
            'dollar'       => ['Dollar', 'Dollar RTA'],
            'europcar'     => ['Europcar'],
            'hertz'        => ['Hertz'],
            'localiza'     => ['Localiza'],
            'perfectdrive' => ['Budget', 'RC - Budget'],
            'sixt'         => ['Sixt', 'Sixt Italy'],
            'thrifty'      => ['Thrifty', 'RC - Thrifty'],
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^\s*([[:alpha:]]+)\s+(\d{1,2})(?:\s*,\s*|\s+)(\d{4})\s*$/u', $text, $m)) {
            // Lug 5 2016
            $month = $m[1];
            $day = $m[2];
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['dropOff'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['dropOff'])}]")->length > 0
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        $this->logger->debug($s);

        return trim($s);
    }
}
