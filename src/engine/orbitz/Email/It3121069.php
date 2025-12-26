<?php

namespace AwardWallet\Engine\orbitz\Email;

class It3121069 extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-3121069.eml";
    public $reBody = "orbitz.com";
    public $reBody2 = "Enjoy your trip!";
    public $reFrom = "care@e.orbitz.com";
    public $reSubject = "Prepare for your Trip";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->getField("Hotel Confirmation Number");

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Orbitz booking number:']/following::a[1]");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->getField("Check-in"));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->getField("Check-out"));

                // Address
                $it['Address'] = implode(" ", $this->http->FindNodes("//a[normalize-space(.)='{$it['HotelName']}']/following::text()[1]|//text()[normalize-space(.)='{$it['HotelName']}']/following::text()[2]|//text()[normalize-space(.)='{$it['HotelName']}']/following::text()[3]"));

                // DetailedAddress

                // Phone
                $it['Phone'] = $this->getField("Phone number");

                // Fax
                // GuestNames
                $it['GuestNames'] = [$this->getField("Room Reservation Name")];

                // Guests
                $it['Guests'] = $this->getField("Total Number of Guests");

                // Kids
                // Rooms
                $it['Rooms'] = $this->getField("Total Number of Rooms");

                // Rate
                // RateType

                // CancellationPolicy
                $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Cancellation Policy for')]/following::div[1]");

                // RoomType
                $it['RoomType'] = re("#^(.*?)\s*-#", $this->getField("Room Description"));

                // RoomTypeDescription
                $it['RoomTypeDescription'] = re("#^.*?\s*-\s*(.+)#", $this->getField("Room Description"));

                // Cost
                // Taxes
                $it['Taxes'] = cost($this->getField("Taxes and Fees"));

                // Total
                $it['Total'] = cost($this->getField("Room for "));

                // Currency
                $it['Currency'] = currency($this->getField("Room for "));

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
        return $this->http->FindSingleNode($q = "//text()[substring(normalize-space(.), 1, " . strlen($str) . ")=\"{$str}\"]/following::text()[string-length(normalize-space(.))>0][1]");
    }
}
