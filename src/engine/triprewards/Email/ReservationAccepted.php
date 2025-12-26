<?php

namespace AwardWallet\Engine\triprewards\Email;

use AwardWallet\Engine\MonthTranslate;

class ReservationAccepted extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "@visitmadison.com";
    public $reSubject = [
        "en"=> "Reservation has been ACCEPTED",
    ];
    public $reBody = 'visitmadison.com';
    public $reBody2 = [
        "en"=> "Room and Guest Details",
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
        $it['ConfirmationNumber'] = $this->nextText("Hotel Confirmation Number:");

        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->nextText("Hotel:");

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq("Check-In") . "]/following::text()[string-length(normalize-space(.))>1][1]")));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq("Check-Out") . "]/following::text()[string-length(normalize-space(.))>1][1]")));

        // Address
        $it['Address'] = implode(", ", $this->http->FindNodes("//text()[" . $this->eq("Hotel:") . "]/following::text()[normalize-space(.)][position()=2 or position()=3]"));

        // DetailedAddress

        // Phone
        // Fax
        // GuestNames
        $it['GuestNames'] = $this->http->FindNodes("//text()[" . $this->eq("Guest") . "]/following::text()[string-length(normalize-space(.))>1][1]", null, "#\d+:\s+(.+)#");

        // Guests
        // Kids
        // Rooms
        // Rate
        $it['Rate'] = implode("\n", $this->http->FindNodes("//text()[" . $this->eq("Rate per Night:") . "]/following::text()[normalize-space(.) and ./following::text()[" . $this->eq("Room Subtotal:") . "]]"));

        // RateType

        // CancellationPolicy
        $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[" . $this->contains("CANCELLATION POLICY:") . "]/following::em[1]");

        // RoomType
        $it['RoomType'] = trim($this->nextText("Room Type"), ': ');

        // RoomTypeDescription
        // Cost
        $it['Cost'] = $this->amount($this->nextText("Room Subtotal:"));

        // Taxes
        $it['Taxes'] = $this->amount($this->http->FindSingleNode("//text()[" . $this->contains("Taxes:") . "]/following::text()[normalize-space(.)][1]"));

        // Total
        $it['Total'] = $this->amount($this->nextText("Total Amount Due:"));

        // Currency
        $it['Currency'] = $this->amount($this->nextText("Total Amount Due:"));

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)-([^\s\d]+)-(\d{4})$#", //24-Sep-2017
        ];
        $out = [
            "$1 $2 $3",
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
