<?php

namespace AwardWallet\Engine\saudisrabianairlin\Email;

use AwardWallet\Engine\MonthTranslate;

class PaymentReminder extends \TAccountChecker
{
    public $mailFiles = "saudisrabianairlin/it-9143058.eml, saudisrabianairlin/it-9718860.eml";
    public $reFrom = "ibesupport@saudiairlines.com";
    public $reSubject = [
        "ar"=> "تذكير بالدفع",
    ];
    public $reBody = ['saudiairlines.com', 'saudia.com'];
    public $reBody2 = [
        "ar" => ["معلومات رحلة المغادرة"],
    ];

    public static $dictionary = [
        "ar" => [
            'passengers' => ['بالغ', 'بالغين', 'طفل', 'رضيع'],
            'terminal'   => ['الصالة'],
        ],
    ];

    public $lang = "ar";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#^([A-Z\d]{6})\s+-#", $this->nextText("رقم الحجز"));

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("passengers")) . "]/ancestor::tr[1]/following-sibling::tr[2]");

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText("إجمالي السعر"));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->nextText("إجمالي السعر"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->nextText("تاريخ الحجز:")));

        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->eq("الرحلة :") . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $root2 = $this->http->XPath->query("./preceding::table[" . $this->contains("التاريخ :") . "][1]", $root)->item(0);

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[normalize-space(.)='التاريخ :']/ancestor::tr[1]/following-sibling::tr[1]/td[1]", $root2)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[2]/td[3]", $root, true, "#^\w{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='التاريخ :']/ancestor::tr[1]/following-sibling::tr[1]/td[2]", $root2, true, "#\s+([A-Z]{3})$#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./tr[2]/td[1]", $root, true, "#\d+:\d+\s+-\s+(.+)#");

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./tr[2]/td[1]", $root, true, "#(\d+:\d+)\s+-\s+(.+)#"), $date);

            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./tr[3]/td[1]", $root, true, "#{$this->opt($this->t('terminal'))}\s+(\w+)#u");

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='التاريخ :']/ancestor::tr[1]/following-sibling::tr[1]/td[4]", $root2, true, "#\s+([A-Z]{3})$#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[7]/td[1]", $root, true, "#\d+:\d+\s+-\s+(.+)#");

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./tr[7]/td[1]", $root, true, "#(\d+:\d+)\s+-\s+(.+)#"), $date);

            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./tr[8]/td[1]", $root, true, "#{$this->opt($this->t('terminal'))}\s+(\w+)#u");

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./tr[2]/td[3]", $root, true, "#^(\w{2})\d+$#");

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='درجة الخدمة :']/ancestor::tr[1]/following-sibling::tr[1]/td[1]", $root2);

            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode("./tr[11]/td[3]", $root);

            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode("./tr[7]/td[3]", $root);

            // Meal
            // Smoking
            // Stops
            $itsegment['Stops'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='التاريخ :']/ancestor::tr[1]/following-sibling::tr[1]/td[3]", $root2);

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody[0]) === false && strpos($body, $this->reBody[1]) === false) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $detectBody) {
            foreach ($detectBody as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang => $detectBody) {
            foreach ($detectBody as $re) {
                if (stripos($this->http->Response["body"], $re) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
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
        $this->logger->debug($str);

        //$year = date("Y", $this->date);
        $in = [
            "#^(\d+ [^\s\d]+ \d{4})$#", //29 أكتوبر 2017
            "#^[^\s\d]+ (\d+ [^\s\d]+ \d{4})$#", //الأحد 29 أكتوبر 2017
        ];
        $out = [
            "$1",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        $this->logger->debug($str);

        return $str;
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
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
