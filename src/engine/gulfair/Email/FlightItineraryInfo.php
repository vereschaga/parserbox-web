<?php

namespace AwardWallet\Engine\gulfair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;

class FlightItineraryInfo extends \TAccountChecker
{
    public $mailFiles = "gulfair/it-10719200.eml, gulfair/it-10966711.eml, gulfair/it-11037820.eml, gulfair/it-151300492.eml, gulfair/it-151565551.eml, gulfair/it-9053985.eml";
    public $reFrom = "@gulfair.com";
    public $reSubject = [
        "en"=> "Flight Itinerary Info",
        'Travel Itinerary',
        // ar
        "تفاصيل الرحلة",
    ];
    public $reBody = 'gulfair.com';
    public $reBody2 = [
        "en" => "Flight Details",
        "ar" => "تفاصيل الرحلة",
    ];

    public static $dictionary = [
        "en" => [
            "Your reference number is"=> ["Your reference number is", "Your Reservation Number is"],
        ],
        "ar" => [
            "Your reference number is" => "رمز حجزك هو",
            "Departure" => "المغادرة",
//            "Terminal" => "",
            "Operated By" => "تشغل بواسطة",
            "Passenger" => "المسافر",
            "Seat Number:" => "عدد المقاعد:",
            "Passenger ticket number:" => "رقم تذكرة المسافر",
            "Base Price" => "أجرة السفر",
            "Taxes Fees and Carrier Charges" => "الضرائب والرسوم الإضافية",
            "Total" => "الإجمالي",
        ]
    ];

    public $lang = "en";

    public function parseHtml()
    {
        $itineraries = [];

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Your reference number is"));

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->starts($this->t("Passenger")) . "]/ancestor::*[(name()='td' or name()='th') and ./following-sibling::*[1]][1]/following-sibling::*[1]/descendant::text()[normalize-space(.)][1]");

        // TicketNumbers
        $it['TicketNumbers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger ticket number:")) . "]/following::text()[normalize-space(.)][1]");

        // AccountNumber

        // Cancelled

        $total = $this->nextText($this->t("Total"));

        if (preg_match("/(?:^|\+)\s*Miles\s+(\d[\d,.]*)/", $total, $m)) {
            $it['SpentAwards'] = $m[1];
            $total = str_replace($m[0], '', $total);
        }

        // Currency
        $it['Currency'] = $this->currency($total);

        // TotalCharge
        $it['TotalCharge'] = (float)PriceHelper::parse($this->amount($total), $it['Currency']);

        // BaseFare
        $it['BaseFare'] = (float)PriceHelper::parse($this->amount($this->nextText($this->t("Base Price"))), $it['Currency']);

        // Tax
        $it['Tax'] = (float)PriceHelper::parse($this->amount($this->nextText($this->t("Taxes Fees and Carrier Charges"))), $it['Currency']);

        // SpentAwards

        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::tr[count(./*)=5 or (*[4][".$this->contains($this->t('Operated By'))."] and count(./*)=6)][1]";
        $nodes = $this->http->XPath->query($xpath);
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));

        foreach ($nodes as $root) {
            if (!$dateStr = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1][contains(translate(normalize-space(.), '1234567890', 'dddddddddd'), 'dddd')]", $root)) {
                $dateStr = $this->http->FindSingleNode("./ancestor::table[1]/preceding-sibling::*[not(" . $this->contains($this->t("Departure")) . ")]/descendant::text()[normalize-space(.)][last()][contains(translate(normalize-space(.), '1234567890', 'dddddddddd'), 'dddd')]", $root);
            }
            $date = strtotime($this->normalizeDate($dateStr));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./*[3]/descendant::text()[normalize-space(.)][2]", $root, true, "#^\w{2}\s*(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#\(([A-Z]{3})\)$#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#(.*?) \([A-Z]{3}\)$#");

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./*[5]//text()[" . $this->contains($this->t("Terminal")) . "]", $root, true, "#" . $this->opt($this->t("Terminal")) . " (.+)#");

            // DepDate
            $depDate = $date;
            $nextday = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)][3]", $root, true, "/^\s*\(\s*([-+]\d)\s*\)\s*$/");
            if (!empty($nextday)) {
                $depDate = strtotime($nextday . ' day', $depDate);
            }
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)][2]", $root)), $depDate);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./*[2]/descendant::text()[normalize-space(.)][last()]", $root, true, "#\(([A-Z]{3})\)$#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./*[2]/descendant::text()[normalize-space(.)][last()]", $root, true, "#(.*?) \([A-Z]{3}\)$#");

            // ArrivalTerminal
            // ArrDate
            $arrDate = $date;
            $nextday = $this->http->FindSingleNode("./*[2]/descendant::text()[normalize-space(.)][3]", $root, true, "/^\s*\(\s*([-+]\d)\s*\)\s*$/");
            if (!empty($nextday)) {
                $arrDate = strtotime($nextday . ' day', $arrDate);
            }
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./*[2]/descendant::text()[normalize-space(.)][2]", $root)), $arrDate);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./*[3]/descendant::text()[normalize-space(.)][2]", $root, true, "#^(\w{2})\s*\d+$#");

            // Operator
            $operator = $this->http->FindSingleNode("./*[4][descendant::text()[normalize-space(.)][1][".$this->contains($this->t('Operated By'))."]]/descendant::text()[normalize-space(.)][2]", $root);
            if ($operator) {
                $itsegment['Operator'] = $operator;
            }
            // Aircraft
            $itsegment['Aircraft'] = $this->http->FindSingleNode("./*[last()]//text()[" . $this->contains(["Airbus", "Boeing"]) . "]", $root, true, "#(?:Airbus|Boeing) .+#");

            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./*[last()-1]/descendant::text()[normalize-space(.)][2]", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            if (!empty($itsegment['DepCode']) && !empty($itsegment['ArrCode'])) {
                $seats = array_filter($this->http->FindNodes("//text()[".$this->eq($this->t("Seat Number:"))."]/following::text()[normalize-space(.)][1]",
                    null, "/^\s*(\d{1,3}[A-Z])\s*\(\s*{$itsegment['DepCode']}\s*-\s*{$itsegment['ArrCode']}\s*\)\s*/"));
                if (!empty($seats)) {
                    $itsegment['Seats'] = $seats;
                }
            }
            // Duration
            // Meal
            // Smoking
            // Stops

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;

        return $itineraries;
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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseHtml(),
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
        $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+ (\d+ [^\s\d]+ \d{4})$#", //Saturday 20 Jan 2018
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

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
        return $this->re("/^\s*\D*(\d[\d\., ]*?)\s*\D*$/", $s);
    }

    private function currency($s)
    {
        $s = trim(preg_replace("/\d[\d., ]*?/", '', $s));
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=> $r) {
            if ($s === $f) {
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

        return '(?:' . implode('|', array_map(function ($s) {
                return preg_quote($s, '/');
            }, $field)) . ')';
    }
}
