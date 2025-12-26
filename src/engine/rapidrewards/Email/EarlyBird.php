<?php

namespace AwardWallet\Engine\rapidrewards\Email;

class EarlyBird extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-7.eml";

    // subject: Southwest Airlines EarlyBird Confirmation

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match("/southwestairlines@luv\.southwest\.com/i", $headers['from'])
            || isset($headers['subject']) && (stripos($headers['subject'], 'Southwest Airlines EarlyBird Confirmation') !== false || stripos($headers['subject'], "Southwest Airlines Confirmation") !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'Thanks for purchasing EarlyBird') !== false
            || stripos($body, '://luv.southwest.com/servlet/') !== false
            || stripos($body, '://www.southwest.com/flight/') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return (bool) preg_match("/\.southwest\.com/", $from);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail($parser);

        return [
            "parsedData" => [
                "Itineraries" => $its,
            ],
            "emailType" => "EarlyBird",
        ];
    }

    protected function ParseEmail(\PlancakeEmailParser $parser)
    {
        $it = ["Kind" => "T", "TripSegments" => []];
        $passengers = [];

        $baseDate = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Upcoming Trip:') and not(.//td)]", null, true, "/^Upcoming Trip\s*:\s*(\d+\/\d+\/\d+)$/");

        if (isset($baseDate)) {
            $baseDate = strtotime($baseDate);
        } else {
            $baseDate = strtotime($parser->getHeader('date'));
        }

        $it["RecordLocator"] = $this->http->FindSingleNode("//div[contains(text(), 'Confirmation Number')]/span");

        if (!isset($it["RecordLocator"])) {
            $it["RecordLocator"] = $this->http->FindSingleNode("//*[contains(text(), 'Confirmation Number')]/parent::*/parent::*", null, true, "/Confirmation Number:? ([A-Z\d]{6})/");
        }

        if (!isset($it["RecordLocator"])) {
            $it["RecordLocator"] = $this->http->FindSingleNode("//text()[normalize-space(.) = 'Confirmation Number:']/parent::*/parent::*/parent::*", null, true, "/Confirmation Number: ([A-Z\d]{6})/");
        }
        $flights = $this->http->XPath->query("//tr[contains(., 'Arrive in') and not(.//tr)]");

        if ($flights->length == 0) {
            // probably some inner <table>s
            $flights = $this->http->XPath->query("//tr[contains(normalize-space(.), 'Arrive in') and count(td) > 2]");
        }
        $date = "xxx";

        foreach ($flights as $flightInfo) {
            $segment = [];
            $segment["FlightNumber"] = $this->http->FindSingleNode("td[3]", $flightInfo, true, "/^#(\d+)/");

            if (!isset($segment["FlightNumber"])) {
                continue;
            }
            $passenger = beautifulName($this->http->FindSingleNode("td[1]", $flightInfo));

            if (!in_array($passenger, $passengers)) {
                $passengers[] = $passenger;
            }

            if ($this->http->XPath->query("td", $flightInfo)->length == 4) {
                $date = $this->http->FindSingleNode("td[4]", $flightInfo, true, "/^(\w{3} \w{3} \d+)/");
            }
            $divs = $this->http->FindNodes("td[2]/div/div", $flightInfo);

            if (count($divs) == 2) {
                [$depart, $arrive] = $divs;
            } elseif (preg_match("/^(.+)(Arrive in.+)$/", $this->http->FindSingleNode("td[2]", $flightInfo), $m)) {
                $depart = $m[1];
                $arrive = $m[2];
            } else {
                $depart = $arrive = "";
            }

            if (preg_match('/^Changes? planes/', $depart)) {
                $regexp = "/^Changes? planes( to (?P<airline>.+))? *in (?P<depname>.+)";
            } else {
                $regexp = "/^Depart (?P<depname>.+)( on (?P<airline>.+))?";
            }
            $regexp .= " *at (?P<depdate>\d+:\d{2} *[AP]M)/U";

            if (preg_match($regexp, $depart, $ma)) {
                $segment["DepName"] = $ma["depname"];

                if (preg_match("/^(?P<depname>.+)[ \(](?P<depcode>[A-Z]{3})\)?$/", trim($ma["depname"]), $matches)) {
                    $segment["DepName"] = $matches["depname"];
                    $segment["DepCode"] = $matches["depcode"];
                } else {
                    $segment["DepCode"] = TRIP_CODE_UNKNOWN;
                }
                $segment["AirlineName"] = $ma["airline"];

                if (empty($segment["AirlineName"])) {
                    $segment["AirlineName"] = "WN";
                }
                $segment["DepDate"] = strtotime($date . " " . $ma["depdate"], $baseDate);
            }
            $regexp = "/^Arrive in (?P<arrname>.+) *at *(?P<arrdate>\d+:\d{2} *[AP]M)/U";

            if (preg_match($regexp, $arrive, $ma)) {
                $segment["ArrName"] = $ma["arrname"];

                if (preg_match("/^(?P<arrname>.+)[ \(](?P<arrcode>[A-Z]{3})\)?$/", trim($ma["arrname"]), $matches)) {
                    $segment["ArrName"] = $matches["arrname"];
                    $segment["ArrCode"] = $matches["arrcode"];
                } else {
                    $segment["ArrCode"] = TRIP_CODE_UNKNOWN;
                }
                $segment["ArrDate"] = strtotime($date . " " . $ma["arrdate"], $baseDate);
            }

            if (!empty($segment["DepDate"]) && !empty($segment["ArrDate"]) && $segment["ArrDate"] < $segment["DepDate"]) {
                $segment['ArrDate'] = strtotime("+ 1 day", $segment['ArrDate']);
            }

            foreach (["DepDate", "ArrDate"] as $pre) {
                if ($segment[$pre] < time() - SECONDS_PER_DAY * 150) {
                    $segment[$pre] = strtotime("+1 year", $segment[$pre]);
                }
            }

            $it["TripSegments"][] = $segment;
        }
        $it["Passengers"] = $passengers;

        return [$it];
    }
}
