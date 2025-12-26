<?php

namespace AwardWallet\Engine\rentacar\Email;

class CarRental extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'CarRental',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//a[contains(@href,'enterprise.com')]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'NO_REPLY@enterprise.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'NO_REPLY@enterprise.com') !== false;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'L'];
        $it['Number'] = $this->http->FindSingleNode(".//span[contains(normalize-space(.), 'Confirmation')]", null, true, "#Confirmation\#\s*:\s*(.+)#");
        $it['PickupDatetime'] = strtotime(str_replace(' at ', ' ', $this->getNode('Pickup Date')));
        $it['PickupHours'] = $this->getNode('Hours');
        $it['PickupLocation'] = $this->getNode('Address', 1, 4);
        $it['PickupPhone'] = $this->getNode('Address', 1, null, true);
        $it['DropoffDatetime'] = strtotime(str_replace(' at ', ' ', $this->getNode('Return Date', 2)));
        $it['DropoffHours'] = $this->getNode('Hours', 2);
        $it['DropoffLocation'] = $this->getNode('Address', 2, 4);
        $it['DropoffPhone'] = $this->getNode('Address', 2, null, true);
        $it['CarType'] = $this->getDetailsCar('Type of Car');
        $it['CarModel'] = $this->getDetailsCar('Examples');
        $it['TotalCharge'] = $this->getDetailsCar('Total Charges');
        $it['RenterName'] = $this->http->FindSingleNode(".//span[contains(normalize-space(.), 'Dear')]", null, true, "#Dear\s+([\w\s]+)#");

        return [$it];
    }

    private function getDetailsCar($str)
    {
        return $this->http->FindSingleNode(".//td[contains(normalize-space(.), '{$str}')]/following-sibling::td[1]");
    }

    private function getNode($str, $td = 1, $text = null, $numText = false)
    {
        $row = ".//tr[contains(normalize-space(.), 'Pickup Branch') or contains(normalize-space(.), 'Return Branch')]/following-sibling::tr/td[$td]/descendant::td[contains(., '{$str}')]/following-sibling::td[1]";

        if ($text === null && $numText === false) {
            return $this->http->FindSingleNode($row);
        } elseif ($text !== null) {
            return implode(', ', $this->http->FindNodes("{$row}/text()[position() < $text]"));
        } elseif ($numText !== false) {
            return $this->http->FindSingleNode("{$row}/text()[4]");
        }
    }
}
