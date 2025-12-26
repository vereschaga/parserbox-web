<?php

namespace AwardWallet\Engine\triprewards\Email;

// parsers with similar formats: gcampaigns/HResConfirmation (object), marriott/ReservationConfirmation (object), marriott/It2506177, mirage/It1591085, woodfield/It2220680, goldpassport/WelcomeTo

class It3520762 extends \TAccountCheckerExtended
{
    public $mailFiles = "triprewards/it-3520762.eml, triprewards/it-3534289.eml";
    public $reBody = "Silverado";
    public $reBody2 = "Hotel Confirmation:";
    public $reBody3 = "Acknowledgement Number:";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = orval(
                    $this->getField("Online Confirmation:"),
                    $this->getField("Acknowledgement Number:")
                );

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = orval(
                    $this->http->FindSingleNode("((//img[contains(@src, 'http://groupmax.passkey.com/images')]/ancestor::tr[1]/following-sibling::tr[1]//tr[1])[1]//text()[string-length(normalize-space(.))>1])[3]"),
                    $this->http->FindSingleNode("//img[not(@src)]/../text()[1]")
                );

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->getField("Arrival Date:"));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->getField("Departure Date:"));

                // Address
                // $it['Address'] = $this->http->FindSingleNode("//*[contains(text(), 'Address')]/parent::*/following-sibling::*[1]");
                $it['Address'] = $it['HotelName'];

                // DetailedAddress

                // Phone
                $it['Phone'] = $this->http->FindSingleNode("//img[not(@src)]/../text()[last()]", null, true, "#^[\d-]+$#");

                // Fax
                // GuestNames
                $it['GuestNames'] = [$this->getField("Reservation Name:")];

                // Guests
                $it['Guests'] = orval(
                    $this->getField("Number of Guests:"),
                    $this->http->FindSingleNode("//*[contains(text(), 'Guest(s)')]/following::text()[normalize-space(.)][1]", null, true, "#\d+-\w+-\d{4}\s+(\d+)#")
                );

                // Kids
                // Rooms
                $it['Rooms'] = $this->getField("Number of Rooms:");

                // Rate
                // RateType

                // CancellationPolicy
                $it['CancellationPolicy'] = strlen($this->getFIeld("Cancel Policy:")) > 1 ? $this->getFIeld("Cancel Policy:") : null;

                // RoomType
                $it['RoomType'] = $this->getField("Room Type:");

                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                $it['Total'] = cost($this->getField("Total Charge:"));

                // Currency
                $it['Currency'] = 'USD';

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

        return strpos($body, $this->reBody) !== false && (
            strpos($body, $this->reBody2) !== false
            || strpos($body, $this->reBody3) !== false
        );
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
        return $this->http->FindSingleNode("//*[normalize-space(text())='{$str}']/ancestor::td[1]/following-sibling::td[1]");
    }
}
