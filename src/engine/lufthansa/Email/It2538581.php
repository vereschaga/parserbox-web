<?php

namespace AwardWallet\Engine\lufthansa\Email;

class It2538581 extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "lufthansa/it-2214642.eml, lufthansa/it-2538581.eml, lufthansa/it-2538582.eml, lufthansa/it-4640322.eml, lufthansa/it-5079614.eml, lufthansa/it-5156780.eml, lufthansa/it-5914756.eml, lufthansa/it-5929487.eml, lufthansa/it-5947429.eml, lufthansa/it-5956391.eml";
    public static $reBody = [
        'de' => 'Buchungscode',
        'ru' => 'код бронирования',
        'es' => ['Información sobre viajes Reserva de premios', 'Lufthansa Líneas Aéreas Alemanas no se hace responsable de los datos'],
        'hu' => ['Utazási információk', 'kérjük vegye fel a kapcsolatot a Lufthansával'],
        'fr' => ['Informations sur votre voyage'],
        'it' => ['Informazioni sugli upgrade'],
        'pt' => ['por favor contacte a sua equipa local da Lufthansa'],
        'pl' => ['Po wystawieniu biletów, na rejsach Lufthansy'],
    ];
    public $lang = '';
    public $dict = [
        'de' => [
            'Date'           => 'Datum',
            'From'           => 'Von',
            'Record Locator' => 'Buchungscode:',
            'Passenger'      => 'Reisedaten für',
            'Info'           => 'NOT TRANSLATED', //need to change
        ],
        'ru' => [
            'Date'           => 'дата',
            'From'           => 'из',
            'Record Locator' => 'код бронирования:',
            'Passenger'      => 'заказ для',
            'Info'           => 'Info',
        ],
        'es' => [
            'Date'           => 'Fecha',
            'From'           => 'De',
            'Record Locator' => 'Código de reserva:',
            'Passenger'      => 'Fechas del viaje para',
        ],
        'hu' => [
            'Date'           => 'Dátum',
            'From'           => 'Honnan',
            'Record Locator' => 'Foglalási kód:',
            'Passenger'      => 'Utas neve',
        ],
        'fr' => [
            'Date'           => 'Date',
            'From'           => 'de',
            'Record Locator' => 'Code de réservation :',
            'Passenger'      => 'Dates de voyage pour :',
        ],
        'it' => [
            'Date'           => 'Data',
            'From'           => 'Da',
            'Record Locator' => 'Codice di prenotazione:',
            'Passenger'      => 'Date di viaggio per:',
        ],
        'pt' => [
            'Date'           => 'Data',
            'From'           => 'De',
            'Record Locator' => 'Código da reserva:',
            'Passenger'      => 'Datas de viagem:',
        ],
        'pl' => [
            'Date'           => 'Data',
            'From'           => 'od',
            'Record Locator' => 'Kod rezerwacji:',
            'Passenger'      => 'Dane Pasażera:',
        ],
    ];

    private $year;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('Date'));

        $year = 1970;
        // 16. Januar 2018
        // 16 March 2016
        // 01-Julio-2015
        // if(preg_match('/\d+\.?[\s-]+[[:alpha:]]+[\s-]+(\d{4})/u', $parser->getSubject(), $matches) ||

        // 2014. október 06
        // preg_match('/(\d{4})\.?\s+[[:alpha:]]+\s+\d+/u', $parser->getSubject(), $matches)) {

        // $year = $matches[1];
        // }

        $this->detectBodyAndAcceptLang();
        // $this->year = $year;
        $its = $this->parseEmail($year);

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBodyAndAcceptLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['from']) && stripos($headers['from'], 'lufthansa.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return !empty($from) && stripos($from, 'lufthansa.com') !== false;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$reBody);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$reBody);
    }

    private function parseEmail($year)
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), '" . $this->t('Record Locator') . "')]/ancestor::td[1]/descendant::a/text()");
        $it['Passengers'] = $this->http->FindNodes("//td[contains(., '" . $this->t('Passenger') . "') and ancestor::tr[1][count(td)=2]]/following-sibling::td[1]");
        $xpath = "//tr[contains(., '" . $this->t('Date') . "') and contains(., '" . $this->t('From') . "')]/following-sibling::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $xpath = "//tr[contains(., '" . $this->t('Date') . "') and contains(., '" . $this->t('From') . "')]/ancestor::table[1]/following-sibling::table[contains(., '" . $this->t('Info') . "')]/descendant::tr[normalize-space(.)!='' and count(td)>6]";
            $roots = $this->http->XPath->query($xpath);
        }

        if ($roots->length === 0) {
            $this->logger->info('Segments not found: ' . $xpath);

            return [];
        }

        foreach ($roots as $root) {
            $seg = [];
            $flight = $this->http->FindSingleNode('td[string-length()>10][1]', $root);

            if (preg_match('#(\D+)\s+(\d+)#', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $d = explode('-', $this->replaceWhitespace($this->http->FindSingleNode('td[string-length(self::td)>6][2]', $root)));
            $date = $this->normalizeDate(trim($d[0]));

            if (count($d) === 2) {
                $dateArr = $this->normalizeDate(trim($d[1]));
            } else {
                $dateArr = $date;
            }

            $seg['DepName'] = $this->http->FindSingleNode('td[string-length(self::td)>6][3]/descendant::text()[normalize-space(.)][1]', $root);
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;

            if (preg_match('/^(.+?)\s*TERMINAL:? (\w+)/', $seg['DepName'], $matches)) {
                $seg['DepName'] = $matches[1];
                $seg['DepartureTerminal'] = $matches[2];
            }

            $seg['ArrName'] = $this->http->FindSingleNode('td[string-length(self::td)>6][4]/descendant::text()[normalize-space(.)][1]', $root);
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            if (preg_match('/^(.+?)\s*TERMINAL:? (\w+)/', $seg['ArrName'], $matches)) {
                $seg['ArrName'] = $matches[1];
                $seg['ArrivalTerminal'] = $matches[2];
            }

            $depTime = $this->normalizeTime($this->replaceWhitespace($this->http->FindSingleNode('td[string-length(self::td)>6][5]', $root)));
            $arrTime = $this->normalizeTime($this->replaceWhitespace($this->http->FindSingleNode('td[string-length(self::td)>6][6]', $root)));
            // if( count($date) === 1 && !empty($depTime) && !empty($arrTime) ){
            $seg['DepDate'] = strtotime($date . ', ' . $depTime);
            $seg['ArrDate'] = strtotime($dateArr . ', ' . $arrTime);

            if ($seg['ArrDate'] < $seg['DepDate']) {
                $seg['ArrDate'] = strtotime("+1 day", $seg['ArrDate']);
            }
            // }
            // if( count($date) === 2 && !empty($depTime) && !empty($arrTime) ){
            // $seg['DepDate'] = strtotime($date['DepDate']. ' ' .$depTime);
            // $seg['ArrDate'] = strtotime($date['ArrDate']. ' ' .$arrTime);
            // }

            if (isset($seg['DepDate']) && isset($seg['ArrDate']) && $seg['DepDate'] > $seg['ArrDate']) {
                $seg['ArrDate'] = strtotime('+1 day', $seg['ArrDate']);
            }

            $cabin = $this->http->FindSingleNode('td[string-length()>10][7]', $root);

            if (preg_match('#(?<Cabin>\w+ \(\S+\))(?<Status>(?:confirmado|bestätigt|подтвержден))#i', $cabin, $mathec)) {
                $seg['Cabin'] = $mathec['Cabin'];
                $it['Status'] = $mathec['Status'];
            }
            $seats = $this->http->FindSingleNode('td[string-length()>10][7]/descendant::text()[3]', $root);

            if (preg_match('#\D+:\s*(\w+)#', $seats, $quant)) {
                $seg['Seats'] = $quant[1];
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeTime($str)
    {
        if (preg_match('#(\d{2}:\d{2}).*#', $str, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * example: 16 марта
     * 			16 марта-17 марта
     * 			október 0​6​.
     *
     * @param $str
     * @param null $year
     *
     * @return array
     */
    // private function normalizeDate($str, $year = null){
    // echo $str."\n";
    // $in = [
    // '#(?<Day>\d+)\.*\s+(?<Month>\D+)-(?<NextDay>\d+)\.*\s+(?<Month2>\D+)#',
    // '#^(?<Day>\d+)\.*\s+(?<Month>[^-]+)$#',
    // '#^(?<Month>\D+)\s+(?<Day>\d+)\.*$#',
    // '#^(?<Day>\d+)\.(?<Month>\d+)\.$#',
    // '#^(?<Day>\d+)\.(?<Month>\d+)\.-(?<NextDay>\d+)\.(?<Month2>\d+)\.$#',
    // ];
    // $count = count($in);
    // $date = [];
    // for($i = 0; $i < $count; $i++){
    // if( preg_match($in[$i], $str, $m) && !empty($year)){
    // $date = [
    // 'Date' => $m['Day']. ' ' .(preg_match("#[^\d\s]+#", $m['Month']) ? $this->monthNameToEnglish($m['Month']) : $m['Month'] ). ' ' .$year
    // ];
    // if( isset($m['NextDay']) ){
    // $date = [
    // 'DepDate' => $m['Day']. ' ' .(preg_match("#[^\d\s]+#", $m['Month']) ? $this->monthNameToEnglish($m['Month']) : $m['Month'] ). ' ' .$year,
    // 'ArrDate' => $m['NextDay']. ' ' .(preg_match("#[^\d\s]+#", $m['Month2']) ? $this->monthNameToEnglish($m['Month2']) : $m['Month2'] ). ' ' .$year
    // ];
    // }
    // }
    // }
    // print_r($date);
    // return $date;
    // }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            '#^(\d+)\.*\s+(\D+)-(\d+)\.*\s+(\D+)$#',
            '#^(\d+)\.*\s+([^-]+)$#',
            '#^(\D+)\s+(\d+)\.*$#',
            '#^(\d+)\.(\d+)\.$#',
            '#^(\d+)\.(\d+)\.-(\d+)\.(\d+)\.$#',
        ];
        $out = [
            "$1 $2 {$year}",
            "$1 $2 {$year}",
            "$2 $1 {$year}",
            "$1.$2.{$year}",
            "$1.$2.{$year}",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function replaceWhitespace($str)
    {
        return preg_replace('#\xe2\x80\x8b#', '', $str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset($this->dict[$this->lang][$s])) {
            return $s;
        }

        return $this->dict[$this->lang][$s];
    }

    private function detectBodyAndAcceptLang()
    {
        $body = $this->http->Response['body'];

        foreach (self::$reBody as $lang => $detect) {
            if (is_array($detect)) {
                foreach ($detect as $dt) {
                    if (stripos($body, $dt) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }

            if (is_string($detect) && stripos($body, $detect) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
