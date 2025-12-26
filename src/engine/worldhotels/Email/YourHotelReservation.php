<?php

namespace AwardWallet\Engine\worldhotels\Email;

use AwardWallet\Engine\MonthTranslate;

class YourHotelReservation extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "info@worldhotels.com";
    public $reSubject = [
        "Your hotel reservation at Worldhotels",
        "Your reservation at",
    ];
    public $reBody = 'worldhotels.com';
    public $reBody2 = [
        "de"=> "Anreisedatum:",
        "en"=> "Departure Date:",
    ];

    public static $dictionary = [
        "de" => [
            //			'Reservierungsnummer:' => '',
            //			'Adresse:' => '',
            //			'Anreisedatum:' => '',
            //			'Abreisedatum:' => '',
            //			'Telefonnummer:' => '',
            //			'Vorname' => '',
            //			'Nachname' => '',
            //			'Anzahl Erwachsene:' => '',
            //			'Stornierungsrichtlinie:' => '',
            //			'Zimmerbeschreibung:' => '',
            //			'Gesamt' => '',
        ],
        "en" => [
            'Reservierungsnummer:'    => 'Reservation Number:',
            'Adresse:'                => 'Address:',
            'Anreisedatum:'           => 'Arrival Date:',
            'Anreise ab:'             => 'Check-in Time:',
            'Abreisedatum:'           => 'Departure Date:',
            'Abreise bis:'            => 'Check-out Time:',
            'Telefonnummer:'          => 'Phone Number:',
            'Vorname'                 => 'First Name',
            'Nachname'                => 'Last Name',
            'Anzahl Erwachsene:'      => 'Number of Adults:',
            'Stornierungsrichtlinie:' => 'Cancellation policy:',
            'Zimmerbeschreibung:'     => 'Room Description:',
            'Gesamt'                  => 'Total',
        ],
    ];

    public $lang = "de";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->nextText($this->t("Reservierungsnummer:"));

        // TripNumber
        // ConfirmationNumbers

        // HotelName
        $it['HotelName'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Adresse:")) . "]/preceding::text()[normalize-space(.)][1]");

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Anreisedatum:")) . ', ' . $this->nextText($this->t("Anreise ab:"))));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Abreisedatum:")) . ', ' . $this->nextText($this->t("Abreise bis:"))));

        // Address
        $it['Address'] = $this->nextText($this->t("Adresse:"));

        // DetailedAddress

        // Phone
        $it['Phone'] = $this->nextText($this->t("Telefonnummer:"));

        // Fax
        // GuestNames
        $it['GuestNames'] = [$this->nextText($this->t("Vorname")) . ' ' . $this->nextText($this->t("Nachname"))];

        // Guests
        $it['Guests'] = $this->nextText($this->t("Anzahl Erwachsene:"));

        // Kids
        // Rooms
        // Rate
        // RateType

        // CancellationPolicy
        $it['CancellationPolicy'] = $this->nextText($this->t("Stornierungsrichtlinie:"));

        // RoomType
        $it['RoomType'] = $this->nextText($this->t("Zimmerbeschreibung:"));

        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        $it['Total'] = $this->amount($this->nextText($this->t("Gesamt")));

        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t("Gesamt")));

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
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false) {
            foreach ($this->reSubject as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
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
            "#^(\d+\.\d+\.\d{4},\s+\d+:\d+)$#", //06.10.2017, 15:00
        ];
        $out = [
            "$1",
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
