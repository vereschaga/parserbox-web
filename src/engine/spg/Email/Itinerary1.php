<?php

namespace AwardWallet\Engine\spg\Email;

class Itinerary1 extends \TAccountChecker
{
    public function detectEmailFromProvider($from)
    {
        return preg_match("#@confirm\.starwoodhotels\.com#i", $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match("#@confirm\.starwoodhotels\.com#i", $headers['from'])
            || isset($headers['subject'])
                && (stripos($headers["subject"], 'IHRE RESERVIERUNG') || stripos($headers["subject"], 'Four Points Reservation'));
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($parser->getPlainBody(), "Here's where I'll be staying") !== false) {
            return true;
        }

        return stripos($body, 'Starwood Hotels & Resorts Worldwide, Inc.') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($parser->getPlainBody(), "Here's where I'll be staying") !== false) {
            return [
                "emailType"  => "shareReservation",
                "parsedData" => [
                    "Itineraries" => [$this->Share()], ],
            ];
        }

        if (stripos($body, 'Willkommen') !== false) {
            return [
                "emailType"  => "reservation",
                "parsedData" => [
                    "Itineraries" => [$this->Whotels_txt()], ],
            ];
        }

        if (stripos($body, 'Rate Details') !== false) {
            return [
                "emailType"  => "reservation",
                "parsedData" => [
                    "Itineraries" => [$this->Sheraton_eml()], ],
            ];
        }

        return [];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailLanguages()
    {
        return ["en", "de"];
    }

    public function Sheraton_eml()
    {
        $itineraries['Kind'] = 'R';
        $itineraries['ConfirmationNumber'] = $this->http->FindSingleNode("(//td[span[contains(text(),'Confirmation:')]])[last()]", null, true, '#Confirmation\:\s*(.*)#');
        $itineraries['HotelName'] = $this->http->FindSingleNode("(//tr[td[contains(text(),'Thanks for choosing us')]])[last()]", null, true, '#at\s*the\s*(.*?)\.\s*Whether#');

        if (isset($itineraries['HotelName'])) {
            $itineraries['Address'] = $this->http->FindSingleNode("(//td[*[contains(text(),'" . trim($itineraries['HotelName']) . "')]])[last()]", null, true, '#.*?(\d+.*)\s*Phone#');
        }
        $itineraries['Phone'] = $this->http->FindSingleNode("(//td[*[contains(text(),'" . trim($itineraries['HotelName']) . "')]])[last()]", null, true, '#Phone:\s*(.*?)\s*\S?\s*Fax#');
        $itineraries['Fax'] = $this->http->FindSingleNode("(//td[*[contains(text(),'" . trim($itineraries['HotelName']) . "')]])[last()]", null, true, '#Fax:\s*(.*)#');
        $CheckInDate = $this->http->FindSingleNode("(//tr[td[contains(text(),'Check In')]])[last()]");
        $CheckOutDate = $this->http->FindSingleNode("(//tr[td[contains(text(),'Check Out')]])[last()]");

        if (preg_match('#Check In\s*(.*?)\-?(\s+\d*\:\d*\s*\w+).*\*#', $CheckInDate, $match)) {
            $itineraries['CheckInDate'] = strtotime($match[1] . '' . $match[2]);
        }

        if (preg_match('#Check Out\s*(.*?)\-?(\s+\d*\:\d*\s*\w+).*\*#', $CheckOutDate, $match)) {
            $itineraries['CheckOutDate'] = strtotime($match[1] . '' . $match[2]);
        }

        $itineraries['Rooms'] = $this->http->FindSingleNode("(//tr[td[contains(text(),'Number of Rooms')]])[last()]", null, true, '#Number of Rooms\s*(.*)#');
        $itineraries['Guests'] = $this->http->FindSingleNode("(//tr[td[b[contains(text(),'Number of Adults')]]])[last()]", null, true, '#Number of Adults\s*(.*)#');
        $itineraries['Kids'] = $this->http->FindSingleNode("(//tr[td[b[contains(text(),'Number of Children')]]])[last()]", null, true, '#Number of Children\s*(.*)#');
        $itineraries['Rate'] = $this->http->FindSingleNode("(//td[contains(text(),'Rates for the night')])[last()]", null, true, '#(\d+\.\d+.*?)\s*per night#');
        $itineraries['RateType'] = $this->http->FindSingleNode("(//td[b[contains(text(),'Room Description')]])[last()]", null, true, '#Room\s*Description\s*(.*?)\s*\•#');
        $itineraries['2ChainName'] = $this->http->FindSingleNode("(//td[div and .//br])[last()]", null, true, '#(.*?)\.#');
        $itineraries['GuestNames'] = $this->http->FindSingleNode("(//tr[.//b[contains(text(),'Guest Name')]and not(.//tr)])[last()]", null, true, '#Guest Name\s*(.*)#');

        return $itineraries;
    }

    public function Whotels_txt()
    {
        $itineraries['Kind'] = 'R';
        $itineraries['ConfirmationNumber'] = $this->http->FindSingleNode("//td[.//span[contains(text(),'Bestätigung:')]and not(.//td)]", null, true, '#Bestätigung:\s*(.*)#');
        $CheckInDate = $this->http->FindSingleNode("//tr[td[.//span[contains(text(),'Ankunft')]and not(.//td)]]");
        $CheckOutDate = $this->http->FindSingleNode("(//tr[td[.//span[contains(text(),'Abreise')]and not(.//td)]])[1]");

        if (preg_match('#Ankunft\s*(.*?)\-?(\s+\d*\:\d*\s*\w+).*\*#', $CheckInDate, $match)) {
            $itineraries['CheckInDate'] = strtotime($match[1] . '' . $match[2]);
        }

        if (preg_match('#Abreise\s*(.*?)\-?(\s+\d*\:\d*\s*\w+).*\*#', $CheckOutDate, $match)) {
            $itineraries['CheckOutDate'] = strtotime($match[1] . '' . $match[2]);
        }
        $itineraries['Rooms'] = $this->http->FindSingleNode("//tr[td[.//span[contains(text(),'Anzahl der Zimmer')]and not(.//td)]]", null, true, '#Anzahl der Zimmer\s*(.*)#');
        $itineraries['Guests'] = $this->http->FindSingleNode("(//tr[.//span[contains(text(),'Anzahl')]and not(.//tr)])[3]", null, true, '#Anzahl Erwachsener\s*(.*)#');
        $itineraries['Kids'] = $this->http->FindSingleNode("//tr[td[.//span[contains(text(),'Anzahl Kinder')]and not(.//td)]]", null, true, '#Anzahl Kinder\s*(.*)#');
        $itineraries['RoomType'] = $this->http->FindSingleNode("//tr/td/p[.//*[contains(text(),'Zimmerbeschreibung')]]", null, true, '#Zimmerbeschreibung\s*(.*)#');
        $itineraries['Rate'] = $this->http->FindSingleNode("//p[.//*[contains(text(),'Details der Rate')]]", null, true, '#.*(\d+\.\d+.*?)\s*Steuern#');

        if (preg_match('#EURO#', $itineraries['Rate'])) {
            $itineraries['Currency'] = 'EUR';
        }
        $itineraries['HotelName'] = $this->http->FindSingleNode("(//a[.//*[contains(text(),'W Istanbul')]and not(.//td)])[1]");
        $itineraries['Address'] = $this->http->FindSingleNode("(//td[.//*[contains(text(),'W Istanbul')]and not(.//td)])[1]", null, true, '#' . $itineraries["HotelName"] . '(.*?)\s*Telefon:#');
        $itineraries['Phone'] = $this->http->FindSingleNode("(//td[.//*[contains(text(),'W Istanbul')]and not(.//td)])[1]", null, true, '#Telefon:(.*?)\s*Fax#');
        $itineraries['Fax'] = $this->http->FindSingleNode("(//td[.//*[contains(text(),'W Istanbul')]and not(.//td)])[1]", null, true, '#Fax:\s*(.*)#');
        $itineraries['GuestNames'] = $this->http->FindSingleNode("//tr[.//*[contains(text(),'Name des Gasts')]and not(.//tr)]", null, true, '#Name des Gasts\s*(.*)#');
        $itineraries['2ChainName'] = $this->http->FindSingleNode("(//td[.//span and .//br])[last()]", null, true, '#(.*?)\.#');

        return $itineraries;
    }

    private function Share()
    {
        $itineraries['Kind'] = 'R';
        $itineraries['ConfirmationNumber'] = CONFNO_UNKNOWN;
        $itineraries['CheckInDate'] = strtotime($this->http->FindPreg("/ll be staying\s+(.*?)\s+-/ims"));
        $itineraries['CheckOutDate'] = strtotime($this->http->FindPreg("/ll be staying\s+.*?\s+-\s+(.*?)\./ims"));
        $itineraries['GuestNames'] = $this->http->FindPreg("/Warm Regards,.*?>([\w\s]+)</ims");
        $itineraries['HotelName'] = $this->http->FindSingleNode("//text()[contains(.,'ll be staying')]/following::text()[normalize-space(.)][1]");
        $itineraries['Address'] = implode(", ", $this->http->FindNodes("//text()[contains(.,'ll be staying')]/following::text()[normalize-space(.)][position()=2 or position()=3]"));
        $itineraries['Phone'] = trim($this->http->FindSingleNode("(//text()[contains(., 'Phone:')])[1]", null, true, "#Phone:\s+([\d\s\(\)-]+)#"));
        $itineraries['Fax'] = trim($this->http->FindSingleNode("(//text()[contains(., 'Fax:')])[1]", null, true, "#Fax:\s+([\d\s\(\)-]+)#"));

        return $itineraries;
    }
}
