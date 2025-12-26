<?php

namespace AwardWallet\Engine\rentacar\Email;

// TODO: merge with parsers national/RentalAgreement2014Pdf (in favor of national/RentalAgreement2014Pdf)

class RentalAgreementPDF extends \TAccountChecker
{
    use \PriceTools;
    public $mailFiles = "rentacar/it-2848204.eml, rentacar/it-5208848.eml, rentacar/it-5328694.eml, rentacar/it-59612089.eml";

    public $reBody = "Enterprise";
    public $reBodyPDF = [
        ['Rental Agreement', 'Vehicle Class Charged'],
    ];
    public $reSubject = [
        'Enterprise Rental Agreement',
    ];

    public $pdf;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName("Enterprise.+Rental.+Agreement.*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $html = str_replace($NBSP, ' ', html_entity_decode($html));
            $this->pdf->SetEmailBody($html);
        } else {
            return null;
        }

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "RentalAgreementPDF",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('Enterprise.+Rental.+Agreement.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));
            $NBSP = chr(194) . chr(160);
            $text = str_replace($NBSP, ' ', html_entity_decode($text));

            if (stripos($text, $this->reBody) !== false) {
                foreach ($this->reBodyPDF as $reBody) {
                    if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@enterprise.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'L'];
        $it['Number'] = $this->pdf->FindSingleNode("//p[starts-with(normalize-space(),'Rental Agreement')]", null, true, "/Rental Agreement\s*#\s*([A-Z\d]+)/");

        if ($this->pdf->FindSingleNode("//text()[normalize-space()='Renter Name']/ancestor::p[1]/following::p[1]") === "Pickup") {
            //one order of the <p> (it-2848204.eml)
            $it['PickupDatetime'] = strtotime($this->pdf->FindSingleNode("//p[contains(.,'Pickup')]/following::p[3]") . ' ' . $this->pdf->FindSingleNode("//p[contains(.,'Pickup')]/following::p[4]"));
            $node = $this->pdf->FindSingleNode("//p[contains(.,'Pickup')]/following::p[7]");
            $it['PickupLocation'] = $node . ' ' . $this->pdf->FindSingleNode("//p[contains(normalize-space(),'Renter Address')]/following::p[1]");
            $it['DropoffDatetime'] = strtotime($this->pdf->FindSingleNode("//p[contains(.,'Pickup')]/following::p[5]") . ' ' . $this->pdf->FindSingleNode("//p[contains(.,'Pickup')]/following::p[6]"));
            $node = $this->pdf->FindSingleNode("//p[contains(.,'Pickup')]/following::p[8]");
            $it['DropoffLocation'] = $node . ' ' . $this->pdf->FindSingleNode("//p[contains(normalize-space(),'Renter Address')]/following::p[2]");
            $it['RenterName'] = $this->pdf->FindSingleNode("//p[contains(.,'Pickup')]/following::p[2]");
            $it['CarType'] = $this->pdf->FindSingleNode("//p[contains(normalize-space(),'Vehicle Class Charged')]", null, true, "/Vehicle\s+Class\s+Charged\s*(.+)/");
        } else {
            //another order of the <p> (it-5208848.eml, it-5328694.eml)
            $i = 1;
            // PickupDatetime
            $pickupDateTexts = [];

            while (!preg_match("/\d+:\d{2}/", ($node = trim($this->pdf->FindSingleNode("//text()[normalize-space()='Pickup']/ancestor::p[1]/following::p[{$i}]"))))
                && $i < 11) {
                $pickupDateTexts[] = $node;
                $i++;
            }
            $pickupDateTexts[] = $node; // add time
            $it['PickupDatetime'] = strtotime($pickupDate = implode(' ', $pickupDateTexts));

            if (!$it['PickupDatetime']) {
                // 15:39 PM
                $it['PickupDatetime'] = strtotime(preg_replace('/\s*AM$/', '', preg_replace('/\s*PM$/', '', $pickupDate)));
            }
            $i++;

            // PickupLocation
            $pickupLocationTexts = [];

            while (stripos(($node = trim($this->pdf->FindSingleNode("//text()[normalize-space()='Pickup']/ancestor::p[1]/following::p[{$i}]"))), "Return") === false
                && $i < 11) {
                if (preg_match("/\d+:\d{2}/", $node)) {
                    $pickupLocationTexts = [];
                } else {
                    $pickupLocationTexts[] = $node;
                }
                $i++;
            }
            $it['PickupLocation'] = implode(' ', $pickupLocationTexts);

            $i = 1;
            // DropoffDatetime
            $dropoffDateTexts = [];

            while (!preg_match("/\d+:\d{2}/", ($node = trim($this->pdf->FindSingleNode("//text()[normalize-space()='Return']/ancestor::p[1]/following::p[{$i}]"))))
                && $i < 11) {
                $dropoffDateTexts[] = $node;
                $i++;
            }
            $dropoffDateTexts[] = $node; // add time
            $it['DropoffDatetime'] = strtotime($dropoffDate = implode(' ', $dropoffDateTexts));

            if (!$it['DropoffDatetime']) {
                $it['DropoffDatetime'] = strtotime(preg_replace('/\s*AM$/', '', preg_replace('/\s*PM$/', '', $dropoffDate)));
            }
            $i++;

            // DropoffLocation
            $dropoffLocationTexts = [];

            while (stripos(($node = trim($this->pdf->FindSingleNode("//text()[normalize-space()='Return']/ancestor::p[1]/following::p[{$i}]"))), "Rental Charge") === false
                && stripos($node, 'Renter Charges') === false && stripos($node, 'Bill-To') === false
                && $i < 11) {
                $dropoffLocationTexts[] = $node;
                $i++;
            }
            $it['DropoffLocation'] = implode(' ', $dropoffLocationTexts);

            $it['RenterName'] = $this->pdf->FindSingleNode("//p[contains(normalize-space(),'Renter Name')]/following::p[1]");
            $it['CarType'] = $this->pdf->FindSingleNode("//p[contains(normalize-space(),'Vehicle Class Charged')]/following::p[1]");
        }
        $it['CarModel'] = $this->pdf->FindSingleNode("//p[contains(normalize-space(),'Vehicle Information')]/following::p[1]");
        $node = $this->pdf->FindSingleNode("//p[normalize-space(.)='Total']/following::text()[string-length(normalize-space(.))>3][1]");
        $it['TotalCharge'] = $this->cost($node);
        $it['Currency'] = $this->currency($node);

        return [$it];
    }
}
