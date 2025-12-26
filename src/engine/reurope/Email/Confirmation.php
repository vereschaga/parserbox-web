<?php

namespace AwardWallet\Engine\reurope\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "reurope/it-11941909.eml, reurope/it-12777339.eml, reurope/it-16870830.eml, reurope/it-17404241.eml, reurope/it-41840129.eml, reurope/it-41889354.eml, reurope/it-5910734.eml, reurope/it-5959145.eml"; // +2 bcdtravel(html)[en]
    public $lang = '';

    public static $dict = [
        'en' => [
            'Booking number:'   => ['Booking number:', 'Your booking number:'],
            'Ticket reference:' => ['Ticket reference:', 'Ticket reference :'],
            'Total'             => ['Total', 'Grand total'],
            'Passengers'        => ['Passengers', 'Passenger', 'Travellers', 'Travelers', 'TRAVELERS', 'Traveler'],
            'travellerPrefixes' => ['Adult', 'Senior', 'ADULT'],
            'Outbound'          => ['Outbound', 'Inbound'],
            'Fare details'      => ['Fare details', 'Details and conditions', 'Product Conditions'],
        ],
        'pt' => [
            'Contact details'   => 'Informacões de contato',
            'Booking number:'   => ['Número de reserva:'],
            'Ticket reference:' => ['Código de referência do bilhete:', 'Código de referência do bilhete :', 'DB BAHNTIX reference:', 'DB BAHNTIX reference :'],
            'Total'             => ['Preço', 'Preço líquido'],
            'Passengers'        => ['Passageiros', 'Passageiro'],
            'travellerPrefixes' => ['Adulto', 'ADULTO', 'Jovem', 'JOVEM'],
            'Outbound'          => ['Ida'],
            'Fare details'      => 'Condições do produto',
            'Product details'   => 'Detalhes do produto',
            'Train'             => 'Tren',
            'SEATS:'            => 'ASSENTOS:',
            'Coach'             => 'Vagão',
            'Seat'              => 'Assento',
        ],
        'es' => [
            'Contact details'   => 'Datos de contacto',
            'Booking number:'   => ['Número de reserva:'],
            'Ticket reference:' => ['Referencia de tickets:', 'Referencia de tickets :'],
            'Total'             => ['Total', 'Total general'],
            'Passengers'        => ['PASAJERO', 'Pasajero'],
            'travellerPrefixes' => ['Adulto', 'ADULTO'],
            'Outbound'          => ['Salida', 'Retorno'],
            'Fare details'      => ['Envío', 'Condiciones del producto'],
            //            'Product details' =>'',
            'Train'  => 'Tren',
            'SEATS:' => 'ASIENTOS:',
            'Coach'  => 'Vagón',
            'Seat'   => 'Asiento',
        ],
    ];

    private $subjects = [
        'en' => ['Confirmation: Booking'],
        'pt' => ['confirmação de pedido:'],
        'es' => ['Confirmación del pedido:'],
    ];

    private $langDetectors = [
        'en' => ['Booking number:', 'Your booking number:', 'Product details', 'Product Conditions'],
        'pt' => ['Informacões de contato'],
        'es' => ['Número de reserva:'],
    ];

    private $reBody = [
        // en
        'your rail products with Rail Europe',
        'Best regards, The Rail Europe Team',
        'administrative fee from Rail Europe',
        'Train Tickets Order Summary',
        'Product Conditions',
        // pt
        'Obrigado por comprar com a Rail Europe!',
        // es
        '¡Gracias por reservar con Rail Europe!',
    ];
    private $usDateFormat = false;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@raileurope.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && stripos($headers['subject'], 'Rail Europe') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[' . $this->contains($this->reBody) . ']')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.raileurope.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('Confirmation' . ucfirst($this->lang));

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
            'confNumber' => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
            'time'       => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon    |    10:42
        ];

        if ($this->http->FindSingleNode("//text()[{$this->eq($this->t("Contact details"))}]/ancestor::table[2]", null, true, '/(United states|\bCanada\b)/i') !== null) {
            $this->usDateFormat = true;
        }

        $email->obtainTravelAgency(); // because Rail Europe is not carrier

        $bookingNumberTitle = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Booking number:')) . ']', null, true, '/^(.+?)[\s:]*$/');

        if ($bookingNumberTitle) {
            $bookingNumber = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Booking number:')) . ']/following::text()[normalize-space(.)][1]', null, true, '/^(' . $patterns['confNumber'] . ')$/');
            $email->ota()->confirmation($bookingNumber, $bookingNumberTitle);
        }

        $xpath = "//text()[{$this->eq($this->t('Fare details'))}]/ancestor::tr[ ./preceding-sibling::tr[normalize-space(.)] ][1]/preceding-sibling::tr[normalize-space(.)][1][ ./descendant::text()[{$this->contains($this->t('Outbound'))}] ]";
        $trains = $this->http->XPath->query($xpath);

        if ($trains->length === 0) {
            $this->logger->notice('Trains not found!');
        } else {
            $this->logger->debug('Trains count: ' . $trains->length);
            $this->logger->debug('Trains [xpath]: ' . $xpath);
        }

        foreach ($trains as $train) {
            $t = $email->add()->train();

            // confirmationNumbers
            $routeFrom = $this->http->FindSingleNode('./descendant::img[1]/preceding::text()[normalize-space(.)][1]', $train);
            $routeTo = $this->http->FindSingleNode('./descendant::img[1]/following::text()[normalize-space(.)][1]', $train);

            if ($routeFrom && $routeTo) {
                $ticketReferenceNodes = $this->http->XPath->query('//text()[' . $this->eq($this->t('Product details')) . "]/preceding::img[ ./preceding::text()[normalize-space(.)][1][{$this->eq($routeFrom)}] and ./following::text()[normalize-space(.)][1][{$this->eq($routeTo)}] ]/ancestor::table[ ./following-sibling::table[normalize-space(.)] ][1]/../descendant::text()[{$this->starts($this->t('Ticket reference:'))}]/ancestor::td[1]", $train);

                foreach ($ticketReferenceNodes as $ticketReferenceNode) {
                    if (preg_match_all('/\b(' . $this->opt($this->t('Ticket reference:')) . ')\s*(' . $patterns['confNumber'] . ')\b/', $ticketReferenceNode->nodeValue, $matches)) {
                        foreach ($matches[1] as $key => $value) {
                            $t->general()->confirmation($matches[2][$key], preg_replace('/\s*:$/', '', $matches[1][$key]));
                        }
                    }
                }

                if ($ticketReferenceNodes->length === 0) {
                    $t->general()->noConfirmation();
                }
            }

            // travellers
            $travellers = [];
            $xpathFragmentTravellers = './descendant::text()[' . $this->contains($this->t('Passengers')) . ']';
            $travellersText = $this->http->FindSingleNode($xpathFragmentTravellers, $train, true, '/:\s*(.+)/');

            if ($travellersText) {
                // it-11941909.eml
                $travellers = preg_split('/\s*,\s*/', $travellersText);
            }

            if (count($travellers) < 1) {
                $travellerTexts = $this->http->FindNodes($xpathFragmentTravellers . '/ancestor::table[1]/descendant::text()[' . $this->starts($this->t('travellerPrefixes')) . ']', $train, '/' . $this->opt($this->t('travellerPrefixes')) . '[^:]*[:]+\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/u');
                $travellerValues = array_values(array_filter($travellerTexts));

                if (!empty($travellerValues[0])) {
                    $travellers = $travellerValues;
                } elseif (!empty(array_filter($this->http->FindNodes($xpathFragmentTravellers . '/ancestor::table[1]/descendant::text()[' . $this->starts($this->t('travellerPrefixes')) . ']', $train, '/' . $this->opt($this->t('travellerPrefixes')) . '\s*:?\s*$/')))) {
                    $travellerTexts = $this->http->FindNodes($xpathFragmentTravellers . '/ancestor::table[1]/descendant::text()[' . $this->starts($this->t('travellerPrefixes')) . ']/following::text()[normalize-space()][1]', $train);
                    $travellerValues = array_values(array_filter($travellerTexts));

                    if (!empty($travellerValues[0])) {
                        $travellers = $travellerValues;
                    }
                }
            }

            if (count($travellers)) {
                $t->general()->travellers($travellers);
            }

            $nodes = $this->http->FindNodes("./descendant::table[1]/descendant::text()[normalize-space()!='']", $train);

            if (count($nodes) == 2) {
                $startTrip = $nodes[0];
                $endTrip = $nodes[1];
            } else {
                $startTrip = $endTrip = null;
            }

            // segments
            $rule1 = 'string-length(normalize-space(.))>3';
            $rule2 = $this->eq($this->t('Fare details'));
            $xpathQuery = "./descendant::tr[ not(.//tr) and count(./td)=2 and ./td[1][count(./descendant::img)=1] and ./td[2][{$rule1}] and count(./following-sibling::tr)=1 and ./following-sibling::tr[count(./descendant::td[{$rule1}])=1] and ./following::text()[{$rule2}] ]/ancestor::tr[1]/..";
            $segments = $this->http->XPath->query($xpathQuery, $train);

            foreach ($segments as $segment) {
                $s = $t->addSegment();

                $date = 0;
                $dateText = $this->http->FindSingleNode('./preceding::text()[' . $this->contains($this->t('Outbound')) . '][1]', $segment, true, '/' . $this->opt($this->t('Outbound')) . '\s+(.+)/i');

                if ($dateText && $dateNormal = $this->normalizeDate($dateText)) {
                    $date = strtotime($dateNormal);
                }

                $patterns['timeAirport'] = '/^(?<time>' . $patterns['time'] . ')\s+(?<airport>.+)/';

                // depDate
                // depName
                $departure = $this->http->FindSingleNode('./tr[1]//tr[1]', $segment);

                if (preg_match($patterns['timeAirport'], $departure, $matches)) {
                    if (!empty($date)) {
                        $s->departure()->date(strtotime($matches['time'], $date));
                    }
                    $s->departure()->name('Europe, ' . $matches['airport']);
                }

                // arrDate
                // arrName
                $arrival = $this->http->FindSingleNode('./tr[1]//tr[2]', $segment);

                if (preg_match($patterns['timeAirport'], $arrival, $matches)) {
                    if (!empty($date)) {
                        $s->arrival()->date(strtotime($matches['time'], $date));
                    }
                    $s->arrival()->name('Europe, ' . $matches['airport']);
                }

                // number
                // cabin
                $segmentInfo = $this->http->FindSingleNode('./tr[2]/descendant::text()[normalize-space(.)][1]', $segment);

                if ($segmentInfo && count($segmentInfoParts = preg_split('/\s*\|\s*/', $segmentInfo)) === 3) {
                    // Train 9327 | Seat reservation included | 1st Class
                    if (preg_match("/(?:{$this->t('Train')} )?(?:(\D+))?(\d+)$/i", $segmentInfoParts[0], $matches)) {
                        // Train 9018
                        $s->extra()->number($matches[2]);
                        //Train VT759100
                        //.Italo 8900
                        if (isset($matches[1]) && !empty($matches[1])) {
                            $s->extra()->type($matches[1]);
                        }
                    } elseif ($segmentInfoParts[0] === '' || $segmentInfoParts[0] === $this->t('Train')) {
                        // | Seat reservation included | 1st Class
                        // Train | Seat reservation not included | 2nd class
                        $s->extra()->noNumber();
                    }

                    if (!empty($segmentInfoParts[2])) {
                        $s->extra()->cabin($segmentInfoParts[2]);
                    }
                }

                // seats
                $seatsText = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('SEATS:'))}]", $segment, false, "/{$this->opt($this->t('SEATS:'))}\s*(.+)/");

                if (preg_match_all("/({$this->opt($this->t('Coach'))}\s+(.+?)\s+{$this->opt($this->t('Seat'))}\s+(\w+))/iu", $seatsText, $seatMatches)
                    || preg_match_all("/((.+)\s+{$this->opt($this->t('Coach'))}\s+{$this->opt($this->t('Seat'))}\s+(\d+))/iu", $seatsText, $seatMatches)
                ) {
                    if (count(array_unique($seatMatches[2])) === 1) {
                        $s->extra()
                            ->car($seatMatches[2][0])
                            ->seats($seatMatches[3]);
                    } else {
                        $s->extra()->seats($seatMatches[1]);
                    }
                }
            }

            // duration
            if (count($t->getSegments()) === 1) {
                $duration = $this->http->FindSingleNode('./preceding::text()[' . $this->contains($this->t('Outbound')) . '][1]/ancestor::tr[count(./td)=5]/td[5]', $segments->item(0));
                $t->getSegments()[0]->extra()->duration($duration, false, true);
            }

            if (!empty($startTrip) && !empty($endTrip)) {
                $priceTexts = $this->http->FindNodes("//text()[starts-with(normalize-space(),'{$startTrip}') and ./following::text()[normalize-space()!=''][1][contains(.,'{$endTrip}')]]/ancestor::table[starts-with(normalize-space(),'{$startTrip}')][1]/ancestor::td[./following-sibling::td][1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]");
                $price = implode(' ', $priceTexts);

                if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
                    // €59 90
                    $t->price()
                        ->currency($this->normalizeCurrency($matches['currency']))
                        ->total($this->normalizeAmount($matches['amount']));
                }
            }
        }

        // p.currencyCode
        // p.total
        $totalPaymentTexts = $this->http->FindNodes('//text()[' . $this->eq($this->t('Total')) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)]');
        $totalPaymentText = implode(' ', $totalPaymentTexts);
        // $610    |    $361 95
        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $totalPaymentText), $matches)) {
            $email->price()
                ->currency($this->normalizeCurrency($matches['currency']))
                ->total($this->normalizeAmount($matches['amount']));
            // p.fees
            $feeRows = $this->http->XPath->query('//text()[' . $this->eq($this->t('Total')) . ']/ancestor::table[ ./preceding-sibling::table[normalize-space(.)] ][1]/preceding-sibling::table[normalize-space(.)][1]/descendant::tr[ not(.//tr) and ./td[normalize-space(.)][2] ]');

            foreach ($feeRows as $feeRow) {
                $feeName = $this->http->FindSingleNode('./td[normalize-space(.)][1]', $feeRow);
                $feeChargeTexts = $this->http->FindNodes('./td[normalize-space(.)][last()]/descendant::text()[normalize-space(.)]', $feeRow);
                $feeChargeText = implode(' ', $feeChargeTexts);

                if (preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1.$2', $feeChargeText), $m)) {
                    $email->price()->fee($feeName, $this->normalizeAmount($m['amount']));
                }
            }
        }
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

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
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

    private function normalizeDate(string $string)
    {
        $string = preg_replace('/^\s*(\d{1,2}\/\d{1,2}\/)(\d{2})\s*$/', '${1}' . '20$2', $string);

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $string, $m)) { // 08/21/2018
            if ($this->usDateFormat === true || (int) $m[2] > 12) {
                return $m[2] . '.' . $m[1] . '.' . $m[3];
            } else {
                return str_replace('/', '.', $string); // 31/03/2018
            }
        }

        return $string;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
            'SGD' => ['SG$'],
            'ZAR' => ['R'],
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
}
