<?php

namespace AwardWallet\Engine\hiltongvc\Email;

class It2851696 extends \TAccountCheckerExtended
{
    public $mailFiles = "hiltongvc/it-2851696.eml";
    public $reBody = "Hilton Worldwide";
    public $reBody2 = "TOUR DETAILS";
    public $reFrom = "VIP@crm.SafeCRM.com";
    public $reSubject = "HGVC Reservation Confirmation";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->http->FindSingleNode("//*[contains(text(),'Confirmation Number:')]/following-sibling::*[1]");

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = trim($this->http->FindSingleNode("//*[contains(text(),'Departure Date')]/../following-sibling::*[1]", null, true, "#^([^\d]+)#"));

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->http->FindSingleNode("//*[contains(text(),'Arrival Date')]/following-sibling::*[1]"));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->http->FindSingleNode("//*[contains(text(),'Departure Date')]/following-sibling::*[1]"));

                // Address
                $it['Address'] = trim($this->http->FindSingleNode("//*[contains(text(),'Departure Date')]/../following-sibling::*[1]", null, true, "#^[^\d]+(.*)\s+[\d+-]+#"));

                // DetailedAddress

                // Phone
                $it['Phone'] = trim($this->http->FindSingleNode("//*[contains(text(),'Departure Date')]/../following-sibling::*[1]", null, true, "#^[^\d]+.*\s+([\d+-]+)#"));

                // Fax
                // GuestNames
                $it['GuestNames'] = explode("|", $this->http->FindSingleNode("//*[contains(text(), 'Name:')]/following-sibling::*[1]"));

                // Guests
                // Kids
                // Rooms
                // Rate
                // RateType

                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                $it['Total'] = $this->http->FindSingleNode("//*[contains(text(), 'Total Paid:')]", null, true, "#Total Paid:\s+[^\s\d\.]+([\d\.]+)#");

                // Currency
                $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(), 'Total Paid:')]", null, true, "#Total Paid:\s+([^\s\d\.]+)[\d\.]+#");

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
        return stripos($headers["from"], $this->reFrom) && stripos($headers["subject"], $this->reSubject);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }
        $body = preg_replace("#<br\s+id=\"yiv.*?>#", "|", $body);
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
