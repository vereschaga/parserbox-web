<?php

namespace AwardWallet\Engine\stash\Email;

class It2868599 extends \TAccountCheckerExtended
{
    public $mailFiles = "stash/it-2868599.eml";
    public $reBody = "stash-rewards.jpg";
    public $reBody2 = "reservation-confirmation.gif";
    public $reFrom = "reservations@shoresresort.com";
    public $reSubject = "Reservation Confirmation";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $text = text($this->http->Response["body"]);

                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->getField("Confirmation Number:");

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = nice(re("#Thank\s+you\s+for\s+choosing\s+(.*?)\s+for\s+your\s+upcoming#ms", $text));

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->getField("Arrival Date:") . ', ' . $this->getField("Check-in time:"));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->getField("Departure Date:") . ', ' . $this->getField("Check-out time:"));

                // Address
                $it['Address'] = trim($this->http->FindSingleNode("//*[contains(text(), '(Tel)')]", null, true, "#(.*?)\s+\(Tel\)#"));

                // DetailedAddress
                // Phone
                $it['Phone'] = trim($this->http->FindSingleNode("//*[contains(text(), '(Tel)')]", null, true, "#\(Tel\)\s+([\d\.]+)#"));

                // Fax
                $it['Fax'] = trim($this->http->FindSingleNode("//*[contains(text(), '(Tel)')]", null, true, "#\(Fax\)\s+([\d\.]+)#"));

                // GuestNames
                $it['GuestNames'] = [$this->getField("Guest Name:")];

                // Guests
                // Kids
                // Rooms
                // Rate
                // RateType

                // CancellationPolicy
                $it['CancellationPolicy'] = $this->getField("Cancellation:");

                // RoomType
                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                $it['Total'] = cost($this->getField("Total Cost including tax:"));

                // Currency
                $it['Currency'] = currency($this->getField("Self Parking:"));

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
        return strpos($headers["from"], $this->reFrom) !== false && strpos($headers["subject"], $this->reSubject) !== false;
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

    private function getField($str)
    {
        return $this->http->FindSingleNode("//td[normalize-space(.)='{$str}']/following-sibling::td[1]");
    }
}
