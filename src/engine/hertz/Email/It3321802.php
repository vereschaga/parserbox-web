<?php

// Beware, maybe this is what you need BookingHtml2017Nl, It3321802

namespace AwardWallet\Engine\hertz\Email;

class It3321802 extends \TAccountCheckerExtended
{
    public $mailFiles = "";
    public $reBody = "Hertz";
    public $reBody2 = "Din bil";
    public $reBody3 = "Hertz Corporation";
    public $reSubject = "#Min\s+Hertz\s+(?:billeje\s+bestilling|Reservierung)#";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $text = text($this->http->Response["body"]);

                $it = [];
                $theme = $this->http->XPath->query('//*[contains(text(), "Din rejseplan")]/ancestor::td[contains(@style, "background-color: #fdf11b;")]')->length > 0;

                $it['Kind'] = "L";

                // Number
                $it['Number'] = re("#Dit\s+bestillingsnr\.\s+er\s*:\s*(\w+)#", $text);
                // TripNumber
                // PickupDatetime
                if ($theme) {
                    $date = $this->http->FindSingleNode("//*[normalize-space(text())='Afhentning:']/ancestor::tr[1]/following-sibling::tr[1]/td[1]");
                } else {
                    $date = $this->http->FindSingleNode("//*[normalize-space(text())='Afhentning:']/following-sibling::div[1]");
                }
                $it['PickupDatetime'] = strtotime(en(re("#(\d+\s+\w+),\s+(\d{4})\s+for\s+(\d+:\d+)#", $date) . ' ' . re(2) . ', ' . re(3)));

                // PickupLocation
                $it['PickupLocation'] = $this->getField("Afhentnings- og returneringskontor");

                if ($theme) {
                    $date = $this->http->FindSingleNode("//*[normalize-space(text())='Returnering:']/ancestor::tr[1]/following-sibling::tr[1]/td[2]");
                } else {
                    $date = $this->http->FindSingleNode("//*[normalize-space(text())='Returnering:']/following-sibling::div[1]");
                }
                $it['DropoffDatetime'] = strtotime(en(re("#(\d+\s+\w+),\s+(\d{4})\s+for\s+(\d+:\d+)#", $date) . ' ' . re(2) . ', ' . re(3)));

                // DropoffLocation
                $it['DropoffLocation'] = $this->getField("Afhentnings- og returneringskontor");

                // PickupPhone
                $it['PickupPhone'] = $this->getField("Telefon::");

                // PickupFax
                $it['PickupFax'] = $this->getField("Fax::");

                // PickupHours
                // DropoffPhone
                // DropoffHours
                // DropoffFax
                // RentalCompany
                // CarType
                $it['CarType'] = trim($this->http->FindSingleNode("(//img[contains(@src, 'images.hertz.com/vehicles')]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space(.)])[1]"));

                // CarModel
                $it['CarModel'] = trim($this->http->FindSingleNode("(//img[contains(@src, 'images.hertz.com/vehicles')]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space(.)])[2]"));

                // CarImageUrl
                $it['CarImageUrl'] = $this->http->FindSingleNode("//img[contains(@src, 'images.hertz.com/vehicles')]/@src");

                // RenterName
                $it["RenterName"] = re("#Tak\s+fordi\s+du\s+valgte\s+at\s+bestille\s+din\s+billeje\s+hos\s+Hertz,\s+(.+)#", $text);

                // PromoCode
                $it["PromoCode"] = $this->getField("Priskode :");

                // TotalCharge
                $it["TotalCharge"] = cost($this->getField("Total"));

                // Currency
                $it["Currency"] = currency($this->getField("Total"));

                // TotalTaxAmount
                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                // ServiceLevel
                // Cancelled
                // PricedEquips
                // Discount
                // Discounts
                // Fees
                // ReservationDate
                // NoItineraries
                $itineraries[] = $it;
            },
            $this->reBody3 => function (&$itineraries) {
                $it = ['Kind' => 'L'];
                $it['Number'] = $this->getField('Ihre Reservierungsnummer lautet:');
                $it['PickupDatetime'] = (preg_match("#(\d+\s+\w+),\s*(\d{4})\s+um\s+(\d+:\d+)#", $this->getField('Anmietung'), $m)) ? strtotime(en($m[1] . ' ' . $m[2]) . ' ' . $m[3]) : null;
                $it['DropoffDatetime'] = (preg_match("#(\d+\s+\w+),\s*(\d{4})\s+um\s+(\d+:\d+)#", $this->getField('Rückgabe'), $math)) ? strtotime(en($math[1] . ' ' . $math[2]) . ' ' . $math[3]) : null;
                $it['DropoffLocation'] = $it['PickupLocation'] = $this->getField('Ort der Anmietung und Ort der Rückgabe');
                $it['PickupPhone'] = $this->getField('Telefonnummer::');
                $it['PickupFax'] = $this->getField('Fax Nummer: :');
                $it['CarType'] = trim($this->http->FindSingleNode("(//img[contains(@src, 'images.hertz.com/vehicles')]/ancestor::tr[1]/following-sibling::tr[2]//text()[normalize-space(.)])[1]"));
                $it['CarModel'] = trim($this->http->FindSingleNode("(//img[contains(@src, 'images.hertz.com/vehicles')]/ancestor::tr[1]/following-sibling::tr[3]//text()[normalize-space(.)])[1]"));
                $it['CarImageUrl'] = $this->http->FindSingleNode("//img[contains(@src, 'images.hertz.com/vehicles')]/@src");
                $it['RenterName'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Vielen Dank für Ihre Reservierung')]/ancestor::*[1]", null, true, "#.+\s+Reservierung\s+([\w\s]+)\s*#");
                $totalCharge = $this->getField('Voraussichtlicher Gesamtpreis');

                if (preg_match("#(\S+)\s+(\w{3})#", $totalCharge, $mathec)) {
                    $it['TotalCharge'] = $mathec[1];
                    $it['Currency'] = $mathec[2];
                }
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false
            || stripos($body, $this->reBody) !== false && stripos($body, $this->reBody3) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }
        $result = [
            'emailType'  => 'Reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return ["da", "de"];
    }

    private function getField($str)
    {
        return $this->http->FindSingleNode("//text()[normalize-space(.)='{$str}']/following::text()[normalize-space(.)][1]");
    }
}
