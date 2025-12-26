<?php

namespace AwardWallet\Engine\spg\Email;

class It3477218 extends \TAccountCheckerExtended
{
    public $mailFiles = "spg/it-3477218.eml";
    public $reBody = "Starwood Hotels";
    public $reBody2 = "Your reservation at";
    public $reFrom = "starwoodvacationownership@starwoodvo.com";
    public $reSubject = "Your Reservation at";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->getField("Confirmation Number");

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $this->http->FindSingleNode("(//img[contains(@src, '/westin/menubar.gif')]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]//text()[normalize-space(.)])[1]");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(re("#(\d+/\d+/\d+)\s+at\s+(\d+:\d+\s+[ap]\.m\.)#", $this->getField("Arrival Date")) . ' ' . re(2));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(re("#(\d+/\d+/\d+)\s+at\s+(\d+:\d+\s+[ap]\.m\.)#", $this->getField("Departure Date")) . ' ' . re(2));

                // Address
                $it['Address'] = $this->http->FindSingleNode("(//img[contains(@src, '/westin/menubar.gif')]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]//text()[normalize-space(.)])[2]");

                // DetailedAddress

                // Phone
                $it['Phone'] = $this->http->FindSingleNode("(//img[contains(@src, '/westin/menubar.gif')]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]//text()[normalize-space(.)])[3]", null, true, "#Phone\s+([\d\.]+)#");

                // Fax
                $it['Fax'] = $this->http->FindSingleNode("(//img[contains(@src, '/westin/menubar.gif')]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]//text()[normalize-space(.)])[3]", null, true, "#Fax\s+([\d\.]+)#");

                // GuestNames
                $it['GuestNames'] = [$this->getField("Explorer Package Holder")];

                // Guests
                // Kids
                // Rooms
                // Rate
                // RateType

                // CancellationPolicy
                $it['CancellationPolicy'] = nice(re("#RESERVATION CANCELLATION POLICY\s+(.*?)\s+RESERVATION VALIDITY#msi", text($this->http->Response["body"])));

                // RoomType
                $it['RoomType'] = $this->getField("Villa Type");

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
        if (!isset($headers['subject'],$headers['from'])) {
            return false;
        }

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
