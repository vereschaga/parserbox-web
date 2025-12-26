<?php

namespace AwardWallet\Engine\joyoflife\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "joyoflife/it-1.eml";

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

        return (stripos($body, 'Joie de Vivre Hotel') !== false || stripos($body, 'We look forward to your stay at  Wyndham Boston Beacon Hill') !== false) && stripos($body, 'Reservation Confirmation') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries['Kind'] = 'R';

        $itineraries['ConfirmationNumber'] =
            $this->http->FindSingleNode("//node()[contains(text(),'Confirmation Number')]/
													ancestor::td[1]/following-sibling::td[1]");

        $itineraries['GuestNames'] =
            $this->http->FindSingleNode("//node()[contains(text(),'Guest Name')]/
													ancestor::td[1]/following-sibling::td[1]");

        $arrivalDate =
            $this->http->FindSingleNode("//node()[contains(text(),'Arrival Date')]/
													ancestor::td[1]/following-sibling::td[1]");

        if (preg_match("#w*\,\s*(\w+)\s*(\d+)\,\s*(\d*)#", $arrivalDate, $match)) {
            $itineraries['CheckInDate'] = strtotime("$match[2] $match[1] $match[3]");
        }

        $departureDate =
            $this->http->FindSingleNode("//node()[contains(text(),'Departure Date')]/
													ancestor::td[1]/following-sibling::td[1]");

        if (preg_match("#w*\,\s*(\w+)\s*(\d+)\,\s*(\d*)#", $departureDate, $match)) {
            $itineraries['CheckOutDate'] = strtotime("$match[2] $match[1] $match[3]");
        }

        $itineraries['Guests'] = $this->http->FindSingleNode("//node()[contains(text(),'Number of Adults')]/ancestor::td[1]/following-sibling::td[1]");

        $itineraries['Kids'] = $this->http->FindSingleNode("//node()[contains(text(),'Number of Children')]/ancestor::td[1]/following-sibling::td[1]");

        $itineraries['Rooms'] = $this->http->FindSingleNode("//node()[contains(text(),'Number of Rooms')]/ancestor::td[1]/following-sibling::td[1]");

        $itineraries['RoomType'] =
            $this->http->FindSingleNode("//node()[contains(text(),'Room Type')]/
													ancestor::td[1]/following-sibling::td[1]");

        $s = $this->http->FindSingleNode("//node()[contains(text(),'Nightly Rate')]/ancestor::td[1]/following-sibling::td[1]");

        $moreRates = $this->http->FindNodes("//td[contains(normalize-space(.),'Nightly Rate') and not(.//td)]/ancestor::tr[1]/following-sibling::tr[count(td)=2][td[1][string-length(normalize-space(.))<=3]]/td[2]", null, '/^\D+(\d+\.\d{2}) on \w+ \d{1,2}$/');
        $allRates = [];

        if (!empty($moreRates)) {
            $allRates = array_merge($allRates, $moreRates);
        }

        if (preg_match('/(\$)([0-9]+\.[0-9]{2})/', $s, $matches)) {
            if ($matches[1] == '$') {
                $itineraries['Currency'] = 'USD';
            }
            $allRates[] = $matches[2];
        }

        if (1 < count($allRates)) {
            $min = min($allRates);
            $max = max($allRates);
            $itineraries['Rate'] = $min . '-' . $max . ' ' . ($itineraries['Currency'] ?? '') . ' per night';
        } elseif (1 === count($allRates)) {
            $itineraries['Rate'] = $allRates[0] . ' ' . ($itineraries['Currency'] ?? '') . ' per night';
        }

        $s = $this->http->FindSingleNode("//node()[contains(text(),'Local Taxes')]/
													ancestor::td[1]/following-sibling::td[1]");

        if (preg_match('/(\$)([0-9]+\.[0-9]{2})/', $s, $matches)) {
            if ($matches[1] == '$') {
                $itineraries['Currency'] = 'USD';
            }
            $itineraries['Taxes'] = (float) $matches[2];
        }

        $s = $this->http->FindSingleNode("//node()[contains(text(),'Total Charges with Tax')]/
													ancestor::td[1]/following-sibling::td[1]");

        if (preg_match('/(\$)([0-9]+\.[0-9]{2})/', $s, $matches)) {
            if ($matches[1] == '$') {
                $itineraries['Currency'] = 'USD';
            }
            $itineraries['Total'] = (float) $matches[2];
        }

        $itineraries['HotelName'] = $this->http->FindPreg('/We look forward to (?:welcoming you to|your stay at) (.*)\./U');

        $itineraries['Address'] =
            $this->http->FindSingleNode("//img[contains(@src,'footer_banner')][1]/
													ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                null, true, '/(.*?)\s*Tel/');

        if (empty($itineraries['Address']) && !empty($itineraries['HotelName'])) {
            $itineraries['Address'] = $itineraries['HotelName'];
        }

        $itineraries['Phone'] =
            $this->http->FindSingleNode("//img[contains(@src,'footer_banner')][1]/
													ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                null, true, '/.*\s*Tel\s*([0-9\-\.]+)/');

        if (empty($itineraries['Phone'])) {
            $itineraries['Phone'] = $this->http->FindSingleNode("//a[contains(@href, 'tel')]/@href", null, true, '/tel\:[ ]*(\d+)/');
        }

        return [
            "emailType"  => "reservation",
            "parsedData" => [
                "Itineraries" => [$itineraries],
            ],
        ];
    }
}
