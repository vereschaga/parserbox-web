<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Engine\MonthTranslate;

class GuestReceipt extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/it-53810540.eml";
    public $reFrom = ".ihg.com";
    public $reSubject = [
        "en"=> "Guest Receipt for Online Booking at",
    ];
    public $reBody = 'Holiday Inn';
    public $reBody2 = [
        "en" => ["Reference Number:", 'Thank you very much for your interest in the Holiday Inn Munich'],
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
        $it['ConfirmationNumber'] = $this->nextText(["Reference Number:", 'Confirmation No:']);

        // TripNumber
        // ConfirmationNumbers

        // HotelName
        $it['HotelName'] = $this->orval(
            $this->nextText("Property Name:"),
            $this->http->FindSingleNode("(//a[contains(@href, 'holidayinn.com/hotels') and contains(@href, 'hoteldetail')])[last()]")
        );

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText("Arrival:")));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText("Departure:")));

        // Address
        $it['Address'] = $this->orval(
            $this->nextText("Property Address:"),
            implode(', ', $this->http->FindNodes("(//a[contains(@href, 'holidayinn.com/hotels') and contains(@href, 'hoteldetail')])[last()]/ancestor::tr[1]/following-sibling::tr[position() = 1 or position() = 2]"))
        );

        // DetailedAddress

        // Phone
        // Fax
        // GuestNames
        $it['GuestNames'] = [$this->nextText(["Primary Guest Name:", 'Guest Name:'])];

        // Guests
        $it['Guests'] = $this->nextText(["Adults (aged 18+):", 'Pax:'], null, '/(\d+)/');

        // Kids
        // Rooms
        // Rate
        $it['Rate'] = $this->orval(
            implode("\n", $this->http->FindNodes("//text()[" . $this->eq("Room Rates Per Night:") . "]/following::table[1]//tr")),
            $this->nextText('Room rate:')
        );

        // RateType

        // CancellationPolicy
        $it['CancellationPolicy'] = $this->nextText(["Cancel Policy is based on hotel time.", 'CANCELLATION POLICY:']);

        // RoomType
        // RoomTypeDescription
        $it['RoomTypeDescription'] = $this->nextText("Room Description:");

        // Cost
        $it['Cost'] = $this->amount($this->nextText("Room Cost:"));

        // Taxes
        $it['Taxes'] = $this->amount($this->nextText("Tax, Recovery Charges, and Service Fees:"));

        // Total
        $it['Total'] = $this->amount($this->nextText("Total:"));

        // Currency
        $it['Currency'] = $this->currency($this->nextText("Total:"));

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
            foreach ($re as $r) {
                if (strpos($body, $r) !== false) {
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

        foreach ($this->reBody2 as $lang=>$re) {
            if ($this->strpos($this->http->Response["body"], $re) !== false) {
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

    private function orval(...$nodes)
    {
        foreach ($nodes as $node) {
            if (!empty($node)) {
                return $node;
            }
        }

        return null;
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
            "#^(\d+\s+[AP]M)\s+-\s+[^\s\d]+,\s+([^\s\d]+)\s+(\d+),\s+(\d{4})$#", //3 PM - Wednesday, October 18, 2017
        ];
        $out = [
            "$3 $2 $4, $1",
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

    private function nextText($field, $root = null, $re = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root, true, $re);
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

    private function strpos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
