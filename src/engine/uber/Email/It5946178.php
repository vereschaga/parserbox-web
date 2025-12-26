<?php

namespace AwardWallet\Engine\uber\Email;

class It5946178 extends \TAccountChecker
{
    public $mailFiles = "uber/it-11597631.eml, uber/it-1836100.eml, uber/it-2863148.eml, uber/it-2863149.eml";
    public $reFrom = "@uber.com";
    public $reSubject = [
        "en"=> "trip with Uber",
    ];
    public $reBody = 'Uber';
    public $reBody2 = [
        "en"=> "You rode with",
    ];

    public static $dictionary = [
        "en" => [
            "CAR"=> ["CAR", "Vehicle"],
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $date = strtotime($this->normalizeDate($this->http->FindSingleNode("(//img)[1]/following::text()[normalize-space(.)][1]")));
        $it = [];

        $it['Kind'] = "T";
        $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;

        // Number
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['Passengers'] = [$this->http->FindSingleNode('//text()[contains(normalize-space(.), "Thanks for choosing Uber,")]', null, true, '/Thanks for choosing Uber, (\S+)\b/')];
        $segment = [];
        $segment['DepDate'] = strtotime($this->http->FindSingleNode("//text()[" . $this->eq($this->t("CAR")) . "]/ancestor::tr[./preceding-sibling::tr][1]/preceding-sibling::tr[1]//tr[2]/../tr[1]/descendant::text()[normalize-space(.)][1]"), $date);
        $segment['ArrDate'] = strtotime($this->http->FindSingleNode("//text()[" . $this->eq($this->t("CAR")) . "]/ancestor::tr[./preceding-sibling::tr][1]/preceding-sibling::tr[1]//tr[2]/descendant::text()[normalize-space(.)][1]"), $date);
        $location = $segment['DepName'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("CAR")) . "]/ancestor::tr[./preceding-sibling::tr][1]/preceding-sibling::tr[1]//tr[2]/../tr[1]/descendant::text()[normalize-space(.)][2]");
        $segment['ArrName'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("CAR")) . "]/ancestor::tr[./preceding-sibling::tr][1]/preceding-sibling::tr[1]//tr[2]/descendant::text()[normalize-space(.)][2]");

        if (count(array_filter(array_map('trim', $segment))) === 4) {
            $segment['DepCode'] = $segment['ArrCode'] = TRIP_CODE_UNKNOWN;
            $segment['Vehicle'] = $this->nextText($this->t("CAR"));
            $it['TripSegments'] = [$segment];
        } else {
            $this->http->Log('Invalid array parsed');
        }

        // PromoCode
        // TotalCharge
        $it["TotalCharge"] = $this->amount($this->re("#([\d\,\.]+)#", $this->http->FindSingleNode("//text()[" . $this->eq($this->t("CHARGED")) . "]/ancestor::td[1]/following-sibling::td[1]")));

        // Currency
        $it["Currency"] = $this->currency($this->http->FindSingleNode("//text()[" . $this->eq($this->t("CHARGED")) . "]/ancestor::td[1]/following-sibling::td[1]"));
        $it["Currency"] = $this->fixCurrencyByAddress($it["Currency"], $location);
        // TotalTaxAmount
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // ServiceLevel
        // Cancelled
        // PricedEquips
        // Discount
        // Discounts
        // Fees
        // ReservationDate
        // NoItineraries
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
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
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})$#",
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function fixCurrencyByAddress($cur, $address)
    {
        $a = [
            'USD' => [
                "Australia"   => 'AUD',
                "Canada"      => 'CAD',
                "Singapore"   => 'SGD',
                "Hong Kong"   => 'AUD',
                "New Zealand" => 'NZD',
                "Taiwan"      => 'TWD',
            ],
        ];

        if (isset($a[$cur])) {
            foreach ($a[$cur] as $find=>$newcur) {
                if (stripos($address, $find) !== false) {
                    return $newcur;
                }
            }
        }

        return $cur;
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
}
