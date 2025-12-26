<?php

namespace AwardWallet\Engine\rentals\Email;

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

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'no-reply@carrentals.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'no-reply@carrentals.com') !== false
        || isset($headers['subject']) && stripos($headers['subject'], 'Your CarRentals.com Confirmation Numbers') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query(".//*[contains(normalize-space(.), 'Thank you for booking with') and contains(span, 'CarRentals.com')]")->length > 0;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'L'];
        $it['Number'] = $this->getNode('Confirmation Number');
        $it['TotalCharge'] = $this->getNode('Est total');
        $it['DropoffLocation'] = $it['PickupLocation'] = $this->getNode('Pick up');
        $it['CarType'] = $this->getNode('Car Type');
        $date = null;
        $date = $this->http->FindSingleNode(".//span[contains(normalize-space(.), 'Upcoming Reservations')]/ancestor::tr[1]/following-sibling::tr[1]");

        if (isset($date) && preg_match("#.+ - .+#", $date)) {
            $date = explode(' - ', $date);
            $it['PickupDatetime'] = strtotime($date[0]);
            $it['DropoffDatetime'] = strtotime($date[1]);
        }

        return [$it];
    }

    private function getNode($str)
    {
        return $this->http->FindSingleNode(".//span[contains(normalize-space(.), '{$str}')]/following-sibling::span");
    }
}
