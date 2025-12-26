<?php

namespace AwardWallet\Engine\movenpick\Email;

class HotelConfirmation extends \TAccountChecker
{
    public $mailFiles = "movenpick/it-2844668.eml";

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@moevenpick.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'resort.somabay@moevenpick.com') !== false
            || isset($headers['subject']) && stripos($headers['subject'], 'Mövenpick Resort Soma Bay Reservation Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($this->http->Response['body'], 'Thank you for your reservation at the Mövenpick Resort Soma Bay.') !== false
            || $this->http->XPath->query('//a[contains(@href,"mailto:resort.somabay@moevenpick.com")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'HotelConfirmation',
        ];
    }

    protected function ParseEmail()
    {
        $it = [];
        $it['Kind'] = 'R';
        $nodes = $this->http->XPath->query('//td[starts-with(normalize-space(.),"Reservation Details")]/../following-sibling::tr[1]/td');

        if ($nodes->length > 0) {
            $reservation = $nodes->item(0);
            $it['ConfirmationNumber'] = $this->http->FindSingleNode('.//b[starts-with(normalize-space(.),"Confirmation/Itinerary Number:")]/following-sibling::text()[1]', $reservation, true, '/([\d\w]+) \/ /');
            $it['CheckInDate'] = strtotime($this->http->FindSingleNode('.//b[starts-with(normalize-space(.),"Arrival Date:")]/following-sibling::text()[1]', $reservation));
            $it['CheckOutDate'] = strtotime($this->http->FindSingleNode('.//b[starts-with(normalize-space(.),"Departure Date:")]/following-sibling::text()[1]', $reservation));
            $guestNames = $this->http->FindSingleNode('.//b[starts-with(normalize-space(.),"Guest Name:")]/following-sibling::text()[1]', $reservation);
            $it['GuestNames'] = array_map('trim', explode(',', $guestNames));
            $nodes = $this->http->XPath->query('.//b[starts-with(normalize-space(.),"Number of guests:")]', $reservation);

            if ($nodes->length > 0) {
                $numberOfGuests = $nodes->item(0);
                $it['Guests'] = $this->http->FindSingleNode('./following-sibling::text()[1]', $numberOfGuests, true, '/Adults = ([\d]+)/');
                $it['Kids'] = $this->http->FindSingleNode('./following-sibling::text()[1]', $numberOfGuests, true, '/Child = ([\d]+)/');
            }
            $it['Rooms'] = $this->http->FindSingleNode('.//b[starts-with(normalize-space(.),"Number of rooms:")]/following-sibling::text()[1]', $reservation);
            $it['RoomType'] = $this->http->FindSingleNode('.//b[starts-with(normalize-space(.),"Room Type:")]/following-sibling::text()[1]', $reservation);
            $it['RoomTypeDescription'] = $this->http->FindSingleNode('.//b[starts-with(normalize-space(.),"Room Description:")]/following-sibling::text()[1]', $reservation);
        }
        $nodes = $this->http->XPath->query('//td[starts-with(normalize-space(.),"Pricing Details")]/../following-sibling::tr[1]/td');

        if ($nodes->length > 0) {
            $pricing = $nodes->item(0);
            $it['Taxes'] = $this->http->FindSingleNode('.//b[starts-with(normalize-space(.),"Taxes:")]/following-sibling::text()[1]', $pricing, true, '/USD ([\d.]+)/');
            $it['Total'] = $this->http->FindSingleNode('.//b[starts-with(normalize-space(.),"Total Rate incl. taxes:")]/following-sibling::text()[1]', $pricing, true, '/USD ([\d.]+)/');
        }
        $nodes = $this->http->XPath->query('//td[starts-with(normalize-space(.),"Hotel Details")]/../following-sibling::tr[1]/td');

        if ($nodes->length > 0) {
            $hotel = $nodes->item(0);
            $it['HotelName'] = $this->http->FindSingleNode('./text()[1]', $hotel);
            $it['Address'] = $this->http->FindSingleNode('./text()[2]', $hotel) . ' ' . $this->http->FindSingleNode('./text()[3]', $hotel);
            $it['DetailedAddress'] = [
                'PostalCode' => $this->http->FindSingleNode('./text()[3]', $hotel, true, '/^([\d]+) /'),
                'Country'    => $this->http->FindSingleNode('./text()[4]', $hotel),
            ];
            $it['Phone'] = $this->http->FindSingleNode('./text()[5]', $hotel, true, '/([\d-]+)/');
            $it['Fax'] = $this->http->FindSingleNode('./text()[6]', $hotel, true, '/([\d-]+)/');
        }

        return $it;
    }
}
