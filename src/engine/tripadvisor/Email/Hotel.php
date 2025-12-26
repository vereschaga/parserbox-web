<?php

namespace AwardWallet\Engine\tripadvisor\Email;

class Hotel extends \TAccountCheckerExtended
{
    public $mailFiles = "tadvisor/it-2.eml";
    public $reBody = "TripAdvisor";
    public $reBody2 = "Rental Summary";
    public $reFrom = "members@e.tripadvisor.com";
    public $reSubject = "Your vacation rental booking confirmation";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $text = $this->text();

                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = re("#Booking\s+\#\s*:\s*(\w+)#", $text);

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $this->http->FindSingleNode("//img[contains(@src, 'vr/hse.jpg')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]//a");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(uberDateTime($this->http->FindSingleNode("//*[normalize-space(text())='Check-in']/ancestor::td[1]")));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(uberDateTime($this->http->FindSingleNode("//*[normalize-space(text())='Check-out']/ancestor::td[1]")));

                // Address
                $it['Address'] = $this->http->FindSingleNode("//img[contains(@src, 'vr/hse.jpg')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]//a/following::text()[normalize-space(.)][1]");

                // DetailedAddress

                // Phone
                // Fax
                // GuestNames
                $it['GuestNames'] = [re("#Reservation\s+Name\s*:\s*([^\n]+)#", $text)];

                // Guests
                $it['Guests'] = re("#(\d+)\s+guests#", $text);

                // Kids
                // Rooms
                // Rate
                // RateType

                // CancellationPolicy
                $it['CancellationPolicy'] = $this->http->FindSingleNode("//*[contains(text(), 'Cancellation Policy')]/ancestor::tr[1]/following-sibling::tr[1]");

                // RoomType
                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                $it['Total'] = cost($this->http->FindSingleNode("//text()[normalize-space(.)='Paid in Full']/following::text()[string-length(normalize-space(.))>1][1]"));

                // Currency
                $it['Currency'] = currency($this->http->FindSingleNode("//text()[normalize-space(.)='Paid in Full']/following::text()[string-length(normalize-space(.))>1][1]"));

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
}
