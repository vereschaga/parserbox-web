<?php

namespace AwardWallet\Engine\rentacar\Email;

class CarRentalFr extends \TAccountCheckerExtended
{
    /* @var \HttpBrowser $pdf */
    protected $pdf;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $search = $parser->searchAttachmentByName('Votre réservation\.pdf');

        if (count($search) !== 1) {
            $this->http->Log(sprintf('Invalid number of pdf attachments %d', count($search)));

            return [];
        }
        $search = $search[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($search), \PDF::MODE_COMPLEX)) !== null) {
            $this->pdf = clone $this->http;
            $this->pdf->SetBody($html);
        }

        if (!isset($this->pdf)) {
            return null;
        }
        $its = $this->parseEmail();
        unset($this->pdf);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'CarRentalFr',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query(".//text()[contains(normalize-space(.), 'ENTERPRISE Rent-A-Car pour votre réservation')]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'noreply@citer.fr') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'noreply@citer.fr') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['fr'];
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'L'];
        $it['Number'] = $this->http->FindSingleNode(".//text()[contains(normalize-space(.), 'véhicule numéro')]/following-sibling::strong[1]");
        $it['RenterName'] = $this->pdf->FindSingleNode(".//p[contains(., 'Renters name')]/preceding-sibling::p[1]");
        $it['PickupLocation'] = implode(', ', $this->pdf->FindNodes(".//p[contains(., 'Rental location')]/preceding-sibling::p[1]/text()[position() < 4]"));
        $it['PickupPhone'] = $it['DropoffPhone'] = $this->pdf->FindSingleNode(".//p[contains(normalize-space(.), 'Return station')]/following-sibling::p[contains(normalize-space(.), 'Tél: ')]", null, true, "#Tél: (.+) Fax:#");
        $it['PickupDatetime'] = $this->getDateTime('Rental date');
        $it['DropoffDatetime'] = $this->getDateTime('Return date');
        $hours = $this->pdf->FindNodes(".//p[contains(normalize-space(.), 'Horaires d')]/following-sibling::p[position() < 3]");
        $it['PickupHours'] = str_replace(['/ ', '/'], '', array_shift($hours));
        $it['DropoffHours'] = str_replace(['/ ', '/'], '', array_shift($hours));
        $it['DropoffLocation'] = implode(', ', $this->pdf->FindNodes(".//p[contains(., 'Return station')]/preceding-sibling::p[1]/text()[position() < 4]"));
        $it['TotalCharge'] = $this->getNode('Montant total');
        $it['CarType'] = $this->getNode('Type de véhicule', true);

        return [$it];
    }

    private function getDateTime($str)
    {
        $node = $this->getNode($str);

        if (preg_match("#(?<day>\d{2})\/(?<month>\d{2})\/(?<year>\d{4}) .+ (?<time>\d{2}:\d{2})#", $node, $m)) {
            $date = strtotime($m['month'] . '/' . $m['day'] . '/' . $m['year'] . ' ' . $m['time']);
        }

        return $date;
    }

    private function getNode($str, $nod = false)
    {
        if (isset($str) && $nod === false) {
            return $this->pdf->FindSingleNode(".//p[contains(., '{$str}')]/preceding-sibling::p[1]");
        } elseif (isset($str) && $nod === true) {
            return $this->pdf->FindSingleNode(".//p[contains(., '{$str}')]/following-sibling::p[1]");
        }
    }
}
