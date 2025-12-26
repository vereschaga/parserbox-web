<?php

namespace AwardWallet\Engine\kimpton\Email;

class It5869611 extends \TAccountChecker
{
    public $reFrom = "@kimptonhotels.com";
    public $reSubject = [
        "en"=> "Reservation Confirmation:",
    ];
    public $reBody = 'Kimpton';
    public $reBody2 = [
        "en"=> "Your Confirmation Number:",
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
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[contains(., 'Your Confirmation Number:')]", null, true, "#Your Confirmation Number:\s+(\S+)#");

        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->nextText("Hotel name:");

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText("Your Arrival Date:")));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText("Your Departure Date:")));

        // Address
        $it['Address'] = $this->nextText("Hotel address:");

        // DetailedAddress

        // Phone
        $it['Phone'] = $this->re("#([\d-]+)\s+ph#", $this->nextText("Phone/Fax number:"));

        // Fax
        $it['Fax'] = $this->re("#([\d-]+)\s+fax#", $this->nextText("Phone/Fax number:"));

        // GuestNames
        // Guests
        $it['Guests'] = $this->re("#(\d+)/#", $this->nextText("Adults/Children:"));

        // Kids
        $it['Kids'] = $this->re("#/(\d+)#", $this->nextText("Adults/Children:"));

        // Rooms
        $it['Rooms'] = $this->nextText("Rooms:");

        // Rate
        $it['Rate'] = $this->nextText("Average nightly rate:");

        // RateType
        $it['RateType'] = $this->nextText("Your rate:");

        // CancellationPolicy
        $it['CancellationPolicy'] = $this->nextText("Policy Information:");

        // RoomType
        $it['RoomType'] = $this->nextText("Your room type:");

        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        $it['Total'] = $this->amount($this->re("#[A-Z]{3}\s+([\d\,\.]+)#", $this->nextText("Approximate total charges:")));

        // Currency
        $it['Currency'] = $this->re("#([A-Z]{3})\s+[\d\,\.]+#", $this->nextText("Approximate total charges:"));

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
            if (strpos($headers["subject"], $re) !== false) {
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

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
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

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
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
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#",
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
}
