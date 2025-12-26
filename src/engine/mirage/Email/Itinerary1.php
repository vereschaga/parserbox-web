<?php

namespace AwardWallet\Engine\mirage\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATE_FORMAT = "m/d/Y";
    public $mailFiles = "mirage/it-2.eml";
    private $itineraries = [];

    public function detectEmailByHeaders(array $headers)
    {
        return false; // covered by 786

        return (isset($headers["from"]) && $this->CheckMail($headers["from"]))
            || (isset($headers["subject"]) && strpos($headers["subject"], 'MGM'));
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@mgmresorts.com") !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (stripos($parser->getHTMLBody(), '@mgmresorts.com') !== false
            || stripos($parser->getHTMLBody(), 'mgmgrand.com') !== false
            || stripos($parser->getHTMLBody(), 'Beau Rivage Resort') !== false
        ) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->itineraries['Kind'] = 'R';
        $reservationDetails = [
            'ConfirmationNumber' => ['Confirmation Number', 'Confirmation No.'],
            'HotelName'          => 'Hotel:',
            'Guests'             => 'Number of Guests:',
            'RoomType'           => 'Room Type:',
            'Status'             => 'Status:',
            'Rooms'              => 'Number of Rooms:',
            'GuestNames'         => 'Guest Name:',
        ];

        foreach ($reservationDetails as $fieldName => $emailTexts) {
            if (!is_array($emailTexts)) {
                $emailTexts = [$emailTexts];
            }

            foreach ($emailTexts as $emailText) {
                if (count($result = preg_split("/\:\s/", $this->http->FindSingleNode("//text()[contains(., '" . $emailText . "')]"))) > 1) {
                    if (isset($result[1])) {
                        $this->itineraries[$fieldName] = $result[1];

                        break;
                    }
                } elseif ($data = $this->http->FindSingleNode("//text()[contains(., '" . $emailText . "')]/following::text()[normalize-space(.)][1]")) {
                    $this->itineraries[$fieldName] = $data;

                    break;
                }
            }
        }

        if ($ArrivalDepartureRes = preg_split("/\s/", $this->http->FindSingleNode("(//text()[contains(., 'Arrival date:')] | //text()[contains(., 'Arrival:')]/following::node()[normalize-space(.)!=''][1])[1]"))) {
            if (isset($ArrivalDepartureRes[2])) {
                $this->itineraries['CheckInDate'] = $this->_buildDate(date_parse_from_format(self::DATE_FORMAT, $ArrivalDepartureRes[2]));
            }

            if (isset($ArrivalDepartureRes[5])) {
                $this->itineraries['CheckOutDate'] = $this->_buildDate(date_parse_from_format(self::DATE_FORMAT, $ArrivalDepartureRes[5]));
            } else {
            }
        }

        if (empty($this->itineraries['CheckInDate'])) {
            $this->itineraries['CheckInDate'] = $this->_buildDate(date_parse_from_format(self::DATE_FORMAT, $this->http->FindSingleNode("//text()[contains(., 'Arrival:')]/following::text()[normalize-space(.)!=''][1]")));
        }

        if (empty($this->itineraries['CheckOutDate'])) {
            $this->itineraries['CheckOutDate'] = $this->_buildDate(date_parse_from_format(self::DATE_FORMAT, $this->http->FindSingleNode("//text()[contains(., 'Departure:')]/following::text()[normalize-space(.)!=''][1]")));
        }

        $this->itineraries['Total'] = $this->http->FindSingleNode("//text()[contains(., 'Total Amount:')]/following::text()[normalize-space(.)!=''][1]", null, true, '#^\s*\$\s*([\d\.]+)\s*$#');
        $this->itineraries['Currency'] = str_replace("$", "USD", $this->http->FindSingleNode("//text()[contains(., 'Total Amount:')]/following::text()[normalize-space(.)!=''][1]", null, true, '#^\s*(\$)\s*[\d\.]+\s*$#'));

        if (empty($this->itineraries['GuestNames']) && preg_match('/Dear\s([A-Z]*)\,/', $this->http->FindSingleNode("//text()[contains(., 'Dear')]"), $matches)) {
            $this->itineraries['GuestNames'] = [$matches[1]];
        }

        if (empty($this->itineraries['GuestNames'])) {
            $this->itineraries['GuestNames'] = [$this->http->FindSingleNode("//text()[contains(., 'Name:')]/following::text()[normalize-space()!=''][1]")];
        }
        $this->itineraries['Address'] = trim($this->http->FindSingleNode("//text()[contains(., 'Privacy Policy')]/ancestor::td/text()[3]") . " " . $this->http->FindSingleNode("//text()[contains(., 'Privacy Policy')]/ancestor::td/text()[4]"));

        if (preg_match('/Thank you for choosing (.+?) as your (.+?) destination/ims', $this->http->FindSingleNode('(//text()[contains(., "Thank you for choosing")])[1]'), $matches)) {
            $this->itineraries['HotelName'] = $matches[1];

            if ($matches[2] !== 'resort') {
                $this->itineraries['Address'] = $matches[2];
            } else {
                $this->itineraries['Address'] = $matches[1];
            }
        } elseif (!empty($src = $this->http->FindSingleNode("//img[contains(@src,'mgmresorts.com/content/dam/MGM')]/@src")) && preg_match("#\/MGM\/(.+?)\/website-graphics#", $src, $matches)) {
            $name = ucwords(str_replace("-", " ", str_replace("_", " ", $matches[1])));
            $this->itineraries['HotelName'] = $name;
            $this->itineraries['Address'] = $name;
        }

        return [
            'emailType'  => 'Itinerary1',
            'parsedData' => [
                'Itineraries' => [$this->itineraries],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    private function CheckMail($input = '')
    {
        preg_match('/(@mgmresorts\.com)/ims', $input, $matches);

        return (isset($matches[0])) ? true : false;
    }

    private function _buildDate($parsedDate)
    {
        return mktime((isset($parsedDate['hour'])) ? $parsedDate['hour'] : 0, (isset($parsedDate['minute'])) ? $parsedDate['minute'] : 0, 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }
}
