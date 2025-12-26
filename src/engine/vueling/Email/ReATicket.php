<?php

namespace AwardWallet\Engine\vueling\Email;

class ReATicket extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = " vueling/it-5239046.eml";
    public $reBody = [
        'it' => ['Numero di conferma', 'Numero del volo'],
    ];
    public $reSubject = [
        '#Vueling\s*/\s*Informazioni\s+sulla\s+prenotazione#', //it
    ];
    public $lang = '';
    public static $dict = [
        'it' => [
            'Confirmation number' => 'Numero di conferma',
            'Date of reservation' => 'Data della prenotazione',
            'Status'              => 'Stato',
            'Passengers'          => 'Passeggeri',
            'Total'               => 'Importo totale',
            'Flight'              => 'Numero del volo',
            'From'                => 'Da',
            'To'                  => 'a',
            'Date'                => 'Data',
            'Departure time'      => 'Orario di partenza',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = text($this->http->Response['body']);

        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
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
        if ($this->http->XPath->query("//*[contains(text(), 'Vueling') or contains(text(), 'vueling')]")->length > 0) {
            $body = text($this->http->Response['body']);

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (preg_match($re, $headers["subject"])) {
                return true;
            }
        }

        return false;
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
        //echo $this->lang;
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Confirmation number') . "')]/following::text()[normalize-space(.)][1]");
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Date of reservation') . "')]", null, true, "#:\s*\S+\s*(.+)#")));
        $it['Status'] = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Status') . ":')]/following::text()[normalize-space(.)][1]");

        $it['Passengers'] = array_filter($this->http->FindNodes("//img[contains(@alt,'" . $this->t('Passengers') . "')]/following::table[2]//tr[normalize-space(.)!='' and count(td)=3]/td[1]"));
        $w = $this->t('Total');

        if (!is_array($w)) {
            $w = [$w];
        }
        $rule = implode(' or ', array_map(function ($s) {
            return "contains(.,'{$s}')";
        }, $w));
        $total = $this->http->FindSingleNode("//text()[{$rule}]");

        if (preg_match("#(\d[\d\.,\s]*\d*)\s+(.+)#", $total, $math)) {
            $it['TotalCharge'] = cost($math[1]);
            $it['Currency'] = currency($math[2]);
        }

        $xpath = "//text()[contains(.,'Numero del volo')]/ancestor-or-self::p[1]";
        $node = implode("\n", $this->http->FindNodes($xpath));

        if (empty($node)) {
            $this->http->Log("segments not found : {$xpath}", LOG_LEVEL_NORMAL);

            return null;
        }

        $roots = preg_split("#\s*" . $this->t('From') . "\s+#", $node, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($roots as $root) {
            if (!preg_match("#.+?\s*\([A-Z]{3}\)\s+" . $this->t('To') . "\s+.+?\s*\([A-Z]{3}\)#", $root)) {
                continue;
            }
            $seg = [];

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)\s+a\s+(.+?)\s*\(([A-Z]{3})\)#", $root, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
                $seg['ArrName'] = $m[3];
                $seg['ArrCode'] = $m[4];
            }

            if (preg_match("#" . $this->t('Date') . "\s*:\s*\S+\s*(\d+\s*\S+\s*\d+)#", $root, $m)) {
                $date = $this->normalizeDate($m[1]);

                if (preg_match("#" . $this->t('Departure time') . "\s*:\s*(\d+:\d+)\s+.+?:\s*(\d+:\d+)#s", $root, $m)) {
                    $seg['DepDate'] = strtotime($date . ' ' . $m[1]);
                    $seg['ArrDate'] = strtotime($date . ' ' . $m[2]);
                }
            }

            if (preg_match("#" . $this->t('Flight') . "\s*:\s*([A-Z\d]{2})\s*(\d+)#", $root, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match("#Terminal\s*:\s*(.+?)$#", $root, $m)) {
                $seg['DepartureTerminal'] = trim($m[1]);
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#.+\s+(\d{2})\s+[in ]*(\w+)\s+(\d{4})#ui", //u - для рус. яз.
        ];
        $out = [
            "$2 $1 $3",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
