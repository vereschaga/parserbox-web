<?php

namespace AwardWallet\Engine\tamair\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "tamair/it-6753775.eml";

    public $reFrom = "tamviagens.com";
    public $reBody = [
        'pt' => ['Agradecemos por escolher a TAM Viagens para realizar a sua compra'],
    ];
    public $reSubject = [
        '',
    ];
    public $lang = '';
    public static $dict = [
        'pt' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'latam')] | //text()[contains(.,'TAM Viagens')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
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

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'LOCALIZADOR DA RESERVA')]", null, true, "#:\s*([A-Z\d]{5,})#");
        $it['Passengers'] = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'Nome Completo')]/ancestor::tr[1][contains(.,'do Bilhete')]/following-sibling::tr/td[normalize-space(.)][1]", null, "#\d+[\s\-]+(.+)#");
        $it['TicketNumbers'] = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'Nome Completo')]/ancestor::tr[1][contains(.,'do Bilhete')]/following-sibling::tr/td[normalize-space(.)][2]");

        $xpath = "//text()[starts-with(normalize-space(.),'Vôo')]/ancestor::tr[1][contains(.,'Aeroporto')]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $node = $this->http->FindSingleNode("./td[normalize-space(.)][1]", $root);

            if (preg_match("#([A-Z\d]{2})[\s\-]*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            //$seg['Operator'] = $this->http->FindSingleNode("./td[normalize-space(.)][2]",$root);
            $seg['BookingClass'] = $this->http->FindSingleNode("./td[normalize-space(.)][3]", $root);
            $seg['DepName'] = $this->http->FindSingleNode("./td[normalize-space(.)][4]", $root);
            $seg['DepCode'] = $this->http->FindSingleNode("./td[normalize-space(.)][5]", $root, true, "#^\s*([A-Z]{3})#");
            $seg['ArrCode'] = $this->http->FindSingleNode("./td[normalize-space(.)][6]", $root, true, "#^\s*([A-Z]{3})#");
            $seg['ArrName'] = $this->http->FindSingleNode("./td[normalize-space(.)][6]", $root, true, "#^\s*[A-Z]{3}[\s\-]+(.+)#");
            $seg['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)][7]", $root)));
            $seg['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)][8]", $root)));

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            //30/09/2016 05:03
            '#^(\d+)\/(\d+)\/(\d+)\s+(\d+:\d+)$#',
        ];
        $out = [
            '$3-$2-$1 $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$re}')]")->length > 0) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }
}
