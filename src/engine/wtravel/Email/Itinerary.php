<?php

namespace AwardWallet\Engine\wtravel\Email;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "wtravel/it-6422612.eml";

    public $reFrom = ["@worldtrav.com"];
    public $reBody = [
        'en' => ['Arrival'],
    ];
    public $reSubject = [
        '#ITINERARY\s+ONLY\s+\d{2}[A-Z]{3}\s+#',
        '#Travel\s+Receipt for\s+.+?:\s+\d{2}[A-Z]{3}\s+Trip#',
    ];
    public $lang = '';
    public $date;

    public static $dict = [
        'en' => [
            '_flights' => ['Air'],
            '_hotels'  => ['Hotel'],
            '_cars'    => 'Car',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();
        $this->date = strtotime($parser->getDate());

        $its = $this->parseEmail();
        $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Fare:')]", null, true, "#:\s+(.+)#");
        $tot = $this->getTotalCurrency(str_replace("$", "USD", $node));

        if (!empty($tot['Total'])) {
            if (count($its) > 1) {
                return [
                    'parsedData' => ['Itineraries' => $its, 'TotalCharge' => ['Amount' => $tot['Total'], 'Currency' => $tot['Currency']]],
                    'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
                ];
            } elseif (count($its) === 1) {
                $its[0]['TotalCharge'] = $tot['Total'];
                $its[0]['Currency'] = $tot['Currency'];
            }
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(.,'Click here to automatically put this itinerary in your organizer')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $flag = false;

        if (isset($headers['from'])) {
            foreach ($this->reFrom as $from) {
                if (stripos($headers['from'], $from) !== false) {
                    $flag = true;
                }
            }
        }

        if ($flag && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $fr) {
            if (stripos($from, $fr) !== false) {
                return true;
            }
        }

        return false;
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
        $tripNum = $this->http->FindSingleNode("//text()[contains(.,'BOOKING LOCATOR')]", null, true, "#:\s+([A-Z\d]+)#");
        $pax = $this->http->FindNodes("(//text()[contains(.,'BOOKING LOCATOR')]/ancestor::table[1]//text()[normalize-space(.)])[1]");
        $date = strtotime($this->normalizeDate($this->http->FindSingleNode("(//text()[contains(.,'BOOKING LOCATOR')]/ancestor::table[1]//text()[normalize-space(.)])[2]")));

        if ($date !== null) {
            $this->date = $date;
        }
        $its = [];
        //###FLIGHT###########

        $rule = $this->getRule($this->t('_flights'));

        if ($this->http->XPath->query("//*[self::strong or self::b]/text()[{$rule}]")->length > 0) {
            $xpath = "//*[self::strong or self::b]/text()[{$rule}]";

            $flights = $this->http->XPath->query($xpath);
            $airs = [];

            foreach ($flights as $root) {
                $rl = $this->http->FindSingleNode("./ancestor::table[1]/following-sibling::table[1]//text()[contains(.,'locator:')]", $root, true, "#:\s+([A-Z\d]+)#");
                $airs[$rl][] = $root;
            }

            foreach ($airs as $rl => $roots) {
                $it = ['Kind' => 'T', 'TripSegments' => []];
                $it['RecordLocator'] = $rl;
                $it['Passengers'] = $pax;
                $it['ReservationDate'] = $date;
                $it['TripNumber'] = $tripNum;

                foreach ($roots as $root) {
                    $seg = [];
                    $node = $this->http->FindSingleNode("./ancestor::table[1]//td[1]//text()[1]", $root);
                    $seg['DepDate'] = strtotime($this->normalizeDate($node));

                    $node = implode("\n", $this->http->FindNodes("./ancestor::table[1]//td[2]//text()[normalize-space(.)]", $root));

                    if (preg_match("#Air\s+(.+?)\s*\n#", $node, $m)) {
                        $seg['AirlineName'] = $m[1];
                    }

                    if (preg_match("#From\s*:\s*(.+?)\s*\n#", $node, $m)) {
                        $seg['DepName'] = $m[1];
                    }

                    if (preg_match("#Meal\s*:\s*(.+?)\s*Equip#", $node, $m)) {
                        $seg['Meal'] = $m[1];
                    }

                    if (preg_match("#Equip\s*:\s*(.+?)\s*\n#", $node, $m)) {
                        $seg['Aircraft'] = $m[1];
                    }

                    if (preg_match("#Arrival\s*:\s*(.+?)\s*\n#", $node, $m)) {
                        $seg['ArrDate'] = strtotime($this->normalizeDate($m[1]));
                    }

                    if (preg_match("#Stops\s*:\s*(\d+)#", $node, $m)) {
                        $seg['Stops'] = $m[1];
                    }

                    $node = implode("\n", $this->http->FindNodes("./ancestor::table[1]//td[3]//text()[normalize-space(.)]", $root));

                    if (preg_match("#Flight\#(\d+)#", $node, $m)) {
                        $seg['FlightNumber'] = $m[1];
                    }

                    if (preg_match("#Class\s*:\s*(.+?)\s*\n#", $node, $m)) {
                        $seg['BookingClass'] = $m[1];
                    }

                    if (preg_match("#To\s*:\s*(.+?)\s*\n#", $node, $m)) {
                        $seg['ArrName'] = $m[1];
                    }

                    if (preg_match("#Status\s*:\s*(\w+)#", $node, $m)) {
                        $it['Status'] = $m[1];
                    }

                    $node = implode("\n", $this->http->FindNodes("./ancestor::table[1]/following-sibling::table[1]//text()[normalize-space(.)]", $root));

                    if (preg_match("#OPERATED BY\s*(.+)#i", $node, $m)) {
                        $seg['Operator'] = $m[1];
                    }

                    if (preg_match("#DEPART TERMINAL\s*(.+)#i", $node, $m)) {
                        $seg['DepartureTerminal'] = $m[1];
                    }

                    if (preg_match("#ARRIVE TERMINAL\s*(.+)#i", $node, $m)) {
                        $seg['ArrivalTerminal'] = $m[1];
                    }

                    $node = implode("\n", $this->http->FindNodes("./ancestor::table[1]/following-sibling::table[2]//text()[normalize-space(.)]", $root));

                    if (preg_match("#Arrival\s+([A-Z]{3})\s+#", $node, $m)) {
                        $seg['ArrCode'] = $m[1];
                    } else {
                        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                    if (preg_match("#Arrival\s+(.+?)\s+\d+\/\d+.+?\s+EST\s+local#", $node, $m)) {
                        $seg['ArrName'] = $m[1];
                    }

                    $it['TripSegments'][] = $seg;
                }

                $its[] = $it;
            }
        }
        //###HOTEL###########
        $rule = $this->getRule($this->t('_hotels'));

        if ($this->http->XPath->query("//*[self::strong or self::b]/text()[{$rule}]")->length > 0) {
            //need to develop
            $it = ['Kind' => 'R'];
            $its[] = $it;
        }
        //###CAR###########
        $rule = $this->getRule($this->t('_cars'));

        if ($this->http->XPath->query("//*[self::strong or self::b]/text()[{$rule}]")->length > 0) {
            //need to develop
            $it = ['Kind' => 'L'];
            $its[] = $it;
        }

        return $its;
    }

    private function getRule($fields, $type = 0)
    {
        $str = "";
        $w = (array) $fields;

        switch ($type) {
            case 1: //for XPath return: "contains(normalize-space(.),'value1') or .. or contains(normalize-space(.),'valueN')"
                $str = implode(" or ", array_map(function ($s) {
                    return "contains(normalize-space(.),'{$s}')";
                }, $w));

                break;

            case 2: //for XPath return: "contains(normalize-space(text()),'value1') or .. or contains(normalize-space(text()),'valueN')"
                $str = implode(" or ", array_map(function ($s) {
                    return "contains(normalize-space(text()),'{$s}')";
                }, $w));

                break;

            case 3: //for XPath return: "starts-with(normalize-space(.),'value1') or .. or starts-with(normalize-space(.),'valueN')"
                $str = implode(" or ", array_map(function ($s) {
                    return "starts-with(normalize-space(.),'{$s}')";
                }, $w));

                break;

            case 4: //for XPath return: "starts-with(normalize-space(text()),'value1') or .. or starts-with(normalize-space(text()),'valueN')"
                $str = implode(" or ", array_map(function ($s) {
                    return "starts-with(normalize-space(text()),'{$s}')";
                }, $w));

                break;

            case 5: //for RegExp return: "(?:value1|valie2|..|valueN)"
                $str = "(?:" . implode("|", $w) . ")";

                break;

            default://for XPath return: "normalize-space(.)='value1' or .. or normalize-space(.)='valueN'"
                $str = implode(" or ", array_map(function ($s) {
                    return "normalize-space(.)='{$s}'";
                }, $w));
        }

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
                    if ($this->http->XPath->query('//*[contains(normalize-space(.),"' . $re . '")]')->length > 0) {
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
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
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

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);

        $in = [
            // 02Jul15  12:18pm
            '#^\s*(\d{2})\s*(\w{3})\s*(\d{2})\s+(\d+:\d+\s*[ap]m)\s*$#i',
            //03Aug 05:50am	Monday 12:18pm
            '#^\s*(\d{2})\s*(\w{3})\s+\D*(\d+:\d+\s*[ap]m)\s*$#i',
        ];
        $out = [
            '$1 $2 20$3 $4',
            '$1 $2 ' . $year . ' $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }
}
