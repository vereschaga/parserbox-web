<?php

namespace AwardWallet\Engine\loews\Email;

// parsers with similar formats: slh/YourReservationConfirmation

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "loews/it-2.eml, loews/it-3495676.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && $this->checkMails($headers["from"])
            && (stripos($headers['subject'], "Loews Reservation"));
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[\.@]loewshotels\.com/", $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (stripos($parser->getHTMLBody(), 'From: Loews Royal Pacific Resort')) {
            return true;
        }

        if (stripos($parser->getHTMLBody(), 'greetings from the Loews Royal Pacific Resort')) {
            return true;
        }

        return stripos($parser->getHTMLBody(), 'We look forward to welcoming you to Loews Hotels') !== false
            || $this->checkMails($parser->getHTMLBody());
    }

    public function checkMails($input = '')
    {
        preg_match('/([\.@]loewshotels\.com)/ims', $input, $match);

        return (isset($match[0])) ? true : false;
    }

    public function TypeB()
    {
        $it = ['Kind' => 'R'];

        $it['ConfirmationNumber'] = $this->http->FindSingleNode("(//*[contains(text(), 'Reservation Number')]/ancestor::tr[1]/td[3])[1]");
        $it['GuestNames'] = $this->http->FindSingleNode("(//*[contains(text(), 'Guest Name')]/ancestor::tr[1]/td[3])[1]");

        $it['Guests'] = $this->http->FindSingleNode("(//*[contains(text(), 'Number of Guests')]/ancestor::tr[1]/td[3])[1]");

        $it['CheckInDate'] = strtotime($this->http->FindSingleNode("(//*[contains(text(), 'Arrival Date')]/ancestor::tr[1]/td[3])[1]"));
        $it['CheckOutDate'] = strtotime($this->http->FindSingleNode("(//*[contains(text(), 'Departure Date')]/ancestor::tr[1]/td[3])[1]"));

        $it['Phone'] = $this->http->FindSingleNode("(//*[contains(text(), 'Hotel Number')]/ancestor::tr[1]/td[3])[1]");

        $it['CancellationPolicy'] = $this->http->FindSingleNode("(//*[contains(text(), 'Cancellation')]/ancestor::tr[1]/td[3])[1]");

        $infos = $this->http->FindNodes("//*[contains(text(), 'All rights reserved.')]//text()");
        $info = end($infos);

        if (preg_match("#^(.*?Resort)\s*\.\s*(.*?)$#i", $info, $m)) {
            $it['HotelName'] = $m[1];
            $it['Address'] = $m[2];
        } elseif (preg_match("#\n\s*([^\n]*?Resort)\s*\n\s*([^\n]*?\s*\n\s*[^\n]+)$#ms", text($this->http->Response["body"]), $m)) {
            $it['HotelName'] = $m[1];
            $it['Address'] = nice($m[2]);
        }

        return $it;
    }

    public function TypeA()
    {
        $itineraries = null;
        $date = '';
        $itineraries['Kind'] = 'R';
        $itineraries['Address'] = implode(', ', $this->http->FindNodes("//p[count(span) = 4 or count(span) = 3 and not(contains(., 'cancellation'))]/span"));
        $itineraries['ConfirmationNumber'] = $this->http->FindSingleNode("//span[contains(., 'Confirmation Number')]/following-sibling::span");
        $guest = rtrim(str_replace('Dear ', '', $this->http->FindSingleNode("//span[contains(., 'Dear')]")), ',');
        $itineraries['GuestNames'] = [$guest];
        $data = $this->http->XPath->query("//tr[count(td) =4 and not(contains(., 'Arrival'))]");

        foreach ($data as $row) {
            $date = $this->http->FindSingleNode(".//td[1]", $row);
            $guests = $this->http->FindSingleNode(".//td[3]", $row);
            preg_match('/\d+\s?Adults?/', $guests, $adults);

            if (isset($adults[0])) {
                $itineraries['Guests'] = preg_replace('/\s?Adults?/', '', $adults[0]);
            }
            preg_match('/\d+\s?Child(ren)?/', $guests, $kids);

            if (isset($kids[0])) {
                $itineraries['Kids'] = preg_replace('/\s?\s?Child(ren)?/', '', $kids[0]);
            }
        }
        $additionalInfo = $this->http->XPath->query("//tr[count(td) =5 and not(contains(., 'Rate Type'))]");

        foreach ($additionalInfo as $info) {
            $itineraries['Rate'] = $this->http->FindSingleNode(".//td[2]", $info) . '/night';
            $itineraries['CheckInDate'] = strtotime($date . ' ' . $this->http->FindSingleNode(".//td[4]", $info));
            $itineraries['CheckOutDate'] = strtotime($date . ' ' . $this->http->FindSingleNode(".//td[5]", $info));
        }
        $itineraries['HotelName'] = $this->http->FindSingleNode("//p[contains(., 'Reservations Department')]/preceding-sibling::p[1]");

        return $itineraries;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->http->FindSingleNode("//img[contains(@src,'/images/RPR_confirm_top.jpg') or contains(@src, 'RPR_Conf_Top_2014_Passbookv3.jpg')]/@src")) {
            return false;
        }

        if ($this->http->FindSingleNode("//p[contains(., 'Reservations Department')]")) {
            $it = $this->TypeA();
        } elseif ($this->http->FindSingleNode("//*[contains(text(), 'RESERVATION INFORMATION')]")) {
            $it = $this->TypeB();
        }

        return [
            "emailType"  => "reservation",
            "parsedData" => [
                "Itineraries" => [$it],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }
}
