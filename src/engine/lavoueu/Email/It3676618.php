<?php

namespace AwardWallet\Engine\lavoueu\Email;

class It3676618 extends \TAccountCheckerExtended
{
    public $mailFiles = "lavoueu/it-3676618.eml";
    public $reBody = 'Lávoueu';
    public $reBody2 = "Bilhete Eletrônico";
    public $reSubject = "Aéreo - Confirmação de Emissão";
    public $reFrom = "corporativo@lavoueuviagens.com.br";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = $this->getField("Localizador da Reserva");
                // TripNumber
                // Passengers
                $it['Passengers'] = [$this->getField("Passageiro")];
                // AccountNumbers
                // Cancelled
                // TotalCharge
                $totalTable = $this->getTable("//*[normalize-space(text())='Pagamento']/following::table[1]//tr[1]", "//*[normalize-space(text())='Pagamento']/following::table[1]//tr[1]/following-sibling::tr");
                $it['TotalCharge'] = cost($totalTable[0]["Total"]);

                // BaseFare
                $it['BaseFare'] = cost($totalTable[0]["Tarifa"]);

                // Currency
                $it['Currency'] = currency($totalTable[0]["Total"]);

                // Tax
                $it['Tax'] = cost($totalTable[0]["Taxas"]);

                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                $flights = $this->getTable("//td[normalize-space(.)='Voo']/ancestor::tr[1]", "//td[normalize-space(.)='Voo']/ancestor::tr[1]/following-sibling::tr");
                $year = re("#\d+/\d+/(\d{4})#", $this->getField("Emissão"));

                foreach ($flights as $flight) {
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = re("#\w{2}\s+(\d+)#", $flight["Voo"]);

                    // DepCode
                    $itsegment['DepCode'] = re("#^[A-Z]{3}#", $flight["Origem"]);

                    // DepName
                    // DepDate
                    $itsegment['DepDate'] = strtotime(re("#(\d+)(\w+)\s+(\d+:\d+)#", $flight["Saída"]) . ' ' . re(2) . ' ' . $year . ', ' . re(3));

                    // ArrCode
                    $itsegment['ArrCode'] = re("#^[A-Z]{3}#", $flight["Destino"]);

                    // ArrName
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(re("#(\d+)(\w+)\s+(\d+:\d+)#", $flight["Chegada"]) . ' ' . re(2) . ' ' . $year . ', ' . re(3));

                    // AirlineName
                    $itsegment['AirlineName'] = re("#(\w{2})\s+\d+#", $flight["Voo"]);

                    // Aircraft
                    // TraveledMiles
                    // Cabin
                    // BookingClass
                    $itsegment["BookingClass"] = $flight["Classe"];

                    // PendingUpgradeTo
                    // Seats
                    // Duration
                    // Meal
                    // Smoking
                    // Stops
                    $it['TripSegments'][] = $itsegment;
                }
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
        $this->http->FilterHTML = false;
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
            'emailType'  => 'Flight',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return ["pt"];
    }

    private function getField($str)
    {
        return $this->http->FindSingleNode("//td[normalize-space(.)='{$str}']/following-sibling::td[1]");
    }

    private function getTable($header, $body)
    {
        $header = $this->http->XPath->query($header)->item(0);
        $header = $this->http->FindNodes("./td", $header);
        $bodyRows = $this->http->XPath->query($body);
        $table = [];

        foreach ($bodyRows as $i=>$row) {
            foreach ($this->http->FindNodes("./td", $row) as $j=>$col) {
                if (isset($header[$j])) {
                    $table[$i][$header[$j]] = $col;
                }
            }
        }

        return $table;
    }
}
