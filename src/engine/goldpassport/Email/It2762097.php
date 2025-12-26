<?php

namespace AwardWallet\Engine\goldpassport\Email;

class It2762097 extends \TAccountCheckerExtended
{
    public $mailFiles = "goldpassport/it-2762097.eml";
    public $reBody = "Premier Hyatt Guest Rate";
    public $reBody2 = "Vacation Confirmation for";
    public $reSubject = "Premier Hyatt Guest Confirmation for";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->http->FindSingleNode("//*[contains(text(), 'Reservation ')]/span");

                // TripNumber
                // ConfirmationNumbers
                // Hotel Name
                $it['HotelName'] = $this->http->FindSingleNode("//*[contains(text(), 'You will be staying at')]/../following-sibling::*[1]");

                // 2ChainName
                // CheckInDate
                $it['CheckInDate'] = strtotime($this->http->FindSingleNode("//*[contains(text(), 'Arrival Date')]/../following-sibling::*[1]"));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->http->FindSingleNode("//*[contains(text(), 'Departure Date')]/../following-sibling::*[1]"));

                // Address
                $it['Address'] = $this->http->FindSingleNode("//*[contains(text(), 'Address')]/../following-sibling::*[1]");

                // DetailedAddress
                // Phone
                // Fax
                // GuestNames
                // Guests
                $it['Guests'] = (int) $this->http->FindSingleNode("//*[contains(text(), 'Adults')]/../following-sibling::*[1]");

                // Kids
                $it['Kids'] = (int) $this->http->FindSingleNode("//*[contains(text(), 'Children')]/../following-sibling::*[1]");

                // Rooms
                // Rate
                // RateType
                // CancellationPolicy
                $it['CancellationPolicy'] = trim($this->http->FindSingleNode("//*[contains(text(), 'Cancel Policy:')]", null, true, "#Cancel Policy:(.+)#ms"));

                // RoomType
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers['subject'], $this->reSubject) !== false;
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
