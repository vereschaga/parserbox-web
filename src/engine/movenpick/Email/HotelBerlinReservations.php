<?php

namespace AwardWallet\Engine\movenpick\Email;

class HotelBerlinReservations extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = "movenpick/it-6149261.eml";

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'hotel.berlin-reservierung@movenpick.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@movenpick.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//node()[contains(.,"by Mövenpick Hotel Berlin")]')->length > 0
            || $this->http->XPath->query('//a[contains(@href,"mailto:hotel.berlin-reservierung@movenpick.com")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'HotelBerlinReservations',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['de'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function ParseEmail()
    {
        $it = [];
        $it['Kind'] = 'R';
        $guestNames = $this->http->FindSingleNode('//td[normalize-space(.)="Gastname:"]/following-sibling::td[normalize-space(.)!=""][1]');
        $it['GuestNames'] = array_map('trim', explode(',', $guestNames));

        if ($dateCheckIn = $this->http->FindSingleNode('//td[normalize-space(.)="Anreisedatum:"]/following-sibling::td[normalize-space(.)!=""][1]', null, true, '/(\d{1,2}[^\d]{4,}\d{4})\s*$/')) {
            $it['CheckInDate'] = strtotime($this->dateStringToEnglish($dateCheckIn));
        }

        if ($dateCheckOut = $this->http->FindSingleNode('//td[normalize-space(.)="Abreisedatum:"]/following-sibling::td[normalize-space(.)!=""][1]', null, true, '/(\d{1,2}[^\d]{4,}\d{4})\s*$/')) {
            $it['CheckOutDate'] = strtotime($this->dateStringToEnglish($dateCheckOut));
        }
        $it['RoomType'] = $this->http->FindSingleNode('//td[normalize-space(.)="Zimmertyp:"]/following-sibling::td[normalize-space(.)!=""][1]');
        $it['Rate'] = $this->http->FindSingleNode('//td[normalize-space(.)="Zimmerrate:"]/following-sibling::td[normalize-space(.)!=""][1]');
        $persons = $this->http->FindSingleNode('//td[normalize-space(.)="Personen pro Zimmer:"]/following-sibling::td[normalize-space(.)!=""][1]');

        if (preg_match('/(\d+)\s+Erwachsen/i', $persons, $matches)) {
            $it['Guests'] = (int) $matches[1];
        }

        if (preg_match('/(\d+)\s+Kind/i', $persons, $matches)) { // взято из переводчика, необходимо найти уточнение в реальном письме
            $it['Kids'] = (int) $matches[1];
        }
        $it['ConfirmationNumber'] = $this->http->FindSingleNode('//td[normalize-space(.)="Bestätigungsnummer:"]/following-sibling::td[normalize-space(.)!=""][1]', null, true, '/^\s*([A-Z\d]+)\s*$/');
        $contactNumbers = $this->http->XPath->query('//*[normalize-space(.)="Kontaktnummern" and (name(.)="b" or name(.)="strong")]');

        if ($contactNumbers->length > 0) {
            $root = $contactNumbers->item(0);
            $it['HotelName'] = $this->http->FindSingleNode('./preceding-sibling::*[normalize-space(.)!="" and (name(.)="b" or name(.)="strong")]', $root);
            $addressRows = $this->http->FindNodes('./preceding-sibling::text()[normalize-space(.)!=""]', $root);

            if (count($addressRows) === 3) {
                $it['Address'] = trim($addressRows[0], ',');
                preg_match('/^\s*(\d+)\s+(.+)/', trim($addressRows[1], ','), $postalcodeAndCity);
                $it['DetailedAddress'] = [
                    'PostalCode' => $postalcodeAndCity[1],
                    'CityName'   => $postalcodeAndCity[2],
                    'Country'    => $addressRows[2],
                ];
            }
            $it['Phone'] = $this->http->FindSingleNode('./following-sibling::text()[starts-with(normalize-space(.),"Telefon:")]', $root, true, '/:\s*([^:]+)$/');
            $it['Fax'] = $this->http->FindSingleNode('./following-sibling::text()[starts-with(normalize-space(.),"Fax:")]', $root, true, '/:\s*([^:]+)$/');
        }

        return $it;
    }
}
