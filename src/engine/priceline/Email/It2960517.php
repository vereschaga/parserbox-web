<?php

namespace AwardWallet\Engine\priceline\Email;

class It2960517 extends \TAccountChecker
{
    public $mailFiles = "priceline/it-2.eml, priceline/it-21.eml, priceline/it-2960469.eml, priceline/it-2960478.eml, priceline/it-2960517.eml";
    public $reBody = [".priceline.com/hotel", "Thank you for booking your hotel on priceline"];
    public $reBody2 = "Hotel Confirmation";
    public $reFrom = "hotel@trans.priceline.com";
    public $reSubject = "Your Itinerary for";

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->reBody as $reBody) {
            if ($this->http->XPath->query("//a[{$this->contains($reBody, '@href')}] | //*[{$this->contains($reBody)}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($this->reBody2)}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
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
            $this->http->SetEmailBody($body);
        }
        $this->parseHtml($itineraries);
        $result = [
            'emailType'  => 'Reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    private function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//*[contains(text(), 'Reservation Name')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", null, true, "#Confirmation\s*\#\s*:\s*(\w+)#i");

        // TripNumber
        $it['TripNumber'] = $this->http->FindSingleNode("(//text()[" . $this->eq(["priceline trip number:", "Your request number:"]) . "])[1]/following::text()[normalize-space()][1]", null, true, "#^\s*([\d\-]{5,})\s*$#i");

        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->http->FindSingleNode("//*[contains(text(), 'Check-In')]/ancestor::tr[1]/preceding-sibling::tr[1]");

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime(str_replace(" at", ",", $this->http->FindSingleNode("//*[contains(text(), 'Check-In')]/..", null, true, "#Check-In\s*(.*?)\s*Check-Out#")));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime(str_replace(" at", ",", $this->http->FindSingleNode("//*[contains(text(), 'Check-Out')]/..", null, true, "#Check-Out\s*(.+)#")));

        // DetailedAddress

        // Address
        // Phone
        $address = implode("\n", $this->http->FindNodes("//*[contains(text(), 'Check-In')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]//text()[normalize-space()]"));

        if (!empty($it['HotelName']) && preg_match("#" . $it['HotelName'] . "\s+(.+)\n([\d \-\(\)]{5,})(?:\n|$)#s", $address, $m)) {
            $it['Address'] = nice($m[1]);
            $it['Phone'] = $m[2];
        }

        // Fax
        // GuestNames
        // Guests
        // Kids
        // Rooms
        $it['Rooms'] = $this->http->FindSingleNode("//td[" . $this->eq("Number of Rooms:") . "]/following-sibling::td[1]");

        // Rate
        // RateType
        // CancellationPolicy
        // RoomType
        $it['RoomType'] = $this->http->FindSingleNode("//*[contains(text(), 'Room Type')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

        // RoomTypeDescription
        $it['RoomTypeDescription'] = $this->http->FindSingleNode("//*[contains(text(), 'Room Type')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]", null, true, "#^.*?,\s*(.+)#");

        // Cost
        $it['Cost'] = $this->http->FindSingleNode("//*[contains(text(), 'Room Subtotal')]/following-sibling::td[1]", null, true, "#[\d\.]+#");

        // Taxes
        $it['Taxes'] = $this->http->FindSingleNode("//*[contains(text(), 'Taxes and Fees')]/following-sibling::td[1]", null, true, "#[\d\.]+#");

        // Total
        $it['Total'] = $this->http->FindSingleNode("//*[contains(text(), 'Total Charged to Card')]/following-sibling::td[1]", null, true, "#[\d\.]+#");

        // Currency
        $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(), 'Total Charged to Card')]/following-sibling::td[1]", null, true, "#\(([A-Z]+)\)#");

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ReservationDate
        // NoItineraries
        $itineraries[] = $it;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }
}
