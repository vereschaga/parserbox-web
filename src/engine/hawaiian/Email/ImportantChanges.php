<?php

namespace AwardWallet\Engine\hawaiian\Email;

class ImportantChanges extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "hawaiian/it-12812311.eml, hawaiian/it-4749156.eml, hawaiian/it-5145275.eml";

    public $reBody = [
        'en' => ['IMPORTANT NOTIFICATION', 'Important Info From Hawaiian Airlines'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Flight' => 'FLT #',
        ],
    ];
    private $regExp = [
        '#\d+\s*\w+\s*\d+#', //Date 20Aug16
        '#[0-1]?[0-9]+\:[0-5][0-9]\s*[PA]M#', //Departs
        '#[0-1]?[0-9]+\:[0-5][0-9]\s*[PA]M#', //Arrives
        '#\d+#', //FLT#
        '#[A-Z]{3}\s+to\s+[A-Z]{3}#', //ROUTE
        '#.+#', //PASSENGERS
        '#.+#', //SEAT
        '#.+#', //TERM
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ImportantChanges",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//a[@href='http://www.hawaiianairlines.com']")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'IMPORTANT FLIGHT CHANGE') !== false
        || isset($headers['from']) && stripos($headers['from'], 'hawaiianairlines.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "hawaiianairlines.com") !== false;
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
        //		$it['RecordLocator'] = $this->http->FindSingleNode("//span[@id='pnr-locator']");
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(.,'Confirmation Code')]/following::*[1]");

        $passengers = array_filter(array_unique($this->http->FindNodes("//table//th[contains(text(),'" . $this->t('Flight') . "')]/../following-sibling::tr[not(contains(@style,'font-weight'))]//td[6]")));

        if (!empty($passengers)) {
            $it['Passengers'] = [];

            foreach ($passengers as $value) {
                $it['Passengers'] = array_merge($it['Passengers'], explode(",", $value));
            }
            $it['Passengers'] = array_unique(array_map('trim', $it['Passengers']));
        }
        //Flights
        $node2 = $this->http->FindNodes("//table//th[contains(text(),'" . $this->t('Flight') . "')]/../following-sibling::tr[not(contains(@style,'font-weight'))]//td");

        foreach ($this->regExp as $i=>$v) {
            $newFlightArr[$i] = $this->http->FindNodes("//table//th[contains(text(),'" . $this->t('Flight') . "')]/../following-sibling::tr[not(contains(@style,'font-weight'))]//td[" . intval($i + 1) . "]", null, $v);
        }
        $segs[] = [];

        for ($i = 0; $i < count($newFlightArr[0]); $i++) {
            if (empty(trim($newFlightArr[3][$i]))) {
                continue;
            }
            $segs[$i]['FlightNumber'] = $newFlightArr[3][$i];
            $segs[$i]['AirlineName'] = 'HA';

            if (preg_match("#([A-Z]{3})\s+to\s+([A-Z]{3})#", $newFlightArr[4][$i], $m)) {
                $segs[$i]['DepCode'] = $m[1];
                $segs[$i]['ArrCode'] = $m[2];
            }
            $segs[$i]['DepDate'] = strtotime($newFlightArr[0][$i] . " " . $newFlightArr[1][$i]);
            $segs[$i]['ArrDate'] = strtotime($newFlightArr[0][$i] . " " . $newFlightArr[2][$i]);
            $segs[$i]['Seats'] = "";

            for ($j = 0; $j < (count($it['Passengers'])); $j++) {
                if (!empty($node2[$j * 8 + $i * 8 + 6])) {
                    $segs[$i]['Seats'] .= (!empty($segs[$i]['Seats']) ? ',' : '') . $node2[$j * 8 + $i * 8 + 6];
                }
            }

            if (!empty(trim($newFlightArr[7][$i]))) {
                $segs[$i]['DepartureTerminal'] = trim($newFlightArr[7][$i]);
            }
        }

        foreach ($segs as $i=>$seg) {
            if (!empty($seg['Seats'])) {
                $seg['Seats'] = array_filter(array_map('trim', explode(',', $seg['Seats'])));
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
