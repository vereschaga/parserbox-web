<?php

namespace AwardWallet\Engine\rentacar\Email;

use AwardWallet\Engine\MonthTranslate;

class BookingRequest extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "enterprise@ehi.com";
    public $reSubject = [
        "en"=> "Enterprise Rent-A-Car: Booking Request",
    ];
    public $reBody = 'Enterprise Rent-A-Car';
    public $reBody2 = [
        "en"=> "Reservation Details:",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "L";

        // Number
        $it['Number'] = $this->nextText($this->t("Reference:"));
        // TripNumber
        // PickupDatetime
        $it['PickupDatetime'] = strtotime($this->normalizeDate($this->nextText($this->t("Rental Start:"))));

        // PickupLocation
        $it['PickupLocation'] = implode(", ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Rental Start:")) . "]/following::text()[normalize-space(.)][position()>1 and position()<5]"));

        // DropoffDatetime
        $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->nextText($this->t("Rental End:"))));

        // DropoffLocation
        $it['DropoffLocation'] = implode(", ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Rental End:")) . "]/following::text()[normalize-space(.)][position()>1 and position()<5]"));

        // PickupPhone
        $it['PickupPhone'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Tel:")) . "]", null, true, "#Tel:\s+(.+)#");

        // PickupFax
        // PickupHours
        // DropoffPhone
        // DropoffHours
        // DropoffFax
        // RentalCompany
        // CarType
        $it['CarType'] = $this->re("#(.*?)\s+\(#", $this->nextText($this->t("Vehicle Class:")));

        // CarModel
        $it['CarModel'] = $this->re("#\((.*?)\)#", $this->nextText($this->t("Vehicle Class:")));

        // CarImageUrl
        // RenterName
        $it['RenterName'] = $this->nextText($this->t("Driver Name:"));

        // PromoCode
        // TotalCharge
        // Currency
        // TotalTaxAmount
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ServiceLevel

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

        $result = [
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)/(\d+)/(\d{4})\s+at\s+(\d+:\d+)\s+\(.*?\)$#", //31/07/2017 at 22:00 (Airport)
        ];
        $out = [
            "$1.$2.$3, $4",
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
}
