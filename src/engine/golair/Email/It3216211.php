<?php

namespace AwardWallet\Engine\golair\Email;

class It3216211 extends \TAccountCheckerExtended
{
    public $mailFiles = "golair/it-3216211.eml";
    public $reBody = 'www.voegol.com.br';
    public $reBody2 = "Dados do(s) Voo(s)";
    public $reSubject = "Lembrete WebCheck-In";
    public $reFrom = "comunicacaovoegol@voegol.com.br";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(),'LOCALIZADOR')]", null, true, "#\s+(\w{6})$#");
                // TripNumber
                // Passengers
                // AccountNumbers
                // Cancelled
                // TotalCharge
                // BaseFare
                // Currency
                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                $xpath = "//*[contains(text(),'PARTIDA')]/ancestor::tr[1]/following-sibling::tr";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                }

                $year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");

                foreach ($nodes as $root) {
                    $date = strtotime(str_replace("/", ".", $this->http->FindSingleNode("./td[2]", $root)));

                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[3]", $root);

                    // DepCode
                    $itsegment['DepCode'] = $this->http->FindSingleNode("./td[4]", $root, true, "#[A-Z]{3}#");

                    // DepName
                    // DepDate
                    $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[4]", $root, true, "#\d+:\d+#"), $date);

                    // ArrCode
                    $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[5]", $root, true, "#[A-Z]{3}#");

                    // ArrName
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[5]", $root, true, "#\d+:\d+#"), $date);

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[6]", $root);

                    // Aircraft
                    // TraveledMiles
                    // Cabin
                    // BookingClass
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
        return strpos($headers["subject"], $this->reSubject) !== false;
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
}
