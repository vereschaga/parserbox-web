<?php

namespace AwardWallet\Engine\mirage\Email;

class It3885562 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $mailFiles = "mirage/it-3885562.eml";

    public $reSubject = [
        "en"=> "ARIA welcomes you",
    ];

    public $reBody2 = [
        "en"  => "We are pleased to confirm the following room reservation",
        'en2' => 'CONFIRMING YOUR DIGITAL KEY REQUEST',
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private $provDetects = [
        'hhonors' => ['You will receive an alert in the Hilton Honors app on your smart device when your key is ready'],
        'mirage'  => ['the Resort or at any MGM Resorts International'],
    ];

    private static $supportedProviders = ['hhonors', 'mirage'];

    private $date;

    public function detectEmailByHeaders(array $headers)
    {
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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'Flight',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if ($prov = $this->getProvider($parser->getHTMLBody())) {
            $result['providerCode'] = $prov;
        }

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return self::$supportedProviders;
    }

    private function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->getField("Confirmation Number:");

        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        if ($hotelName = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'for choosing')]", null, true, '/for choosing[ ]*(.+) as/')) {
            $it['HotelName'] = $hotelName;
        } elseif ($hotelName = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'for your upcoming stay at the')]", null, true, '/for your upcoming stay at the[ ]*(.+), located/')) {
            $it['HotelName'] = $hotelName;
        }

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->getField("Arrival:") . ', ' . $this->http->FindSingleNode("//text()[contains(., 'Check in time is')]", null, true, "#Check in time is (\d+:\d+ [ap]m)#")));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->getField("Departure:") . ', ' . $this->http->FindSingleNode("//text()[contains(., 'Check in time is')]", null, true, "#check out time is (\d+:\d+ [ap]m)#")));

        // Address
        if ($address = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'for your upcoming stay at the')]", null, true, '/located at (.+)/')) {
            $it['Address'] = $address;
        } else {
            $it['Address'] = $it['HotelName'];
        }

        // DetailedAddress

        // Phone
        if ($phone = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'please call us at')]", null, true, '/please call us at[ ]*\([A-Z]{1,3}\)[ ]*([\d\+\-]+)/')) {
            $it['Phone'] = $phone;
        }
        // Fax
        // GuestNames
        $it['GuestNames'] = [$this->getField("Name:")];

        // Guests
        // Kids
        // Rooms
        $it['Rooms'] = $this->getField("Number of Rooms:");

        // Rate
        // RateType

        // CancellationPolicy
        $it['CancellationPolicy'] = implode(" ", $this->http->FindNodes("//text()[normalize-space(.)][preceding::text()[normalize-space(.)='Cancellation Policy']][following::text()[normalize-space(.)='Terms and Conditions']]"));

        // RoomType
        $it['RoomType'] = $this->getField("Room Type:");

        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        $it['Total'] = $this->cost($this->http->FindSingleNode("//text()[contains(., 'Total:')]", null, true, "#Total:\s*(.+)#"));

        // Currency
        $it['Currency'] = 'USD';

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ReservationDate
        // NoItineraries
        $itineraries[] = $it;
    }

    private function getProvider(string $body): ?string
    {
        foreach ($this->provDetects as $prov => $detects) {
            foreach ($detects as $detect) {
                if (false !== stripos($body, $detect)) {
                    return $prov;
                }
            }
        }

        return null;
    }

    private function getField($field)
    {
        return $this->http->FindSingleNode("//text()[normalize-space(.)=\"{$field}\"]/following::text()[normalize-space(.)][1]");
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
            "#^(\d+)-(\w+)-(\d{4}),\s+(\d+:\d+\s+[ap]m)$#",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
