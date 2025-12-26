<?php

namespace AwardWallet\Engine\priceline\Email;

class Itinerary1 extends \TAccountChecker
{
    public const EMAIL_TYPE_CAR = 'Car Rental';
    public const EMAIL_TYPE_UNDEFINED = 'Undefined';
    public $mailFiles = "priceline/it-20.eml, priceline/it-7.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $data = $this->parseCar();

        return [
            'parsedData' => $data,
            'emailType'  => 'Itinerary1',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && (stripos($headers['subject'], 'Your priceline.com Itinerary') !== false
                                            || stripos($headers['subject'], 'priceline.com Rental Car Confirmation') !== false)
            || isset($headers['from']) && preg_match("/[@\.]priceline\.com/", $headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (preg_match("#From:(<[^>]+>|\s)*priceline.com#i", $body)
            || stripos($body, 'HUTCHISON-PRICELINE') !== false
            || stripos($body, 'Your priceline.com Itinerary') !== false
            || stripos($body, 'Thanks again for using priceline') !== false
            || stripos($body, 'Thank you for booking your trip on Priceline') !== false
            || stripos($parser->emailRawContent, 'Thanks again for using priceline') !== false) {
            if (stripos($parser->emailRawContent, 'Rental Car Confirmation') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]priceline\.com/", $from);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseCar()
    {
        $result = ["Kind" => "L"];

        // $conf = $this->http->FindSingleNode("//span[contains(text(), 'CAR CONFIRMATION #')]/ancestor::td[1]/text()[2]");
        $result['Number'] = $this->http->FindSingleNode("//b[contains(text(), 'priceline trip number:')]/following::a[1]/text()[1]");

        if (!$result['Number']) {
            $result['Number'] = $this->http->FindSingleNode("//*[contains(text(), 'CAR CONFIRMATION')]/ancestor-or-self::td[1]",
                null, true, "#CAR CONFIRMATION\s*\#\s*([\w\d]+)#");
        }

        $date = $this->http->FindSingleNode("//span[contains(text(), 'Pick-Up')]/ancestor::td[1]");

        if (preg_match("#Pick-Up\s*(.+) at (.+)\s*Drop-Off\s*(.+) at (.+)#", $date, $m)) {
            $result['PickupDatetime'] = strtotime($m[1] . ' ' . $m[2]);
            $result['DropoffDatetime'] = strtotime($m[3] . ' ' . $m[4]);
        }

        $place = $this->http->FindSingleNode("//td[contains(text(), 'Pick-Up/Drop-Off:')]/following-sibling::td[1]");

        if (!$place) {
            $result['DropoffLocation'] = $this->http->FindSingleNode('//tr[td[contains(text(), "Drop-Off:")]]/td/b');
            $result['PickupLocation'] = $this->http->FindSingleNode('//tr[td[contains(text(), "Pick-Up:")]]/td/b');
        } else {
            $result['PickupLocation'] = $place;
            $result['DropoffLocation'] = $place;
        }

        $result['CarType'] = $this->http->FindSingleNode("//td[contains(text(), 'Car type:')]/following::b[1]/text()");
        $result['CarModel'] = $this->http->FindSingleNode("//td[contains(text(), 'Car type:')]/following-sibling::td[1]/text()[2]");
        $result['RenterName'] = $this->http->FindSingleNode("//td[contains(text(), 'Driver Name')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");
        $charge = $this->http->FindSingleNode("//td[contains(text(), 'Total Charges:')]/following-sibling::td[1]");

        if (preg_match('/(.*?)(\d+\.\d+)/ims', $charge, $matches)) {
            $result['TotalCharge'] = $matches[2];
            $result['Currency'] = $matches[1];
        }

        $result['RentalCompany'] = count($this->http->FindNodes("//img[contains(@src, '/AL.gif')]")) ? 'Alamo' : null;

        $taxes = $this->http->FindSingleNode("//*[contains(text(), 'Taxes and Fees')]/ancestor-or-self::td[1]/following-sibling::td[1]");

        if (preg_match('/(\d+\.\d+)/ims', $taxes, $matches)) {
            $result['TotalTaxAmount'] = $matches[1];
        }

        return ["Itineraries" => [$result]];
    }
}
