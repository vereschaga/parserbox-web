<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Engine\MonthTranslate;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "stay@indigopaddington.com";
    public $reSubject = [
        "en"=> "Hotel Indigo London Paddington Booking Confirmation",
    ];
    public $reBody = 'Hotel Indigo';
    public $reBody2 = [
        "en"=> "Booking Details",
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
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[" . $this->starts("Reference:") . "]", null, true, "#Reference:\s+(.+)#");

        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->http->FindSingleNode("//text()[" . $this->starts("Reference:") . "]/following::text()[string-length(normalize-space(.))>1][1]");

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts("ROOM DETAILS") . "]/following::text()[normalize-space(.)][2]", null, true, "#(.*?)\s+-\s+#")));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts("ROOM DETAILS") . "]/following::text()[normalize-space(.)][2]", null, true, "#\s+-\s+(.+)#")));

        // Address
        $it['Address'] = $this->http->FindSingleNode("//text()[" . $this->starts("Reference:") . "]/following::text()[string-length(normalize-space(.))>1][2]");

        // DetailedAddress

        // Phone
        $it['Phone'] = $this->http->FindSingleNode("//text()[" . $this->starts("Reference:") . "]/following::text()[string-length(normalize-space(.))>1][1]/ancestor::td[1]/descendant::text()[normalize-space(.)][3]");

        // Fax
        // GuestNames
        $it['GuestNames'] = explode(", ", $this->nextText("Guest Names:"));

        // Guests
        $it['Guests'] = $this->nextText("Number of Guests:");

        // Kids
        // Rooms
        $it['Rooms'] = $this->re("#(\d+)\s+Room#", $this->nextText("ROOMS"));

        // Rate
        // RateType
        // CancellationPolicy
        $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[" . $this->starts("Cancellation Policy:") . "]", null, true, "#Cancellation Policy:\s+(.+)#");

        // RoomType
        $it['RoomType'] = $this->http->FindSingleNode("//text()[" . $this->starts("ROOM DETAILS") . "]/following::text()[normalize-space(.)][1]");

        // RoomTypeDescription
        // Cost
        $it['Cost'] = $this->amount($this->nextText("ROOMS", null, 2));

        // Taxes
        // Total
        $it['Total'] = $this->amount($this->nextText("Total"));

        // Currency
        $it['Currency'] = $this->currency($this->nextText("Total"));

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->nextText("Booking Date:")));

        // NoItineraries
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

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
        $this->http->setBody($parser->getHTMLBody());
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
            "#^(\d+:\d+)\s+(\d+)/(\d+)/(\d{2})$#", //14:00 29/8/17
        ];
        $out = [
            "$3/$2/20$4, $1",
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
