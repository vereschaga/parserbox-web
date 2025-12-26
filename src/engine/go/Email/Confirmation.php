<?php

namespace AwardWallet\Engine\go\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "go/it-7691866.eml, go/it-7764612.eml";

    public $reFrom = "@mokuleleairlines.com";
    public $reBody = [
        'en' => ['Please print this confirmation for your records', 'Mokulele cannot provide transportation'],
    ];
    public $reSubject = [
        'MOKULELE Confirmation #',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'mokuleleairlines.com')] | //text()[contains(.,'Mokulele Airlines')] | //img[contains(@src,'MokuleleAir')]")->length > 0) {
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(.,'Confirmation number')]/following::text()[normalize-space(.)][1]");
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[contains(.,'Receipt and Itinerary as of')]", null, true, "#Receipt and Itinerary as of\s*(.+)#")));
        $it['Passengers'] = array_values(array_unique($this->http->FindNodes("//text()[contains(.,'Passenger(s)')]/ancestor::table[1]/following-sibling::table[count(descendant::td)=4]/descendant::td[1][normalize-space(.)][not(contains(.,'Passenger(s)'))]")));

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[contains(.,'Reservation Totals')]/ancestor::table[1]//td[contains(.,'Air fare')]/following-sibling::td[normalize-space(.)][1]"));

        if (!empty($tot['Total'])) {
            $it['BaseFare'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[contains(.,'Reservation Totals')]/ancestor::table[1]//td[contains(.,'Tax')]/following-sibling::td[normalize-space(.)][1]"));

        if (!empty($tot['Total'])) {
            $it['Tax'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[contains(.,'Reservation Totals')]/ancestor::table[1]//td[contains(.,'TOTAL')]/following-sibling::td[normalize-space(.)][1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $xpath = "//text()[normalize-space(.)='ITINERARY:']/ancestor::table[1]/descendant::tr[not(contains(.,'ITINERARY') or contains(.,'ARRIVAL'))]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $node = $this->http->FindSingleNode("./td[normalize-space(.)][1]", $root);

            if (preg_match("#([A-Z]{3})\s*\(\s*(.+?)\s*\)\s*\/\s*([A-Z]{3})\s*\(\s*(.+?)\s*\)#", $node, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['DepName'] = $m[2];
                $seg['ArrCode'] = $m[3];
                $seg['ArrName'] = $m[4];
            }
            $node = $this->http->FindSingleNode("./td[normalize-space(.)][2]", $root);

            if (preg_match("#([A-Z\d]{2})\s*\-\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $seg['Stops'] = $this->http->FindSingleNode("./td[normalize-space(.)][3]", $root, true, "#\d+#");
            $seg['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)][4]", $root)));
            $seg['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)][5]", $root)));

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            //Wed-10Dec2014 12:10 AM
            '#^\s*\w+\-(\d+)\s*(\D{3})\s*(\d{4})\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#i',
        ];
        $out = [
            '$1 $2 $3 $4',
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
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
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
}
