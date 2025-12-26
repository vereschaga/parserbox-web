<?php

namespace AwardWallet\Engine\rapidrewards\Email;

class FlightOld extends \TAccountChecker
{
    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers["from"], "noreply@sfly.southwest.com") !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'Southwest Airlines does not have assigned seats') !== false
        || stripos($body, '://www.southwest.com/fares/') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return (bool) preg_match("/[@\.]southwest\.com/", $from);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $pos = stripos($body, "<meta http-equiv=\"Content-Type\"");

        if ($pos !== false) {
            $body = substr($body, 0, $pos) . substr($body, stripos($body, ">", $pos));
            $this->http->SetBody($body);
        }
        $its = $this->ParseEmail();

        return [
            "parsedData" => [
                "Itineraries" => $its,
            ],
            "emailType" => "FlightOld",
        ];
    }

    protected function ParseEmail()
    {
        $travelers = $this->http->FindNodes("//thead[tr[contains(., 'Traveler Names') and not(.//tr)]]/following-sibling::tr/td[1]");
        $flights = [];
        $hotels = [];
        $roots = $this->http->XPath->query("//*[tr[1][contains(., 'Flights')] and tr[2][contains(., 'Air Confirmation')]]");

        foreach ($roots as $root) {
            $segment = [];
            $title = $this->http->FindSingleNode("tr[2]", $root);

            if (preg_match("/^(.+) Air Confirmation \# ([A-Z\d]{6})/", $title, $m)) {
                $segment["AirlineName"] = $m[1];
                $conf = $m[2];
            } else {
                $conf = null;
            }
            $depRow = $this->http->XPath->query("tr[3]//tr[contains(., 'Arrive')]/preceding-sibling::tr[contains(., 'Depart')]", $root);
            $arrRow = $this->http->XPath->query("tr[3]//tr[contains(., 'Depart')]/following-sibling::tr[contains(., 'Arrive')]", $root);

            if ($depRow->length > 0 && $arrRow->length > 0) {
                $depRow = $depRow->item(0);
                $arrRow = $arrRow->item(0);
                $segment = array_merge($segment, [
                    "FlightNumber" => $this->http->FindSingleNode("td[2]", $depRow, true, "/^(\d+)\D/"),
                    "DepName"      => $this->http->FindSingleNode("td[3]", $depRow, true, "/^(.+) - \([A-Z]{3}\)/"),
                    "DepCode"      => $this->http->FindSingleNode("td[3]", $depRow, true, "/^.+ - \(([A-Z]{3})\)/"),
                    "DepDate"      => strtotime($this->http->FindSingleNode("td[5]", $depRow)),
                    "ArrName"      => $this->http->FindSingleNode("td[1]", $arrRow, true, "/^(.+) - \([A-Z]{3}\)/"),
                    "ArrCode"      => $this->http->FindSingleNode("td[1]", $arrRow, true, "/^.+ - \(([A-Z]{3})\)/"),
                    "ArrDate"      => strtotime($this->http->FindSingleNode("td[3]", $arrRow)),
                ]);
            }

            if (isset($conf)) {
                if (!isset($flights[$conf])) {
                    $flights[$conf] = ["Kind" => "T", "RecordLocator" => $conf, "Passengers" => $travelers, "TripSegments" => []];
                }
                $flights[$conf]["TripSegments"][] = $segment;
            }
        }
        $roots = $this->http->XPath->query("//*[tr[1][contains(., 'Hotel')] and tr[2][contains(., 'Supplier Confirmation')]]");

        foreach ($roots as $root) {
            $it = ["Kind" => "R", "Guests" => $travelers];
            $it["ConfirmationNumber"] = $this->http->FindSingleNode("tr[2]", $root, true, "/Confirmation: (\d+)/");
            $table = $this->http->XPath->query(".//*[tr[4][contains(., 'Room description') and not(.//tr)]]", $root);

            if ($table->length > 0) {
                $table = $table->item(0);
                $it["HotelName"] = $this->http->FindSingleNode("tr[1]", $table);
                $it["Address"] = $this->http->FindSingleNode("tr[3]", $table);
                $it["RoomType"] = implode(", ", $this->http->FindNodes("tr[4]//li", $table));
            }
            $it["CheckInDate"] = strtotime($this->http->FindSingleNode(".//tr[contains(., 'Check-in') and not(.//tr)]/td[2]", $root));
            $it["CheckOutDate"] = strtotime($this->http->FindSingleNode(".//tr[contains(., 'Check-out') and not(.//tr)]/td[2]", $root));
            $it["Guests"] = $this->http->FindSingleNode(".//tr[contains(., 'Occupants') and not(.//tr)]/td[2]", $root, true, "/(\d+) Adult/");
            $hotels[] = $it;
        }
        $its = array_merge(array_values($flights), $hotels);

        return $its;
    }
}
