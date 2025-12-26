<?php

namespace AwardWallet\Engine\sncf\Email;

class ConfirmationEmail extends \TAccountChecker
{
    public $mailFiles = "";

    public $reBody = [
        'en' => ['COLLECTION REFERENCE', 'local time'],
    ];
    public $reSubject = [
        'Your confirmation email',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6,
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    private $tot;
    private $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        if (count($its) === 1) {
            $its[0]['TotalCharge'] = $this->tot['Total'];
            $its[0]['Currency'] = $this->tot['Currency'];
        } else {
            return [
                'parsedData' => ['Itineraries' => $its, 'TotalCharge' => ['Amount' => $this->tot['Total'], 'Currency' => $this->tot['Currency']]],
                'emailType'  => "ConfirmationEmail",
            ];
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ConfirmationEmail",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'sncf')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
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
        return stripos($from, "voyages-sncf.com") !== false;
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

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
    }

    private function parseEmail()
    {
        $its = [];
        $pax = array_unique($this->http->FindNodes("//text()[contains(.,'" . $this->t('COLLECTION REFERENCE') . "')]/ancestor::tr[1]/following-sibling::tr[count(descendant::table)>0]//table/descendant::table//td[1]"));
        $recLocs = array_unique($this->http->FindNodes("//text()[contains(.,'" . $this->t('COLLECTION REFERENCE') . "')]/ancestor::tr[1]/following-sibling::tr[count(descendant::table)>0]//table/descendant::table//td[2]", null, "#([A-Z\d]+)#"));
        $this->tot = $this->getTotalCurrency(str_replace("£", "GBP", $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('TOTAL PRICE') . "')]/ancestor::td[1]/following-sibling::td[1]")));

        if (count($recLocs) === 1) {
            $mas[$recLocs[0]] = $pax;
        } elseif (count($pax) === count($recLocs)) {
            $mas = array_combine($recLocs, $pax);
        } else {
            $trip = $this->http->FindSingleNode("text()[contains(.,'" . $this->t('BOOKING NUMBER') . "')]", null, true, "#:\s*([A-Z\d]+)#");
            $mas[$trip] = $pax;
        }

        foreach ($mas as $rl => $p) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['Passengers'] = $p;
            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;
            $xpath = "//text()[contains(.,'" . $this->t('local time') . "')]/ancestor::table[2]";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $seg = [];
                $date = strtotime($this->normalizeDate(implode(" ", $this->http->FindNodes("./descendant::tr[1]/descendant::tr[count(descendant::tr)=0 and contains(.,':')]//text()[normalize-space(.)]", $root))));
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $seg['DepDate'] = strtotime($this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[normalize-space(.)][1]/descendant::table[1]/descendant::tr[1]", $root), $date);
                $seg['DepName'] = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[normalize-space(.)][1]/descendant::table[1]//tr[2]", $root);
                $node = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[normalize-space(.)][1]/descendant::table[1]//tr[3]", $root);

                if (preg_match("#(.+?)\s*\|\s*(.+)#", $node, $m)) {
                    $seg['FlightNumber'] = $m[1];
                    $seg['Cabin'] = trim($m[2]);
                }
                $seg['Duration'] = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[normalize-space(.)][1]/descendant::table[1]//tr[4]", $root);
                $node = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[normalize-space(.)][1]/descendant::table[1]//tr[6]", $root);

                if (preg_match("#(Coach\s+\d+)\s+seat\s+(\d+)#i", $node, $m)) {
                    $seg['Type'] = isset($seg['FlightNumber']) ? $seg['FlightNumber'] . '/' . $m[1] : $m[1];
                    $seg['Seats'] = $m[2];
                }
                $seg['ArrDate'] = strtotime($this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[normalize-space(.)][2]/descendant::table[1]//tr[1]", $root, true, "#(\d+:\d+)#"), $date);
                $seg['ArrName'] = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[normalize-space(.)][2]/descendant::table[1]//tr[2]", $root);
                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //30 MAR Thu 10:13
            '#(\d+)\s+(\S+)\s+\S+\s+(\d+:\d+)#',
        ];
        $out = [
            '$1 $2 ' . $year . ', $3',
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $date));
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
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
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

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
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
