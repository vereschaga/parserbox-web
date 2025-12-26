<?php

namespace AwardWallet\Engine\spg\Email;

use AwardWallet\Engine\MonthTranslate;

class YourReservationNumber extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "@CONFIRM.STARWOODHOTELS.COM";
    public $reSubject = [
        "es"=> "Su Número de reserva en Four Points",
    ];
    public $reBody = 'Starwood';
    public $reBody2 = [
        "es"=> "Su reserva:",
    ];

    public static $dictionary = [
        "es" => [],
    ];

    public $lang = "es";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->nextText("Confirmación:");

        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->http->FindSingleNode("(//a[contains(@href, '/fourpoints/property/overview') and normalize-space(.)])[1]");

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText("Llegada")));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText("Salida")));

        // Address
        $it['Address'] = implode(", ", $this->http->FindNodes("(//a[contains(@href, '/fourpoints/property/overview') and normalize-space(.)])[1]/ancestor::tr[1]/following-sibling::tr[position()<3]"));

        // DetailedAddress

        // Phone
        $it['Phone'] = trim($this->http->FindSingleNode("//text()[" . $this->contains("Teléfono:") . "]/ancestor::tr[1]", null, true, "#Teléfono:\s+([\d\(\)\s]+)#"));

        // Fax
        $it['Fax'] = trim($this->http->FindSingleNode("//text()[" . $this->contains("Fax:") . "]/ancestor::tr[1]", null, true, "#Fax:\s+([\d\(\)\s]+)#"));

        // GuestNames
        $it['GuestNames'] = [$this->nextText("Nombre del Huésped")];

        // Guests
        $it['Guests'] = $this->nextText("Número de Adultos");

        // Kids
        $it['Kids'] = $this->nextText("Número de Niños");

        // Rooms
        $it['Rooms'] = $this->nextText("Número de Habitaciones");

        // Rate
        // RateType

        // CancellationPolicy
        $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[" . $this->eq("Reglas de Garantía y Política de Cancelación") . "]/ancestor::tr[1]/following-sibling::tr[1]");

        // RoomType
        $it['RoomType'] = $this->re("#(.*?):#", $this->nextText("Descripción de la Habitación"));

        // RoomTypeDescription
        $it['RoomTypeDescription'] = $this->re("#:\s+(.+)#", $this->nextText("Descripción de la Habitación"));

        // Cost
        $it['Cost'] = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq("Tarifa de Habitación") . "]/ancestor::td[1]/following-sibling::td[last()]"));

        // Taxes
        // Total
        $it['Total'] = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq("Total Estimativo*:") . "]/ancestor::td[1]/following-sibling::td[last()]"));

        // Currency
        $it['Currency'] = $this->currency($this->http->FindSingleNode("//text()[" . $this->eq("Total Estimativo*:") . "]/ancestor::td[1]/following-sibling::td[last()]"));

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
        if (!isset($headers['subject'],$headers['from'])) {
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
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);
        $name = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($name) . ucfirst($this->lang),
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
            "#^(\d+)-([^\s\d]+)-(\d{4})\s+-\s+(\d+:\d+)\s+\*$#", //27-AGO-2017 - 15:00 *
        ];
        $out = [
            "$1 $2 $3, $4",
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
