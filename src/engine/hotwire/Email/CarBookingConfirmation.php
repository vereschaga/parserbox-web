<?php

namespace AwardWallet\Engine\hotwire\Email;

class CarBookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "hotwire/it-1699992.eml, hotwire/it-2221055.eml, hotwire/it-6161372.eml, hotwire/it-6193083.eml";
    public $reFrom = "@hotwire.com";
    public $reSubject = [
        "en"=> "Hotwire Car Booking Confirmation",
    ];
    public $reBody = 'Hotwire';
    public $reBody2 = [
        "en"=> "Your car",
    ];

    public static $dictionary = [
        "en" => [
            "Trip total"    => ["Trip total", "Estimated trip total"],
            "Taxes and fees"=> ["Taxes and fees", "Estimated taxes and fees"],
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "L";

        // Number
        $it['Number'] = $this->nextText("Hotwire itinerary");
        // TripNumber
        // PickupDatetime
        $it['PickupDatetime'] = strtotime($this->normalizeDate($this->nextText("Pick up")));

        // PickupLocation
        $it['PickupLocation'] = implode(" ", $this->http->FindNodes("//text()[" . $this->eq($this->t("View map")) . "]/ancestor::tr[1]/preceding-sibling::tr[1]/td[2]/descendant::text()[normalize-space(.)][position()>1]"));

        // DropoffDatetime
        $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->nextText("Drop off")));

        // DropoffLocation
        $it['DropoffLocation'] = $it['PickupLocation'];

        // PickupPhone
        $it['PickupPhone'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("View map")) . "]/preceding::text()[string-length(normalize-space(.))>1][1]");

        // PickupFax
        // PickupHours
        // DropoffPhone
        $it['DropoffPhone'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("View map")) . "]/preceding::text()[string-length(normalize-space(.))>1][1]");

        // DropoffHours
        // DropoffFax
        // RentalCompany
        $it['RentalCompany'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("View map")) . "]/ancestor::tr[1]/preceding-sibling::tr[1]/td[3]//img/@alt");

        // CarType
        $it['CarType'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("View map")) . "]/ancestor::tr[1]/preceding-sibling::tr[1]/td[2]/descendant::text()[normalize-space(.)][1]");

        // CarModel
        // CarImageUrl
        // RenterName
        $it['RenterName'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Billed to']/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

        // PromoCode
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->re("#([\d\,\.]+)#", $this->nextText($this->t("Trip total"))));

        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t("Trip total")));

        // TotalTaxAmount
        $it['TotalTaxAmount'] = $this->amount($this->re("#([\d\,\.]+)#", $this->nextText($this->t("Taxes and fees"))));

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
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space(.)='Billed to']/ancestor::tr[1]/following-sibling::tr[1]/td[3]")));

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
        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
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
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+[AP]M)$#", //Sun, Mar 18, 2017, 4:30PM
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#", //Fri, Feb 17, 2017
        ];
        $out = [
            "$2 $1 $3, $4",
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
