<?php

namespace AwardWallet\Engine\hertz\Email;

class It3209463 extends \TAccountCheckerExtended
{
    public $mailFiles = "";
    public $reBody = "Dank u voor uw reservering bij Hertz";
    public $reBody2 = "uw voertuig:";
    public $reFrom = "noreply@hertz.com";
    public $reSubject = "Mijn Hertz Reservering";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "L";

                // Number
                $it['Number'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Uw bevestigingsnummer is:']/following::text()[normalize-space(.)][1]");

                // TripNumber
                // PickupDatetime
                $it['PickupDatetime'] = strtotime(
                    str_replace(",", "", en(re("#,\s*(.*?)\s+om\s+(\d+:\d+)#", $this->http->FindSingleNode("//text()[normalize-space(.)='Ophaalgegevens']/ancestor::tr[1]/following-sibling::tr[1]/td[1]"))))
                    . ', ' . re(2)
                );

                // PickupLocation
                $it['PickupLocation'] = nice($this->http->FindSingleNode("(//text()[normalize-space(.)='Addres']/ancestor::td[1])[1]", null, true, "#Addres\s+(.+)#"), ',');

                // DropoffDatetime
                $it['DropoffDatetime'] = strtotime(str_replace(",", "", en(re("#,\s*(.*?)\s+om\s+(\d+:\d+)#", $this->http->FindSingleNode("//text()[normalize-space(.)='Inlevergegevens']/ancestor::tr[1]/following-sibling::tr[1]/td[2]")))) . ', ' . re(2));

                // DropoffLocation
                $it['DropoffLocation'] = orval(
                    nice($this->http->FindSingleNode("(//text()[normalize-space(.)='Addres']/ancestor::td[1])[2]", null, true, "#Addres\s+(.+)#"), ','),
                    $it['PickupLocation']
                );

                // PickupPhone
                $it['PickupPhone'] = $this->http->FindSingleNode("(//text()[normalize-space(.)='Telefoonnummer:'])[1]/following::text()[normalize-space(.)][1]");

                // PickupFax
                $it['PickupFax'] = $this->http->FindSingleNode("(//text()[normalize-space(.)='Faxnummer::'])[1]/following::text()[normalize-space(.)][1]");

                // PickupHours
                // DropoffPhone
                $it['DropoffPhone'] = orval(
                    $this->http->FindSingleNode("(//text()[normalize-space(.)='Telefoonnummer:'])[2]/following::text()[normalize-space(.)][1]"),
                    $it['PickupPhone']
                );

                // DropoffFax
                $it['DropoffFax'] = orval(
                    $this->http->FindSingleNode("(//text()[normalize-space(.)='Faxnummer::'])[2]/following::text()[normalize-space(.)][1]"),
                    $it['PickupFax']
                );

                // DropoffHours
                // RentalCompany
                // CarType
                $it['CarType'] = trim($this->http->FindSingleNode("(//text()[normalize-space(.)='uw voertuig:']/ancestor::table[1]/following-sibling::table[1]//td[2]//text()[normalize-space(.)])[1]"));

                // CarModel
                $it['CarModel'] = trim($this->http->FindSingleNode("(//text()[normalize-space(.)='uw voertuig:']/ancestor::table[1]/following-sibling::table[1]//td[2]//text()[normalize-space(.)])[2]"));

                // CarImageUrl
                $it['CarImageUrl'] = $this->http->FindSingleNode("//text()[normalize-space(.)='uw voertuig:']/ancestor::table[1]/following-sibling::table[1]//td[1]//img/@src");

                // RenterName
                $it['RenterName'] = trim($this->http->FindSingleNode("//text()[contains(., 'Dank u voor uw reservering bij Hertz')]", null, true, "#Dank u voor uw reservering bij Hertz,\s+(.+)#"));

                // PromoCode
                // TotalCharge
                $it["TotalCharge"] = cost($this->http->FindSingleNode("(//*[contains(text(),'Totaal')])[1]/ancestor-or-self::td[1]/following-sibling::td[1]"));

                // Currency
                $it["Currency"] = currency($this->http->FindSingleNode("(//*[contains(text(),'Totaal')])[1]/ancestor-or-self::td[1]/following-sibling::td[1]"));

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
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false && strpos($headers["subject"], $this->reSubject) !== false;
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
        return ["nl"];
    }
}
