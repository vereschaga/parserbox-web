<?php

namespace AwardWallet\Engine\mileageplus\Email;

class Delay extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->http->Response["body"]) {
            $body = $this->http->XPath->query("/*")->item(0)->nodeValue;
        } else {
            $body = $parser->getPlainBody();
        }
        $its = $this->ParseEmail($body);

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "Delayed",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject'])
                    && (stripos($headers['subject'], 'Flight delay - UA') !== false
                    || stripos($headers['subject'], 'Flight reschedule - UA') !== false)
            || isset($headers['from']) && stripos($headers['from'], 'unitedairlines@united.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (!$body) {
            $body = $parser->getPlainBody();
        }

        return stripos($body, "united.com") !== false
                  && (stripos($body, "is delayed") !== false
                      || stripos($body, "is rescheduled") !== false
                      || stripos($body, "is canceled"));
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]united\./", $from);
    }

    // Flight delay - UAXXXX departing ABC
    // Flight reschedule - UAXXXX departing ABC

    protected function ParseEmail($body)
    {
        $it = ["Kind" => "T", "TripSegments" => []];

        if (strlen($body) < 2000) {
            $body = str_replace(["\n", "\t"], " ", $body);
            $this->http->SetBody($body);
            $it["RecordLocator"] = $this->http->FindPreg("/Confirmation number: ([A-Z\d]{6})/i");
            $segment = [];

            if (preg_match("/United flight ([A-Z]{2})(\d+)/", $body, $m)) {
                $segment["AirlineName"] = $m[1];
                $segment["FlightNumber"] = $m[2];
            }

            if (preg_match("/Now\s+departs:\s+(\d+:\d+\s+[ap]\.m\.)\s+on\s+(\w+\s+\d+)[^\(]+\(([A-Z]{3})/", $body, $m)) {
                $segment["DepDate"] = strtotime($m[2] . " " . $m[1]);
                $segment["DepCode"] = $m[3];
            }

            if (preg_match("/Now\s+arrives:\s+(\d+:\d+\s+[ap]\.m\.)\s+on\s+(\w+\s+\d+)\s+at\s+[^\(]+\(([A-Z]{3})/", $body, $m)) {
                $segment["ArrDate"] = strtotime($m[2] . " " . $m[1]);
                $segment["ArrCode"] = $m[3];
            }

            if (preg_match("/Departs:\s+(\d+:\d+\s+[ap]\.m\.)\s+on\s+(\w+\s+\d+)[^\(]+\(([A-Z]{3})/", $body, $m)) {
                $segment["DepDate"] = strtotime($m[2] . " " . $m[1]);
                $segment["DepCode"] = $m[3];
            }

            if (preg_match("/Arrives:\s+(\d+:\d+\s+[ap]\.m\.)\s+on\s+(\w+\s+\d+)\s+at\s+[^\(]+\(([A-Z]{3})/", $body, $m)) {
                $segment["ArrDate"] = strtotime($m[2] . " " . $m[1]);
                $segment["ArrCode"] = $m[3];
            }

            if (preg_match("/([\w\s]+) flight (\d+) ([A-Z]{3})\-([A-Z]{3}) on (\d+[A-Z]{3}) (delayed|rescheduled)/", $body, $m)) {
                $segment["AirlineName"] = $m[1];
                $segment["FlightNumber"] = $m[2];
                $segment["DepCode"] = $m[3];
                $segment["ArrCode"] = $m[4];
                $date = $m[5];
                $it["RecordLocator"] = CONFNO_UNKNOWN;
            }

            if (preg_match("/Departure time now (\d+:\d+ [ap]m)/", $body, $m) && isset($date)) {
                $segment["DepDate"] = strtotime($m[1] . " " . $date);
            }

            if (preg_match("/Arrival time now (\d+:\d+ [ap]m)/", $body, $m) && isset($date)) {
                $segment["ArrDate"] = strtotime($m[1] . " " . $date);
            }

            if (preg_match("/on \w+ \d+ is canceled due to/", $body)) {
                $it["Cancelled"] = true;
                $it["Status"] = "cancelled";
            }
            $it["TripSegments"] = [$segment];
        }

        return [$it];
    }
}
