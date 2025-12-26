<?php

namespace AwardWallet\Engine\rapidrewards\Email;

class TripAround2016 extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-4237724.eml, rapidrewards/it-4254595.eml";

    protected $year;
    protected $base;

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]southwest\.com/", $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'])) {
            return false;
        }

        return preg_match("/southwestairlines@luv\.southwest\.com/i", $headers['from'])
            || stripos($headers['subject'], 'Southwest Airlines Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'Southwest Airlines does not have assigned seats') !== false
            || stripos($body, '://luv.southwest.com/servlet/') !== false
            || stripos($body, '://www.southwest.com/flight/login') !== false
            || stripos($body, 'Thanks for choosing Southwest® for your trip') !== false
            || $this->http->XPath->query('//*[contains(.,"24 hours before your trip on Southwest.com")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $pos = stripos($body, "<meta http-equiv=\"Content-Type\"");

        if ($pos === false) {
            $pos = stripos($body, "<meta http-equiv=Content-Type");
        }

        if ($pos !== false) {
            $body = substr($body, 0, $pos) . substr($body, stripos($body, ">", $pos));
            $this->http->SetBody($body);
        }
        $its = $this->parseEmail($parser);

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'TripAround2016',
        ];
    }

    protected function parseEmail(\PlancakeEmailParser $parser)
    {
        $date = $parser->getDate();
        $this->base = strtotime($date);

        if (!empty($this->base)) {
            $this->year = date('Y', $this->base);
        }
        // conf numbers, sometimes there are multiple
        $confRows = $this->http->XPath->query('//tr[td[contains(.,"Confirmation")]/following-sibling::td[contains(.,"Passenger")]]/following-sibling::tr[1]/td');

        if ($confRows->length !== 2) {
            return [];
        }
        $confLines = $this->http->FindNodes('.//text()[normalize-space(.)!=""]', $confRows->item(0));
        $passengerLines = $this->http->FindNodes('.//text()[normalize-space(.)!=""]', $confRows->item(1));

        if (count($confLines) !== count($passengerLines)) {
            return [];
        }
        $nums = [];

        foreach ($confLines as $i => $conf) {
            if (preg_match('/^[A-Z\d]{6}$/', $conf)) {
                $nums[$conf] = $passengerLines[$i];
            }
        }

        if (count($nums) === 0) {
            return [];
        }
        // segments
        $segments = [];
        $date = null;
        $rows = $this->http->XPath->query('//tr[td[contains(.,"Date")]/following-sibling::td[contains(.,"Flight")]]/ancestor::tr[following-sibling::tr[contains(.,"Arrive")]]/following-sibling::tr//tr[count(td)=3]');

        foreach ($rows as $row) {
            $segment = [];
            $tds = $this->http->FindNodes('td', $row);

            if (!empty($tds[0]) && preg_match('/\w{3} \d{1,2}$/', $tds[0], $m)) {
                $date = $m[0];
            }

            if (preg_match('/^\d{1,4}$/', $tds[1])) {
                $segment['FlightNumber'] = $tds[1];
            }

            if (preg_match('/^Depart (?<depname>.+) \((?<depcode>[A-Z]{3})\) on (?<airline>.+) at (?<deptime>\d+:\d+ [AP]M)\. Arrive in (?<arrname>.+) \((?<arrcode>[A-Z]{3})\) at (?<arrtime>\d+:\d+ [AP]M)\.( Stops.+)?$/', $tds[2], $m)
            || preg_match('/^Change planes to (?<airline>.+) in (?<depname>.+) \((?<depcode>[A-Z]{3})\) at (?<deptime>\d+:\d+ [AP]M)\. Arrive in (?<arrname>.+) \((?<arrcode>[A-Z]{3})\) at (?<arrtime>\d+:\d+ [AP]M)\.( Stops.+)?$/', $tds[2], $m)) {
                $segment['AirlineName'] = $m['airline'];
                $segment['DepName'] = $m['depname'];
                $segment['ArrName'] = $m['arrname'];
                $segment['DepCode'] = $m['depcode'];
                $segment['ArrCode'] = $m['arrcode'];
                $segment['DepDate'] = $this->realDate(sprintf('%s %s', $m['deptime'], $date), $this->year, $this->base);
                $segment['ArrDate'] = $this->realDate(sprintf('%s %s', $m['arrtime'], $date), $this->year, $this->base);
                $segments[] = $segment;
            }
        }
        $its = [];

        foreach ($nums as $conf => $passenger) {
            $its[] = [
                'Kind'          => 'T',
                'RecordLocator' => $conf,
                'Passengers'    => [$passenger],
                'TripSegments'  => $segments,
            ];
        }

        return $its;
    }

    protected function realDate($date, &$year, $baseDate)
    {
        if ($year && $baseDate) {
            while (($result = strtotime($date . ' ' . $year)) < $baseDate) {
                $year++;
            }
        } else {
            $result = strtotime($date);
        }

        return $result;
    }
}
