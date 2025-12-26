<?php

namespace AwardWallet\Engine\preferred\Email;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "preferred/it-6702234.eml";

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting provider
        $condition1 = $this->http->XPath->query('//a[contains(@href,"//www.iprefer.com") or contains(@href,"//www.phgsecure.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//img[contains(@src,"/phr-lifestyle-black.") or contains(@src,"/PH-rebrand_logo.") or contains(@alt,"Preferred Hotel")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        // Detecting hotel
        $condition1 = $this->http->XPath->query('//a[contains(@href,"//www.royalplazagroup.com.sg") or contains(@href,"//www.royalplaza.com.sg")]')->length === 0;
        $condition2 = $this->http->XPath->query('//img[contains(@src,"/RP-SG-Logo") or contains(@src,"/RoyalPlazaonScottsMap3.")]')->length === 0;
        $condition3 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Royal Plaza on Scotts") or contains(.,"@royalplaza.com.sg") or contains(.,"www.royalplaza.com.sg")]')->length === 0;

        if ($condition1 && $condition2 && $condition3) {
            return false;
        }

        // Detecting format
        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"Arrival Date:")]')->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'ReservationConfirmation',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function parseEmail()
    {
        $patterns = [
            'date' => '/^[^:]+:\s*([,\w\s]+)/u',
        ];

        $it = [];
        $it['Kind'] = 'R';

        $hotelNames = [
            'Royal Plaza on Scotts',
        ];
        $fromHotel = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Greetings from")]');

        foreach ($hotelNames as $hotelName) {
            if (stripos($fromHotel, $hotelName) !== false) {
                $it['HotelName'] = $hotelName;
                $contacts = $this->http->FindSingleNode('//tr[not(.//tr) and starts-with(normalize-space(.),"' . $it['HotelName'] . '") and contains(.,"Tel:")]');

                if (preg_match('/([^|]+)\s*[|]\s*Tel:\s*([-.)(\d\s]+)/i', $contacts, $matches)) {
                    $it['Address'] = $matches[1];
                    $it['Phone'] = $matches[2];
                }
            }
        }

        $it['ConfirmationNumber'] = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Your confirmation reference is:")]', null, true, '/^[^:]+:\s*([-A-Z\d]{5,})/');

        $xpathFragment1 = '//*[contains(normalize-space(.),"Stay Details:") and not(.//*)]/following::text()';

        $name = $this->http->FindSingleNode($xpathFragment1 . '[starts-with(normalize-space(.),"Name:")]', null, true, '/^[^:]+:\s*([^:]+)/');

        if ($name) {
            $it['GuestNames'] = [$name];
        }
        $dateCheckIn = $this->http->FindSingleNode($xpathFragment1 . '[starts-with(normalize-space(.),"Arrival Date:")]', null, true, $patterns['date']);

        if ($dateCheckIn) {
            $it['CheckInDate'] = strtotime($dateCheckIn);
        }
        $dateCheckOut = $this->http->FindSingleNode($xpathFragment1 . '[starts-with(normalize-space(.),"Departure Date:")]', null, true, $patterns['date']);

        if ($dateCheckOut) {
            $it['CheckOutDate'] = strtotime($dateCheckOut);
        }
        $guestsCount = $this->http->FindSingleNode($xpathFragment1 . '[starts-with(normalize-space(.),"Adult:")]');

        if (preg_match('/Adult\s*:\s*(\d{1,3})/i', $guestsCount, $matches)) {
            $it['Guests'] = $matches[1];
        }

        if (preg_match('/Child\s*:\s*(\d{1,3})/i', $guestsCount, $matches)) {
            $it['Kids'] = $matches[1];
        }

        $xpathFragment2 = '[starts-with(normalize-space(.),"Room Type:")]/following::text()[normalize-space(.)][1]';
        $it['RoomType'] = $this->http->FindSingleNode($xpathFragment1 . $xpathFragment2);
        $descriptionTexts = $this->http->FindNodes($xpathFragment1 . $xpathFragment2 . '/following-sibling::text()[normalize-space(.) and ./following::text()[starts-with(normalize-space(.),"Rate Details:")]]');
        $it['RoomTypeDescription'] = implode(' ', $descriptionTexts);
        $xpathFragment3 = '[starts-with(normalize-space(.),"Rate Details:")]/following::text()[normalize-space(.)][1]';
        $it['Rate'] = $this->http->FindSingleNode($xpathFragment1 . $xpathFragment3);
        $rateTexts = $this->http->FindNodes($xpathFragment1 . $xpathFragment3 . '/following-sibling::text()[normalize-space(.) and ./following::text()[starts-with(normalize-space(.),"Options Purchased:")]]');
        $it['RateType'] = implode(' ', $rateTexts);

        $totalCost = $this->http->FindSingleNode($xpathFragment1 . '[starts-with(normalize-space(.),"Total Cost with Tax:")]', null, true, '/^[^:]+:\s*([,.\w\s]+)/u');

        if (preg_match('/([^\d]+)\s*([,.\d]+)/', $totalCost, $matches)) {
            $it['Currency'] = $matches[1];
            $it['Total'] = $this->normalizePrice($matches[2]);
        }

        return $it;
    }

    protected function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }
}
