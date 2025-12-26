<?php
/**
 * Created by PhpStorm.
 * User: Roman.
 */

namespace AwardWallet\Engine\vueling\Email;

class AirTicket extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "vueling/it-4072328.eml, vueling/it-4117862.eml, vueling/it-4148540.eml, vueling/it-4249496.eml";
    public $reBody = [
        'pt' => 'Passageiros',
        'es' => 'Pasajeros',
        'en' => 'Passengers',
        'it' => 'Passeggeri',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
        'pt' => [
            'Confirmation number' => 'Número de confirmação',
            'Passengers'          => 'Passageiros',
            'Route'               => 'Rota',
            'Flight'              => 'Voo',
        ],
        'es' => [
            'Confirmation number' => 'Número de confirmación',
            'Passengers'          => 'Pasajeros',
            'Route'               => 'Ruta',
            'Flight'              => 'Vuelo',
        ],
        'it' => [
            'Confirmation number' => 'Numero di conferma',
            'Passengers'          => 'Passeggeri',
            'Route'               => 'Rotta',
            'Flight'              => 'Volo',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($body, $reBody) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//*[contains(text(), 'Vueling') or contains(text(), 'vueling')]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], "@vueling.com") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@vueling.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), '" . $this->t('Confirmation number') . "')]/following-sibling::*[normalize-space(.)!=''][1]");
        $it['Passengers'] = $this->http->FindNodes("(//*[contains(text(), '" . $this->t('Passengers') . "')]/following::tr[normalize-space(.)!=''][//tbody[1]]/td[1])[1]");
        $xpath = "//*[contains(text(), '" . $this->t('Route') . "')]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->http->Log("Segments not found $xpath", LOG_LEVEL_NORMAL);
        }
//        all data are in the same tr
        $depName = $this->http->FindNodes("//*[contains(text(), '" . $this->t('Route') . "')]/following::tr[1]/td[position() = 2 or position() = 6]");
        $departTime = $this->http->FindNodes("//*[contains(text(), '" . $this->t('Route') . "')]/following::tr[1]/td[position() = 3 or position() = 7]");
        $depTerm = $this->http->FindNodes("//*[contains(text(), '" . $this->t('Route') . "')]/following::tr[1]/td[position() = 4 or position() = 8]", null, "#([A-Z0-9]+)#");
        $arrName = $this->http->FindNodes("//*[contains(text(), '" . $this->t('Route') . "')]/following::tr[2]/td[position() = 2 or position() = 6]");
        $arrivTime = $this->http->FindNodes("//*[contains(text(), '" . $this->t('Route') . "')]/following::tr[2]/td[position() = 3 or position() = 7]");
        $arrTerm = $this->http->FindNodes("//*[contains(text(), '" . $this->t('Route') . "')]/following::tr[2]/td[position() = 4 or position() = 8]", null, "#([A-Z0-9]+)#");
        $flightAir = $this->http->FindNodes("//*[contains(text(), '" . $this->t('Route') . "')]/preceding::strong[starts-with(., '" . $this->t('Flight') . "')]/following-sibling::*[1]");
        $date = $this->http->FindNodes("//*[contains(text(), '" . $this->t('Route') . "')]/preceding::strong[starts-with(., '" . $this->t('Flight') . "')]/ancestor::tr[1]/following-sibling::tr[1]/td[position() = 2 or position() = 4]");

        foreach ($roots as $i => $root) {
            $seg = [];
            $flight = array_shift($flightAir);

            if (preg_match("#(\w{2})\s*(\d+)#", $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $dep = $this->processingCodes(array_shift($depName));

            if (count($dep) === 2) {
                $seg['DepName'] = $dep['Name'];
                $seg['DepCode'] = $dep['Code'];
            }

            if (count($departTime) > 0) {
                $depTime = array_shift($departTime);
                $seg['DepDate'] = strtotime($this->normalizeDate($date[$i] . ' ' . $depTime));
            }
            $seg['DepartureTerminal'] = array_shift($depTerm);
            $arr = $this->processingCodes(array_shift($arrName));

            if (count($arr) === 2) {
                $seg['ArrName'] = $arr['Name'];
                $seg['ArrCode'] = $arr['Code'];
            }

            if (count($arrivTime) > 0) {
                $arrTime = array_shift($arrivTime);
                $seg['ArrDate'] = strtotime($this->normalizeDate($date[$i] . ' ' . $arrTime));
            }
            $seg['ArrivalTerminal'] = array_shift($arrTerm);
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^.*\s+(\d{2})\s+(\w+)\s+(\d{4})\s+(\d+:\d+)\w*$#",
        ];
        $out = [
            "$2 $1 $3 $4",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
    }

    private function processingCodes($str)
    {
        if (preg_match("#([\w|\w\s]*)\s*\((\D{3})\)#", $str, $m)) {
            return [
                'Name' => (isset($m[1])) ? $m[1] : null,
                'Code' => (isset($m[2])) ? $m[2] : TRIP_CODE_UNKNOWN,
            ];
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
