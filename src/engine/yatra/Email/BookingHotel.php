<?php

namespace AwardWallet\Engine\yatra\Email;

class BookingHotel extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'BookingHotelEn',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'no-reply@yatra.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'no-reply@yatra.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//*[contains(normalize-space(text()), 'Yatra Corporate Hotel Solutions')]")->length > 0;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = $this->getNode('Hotel Confirmation');
        $it['HotelName'] = $this->getNode('Hotel Name');
        $it['CheckInDate'] = strtotime($this->getNode('Check in date'));
        $it['CheckOutDate'] = strtotime($this->getNode('Check out date'));
        $it['Address'] = $this->getDetails('Address');
        $it['Phone'] = $this->getDetails('Contact No');
        $it['RoomType'] = $this->getNode('Room Type');
        $it['GuestNames'] = $this->http->FindNodes("//*[normalize-space(text())='Customer Details']/following::*[normalize-space() != ''][1]/td[2]");
        $adult = $this->getNode('Adult | Child');

        if (preg_match("#(\d+)\s+\|\s*(\d+)#", $adult, $m)) {
            $it['Guests'] = $m[1];
            $it['Kids'] = $m[2];
        }
        $rate = $this->getNode('Room Rate');

        if (preg_match("#(\w{3})\s+([\d.,]+)#", $rate, $math)) {
            $it['Currency'] = $math[1];
            $it['Rate'] = str_replace(',', '', $math[2]);
        }
        $it['Status'] = $this->http->FindSingleNode("//*[contains(normalize-space(text()), 'Your reservation is')]", null, true, "#Your reservation is\s*(\w+)\s*\..+#");

        return [$it];
    }

    private function getDetails($str)
    {
        return $this->http->FindSingleNode("(//*[normalize-space(text())='Hotel Details']/following::*[normalize-space() != ''][contains(., '{$str}')]/td[2])[1]");
    }

    private function getNode($str)
    {
        return $this->http->FindSingleNode("//*[contains(normalize-space(text()), '{$str}')]/following::*[normalize-space() != ''][1]");
    }
}
