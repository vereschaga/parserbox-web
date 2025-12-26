<?php

namespace AwardWallet\Engine\phound\Email;

class Itinerary1 extends \TAccountCheckerExtended
{
    public $reBody = "PointsHound";
    public $reBody2 = "You’re booked!";
    public $reFrom = "/pointshound\.com/";
    public $reSubject = 'Your reservation is confirmed!';

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            // Parsing subject "phound/it-2694490.eml" "phound/it-2704046.eml"
            $this->reBody2 => function (&$itineraries) {
                $it = [];

                // echo $this->http->Response['body'];
                // die();

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = CONFNO_UNKNOWN;

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $this->http->FindSingleNode("//*[contains(text(),'per night')]/../../span[1]");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->http->FindSingleNode("//*[contains(text(),'Check in')]/strong"));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->http->FindSingleNode("//*[contains(text(),'Check out')]/strong"));

                // Address
                $it['Address'] = $this->http->FindSingleNode("//*[contains(text(),'per night')]/ancestor::table[2]/ancestor::tr[1]/following-sibling::*[1]");

                // DetailedAddress

                // Phone
                // Fax
                // GuestNames
                $it['GuestNames'] = $this->http->FindSingleNode("//*[contains(text(),'Guest name')]/strong");

                // Guests
                $it['Guests'] = (int) $this->http->FindSingleNode("//*[contains(text(),'Guests')]/strong");

                // Kids
                // Rooms
                // Rate
                $it['Rate'] = trim(preg_replace("#\s+#", ' ', preg_replace("#(\d+)#", "$1 ", $this->http->FindSingleNode("//*[contains(text(),'per night')]/.."))));

                // RateType
                // CancellationPolicy
                $it['CancellationPolicy'] = trim(re("#Cancellation Policy(.*?)Support#ms", $this->http->FindSingleNode("//*[contains(text(), 'Cancellation Policy')]/..")));

                // RoomType
                $it['RoomType'] = $this->http->FindSingleNode("//*[contains(text(),'Room type')]/strong");

                // RoomTypeDescription
                // Cost
                $it['Cost'] = $this->http->FindSingleNode("//*[contains(text(),'Subtotal')]/strong", null, true, "#(\d+)#");

                // Taxes
                $it['Taxes'] = $this->http->FindSingleNode("//*[contains(text(),'Taxes & fees')]/strong", null, true, "#(\d+)#");

                // Total
                $it['Total'] = $this->http->FindSingleNode("//*[contains(text(),'Grand total')]/strong", null, true, "#(\d+)#");

                // Currency
                $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(),'All prices in')]", null, true, "#All prices in (.+)#");

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
        return preg_match($this->reFrom, $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match($this->reFrom, $headers["from"]) && strpos($headers["subject"], $this->reSubject) !== false;
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
