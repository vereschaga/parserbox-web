<?php

namespace AwardWallet\Engine\googlefl\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "googlefl/it-13592923.eml, googlefl/it-13617764.eml, googlefl/it-13715883.eml, googlefl/it-13729054.eml, googlefl/it-13785239.eml, googlefl/it-13933361.eml, googlefl/it-13968944.eml, googlefl/it-20652305.eml, googlefl/it-22846213.eml, googlefl/it-61791019.eml, googlefl/it-67446696.eml, googlefl/it-99499539.eml";

    private $subjectPatterns = [
        'es' => ['/Confirmación de la reserva .+ de/i'],
        'pt' => ['/Confirmação da reserva .+ com a companhia aérea/i'],
        'fr' => ['/Confirmation de votre réservation/i'],
        'nl' => ['/Bevestiging van je boeking bij .+ met referentiecode/i'],
        'en' => ['/Confirmation for .+ booking/i'],
    ];
    private $langDetectors = [
        'es' => ['Gracias por hacer una reserva con'],
        'pt' => ['Agradecemos sua reserva com a'],
        'fr' => ["Merci d'avoir réservé votre vol"],
        'nl' => ['Bedankt dat je via'],
        'en' => ['Thanks for booking with'],
    ];
    private $lang = '';
    private $year;
    private static $dict = [
        'es' => [
            'Thanks for booking with'   => 'Gracias por hacer una reserva con',
            'on Google'                 => 'en Google',
            'your reservation is'       => 'Ya se ha',
            'Your confirmation code'    => 'Tu código de confirmación',
            'Passenger'                 => ['Pasajeros', 'Pasajero'],
            'Total paid to'             => 'Precio total pagado a',
            'cost'                      => 'Tarifas, impuestos y comisiones de la compañía aérea',
            'feeNames'                  => ['Equipaje'],
            'Need help? Please contact' => '¿Necesitas ayuda? Ponte en contacto con',
            ' to '                      => ' a ',
            //			'operated by' => '',
        ],
        'pt' => [
            'Thanks for booking with' => 'Agradecemos sua reserva com a',
            'on Google'               => 'no Google',
            'your reservation is'     => 'sua reserva foi',
            'Your confirmation code'  => ['O código da viagem do seu parceiro Priceline é', 'Seu código de confirmação'],
            'Passenger'               => ['Passageiro', 'Passageiros'],
            'Total paid to'           => 'Total pago a',
            //            'cost' => '',
            //            'feeNames' => [''],
            'Need help? Please contact' => 'Precisa de ajuda? Entre em contato com',
            ' to '                      => ' para ',
            //			'operated by' => '',
        ],
        'fr' => [
            'Thanks for booking with' => "Merci d'avoir réservé votre vol",
            'on Google'               => 'sur Google',
            'your reservation is'     => 'Votre réservation est',
            'Your confirmation code'  => 'Votre code de confirmation',
            'Passenger'               => ['Passager', 'Passagers'],
            'Total paid to'           => 'Montant total payé à',
            //            'cost' => '',
            //            'feeNames' => [''],
            'Need help? Please contact' => "Vous avez besoin d'aide ? Veuillez contacter",
            ' to '                      => ' – ',
            //			'operated by' => '',
        ],
        'nl' => [
            'Thanks for booking with' => "Bedankt dat je via Google hebt geboekt bij",
            'on Google'               => 'via Google',
            'your reservation is'     => 'Je reservering is',
            'Your confirmation code'  => 'Je bevestigingscode',
            'Passenger'               => ['Passagier', 'Passagiers'],
            'Total paid to'           => 'Totaalbedrag betaald aan',
            //            'cost' => '',
            //            'feeNames' => [''],
            'Need help? Please contact' => 'Hulp nodig? Neem contact op met',
            ' to '                      => ' naar ',
            //			'operated by' => '',
        ],
        'en' => [
            'Your confirmation code' => ['Your confirmation code', 'Your reference code'],
            'Passenger'              => ['Passenger', 'Passengers'],
            'cost'                   => 'Fare + taxes + airline fees',
            'feeNames'               => ['Seats', 'Baggage'],
            ' to '                   => [' to ', ' TO '],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Google Travel') !== false
            || stripos($from, 'travel-support@google.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjectPatterns as $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Google — ")]')->length === 0;
        $condition2 = $this->http->XPath->query('//img[contains(@src,"//www.gstatic.com/travel-booking/") and contains(@src,"/google_flights_logo")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->year = date('Y', strtotime($parser->getDate()));

        if (!$this->assignLang()) {
            $this->logger->debug('Can\'t determine a language!');

            return $email;
        }
        $email->setType('Booking' . ucfirst($this->lang));
        $this->parseEmail($email);

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

    private function parseEmail(Email $email)
    {
        $patterns = [
            'tripNumber' => '/^(\d{5,})$/', // 11986371476
            'confNumber' => '/^([A-Z\d]{5,})$/', // 11986371476    |    M5GPQK
            'time'       => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?',
        ];

        $email->ota(); // because Google Flight is not airline

        $f = $email->add()->flight();

        // Thanks for booking with Alaska on Google — your reservation is confirmed.
        $thanksText = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Thanks for booking with')) . "]/ancestor::*[(" . $this->starts($this->t('Thanks for booking with')) . ") and (" . $this->contains($this->t('on Google')) . ")][1]");

        // provider
        $fProvider = preg_match('/' . $this->opt($this->t('Thanks for booking with')) . '\s+(.+?)\s+' . $this->opt($this->t('on Google')) . '/', $thanksText, $matches) ? $matches[1] : null;

        if (empty($fProvider) && preg_match('/' . $this->opt($this->t('on Google')) . '/', is_array($this->t('Thanks for booking with')) ? implode(', ', $this->t('Thanks for booking with')) : $this->t('Thanks for booking with'))) {
            $fProvider = preg_match('/' . $this->opt($this->t('Thanks for booking with')) . '\s+(.+?)\./', $thanksText, $matches) ? $matches[1] : null;
        }
        $fProviderCode = $this->normalizeProvider($fProvider);

        // phone
        $contactText = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Need help? Please contact')) . ']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]');
        $fPhone = preg_match('/([+(\d][-)(\d\s]{5,}[)\d])\b/', $contactText, $matches) ? $matches[1] : '';

        // status
        $f->setStatus(preg_match('/' . $this->opt($this->t('your reservation is')) . '\s+(\w+)/u', $thanksText, $matches) ? $matches[1] : '');

        // confirmation number
        $confirmationCodeTitle = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Your confirmation code')) . ']');
        $confirmationCode = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Your confirmation code')) . ']/following::text()[normalize-space(.)][1]', null, true, $patterns['confNumber']);

        if (empty($confirmationCode) && !empty($fProvider)) {
            $confirmationCodeTitle = 'Your ' . $fProvider . ' Trip Number';
            $confirmationCode = $this->http->FindSingleNode('//text()[normalize-space(.)="' . $confirmationCodeTitle . '"]/following::text()[normalize-space(.)][1]', null, true, $patterns['confNumber']);
        }

        if (preg_match($patterns['tripNumber'], $confirmationCode)) { // Trip Number
            if ($fProviderCode) {
                $f->ota()->code($fProviderCode);
            } else {
                $f->ota()->keyword($fProvider);
            }
            $f->ota()->confirmation($confirmationCode, $confirmationCodeTitle);
            $f->ota()->phone($fPhone);
            $f->setNoConfirmationNumber(true);
        } else { // Record Locator
            if ($fProviderCode) {
                $f->program()->code($fProviderCode);
            } else {
                $f->program()->keyword($fProvider);
            }
            $f->addConfirmationNumber($confirmationCode, $confirmationCodeTitle);
            $f->addProviderPhone($fPhone);
        }

        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
        // segments
        $segments = $this->http->XPath->query($xpath = '//tr[ not(.//tr) and count(./td)=2 and ./td[1][./descendant::img] and ./td[2][contains(.,":") and contains(.,"–") and contains(.,"·")] ]');

        if ($segments->count() == 0) {
            $segments = $this->http->XPath->query($xpath = '//tr[ not(.//tr) and count(./td)=2 and ./td[1][./descendant::img] and ./td[2][contains(.,".") and contains(.,"–") and contains(.,"·")] ]');
        }
        $this->logger->debug("[xpath-seg] " . $xpath);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $date = 0;
//            $dateText = $this->http->FindSingleNode('./preceding-sibling::tr[ not(.//tr) and count(./td)=2 and ./td[1][ ./descendant::img[contains(@src,"/takeoff-right.") or contains(@src,"/takeoff-left.") or contains(@src, "takeoff_gm") or contains(@src, "return_gm")] ] ][1]', $segment);
            $dateText = $this->http->FindSingleNode("./preceding-sibling::tr[ not(.//tr) and count(./td)=2 and ./td[2][ ./descendant::text()[({$this->contains($this->t(' to '))}) and not($ruleTime)] ] ][1]", $segment);

            if (preg_match('/^(?<weekDay>[^\d\W]{2,})\s*\.?\s*[,\s+]\s*(.+?)(?:\s*·|$)/u', $dateText, $matches)) {
                $weekDayNumber = WeekTranslate::number1($matches['weekDay']);
                $dateNormal = $this->normalizeDate($matches[2]);

                if ($weekDayNumber && $dateNormal) {
                    $date = EmailDateHelper::parseDateUsingWeekDay($dateNormal, $weekDayNumber);
                }
            }

            // depDate
            // arrDate
            $times = $this->http->FindSingleNode('./td[2]/descendant::text()[normalize-space(.)][1]', $segment);
            // 6:31 PM–10:06 PM
            $times = preg_replace("/(\d+)\.(\d+)\–(\d+)\.(\d+)/i", '$1:$2–$3:$4', $times);
            $times = preg_replace("/\b([ap])\.m\./i", '$1m', $times);

            if ($date && preg_match('/^(' . $patterns['time'] . ')\s*–\s*(' . $patterns['time'] . ')/', $times, $matches)) {
                $s->setDepDate(strtotime($matches[1], $date));
                $s->setArrDate(strtotime($matches[2], $date));
            }

            // duration
            $duration = $this->http->FindSingleNode('./td[2]/descendant::text()[normalize-space(.)][2]', $segment, true, '/(\d[\d\shminur,]+(?:minuten)?)$/i');

            if ($duration) {
                $s->setDuration($duration);
            }

            // depCode
            // depName
            // arrCode
            // arrName
            $route = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][1]', $segment);
            // YOW (Ottawa Macdonald–Cartier International Airport)–YYZ (Toronto Pearson International Airport)
            if (preg_match('/^([A-Z]{3})\s*\((.+)\)\s*–\s*([A-Z]{3})\s*\((.+)\)$/', $route, $matches)) {
                $s->departure()
                    ->code($matches[1])
                    ->name($matches[2]);
                $s->arrival()
                    ->code($matches[3])
                    ->name($matches[4]);
            }

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][2]/descendant::text()[normalize-space(.)][1]', $segment);

            if (preg_match('/^(?<airline>.+?)\s+(?<flightNumber>\d+)$/', $flight, $matches)) {
                $matches['airline'] = str_replace([',', '.'], ' ', $matches['airline']);
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);
            }

            if (empty($s->getAirlineName())) {
                $airline = $this->http->FindSingleNode('./td[1]/descendant::img/@alt', $segment);
                $s->setAirlineName($airline);
            }

            // operatedBy
            $operator = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][2]/descendant::text()[normalize-space(.)][last()]', $segment, true, '/' . $this->opt($this->t('operated by')) . '\s+(.+)/');

            if ($operator) {
                $s->airline()->operator($operator);
            }
        }

        // seats
        $seats = [];
        $seatRows = $this->http->XPath->query("//tr[{$this->eq($this->t('Seats'))}]/ancestor::table[1]/descendant::tr[not(.//tr) and normalize-space()][position()>1]");

        foreach ($seatRows as $sRow) {
            if ($this->http->XPath->query("self::tr[count(*)=2 and normalize-space(*[1])='' and normalize-space(*[2])]", $sRow)->length > 0) {
                if (isset($seatSet)) {
                    $seats[] = $seatSet;
                }
                $seatSet = [];

                continue;
            }
            $seatValue = $this->http->FindSingleNode("self::tr[count(*)=3 and normalize-space(*[1])='' and normalize-space(*[2])]/*[3][normalize-space()]", $sRow, true, '/^\d+[A-Z]$/');

            if ($seatValue) {
                $seatSet[] = $seatValue;
            }
        }

        if (isset($seatSet)) {
            $seats[] = $seatSet;
        }

        if (count($f->getSegments()) && count($seats) === count($f->getSegments())) {
            foreach ($seats as $key => $value) {
                if ($value) {
                    $f->getSegments()[$key]->extra()->seats($value);
                }
            }
        }

        // travellers
        $passengers = $this->http->FindNodes('//text()[' . $this->eq($this->t('Passenger')) . ']/ancestor::table[1]/descendant::tr[ ./td[1][./descendant::img] ]/td[2][string-length(normalize-space(.))>1]');
        $f->setTravellers($passengers);

        // price
        $payment = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Total paid to')) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]');

        if (
            preg_match('/^\s*(?<currency>[^\d\s]+)\s*(?<amount>\d[,.\'\d ]*)\s*$/', $payment, $matches)
            || preg_match('/^\s*(?<amount>\d[,.\'\d ]*)\s*(?<currency>[^\d\s]+)\s*$/', $payment, $matches)
            || preg_match('/^\s*\W?\s*(?<amount>\d[,.\'\d ]*)\s*(?:\W\s+)?(?<currency>[A-Z]{3})\s*$/u', $payment, $matches)
            || preg_match('/^\s*(?<currency>[A-Z]{3})(?:\s+\W)?\s*(?<amount>\d[,.\'\d ]*)\s*\W?\s*$/', $payment, $matches)
        ) {
            // $ 383,60    |    $311.80    |    1394,00 kr    |    $215,314.00 COP    |    222,12 € EUR    |    €165.98 EUR
            $email->price()
                ->currency($this->normalizeCurrency($matches['currency']))
                ->total($this->normalizeAmount($matches['amount']));

            $matches['currency'] = trim($matches['currency']);
            $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('cost'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

            if (preg_match('/^(?<amount>\d[,.\'\d ]*)\s*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $cost, $m)
                || preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d ]*)(?:[A-Z]{3})?$/', $cost, $m)
            ) {
                $email->price()->cost($this->normalizeAmount($m['amount']));
            }

            $feeRows = $this->http->XPath->query("//tr[not(.//tr) and count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('feeNames'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow);

                if (preg_match('/^(?<amount>\d[,.\'\d ]*)\s*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $feeCharge, $m)
                    || preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d ]*)(?:[A-Z]{3})?$/', $feeCharge, $m)
                ) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow);
                    $email->price()->fee($feeName, $this->normalizeAmount($m['amount']));
                }
            }
        }
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/^([^\d\W\.]{3,})[.]?\s+(\d{1,2})$/u', $string, $matches)) { // mai 13
            $month = $matches[1];
            $day = $matches[2];
            $year = $this->year;
        } elseif (preg_match('/^(\d{1,2})\s+([^\d\.]{3,})[.]?$/u', $string, $matches)) { // 22 Dec
            $month = $matches[2];
            $day = $matches[1];
            $year = $this->year;
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . ($year ? '.' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }
            $this->logger->error('0000000000000000');

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
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

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£', 'GBP £'],
            'EUR' => ['€', 'EUR €'],
            'USD' => ['US$', 'USD $'],
            'CAD' => ['CAD $'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
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

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
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

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'priceline'     => ['Priceline'],
            'alaskaair'     => ['Alaska Airlines', 'Alaska'],
            'virginamerica' => ['Virgin America'],
            'flybe'         => ['Flybe'],
            'klm'           => ['KLM'],
            'norwegian'     => ['Norwegian Air', 'Norwegian'],
            'aeroplan'      => ['Air Canada'],
            'colombia'      => ['Viva Air'],
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
}
