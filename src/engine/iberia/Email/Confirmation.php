<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "iberia/it-10184065.eml, iberia/it-1680412.eml, iberia/it-1705010-es.eml, iberia/it-2-es.eml, iberia/it-211801153.eml, iberia/it-2143969-es.eml, iberia/it-2190694-de.eml, iberia/it-2514344-fr.eml, iberia/it-2771934-pt.eml, iberia/it-2913643-es.eml, iberia/it-3904714.eml, iberia/it-3904715.eml, iberia/it-4011918-es.eml, iberia/it-4052798-it.eml, iberia/it-4090118-it.eml, iberia/it-4712460-de.eml, iberia/it-4712462-de.eml, iberia/it-4773250.eml, iberia/it-5350737-it.eml, iberia/it-5888018-ru.eml, iberia/it-5945197-ca.eml, iberia/it-8914939-de.eml, iberia/it-9933330.eml";

    public $reBody = [
        'de' => [
            'Kaufvorgang läuft',
            'Der Reservierung wurde erfolgreich durchgeführt',
            'Buchungsantrag läuft',
            "Bestätigungscode",
        ],
        'it' => ['Acquisto confermato', 'Codice di conferma'],
        'en' => ['The booking was made correctly', 'Confirmation code'],
        'es' => [
            'Información de pasajeros',
            'Código de confirmación',
            'Salida', ],
        'pt' => ['Informação de passageiros', 'Código de confirmação'],
        'ru' => ['Код подтверждения', 'Информация о пассажирах'],
        'ca' => ['Codi de confirmació', 'Informació de passatgers'],
        'fr' => ['Code de confirmation', 'Information sur les passagers'],
    ];
    public $reSubject = [
        'de' => ['Buchungsbestätigung'],
        'it' => ['Conferma di prenotazione'],
        'en' => ['Booking confirmation'],
        'es' => ['Confirmación de reserva'],
        'pt' => ['Confirmação de reserva'],
        'ru' => ['Подтверждение бронирования'],
        'ca' => ['Confirmació de reserva'],
        'fr' => ['Confirmation de réservation'],
    ];
    public $lang = '';

    public static $dictionary = [
        'de' => [
            'Record locator' => 'Bestätigungscode',
            'Passenger Info' => 'Passagierinformation',
            'Flight'         => 'urchgeführt von', //D|d
            'Your Trip'      => 'Ihre Reise',
            'Departure'      => 'Abflug',
            'Tarif'          => 'Tarifbedingungen',
            'Passenger'      => 'Passagier',
            'Total'          => 'Gesamtpreis',
            'Cabin'          => 'Kabine',
            'Seats'          => 'Sitzplatz',
            'Meal'           => 'Menü',
        ],
        'it' => [
            'Record locator' => 'Codice di conferma',
            'Passenger Info' => 'Informazione dei passeggeri',
            'Flight'         => ['olo operato da', 'operado por'], //V|v //operado por- 4052798
            'Your Trip'      => 'Il Suo viaggio',
            'Departure'      => 'Partenza',
            'Tarif'          => 'Tariffa',
            'Passenger'      => 'Passeggeri',
            'Total'          => 'prezzo totale',
            'Cabin'          => 'Cabina',
            'Seats'          => 'Posti',
            //			'Meal' => '',
        ],
        'en' => [
            'Record locator' => 'Confirmation code',
            'Passenger Info' => ['Passengers information', 'Passenger information'],
            'Flight'         => ['Flight operated by', 'Operated by'], //F|f
            'Your Trip'      => 'Your trip',
            'Departure'      => 'Departure',
            'Tarif'          => 'Fare',
            'Passenger'      => 'Passengers',
            'Total'          => 'Total Price',
            'Cabin'          => 'Cabin',
            'Seats'          => 'Seat',
            //			'Meal' => '',
        ],
        'es' => [
            'Record locator' => ['Código de confirmación', 'Codigo de confirmacion'],
            'Passenger Info' => ['Información de pasajeros', 'Informacion de pasajeros'],
            'Flight'         => ['uelo operado por', 'Operado por'], //V|v
            'Your Trip'      => 'Tu viaje',
            'Departure'      => 'Salida',
            'Tarif'          => 'Tarifa',
            'Passenger'      => 'Pasajeros',
            'Total'          => 'Precio Total',
            'Cabin'          => 'Cabina',
            'Seats'          => 'Asiento',
            //			'Meal' => '',
        ],
        'pt' => [
            'Record locator' => 'Código de confirmação',
            'Passenger Info' => 'Informação de passageiros',
            'Flight'         => 'oo operado por', //V|v
            'Your Trip'      => 'A sua viagem',
            'Departure'      => 'Saída',
            'Tarif'          => 'Tarifa',
            'Passenger'      => 'Passageiros',
            'Total'          => 'Preço Total',
            'Cabin'          => 'Cabina',
            'Seats'          => 'Lugar',
            //			'Meal' => '',
        ],
        'ru' => [
            'Record locator' => 'Код подтверждения',
            'Passenger Info' => 'Информация о пассажирах',
            'Flight'         => 'Выполняется',
            'Your Trip'      => 'Ваше путешествие',
            'Departure'      => 'Вылет',
            'Tarif'          => 'Тариф',
            'Passenger'      => 'Пассажиры',
            'Total'          => 'Общая стоимость',
            'Cabin'          => 'Салон',
            'Seats'          => 'Место',
            //			'Meal' => '',
        ],
        'ca' => [
            'Record locator' => 'Codi de confirmació',
            'Passenger Info' => 'Informació de passatgers',
            'Flight'         => 'Vol operat per',
            'Your Trip'      => 'El teu vitage',
            'Departure'      => 'Sortida',
            'Tarif'          => 'Tarifa',
            'Passenger'      => 'Passatgers',
            'Total'          => 'Preu Total',
            'Cabin'          => 'Cabina',
            'Seats'          => 'Seient',
            //			'Meal' => '',
        ],
        'fr' => [
            'Record locator' => 'Code de confirmation',
            'Passenger Info' => 'Information sur les passagers',
            'Flight'         => 'Vol opéré par',
            'Your Trip'      => 'Votre voyage',
            'Departure'      => 'Départ',
            'Tarif'          => 'Tarif',
            'Passenger'      => 'Passagers',
            'Total'          => 'Prix total',
            'Cabin'          => 'Cabine',
            'Seats'          => 'Siège',
            //			'Meal' => '',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->assignLang($body);

        $its = $this->parseEmail();

        return [
            'emailType'  => 'Confirmation' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject) && isset($headers['subject'])) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers['subject'], $reSubject[0]) !== false) {// || stripos($headers['subject'], $reSubject[1]) !== false
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@iberia.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if ($this->http->XPath->query("//a[contains(@href,'iberia.com')]/@href")->length > 0) {
            return $this->assignLang($body);
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(): array
    {
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Record locator'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, '/^[:\s]*([A-Z\d]{5,10})$/');

        $rootPax = $this->http->XPath->query("//text()[{$this->contains($this->tPlusEn('Passenger Info'))}]/ancestor::tr[1]/following-sibling::tr[1]//table[thead]/thead/following-sibling::*/tr");
        if ($rootPax->length == 0) {
            $rootPax = $this->http->XPath->query("//text()[{$this->contains($this->tPlusEn('Passenger Info'))}]/ancestor::tr[1]/following-sibling::tr[1]//table[thead]/thead/following-sibling::tr");
        }
        foreach ($rootPax as $root) {

            if (!empty($pax = $this->http->FindSingleNode(".//td[1]/descendant::text()[normalize-space()!=''][1]", $root, true, "/(.+?)(?:\s*\(.*\))?\s*$/"))) {
                $it['Passengers'][] = $pax;
            }

            if (!empty($acc = $this->http->FindSingleNode(".//td[2]", $root, false, "#.*\d.*#"))) {
                $it['AccountNumbers'][] = $acc;
            }

            if (!empty($tn = $this->http->FindSingleNode(".//td[3]", $root, false, "#.*\d.*#"))) {
                $it['TicketNumbers'][] = $tn;
            }
        }

        $it['Passengers'] = array_unique($it['Passengers'] ?? []);

        if (isset($it['AccountNumbers']) && count($acc = array_unique($it['AccountNumbers'])) > 0) {
            $it['AccountNumbers'] = $acc;
        }

        if (isset($it['TicketNumbers']) && count($tn = array_unique($it['TicketNumbers'])) > 0) {
            $it['TicketNumbers'] = $tn;
        }

        $tarifPos = count($this->http->FindNodes("//text()[{$this->contains($this->tPlusEn('Tarif'))}]/ancestor::*[local-name()='th' or local-name()='td'][1]/preceding-sibling::*[local-name()='th' or local-name()='td']"));
        $passPos = count($this->http->FindNodes("//text()[{$this->contains($this->tPlusEn('Passenger'))} and {$this->contains($this->tPlusEn('Tarif'), "ancestor::table[1]")}]/ancestor::*[local-name()='th' or local-name()='td'][1]/preceding-sibling::*[local-name()='th' or local-name()='td']"));
        $baseFare = $this->http->XPath->query("//text()[{$this->contains($this->tPlusEn('Tarif'))}]/ancestor::table[1]/tr");

        if ($baseFare->length == 0) {
            $baseFare = $this->http->XPath->query("//text()[{$this->contains($this->tPlusEn('Tarif'))}]/ancestor::table[1]/tbody/tr");
        }

        if ($baseFare->length > 0 && !empty($tarifPos) && empty($passPos)
            && $this->http->XPath->query("//text()[{$this->eq($this->tPlusEn('Tarif'))}]/following::*[{$this->contains($this->tPlusEn('Passenger'))}]")->length === 0
        ) {
            $all = count($this->http->FindNodes("//text()[{$this->contains($this->tPlusEn('Tarif'))} and {$this->contains($this->tPlusEn('Tarif'), "ancestor::table[1]")}]/ancestor::*[local-name()='th' or local-name()='td'][1]/ancestor::*[1]/*"));
            if (!empty($this->http->FindSingleNode("./td[" . ($all - 1) . "]", $baseFare->item(0), true, "#^\s*x\s*(\d+)\s*$#"))) {
                $passPos = $all - 2;
            }
        }

        if ($baseFare->length > 0 && !empty($tarifPos) && !empty($passPos)) {
            $costAmounts = $taxAmounts = [];

            foreach ($baseFare as $bfRow) {
                $passVal = $this->http->FindSingleNode("td[" . ($passPos + 1) . "]", $bfRow);

                if ($passVal === null || $passVal === '') {
                    $pass = 1;
                } elseif (preg_match('/^[*x\s]*(\d{1,3})$/i', $passVal, $m)) {
                    $pass = $m[1];
                } else {
                    $passVal2 = $this->http->FindSingleNode("td[{$passPos}]", $bfRow);

                    if ($passVal2 === null || $passVal2 === '') {
                        $pass = 1;
                    } elseif (preg_match('/^[*x\s]*(\d{1,3})$/i', $passVal2, $m)) {
                        $pass = $m[1];
                    } else {
                        $this->logger->debug('Wrong passengers count in price!');
                        $costAmounts = $taxAmounts = [];
    
                        break;
                    }
                }

                $amount = $this->http->FindSingleNode("td[" . ($tarifPos + 1) . "]", $bfRow, true, '/^[+\s]*(.*\d.*)$/');

                if ($amount !== null) {
                    $costAmounts[] = PriceHelper::parse($amount) * $pass;
                }

                for ($i = ($tarifPos + 2); $i < $passPos + 1; $i++) {
                    $amount = $this->http->FindSingleNode("td[" . $i . "]", $bfRow, true, '/^[+\s]*(.*\d.*)$/');
                    
                    if ($amount !== null) {
                        $taxAmounts[] = PriceHelper::parse($amount) * $pass;
                    }
                }
            }

            if (count($costAmounts) > 0) {
                $it['BaseFare'] = array_sum($costAmounts);
            }

            if (count($taxAmounts) > 0) {
                $it['Tax'] = array_sum($taxAmounts);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->tPlusEn('Total'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/')
            ?? $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->tPlusEn('Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match("/^\s*(?:(?<awards>.*Avios)\s*\+\s*)?(?<amount>\d[\d,. ]*?)[ ]*(?<currency>\D+)$/i", $totalPrice, $matches)
            || preg_match("/^\s*(?:(?<awards>.*Avios)\s*\+\s*)?(?<currency>\D+?)[ ]*(?<amount>\d[\d,. ]*)$/i", $totalPrice, $matches)
        ) {
            // 24.813,60 BRL
            $currency = $this->currency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $it['TotalCharge'] = PriceHelper::parse($matches['amount'], $currencyCode);
            $it['Currency'] = $currency;

            if (!empty($matches['awards'])) {
                $it['SpentAwards'] = $matches['awards'];
            }
        }

        $it['TripSegments'] = [];

        $xpath = "//text()[{$this->contains($this->tPlusEn("Your Trip"))}]/ancestor::tr[1]/following-sibling::tr[1]//td[{$this->eq($this->tPlusEn("Departure"))}]/ancestor::tr[1]/following-sibling::tr[1]";
        $rows = $this->http->XPath->query($xpath);

        $this->logger->info('[XPath] Segments:');
        $this->logger->debug($xpath);

        foreach ($rows as $row) {
            $seg = [];
            //        FlightNumber, AirlineName
            $flightNumAirName = implode("\n", $this->http->FindNodes("preceding::tr[1]/td[2]/descendant::text()[normalize-space()]", $row));

            if (($flightNumAirName !== null) && preg_match("/^(?<airName>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<fNum>\d+)(?:\n|$)/", $flightNumAirName, $m)) {
                $seg['AirlineName'] = $m['airName'];
                $seg['FlightNumber'] = $m['fNum'];
            } elseif (($flightNumAirName !== null) && preg_match("#IBOPEN#", $flightNumAirName, $m)) {
                $seg['AirlineName'] = 'IB';
                $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            }

            $node = $this->http->FindSingleNode("td[1]", $row, true, "/{$this->opt($this->tPlusEn('Flight'))}\s+(.+)/i");

            if ($node !== null) {
                $seg['Operator'] = trim($node, ".");
            }

            $node = implode("\n", $this->http->FindNodes("td[2]//text()", $row));

            if (($node !== null) && preg_match("#(?<DepDate>\d+\:\d+.+\d{1,2}.+\d{4}[^\n]*)\s+(?<DepName>.+)\s+\((?<DepCode>[A-Z]{3})\)\s*(?:Terminal\s+(?<Terminal>.+))?#s", $node, $math)) {
                $seg['DepDate'] = strtotime($this->normalizeDate($math['DepDate']));

                if ($math['DepName'] !== 'All airports') {
                    $seg['DepName'] = $math['DepName'];
                }
                $seg['DepCode'] = $math['DepCode'];

                if (isset($math['Terminal'])) {
                    $seg['DepartureTerminal'] = trim($math['Terminal']);
                }
            } elseif (($node !== null) && $seg['FlightNumber'] == FLIGHT_NUMBER_UNKNOWN && preg_match("#(?<DepName>.+)\s+\((?<DepCode>[A-Z]{3})\)\s*(?:Terminal\s+(?<Terminal>.+))?#s", $node, $math)) {
                $seg['DepDate'] = MISSING_DATE;

                if ($math['DepName'] !== 'All airports') {
                    $seg['DepName'] = $math['DepName'];
                }
                $seg['DepCode'] = $math['DepCode'];

                if (isset($math['Terminal'])) {
                    $seg['DepartureTerminal'] = trim($math['Terminal']);
                }
            }

            $node = implode("\n", $this->http->FindNodes("td[3]//text()", $row));

            if (($node !== null) && preg_match("#(?<ArrDate>\d+\:\d+.+\d{1,2}.+\d{4}[^\n]*)\s+(?<ArrName>.+)\s+\((?<ArrCode>[A-Z]{3})\)\s*(?:Terminal\s+(?<Terminal>.+))?#s", $node, $math)) {
                $seg['ArrDate'] = strtotime($this->normalizeDate($math['ArrDate']));

                if ($math['ArrName'] !== 'All airports') {
                    $seg['ArrName'] = $math['ArrName'];
                }
                $seg['ArrCode'] = $math['ArrCode'];

                if (isset($math['Terminal'])) {
                    $seg['ArrivalTerminal'] = trim($math['Terminal']);
                }
            } elseif (($node !== null) && $seg['FlightNumber'] == FLIGHT_NUMBER_UNKNOWN && preg_match("#(?<ArrName>.+)\s+\((?<ArrCode>[A-Z]{3})\)\s*(?:Terminal\s+(?<Terminal>.+))?#s", $node, $math)) {
                $seg['ArrDate'] = MISSING_DATE;

                if ($math['ArrName'] !== 'All airports') {
                    $seg['ArrName'] = $math['ArrName'];
                }
                $seg['ArrCode'] = $math['ArrCode'];

                if (isset($math['Terminal'])) {
                    $seg['ArrivalTerminal'] = trim($math['Terminal']);
                }
            }

            $arr = [4, 5, 6];

            foreach ($arr as $i) {
                $node = $this->http->FindSingleNode("./td[" . ($i) . "]", $row);

                if (empty($node)) {
                    continue;
                }
                $colname = $this->http->FindSingleNode("./preceding::tr[1]/td[" . ($i + 1) . "]", $row);

                if (preg_match("/{$this->opt($this->tPlusEn('Cabin'))}/i", $colname)) {
                    $seg['Cabin'] = $node;
                }

                if (preg_match("/{$this->opt($this->tPlusEn('Seats'))}/i", $colname)) {
                    $seg['Seats'] = array_filter(array_map(function ($v) {
                        if (preg_match("#^\s*(\d{1,3}[A-Z])\s*$#", $v, $m)) {
                            return $m[1];
                        }

                        return "";
                    }, explode(",", $node)));
                }

                if (preg_match("/{$this->opt($this->tPlusEn('Meal'))}/i", $colname)) {
                    $seg['Meal'] = $node;
                }
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function tPlusEn(string $s): array
    {
        return array_unique(array_merge((array) $this->t($s), (array) $this->t($s, 'en')));
    }

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function normalizeDate(string $str): string
    {
        //		$this->http->Log('$str = '.print_r( $str,true));
        $in = [
            "#^(\d+:\d+).*\D(\d{1,2})[\.]?\s+(\w+)\s+(\d{4}).*$#us", //113:45h, mercoledì 25 giugno 2014
            "#^(\d+:\d+).*\D(\d{1,2})\s+de\s+(\w+)\s+de\s+(\d{4})$#us", //19:10h, sábado 16 de noviembre de 2013
            "#^(\d+:\d+).*\D(\d{1,2})\s+/\s+(\w+)\s+/\s+(\d{4})$#us", //9:20h, dilluns, 11 / agost / 2014
            "#^(\d+:\d+).*?(\w+)\s+(\d+),\s+(\d{4})$#us", //06:20h, Tuesday, November 7, 2017
        ];
        $out = [
            "$2 $3 $4 $1",
            "$2 $3 $4 $1",
            "$2 $3 $4 $1",
            "$3 $2 $4 $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function currency($str)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if (preg_match("#([A-Z]{3})#", $str, $m)) {
            return $m[1];
        }

        foreach ($sym as $f => $r) {
            if ($str == $f) {
                return $r;
            }
        }

        return null;
    }
}
