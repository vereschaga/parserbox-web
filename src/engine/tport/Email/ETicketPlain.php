<?php

namespace AwardWallet\Engine\tport\Email;

class ETicketPlain extends \TAccountChecker
{
    public $mailFiles = "tport/it-17604615.eml, tport/it-3168572.eml, tport/it-8752729.eml, tport/it-8752741.eml";

    public $reFrom = "@travelport.com";
    public $reFromH = "viewtrip-admin@travelport.com";
    public $reBody = [
        'en' => 'This Electronic Ticket Receipt has been brought to you by Travelport ViewTrip and your travel provider',
        'it' => 'Questa ricevuta del biglietto elettronico ti è stata fatta pervenire da Travelport ViewTrip e dal  tuo agente di viaggio',
    ];
    public $reSubject = [
        'Electronic Ticket Receipt',
        'Ricevuta biglietto elettronico',
    ];
    public $lang = '';
    public $text;
    public $amount;
    public static $dict = [
        'en' => [],
        'it' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $this->http->SetBody($body);

        if (count(explode("\n", $body)) < 10) {
            $body = '';
        }

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }
        $this->AssignLang($body);

        $its = $this->parseEmail();

        if (isset($this->amount) && is_array($this->amount)) {
            return [
                'parsedData' => ['Itineraries' => $its, 'TotalCharge' => $this->amount],
                'emailType'  => 'ETicketPlain' . ucfirst($this->lang),
            ];
        } else {
            return [
                'parsedData' => ['Itineraries' => $its],
                'emailType'  => 'ETicketPlain' . ucfirst($this->lang),
            ];
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        return $this->AssignLang($body);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFromH) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
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

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                $its[$j]['Passengers'] = array_merge($its[$j]['Passengers'], $its[$i]['Passengers']);
                $its[$j]['Passengers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['Passengers'])));
                $its[$j]['TicketNumbers'] = array_merge($its[$j]['TicketNumbers'], $its[$i]['TicketNumbers']);
                $its[$j]['TicketNumbers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['TicketNumbers'])));
                unset($its[$i]);
            }
        }

        return $its;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmail()
    {
        $textFull = $this->text = text($this->http->Response['body']);

        $itineraries = [];

        $tripNum = $this->getField("Reservation Number");
        $pax = [$this->getField("Passenger Name")];
        $acc = [$this->re("#Frequent\s+Traveller\s+.+\n\s*([A-Z\d]{5,})#", $textFull)];
        $ticket = [$this->getField("e-Ticket Number")];
        $resDate = strtotime($this->getField("Ticket Issue Date"));

        //		$splitrow = "\n\s*----------------------------------------------------------------------------\s*";
        $splitrow = "\n\s*-{20,}\s*";
        $container = $this->re("#Flight Information\s*\n\s*=+\s*\n(.*?)\s*Fare Information\s*\n\s*=+#ms", $textFull);

        if (preg_match_all("#.*?{$splitrow}.*?{$splitrow}#ms", $container, $m)) {
            if (isset($m[0])) {
                $m = $m[0];
            }
        } else {
            $this->http->Log("segments regexp not found", LOG_LEVEL_NORMAL);

            return null;
        }

        $xpath = "//*[contains(text(),'FLIGHT') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[2]//tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($m as $text) {
            $this->text = $text;
            $it = ['Kind' => 'T', 'TripSegments' => []];

            $it['RecordLocator'] = $this->re("#([A-Z\d]+?)(?:Flight|$)#", $this->getField("Confirmation Number"));
            $it['TripNumber'] = $tripNum;
            $it['Passengers'] = $pax;
            $it['TicketNumbers'] = $ticket;
            $it['AccountNumbers'] = $acc;
            $it['ReservationDate'] = $resDate;

            $date = $this->re("#(\d+\s+\w+\s+\d+)#", $text);
            $itsegment = [];

            $itsegment['FlightNumber'] = $this->re("#\(\w{2}\)\s*(\d+)#", $text);

            $depart = $this->getField('Depart');

            if (!preg_match("#\d+:\d+#", $depart)) {
                $depart = $this->getField('Depart', false);
            }
            $itsegment['DepCode'] = $this->re("#\(\s*([A-Z]{3})\s*\)#", $depart);
            $itsegment['DepartureTerminal'] = $this->re("#Terminal\s*(\w+?)\s*(?:[A-Z][a-z]+)?\s*\d+:\d+#", $depart);

            $itsegment['DepDate'] = strtotime($date . ', ' . $this->re("#(\d+:\d+\s*(?:[AP]M)?)#i", $depart));

            $arrive = $this->getField('Arrive');

            if (!preg_match("#\d+:\d+#", $arrive)) {
                $arrive = $this->getField('Arrive', false);
            }
            $itsegment['ArrCode'] = re("#\(\s*([A-Z]{3})\s*\)#", $arrive);
            $itsegment['ArrivalTerminal'] = $this->re("#Terminal\s*(\w+?)\s*(?:[A-Z][a-z]+)?\s*\d+:\d+#", $arrive);

            $itsegment['ArrDate'] = strtotime($date . ', ' . $this->re("#(\d+:\d+\s*(?:[AP]M)?)#i", $arrive));

            if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                $itsegment['ArrDate'] = strtotime('+1 day', $itsegment['ArrDate']);
            }

            $itsegment['AirlineName'] = $this->re("#\(\s*(\w{2})\s*\)\s*\d+#", $text);

            $op = $this->getField('Flight Operated By');

            if (!empty($op)) {
                $itsegment['Operator'] = $op;
            }

            $cabin = re("#\(\s*\w{2}\s*\)\s*\d+\s+([^\n]+)#", $text);

            if (preg_match("#(.+?)\s*\(([A-Z]{1,2})\)\s*$#", $cabin, $t)) {
                $itsegment['Cabin'] = $t[1];
                $itsegment['BookingClass'] = $t[2];
            } else {
                $itsegment['Cabin'] = $cabin;
            }
            //$itsegment['Cabin'] = str_replace("/","-",$itsegment['Cabin']);

            $it['TripSegments'][] = $itsegment;
            $itineraries[] = $it;
        }
        $itineraries = $this->mergeItineraries($itineraries);

        $this->text = $textFull;
        $tot = $this->getTotalCurrency($this->getField("Total"));

        if (!empty($tot['Total'])) {
            if (count($itineraries) > 1) {
                $this->amount = ['Amount' => $tot['Total'], 'Currency' => $tot['Currency']];
            } elseif (count($itineraries) === 1) {
                $itineraries[0]['TotalCharge'] = $tot['Total'];
                $itineraries[0]['Currency'] = $tot['Currency'];
                $tot = $this->getTotalCurrency($this->getField("Fare:"));
                $itineraries[0]['BaseFare'] = $tot['Total'];
            }
        }

        return $itineraries;
    }

    private function getField($str, $onestr = true)
    {
        if ($onestr) {
            return trim(re("#" . white($str) . "\s*:?\s*([^\n]+)#", $this->text));
        } else {
            return trim(re("#" . white($str) . "\s*:?\s*([^\n]+\n[^\n]+)#", $this->text));
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody) !== false) {
                    $this->lang = $lang;

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

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
