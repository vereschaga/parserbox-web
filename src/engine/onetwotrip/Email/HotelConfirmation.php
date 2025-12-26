<?php

namespace AwardWallet\Engine\onetwotrip\Email;

// it-3801643.eml

class HotelConfirmation extends \TAccountChecker
{
    public $mailFiles = "onetwotrip/it-3801643.eml";
    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@onetwotrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'support.usa@onetwotrip.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//a[contains(@href,"//emailnotify.onetwotrip.com/")]')->length > 0;
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
        $it['ConfirmationNumber'] = $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Booking Number:") and not(.//td)]', null, true, '/Booking Number: ([\d\w]+)/');
        $nodes = $this->http->XPath->query('//tr[starts-with(normalize-space(.),"Your Reservation") and not(.//tr)]/following-sibling::tr[./td/table][1]');

        if ($nodes->length > 0) {
            $reservation = $nodes->item(0);
            $it['HotelName'] = trim($this->http->FindSingleNode('.//table[1]//td[2]/div[1]', $reservation), '*');
            $addressLines = $this->http->XPath->query('.//table[1]//td[2]/div[not(position()=1) and not(./*)]', $reservation);

            if ($addressLines->length === 2) {
                $address1 = $this->http->FindSingleNode('.', $addressLines->item(0));
                $address2 = $this->http->FindSingleNode('.', $addressLines->item(1));
                $it['Address'] = $address1 . ', ' . $address2;
                $cityAndCountry = array_map('trim', explode(',', $address2));

                if (count($cityAndCountry) === 2) {
                    $it['DetailedAddress'] = [
                        'CityName' => $cityAndCountry[0],
                        'Country'  => $cityAndCountry[1],
                    ];
                }
            }
            $it['Phone'] = $this->http->FindSingleNode('.//table[1]//td[2]//div[starts-with(normalize-space(.),"Phone:") and not(./div)]', $reservation, true, '/Phone: ([-\d]+)/');
            $checkInDate = $this->http->FindSingleNode('.//table[1]//td[3]/div[starts-with(normalize-space(.),"Check In")]/following-sibling::div[1]', $reservation);
            $checkOutDate = $this->http->FindSingleNode('.//table[1]//td[3]/div[starts-with(normalize-space(.),"Check Out")]/following-sibling::div[1]', $reservation);

            if ($checkInDate && $checkOutDate) {
                $it['CheckInDate'] = strtotime(str_replace(',', '', $checkInDate));
                $it['CheckOutDate'] = strtotime(str_replace(',', '', $checkOutDate));
            }
        }
        $nodes = $this->http->XPath->query('//tr[starts-with(normalize-space(.),"Reservation Details") and not(.//tr)]/ancestor::tr[1]/following-sibling::tr[./td/table][1]');

        if ($nodes->length > 0) {
            $details = $nodes->item(0);
            $guestNames = $this->http->FindSingleNode('.//tr[starts-with(normalize-space(.),"Traveller") and not(.//tr)]/td[2]', $details);
            $it['GuestNames'] = array_map('trim', explode(',', $guestNames));
            $it['RoomType'] = $this->http->FindSingleNode('.//tr[starts-with(normalize-space(.),"Room Type") and not(.//tr)]/td[2]', $details);
            $it['Rooms'] = $this->http->FindSingleNode('.//tr[starts-with(normalize-space(.),"Number of Rooms") and not(.//tr)]/td[2]', $details);
            $it['Guests'] = $this->http->FindSingleNode('.//tr[starts-with(normalize-space(.),"Number of Guests") and not(.//tr)]/td[2]', $details);
            $it['RoomTypeDescription'] = $this->http->FindSingleNode('.//tr[starts-with(normalize-space(.),"Room Details") and not(.//tr)]/td[2]', $details);
        }
        $nodes = $this->http->XPath->query('//tr[starts-with(normalize-space(.),"Booking Cost") and not(.//tr)]/following-sibling::tr[./td/div][1]');

        if ($nodes->length > 0) {
            $cost = $nodes->item(0);
            $it['Cost'] = $this->http->FindSingleNode('.//div[starts-with(normalize-space(.),"Subtotal") and not(./div)]', $cost, true, '/([.\d]+) USD/');
            $it['Taxes'] = $this->http->FindSingleNode('.//div[starts-with(normalize-space(.),"Tax") and not(./div)]', $cost, true, '/([.\d]+) USD/');
            $xpath = './/div[starts-with(normalize-space(.),"Total") and not(./div)]';
            $it['Total'] = $this->http->FindSingleNode($xpath, $cost, true, '/([.\d]+) USD/');
            $it['Currency'] = $this->http->FindSingleNode($xpath, $cost, true, '/[.\d]+ (USD)/');
        }

        return $it;
    }
}
