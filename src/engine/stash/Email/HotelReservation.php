<?php

namespace AwardWallet\Engine\stash\Email;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "stash/it-3745305.eml, stash/it-3745308.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'HotelReservation',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'mhernandez@thebristolsandiego.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'mhernandez@thebristolsandiego.com') !== false
            || isset($headers['subject']) && stripos($headers['subject'], 'Bristol Hotel Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(.), 'Stash Hotel Rewards')]")->length > 0;
    }

    protected function parseEmail()
    {
        $it = ['Kind' => 'R'];
//        $it['RecordLocator'] = $this->http->FindSingleNode();
//        ConfirmationNumber
        $it['ConfirmationNumber'] = $this->getNode('Confirmation Number:');
//        ConfirmationNumbers
//        HotelName
        $it['HotelName'] = $this->http->FindSingleNode("//strong[contains(normalize-space(.), ' Hotel ')]");
//        CheckInDate
//        CheckOutDate
        $checkInOutDate = explode("/", $this->getNode('Check-in / Check-out:'));

        if (count($checkInOutDate) > 1) {
            $it['CheckInDate'] = strtotime($checkInOutDate[0]);
            $it['CheckOutDate'] = strtotime($checkInOutDate[1]);
        }
//        Address
        $it['Address'] = implode($this->http->FindNodes("//text()[contains(normalize-space(.), 'Phone:')]/preceding-sibling::text()"));
//        Phone
        $it['Phone'] = preg_replace("#\.#", ' ', $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Phone:')]/following-sibling::span"));
//        Fax
        $it['Fax'] = preg_replace("#\.#", ' ', $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Fax:')]", null, true, "#[\w\S]+ ([\d\S]+)#"));
//        GuestNames
        $it['GuestNames'] = [$this->getNode('Guest Name:')];
//        Guests
        $it['Guests'] = $this->getNode('Number of Guests:');
//        Kids
//        Rooms
//        Rate
        $it['Rate'] = $this->getNode('Average Daily Rate:', "#\w{3} (\d+\S+)#");
//        RateType
//        RoomType
//        Cost
//        Taxes
//        Total
        $it['Total'] = $this->getNode('Total Reservation:', "#[\w]{3} ([\d\S]+)#");
//        Currency
        $it['Currency'] = $this->getNode('Total Reservation:', "#([\w]{3}) [\d\S]+#");
//        AccountNumbers
        return [$it];
    }

    protected function getNode($str, $regExp = null)
    {
        return $this->http->FindSingleNode("//strong[contains(normalize-space(.), '{$str}')]/following::strong[1]", null, true, "{$regExp}");
    }
}
