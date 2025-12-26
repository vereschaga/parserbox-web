<?php

namespace AwardWallet\Engine\onetravel\Email;

class Alert extends \TAccountChecker
{
    public $mailFiles = "onetravel/it-6174597.eml";

    public $reFrom = "onetravel.com";
    public $reBody = [
        'en' => ['Depart:', 'Confirmation Code:'],
    ];
    public $reSubject = [
        'Flight Watcher Alert from OneTravel.com',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Confirmation Code:' => ['Confirmation Code:', 'Booking Confirmation Code:'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "Alert" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(translate(.,' ',''),'OneTravel')]")->length > 0) {
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Confirmation Code:')) . "]/following::text()[normalize-space(.)][1]");
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->starts($this->t('Passengers:')) . "]/ancestor::table[1]//td[2]");
        $xpath = "//text()[" . $this->starts($this->t('Depart:')) . "]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $i = 0;
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./ancestor::table[1]/preceding-sibling::table[descendant::img[contains(@src,'plane')]][1]//td[3]", $root)));

            $node = $this->http->FindSingleNode("./ancestor::table[1]/preceding-sibling::div[2]", $root);

            if (preg_match("#([A-Z]{3})\s+to\s+([A-Z]{3})#", $node, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['ArrCode'] = $m[2];
                $i = 1;
            } else {
                $node = $this->http->FindSingleNode("./ancestor::table[1]/preceding-sibling::div[1]", $root);

                if (preg_match("#([A-Z]{3})\s+to\s+([A-Z]{3})#", $node, $m)) {
                    $seg['DepCode'] = $m[1];
                    $seg['ArrCode'] = $m[2];
                }
            }
            $node = $this->http->FindSingleNode("./ancestor::table[1]/preceding-sibling::div[(2+$i)]", $root);

            if (preg_match("#\(([A-Z\d]{2})\).+?\s+(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $seg['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]", $root), $date);
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("./following-sibling::tr[starts-with(normalize-space(.),'Arrive:')]/td[2]", $root), $date);

            $depT = $this->http->FindSingleNode("//text()[(contains(normalize-space(.),'" . $seg['FlightNumber'] . " Flight Detail'))]/following::table[(contains(normalize-space(.),'" . $this->t('Departure:') . "')) and (contains(normalize-space(.),'" . $seg['DepCode'] . "'))]/descendant::td[" . $this->starts($this->t('Terminal:')) . "]/following-sibling::*[1]", null, true, "/^[A-Z\d]{1,4}$/");

            if (!empty($depT)) {
                $seg['depTerminal'] = $depT;
            }
            $arrT = $this->http->FindSingleNode("//text()[(contains(normalize-space(.),'" . $seg['FlightNumber'] . " Flight Detail'))]/following::table[(contains(normalize-space(.),'" . $this->t('Arrival:') . "')) and (contains(normalize-space(.),'" . $seg['ArrCode'] . "'))]/descendant::td[" . $this->starts($this->t('Terminal:')) . "]/following-sibling::*[1]", null, true, "/^[A-Z\d]{1,4}$/");

            if (!empty($arrT)) {
                $seg['arrTerminal'] = $arrT;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            //Jul-18-2016
            '#(\w+)-(\d+)-(\d+)#u',
        ];
        $out = [
            '$2 $1 $3',
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
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }
}
