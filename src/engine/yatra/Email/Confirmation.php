<?php

namespace AwardWallet\Engine\yatra\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "yatra/it-6805851.eml";

    public $reFrom = "yatra.com";
    public $reBody = [
        'en' => ['Thank you for booking with Yatra.com', 'Yatra.com Representative'],
    ];
    public $reSubject = [
        'Confirmation Email',
        'Booking Confirmation - Yatra Reference',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        if (count($its) === 1) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::td[1]/following-sibling::td[1]"));

            if (!empty($tot['Total'])) {
                $its[0]['BaseFare'] = $tot['Total'];
                $its[0]['Currency'] = $tot['Currency'];
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[normalize-space()='Total Amount']/ancestor::td[1]/following-sibling::td[1]"));

            if (!empty($tot['Total'])) {
                $its[0]['TotalCharge'] = $tot['Total'];
                $its[0]['Currency'] = $tot['Currency'];
            }
        } else {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[normalize-space()='Total Amount']/ancestor::td[1]/following-sibling::td[1]"));

            if (!empty($tot['Total'])) {
                return [
                    'parsedData' => ['Itineraries' => $its, 'TotalCharge' => ['Amount' => $tot['Total'], 'Currency' => $tot['Currency']]],
                    'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
                ];
            }
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'yatra.com')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
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
        $its = [];
        $tripNum = $this->http->FindSingleNode("//text()[" . $this->contains(["Your booking reference no", "Reference No"]) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([A-Z\d]+)\s*$#");
        $pax = $this->http->FindNodes("//text()[normalize-space()='Name']/ancestor::table[1][contains(.,'Type')]//tr[not(contains(.,'Name') and contains(.,'Type'))]/td[2]");

        $xpath = "//text()[normalize-space()='Departure:']/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $node = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(.),'Airline PNR')]/following::text()[normalize-space(.)][1]", $root, true, "#^\s*([A-Z\d]+)\s*$#");

            if ($node) {
                $airs[$node][] = $root;
            } else {
                $airs[$tripNum][] = $root;
            }
        }

        foreach ($airs as $rl => $nodes) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $tripNum;
            $it['Passengers'] = $pax;

            $tickets = [];
            $pax = [];

            foreach ($nodes as $root) {
                $seg = [];
                $node = $this->http->FindSingleNode("./td[1]", $root);

                if (preg_match("#([A-Z\d]{2}).*?(\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $seg['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[2]", $root, true, "#Departure:\s+(.+)#")));
                $seg['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root, true, "#Arrival:\s+(.+)#")));
                $node = $this->http->FindSingleNode("./td[3]", $root);

                if (preg_match("#^(?:Terminal[ ]*(.*?),)?\s*(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                    if (isset($m[1]) && !empty($m[1])) {
                        $seg['DepartureTerminal'] = $m[1];
                    }
                    $seg['DepName'] = $m[2];
                    $seg['DepCode'] = $m[3];
                }
                $node = $this->http->FindSingleNode("./following-sibling::tr[1]/td[3]", $root);

                if (preg_match("#^(?:Terminal[ ]*(.*?),)?\s*(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                    if (isset($m[1]) && !empty($m[1])) {
                        $seg['ArrivalTerminal'] = $m[1];
                    }
                    $seg['ArrName'] = $m[2];
                    $seg['ArrCode'] = $m[3];
                }
                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Thu, 2 Oct,2014 , 13:55
            '#^.*?(\d+)\s+(\D+?),?\s*(\d{4})[,\s]+(\d+:\d+)\s*$#',
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

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("Rs.", "INR", $node);
        $node = preg_replace("#(?:^|\s)\\$(?:\s|$)#", " USD ", $node);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
