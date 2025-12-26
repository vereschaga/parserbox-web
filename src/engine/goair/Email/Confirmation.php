<?php

namespace AwardWallet\Engine\goair\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "goair/it-11342674.eml, goair/it-11387056.eml, goair/it-6329653.eml, goair/it-6336880.eml, goair/it-32531867.eml, goair/it-6204017.eml";

    public $reBody = [
        'es'  => ['ITINERARIO:', 'Descripción del Cargo'],
        'en'  => ['Traveler(s) Information:', 'Visit www.GoAir.in or Call'],
        'en2' => ['Information:', 'Go Airlines (India) Ltd.'],
        'en3' => ['Receipt and Itinerary as', 'Visit www.GoAir.in or Call'],
        'en4' => ['Thank you for choosing GoAir as your preferred airline', 'Go Travel'],
        'en5' => ['Apart from above charges Akbar Online service fee will be applicable for cancellation', 'Akbar'],
        'en6' => ['ITINERARY:', 'Charge Description'],
    ];
    public $subjects = [
        'es' => ['Confirmación de Vuelo en'],
        'en' => ['Confirmation #'],
    ];
    public $lang = '';
    public static $dictionary = [
        'es' => [
            'Booking Reference:' => 'Numero de Confirmación:',
            'Passenger(s)'       => 'Pasajero(s)',
            'Amount'             => 'Monto Final',
            'Seat'               => 'Asiento',
            'Summary'            => 'Resumen',
            //            'TOTAL' => '',
            'Fare Summary:' => 'Desglose parcial:',
            'Air fare'      => 'Tarifa',
            'Departure'     => ['Salida', 'SALIDA'],
            'Arrival'       => ['Llegada', 'LLEGADA'],
        ],
        'en' => [
            'Booking Reference:' => ['Booking Reference:', 'Confirmation number:'],
            'Fare Summary:'      => ['Fare Summary:', 'Reservation Totals:'],
            'Departure'          => ['Departure', 'DEPARTURE'],
            'Arrival'            => ['Arrival', 'ARRIVAL'],
        ],
    ];

    private $providerCode = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignProvider($parser->getHeaders());

        $this->assignLang($this->http->Response['body']);

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'AKBARTRAVELS.COM') or contains(normalize-space(), 'akbartravels.com')]")->length > 0) {
            $its = $this->parseEmailAkbar();
        } else {
            $its = $this->parseEmail();
        }

        return [
            'providerCode' => $this->providerCode,
            'emailType'    => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData'   => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->assignProvider($parser->getHeaders())
            && $this->assignLang($parser->getHTMLBody());
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@goair.in') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
    }

    public static function getEmailProviders()
    {
        return ['goair', 'airindia'];
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference:'))}]/following::text()[normalize-space()][1]");

        $passengers = $this->http->FindNodes("//text()[{$this->starts($this->t('Passenger(s)'))}]/ancestor::table[1][{$this->contains($this->t('Amount'))}]/following-sibling::table[.//tr[count(.//td)=4] and .//td[1][string-length(normalize-space())>2 and not({$this->contains($this->t('Passenger(s)'))} or {$this->contains($this->t('Seat'))} or {$this->contains($this->t('Summary'))})]]//td[1]", null, '/^[[:alpha:]][-,.\'[:alpha:] ]*[[:alpha:]]$/u');
        $passengers = array_filter($passengers);
        $it['Passengers'] = array_unique(array_map(function ($s) {
            return str_replace(" ,", ",", preg_replace("#\s+#", " ", $s));
        }, $passengers));

        $payment = $this->http->FindSingleNode("//text()[ {$this->eq($this->t('TOTAL'))} and ancestor::table[1][{$this->contains($this->t('Fare Summary:'))}] ]/ancestor::td[1]/following-sibling::td[2]");
        $tot = $this->getTotalCurrency($payment);

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[ {$this->eq($this->t('Air fare'))} and ancestor::table[1][{$this->contains($this->t('Fare Summary:'))}] ]/ancestor::td[1]/following-sibling::td[2]"));

        if (!empty($tot['Total'])) {
            $it['BaseFare'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $xpath = "//text()[{$this->starts($this->t('Departure'))}]/ancestor::tr[1][{$this->contains($this->t('Arrival'))}]/following-sibling::tr[1]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            if ($this->http->XPath->query("following-sibling::tr[normalize-space()]", $root)->length > 0) {
                $this->logger->alert("Found wrong flight segment!");

                return null;
            }

            $seg = [];

            $airportArr = $airportDep = '';
            $route = $this->http->FindSingleNode("*[1]", $root);

            if (count($parts = preg_split('/\s*–\s*/', $route)) === 2
                || count($parts = preg_split('/\s*\/\s*/', $route)) === 2
            ) {
                $airportDep = $parts[0];
                $airportArr = $parts[1];
            } elseif (preg_match("#(.+?)\s*[–-\/]\s*(.+)#", $route, $m)) {
                $airportDep = $m[1];
                $airportArr = $m[2];
            }

            // AUH (Abu Dhabi, AUH) Terminal 1
            $patterns['codeNameTerminal'] = '/^(?<code>[A-Z]{3})\s*\([^)(]*\)\s*(?i)Terminal\s*(?<terminal>[A-z\d]+)$/';

            // CJS (CIUDAD JUAREZ)
            $patterns['codeName'] = '/^(?<code>[A-Z]{3})\s*\([^)(]*\)$/';

            if (preg_match($patterns['codeNameTerminal'], $airportDep, $m)) {
                $seg['DepCode'] = $m['code'];
                $seg['DepartureTerminal'] = $m['terminal'];
            } elseif (preg_match($patterns['codeName'], $airportDep, $m)) {
                $seg['DepCode'] = $m[1];
            } elseif ($airportDep) {
                $seg['DepName'] = $airportDep;
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            if (preg_match($patterns['codeNameTerminal'], $airportArr, $m)) {
                $seg['ArrCode'] = $m['code'];
                $seg['ArrivalTerminal'] = $m['terminal'];
            } elseif (preg_match($patterns['codeName'], $airportArr, $m)) {
                $seg['ArrCode'] = $m[1];
            } elseif ($airportArr) {
                $seg['ArrName'] = $airportArr;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $node = $this->http->FindSingleNode("*[2]", $root);

            if (preg_match("#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*[–-]?\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            // it-11342674.eml vs it-32531867.eml
            $segmentType = $this->http->XPath->query("preceding-sibling::tr[1]/*[3][ descendant::text()[{$this->starts($this->t('Departure'))}] ]", $root)->length > 0 ? 0 : 1;

            $seg['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("*[3+$segmentType]", $root)));
            $seg['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("*[4+$segmentType]", $root)));

            $seg['Stops'] = $this->http->FindSingleNode($segmentType ? "*[3]" : "*[5]", $root, true, '/^\d{1,3}$/');

            if ($segmentType === 0) {
                $seg['DepartureTerminal'] = trim(preg_replace("#\s*Terminal\s*#i", ' ', $this->http->FindSingleNode("*[6]", $root)));
                $it['Status'] = $this->http->FindSingleNode("*[7]", $root);
            }

            $num = 6 * count($it['Passengers']) + 1;
            $a = $this->http->FindNodes("following::text()[{$this->starts($this->t('Passenger(s)'))}][1]/ancestor::table[1][{$this->contains($this->t('Amount'))}]/following-sibling::table[position()<{$num} and count(.//td)=4 and .//td[1][string-length(normalize-space(.))>2 and not({$this->contains($this->t('Passenger(s)'))})]]//td[2]", $root);
            $node = array_shift($a);

            if (preg_match("#^([A-Z]{1,2})\s+\-\s+(.+?)\s+\-#", $node, $m)) {
                $seg['BookingClass'] = $m[1];
                $seg['Cabin'] = $m[2];
            } else {
                $a = $this->http->FindNodes("following::text()[{$this->starts($this->t('Passenger(s)'))}][1]/ancestor::table[1][{$this->contains($this->t('Amount'))}]/following-sibling::table[position()<{$num} and count(.//td)=4 and .//td[1][string-length(normalize-space(.))>2 and {$this->contains($this->t('Seat'))}]]//td[2]", $root);
                $node = array_shift($a);

                if (preg_match("#^([A-Z]{1,2})\s+\-\s+(.+?)\s+\-#", $node, $m)) {
                    $seg['BookingClass'] = $m[1];
                    $seg['Cabin'] = $m[2];
                }
                $node = $this->http->FindNodes("following::text()[{$this->starts($this->t('Passenger(s)'))}][1]/ancestor::table[1][{$this->contains($this->t('Amount'))}]/following-sibling::table[position()<{$num} and count(.//td)=4 and .//td[1][string-length(normalize-space(.))>2 and {$this->contains($this->t('Seat'))}]]//td[1]", $root, "#{$this->opt($this->t('Seat'))}\s*:\s*(\d+[A-Z])\b#i");
                $seg['Seats'] = $node;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

//    private function parseAdditionally()
//    {
//        $result = ['Passengers' => []];
//        foreach ($this->http->XPath->query('//text()[contains(., "Charge Description")]/ancestor::table[1]/following-sibling::table//td[2]') as $root) {
//            if ('TFEE - Transaction Fee' == $root->nodeValue || 'WFEE - Web Consumer Fee' == $root->nodeValue || stripos($root->nodeValue,
//                    'CANCELLATION FEES') !== false
//            ) {
//                $node = $this->http->FindSingleNode('preceding-sibling::td[1]', $root, true, '/([A-Z\s\,\.]+)/i');
//                if (stripos($node, 'Bag Allowance') === false) {
//                    $result['Passengers'][] = $node;
//                }
//            }
//        }
//        foreach ($this->http->XPath->query('//text()[contains(., "Reservation Totals")]/ancestor::table[1]//td[2]') as $root) {
//            if (preg_match('/^([\d.,]+)\s*([A-Z]{3})$/',
//                $this->http->FindSingleNode('following-sibling::td[last()]', $root), $matches)) {
//                if (in_array($matches[2], ['KWD'])) {
//                    $matches[1] = PriceHelper::cost($matches[1], '.', ',');
//                } else {
//                    $matches[1] = PriceHelper::cost($matches[1]);
//                }
//                if ('Air fare' == trim($root->nodeValue)) {
//                    $result['BaseFare'] = $matches[1];
//                    $result['Currency'] = $matches[2];
//                } elseif ('Tax' == trim($root->nodeValue) || 'Taxd>' == $root->nodeValue) {
//                    $result['Tax'] = $matches[1];
//                    $result['Currency'] = $matches[2];
//                } elseif ('TOTAL' == trim($root->nodeValue) || 'TOTAL PAYMENTS' == trim($root->nodeValue)) {
//                    $result['TotalCharge'] = $matches[1];
//                    $result['Currency'] = $matches[2];
//                } else {
//                    if (preg_match("#^\s*[^\d]#", $root->nodeValue) && stripos(trim($root->nodeValue),
//                            'XXXXXXXXXX') === false
//                    ) {
//                        $result['Fees'][] = ['Name' => $root->nodeValue, 'Charge' => $matches[1]];
//                    }
//                }
//            }
//        }
//        if (isset($result['Passengers'])) {
//            $result['Passengers'] = array_filter(array_unique(array_map("trim", $result['Passengers'])));
//        }
//        if( empty($result['Passengers']) )
//            $result['Passengers'][] = $this->http->FindSingleNode("//td[contains(., 'Payment Summary') and not(.//td)]/following-sibling::td[1]/descendant::text()[1]", null, true, '/([A-Z\s\,\.]+)/i');
//        return $result;
//    }

    private function parseEmailAkbar()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'PNR:') or starts-with(normalize-space(.), 'Confirmation number:')]/following::text()[normalize-space()][1]");
        $it['Passengers'] = array_values(array_unique($this->http->FindNodes("//text()[normalize-space(.)='Passenger(s)']/ancestor::tr[1][contains(.,'Total')]/following-sibling::tr[count(./td)>3]/td[1][string-length(normalize-space(.))>2]")));

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[normalize-space(.)='TOTAL' or normalize-space(.)='TOTAL PAYMENTS']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1])[last()]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $xpath = "//text()[starts-with(normalize-space(.),'DEPARTURE')]/ancestor::tr[1][contains(.,'ARRIVAL')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $node = $this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root);
            $parts = array_map('trim', explode('/', $node));

            if (count($parts) === 2) {
                if (preg_match("#^\s*([A-Z]{3})\s*\((.+?)(?:TERMINAL[ ]*(.*))?\)#", $parts[0], $m)) {
                    $seg['DepCode'] = $m[1];
                    $seg['DepName'] = trim($m[2]);

                    if (!empty($m[3])) {
                        $seg['DepartureTerminal'] = trim(str_ireplace('Terminal', '', $m[3]));
                    }
                } else {
                    $seg['DepName'] = $parts[0];
                }

                if (preg_match("#^\s*([A-Z]{3})\s*\((.+?)(?:TERMINAL[ ]*(.*))?\)#", $parts[1], $m)) {
                    $seg['ArrCode'] = $m[1];
                    $seg['ArrName'] = trim($m[2]);

                    if (!empty($m[3])) {
                        $seg['ArrivalTerminal'] = trim(str_ireplace('Terminal', '', $m[3]));
                    }
                } else {
                    $seg['ArrName'] = $parts[1];
                }
            }
            $node = $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root);

            if (preg_match("#([A-Z\d]{2})[\–\- ]*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $seg['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[4]", $root)));
            $seg['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[5]", $root)));
            $seg['Stops'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[6]", $root);
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        //	    $this->logger->alert($date);
        $in = [
            //Fri-05Dec2014 14:30
            '#\D+?(\d+)\s*(\w+)\s*(\d{4})\s+(\d+:\d+)#',
            '/(\d{1,2}\/\d{1,2}\/\d{2,4}\s+\d{1,2}:\d{2})/',
        ];
        $out = [
            '$1 $2 $3 $4',
            '$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function assignProvider(array $headers): bool
    {
        if ($this->http->XPath->query('//a[contains(@href,"goair.in") or contains(@href,"www.tarmexico.com")]')->length > 0
            || $this->http->XPath->query('//node()[contains(.,"www.GoAir.in") or contains(.,"@tarmexico.com")]')->length > 0
            || $this->http->XPath->query('//img[contains(@src,"goair.in")]')->length > 0
        ) {
            $this->providerCode = 'goair'; // + tarmexico

            return true;
        }

        if (stripos($headers['from'], '@airindiaexpress') !== false
            || stripos($headers['subject'], 'AIRINDIAEXPRESS Confirmation #') !== false
            || $this->http->XPath->query('//node()[contains(.,"@airindiaexpress.")]')->length > 0
            || $this->http->XPath->query('//img[contains(@src,"air-india-express")]')->length > 0
        ) {
            $this->providerCode = 'airindia';

            return true;
        }

        return false;
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
}
