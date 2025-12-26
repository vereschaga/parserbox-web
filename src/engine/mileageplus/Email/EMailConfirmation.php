<?php

namespace AwardWallet\Engine\mileageplus\Email;

class EMailConfirmation extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-1594571.eml";
    private $date = 0;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader("date"));
        $its = $this->ParseEmail();

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "EMailConfirmation",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'Your E-Mail Confirmation from United') !== false
            || isset($headers['from']) && stripos($headers['from'], '@united.ipmsg.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, "Thank you for choosing United. Your electronic ticket has been issued") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]united\./", $from);
    }

    // Your E-Mail Confirmation from United

    protected function ParseEmail()
    {
        $it = ["Kind" => "T", "TripSegments" => []];

        $it["RecordLocator"] = $this->http->FindSingleNode("//*[contains(text(), 'Confirmation number:')]//strong");
        $tables = $this->http->XPath->query("//table[contains(., 'Depart:') and not(.//table)]");

        foreach ($tables as $table) {
            $segment = ["DepDate" => null, "ArrDate" => null, "DepCode" => null, "ArrCode" => null];
            $airline = $this->http->FindSingleNode(".//tr/td[2]", $table);

            if ($airline) {
                $airline = trim($airline, " *");
            }

            if (preg_match("/^(.+) (\d+)$/", $airline, $m)) {
                $segment["AirlineName"] = trim($m[1]);

                if ($segment["AirlineName"] == 'UNITED') {
                    $segment["AirlineName"] = 'UA';
                }
                $segment["FlightNumber"] = $m[2];
            }
            $flight = implode(" ", $this->http->FindNodes(".//tr/td[3]//text()[normalize-space()]", $table));

            if (preg_match("/Depart: (.+) (\d+:\d+ [AP]M).+Arrive:/", $flight, $m)) {
                $depTime = $m[2];
                $segment["DepName"] = $m[1];
                $segment["DepCode"] = TRIP_CODE_UNKNOWN;
            }

            if (preg_match("/Arrive: (.+) (\d+:\d+ [AP]M).+Seat/", $flight, $m)) {
                $arrTime = $m[2];
                $segment["ArrName"] = $m[1];
                $segment["ArrCode"] = TRIP_CODE_UNKNOWN;
            }

            if (preg_match("/Seat\(s\): (.+)/", $flight, $m)) {
                $segment["Seats"] = $m[1];
            }
            $date = $this->http->FindSingleNode("preceding-sibling::table[1]", $table, true, "/departing .+, (\w+ \d+)/");

            if (isset($date) && isset($depTime) && isset($arrTime)) {
                $segment["DepDate"] = strtotime($date . " " . $depTime, $this->date);
                $segment["ArrDate"] = strtotime($date . " " . $arrTime, $this->date);

                if ($segment["DepDate"] && $segment["ArrDate"] && $segment["ArrDate"] < $segment["DepDate"]) {
                    $segment["ArrDate"] = strtotime("+ 1 day", $segment["ArrDate"]);
                }
            }
            $it["TripSegments"][] = $segment;
        }
        $it["Passengers"] = [$this->http->FindSingleNode("//*[b[contains(text(), 'Name:')]]/text()[last()]")];

        return [$it];
    }
}
