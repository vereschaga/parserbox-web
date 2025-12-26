<?php

namespace AwardWallet\Engine\jetstar\Email;

class AirTicketZh extends \TAccountChecker
{
    use \DateTimeTools;
    public const numberMonths = 12;
    public $mailFiles = "jetstar/it-3825962.eml";
    public $monthNames = [
        'en' => [
            1  => "january",
            2  => "february",
            3  => "march",
            4  => "april",
            5  => "may",
            6  => "june",
            7  => "july",
            8  => "august",
            9  => "september",
            10 => "october",
            11 => "november",
            12 => "december",
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'AirTicketZh',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query(".//b[contains(normalize-space(.), 'Jetstar Flight Itinerary')]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], "noreplyitineraries@jetstar.com") !== false
            || isset($headers['subject']) && stripos($headers['subject'], "Jetstar Flight Itinerary") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "noreplyitineraries@jetstar.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return ['zh'];
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode(".//strong[contains(normalize-space(.), '預訂參考號') or contains(normalize-space(.), 'ご予約番号')]/following::tr[1]/td");
        $total = $this->http->FindSingleNode(".//td/strong[contains(normalize-space(.), '已收到')]");

        if (preg_match("#(\d+.\d+)\s+(\D{3})#", $total, $math)) {
            $it['TotalCharge'] = $math[1];
            $it['Currency'] = $math[2];
        }
        $nodes = $this->http->FindNodes("//text()[normalize-space(.)='乘客' or normalize-space(.)='搭乗者']/ancestor::tr[1]/following-sibling::tr/td[1]/descendant::text()[normalize-space(.)][1]");

        if ($nodes != null) {
            $it['Passengers'] = array_unique($nodes);
        }

        $xpath = ".//text()[contains(normalize-space(.), '飛行時間:')]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length > 0) {
            foreach ($roots as $root) {
                $seg = [];
                $duration = $this->http->FindSingleNode("(./td[1]//text())[4]", $root);

                if (preg_match("#.+: (\d+).+ (\d+)#u", $duration, $var)) {
                    $seg['Duration'] = $var[1] . ':' . $var[2];
                }
                $depTime = $this->http->FindSingleNode("(./td[2]//text())[3]", $root);

                if (preg_match("#(\d{2}):?(\d{2})\s*.+#u", $depTime, $mathec)) {
                    $depTime = $mathec[1] . ':' . $mathec[2];
                }
                $seg['DepDate'] = strtotime($this->getDate($this->http->FindSingleNode("(./td[2]//text())[2]", $root)) . ' ' . $depTime);
                $seg['DepName'] = $this->http->FindSingleNode("(./td[2]//text())[4]", $root);
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $arrTime = $this->http->FindSingleNode("(./td[3]//text())[3]", $root);

                if (preg_match("#(\d{2}):?(\d{2}) .+#", $arrTime, $v)) {
                    $arrTime = $v[1] . ':' . $v[2];
                }
                $seg['ArrDate'] = strtotime($this->getDate($this->http->FindSingleNode("(./td[3]//text())[2]", $root)) . ' ' . $arrTime);
                $seg['ArrName'] = $this->http->FindSingleNode("(./td[3]//text())[4]", $root);
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $flighAir = $this->http->FindSingleNode("(./td[1]//text())[2]", $root);

                if (preg_match("#.+ ([A-Z]\d|\d[A-Z]|[A-Z]{2})(\d+)#", $flighAir, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $it['TripSegments'][] = $seg;
            }
        }

        return [$it];
    }

    private function getDate($node)
    {
        if (preg_match("#(?<Year>\d{4})\s*\D*\s*(?<Month>\d+)\D+\s*(?<Day>\d+)#u", $node, $m)) {
            $node = $m['Day'] . ' ' . $this->monthNames['en'][$m['Month']] . ' ' . $m['Year'];
        }

        return $node;
    }
}
