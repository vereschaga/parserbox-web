<?php
/**
 * Created by PhpStorm.
 * User: Roman.
 */

namespace AwardWallet\Engine\lastminute\Email;

class AirTravel extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "lastminute/it-4576773.eml, lastminute/it-5014419.eml, lastminute/it-5082498.eml";
    public $lang = '';
    public static $reBody = [
        'es' => 'referencia del vuelo',
        'en' => 'Airline reference (locator)',
        'pt' => 'estar em nome de lastminute.com',
    ];
    public static $dict = [
        'en' => [],
        'es' => [
            'Airline reference'  => 'referencia del vuelo',
            'Operating Airline:' => 'Línea Aérea:', // ":" - it need for xpath
            'Flight Number'      => 'de vuelo',
            'Departure Terminal' => 'Terminal de salida',
            'Class'              => 'Clase',
            'Departing'          => 'Saliendo',
            'Arriving'           => 'Llegando',
            'Duration'           => 'Duración',
        ],
        'pt' => [
            'Airline reference'  => 'de referência do voo',
            'Operating Airline:' => 'Companhia aérea:', // ":" - it need for xpath
            'Flight Number'      => 'de voo',
            'Departure Terminal' => 'Terminal de saída',
            'Arrival Terminal'   => 'Terminal de chegada',
            'Class'              => 'Clase',
            'Departing'          => 'Partida',
            'Arriving'           => 'Chegada',
            'Duration'           => 'Duração',
        ],
    ];
    private $currency = [
        '€' => 'EUR',
        '£' => 'GBP',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach (self::$reBody as $lang => $value) {
            if (stripos($this->http->Response['body'], $value) !== false) {
                $this->lang = $lang;
            }
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'lastminute.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'lastminute.com')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'lastminute.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$reBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$reBody);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//b[contains(text(), '" . $this->t('Airline reference') . "')]", null, true, '#.*:\s+(\w+)#');
        $total = $this->http->FindSingleNode("//td[contains(text(), 'Total cargado a tu cuenta')]/strong");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//td[b = 'Total']/ancestor::tr[2]/following-sibling::tr[2]/descendant::td[normalize-space()!=''][7]");
        }

        if (preg_match('#(\D+)\s*([\d,.]+)#', $total, $count)) {
            $it['TotalCharge'] = (strstr($count[2], ',')) ? str_replace(',', '.', $count[2]) : $count[2];
            $it['Currency'] = $this->getCurrency($count[1]);
        }
        $xpath = "//tr[contains(td/b, '" . $this->t('Operating Airline:') . "')]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found: ' . $xpath);

            return false;
        }
        //		Barcelona Apt (BCN) a Amsterdam (AMS)[es lang] | Doha (DOH) To Dubai (DXB)[en lang]
        $regExpForNameCodeAir = '#(?<DepName>.+)\s+\((?<DepCode>\D{3})\)\s+(?:a|to)\s+(?<ArrName>.+)\s+\((?<ArrCode>\D{3})\)#i';

        foreach ($roots as $root) {
            $seg = [];
            $infoAir = $this->http->FindSingleNode('ancestor::tr[1]/preceding-sibling::tr[2]', $root, true, '#(.+\s+\(\D{3}\)\s+(?:a|to)\s+.+\s+\(\D{3}\))#i');

            if (preg_match($regExpForNameCodeAir, $infoAir, $m)) {
                $seg['DepName'] = $m['DepName'];
                $seg['ArrName'] = $m['ArrName'];
                $seg['DepCode'] = $m['DepCode'];
                $seg['ArrCode'] = $m['ArrCode'];
            }
            $flightNumAirName = $this->http->FindSingleNode("following-sibling::tr[contains(., '" . $this->t('Flight Number') . "')]/td[2]", $root);

            if (preg_match('#([A-Z]{2})\s*(\d+)#', $flightNumAirName, $math) || preg_match('#([A-Z]{1}\d)\s+(\d+)#', $flightNumAirName, $math)) {
                $seg['AirlineName'] = $math[1];
                $seg['FlightNumber'] = $math[2];
            }
            $seg['DepartureTerminal'] = $this->http->FindSingleNode("following-sibling::tr[contains(., '" . $this->t('Departure Terminal') . "')]/td[2]", $root, true, '#(\b[\dA-Z]{1,3}\b)#');
            $seg['ArrivalTerminal'] = $this->http->FindSingleNode("following-sibling::tr[contains(., '" . $this->t('Arrival Terminal') . "')]/td[2]", $root);
            $seg['Cabin'] = $this->http->FindSingleNode("following-sibling::tr[contains(., '" . $this->t('Class') . "')]/td[2]", $root);
            $depDate = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(., '" . $this->t('Departing') . "')]/td[2]", $root));

            if (!empty($depDate)) {
                $seg['DepDate'] = $depDate;
            }
            $arrDate = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(., '" . $this->t('Arriving') . "')]/td[2]", $root));

            if (!empty($arrDate)) {
                $seg['ArrDate'] = $arrDate;
            }
            $seg['Duration'] = $this->http->FindSingleNode("following-sibling::tr[contains(., '" . $this->t('Duration') . "')]/td[2]", $root);

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getCurrency($str)
    {
        if (!empty($str)) {
            foreach ($this->currency as $symbol => $cur) {
                if ($str === $symbol) {
                    return $cur;
                }
            }
        }

        return null;
    }

    /**
     * example: 20:45 mié 1 ene 2014.
     *
     * @param $str
     *
     * @return int
     */
    private function normalizeDate($str)
    {
        $regExpForDate = [
            '#(?<Time>\d{2}:\d{2})\s+[\S]*\s+(?<Day>\d+)\s+(?<Month>\w+)\s+(?<Year>\d{2,4}).*#',
        ];
        $out = [
            '$2 $3 $4 $1',
        ];

        return strtotime($this->dateStringToEnglish(preg_replace($regExpForDate, $out, $str)));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
