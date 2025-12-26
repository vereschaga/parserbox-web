<?php

namespace AwardWallet\Engine\rapidrewards\Email;

class TicketlessOld extends \TAccountChecker
{
    // subject: Ticketless Confirmation

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers["from"], "SouthwestAirlines@mail.southwest.com") !== false
        || isset($headers['subject']) && stripos($headers['subject'], 'Ticketless Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'Valid only on Southwest Airlines') !== false;
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
            "emailType" => "TicketlessOld",
        ];
    }

    protected function ParseEmail()
    {
        $it = ["Kind" => "T", "TripSegments" => []];
        $rows = $this->http->FindNodes("//tr[contains(., 'Confirmation Number') and not(.//tr)]/following-sibling::tr[1]");

        foreach ($rows as $row) {
            if (preg_match("/^[A-Z\d]{6}$/", $row)) {
                $it["RecordLocator"] = $row;

                break;
            }
        }
        $cdate = $this->http->FindSingleNode("//text()[contains(., 'Confirmation Date:')]");

        if (preg_match("/\d{1,2}\/\d{1,2}\/(\d{2})/", $cdate, $m)) {
            $year = "20" . $m[1];
            $cdate = strtotime($m[0]);
        } else {
            $year = null;
            $cdate = null;
        }
        $rows = $this->http->XPath->query("//tr[td[contains(., 'Date') and not(.//td)] and td[contains(., 'Date') and not(.//td)]]/following-sibling::tr");

        foreach ($rows as $row) {
            $info = $this->http->FindSingleNode("td[3]", $row);
            preg_match("/Depart (?<name>[^\(]+) \((?<code>[A-Z]{3})\) at (?<time>[\d\:]+ [AP]M)/", $info, $dep);
            preg_match("/Arrive in (?<name>[^\(]+) \((?<code>[A-Z]{3})\) at (?<time>[\d\:]+ [AP]M)/", $info, $arr);
            $date = $this->http->FindSingleNode("td[1]", $row);

            if ($date && $cdate && !empty($dep) && !empty($arr)) {
                $segment = [
                    "DepName"      => $dep["name"],
                    "DepCode"      => $dep["code"],
                    "DepDate"      => strtotime($date . " " . $year . " " . $dep["time"]),
                    "ArrName"      => $arr["name"],
                    "ArrCode"      => $arr["code"],
                    "ArrDate"      => strtotime($date . " " . $year . " " . $arr["time"]),
                    "FlightNumber" => $this->http->FindSingleNode("td[2]", $row),
                    "AirlineName"  => "WN",
                ];

                foreach (["DepDate", "ArrDate"] as $key) {
                    if ($segment[$key] && $segment[$key] < $cdate) {
                        $segment[$key] = strtotime("+1 year", $segment[$key]);
                    }
                }
                $it["TripSegments"][] = $segment;
            }
        }
        $it["Passengers"] = $this->http->FindNodes("//tr[td[contains(., 'Passenger Name') and not(.//td)]]/following-sibling::tr[count(td) >3]/td[1]");

        return [$it];
    }
}
