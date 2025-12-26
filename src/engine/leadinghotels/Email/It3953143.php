<?php

namespace AwardWallet\Engine\leadinghotels\Email;

class It3953143 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "leadinghotels/it-3953143.eml";

    public $reFrom = "reservations@thegreenwichhotel.com";
    public $reSubject = [
        "en"=> "The Greenwich Cancellation",
    ];
    public $reBody = 'www.lhw.com';
    public $reBody2 = [
        "en"=> "Reservation Number:",
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
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[contains(., 'Reservation Number:')]", null, true, "#Reservation Number:\s*(\w+)#");

        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->http->FindSingleNode("//*[@class='hotelName']");

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->getField("Arrival Date:") . ', ' . $this->getField("Check-in Time:")));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->getField("Departure Date:") . ', ' . $this->getField("Check-out Time:")));

        // Address
        $it['Address'] = implode(' ', $this->http->FindNodes("(//*[@class='hotelAddress'])[position()=1 or position()=2]"));

        // DetailedAddress

        // Phone
        $it['Phone'] = $this->http->FindSingleNode("(//*[@class='hotelAddress'])[3]");

        // Fax
        // GuestNames
        $it['GuestNames'] = [$this->getField("Guest Name:")];

        // Guests
        $it['Guests'] = $this->re("#(\d+)#", $this->getField("Occupancy:"));

        // Kids
        // Rooms
        // Rate
        $it['Rate'] = $this->cost($this->getField("Rate per night:"));

        // RateType
        $it['RateType'] = $this->getField("Rate type:");

        // CancellationPolicy
        $it['CancellationPolicy'] = $this->getField("Cancellation policy");

        // RoomType
        $it['RoomType'] = $this->getField("Room Type:");

        // RoomTypeDescription
        $it['RoomTypeDescription'] = $this->getField("Room Description");

        // Cost
        $it['Cost'] = $this->cost($this->getField("Subtotal:"));

        // Taxes
        // Total
        $it['Total'] = $this->cost($this->getField("Total (incl Tax):"));

        // Currency
        $it['Currency'] = $this->currency($this->getField("Total (incl Tax):"));

        // SpentAwards
        // EarnedAwards
        // AccountNumbers

        // Status
        // Cancelled
        if ($this->http->FindSingleNode("//*[@class='txt_headline' and normalize-space(.)='Cancellation']")) {
            $it['Status'] = 'Cancelled';
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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'Hotel',
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

    private function getField($field)
    {
        return $this->http->FindSingleNode("//td[not(.//td) and normalize-space(.)='{$field}']/following-sibling::td[1]");
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
            "#^(\w+)\s+(\d+),\s+(\d{4}),\s+(?:Check-in after|Check-out before)\s+(\d+:\d+\s+[AP]M)$#",
        ];
        $out = [
            "$2 $1 $3, $4",
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
