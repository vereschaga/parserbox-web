<?php

namespace AwardWallet\Engine\ryanair\Email;

class CancellationPDF extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "ryanair/it-4031334.eml";

    public $reFrom = "info@travelplanner.ryanair.com";
    public $reSubject = [
        "es"=> "Cancelación de vuelo: Referencia de reserva:",
    ];
    public $reBody = 'Ryanair';
    public $reBody2 = [
        "es"=> "Ryanair se disculpa sinceramente por la cancelación de su vuelo",
    ];

    public static $dictionary = [
        "es" => [],
    ];

    public $lang = "es";

    public function parsePdf(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->pdf->FindSingleNode("//text()[contains(., 'Confirmación de reserva:')]", null, true, "#Confirmación de reserva:\s+(\w+)#");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->pdf->FindNodes("//text()[contains(., 'Confirmación de reserva:')]/following::text()[following::text()[contains(., 'IDA:')]][normalize-space(.)]");

        // AccountNumbers
        if ($this->pdf->FindSingleNode("//text()[contains(., 'Confirmación de la cancelación')]")) {
            // Cancelled
            $it['Cancelled'] = true;
            // Status
            $it['Status'] = 'cancelled';
        }
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards

        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[contains(., 'IDA:') or contains(., 'VUELTA:')]";
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)][1]", $root, true, "#^\w{2}\s*(\d+)\s#");

            // DepCode
            $itsegment['DepCode'] = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)][1]", $root, true, "#depart\s+([A-Z]{3})\s+on\s+\d+-\d+-\d{4}\s+at\s+\d+:\d+#");

            // DepName
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("./following::text()[normalize-space(.)][1]", $root, true, "#depart\s+[A-Z]{3}\s+on\s+(\d+-\d+-\d{4}\s+at\s+\d+:\d+)#")));

            // ArrCode
            $itsegment['ArrCode'] = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)][1]", $root, true, "#arrive\s+([A-Z]{3})\s+on\s+\d+-\d+-\d{4}\s+at\s+\d+:\d+#");

            // ArrName
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("./following::text()[normalize-space(.)][1]", $root, true, "#arrive\s+[A-Z]{3}\s+on\s+(\d+-\d+-\d{4}\s+at\s+\d+:\d+)#")));

            // AirlineName
            $itsegment['AirlineName'] = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)][1]", $root, true, "#^(\w{2})\s*\d+\s#");

            // Operator
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
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('Cancellation Proof.pdf');

        if (!isset($pdfs[0])) {
            return null;
        }

        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) === null) {
            return null;
        }

        $this->pdf = clone $this->http;
        $this->pdf->SetEmailBody($html);

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($itineraries);

        $result = [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)-(\d+)-(\d{4})\s+at\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1.$2.$3, $4",
        ];

        return preg_replace($in, $out, $str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
