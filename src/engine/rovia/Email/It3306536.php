<?php

namespace AwardWallet\Engine\rovia\Email;

class It3306536 extends \TAccountCheckerExtended
{
    public $mailFiles = "rovia/it-3306536.eml";
    public $reBody = "Rovia";
    public $reBody2 = "Your booking has been received";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $text = text($this->http->Response["body"]);
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = re("#Your\s+Booking\s+ID\s+number\s+is\s*:\s*(\w+)#", $text);

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $this->http->FindSingleNode("(//*[normalize-space(text())='Hotel Address:']/ancestor::tr[1]/following-sibling::tr[1]//text()[normalize-space(.)])[1]");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(re("#Check in\s*:\s*(.+)#", $text) . ", " . re("#Check-in\s*:\s*(.*?)\s+Check-out\s*:\s*(.+)#", $text));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(re("#Check out\s*:\s*(.+)#", $text) . "," . re("#Check-in\s*:\s*(.*?)\s+Check-out\s*:\s*(.+)#", $text, 2));

                // Address
                $it['Address'] = nice(implode("", $this->http->FindNodes("(//*[normalize-space(text())='Hotel Address:']/ancestor::tr[1]/following-sibling::tr[1]//text()[normalize-space(.)])[position()>1]")));

                // DetailedAddress
                // Phone
                // Fax
                // GuestNames
                $it['GuestNames'] = array_map(function ($s) { return trim($s, ": "); }, $this->http->FindNodes("//*[normalize-space(text())='Traveler Info, Check in CheckOut Date']/ancestor::tr[1]/following-sibling::tr/td/table//tr[contains(./td[1], 'Traveler')]/td[2]"));

                // Guests
                $it['Guests'] = re("#Adults\s*:\s*(\d+)#", $text);

                // Kids
                $it['Kids'] = re("#Children\s*:\s*(\d+)#", $text);

                // Rooms
                // Rate
                // RateType
                // CancellationPolicy
                $it['CancellationPolicy'] = $this->http->FindSingleNode("//*[normalize-space(text())='Cancellation Policy:']/following::text()[normalize-space(.)][1]");

                // RoomType
                $it['RoomType'] = re("#Room Type\s*:\s*(.+)#", $text);

                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                // Currency
                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                $it['Status'] = re("#Booking Status\s*:\s*(.+)#", $text);

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

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
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
}
