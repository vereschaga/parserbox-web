<?php

namespace AwardWallet\Engine\lastminute\Email;

class AirTicket extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "lastminute/it-4533137.eml, lastminute/it-4614779.eml, lastminute/it-4627865.eml";

    public static $reBody = [
        'en' => ['PASSENGER DETAILS', 'Thank you for booking with lastminute.com'],
        'de' => 'Ihre Flugdetails',
        'es' => 'Los detalles',
    ];
    public $lang = '';
    public $dict = [
        'en' => [
            'Itinerary'       => 'ITINERARY',
            'From'            => 'FROM',
            'Depart Terminal' => 'DEPARTURE TERMINAL',
            'To'              => 'TO',
            'Dept Date'       => 'DEPT DATE',
            'Arrv Date'       => 'ARRV DATE',
            'Class'           => 'CLASS',
            'Air'             => 'CARRIER',
            'Dept Time'       => 'DEPT TIME',
            'Arrv Time'       => 'ARRV TIME',
            'Arrv Terminal'   => 'ARRIVAL TERMINAL',
        ],
        'de' => [
            'Itinerary'       => 'Ihre Flugdetails',
            'From'            => 'VON',
            'Depart Terminal' => 'ABFLUGTERMINAL',
            'To'              => 'NACH',
            'Dept Date'       => 'ABFLUGSDATUM',
            'Arrv Date'       => 'ANKUNFTSDATUM',
            'Class'           => 'KLASSE',
            'Air'             => 'FLUGGESELLSCHAFT',
            'Dept Time'       => 'ABFLUGZEIT',
            'Arrv Time'       => 'ANKUNFTZEIT',
            'Arrv Terminal'   => 'ANKUNFTTERMINAL',
            'Locator'         => 'NOT TRANSLATED',
        ],
        'es' => [
            'Itinerary'       => 'Los detalles',
            'From'            => 'Desde',
            'Depart Terminal' => 'Aeropuerto de Salida',
            'To'              => 'Hasta',
            'Dept Date'       => 'Fecha de salida',
            'Arrv Date'       => 'Fecha de llegada',
            'Class'           => 'Clase de reserva',
            'Air'             => 'Compañia aérea',
            'Dept Time'       => 'Horario de salida',
            'Arrv Time'       => 'Horario de llegada',
            'Arrv Terminal'   => 'Aeropuerto de Llegada',
            'Locator'         => 'Localizador',
        ],
    ];

    private $subjects = [
        'de' => ['Ihr Ticket wurde ausgestellt'],
        'es' => ['Confirmación emisión billetes'],
        'en' => ['Your tickets have been issued'],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBodyAndAcceptLang();
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
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
        return isset($from) && stripos($from, '@lastminute.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//node()[contains(.,"lastminute.com") or contains(.,"lastminute.de")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBodyAndAcceptLang();
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
        $passengersInfo = $this->http->FindNodes("//tr[contains(., 'Ticket')]/following-sibling::tr[1]/descendant::td[position()=3 or position()=4 or position()=6]");

        if (!empty($passengersInfo)) {
            $it['RecordLocator'] = array_pop($passengersInfo);
            $it['Passengers'][] = implode(' ', $passengersInfo);
        } else {
            $it['RecordLocator'] = $this->http->FindSingleNode("//td[contains(.,'" . $this->t('Locator') . "')]/following-sibling::td[1]");
        }

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        if (empty($it['Passengers'])) {
            $it['Passengers'][] = $this->http->FindSingleNode("//p[contains(., 'informationen')]/preceding-sibling::p[1]/descendant::text()", null, true, '#\d*\s+(?:MRS|MISS|MR)\s+([\w\s]+)\s+\w+#');
        }

        $xpath = "//p[contains(., '" . $this->t('Itinerary') . "')]/following-sibling::table[contains(., '" . $this->t('From') . "')]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $xpath = "//*[contains(., 'Locator')]/following-sibling::table[contains(., 'FROM')]";
            $roots = $this->http->XPath->query($xpath);
        }

        if ($roots->length === 0) {
            $this->http->Log('Segments not found ' . $xpath, LOG_LEVEL_NORMAL);

            return null;
        }

        foreach ($roots as $root) {
            $seg = [];
            $seg['DepName'] = $this->getNode($this->t('From'), $root);
            $seg['DepartureTerminal'] = $this->getNode($this->t('Depart Terminal'), $root);
            $seg['ArrName'] = $this->getNode($this->t('To'), $root);
            $seg['ArrivalTerminal'] = $this->getNode($this->t('Arrv Terminal'), $root);
            $depDate = $this->normalizeDate($this->getNode($this->t('Dept Date'), $root));
            $arrDate = $this->normalizeDate($this->getNode($this->t('Arrv Date'), $root));
            $seg['Cabin'] = $this->getNode($this->t('Class'), $root);
            $seg['AirlineName'] = $this->getNode($this->t('Air'), $root);
            $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            $seg['DepDate'] = $this->getDate($this->getNode($this->t('Dept Time'), $root), $depDate);
            $seg['ArrDate'] = $this->getDate($this->getNode($this->t('Arrv Time'), $root), $arrDate);
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    /**
     * example: 16-abril-2014 miércoles
     *          20-03-2015.
     *
     * @param $str
     *
     * @return bool|mixed
     */
    private function normalizeDate($str)
    {
        $in = [
            '#(\d+)-(\D+)-(\d+)\s*.*#',
        ];
        $out = [
            '$1 $2 $3',
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str), false, true);
    }

    private function getDate($strTime, $date)
    {
        $dateSeg = null;

        if (preg_match('#(\d{2})(\d{2})#', $strTime, $m)) {
            $dateSeg = strtotime($date . ' ' . $m[1] . ':' . $m[2]);
        }

        return $dateSeg;
    }

    private function getNode($str, $root)
    {
        $res = null;
        $res = $this->http->FindSingleNode("descendant::td[contains(., '" . $str . "')]/following-sibling::td[1]", $root);

        if (strstr($res, ':') !== false) {
            $res = trim(str_replace(':', '', $res));
        }

        return $res;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset($this->dict[$this->lang][$s])) {
            return $s;
        }

        return $this->dict[$this->lang][$s];
    }

    private function detectBodyAndAcceptLang(): bool
    {
        $body = $this->http->Response['body'];

        foreach (self::$reBody as $lang => $reBody) {
            if (is_array($reBody)) {
                foreach ($reBody as $item) {
                    if (stripos($body, $item) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }

            if (is_string($reBody) && stripos($body, $reBody) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
