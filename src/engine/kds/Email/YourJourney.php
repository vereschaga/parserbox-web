<?php

namespace AwardWallet\Engine\kds\Email;

use AwardWallet\Engine\MonthTranslate;

class YourJourney extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "@kds.com";
    public $reSubject = [
        "en"=> "Your journey to",
    ];
    public $reBody = 'kds.com';
    public $reBody2 = [
        "en"=> "Hotel Name",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $text = $this->http->Response['body'];
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->re("#Confirmation Number\s*:\s+(\w+)#", $text);

        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = trim($this->re("#Hotel Name\s*:\s+(.+)#", $text));

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Check In\s*:\s+(.+)#", $text)));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Check Out\s*:\s+(.+)#", $text)));

        // Address
        $it['Address'] = trim($this->re("#Address\s*:\s+(.+)#", $text));

        // DetailedAddress

        // Phone
        $it['Phone'] = trim($this->re("#Phone\s*:\s+(.+)#", $text));

        // Fax
        $it['Fax'] = trim($this->re("#Fax\s*:\s+(.+)#", $text));

        // GuestNames
        $it['GuestNames'] = array_filter([$this->re("#Traveller\s*-+[\s-]+([^\n\r]+)#ms", $text)]);

        // Guests
        // Kids
        // Rooms
        // Rate
        // RateType
        // CancellationPolicy
        $it['CancellationPolicy'] = trim(str_replace(["\r", "\n"], '', preg_replace('#^\s*\*\s*#m', ' ', $this->re("#Cancellation policy\s*\n\s*(\*\s+Cancellation.+\n(\s*\*\s+.+\n){1,9})#", $text))));

        // RoomType
        $it['RoomType'] = trim($this->re("#Room\s*:\s+(.+)#", $text));

        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        $it['Total'] = $this->amount($this->re("#Price\s*:\s+(.+)#", $text));

        // Currency
        $it['Currency'] = $this->currency($this->re("#Price\s*:\s+(.+)#", $text));

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status

        if ($this->http->FindSingleNode("//text()[" . $this->contains($this->t("changed")) . "]")) {
            $it['Status'] = 'changed';
        } elseif ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation Number:")) . "]")) {
            $it['Status'] = 'confirmed';
        } elseif ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Cancellation Number:")) . "]")) {
            $it['Status'] = 'cancelled';
        }

        // Cancelled
        if ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Cancellation Number:")) . "]")) {
            $it['Cancelled'] = true;
        }

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
        $body = $parser->getPlainBody();

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

        /*foreach($this->reBody2 as $lang=>$re){
            if(strpos($this->http->Response["body"], $re) !== false){
                return false;
            }
        }*/

        $this->http->setBody($parser->getPlainBody());

        $itineraries = [];

        /*foreach($this->reBody2 as $lang=>$re){
            if(strpos($this->http->Response["body"], $re) !== false){
                $this->lang = $lang;
                break;
            }
        }*/

        $this->parsePlain($itineraries);
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
            "#^\s*(\d+)/(\d+)/(\d{4})\s*$#", //21/06/2017
        ];
        $out = [
            "$1.$2.$3",
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
