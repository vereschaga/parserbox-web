<?php

namespace AwardWallet\Engine\venere\Email;

class HotelConfirmDe extends \TAccountChecker
{
    use \DateTimeTools;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = $this->parseEmail();

        return [
            'emailType'  => 'HotelConfirmDe',
            'parsedData' => ['Itineraries' => $itineraries],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match("#[@\w.]+venere\.com#", $headers['from'])
        || isset($headers['subject']) && stripos($headers['subject'], 'Venere.com-Buchungsbestatigung') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("#[@\w.]+venere\.com#", $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//strong[contains(normalize-space(.), 'Venere.com-Bestatigungsnummer')]")->length > 0;
    }

    public static function getEmailLanguages()
    {
        return ['de'];
    }

    protected function parseEmail()
    {
        $it = ['Kind' => 'R'];
        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->getNode('Bestätigungsnummer');
        // HotelName
        $it['HotelName'] = $this->http->FindSingleNode("//h1[contains(normalize-space(.), 'Hotelinformationen')]/ancestor::td/following::td[1]");
        // Address, Phone
        $Address = $this->http->FindSingleNode("//h1[contains(normalize-space(.), 'Hotelinformationen')]/ancestor::td/following::td[2]");

        if (preg_match("#(?<address>[\S\d\w\s]+)\s*Telefon:\s*(?<phone>[\d\S\s]+)#", $Address, $m)) {
            $it['Address'] = $m['address'];
            $it['Phone'] = $m['phone'];
        }
        // CheckInDate
        $CheckInDate = $this->getNode('Anreise');
        $it['CheckInDate'] = $this->getDate($CheckInDate);
        // CheckOutDate
        $CheckOutDate = $this->getNode('Abreise');
        $it['CheckOutDate'] = $this->getDate($CheckOutDate);
        // GuestNames
        $names = $this->http->FindNodes("//h1[contains(normalize-space(.), 'Zimmerdetails')]/ancestor::td/following::tr[1]//td[2]/span[position() < 3]");
        $it['GuestNames'] = [array_shift($names) . ' ' . array_shift($names)];
        // Guests
        $it['Guests'] = $this->http->FindSingleNode("//h1[contains(normalize-space(.), 'Zimmerdetails')]/ancestor::td/following::tr[1]//td[2]/span[position()=3]", null, true, "#([\d]+)#");
        // Rooms
        // Rate
        $it['Rate'] = $this->http->FindSingleNode("//h1[contains(normalize-space(.), 'Zahlungsdetails')]/ancestor::tr/following-sibling::tr[1]//td[2]");
        // RoomType
        $it['RoomType'] = $this->getNode('Zimmerpräferenzen');
        // Total, Currency
        $Total = $this->getNode('Gesamtbetrag');

        if (preg_match("#(?<total>[\d,]+)\s*(?<cur>[\w\S]{1,3})#", $Total, $tot)) {
            $tot['total'] = preg_replace("#([,])#", '.', $tot['total']);
            $it['Total'] = $tot['total'];
            $it['Currency'] = $tot['cur'] == '€' ? 'EUR' : 'USD';
        }
        // Status
        $Status = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Guten Tag Henning')]");

        if (preg_match("#[\w\S]+ ist (?<status>[\w\S]+)#", $Status, $stat)) {
            $stat['status'] = preg_replace("#[.]#", '', $stat['status']);

            if ($stat['status'] == 'bestatigt') {
                $it['Status'] = 'confirmed';
            }
        }

        return [$it];
    }

    protected function getNode($str)
    {
        return $this->http->FindSingleNode("//strong[contains(normalize-space(.), '$str')]/ancestor::td/following-sibling::td");
    }

    protected function getDate($nodeForDate)
    {
        $month = $this->monthNames['en'];
        $monthDe = $this->monthNames['de'];
        preg_match("#(?<dayOfWeek>[\w]+), (?<day>[\d]+). (?<month>[\w]+) (?<year>[\d]{4}) (?<hour>[\d.()]+)#", $nodeForDate, $chek);

        for ($i = 0; $i < 12; $i++) {
            if ($monthDe[$i] == $chek['month']) {
                $chek['month'] = preg_replace("#[\w]+#", $month[$i], $chek['month']);
                $chek['hour'] = preg_replace("#[()]#", '', $chek['hour']);
                $res = strtotime($chek['month'] . ' ' . $chek['day'] . ' ' . $chek['year'] . ' ' . $chek['hour']);
            }
        }

        return $res;
    }
}
