<?php

namespace AwardWallet\Engine\israel\Email;

class EmployeesTicket extends \TAccountChecker
{
    public $mailFiles = "israel/it-8459568.eml, israel/it-8580556.eml, israel/it-8580694.eml";

    public $reFrom = "elal.co.il";
    public $reSubject = [
        "EL AL Employees Ticketing",
        "tickets reservation", ];
    public $reBody = "ELAL Employees";
    public $reBody2 = [
        "אישור להזמנה",
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return [
            "emailType"  => "EmployeesTicket",
            "parsedData" => [
                "Itineraries" => [$this->ParseEmail($body)],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["he"];
    }

    public function ParseEmail()
    {
        $itineraries['Kind'] = 'T';

        $count = count($this->http->FindNodes("//text()[contains(normalize-space(.),'מספר הזמנה:')]/ancestor::div[1]/preceding-sibling::div"));

        if (empty($count)) {
            $count = 0;
        }
        $itineraries['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'מספר הזמנה:')]/ancestor::td[1]/following-sibling::td[1]/div[" . ($count + 1) . "]", null, true, '#([A-Z\d]+)#');

        if (empty($itineraries['RecordLocator'])) {
            $itineraries['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'מספר הזמנה:')]/following::text()[normalize-space(.)][1]", null, true, '#([A-Z\d]+)#');
        }

        $itineraries['Passengers'] = $this->http->FindNodes("//text()[contains(normalize-space(.),'פרטי נוסע')]/following::text()[normalize-space(.)][1]/ancestor::*[local-name()='div' or local-name()='span'][2]/*", null, "#([A-Z\-\/ ]+)#");

        $nodes = $this->http->XPath->query("//text()[contains(normalize-space(.),'המראה:')]/ancestor::table[1]");
        $this->http->Log("Total nodes found (1) " . $nodes->length);

        if ($nodes->length > 0) {
            $itineraries['TripSegments'] = $this->segments_1($nodes);
        } else {
            $nodes = $this->http->XPath->query("//text()[contains(normalize-space(.),'המראה')]/ancestor::table[contains(.,'טיסה')][1]");
            $this->http->Log("Total nodes found (2) " . $nodes->length);

            if ($nodes->length > 0) {
                $itineraries['TripSegments'] = $this->segments_2($nodes);
            }
        }

        return $itineraries;
    }

    public function segments_1($nodes)
    {
        $itineraries['TripSegments'] = [];

        foreach ($nodes as $root) {
            $seg = [];
            $date = $this->http->FindSingleNode(".//tr[1]/td[2]", $root, true, '#(\d+/\d{2}/\d{4})#');
            $date = strtotime(str_replace("/", ".", $date));
            $trs = $this->http->XPath->query(".//tr[position()>1]", $root);
            $table = [];

            foreach ($trs as $tr) {
                $t = [];
                $tds = $this->http->XPath->query("./td", $tr);

                foreach ($tds as $i => $td) {
                    $t[] = $this->http->FindNodes(".//div", $td);
                }

                foreach ($t as $key => $value) {
                    $table[] = implode(" ", array_column($t, $key));
                }
            }
            $table = implode("\n", $table);

            $route = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]", $root);

            if (preg_match("#^\s*([A-Z]{3})\s*-\s*([A-Z]{3})\s*#", $route, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['ArrCode'] = $m[2];
            } elseif (preg_match("#^\s*(.+)\s+\W\s+(.+)\s*$#", $route, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrName'] = $m[2];
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            if (preg_match("#מספר טיסה:\s+([A-Z\d]{2})(\d+)#", $table, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match("#המראה:\s+(\d+:\d+)#", $table, $m)) {
                $seg['DepDate'] = strtotime($m[1], $date);
            }

            if (preg_match("#נחיתה:\s+(\d+:\d+)#", $table, $m)) {
                $seg['ArrDate'] = strtotime($m[1], $date);
            }

            if (preg_match("#סוג מטוס:\s+(.+)#", $table, $m)) {
                $seg['Aircraft'] = $m[1];
            }

            if (preg_match("#סוג מחלקה:\s+(.+)#", $table, $m)) {
                $seg['Cabin'] = $m[1];
            }

            $itineraries['TripSegments'][] = $seg;
        }

        return $itineraries['TripSegments'];
    }

    public function segments_2($nodes)
    {
        $itineraries['TripSegments'] = [];

        foreach ($nodes as $root) {
            $seg = [];
            $date = $this->http->FindSingleNode(".//text()[starts-with(.,'טיסה')][1]/following::text()[normalize-space(.)][1]", $root, true, '#(\d+/\d{2}/\d{4})#');
            $date = strtotime(str_replace("/", ".", $date));

            $route = $this->http->FindSingleNode("(.//text()[normalize-space(.)])[1]", $root);

            if (preg_match("#^\s*([A-Z]{3})\s*-\s*([A-Z]{3})\s*#", $route, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['ArrCode'] = $m[2];
            } elseif (preg_match("#^\s*(.+)\s+\W\s+(.+)\s*$#u", $route, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrName'] = $m[2];
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $flight = $this->http->FindSingleNode(".//text()[contains(.,'חברת תעופה')][1]/following::text()[normalize-space(.)][1]", $root);

            if (preg_match("#^\s*(?<airline>[A-Z\d]{2})\s+(?<number>\d{1,5})\s*(?<operator>[\w\s]*)$#", $flight, $m) || preg_match("#^\s*(?<operator>[\w\s]*)\s*(?<airline>[A-Z\d]{2})\s+(?<number>\d{1,5})\s*$#", $flight, $m)) {
                $seg['AirlineName'] = $m["airline"];
                $seg['FlightNumber'] = $m["number"];

                if (!empty(trim($m["operator"]))) {
                    $seg['Operator'] = $m["operator"];
                }
            } else {
                $flight = $this->http->FindSingleNode(".//text()[contains(.,'חברת תעופה')][1]/following::text()[normalize-space(.)][2]", $root);

                if (preg_match("#^\s*(?<airline>[A-Z\d]{2})\s+(?<number>\d{1,5})\s*$#", $flight, $m)) {
                    $seg['AirlineName'] = $m["airline"];
                    $seg['FlightNumber'] = $m["number"];
                }
            }
            $seg['DepDate'] = strtotime($this->http->FindSingleNode(".//text()[starts-with(.,'המראה')][1]/following::text()[normalize-space(.)][1]", $root), $date);
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode(".//text()[starts-with(.,'נחיתה')][1]/following::text()[normalize-space(.)][1]", $root), $date);
            $dt = $this->http->FindSingleNode(".//text()[starts-with(.,'נחיתה')][1]/following::text()[normalize-space(.)][2]", $root, true, "#\+\d+ ימים#");

            if (!empty($dt)) {
                $seg['ArrDate'] = strtotime("+1 day", $seg['ArrDate']);
            }
            $seg['Aircraft'] = $this->http->FindSingleNode(".//text()[contains(normalize-space(.),'סוג מטוס')][1]/following::text()[normalize-space(.)][1]", $root);

            $seg['Cabin'] = $this->http->FindSingleNode(".//text()[contains(.,'סוג מחלקה')][1]/following::text()[normalize-space(.)][1]", $root);

            $itineraries['TripSegments'][] = $seg;
        }

        return $itineraries['TripSegments'];
    }
}
