<?php

namespace AwardWallet\Engine\hotwire\Email;

class HotelPurchaseConfirmation extends \TAccountChecker
{
    public $mailFiles = "hotwire/it-1.eml, hotwire/it-2.eml, hotwire/it-2514185.eml, hotwire/it-3.eml, hotwire/it-7.eml";
    public $reFrom = "support@hotwire.com";
    public $reSubject = [
        "en"=> "Hotwire Hotel Purchase Confirmation",
    ];
    public $reBody = 'Hotwire';
    public $reBody2 = [
        "en"=> "Check-in",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[" . $this->contains("confirmation code:") . "]/following::text()[normalize-space(.)][1]", null, true, "#^(\w+)\s+#");

        // TripNumber
        // ConfirmationNumbers

        // HotelName
        $it['HotelName'] = $this->http->FindSingleNode("//text()[" . $this->contains("confirmation code:") . "]", null, true, "#(.*?)\s+confirmation code:#");

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Check-in"))));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Check-out"))));

        // Address
        $it['Address'] = implode(" ", $this->http->FindNodes("//a[contains(@href, '/account/purchase-details-map-http.jsp') or contains(@href, '/confirmation/map?')]/ancestor::td[1]/descendant::text()[normalize-space(.)][position()=1 or position()=2]"));

        // DetailedAddress

        // Phone
        $it['Phone'] = $this->http->FindSingleNode("//a[contains(@href, '/account/purchase-details-map-http.jsp') or contains(@href, '/confirmation/map?')]/ancestor::td[1]/descendant::text()[normalize-space(.)][3]");

        // Fax
        // GuestNames
        $it['GuestNames'] = array_filter([$this->nextText($this->t("Primary guest"))]);

        // Guests
        $it['Guests'] = $this->http->FindSingleNode("//text()[" . $this->eq("Adults") . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

        // Kids
        $it['Kids'] = $this->http->FindSingleNode("//text()[" . $this->eq("Children") . "]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

        // Rooms
        $it['Rooms'] = $this->nextText($this->t("Rooms"));

        // Rate
        $it['Rate'] = $this->nextText($this->t("Rate per night:"));

        // RateType
        // CancellationPolicy
        if (!$it['CancellationPolicy'] = $this->http->FindSingleNode("//td[" . $this->eq("Hotel cancellation policy:") . "]/following-sibling::td[1]")) {
            $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Hotel cancellation policy:']/following::table[1]");
        }

        // RoomType
        // RoomTypeDescription
        // RoomTypeDescription
        // Cost
        // Taxes
        $it['Taxes'] = $this->amount($this->re("#([\d\,\.]+)#", $this->nextText($this->t("Tax recovery charges & fees:"))));

        // Total
        $it['Total'] = $this->amount($this->re("#([\d\,\.]+)#", $this->nextText($this->t("Trip total:"))));

        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t("Trip total:")));

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        $it['Status'] = $this->http->FindSingleNode("//text()[" . $this->contains("confirmation code:") . "]/following::text()[normalize-space(.)][1]", null, true, "#Your hotel booking is ([^\d\s\.]+)#");

        // Cancelled
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
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+\s+[AP]M)$#", //Tue, May 15, 2012, 3:00 PM
        ];
        $out = [
            "$2 $1 $3, $4",
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
