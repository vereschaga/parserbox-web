<?php

namespace AwardWallet\Engine\spg\Email;

class It3088369 extends \TAccountCheckerExtended
{
    public $mailFiles = "spg/it-3088369.eml, spg/it-3481981.eml";
    public $reBody = "starwoodhotels.com";
    public $reBody2 = "Reservation Advice";
    public $reSubject = "Your Four Points by";
    public $reFrom = "FPbSWakefieldBostonHotelandConferenceCenter@starwoodhotels.com";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $text = text($this->http->Response['body']);
                $text = preg_replace("#\n\s*>+#", "\n", $text);
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = re("#Reservation Number\s*:\s*(\d+)#", $text);

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = nice(re("#Remarks.*?[\-]{5,}\s*\n\s*(.*?)\s*\n\s*(.*?)\s*\n\s*(\S[\d\-\(\)\+ ]{5,})\s*\n#msi", $text));

                // Address
                $it['Address'] = nice(re(2));

                // DetailedAddress

                // Phone
                $it['Phone'] = re(3);

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(re("#Arrival Date\s*:\s*(\d+)-(\d+)-(\d+)#", $text, 2) . '.' . re(1) . '.' . re(3));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(re("#Departure Date\s*:\s*(\d+)-(\d+)-(\d+)#", $text, 2) . '.' . re(1) . '.' . re(3));

                // Fax
                // GuestNames
                $it['GuestNames'] = [re("#Guest Name\(s\)\s*:\s*(.*?)\s*(?:Arrival Flight|$)#", $text)];

                // Guests
                $it['Guests'] = re("#Number of Guests\s*:\s*(\d+)#", $text);

                // Kids
                // Rooms
                $it['Rooms'] = re("#Number of Rooms\s*:\s*(\d+)#", $text);

                // Rate
                $it['Rate'] = re("#Daily Room Rate\s*:\s*(\S+)#", $text);

                // RateType

                // CancellationPolicy
                // RoomType
                $it['RoomType'] = re("#Accommodation\s*:\s*(.+?)(?:\s{2,}|\s*Number of Rooms|\n)#", $text);

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
        if (!isset($headers['subject'],$headers['from'])) {
            return false;
        }

        return strpos($headers["from"], $this->reFrom) !== false && strpos($headers["subject"], $this->reSubject) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $body = $parser->getPlainBody();
        $this->http->SetBody($body);

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
