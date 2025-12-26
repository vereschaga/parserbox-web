<?php

namespace AwardWallet\Engine\milleniumnclc\Email;

class It3226334 extends \TAccountCheckerExtended
{
    public $mailFiles = "milleniumnclc/it-3226334.eml";
    public $reBody = "www.millenniumhotels";
    public $reBody2 = "We are pleased to confirm";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $text = text($this->http->Response['body']);

                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = re("#Confirmation Number\s*:\s*(\d+)#", $text);

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = re("#Thank you for booking at the (.*?)\.#", $text);

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(re("#Date of Arrival\s*:\s*\n([^\n]+)#", $text));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(re("#Date of Departure\s*:\s*\n([^\n]+)#", $text));

                // Address
                $it['Address'] = nice(re("#{$it['HotelName']}\n(.*?)\nTelephone:#ms", $text), ", ");

                // DetailedAddress

                // Phone
                $it['Phone'] = re("#Telephone\s*:\s*([^\n]+)#", $text);

                // Fax
                // GuestNames
                $it['GuestNames'] = [re("#\nName\s*:\s*\n([^\n]+)#", $text)];

                // Guests
                $it['Guests'] = re("#\nNumber of Adults\s*:\s*\n([^\n]+)#", $text);

                // Kids
                $it['Kids'] = re("#\nNumber of Children\s*:\s*\n([^\n]+)#", $text);

                // Rooms
                $it['Rooms'] = re("#\nNumber of Rooms\s*:\s*\n([^\n]+)#", $text);

                // Rate
                $it['Rate'] = re("#\nRate GBP \(excl Tax\)\s*:\s*\n([^\n]+)#", $text);

                // RateType
                $it['RateType'] = re("#\nRate Booked\s*:\s*\n([^\n]+)#", $text);

                // CancellationPolicy
                // RoomType
                $it['RoomType'] = re("#\nRoom Type\s*:\s*\n([^\n]+)#", $text);

                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                $it['Total'] = cost(re("#\nTotal Cost \(incl Tax\)\s*:\s*\n([^\n]+)#", $text));

                // Currency
                $it['Currency'] = currency(re("#\nTotal Cost \(incl Tax\)\s*:\s*\n([^\n]+)#", $text));

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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }

        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) === null) {
            return false;
        }

        return strpos($html, $this->reBody) !== false && strpos($html, $this->reBody2) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $pdf = $pdfs[0];
        $body = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE);
        $this->http->SetEmailBody($body);

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
