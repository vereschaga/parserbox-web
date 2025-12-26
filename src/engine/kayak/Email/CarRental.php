<?php

namespace AwardWallet\Engine\kayak\Email;

/**
 * it-3858793.eml, it-3872309.eml, it-3976702.eml.
 */
class CarRental extends \TAccountChecker
{
    public $mailFiles = "kayak/it-3858793.eml, kayak/it-3872309.eml, kayak/it-3976702.eml";

    public function parseEmail()
    {
        $it = ['Kind' => 'L'];
        $it['Number'] = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Confirmation Number")]/ancestor::tr[1]/preceding-sibling::tr');

        $it['DropoffPhone'] = $it['PickupPhone'] = $this->http->FindSingleNode(
                '//text()[contains(normalize-space(.),"Confirmation Number")]/ancestor::table[2]/following-sibling::table[1]//table//tr[1]/td[1]',
                null, false, '/[+\d\s]+/');

        if ($carInfo = $this->http->FindSingleNode('//*[normalize-space(text())="Car Details"]/following::td[normalize-space(text()) != " "][2]')) {
            $carInfo = explode(' - ', $carInfo);
            $it['CarType'] = $carInfo[0];
            $it['CarModel'] = $carInfo[1];
        }

        $it['TotalTaxAmount'] = $this->http->FindSingleNode("//td[contains(., 'Taxes, Fees and Surcharges')]/following-sibling::td[2]", null, false, '/[\d.,\s]+/');
        $totalCharge = $this->http->FindSingleNode("//td[contains(., 'Rental Car Total')][1]/following-sibling::td[2]");

        if (preg_match('/[\d.,\s]+/', $totalCharge, $m)) {
            $this->logger->error(var_export($m, true));
            $it['TotalCharge'] = $m[0];
            $it['Currency'] = $this->currency($totalCharge);
        }
        $it['RenterName'] = $this->http->FindSingleNode(".//tbody[count(tr)=2]/tr[contains(normalize-space(.), 'Name')]/following-sibling::tr/td[1]");

        $it += $this->parseBlocks() + $this->parseBlocks('Drop off', 'Dropoff');

        return [$it];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailByBody() === false) {
            return [];
        }

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'CarRental',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'bookings-noreply@message.kayak.com') !== false
                || isset($headers['subject']) && stripos($headers['subject'], 'Your KAYAK reservation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser = null)
    {
        return $this->http->XPath->query('//*[normalize-space(text()) = "Rental car reserved."]')->length > 0
                && $this->http->XPath->query('//text()[contains(normalize-space(.), "via KAYAK.")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'bookings-noreply@message.kayak.com') !== false;
    }

    private function parseBlocks($blockName = 'Pick up', $preffixName = 'Pickup')
    {
        $result = [];
        $isPickupLocation = true;
        $pickupLocation = [];

        foreach ($this->http->FindNodes('//*[normalize-space(text())="' . $blockName . '"]/ancestor::table[1]//tr') as $key => $value) {
            // Tue Aug 16 2016 - 4:00PM
            if (preg_match('/^.*([\d]{1,2}:[\d]{1,2})(AM|PM)$/', $value)) {
                $result[$preffixName . 'Datetime'] = strtotime(str_replace(' - ', ' ', $value));

                continue;
            }

            // Economy Rent a Car
            if (empty($result['RentalCompany']) && $key === 2) {
                $result['RentalCompany'] = $value;

                continue;
            }

            // Phone: +64 9303 3912
            if (preg_match('/^Phone:(.*)/', $value, $matches)) {
                //$result[$preffixName . 'Phone'] = $matches[1];
                $isPickupLocation = false;
            }

            // Operating hours: Tue 7:00 am - 5:00 pm
            if (preg_match('/^Operating hours: (.*)/', $value, $matches)) {
                $result[$preffixName . 'Hours'] = $matches[1];
                $isPickupLocation = false;
            }

            if ($key > 2 && $isPickupLocation) {
                $pickupLocation[] = $value;
            }
        }

        if (empty($pickupLocation) !== true) {
            $result[$preffixName . 'Location'] = join(', ', $pickupLocation);
        }

        return $result;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
