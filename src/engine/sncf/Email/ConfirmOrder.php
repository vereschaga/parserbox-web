<?php

namespace AwardWallet\Engine\sncf\Email;

class ConfirmOrder extends \TAccountChecker
{
    public $mailFiles = "sncf/it-5224974.eml, sncf/it-5228820.eml";

    public $reBody = [
        'en' => 'Segment',
    ];
    public $reSubject = [
        'Confirmation of your order with Voyages-sncf',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $lng => $re) {
            if (stripos($body, $re) !== false) {
                $this->lang = $lng;

                break;
            }
        }
        $its = $this->parseEmail();
        $node = $this->http->FindSingleNode("//text()[contains(translate(., 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'),'TOTAL PRICE TRAIN')]/following::text()[string-length(normalize-space(.))>3][1]");

        if (preg_match("#([A-Z]{3})\s*([\d\.\,]+)#", $node, $m)) {
            if (count($its) === 1) {
                $its[0]['TotalCharge'] = $m[2];
                $its[0]['Currency'] = $m[1];
            } else {
                $tot = [
                    'Amount'   => $m[2],
                    'Currency' => $m[1],
                ];
            }
        }

        if (isset($tot)) {
            return [
                'parsedData' => [
                    'Itineraries' => $its,
                    'TotalCharge' => $tot,
                ],
                'emailType' => "ConfirmOrder",
            ];
        } else {
            return [
                'parsedData' => ['Itineraries' => $its],
                'emailType'  => "ConfirmOrder",
            ];
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'Voyages-sncf')]")->length > 0) {
            $body = $parser->getHTMLBody();

            foreach ($this->reBody as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
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
        return stripos($from, "uk.voyages-sncf.eu") !== false;
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
        $xpathRL = "//text()[contains(.,'" . $this->t('PNR Reference') . "')]/following::text()[string-length(normalize-space())>4][1]";
        $nodes = $this->http->XPath->query($xpathRL);

        if ($nodes->length < 1) {
            return null;
        }
        $list = [];

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode(".", $root, true, "#[A-Z\d]+#");
            $numPax = $this->http->FindSingleNode("./preceding::text()[contains(.,'Product')][1]", $root, true, "#\d+#");
            $pax = [];
            $paxs = $this->http->FindNodes("./preceding::text()[normalize-space(.)='Travellers:'][1]/ancestor::tr[1]/preceding-sibling::tr[1]/following-sibling::tr/td[2]", $root);

            if (empty($numPax)) {
                $pax = $paxs;
            } else {
                $pax[] = $paxs[$numPax - 1];
            }

            $seats = $this->http->FindSingleNode("./following::text()[normalize-space(.)='Seating'][1]/ancestor::tr[1]/following::tr[1]", $root);
            $cabins = $this->http->FindSingleNode("./following::text()[normalize-space(.)='Class'][1]/ancestor::tr[1]/following::tr[1]", $root);

            $node = $this->http->XPath->query("./preceding::text()[contains(.,'Segment')][1]/ancestor::tr[1]", $root);
            $list[$rl][] = [$node->item(0), $pax, $seats, $cabins];
        }
        $its = [];

        foreach ($list as $rl => $value) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[contains(.,'Booking reference')]/following::text()[string-length(normalize-space(.))>3][1]");
            $it['ReservationDate'] = strtotime(str_replace("/", ".", $this->http->FindSingleNode("//text()[contains(.,'Order date')]/following::text()[string-length(normalize-space(.))>3][1]")));

            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            foreach ($value as $val) {
                foreach ($val[1] as $p) {
                    $it['Passengers'][] = $p;
                }
                $root = $val[0];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $node = $this->http->FindSingleNode(".", $root);

                if (preg_match("#.+?Origin:\s+(.+)\s*>\s*Destination:\s+(.+)#", $node, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['ArrName'] = $m[2];
                }
                $node = $this->http->FindSingleNode("./following::table[1]//tr[1]", $root);

                if (preg_match("#(.+?)\s+(\d+)#", $node, $m)) {
                    $seg['FlightNumber'] = $m[2];
                    $seg['AirlineName'] = $m[1];
                }
                $dateDep = $this->http->FindSingleNode("./following::table[1]//td[contains(.,'Departure date')]/following-sibling::td[1]", $root);
                $timeDep = $this->http->FindSingleNode("./following::table[1]//td[contains(.,'Departure time')]/following-sibling::td[1]", $root);
                $dateArr = $this->http->FindSingleNode("./following::table[1]//td[contains(.,'Arrival date')]/following-sibling::td[1]", $root);
                $timeArr = $this->http->FindSingleNode("./following::table[1]//td[contains(.,'Arrival time')]/following-sibling::td[1]", $root);
                $seg['DepDate'] = strtotime(str_replace("/", ".", $dateDep . ', ' . $timeDep));
                $seg['ArrDate'] = strtotime(str_replace("/", ".", $dateArr . ', ' . $timeArr));
                $seg['Cabin'] = $val[3];

                if (preg_match("#(Coach\s+\d+),\s+Seat\s+(\d+)#", $val[2], $m)) {
                    $seg['Type'] = $m[1];
                    $seg['Seats'] = $m[2];
                }
                $it['TripSegments'][] = $seg;
            }
            $it['Passengers'] = array_unique($it['Passengers']);
            $its[] = $it;
        }

        return $its;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
