<?php

namespace AwardWallet\Engine\wagonlit\Email;

class It3477893 extends \TAccountCheckerExtended
{
    public $mailFiles = "wagonlit/it-3477893.eml, wagonlit/it-3477894.eml";
    public $reBody = "Carlson Wagonlit Travel";
    public $reBody2 = "Car Rental in:";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $text = text($this->http->Response["body"]);

                $it = [];

                $it['Kind'] = "L";

                // Number
                $it['Number'] = $this->getField("Reservation number:");
                // TripNumber
                // PickupDatetime
                $it['PickupDatetime'] = strtotime(str_replace(' at ', ', ', $this->getField("Pick Up:")));

                // PickupLocation
                $it['PickupLocation'] = nice(ure("#Address\s*:\s*([^:]*?)Phone#", $text));

                // DropoffDatetime
                $it['DropoffDatetime'] = strtotime(str_replace(' at ', ', ', $this->getField("Drop Off:")));

                // DropoffLocation
                $it['DropoffLocation'] = nice(ure("#Address\s*:\s*([^:]*?)Phone#", $text, 2));

                // PickupPhone
                $it['PickupPhone'] = $this->getField("Phone:");

                // PickupFax
                // PickupHours
                $it['PickupHours'] = $this->getField("Hours of operation:");

                // DropoffPhone
                $it['DropoffPhone'] = $this->getField("Phone:", 2);

                // DropoffHours
                $it['DropoffHours'] = $this->getField("Hours of operation:", 2);

                // DropoffFax
                // RentalCompany
                $it['RentalCompany'] = $this->http->FindSingleNode("//img[contains(@src, 'static/core/img/travel/logos/car/')]/preceding::text()[normalize-space(.)][1]");

                // CarType
                $it['CarType'] = $this->getField("Full size:") ? 'Full size' : null;

                // CarModel
                // CarImageUrl
                // RenterName
                $it['RenterName'] = $this->getField("Traveler:");

                // PromoCode
                // TotalCharge
                $it["TotalCharge"] = cost($this->http->FindSingleNode("(//*[normalize-space(text())='Estimated total cost for this traveler']/ancestor-or-self::td[1]/following-sibling::td[1]//text()[normalize-space(.)])[1]"));

                // Currency
                $it["Currency"] = currency($this->http->FindSingleNode("(//*[normalize-space(text())='Estimated total cost for this traveler']/ancestor-or-self::td[1]/following-sibling::td[1]//text()[normalize-space(.)])[1]"));

                // TotalTaxAmount
                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                $it['Status'] = $this->getField("Status:");

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

    private function getField($str, $pos = 1)
    {
        $nodes = $this->http->FindNodes("//b[contains(text(), '{$str}')]/following::text()[string-length(normalize-space(.))>1][1]");

        return $nodes[$pos - 1] ?? false;
    }
}
