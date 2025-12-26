<?php

namespace AwardWallet\Engine\redlion\Email;

class Itinerary1 extends \TAccountCheckerExtended
{
    public $mailFiles = "redlion/it-1706219.eml, redlion/it-2654273.eml, redlion/it-3419662.eml";
    public $reFrom = "redlion.com";
    public $reSubject = "Your Redlion.com Reservation";
    public $reSubject2 = "Reservation Confirmation";
    public $reSubject3 = "Modification,confirmation number";

    public $reBody = "Red Lion Hotel";
    public $reBody2 = "GUEST INFORMATION";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            0 => function (&$itineraries) {
                $text = text($this->http->Response["body"]);
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = re("#Confirmation Number\s*:\s*(\w+)#", $text);

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $this->http->FindSingleNode("(//*[contains(text(),'Confirmation Number')]/ancestor::tr[1]/td[1]//text()[normalize-space(.)])[1]");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->http->FindSingleNode("//*[contains(text(),'Check-In')]/ancestor-or-self::td[1]", null, true, "#Check-In:\s+(.*?)Check-Out#"));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->http->FindSingleNode("//*[contains(text(),'Check-In')]/ancestor-or-self::td[1]", null, true, "#Check-Out:\s+(.*?)Number of Nights#"));

                // Address
                $it['Address'] = nice(implode("", $this->http->FindNodes("(//*[contains(text(),'Confirmation Number')]/ancestor::tr[1]/td[1]//text()[normalize-space(.)])[position()=2 or position()=3]")));

                // DetailedAddress

                // Phone
                $it['Phone'] = $this->http->FindSingleNode("//*[contains(text(),'Confirmation Number')]/ancestor::tr[1]/td[1]//text()[contains(., 'Phone Number')]", null, true, "#Phone\s+Number\s+([\d\-\+]+)#");

                // Fax
                $it['Fax'] = $this->http->FindSingleNode("//*[contains(text(),'Confirmation Number')]/ancestor::tr[1]/td[1]//text()[contains(., 'Fax Number')]", null, true, "#Fax\s+Number\s+([\d\-\+]+)#");

                // GuestNames

                // Guests
                $it['Guests'] = (int) $this->http->FindSingleNode("//*[contains(text(),'Guests:')]/ancestor-or-self::td[1]", null, true, "#Guests:\s*(\d+)\s+adults,\s+\d+\s+children#i");

                // Kids
                $it['Kids'] = (int) $this->http->FindSingleNode("//*[contains(text(),'Guests:')]/ancestor-or-self::td[1]", null, true, "#Guests:\s*\d+\s+adults,\s+(\d+)\s+children#i");

                // Rooms
                // Rate
                // RateType
                // CancellationPolicy

                // RoomType
                $it['RoomType'] = trim($this->http->FindSingleNode("//*[contains(text(),'ROOM INFORMATION')]/following::text()[normalize-space(.)][1]"));

                // RoomTypeDescription

                // Cost
                $it['Cost'] = $this->http->FindSingleNode("//*[contains(text(),'NIGHT(s)')][contains(text(), 'Room')][contains(text(),'Fee:')]/ancestor::tr[1]/td[3]", null, true, '#([\d\.]+)#');
                // Taxes
                $it['Taxes'] = $this->http->FindSingleNode("//*[contains(text(),'NIGHT(s)')][contains(text(), 'Room')][contains(text(),'Fee:')]/ancestor::tr[1]/following-sibling::tr[1]/td[3]", null, true, '#([\d\.]+)#');
                // Total
                $it['Total'] = $this->http->FindSingleNode("//*[contains(text(),'Total:')]/ancestor::tr[1]/td[3]", null, true, '#([\d\.]+)#');
                // Currency
                $it['Currency'] = orval(
                                    trim($this->http->FindSingleNode("//*[contains(text(),'Total:')]/ancestor::tr[1]/td[3]", null, true, '#^(\D+)[\d\.]+#')),
                                    trim($this->http->FindSingleNode("//*[contains(text(),'Total:')]/ancestor::tr[1]/td[3]", null, true, '#[\d\.]+(.+)#'))
                                    );
                $it['Currency'] = $it['Currency'] == '$' ? 'USD' : $it['Currency'];

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
        return strpos($headers["from"], $this->reFrom) !== false
            || (
                strpos($headers["subject"], $this->reSubject) !== false
                || strpos($headers["subject"], $this->reSubject2) !== false
                || strpos($headers["subject"], $this->reSubject3) !== false
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

        $processor = $this->processors[0];
        $processor($itineraries);

        // foreach($this->processors as $re => $processor){
        // if(stripos($body, $re)){
        // $processor($itineraries);
        // break;
        // }
        // }

        $result = [
            'emailType'  => 'Reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }
}
