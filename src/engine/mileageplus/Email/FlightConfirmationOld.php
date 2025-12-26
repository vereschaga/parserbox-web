<?php

namespace AwardWallet\Engine\mileageplus\Email;

class FlightConfirmationOld extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-1594570.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "FlightConfirmationOld",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'Your United flight confirmation') !== false
            || isset($headers['from']) && stripos($headers['from'], 'UNITED-CONFIRMATION@UNITED.COM') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, "United Airlines Contract of Carriage") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]united\./", $from);
    }

    // Your United flight confirmation

    protected function ParseEmail()
    {
        $it = ["Kind" => "T", "TripSegments" => []];

        $it["RecordLocator"] = $this->http->FindSingleNode("//*[contains(text(), 'Your confirmation number is')]", null, true, "/Your confirmation number is ([A-Z\d]+)/");
        $rows = $this->http->XPath->query("//td[contains(., 'Depart:') and not(.//td)]/parent::tr");

        foreach ($rows as $row) {
            $segment = ["DepDate" => null, "ArrDate" => null, "DepCode" => null, "ArrCode" => null];
            $airline = $this->http->FindSingleNode("td[1]/text()[1]", $row);

            if (preg_match("/^(.+) (\d+)$/", $airline, $m)) {
                $segment["AirlineName"] = $m[1];
                $segment["FlightNumber"] = $m[2];
            }
            $flight = $this->http->FindSingleNode("td[2]", $row);

            if (preg_match("/Depart: ([A-Z]{3}) (\d+:\d+ [AP]M)/", $flight, $m)) {
                $depTime = $m[2];
                $segment["DepCode"] = $m[1];
            }

            if (preg_match("/Arrive: ([A-Z]{3}) (\d+:\d+ [AP]M)/", $flight, $m)) {
                $arrTime = $m[2];
                $segment["ArrCode"] = $m[1];
            }
            $seats = $this->http->FindSingleNode("td[5]", $row, true, "/Seats:(.+)/");

            if (stripos($seats, 'n/a') === false) {
                $segment["Seats"] = $seats;
            }
            $date = $this->http->FindSingleNode("parent::*/tr[1]", $row, true, "/^\w{3}, (\w{3} \d+, \d{4})/");

            if (isset($date) && isset($depTime) && isset($arrTime)) {
                $segment["DepDate"] = strtotime($date . " " . $depTime);
                $segment["ArrDate"] = strtotime($date . " " . $arrTime);

                if ($segment["DepDate"] && $segment["ArrDate"] && $segment["ArrDate"] < $segment["DepDate"]) {
                    $segment["ArrDate"] = strtotime("+ 1 day", $segment["ArrDate"]);
                }
            }
            $it["TripSegments"][] = $segment;
        }
        $it["Passengers"] = $this->http->FindNodes("//td[normalize-space(text()) = 'Name']/following-sibling::td[1]");
        $total = $this->http->FindSingleNode("//*[contains(text(), 'Total price')]");

        if (preg_match("/([A-Z]{3}) ([\d\.\,]+)$/", $total, $m)) {
            $it["TotalCharge"] = str_replace(",", "", $m[2]);
            $it["Currency"] = $m[1];
        }

        return [$it];
    }
}
