<?php

namespace AwardWallet\Engine\rapidrewards\Email;

// TODO: merge with parsers mileageplus/FlightPlain (in favor of mileageplus/FlightPlain)

class InfoPlainText extends \TAccountChecker
{
    public $mailFiles = "";

    public $reBody = [
        'en'  => ['Confirmation #(s):', 'Arrives:'],
        'en2' => ['Confirmation #:', 'Arrives:'],
        'en3' => ['Confirmation number:', 'Arrive:'],
    ];
    public $reSubject = [
        '#[A-Z].+\s+Flight\s+\d+(\s*/\s*\d+)?\s+[A-Z].+\s+to\s+[A-Z].+#', //Southwest Flight 39 Orlando to Raleigh-Durham, Southwest Flight 4538 / 3831 Burbank to St. Louis
    ];
    public $emailSubject = '';
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();
        $this->emailSubject = $parser->getHeader("subject");
        $its = $this->parseEmail($body);

        return [
            'emailType'  => "InfoPlainText",
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        foreach ($this->reBody as $re) {
            if ((strpos($body, $re[0]) !== false) && (strpos($body, $re[1]) !== false)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail($body)
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $len = strlen($body);
        $segments = preg_split("#[ ]*(?:Departing|Returning)[ ]*Flight:[ ]*#", $body);

        if (empty($segments) || (!empty($segments[0]) && $len === strlen($segments[0]))) {
            $segments = preg_split("#\n.*Flight:\s*#", $body);
        }

        $mainInfo = array_shift($segments);

        if (preg_match("#Confirmation\s+\#?(?:\(s\)|number)?:\s*([A-Z\d]{5,7})#", $mainInfo, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        if (preg_match("/Passenger\s+name\(?s\)?:\s*([\s\S]+)($|\n.*:)/", $mainInfo, $m)) {
            $it['Passengers'] = array_filter(array_map(function ($e) { return trim(str_replace('<br>', '', $e)); }, preg_split("#[,\n]#", $m[1])));
        }

        $type = 1;
        $anchor = false;
        array_walk($segments, function ($el) use (&$anchor) {
            if (false !== stripos($el, 'Travelers')) {
                $anchor = true;
            }
        });

        if ($anchor) {
            $type = 2;
        }

        switch ($type) {
            case 1:
                foreach ($segments as $segment) {
                    $seg = [];

                    // Thu, Nov 21, 2019
                    if (preg_match("#\s*(\w+, \w+ \d{1,2}, \d{2,4})#", $segment, $m)) {
                        $date = trim($m[1]);
                    }

                    if (preg_match("#([A-Z][^:]+)\s+Flight\s+\d+(\s*/\s*\d+)?\s+[A-Z]#", $this->emailSubject, $m)) {
                        $seg['AirlineName'] = $m[1];
                    }

                    if (preg_match("#Flight\s+\#:\s*(\d+)#", $segment, $m)) {
                        $seg['FlightNumber'] = $m[1];
                    }

                    if (preg_match("#Departs:\s+(?<time>\d+:\d+\s*([AP]M)?)\s+(?<code>[A-Z]{3})#", $segment, $m)) {
                        $seg['DepCode'] = $m['code'];

                        if ($date) {
                            $seg['DepDate'] = strtotime($date . ' ' . $m['time']);
                        }
                    }

                    if (preg_match("#Arrives:\s+(?<time>\d+:\d+\s*([AP]M)?)\s+(?<code>[A-Z]{3})#", $segment, $m)) {
                        $seg['ArrCode'] = $m['code'];

                        if ($date) {
                            $seg['ArrDate'] = strtotime($date . ' ' . $m['time']);
                        }
                    }

                    if (preg_match("#\n\s*(\d+)\s*stop#", $segment, $m)) {
                        $seg['Stops'] = $m[1];
                    }

                    if (preg_match("#\n\s*Nonstop#", $segment, $m)) {
                        $seg['Stops'] = 0;
                    }

                    if (preg_match("#Travel time:\s([\w ]+)#", $segment, $m)) {
                        $seg['Duration'] = $m[1];
                    }
                    $it['TripSegments'][] = $seg;
                }

                break;

            case 2:
                foreach ($segments as $segment) {
                    $seg = [];
                    // UA 74
                    // Depart: IAH - Houston on Wed, Jan 1 2015 at 13:35 AM
                    // Arrive: NRT - Tokyo on Thu, Jan 3 2015 at 23:23 PM
                    // Fare class: United Economy
                    // Meal: Lunch
                    // Travelers: Diiana Pirce
                    $re = '/^[ ]*(?<AirlineName>[A-Z\d]{2})[ ]+(?<FlightNumber>\d+)\s*Depart\:[ ]+(?<DepCode>[A-Z]{3})[ ]*\-[ ]*.+ on \w+, (?<DepMonth>\w+) (?<DepDay>\d{1,2}) (?<DepYear>\d{2,4}) at (?<DepTime>\d{1,2}\:\d{2} [AP]M)\s*Arrive\:[ ]+(?<ArrCode>[A-Z]{3})[ ]*\-[ ]*.+ on \w+, (?<ArrMonth>\w+) (?<ArrDay>\d{1,2}) (?<ArrYear>\d{2,4}) at (?<ArrTime>\d{1,2}\:\d{2} [AP]M)\s*Fare class\: (?<Cabin>.+)\s*Meal\: (?<Meal>.+)\s*Travelers\: (?<Pax>.+)/';

                    if (preg_match($re, $segment, $m)) {
                        foreach (['AirlineName', 'FlightNumber', 'DepCode', 'ArrCode', 'Cabin', 'Meal'] as $i => $name) {
                            $seg[$name] = trim($m[$name]);
                        }
                        $depDate = strtotime($m['DepDay'] . ' ' . $m['DepMonth'] . ' ' . $m['DepYear'] . ' ' . $m['DepTime']);

                        if (false !== $depDate) {
                            $seg['DepDate'] = $depDate;
                        }
                        $arrDate = strtotime($m['ArrDay'] . ' ' . $m['ArrMonth'] . ' ' . $m['ArrYear'] . ' ' . $m['ArrTime']);

                        if (false !== $arrDate) {
                            $seg['ArrDate'] = $arrDate;
                        }
                    }
                    $travellers[] = array_map(function ($e) { return trim(str_replace('<br>', '', $e)); }, preg_split("#[,\n]#", trim($m['Pax'])));
                    $it['TripSegments'][] = $seg;
                }
                $it['Passengers'] = array_filter(array_unique($travellers));

                break;
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    /*
     *
email 1
    Confirmation #(s): LOXUVL
    Passenger name(s): James Flowers, Lisa Flowers
    Departing Flight:
    Mon, Oct 2, 2017
    Flight #: 1022/4386
    Departs: 10:05 AM IND
    1 stop change planes LAS
    Arrives: 1:35 PM SNA
    Travel time: 6 hrs 30 mins

    Returning Flight:
    Sat, Oct 7, 2017
    Flight #: 5452/5140
    Departs: 11:20 AM SNA
    1 stop change planes DEN
    Arrives: 7:45 PM IND
    Travel time: 5 hrs 25 mins

----------------------------------------------------------
email 2
    Confirmation #: U3VXQA
    Passenger names: DANIEL MADDIGAN
    HEATHER MADDIGAN

    Departing Flight: Thu, Oct 12, 2017
    Flight #: 4641
    Departs: 09:00 PM SNA
    Arrives: 12:10 AM DEN
    Travel time: 2 hrs 10 mins

     */
}
