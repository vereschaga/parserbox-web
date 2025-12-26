<?php
/**
 * Created by PhpStorm.
 * User: Роман
 * Date: 10.03.2016
 * Time: 19:06.
 */

namespace AwardWallet\Engine\thed\Email;

class CasinoHotel extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'CasinoHotel',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'RESERVATIONS@thed.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['from']) && (stripos($headers['from'], 'RESERVATIONS@thed.com')) !== false
        || isset($headers['subject']) && stripos($headers['subject'], 'the D Reservation Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(.), 'D Las Vegas Casino Hotel')]")->length > 0;
    }

    protected function parseEmail()
    {
        $it = ['Kind' => 'R'];
        $record = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Confirmation #')]");

        if (preg_match("/[\w #]+: (?<code>[\w]{5})/", $record, $m)) {
            $it['ConfirmationNumber'] = $m['code'];
        }
        $date = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Arrival Date')]");

        if (preg_match("/[\w ]+: (?<date>[\d\/]+)/", $date, $math)) {
            $it['CheckInDate'] = strtotime($math['date']);
        }
        $arrdate = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Departure Date')]");

        if (preg_match("/[\w ]+: (?<arrdate>[\d\/]+)/", $arrdate, $mathec)) {
            $it['CheckOutDate'] = strtotime($mathec['arrdate']);
        }
        $text = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Thank you for choosing to stay at the D in Downtown Las Vegas')]");

        if ($text && $it['CheckInDate'] && $it['CheckOutDate']) {
            $it['HotelName'] = 'the D Las Vegas Casino Hotel';
            $it['Address'] = 'Downtown Las Vegas';
        }
        $guestName = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Guest Name')]");

        if (preg_match("/[\w ]+: (?<name>[\w ]+)/", $guestName, $b)) {
            $it['GuestNames'] = [$b['name']];
        }
        $rooms = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Number of Rooms')]");

        if (preg_match("/[\w]+: (?<crooms>[\d]{1})/", $rooms, $res)) {
            $it['Rooms'] = $res['crooms'];
        }

        return [$it];
    }
}
