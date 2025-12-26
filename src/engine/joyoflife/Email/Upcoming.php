<?php

namespace AwardWallet\Engine\joyoflife\Email;

class Upcoming extends \TAccountChecker
{
    public $mailFiles = "joyoflife/it-2.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match("/@crm\.data2gold\.com/i", $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from'])
                    && (stripos($headers['from'], 'resinquiry@crm.data2gold.com') !== false
                    || stripos($headers['from'], 'hotellincolnreservations@jdvhotels.com') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return (stripos($body, 'Joie de Vivre Hotel') && stripos($body, 'Your Upcoming Stay with Us')) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries['Kind'] = 'R';

        $itineraries['ConfirmationNumber'] = $this->http->FindPreg('/Reservation #([0-9]{7})/');

        $arrivalDate = $this->http->FindPreg('/to your arrival on (.*)\(/U');

        if (preg_match("#w*\,\s*(\w+)\s*(\d+)\,\s*(\d*)#", $arrivalDate, $match)) {
            $itineraries['CheckInDate'] = strtotime("$match[2] $match[1] $match[3]");
        }

        $departureDate = $this->http->FindPreg('/with a check-out date of (.*)\)/U');

        if (preg_match("#w*\,\s*(\w+)\s*(\d+)\,\s*(\d*)#", $departureDate, $match)) {
            $itineraries['CheckOutDate'] = strtotime("$match[2] $match[1] $match[3]");
        }

        $itineraries['HotelName'] = $this->http->FindPreg('/Thank you for choosing (.*),/U');

        $itineraries['Address'] =
                $this->http->FindSingleNode("//img[contains(@src,'footer_banner')][1]/
													ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                                            null, true, '/(.*?)\s*Tel/');

        $itineraries['Phone'] =
                $this->http->FindSingleNode("//img[contains(@src,'footer_banner')][1]/
													ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                                            null, true, '/.*\s*Tel\s*([0-9\-\.]+)/');

        return [
            "emailType"  => "reservation",
            "parsedData" => [
                "Itineraries" => [$itineraries],
            ],
        ];
    }
}
