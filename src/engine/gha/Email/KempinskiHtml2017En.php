<?php

namespace AwardWallet\Engine\gha\Email;

// bcdtravel
class KempinskiHtml2017En extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result[] = $this->parseHotel();

        return [
            'emailType'  => 'Hotel "Kempinski" format HTML from 2017 in "en"',
            'parsedData' => ['Itineraries' => $result],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@kempinski.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Your Reservation for ') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Hotel Reservation ') !== false
                && strpos($parser->getHTMLBody(), 'Kempinski') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@kempinski.com') !== false;
    }

    protected function parseHotel()
    {
        $result['Kind'] = 'R';
        $result['Status'] = $this->http->FindSingleNode('//text()[contains(.,"Hotel Reservation ")]', null, false, '/Hotel Reservation\s+([\w\s]+)/');
        $result['ConfirmationNumber'] = $this->http->FindSingleNode('//text()[contains(.,"Confirmation Number ")]', null, false, '/\s+(\d+)/');
        $result['HotelName'] = $this->http->FindSingleNode('//*[contains(@href, "mailto:reservations.")]/preceding-sibling::text()[3]');
        $result['Address'] = $this->http->FindSingleNode('//*[contains(@href, "mailto:reservations.")]/preceding-sibling::text()[2]');
        $phone = $this->http->FindSingleNode('//*[contains(@href, "mailto:reservations.")]/preceding-sibling::text()[1]');

        // Tel +86 21 6157 1688 · Fax +86 21 6093 7600
        if (preg_match('/Tel\s+([-+\d\s]+)(?:·\s+Fax\s+([-+\d\s]+))?/', $phone, $matches)) {
            $result['Phone'] = trim($matches[1]);

            if (isset($matches[2])) {
                $result['Fax'] = trim($matches[2]);
            }
        }

        $text = $this->http->FindSingleNode('//text()[contains(.,"Arrival Date")]/ancestor::td[1]');

        if (preg_match('/Arrival Date\s*:\s*(\d+ \w+ \d+)/', $text, $matches)) {
            $result['CheckInDate'] = strtotime($matches[1]);
        }

        if (preg_match('/Departure Date\s*:\s*(\d+ \w+ \d+)/', $text, $matches)) {
            $result['CheckOutDate'] = strtotime($matches[1]);
        }

        if (preg_match('/Number of Guests\s*:\s*(\d+)\s+Adult$/i', $text, $matches)) {
            $result['Guests'] = $matches[1];
        }

        if (preg_match('/Accomodation Type\s*:\s*(.+?)\s+Room Rate\s*:\s*(.+?)$/', $this->http->FindSingleNode('//text()[contains(.,"Accomodation Type")]/ancestor::td[1]'), $matches)) {
            $result['RoomType'] = $matches[1];
            $result['Rate'] = $matches[2];
        }

        $result['GuestNames'] = $this->http->FindSingleNode('//text()[contains(.,"Guest Information")]/ancestor::tr[1]/'
                . 'following-sibling::tr//text()[starts-with(., "Mr.")]');

        return $result;
    }
}
