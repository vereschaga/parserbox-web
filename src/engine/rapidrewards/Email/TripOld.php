<?php

namespace AwardWallet\Engine\rapidrewards\Email;

class TripOld extends \TAccountChecker
{
    public $mailFiles = "";

    // old format, grey tables with trips

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers["from"], "SouthwestAirlines@mail.southwest.com") !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, '://luv.southwest.com/servlet/') !== false
        || stripos($body, '://www.southwest.com/flight/') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return (bool) preg_match("/\.southwest\.com/", $from);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            "parsedData" => [
                "Itineraries" => $its,
            ],
            "emailType" => "TripOld",
        ];
    }

    protected function ParseEmail()
    {
        $it = ["Kind" => "T", "TripSegments" => []];

        $cells = $this->http->FindNodes("//td[contains(., 'Confirmation Number') and not(.//td)]");

        foreach ($cells as $cell) {
            if (preg_match("/Confirmation Number: ([A-Z\d]{6})/", $cell, $m)) {
                $it["RecordLocator"] = $m[1];

                break;
            }
        }
        $it["Passengers"] = array_filter($this->http->FindNodes("//tr[contains(., 'Passenger(s)') and not(.//tr)]/following-sibling::tr[not(.//sup)]/td[2]"), "strlen");
        $rows = $this->http->XPath->query("//tr[td[contains(., 'Date') and not(.//td)] and td[contains(., 'Flight') and not(.//td)] and not(preceding-sibling::tr[contains(., 'Date')])]/following-sibling::tr");
        $date = null;

        foreach ($rows as $row) {
            $info = $this->http->FindSingleNode("td[contains(., 'Arrive in')]", $row);

            if (!$info) {
                continue;
            }
            $cell = $this->http->FindSingleNode("td[2]", $row);

            if ($cell && preg_match("/^\w{3} (\w{3} \d+)$/", $cell, $m)) {
                $date = $m[1];
            }
            $segment = [];

            foreach ([
                "/Depart (?<name>[^\(]+)\s*(?:\([^\)]+\))*\((?<code>[A-Z]{3})\) at (?<time>[\d:]+ [AP]M)/" => "Dep",
                "/Change planes in (?<name>[^\(]+)\s*\((?<code>[A-Z]{3})\) (departing )?at (?<time>[\d:]+ [AP]M)/" => "Dep",
                "/Arrive in (?<name>[^\(]+)\s*(?:\([^\)]+\))*\((?<code>[A-Z]{3})\) at (?<time>[\d:]+ [AP]M)/" => "Arr",
            ] as $regexp => $prefix) {
                if (preg_match($regexp, $info, $m) && isset($date)) {
                    $segment[$prefix . "Name"] = trim($m["name"]);
                    $segment[$prefix . "Code"] = $m["code"];
                    $segment[$prefix . "Date"] = strtotime($date . " " . $m["time"]);
                }
            }
            $segment["FlightNumber"] = $this->http->FindSingleNode("td[3]", $row);
            $segment["AirlineName"] = "WN";
            $it["TripSegments"][] = $segment;
        }

        return [$it];
    }
}
