<?php

namespace AwardWallet\Engine\wagonlit\Email;

class TravelDocuments2017 extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-5970491.eml";

    public $reBody = [
        'en' => ['DEPARTURE', 'This document reflects the latest status of your booking'],
    ];

    public $reSubject = [
        '#Travel\s+documents\s+for\s+.+?\([A-Z\d]+\)\s+-\s+E-ticket#',
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

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "TravelDocuments2017" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'cwt')] | //text()[contains(.,'CWT')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (preg_match($reSubject, $headers["subject"])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "contactcwt.com") !== false;
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
        $its = [];
        $tripNum = $this->http->FindSingleNode("//text()[contains(.,'Trip Locator')]/following::text()[string-length(normalize-space(.))>4][1]", null, true, "#[A-Z\d]+#");
        $pax[] = $this->http->FindSingleNode("//text()[contains(.,'Traveler')]/following::text()[string-length(normalize-space(.))>4][1]");
        $xpath = "//text()[contains(.,'DEPARTURE')]/ancestor::tr[contains(.,'ARRIVAL')]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $airline = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)][1]", $root, true, "#\s*([A-Z\d]{2})\s+\d+$#");
            $rl = "";

            if (!empty($airline)) {
                $rl = $this->http->FindSingleNode("//text()[contains(.,'AIRLINE BOOKING REFERENCE(S)')]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][contains(.,'{$airline}/')][1]", null, true, "#{$airline}\/([A-Z\d]+)#");
            }

            if (empty($rl)) {
                $airs[$tripNum][] = $root;
            } else {
                $airs[$rl][] = $root;
            }
        }

        foreach ($airs as $rl => $roots) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['Passengers'] = $pax;
            $it['TripNumber'] = $tripNum;

            foreach ($roots as $root) {
                $seg = [];

                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)][2]", $root)));

                $seg['DepName'] = $this->http->FindSingleNode("./td[normalize-space(.)][2]", $root);
                $seg['ArrName'] = $this->http->FindSingleNode("./td[normalize-space(.)][4]", $root);

                $node = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)][1]", $root);

                if (preg_match("#\s*([A-Z\d]{2})\s+(\d+)$#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                $num = 1;
                $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][1][contains(.,'Terminal')]", $root);

                if (!empty($node)) {
                    $cnt = $this->http->XPath->query("./following-sibling::tr[normalize-space(.)][1][contains(.,'Terminal')]/td", $root)->length;

                    switch ($cnt) {
                        case 2:
                            $seg['ArrivalTerminal'] = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][1][contains(.,'Terminal')]/td[2]", $root);

                            break;

                        case 3:
                            $seg['DepartureTerminal'] = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][1][contains(.,'Terminal')]/td[2]", $root);

                            break;

                        case 4:
                            $seg['DepartureTerminal'] = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][1][contains(.,'Terminal')]/td[2]", $root);
                            $seg['ArrivalTerminal'] = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][1][contains(.,'Terminal')]/td[4]", $root);

                            break;
                    }
                    $num = 2;
                }
                $dateDep = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][{$num}]/td[normalize-space(.)][2]", $root)));

                if (!$dateDep) {
                    $dateDep = $date;
                }
                $seg['DepDate'] = strtotime($this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][{$num}]/td[normalize-space(.)][1]", $root, true, "#(\d+:\d+)#"), $dateDep);
                $dateArr = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][{$num}]/td[normalize-space(.)][4]", $root)));

                if (!$dateArr) {
                    $dateArr = $date;
                }
                $seg['ArrDate'] = strtotime($this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][{$num}]/td[normalize-space(.)][3]", $root, true, "#(\d+:\d+)#"), $dateArr);

                if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
                    $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
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
            '#(\d{2})[\.\/]+(\d{2})[\.\/]+(\d{4})#',
            '#\S+\s+(\d+)\s+(\S+?),\s+(\d+)#',
        ];
        $out = [
            '$3-$2-$1',
            '$1 $2 $3',
        ];

        return preg_replace($in, $out, $date);
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
        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
