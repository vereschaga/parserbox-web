<?php

namespace AwardWallet\Engine\ticketone\Email;

use AwardWallet\Engine\MonthTranslate;

class PurchaseOrderConfirmation extends \TAccountChecker
{
    public $mailFiles = "ticketone/it-11544728.eml, ticketone/it-11563054.eml, ticketone/it-11615704.eml, ticketone/it-35334582.eml";
    public $reFrom = "@ticketone.it";
    public $reSubject = [
        "en" => "Purchase Order Confirmation",
        "en2"=> "TicketOne Sport - order confirmation",
        "it" => "TicketOne Sport - Conferma d'ordine",
    ];
    public $reBody = 'TicketOne';
    public $reBody2 = [
        "en" => "The delivery address for this order is the same as your invoice address",
        "en2"=> "TicketOne is pleased to provide you the details of the order",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private $text;

    private $date;

    public function parsePlain()
    {
        $itineraries = [];
        $text = $this->text;
        $text = preg_replace("#\n[ >]+#", "\n", $text);
        $it = [];

        $it['Kind'] = "E";

        // ConfNo
        $it['ConfNo'] = $this->re("#Your order ID is:\s+\*?(\d+)\*?#", $text);

        // TripNumber
        if (preg_match("#\n(?<Name>\S[^\n]+)[\n\r]+\*?Date[ ]*\/[ ]*Time:\*?\s+(?<Date>[^\n]+)\n\s*\*?Place:\*?\s+(?<Address>[^\n]+)#", $text, $m)) {
            // Name
            $it['Name'] = trim($m['Name']);
            // StartDate
            $it['StartDate'] = strtotime($this->normalizeDate(trim($m['Date'])));
            // EndDate
            // Address
            $it['Address'] = trim($m['Address']);
        }
        // Phone
        // DinerName
        $it['DinerName'] = $this->re("#\*?Your address:\*?\s+(.+)#i", $text);

        // Guests
        $it['Guests'] = $this->re("#(\d+) Presale#", $text);

        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->re("#\*?Total \(VAT included\):\*?\s+(.+)#", $text));

        // Currency
        $it['Currency'] = $this->currency($this->re("#\*?Total \(VAT included\):\*?\s+(.+)#", $text));

        // Tax
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ReservationDate
        // NoItineraries
        // EventType
        $it['EventType'] = EVENT_SHOW;

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
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() . $parser->getPlainBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $this->text = $parser->getPlainBody();

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parsePlain(),
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+, (\d+)/(\d+)/(\d{2}) (\d+:\d+ [AP]M)$#", //Sun, 4/10/16 12:00 AM
            "#^[^\s\d]+, (\d+)/(\d+)/(\d{4}) (\d+)\.(\d+)$#", //Saturday, 30/03/2019 18.00
        ];
        $out = [
            "$2.$1.20$3, $4",
            "$1.$2.$3, $4:$5",
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
