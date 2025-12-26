<?php

namespace AwardWallet\Engine\spg\Email;

// BCD
class BookingHtml2016En extends \TAccountChecker
{
    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->result['Kind'] = 'R';

        $common = $this->http->XPath->query('//td[contains(., "confirmation no.") and not(.//td)]/ancestor::table[1]');
        $address = $this->http->XPath->query('//p[contains(., "Reservations Manager")]/following-sibling::p[contains(., "T ") and contains(., "F ")]');

        if ($common->length > 0 && $address->length) {
            $this->parseResrvation($common->item(0), $address->item(0));
        }

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'joshua.chan@dowcorning.com') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Dow Corning_New Room Booking for AWA Seminar') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Renewal awaits. Thank you for choosing to experience') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@dowcorning.com') !== false;
    }

    protected function parseResrvation($common, $address)
    {
        $this->result['HotelName'] = $this->http->FindSingleNode('//*[contains(text(), "for choosing to experience")]', null, false, '/experience\s*(.+?)\s*where/');
        $this->result['Address'] = $this->http->FindSingleNode('span[2]', $address);
        $this->result['ConfirmationNumber'] = $this->getField('confirmation no.', $common, '/#\s*(\d+)/');
        $this->result['GuestNames'] = preg_split('/\s*,\s*/', $this->getField('guest name', $common));
        $this->result['CheckInDate'] = strtotime(str_replace('’', ' ', $this->getField('arrival date', $common)));
        $this->result['CheckOutDate'] = strtotime(str_replace('’', ' ', $this->getField('departure date', $common)));
        $this->result['RoomType'] = $this->getField('room type', $common);
        $this->result['Rate'] = $this->getField('room rate/ per room per night', $common);
    }

    protected function getField($name, $root, $regular = null)
    {
        return $this->http->FindSingleNode('.//td[contains(., "' . $name . '")]/following-sibling::td[1]', $root, false, $regular);
    }
}
