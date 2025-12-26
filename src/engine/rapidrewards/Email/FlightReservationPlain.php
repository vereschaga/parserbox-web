<?php

namespace AwardWallet\Engine\rapidrewards\Email;

class FlightReservationPlain extends \TAccountChecker
{
    // plain text email, subject "Flight reservation ... "

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["subject"]) && stripos($headers["subject"], "luv.southwest.com: Flight reservation") !== false
        && isset($headers["content-type"]) && stripos($headers["content-type"], "text/plain") !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'Southwest Airlines does not have assigned seats') !== false
        || stripos($body, 'http://www.southwest.com/rapidrewards and sign up today') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]southwest\.com/i", $from);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            "parsedData" => [
                "Itineraries" => $its,
            ],
            "emailType" => "FlightReservationPlain",
        ];
    }

    protected function ParseEmail()
    {
        $it = ["Kind" => "T", "TripSegments" => [], "Passengers" => []];
        $text = $this->http->Response['body'];

        if (strpos($text, "AIR Confirmation:") === false) {
            return [$it];
        }
        $lines = explode("\n", $text);
        $lines = array_filter($lines, function ($s) {
            $s = trim($s, " -\t━");

            return !empty($s);
        });
        $current = null;
        $segment = [];

        foreach ($lines as $line) {
            switch ($current) {
                case "p":
                    if (preg_match("/^\s+([A-Z\/]+)\s+/", $line, $m)) {
                        $it["Passengers"][] = $m[1];
                    }

                    break;

                case "ss":
                case "sc":
                    if (preg_match("/\s+[A-Za-z]{3} ([A-Za-z]{3} \d{1,2})\s+(\d+)\s+(Depart.+)$/", $line, $m)) {
                        $segment = [];
                        $segment["Date"] = $m[1];
                        $segment["FlightNumber"] = $m[2];
                        $segment["Info"] = trim($m[3]);
                        $current = "sc"; // segment continues
                    } elseif ($current === "sc") {
                        if (strpos($line, "       ") !== false) {
                            $segment["Info"] .= " " . trim($line);
                        } else {
                            $current = "ss";
                        }

                        if (preg_match("/Depart (?<depname>.+) \((?<depcode>[A-Z]{3})\) on (?<airline>.+) at (?<deptime>\d+\:\d+ [AP]M)\s+Arrive in (?<arrname>.+) \((?<arrcode>[A-Z]{3})\) at (?<arrtime>\d+\:\d+ [AP]M)/", trim($segment["Info"]), $m)) {
                            $segment["DepName"] = $m["depname"];
                            $segment["DepCode"] = $m["depcode"];
                            $segment["DepDate"] = strtotime($segment["Date"] . " " . $m["deptime"]);
                            $segment["ArrName"] = $m["arrname"];
                            $segment["ArrCode"] = $m["arrcode"];
                            $segment["ArrDate"] = strtotime($segment["Date"] . " " . $m["arrtime"]);
                            $segment["AirlineName"] = $m["airline"];

                            if ($segment["DepDate"] && $segment["ArrDate"] && $segment["ArrDate"] < $segment["DepDate"]) {
                                $segment["ArrDate"] = strtotime("+1 day", $segment["ArrDate"]);
                            }

                            if (isset($it["ReservationDate"])) {
                                if ($segment["DepDate"] < $it["ReservationDate"]) {
                                    $segment["DepDate"] = strtotime("+1 year", $segment["DepDate"]);
                                }

                                if ($segment["ArrDate"] < $it["ReservationDate"]) {
                                    $segment["ArrDate"] = strtotime("+1 year", $segment["ArrDate"]);
                                }
                            }
                            unset($segment["Date"]);
                            unset($segment["Info"]);
                            $it["TripSegments"][] = $segment;
                            $current = "ss";
                        }
                    } else {
                        if (stripos($line, "What you need to know to travel") !== false || stripos($line, "Fare Rule(s)") !== false) {
                            $current = null;
                        }
                    }

                    break;

                default:
                    if (preg_match("/AIR Confirmation: ([A-Z\d]{6})/", $line, $m)) {
                        $it["RecordLocator"] = $m[1];
                    }

                    if (preg_match("/Confirmation Date: ([\d\/]+)/", $line, $m)) {
                        $it["ReservationDate"] = strtotime($m[1]);
                    }

                    if (preg_match("/Base Fare +(\D+) ([\d\.\,]+)/", $line, $m)) {
                        $it["BaseFare"] = str_replace(",", "", $m[2]);
                        $it["Currency"] = $m[1];

                        if ($it["Currency"] === "$") {
                            $it["Currency"] = "USD";
                        }
                    }

                    if (preg_match("/Total Air Cost +\D+ ([\d\.\,]+)/", $line, $m)) {
                        $it["TotalCharge"] = str_replace(",", "", $m[1]);
                    }
            }
            $line = trim($line);

            if (strpos($line, "Passenger(s)") === 0) {
                $current = "p";
            } // passengers

            if (strpos($line, "Date") === 0 && strpos($line, "Departure/Arrival") !== false) {
                $current = "ss";
            } // segment starts
        }

        return [$it];
    }
}
