<?php

namespace AwardWallet\Engine\trumpcard\Email;

class It3693314 extends \TAccountCheckerExtended
{
    public $reBody = "TRUMP";
    public $reBody2 = "We invite you to";
    public $reFrom = "reservations@trumphotels.com";
    public $reSubject = "Upcoming Stay - Trump";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $text = text($this->http->Response["body"]);
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->getField("Confirmation Number");

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = orval(
                    re("#Upcoming\s+Stay\s+-\s+(.+)#", $this->subject),
                    $this->http->FindSingleNode("//text()[normalize-space(.)='PHONE:']/../descendant::text()[normalize-space(.)][1]")
                );

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->getField("Arrival Date"));

                if ($time = re("#Check-in\s+Time\s*:\s*(\d+:\d+\s+[AP]M)#", $text)) {
                    $it['CheckInDate'] = strtotime($time, $it['CheckInDate']);
                }

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->getField("Departure Date"));

                if ($time = re("#Check-out\s+Time\s*:\s*(\d+:\d+\s+[AP]M)#", $text)) {
                    $it['CheckOutDate'] = strtotime($time, $it['CheckOutDate']);
                }

                // Address
                $it['Address'] = $this->http->FindSingleNode($q = "(//text()[normalize-space(.)='" . strtoupper($it['HotelName']) . "'])[last()]/following::text()[normalize-space(.)][1]");

                // DetailedAddress

                // Phone
                $it['Phone'] = $this->http->FindSingleNode($q = "(//text()[normalize-space(.)='" . strtoupper($it['HotelName']) . "'])[last()]/following::a[1][contains(@href, 'tel:')]/@href", null, true, "#^tel:(\d+)$#");

                // Fax
                // GuestNames
                $it['GuestNames'] = [$this->getField("Guest Name")];

                // Guests
                $it['Guests'] = re("#(\d+)\s+Adult#", $this->getField("No. of Persons"));

                // Kids
                // Rooms
                $it['Rooms'] = re("#\d+#", $this->getField("Room Description"));

                // Rate
                // RateType
                $it['RateType'] = $this->getField("Rate Name");

                // CancellationPolicy
                // RoomType
                $it['RoomType'] = $this->getField("Room Description");

                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                // Currency
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

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false || strpos($headers["subject"], $this->reSubject) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->subject = $parser->getHeader("subject");
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }
        $result = [
            'emailType'  => 'Reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    private function getField($str)
    {
        return $this->http->FindSingleNode("//td[normalize-space(.)='{$str}']/following-sibling::td[1]");
    }
}
