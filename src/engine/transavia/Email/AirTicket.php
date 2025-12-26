<?php

namespace AwardWallet\Engine\transavia\Email;

class AirTicket extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "transavia/it-4080694.eml";
    public $reBody = [
        'it' => ['La ringraziamo'],
    ];
    public $lang = '';
    public $dict = [
        'en' => [],
        'it' => [
            'Record locator'  => 'Numero di conferma',
            'Outbound flight' => 'Volo di andata',
            'Flight'          => 'volo',
            'Departure'       => 'partenza',
            'Arrival'         => 'arrivo',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false) {
                    $this->lang = $lang;

                    break;
                } else {
                    $this->lang = 'en';
                }
            }
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match("#[\w]*[@][\w]*[.]*transavia\.com#", $headers['from']);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'transavia.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['it'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("(//*[contains(normalize-space(.), '" . $this->t('Record locator') . "')]/following-sibling::*[1])[1]");
        $passengers = $this->http->FindNodes("//*[contains(normalize-space(text()), 'Passeggeri')]/ancestor::table[1]/following-sibling::*[1]/descendant::text()[1]");

        if ($d = array_map(function ($e) { if (preg_match("#(?:MRS|MR|MISS)\s*(\w+)\s*(?:\/)\s+(\w+)#", $e, $m)) { return ucfirst($m[1]) . ' ' . ucfirst($m[2]); } }, $passengers)) {
            $it['Passengers'] = $d;
        }
        $it['BaseFare'] = $this->getTotal($this->http->FindSingleNode("//text()[contains(., 'tariffa')]"));
        $it['Tax'] = $this->getTotal($this->http->FindSingleNode("//text()[contains(., 'tasse e commissioni')]"));
        $totalCharge = $this->http->FindSingleNode("//text()[contains(., 'Pagamento')]/following::text()[normalize-space(.)!=''][1]");

        if (preg_match("#([\d.,]*)\s+(\w+)\s+.+#", $totalCharge, $m)) {
            $it['TotalCharge'] = $m[1];
            $it['Currency'] = ($m[2] === 'Euro') ? 'EUR' : null;
        }
        $xpath = "//*[contains(normalize-space(text()), '" . $this->t('Outbound flight') . "')]/ancestor::table[1]/following-sibling::p[1]/font/descendant::text()[contains(., '" . $this->t('Flight') . "')]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->http->Log("roots not found {$xpath}", LOG_LEVEL_NORMAL);
        }

        foreach ($roots as $root) {
            $seg = [];
            $flightNumber = $this->http->FindSingleNode(".", $root);

            if (preg_match("#(?<day>\d+)-(?<month>\d+)-(?<year>\d{4}),\s+\w+\s+(?<aName>\D{2})(?<fNumber>\d+)#", $flightNumber, $math)) {
                $seg['AirlineName'] = $math['aName'];
                $seg['FlightNumber'] = $math['fNumber'];
                $date = $math['month'] . '/' . $math['day'] . '/' . $math['year'];
            }
            $depName = $this->getDetails($this->http->FindSingleNode("following-sibling::text()[contains(., '" . $this->t('Departure') . "')]", $root));

            if (count($depName) === 3) {
                $seg['DepName'] = $depName['Name'];
                $seg['DepCode'] = $depName['Code'];
                $seg['DepDate'] = strtotime($date . ' ' . $depName['Time']);
            }
            $arrName = $this->getDetails($this->http->FindSingleNode("following-sibling::text()[contains(., 'arrivo')]", $root));

            if (count($arrName) === 3) {
                $seg['ArrName'] = $arrName['Name'];
                $seg['ArrCode'] = $arrName['Code'];
                $seg['ArrDate'] = strtotime($date . ' ' . $arrName['Time']);
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getDetails($str)
    {
        $arr = [];

        if (preg_match("#(?:" . $this->t('Departure') . "|" . $this->t('Arrival') . ")\s+(?<name>[\w]+)[()\w\s]*\s+\((?<code>\w{3})\)\s+(?<time>\d+:\d+)#", $str, $m)) {
            $arr = [
                'Name' => $m['name'],
                'Code' => $m['code'],
                'Time' => $m['time'],
            ];
        }

        return $arr;
    }

    private function getTotal($str)
    {
        if (preg_match("#:\s+([\d.\d|\d,\d]*)\s+\w*#", $str, $m)) {
            return $m[1];
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset($this->dict[$this->lang][$s])) {
            return $s;
        }

        return $this->dict[$this->lang][$s];
    }
}
