<?php

namespace AwardWallet\Engine\premierinn\Email;

use AwardWallet\Engine\MonthTranslate;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "premierinn";
    public $reSubject = [
        "en"=> "Booking confirmation hotel Premier Inn",
    ];
    public $reBody = 'Premier Inn';
    public $reBody2 = [
        "de"=> "Hotelangaben",
    ];

    public static $dictionary = [
        "de" => [],
    ];

    public $lang = "de";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->nextText("Ihre Buchungsnummer");

        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->http->FindSingleNode("//text()[" . $this->eq("Anreise:") . "]/ancestor::tr[1]/preceding::text()[string-length(normalize-space(.))>1][1]");

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText("Anreise:")));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText("Abreise:")));

        // Address
        $it['Address'] = $this->http->FindSingleNode("//text()[" . $this->eq("Anreise:") . "]/ancestor::tr[1]", null, true, "#(.*?)\s+\(Anfahrtsbeschreibung siehe Karte unten\)#");

        // DetailedAddress

        // Phone
        $it['Phone'] = $this->http->FindSingleNode("//text()[" . $this->contains("Tel:") . "]", null, true, "#Tel:\s+(.+)#");

        // Fax
        // GuestNames
        $it['GuestNames'] = [$this->nextText("Ansprechpartner")];

        // Guests
        $it['Guests'] = $this->http->FindSingleNode("//text()[" . $this->contains("Gäste:") . "]", null, true, "#(\d+)\s+Erwachsene#");

        // Kids
        // Rooms
        // Rate
        $it['Rate'] = $this->nextText("Zimmerpreis:");

        // RateType
        // CancellationPolicy
        // RoomType
        $it['RoomType'] = $this->http->FindSingleNode("//text()[" . $this->contains("Zimmerart:") . "]", null, true, "#Zimmerart:\s*(.+)#");

        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        $it['Total'] = $this->amount($this->http->FindSingleNode("(//text()[" . $this->contains("Gesamtpreis") . "])[last()]", null, true, "#Gesamtpreis\s*(.+)#"));

        // Currency
        $it['Currency'] = $this->currency($this->http->FindSingleNode("(//text()[" . $this->contains("Gesamtpreis") . "])[last()]", null, true, "#Gesamtpreis\s*(.+)#"));

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
            "#^(?:Nach|Check-out bis)\s+(\d+:\d+)\s+am\s+(\d+\.\d+\.\d{4})$#", //Nach 14:00 am 31.07.2017
            "#^(?:Nach|Check-out bis)\s+Mittag\s+am\s+(\d+\.\d+\.\d{4})$#", //Check-out bis Mittag am 01.08.2017
        ];
        $out = [
            "$2, $1",
            "$1, 12:00",
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
