<?php

namespace AwardWallet\Engine\virgin\Email;

class It5090865 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "virgin/it-5090865.eml";

    public $reFrom = "do_not_reply@fly.virgin.com";
    public $reSubject = [
        "en"=> "Virgin Atlantic Airways Check In Confirmation",
    ];
    public $reBody = 'Virgin Atlantic';
    public $reBody2 = [
        "en"=> "Your flight details",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $xpath = "//text()[normalize-space(.)='Flight number:']/ancestor::tr[contains(.,'Arrive:')][1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];
        $fls = [];
        $seats = [];

        foreach ($nodes as $root) {
            if (($rl = $this->nextText("Booking reference:", $root)) && !isset($fls[$this->nextText("Flight number:", $root)])) {
                $airs[$rl][] = $root;
                $fls[$this->nextText("Flight number:", $root)] = 1;
            }
            $seats[$this->nextText("Flight number:", $root)][] = $this->nextText("Seat number:", $root);
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='Name:']/following::text()[normalize-space(.)][1]");

            // AccountNumbers
            // Cancelled
            // TotalCharge
            // BaseFare
            // Currency
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory

            foreach ($roots as $root) {
                $date = strtotime($this->normalizeDate($this->nextText("Date:", $root)));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#^\w{2}(\d+)$#", $this->nextText("Flight number:", $root));

                // DepCode
                $itsegment['DepCode'] = $this->re("#^([A-Z]{3})#", $this->nextText("Depart:", $root));

                // DepName
                // DepDate
                $itsegment['DepDate'] = strtotime($this->re("#\s+at\s+(.+)#", $this->nextText("Depart:", $root)), $date);

                // ArrCode
                $itsegment['ArrCode'] = $this->re("#^([A-Z]{3})#", $this->nextText("Arrive:", $root));

                // ArrName
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->re("#\s+at\s+(.+)#", $this->nextText("Arrive:", $root)), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#^(\w{2})\d+$#", $this->nextText("Flight number:", $root));

                // Operator
                // Aircraft
                // TraveledMiles
                // Cabin
                $itsegment['Cabin'] = $this->nextText("Checked in to:", $root);

                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->setBody(str_replace("\n", "", $this->http->Response["body"]));

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+\s+[^\d\s]+\s+\d{4})$#",
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./:]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }
}
