<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class It3377658 extends \TAccountCheckerExtended
{
    public $mailFiles = "perfectdrive/it-3377658.eml";
    public $reBody = "Gracias por elegir Budget";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $text = text($this->http->Response["body"]);

                $it = [];

                $it['Kind'] = "L";

                // Number
                $it['Number'] = $this->getField("Número de reserva");
                // TripNumber
                // PickupDatetime
                $it['PickupDatetime'] = strtotime(re("#\s+(\d{1,2}\s+\w+\s+\d{4})\s+(\d+:\d+)#", en($this->getField("Fecha de alquiler"))) . ', ' . re(2));

                // PickupLocation
                $it['PickupLocation'] = $this->getField("Oficina de alquiler");

                // DropoffDatetime
                $it['DropoffDatetime'] = strtotime(re("#\s+(\d{1,2}\s+\w+\s+\d{4})\s+(\d+:\d+)#", en($this->getField("Fecha de devolución"))) . ', ' . re(2));

                // DropoffLocation
                $it['DropoffLocation'] = $this->getField("Oficina de devolución");

                // PickupPhone
                $it['PickupPhone'] = $this->getField("Teléfono");

                // PickupFax
                // PickupHours
                $it['PickupHours'] = $this->getField("Horario de apertura el día de la recogida");

                // DropoffPhone
                // DropoffHours
                $it['DropoffHours'] = $this->getField("Horario de apertura el día de la devolución");

                // DropoffFax
                // RentalCompany
                // CarType
                $it['CarType'] = re("#(.*?)\s*\(#", $this->getField("Grupo de vehículo"));

                // CarModel
                $it['CarModel'] = re("#\((.*?)\)#", $this->getField("Grupo de vehículo"));

                // CarImageUrl
                // RenterName
                // PromoCode
                // TotalCharge
                $it["TotalCharge"] = cost($this->getField("Cantidad"));

                // Currency
                $it["Currency"] = currency($this->getField("Cantidad"));

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

        return strpos($body, $this->reBody) !== false;
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
        return ["es"];
    }

    private function getField($s)
    {
        return $this->http->FindSingleNode("(//*[normalize-space(text())='{$s}']/ancestor-or-self::td[1]/following-sibling::td[1])[1]");
    }
}
