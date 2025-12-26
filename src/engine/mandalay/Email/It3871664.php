<?php

namespace AwardWallet\Engine\mandalay\Email;

class It3871664 extends \TAccountCheckerExtended
{
    public $reBody = 'Mandalay Bay';
    public $reBody2 = [
        "en"=> "Thank you for your reservation",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            "html" => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = trim($this->http->FindSingleNode("//text()[normalize-space(.)='Room Confirmation']/following::text()[normalize-space(.)][1]"), "# ");

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $this->http->FindSingleNode("(//text()[normalize-space(.)='Reservation Total']/ancestor::td[2]/span[2]//text()[normalize-space(.)])[1]");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(
                    $this->re("#(?<month>\w+)\s+(?<day1>\d+)\s+-\s+(?<day2>\d+),\s+(?<year>\d{4})#", $this->http->FindSingleNode("(//text()[normalize-space(.)='Reservation Total']/ancestor::table[3]/preceding-sibling::table[1]//text()[normalize-space(.)])[1]"), 'day1') . ' ' .
                    $this->re('month') . ' ' .
                    $this->re('year')
                );

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(
                    $this->re('day2') . ' ' .
                    $this->re('month') . ' ' .
                    $this->re('year')
                );

                // Address
                $it['Address'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Privacy']/preceding::text()[string-length(normalize-space(.))>1][1]");

                // DetailedAddress

                // Phone
                $it['Phone'] = trim($this->http->FindSingleNode("//text()[normalize-space(.)='Reservation Total']/ancestor::td[2]/span[2]", null, true, "#Reservations\s+Phone\s+Number\s+([\(\)\s-\d]+)#"));

                // Fax
                // GuestNames
                // Guests
                // Kids
                // Rooms
                // Rate
                // RateType

                // CancellationPolicy
                // RoomType
                $it['RoomType'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Reservation Total']/ancestor::td[2]/span[1]");

                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                $it['Total'] = cost($this->getField("Reservation Total"));

                // Currency
                $it['Currency'] = currency($this->getField("Reservation Total"));

                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                // Cancelled
                // ReservationDate
                // NoItineraries
                $itineraries[] = $it;
            },
        ];
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

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'  => 'Flight',
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

    private function re($re, $str = null, $c = 1)
    {
        if ($str === null && isset($this->lastre)) {
            if (isset($this->lastre[$re])) {
                return $this->lastre[$re];
            }

            return null;
        }

        preg_match($re, $str, $m);
        $this->lastre = $m;

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
