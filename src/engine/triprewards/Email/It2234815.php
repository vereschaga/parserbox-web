<?php

namespace AwardWallet\Engine\triprewards\Email;

class It2234815 extends \TAccountCheckerExtended
{
    public $mailFiles = "triprewards/it-2234815.eml";
    public $reBody = "This email and attached report was sent to you from the RAMADA";
    public $reSubject = "Reservation Folio From RAMADA";
    public $reFrom = "donotreply@wyn.com";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            "RAMADA" => function (&$itineraries) {
                $text = text($this->http->Response['body']);
                $lines = explode("\n", $text);
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = re("#Confirmation Number:\s+(\d+)#ms", $text);

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = trim($lines[0]);

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(re("#Arrival:\s+(\d+)/(\d+)/(\d+)#ms", $text, 2) . '.' . re(1) . '.' . re(3));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(re("#Departure:\s+(\d+)/(\d+)/(\d+)#ms", $text, 2) . '.' . re(1) . '.' . re(3));

                // Address
                $it['Address'] = trim($lines[1]) . ', ' . trim($lines[2]);

                // DetailedAddress

                // Phone
                $it['Phone'] = trim(re("#Phone:\s+([^\n]+)#", $text));

                // Fax
                $it['Fax'] = trim(re("#Fax:\s+([^\n]+)#", $text));

                // GuestNames
                $GuestNames = $this->http->FindNodes("//*[contains(text(), 'Guest')][contains(text(),'name')]/parent::*/following-sibling::*");

                if (count($GuestNames) > 0) {
                    $it['GuestNames'] = array_unique($GuestNames);
                }

                // Guests
                $it['Guests'] = (int) re("#Guests:\s+(\d+)/\d+#", $text);

                // Kids
                $it['Kids'] = (int) re("#Guests:\s+\d+/(\d+)#", $text);

                // Rooms
                // Rate
                $it['Rate'] = re("#Daily Rate:\s+([^\n]+)#ms", $text);

                // RateType

                // CancellationPolicy

                // RoomType
                $it['RoomType'] = re("#Room Type:\s+([^\n]+)#ms", $text);

                $n = 0;

                foreach ($lines as $key=>$line) {
                    if ($line == " DB") {
                        $n = $key;

                        break;
                    }
                }
                // RoomTypeDescription
                // Cost
                $it['Cost'] = re("#[\d\.]+#", $lines[$n + 1]);

                // Taxes
                $it['Taxes'] = re("#[\d\.]+#", $lines[$n + 2]);

                // Total
                $it['Total'] = re("#[\d\.]+#", $lines[$n + 5]);

                // Currency
                $it['Currency'] = re("#[^\(\)\s\d\.]+#", $lines[$n + 5]);

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

        return strpos($body, $this->reBody) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false && strpos($headers["from"], $this->reFrom) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                $this->http->SetBody($html);

                foreach ($this->processors as $re => $processor) {
                    if (stripos($html, $re)) {
                        $processor($itineraries);

                        break;
                    }
                }
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
