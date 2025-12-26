<?php

namespace AwardWallet\Engine\hertz\Email;

class It3754036 extends \TAccountChecker
{
    public $mailFiles = "hertz/it-3754036.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'emailType'  => 'Reservations',
            'parsedData' => ['Itineraries' => $its], ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match('#[\w]+@hertz.com#', $headers['from'])
        && isset($headers['subject']) && stripos($headers['subject'], 'Reservation') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return !empty($from) && preg_match('#[\w]+@hertz.com#', $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//*[contains(normalize-space(.), 'please contact Hertz Hellas at the following telephone')]")->length > 0
            && $this->http->XPath->query("//*[contains(normalize-space(.), 'Pick-up details')]")->length > 0
            && $this->http->XPath->query("//*[contains(normalize-space(.), 'Return details')]")->length > 0;
    }

    protected function parseEmail()
    {
        $it = ['Kind' => 'L'];
        // Number
        $it["Number"] = $this->http->FindSingleNode("//text()[contains(., 'Reservation Number:')]", null, true, "#Reservation Number: (\d+)#");
        // DropoffHours
        //$it["PickupHours"] = $it["DropoffHours"] = $this->http->FindSingleNode("//td[contains(text(), 'Reservation duration: ')]", null, true, "#: (.+)#ims");
        // PickupDatetime
        $it["PickupDatetime"] = strtotime(str_ireplace('at', ' ', implode(" ", $this->http->FindNodes('//b[contains(text(), "Pick-up details")]/following-sibling::text()[position() = 1 or position() = 2]'))));
        // PickupDatetime
        $it["DropoffDatetime"] = strtotime(str_ireplace('at', ' ', implode($this->http->FindNodes('//b[contains(text(), "Return details")]/following-sibling::text()[position() = 1 or position() = 2]'))));
        // CarImageUrl
        $it["CarImageUrl"] = $this->http->FindSingleNode("//img[contains(@src, 'autohellas')]/@src");
        // TotalTaxAmount
        $taxAndCur = $this->http->FindSingleNode("//td[normalize-space(.) = 'RENTAL COST']/following::td[2]");

        if (preg_match("#(.+)\s+([\d.]+|\d+)#", $taxAndCur, $m)) {
            $it['Currency'] = ($m[1] == "€") ? 'EUR' : null;
            $it['TotalCharge'] = str_ireplace(".", ',', $m[2]);
        }
        // PickupLocation and ReturnLocation
        $it['PickupLocation'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pick-up details')]/following::text()[normalize-space()][3]", null, true, "#From: (.+)#");
        // DropoffLocation
        $it['DropoffLocation'] = $it['PickupLocation'];
        // PichupPhone
        $it['DropoffPhone'] = $it['PickupPhone'] = str_ireplace("/", '-', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pick-up details')]/following::text()[normalize-space()][4]", null, true, "#Tel.: (.+)#"));
        // PickupFax
        // TotalCharge
        // RenterName
        $it['RenterName'] = $this->http->FindSingleNode("//div[contains(., 'Reservation Number:')]/following-sibling::div[2]");
        // CarType, CarModel
        $CarType = $this->http->FindSingleNode("//td[contains(text(), 'VEHICLE CATEGORY')]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>3]");
        /*if(preg_match("#(\w{1}) ([\w\d\s]+)#", $CarType, $v)){
            $it['CarType'] = $v[1];
            $it['CarModel'] = $v[2];
        }*/
        if (preg_match("#^(.+or\s+similar)\s+(.+)\s*$#", $CarType, $v)) {
            $it['CarType'] = $v[1];
            $it['CarModel'] = $v[2];
        }

        return [$it];
    }
}
