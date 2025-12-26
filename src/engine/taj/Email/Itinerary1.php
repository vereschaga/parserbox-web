<?php

namespace AwardWallet\Engine\taj\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATE_FORMAT = ' D, j M y';
    public const DATE_TIME_FORMAT = 'M j,Y h:i A';
    public $mailFiles = "taj/it-2.eml";

    private $itineraries = [];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && $this->СheckMail($headers["from"]);
    }

    public function detectEmailFromProvider($from)
    {
        return in_array($from, ["reservations@tajhotels.com"]);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // always returns
        return stripos($parser->getHTMLBody(), '@tajhotels.com') !== false
            || stripos($parser->getHTMLBody(), 'Thank you very much for booking at www.thegatewayhotels.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $node = $this->http->XPath->query("//a[@href[contains(., 'umaidbhawan.jodhpur@tajhotels.com')]]");

        if ($node->length) {
            $this->itineraries['Kind'] = 'R';

            if (preg_match("/\d*$/", $this->http->FindSingleNode("//text()[contains(., 'Confirmation Number:')]"), $matches)) {
                $this->itineraries['ConfirmationNumber'] = $matches[0];
            }

            if (!isset($this->itineraries['ConfirmationNumber']) || !$this->itineraries['ConfirmationNumber']) {
                $conf = preg_match("#Confirmation Number\s*:\s*([^\n]+)#", $parser->getHtmlBody(), $m) ? $m[1] : null;
                $this->itineraries['ConfirmationNumber'] = preg_match("#([A-Z\d\-]+)#", $conf, $m) ? $m[1] : null;
            }

            if ($result = preg_replace("/Thank\syou\sfor\schoosing/", "", $this->http->FindSingleNode("//p[contains(., 'Thank you for choosing')]"))) {
                $this->itineraries['HotelName'] = $result;
            }

            if ($CheckInDate = $this->http->FindPreg('/Arrival\son\:([^\<]*)/')) {
                $this->itineraries['CheckInDate'] = $this->_buildDate(date_parse_from_format(self::DATE_FORMAT, $CheckInDate));
            }

            if ($CheckOutDate = $this->http->FindPreg('/Departure\son\:([^\<]*)/')) {
                $this->itineraries['CheckOutDate'] = $this->_buildDate(date_parse_from_format(self::DATE_FORMAT, $CheckOutDate));
            }
            $this->itineraries['Address'] = $this->http->FindPreg('/Hotel\sDetails\:(.*)Tel/');
            $this->itineraries['Phone'] = $this->http->FindPreg('/Tel\:([^\,]*)/');

            if ($Fax = $this->http->FindPreg('/Fax\:([^\,]*)Email/')) {
                $this->itineraries['Fax'] = $Fax;
            }
            $this->itineraries['GuestNames'] = $this->http->FindPreg('/Guest\sName\:([^\<]*)/');
            $this->itineraries['Guests'] = $this->http->FindPreg('/Number\sof\sPersons\:.*(\d+)/');
            $this->itineraries['Rooms'] = $this->http->FindPreg('/Number\sof\sRooms\:.*(\d+)/');
            $this->itineraries['Rate'] = $this->http->FindPreg('/Rate\sper\sRoom\:[^\d]*([\d\.]*)/');
            $this->itineraries['RateType'] = $this->http->FindPreg('/Accommodation\sType\:([^\<]*)/');

            if ($rateType = preg_replace("<br>", "", $this->http->FindPreg('/Rate\sInformation\:(.*)Total\sRate/ms'))) {
                $rateType = preg_replace("#<.*?>#ims", ' ', $rateType);
                $rateType = preg_replace("#&nbsp;#ims", ' ', $rateType);
                $rateType = preg_replace("#\s+#ims", ' ', $rateType);
                $this->itineraries['RoomTypeDescription'] = trim($rateType);
            }
            $this->itineraries['CancellationPolicy'] = 'Cancellation Deadline: ' . $this->http->FindSingleNode("//text()[contains(., 'Cancellation Deadline')]/following-sibling::text()[1]");
            $this->itineraries['Total'] = $this->http->FindPreg('/Total\sRate\:[^\d]*([\d\.]*)/');
            $this->itineraries['Currency'] = $this->http->FindPreg('/Total\sRate\:([^\d]*)/');
            $this->itineraries['AccountNumbers'] = $this->http->FindPreg('/Taj\sInnerCircle\sNumber\:([^\<]*)/');

            return [
                'emailType'  => 'reservations',
                'parsedData' => [
                    'Itineraries' => [$this->itineraries],
                ],
            ];
        } else {
            $hotel = [];
            $hotel['name'] = $this->http->FindSingleNode("//td[@id='hotel_address']/div[1]");
            $hotel['address'] = $this->http->FindSingleNode("//td[@id='hotel_address']/text()[2]") . ' ' . $this->http->FindSingleNode("//td[@id='hotel_address']/text()[3]");
            $hotel['phone'] = preg_replace("/Telephone\:\s/", "", $this->http->FindSingleNode("//td[@id='hotel_address']/text()[4]"));
            $confNumber = $this->http->XPath->query("//table[@id='resHeadingInformation_table']");

            if ($confNumber->length > 0) {
                $i = 0;

                foreach ($confNumber as $number) {
                    $this->itineraries[$i]['Kind'] = 'R';
                    $this->itineraries[$i]['ConfirmationNumber'] = $this->http->FindSingleNode(".//span[2]", $number);
                    $this->itineraries[$i]['HotelName'] = $hotel['name'];
                    $i++;
                }
            }
            $reservations = $this->http->XPath->query("//table[@id='reservation_table']");

            if ($reservations->length > 0) {
                $i = 0;

                foreach ($reservations as $reservation) {
                    if ($checkIn = $this->http->FindSingleNode(".//td[contains(text(), 'Arrival/Check-in')]/following-sibling::td[1]", $reservation)) {
                        if (preg_match("/([^\,]*\,\d{4}\s)[^\d]*(.*)$/", $checkIn, $matches)) {
                            $this->itineraries[$i]['CheckInDate'] = $this->_buildDate(date_parse_from_format(self::DATE_TIME_FORMAT, $matches[1] . $matches[2]));
                        }
                    }

                    if ($checkIn = $this->http->FindSingleNode(".//td[contains(text(), 'Departure/Check-out')]/following-sibling::td[1]", $reservation)) {
                        if (preg_match("/([^\,]*\,\d{4}\s)[^\d]*(.*)$/", $checkIn, $matches)) {
                            $this->itineraries[$i]['CheckOutDate'] = $this->_buildDate(date_parse_from_format(self::DATE_TIME_FORMAT, $matches[1] . $matches[2]));
                        }
                    }
                    $this->itineraries[$i]['Address'] = $hotel['address'];

                    if ($hotel['phone']) {
                        $this->itineraries[$i]['Phone'] = $hotel['phone'];
                    }
                    $this->itineraries[$i]['GuestNames'] = [$this->http->FindSingleNode(".//div[@class='traveller']/span[2]", $reservation)];

                    if (preg_match("/^\d*/", $this->http->FindSingleNode(".//td[contains(text(), 'Occupancy')]/following-sibling::td", $reservation), $matches)) {
                        $this->itineraries[$i]['Guests'] = $matches[0];
                    }

                    if (preg_match("/Children\:\s(\d*)/", $this->http->FindSingleNode(".//td[contains(text(), 'Children:')]", $reservation), $matches)) {
                        $this->itineraries[$i]['Kids'] = $matches[1];
                    }

                    if (preg_match("/^\d*/", $this->http->FindSingleNode(".//td[contains(text(), 'Room Type')]/following-sibling::td[1]", $reservation), $matches)) {
                        $this->itineraries[$i]['Rooms'] = $matches[0];
                    }
                    $this->itineraries[$i]['RateType'] = $this->http->FindSingleNode(".//td[contains(text(), 'Rate Type')]/following-sibling::td[1]", $reservation);
                    $curr_policy_table = $this->http->XPath->query("//span[contains(text(), 'Room/Rate Details & Policies Reservation Number: " . $this->itineraries[$i]['ConfirmationNumber'] . "')]/ancestor::table[@id='policies_table']")->item(0);
                    $this->itineraries[$i]['CancellationPolicy'] = $this->http->FindSingleNode(".//td[contains(text(), 'Cancellation Policy')]/following-sibling::td", $curr_policy_table);
                    $this->itineraries[$i]['RoomTypeDescription'] = $this->http->FindSingleNode(".//td[contains(text(), 'Room Description')]/following-sibling::td", $curr_policy_table);

                    if (preg_match("/([^\s]*)\s(.*)$/", $this->http->FindSingleNode(".//td[contains(text(), 'Room total')]/following-sibling::td[1]", $reservation), $matches)) {
                        $this->itineraries[$i]['Total'] = $matches[2];
                        $this->itineraries[$i]['Currency'] = $matches[1];
                    }

                    if (preg_match("/\d*$/", $this->http->FindSingleNode(".//span[contains(text(), 'Taj InnerCircle Number:')]", $reservation), $matches)) {
                        $this->itineraries[$i]['AccountNumbers'] = $matches[0];
                    }
                    $i++;
                }
            }

            return [
                'emailType'  => 'reservations',
                'parsedData' => [
                    'Itineraries' => $this->itineraries,
                ],
            ];
        }
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    private function СheckMail($input = '')
    {
        preg_match('/([\.@]tajhotels\.com)/ims', $input, $matches);

        return (isset($matches[0])) ? true : false;
    }

    private function _buildDate($parsedDate)
    {
        return mktime((isset($parsedDate['hour'])) ? $parsedDate['hour'] : 0, (isset($parsedDate['minute'])) ? $parsedDate['minute'] : 0, 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }
}
