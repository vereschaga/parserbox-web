<?php

namespace AwardWallet\Engine\swissair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Itinerary extends \TAccountChecker
{
    public $reFrom = '@swiss';

    public $mailFiles = "swissair/it-1835056.eml, swissair/it-1859845.eml, swissair/it-2315228.eml, swissair/it-2588007.eml, swissair/it-2768603.eml, swissair/it-2856949.eml, swissair/it-3048617.eml, swissair/it-4618843.eml, swissair/it-4680720.eml, swissair/it-49258061.eml, swissair/it-5388946.eml, swissair/it-5698854.eml, swissair/it-5708658.eml, swissair/it-6109753.eml, swissair/it-124057903.eml";

    public $reBody = [
        'fr'  => ['Informations sur le vol', 'NOTTRANSLATED', 'Référence'],
        'fr2' => ['Information sur le vol', 'NOTTRANSLATED', 'Référence'],
        'de'  => ['Fluginformationen', 'Umbuchungs-Information', 'Buchungsreferenz'],
        'de2' => ['Fluginformationen', 'Ihre Vorteile mit SWISS Upgrade Bargain', 'Buchungsreferenz'],
        'de3' => ['Fluginformationen', 'Fluginformation nach der Umbuchung', 'Buchungsreferenz'],
        'en'  => ['Flight', 'NOTTRANSLATED', 'information'],
        'pt'  => ['Voo', 'NOTTRANSLATED', 'Informações'],
        'it'  => ['Informazioni di volo', 'NOTTRANSLATED', 'swiss'],
        'es'  => ['Información del vuelo', 'Reserva avanzada de asiento', 'swiss'],
        'ja'  => ['フライト', 'NOTTRANSLATED', 'インフォメーション'],
    ];

    public $reSubject = [
        'en' => ['Your SWISS flight', 'Rebooking information'],
        'fr' => ['Votre vol SWISS'],
        'de' => ['Ihr SWISS Flug', 'Umbuchungsinformation'],
        'pt' => ['Seu voo SWISS'],
        'it' => ['Il suo volo SWISS'],
        'es' => ['Su vuelo SWISS'],
        'ja' => ['お客様の SWISS ご搭乗便'],
    ];

    public $lang = 'en';
    public static $dict = [
        'en' => [
            'Booking Reference'  => ['Your booking reference', 'Booking Reference', 'Booking reference', 'Référence de réservation'],
            'Flight information' => ['Flight information', 'Your booking information', 'Flight details after rebooking', 'Following flights are eligible'],
            'Operated by'        => ['Operated by', 'Flight operated by', 'OPERATED BY'],
            'Hello'              => ['Hello', 'Dear'],
            'Passenger'          => ['customer', 'Passenger'], // Dear Passenger
        ],
        'fr' => [
            'Booking Reference'  => 'Référence de réservation',
            'Flight information' => ['Informations sur le vol', 'Information sur le vol'],
            'Selected services'  => 'Prestations choisies',
            'Outbound flight'    => 'Vol aller',
            'Return flight'      => 'Vol de retour',
            'Operated by'        => ['Opéré par', 'Operated by'],
            'Fare'               => 'Tarif',
            'Grand total'        => 'Montant total',
            'Hello'              => 'Bonjour',
            'Passenger'          => 'cliente', // Dear Passenger
        ],
        'de' => [
            'Booking Reference'  => 'Buchungsreferenz:',
            'Selected services'  => 'Gewählte Leistungen',
            'Flight information' => ['Fluginformationen', 'Umbuchungs-Information', 'Für folgende Flüge', 'Fluginformation nach der Umbuchung'],
            'Outbound flight'    => 'Hinflug',
            'Return flight'      => 'Rückflug',
            'Operated by'        => ['Durchgeführt von', 'Operated by'],
            'Fare'               => 'Flugtarif',
            'Grand total'        => 'Gesamtpreis',
            'time'               => 'Reisedauer', // any word in duration
            'Hello'              => 'Grüezi',
            'Passenger'          => 'Passagier', // Dear Passenger
        ],
        'pt' => [
            'Booking Reference'  => 'Sua referência de reserva:',
            'Selected services'  => 'Serviços selecionados',
            'Flight information' => 'Informações de voos',
            'Outbound flight'    => 'Voo de ida',
            'Return flight'      => 'Voo de volta',
            'Operated by'        => ['Operado por'],
            'Fare'               => 'Tarifa',
            'Grand total'        => 'Total final',
            'time'               => 'Horário', // any word in duration
            // 'Passenger'          => '', // Dear Passenger
        ],
        'it' => [
            'Booking Reference'  => ['Codice di prenotazione:', 'Riferimento della prenotazione:', 'Referenza della prenotazione:'],
            'Selected services'  => 'Servizi scelti',
            'Flight information' => 'Informazioni di volo',
            'Outbound flight'    => 'Volo di andata',
            'Return flight'      => 'NOTTRANSLATED',
            'Operated by'        => ['Operato da'],
            'Fare'               => 'Tariffa',
            'Grand total'        => 'Totale',
            'time'               => 'Durata ', // any word in duration
            'Hello'              => 'Buongiorno',
            // 'Passenger'          => '', // Dear Passenger
        ],
        'es' => [
            'Booking Reference'  => ['Su código localizador:', 'Referencia de la reserva:', 'Número de reserva:'],
            'Selected services'  => 'Servicios seleccionados',
            'Flight information' => ['Información del vuelo', 'Información de la reserva'],
            'Outbound flight'    => ['Vuelo de ida', 'Outbound flight'],
            'Return flight'      => 'Vuelo de vuelta',
            'Operated by'        => ['Realizado por', 'Operated by', 'Operado por'],
            'Fare'               => 'Tarifa',
            'Grand total'        => 'Suma total',
            'time'               => ['Duración ', 'Tiempo de trasbordo'], // any word in duration
            'Hello'              => 'Saludos',
            'Passenger'          => 'cliente', // Dear Passenger
        ],
        'ja' => [
            'Booking Reference'  => 'お客様のご予約番号:',
            'Selected services'  => '選択されたサービス',
            'Flight information' => 'フライトインフォメーション',
            'Outbound flight'    => '往路便',
            //            'Return flight' => '',
            'Operated by' => ['運航航空会社'],
            //            'Fare' => '',
            'Grand total' => '合計',
            'time'        => ['移動時間: '], // any word in duration
            'Hello'       => 'Dear',
            // 'Passenger'          => '', // Dear Passenger
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = text($this->http->Response['body']);
        $this->assignLang($body);
        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->assignLang($parser->getHTMLBody()) && $this->http->XPath->query("//img[contains(@src, 'swiss.com')]|//text()[contains(normalize-space(.), 'SWISS')]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            foreach ($reSubject as $ss) {
                if (stripos($headers['subject'], $ss) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function replace_one($search, $replace, $text)
    {
        if (substr_count($text, $search) >= 2) {
            $pos = strpos($text, $search);

            return $pos !== false ? substr_replace($text, $replace, $pos, strlen($search)) : $text;
        } else {
            return $text;
        }
    }

    protected function contains($params, $operator = 'or', $str1 = 'normalize-space(text())')
    {
        $arr = [];

        if (is_array($params)) {
            foreach ($params as $str2) {
                $arr[] = "contains({$str1}, '{$str2}')";
            }
        } else {
            $arr[] = "contains({$str1}, '{$params}')";
        }

        return join(" {$operator} ", $arr);
    }

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        $bookingRef = $this->contains($this->t('Booking Reference'), 'or', 'normalize-space(.)');

        $confNo = $this->http->FindSingleNode('(//text()[' . $bookingRef . '])[1]', null, true, "#.+\:\s*(.+)#");

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode('//text()[' . $bookingRef . ']/following::text()[normalize-space(.)][1]', null, true, '/([A-Z\d]{5,9})/');
        }
        $f->general()->confirmation($confNo);

        $nodes = $this->http->FindNodes("//*[contains(text(), '" . $this->t("Selected services") . "')]/following::table[2]/tbody/tr/td/table[1]");

        if ($nodes == null) {
            $nodes = $this->http->FindNodes("(//*[contains(text(), '" . $this->t("Selected services") . "')]/following::table[2]//table)[1]");
        }

        if ($nodes != null) {
            $travellers = array_map(function ($x) {
                return trim(str_replace($this->t("name"), '', $x));
            }, $nodes);
        }

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(.), '{$this->t('Passagier(e)')}')][1]/ancestor::tr[1]/descendant::text()[normalize-space()!=''][not(contains(normalize-space(.), '{$this->t('Passagier(e)')}'))]");
        }

        if (empty($travellers)) {
            $customer = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*(?:[,;!?]+|$)/u");

            if (!preg_match("/^{$this->opt($this->t('Passenger'))}$/i", $customer)) {
                // it-4618843.eml
                $travellers[] = $customer;
            }
        }

        if (!empty($travellers)) {
            if ($travellers = array_filter($travellers)) {
                $f->general()->travellers(array_filter($travellers));
            }
        }

        $totalPrice = $this->http->FindSingleNode("//*[{$bookingRef}]/following::table[contains(.,'{$this->t("Grand total")}')]/following-sibling::table[1]");

        if (preg_match("#\b([A-Z]{3})\s*([\d.,\s']*\d)#", $totalPrice, $m)) {
            // AED 2,635.00
            $f->price()
                ->currency($m[1])
                ->total($this->normalizeAmount($m[2]));

            // Fees
            $fees = $this->http->XPath->query("//td[{$this->contains($this->t('SWISS Service Fee'))}]/ancestor::table[1]//tr");

            foreach ($fees as $fee) {
                $name = $this->http->FindSingleNode("./td[1]", $fee);
                $sum = $this->http->FindSingleNode('td[2]', $fee, true, '/.*\d.*/');

                if ($sum === null) {
                    continue;
                }
                $sum = $this->normalizeAmount($sum);

                if (in_array($name, (array) $this->t('Fare'))) {
                    $f->price()->cost($sum);

                    continue;
                }
                $f->price()->fee($name, $sum);
            }
        }
        $tickets = array_filter($this->http->FindNodes("//*[contains(text(), '" . $this->t("Selected services") . "')]/following::table[2]/descendant::tr[1]/../tr/td/table[2]/descendant::table[1]",
            null, "#^\s*(?:E-Ticket)?\s*([\d\- ]{5,})\s*$#i"));

        if (!empty($tickets)) {
            $f->issued()->tickets($tickets, false);
        }

        // 06:45    |    11.30
        $xpathTime = 'starts-with(translate(normalize-space(),"0123456789：.","dddddddddd::"),"dd:dd")';

        $flightInfo = $this->contains($this->t('Flight information'));
        $xpath = "//*[{$flightInfo}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]/descendant::tr[count(*[{$xpathTime}])=2 or count(*[{$xpathTime}])=1 and count(*/descendant::text()[string-length(normalize-space())=3])=2]/ancestor::table[2][count(following-sibling::table[normalize-space()])=1]/ancestor::table[1][count(preceding-sibling::table[normalize-space()])=1]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->notice("Segments root not found: $xpath");

            return;
        }

        $this->logger->notice('Segments found by: ' . $xpath);

        $patterns['time'] = '\d{1,2}[:.]\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?'; // 09:50    |    13.25

        if ($roots->length > 0) {
            foreach ($roots as $root) {
                $dataFlight = implode(' ', $this->http->FindNodes('./td//text()[normalize-space(.)]', $root));
                $this->logger->critical($dataFlight);

                // Sun 15.03.2020 13:45 ZRH 19:05 SJO Travel time 12h 20m LX 8036 Economy Saver - L Operated by EDELWEISS AIR
                $re = '/';
                $re .= "(?<DateFly>\d+[\/\s.]+[\d\w]+[\/\s.]+\d+)(?:\s+\w*)?\s+(?<DepTime>{$patterns['time']})\s*(?<addDepDay>[+]\s*\d+)?\s+";
                $re .= "(?<DepCode>[A-Z]{3})\s*(?<ArrTime>{$patterns['time']}|:)?\s*(?<addArrDay>[+]\s*\d+)?\s+(?<ArrCode>[A-Z]{3})\s+";
                $re .= '(?<Duration>.*)?\s*';
                $re .= '(?<AirlineName>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s+(?<FlightNumber>\d+).?\*?\s*(?<other>.+)?';
                $re .= '/u';

                if (preg_match($re, $dataFlight, $m)) {
                    $s = $f->addSegment();
                    $s->departure()->date($this->normalizeDate($m['DateFly'] . ' ' . str_replace('.', ':', $m['DepTime'])));

                    if (!empty($m['addDepDay']) && !empty($s->getDepDate())) {
                        $s->departure()->date(strtotime($m['addDepDay'] . ' day', $s->getDepDate()));
                    }

                    if ($m['ArrTime'] === ':' || empty($m['ArrTime'])) {
                        $s->arrival()->noDate();
                    } else {
                        $s->arrival()->date($this->normalizeDate($m['DateFly'] . ' ' . str_replace('.', ':', $m['ArrTime'])));
                    }

                    if (!empty($m['addArrDay']) && !empty($s->getArrDate())) {
                        $s->arrival()->date(strtotime($m['addArrDay'] . ' day', $s->getArrDate()));
                    }

                    $s->departure()->code($m['DepCode']);
                    $s->arrival()->code($m['ArrCode']);

                    if (!empty($m['Duration'])) {
                        if (preg_match("/{$this->opt($this->t('time'))}.*?:?\s*(?<Duration>\d+h\s*\d+m(?:in)?)/", $m['Duration'], $mat)) {
                            $s->extra()->duration($mat['Duration']);
                        }
                    }
                    $s->airline()->name($m['AirlineName']);
                    $s->airline()->number($m['FlightNumber']);
                    $operator = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Operated by'))}]", $root, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)/");

                    if ($operator) {
                        $s->airline()->operator($this->normalizeOperatorBy($operator));
                    }

                    if (!empty($m['other']) && preg_match("/^(?<Cabin>[\w\s]{2,}?)(?:\s*-\s*(?<BookingClass>[A-Z]{1,2}))?\s*(?:{$this->opt($this->t('Operated by'))}|$)/", $m['other'], $matches)) {
                        // Economy Saver - L Operated by EDELWEISS AIR    |    Economy Saver Operated by EDELWEISS AIR
                        $s->extra()->cabin($matches['Cabin']);

                        if (!empty($matches['BookingClass'])) {
                            $s->extra()->bookingCode($matches['BookingClass']);
                        }
                    }
                    //$this->logger->debug(var_export($m, true));
                } else {
                    $this->logger->notice('Parse 2');
                    $re2 = '/';
                    $re2 .= "(?<AirlineName>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<FlightNumber>\d+)\s+(?:{$this->opt($this->t('Operated by'))}?\s+";
                    $re2 .= "(?<Operator>.+)\s+)?(?<DepTime>{$patterns['time']})\s+(?<DepCode>[A-Z]{3})\s+(?:Terminal\s+";
                    $re2 .= "(?<DepartureTerminal>[\w\s]+))?.+heck-in.*:\s*.*(?:{$patterns['time']})?\s+(?<ArrTime>{$patterns['time']}|:)\s+";
                    $re2 .= '(?<ArrCode>[A-Z]{3})\s+(?<Cabin>.+)';
                    $re2 .= '/isu';

                    $outbound = $this->contains($this->t('Outbound flight'));
                    $return = $this->contains($this->t("Return flight"));

                    if (preg_match($re2, $dataFlight, $m)) {
                        $s = $f->addSegment();
                        $node = $this->http->FindSingleNode("./preceding::tr[" . $outbound . " or " . $return . "][1]/following-sibling::tr", $root, true, "#.+[\.\s]+(\d+\.\d+\.\d+)#");

                        if (empty($node)) {
                            $node = $this->http->FindSingleNode("preceding::tr[descendant::img[contains(@src, 'pdc/ico-08') or contains(@src, 'pdc/ico-10')]][1]", $root, true, "#.+[\.\s]+(\d+\.\d+\.\d+)#");
                        }
                        $s->departure()->date(strtotime($node . ' ' . $m['DepTime']));

                        if ($m['ArrTime'] === ':') {
                            $s->arrival()->noDate();
                        } else {
                            $s->arrival()->date(strtotime($node . ' ' . $m['ArrTime']));
                        }
                        $s->departure()->code($m['DepCode']);
                        $s->arrival()->code($m['ArrCode']);
                        $s->airline()->name($m['AirlineName']);
                        $s->airline()->number($m['FlightNumber']);
                        $s->extra()->cabin($m['Cabin']);

                        if (isset($m['DepartureTerminal'])) {
                            $s->departure()->terminal($m['DepartureTerminal'], true, true);
                        }

                        if ((isset($m['Operator'])) && (strlen(trim($m['Operator'])) > 0)) {
                            $s->airline()->operator($this->normalizeOperatorBy($m['Operator']), true, true);
                        } else {
                            $s->airline()->operator($this->normalizeOperatorBy(preg_replace('/(?:On behalf|per|von|en nombre de|au nom de).+/i', '', $m['Operator'])), true, true);
                        }
                    }

                    $re = '/';
                    $re .= "(?<DateFly>\d+[\/\s.]+[\d\w]+[\/\s.]+\d+)\s+(?:\w*\s+)?(?<DepTime>{$patterns['time']})\s*(?<addDepDay>[+]\s*\d+)?\s+";
                    $re .= "(?<DepCode>[A-Z]{3})\s+(?<ArrTime>{$patterns['time']}|:)?\s*(?<addArrDay>[+]\s*\d+)?\s+(?<ArrCode>[A-Z]{3})\s+";
                    $re .= '.*\s*';
                    $re .= '(?<AirlineName>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s+(?<FlightNumber>\d+)';
                    $re .= '/u';

                    if (preg_match($re, $dataFlight, $m)) {
                        $s = $f->addSegment();
                        $s->departure()->date($this->normalizeDate($m['DateFly'] . ' ' . str_replace('.', ':', $m['DepTime'])));

                        if (!empty($m['addDepDay']) && !empty($s->getDepDate())) {
                            $s->departure()->date(strtotime($m['addDepDay'] . ' day', $s->getDepDate()));
                        }

                        if ($m['ArrTime'] === ':') {
                            $s->arrival()->noDate();
                        } else {
                            $s->arrival()->date($this->normalizeDate($m['DateFly'] . ' ' . str_replace('.', ':', $m['ArrTime'])));
                        }

                        if (!empty($m['addArrDay']) && !empty($s->getArrDate())) {
                            $s->arrival()->date(strtotime($m['addArrDay'] . ' day', $s->getArrDate()));
                        }

                        $s->departure()->code($m['DepCode']);
                        $s->arrival()->code($m['ArrCode']);
                        $s->airline()->name($m['AirlineName']);
                        $s->airline()->number($m['FlightNumber']);
                    }
                }
            }
        }
    }

    private function normalizeOperatorBy(string $operator): string
    {
        return trim(mb_strlen($operator) > 50 ? $this->http->FindPreg('/^(.+?)\s+On behalf of/', false, $operator) : $operator);
    }

    private function normalizeDate(string $s)
    {
        $this->logger->debug($s);
        $in = [
            '/(\d{1,2})\/(\d{2})\/(\d{4}) (\d+:\d+)/',
            '/(\d{4})\/(\d{2})\/(\d{1,2}) (\d+:\d+)/',
        ];
        $out = [
            '$1.$2.$3, $4',
            '$3.$2.$1, $4',
        ];

        return strtotime(preg_replace($in, $out, $s));
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
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

    private function assignLang($body): bool
    {
        foreach ($this->reBody as $lang => $reBody) {
            if (
                (stripos($body, $reBody[0]) !== false || stripos($body, $reBody[1]) !== false)
                && stripos($body, $reBody[2]) !== false
            ) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
