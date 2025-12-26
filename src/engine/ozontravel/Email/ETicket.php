<?php

namespace AwardWallet\Engine\ozontravel\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "ozontravel/it-10416568.eml, ozontravel/it-11532644.eml, ozontravel/it-37383934.eml, ozontravel/it-8205334.eml";
    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "ru";
    private $reFrom = "mailer@ozon.travel";
    private $reSubject = [
        "ru"=> "Маршрут-квитанция по заказу",
    ];
    private $reBody = 'OZON.travel';
    private $reBody2 = [
        "ru" => "ЭЛЕКТРОННЫЙ БИЛЕТ",
        "ru2"=> "Маршрутная квитанция",
    ];
    private $date;

    public function parseHtml(&$itineraries)
    {
        $refstext = $this->http->FindSingleNode("(//text()[{$this->eq("BOOKING REF / DATE")}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][not({$this->contains($this->t('DEPARTURE'))})]/td[normalize-space()!=''][2]/descendant::text()[normalize-space(.)!=''][1])[1]");

        if (empty($refstext)) {
            $refstext = $this->http->FindSingleNode("(//text()[{$this->eq("НОМЕР БРОНИРОВАНИЯ")}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][1]/td[normalize-space()!=''][2]/descendant::text()[normalize-space(.)!=''][1])[1]");
        }
        $rls = [];
        preg_match_all("#(?<al>\w{2})/(?<rl>[A-Z\d]{6});#", $refstext, $ms, PREG_SET_ORDER);

        if (empty($ms)) {
            preg_match_all("#(?<rl>[A-Z\d]{5,6}) - .*\s(?<al>[A-Z\d]{2})#", $refstext, $ms, PREG_SET_ORDER);
        }

        if (empty($ms)) {
            preg_match_all("#(?<al>[\w ]+) (?<rl>[A-Z\d]{6})(,|$)#", $refstext, $ms, PREG_SET_ORDER);
        }

        foreach ($ms as $m) {
            $rls[trim($m['al'])] = $m['rl'];
        }
        $xpath = "//text()[" . $this->eq("РЕЙС / БАГАЖ") . "]/ancestor::tr[1]/following-sibling::tr[last()]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $key => $root) {
            $airline = $this->http->FindSingleNode("./td[normalize-space()!=''][2]/descendant::text()[normalize-space(.)!=''][1]", $root, true, "#^(\w{2})\s+\d+$#");

            if (isset($rls[$airline])) {
                $airs[$rls[$airline]][] = $root;

                continue;
            }

            foreach ($rls as $al => $rl) {
                if (!empty($this->http->FindSingleNode("./td[normalize-space()!=''][2]//text()[starts-with(normalize-space(.), '{$al}')][1]", $root))) {
                    $airs[$rl][] = $root;

                    continue 2;
                }
            }
            $rlvalue = array_values(array_unique($rls));

            if (count($rlvalue) == 1) {
                $airs[$rlvalue[0]][] = $root;

                continue;
            }
            $this->logger->debug("rl not found");

            return;
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = $this->http->FindNodes("//text()[{$this->eq("ПАССАЖИР / НОМЕР ДОКУМЕНТА")}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][not({$this->contains($this->t('DEPARTURE'))})]/td[normalize-space()!=''][3]/descendant::text()[normalize-space(.)!=''][1]");

            if (empty($it['Passengers'])) {
                $it['Passengers'] = $this->http->FindNodes("//text()[{$this->eq("ПАССАЖИР")}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][1]/td[normalize-space(.)!=''][1]");
            }
            // TicketNumbers
            $it['TicketNumbers'] = array_values(array_filter($this->http->FindNodes("//text()[{$this->eq("НОМЕР БИЛЕТА / ВАЛИДИРУЮЩИЙ ПЕРЕВОЗЧИК")}]/ancestor::tr[1]/following-sibling::tr/td[normalize-space()!=''][1]/descendant::text()[normalize-space(.)!=''][1]", null, "#^(\d+.*)#")));

            if (empty($it['TicketNumbers'])) {
                $it['TicketNumbers'] = $this->http->FindNodes("//text()[{$this->eq("НОМЕР БИЛЕТА И ВАЛИДИРУЮЩИЙ ПЕРЕВОЗЧИК")}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][1]/td[normalize-space()!=''][1]/descendant::text()[normalize-space(.)!=''][1]");
            }
            // AccountNumbers
            // Cancelled
            // TotalCharge
            // BaseFare
            // Currency
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->eq("НОМЕР БРОНИРОВАНИЯ / ДАТА ОФОРМЛЕНИЯ")}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][not({$this->contains($this->t('РЕЙС / БАГАЖ'))})]/td[normalize-space()!=''][2]/descendant::text()[normalize-space(.)!=''][2])[1]")));

            if (empty($it['ReservationDate'])) {
                $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->eq("ДАТА ОФОРМЛЕНИЯ")}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][1]/td[normalize-space(.)!=''][3])[1]")));
            }
            // NoItineraries
            // TripCategory
            $depDates = [];

            foreach ($roots as $root) {
                $depDate = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space()!=''][1]/descendant::text()[normalize-space(.)!=''][1]", $root)));
                $flight = $this->http->FindSingleNode("./td[normalize-space()!=''][2]/descendant::text()[normalize-space(.)!=''][1]", $root);

                if (isset($depDates[$depDate]) && $depDates[$depDate] = $flight) {
                    continue;
                } else {
                    $depDates[$depDate] = $flight;
                }
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[normalize-space()!=''][2]/descendant::text()[normalize-space(.)!=''][1]", $root, true, "#^\w{2}\s+(\d+)$#");

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./td[normalize-space()!=''][1]", $root, true, "#\(([A-Z]{3})\)#");

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./td[normalize-space()!=''][1]/descendant::text()[normalize-space(.)!=''][2]", $root);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[normalize-space()!=''][1]", $root, true, "#(?:Terminal|Терминал)\s*(.+)#");

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space()!=''][1]/descendant::text()[normalize-space(.)!=''][1]", $root)));

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[normalize-space()!=''][3]", $root, true, "#\(([A-Z]{3})\)#");

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./td[normalize-space()!=''][3]/descendant::text()[normalize-space(.)!=''][2]", $root);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[normalize-space()!=''][3]", $root, true, "#(?:Terminal|Терминал)\s*(.+)#");

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space()!=''][3]/descendant::text()[normalize-space(.)!=''][1]", $root)));

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[normalize-space()!=''][2]/descendant::text()[normalize-space(.)!=''][1]", $root, true, "#^(\w{2})\s+\d+$#");

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode("./td[normalize-space()!=''][2]/descendant::text()[normalize-space(.)!=''][2]", $root, true, "#^.*?, (.*?), \w,#");

                // BookingClass
                $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[normalize-space()!=''][2]/descendant::text()[normalize-space(.)!=''][2]", $root, true, "#^.*?, .*?, (\w),#");

                // PendingUpgradeTo
                // Seats
                // Duration
                // Meal
                // Smoking
                // Stops

                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false && stripos($headers["subject"], 'OZON.travel') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parseHtml($itineraries);
        $class = explode('\\', __CLASS__);

        $totals = array_filter($this->nextTexts("Итого:"));
        $total = 0.0;
        $cur = null;

        foreach ($totals as $node) {
            $total += $this->amount($node);

            if (!isset($cur)) {
                $cur = $this->currency($node);
            }
        }
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
                'TotalCharge' => [
                    "Amount"   => $total,
                    "Currency" => $cur,
                ],
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+:\d+),\s+(\d+\s+[^\s\d]+\s+\d{4})$#", //11:55, 4 june 2017
        ];
        $out = [
            "$2, $1",
        ];
        $str = preg_replace($in, $out, $str);
        $str = $this->dateStringToEnglish($str);

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
            'руб'=> 'RUB',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s|\.)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextTexts($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindNodes(".//text()[{$rule}]/following::text()[normalize-space(.)!=''][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
