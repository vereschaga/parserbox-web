<?php

namespace AwardWallet\Engine\japanair\Email;

class AirTicketZh extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            "emailType"  => 'AirTicketZh',
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query(".//*[contains(normalize-space(.), '日本航空をご利用いただきまことにありがとうございます')]")->length > 0
            && stripos($this->http->Response['body'], '日本航空をご利用いただきまことにありがとうございます') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@jal.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@jal.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['zh'];
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode(".//*[contains(normalize-space(text()), '航空会社／予約番号:')]/font/span", null, true, "#\S{1,3}([\d\D]+)#");
        $it['Passengers'] = $this->http->FindNodes(".//td[contains(normalize-space(.), 'お客様情報')]/following::td[3]/span[1]");
        $xpath = ".//td[contains(normalize-space(.), 'フライト1')]/following-sibling::td[1]/ancestor::table[1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length > 0) {
            foreach ($roots as $root) {
                $seg = [];
                $dep = $this->normalizeNod($this->getNode('出発地', $root, 2));
                $seg['DepName'] = $dep['Name'];
                $seg['DepartureTerminal'] = $dep['Terminal'];
                $arr = $this->normalizeNod($this->getNode('到着地', $root, 2));
                $seg['ArrName'] = $arr['Name'];
                $seg['ArrivalTerminal'] = $arr['Terminal'];
                $flightNumber = $this->getNode('ご利用便', $root);

                if (preg_match("#(\D{2})\s*(\d+)#", $flightNumber, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $seg['Aircraft'] = $this->getNode('機種', $root);
                $seg['Cabin'] = $this->getNode('運賃の種類', $root);
                $depTime = $this->normalizeDate($this->getNode('出発地', $root));
                $arrTime = $this->normalizeDate($this->getNode('到着地', $root));
                $date = $this->normalizeDate($this->getNode('フライト', $root));

                if (preg_match("#(\d{1,2}) (\d{2}) (\d{4})\s*.+#", $date, $m) && isset($depTime) && isset($arrTime)) {
                    $seg['DepDate'] = $this->getResultDate($depTime, $m);
                    $seg['ArrDate'] = $this->getResultDate($arrTime, $m);
                }
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $it['TripSegments'][] = $seg;
            }
        }

        return [$it];
    }

    private function getResultDate($time, $m)
    {
        if (is_array($time = $this->doCheckForDay($time))) {
            ++$m[2];

            return strtotime($m[1] . '/' . $m[2] . '/' . $m[3] . ' ' . $time[0]);
        } else {
            return strtotime($m[1] . '/' . $m[2] . '/' . $m[3] . ' ' . $time);
        }
    }

    private function doCheckForDay($time)
    {
        if (preg_match("#(\d{2}:\d{2})\s*(\S+)#", $time, $m)) {
            return [$m[1], $m[2]];
        } else {
            return $time;
        }
    }

    private function normalizeDate($date)
    {
        $pattern = [
            '0' => [
                "#(\d{4}).*(\d{1,2}).*(\d{2})#",
                "#(\d{2}:\d{2})\s+(\S{1,2})\s*.+#",
            ],
            '1' => [
                "$2 $3 $1",
                "$1 $2",
            ],
        ];

        return $date = (preg_replace($pattern[0], $pattern[1], $date));
    }

    private function normalizeNod($nod)
    {
        if (preg_match("#(\w+\s*).+ - [\w\s,]+(.+)#", $nod, $m)) {
            return ['Name' => $m[1], 'Terminal' => $m[2]];
        }
    }

    private function getNode($str, $root, $td = null)
    {
        if ($td === null) {
            return $this->http->FindSingleNode("descendant::td[contains(., '{$str}')]/following-sibling::td[1]", $root);
        } else {
            return $this->http->FindSingleNode("descendant::td[contains(., '{$str}')]/following-sibling::td[$td]", $root);
        }
    }
}
