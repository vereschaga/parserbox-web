<?php

namespace AwardWallet\Engine\klm\Email;

class It4723666 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "klm/it-4723666.eml";

    public $reFrom = "donotreply@klm.com";
    public $reSubject = [
        "nl"=> "Mijn boekingsinformatie",
    ];
    public $reBody = 'KLM';
    public $reBody2 = [
        "nl" => "Mijn Reis",
        'en' => 'You can make use of your booking code there to',
    ];

    public static $dictionary = [
        "nl" => [],
        'en' => [
            'Boekingscode' => 'Booking code',
            'Van'          => 'From',
        ],
    ];

    public $lang = "nl";

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

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
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

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

    private function parseHtml(&$itineraries)
    {
        $xpath = "//text()[normalize-space(.)='{$this->t('Van')}']/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            if ($rl = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(./td[2])='{$this->t('Boekingscode')}'][1]/td[4]", $root)) {
                $airs[$rl][] = $root;
            }
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
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
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

                $itsegment['AirlineName'] = AIRLINE_UNKNOWN;

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./td[4]", $root);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[4]", $root)));

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./td[6]", $root);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[5]", $root)));

                // AirlineName
                // Operator
                // Aircraft
                // TraveledMiles
                // Cabin
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
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        $str = preg_replace_callback(
            '#^(\d+)\s+(?<month>[^\d\s]+)\s+(\d{4}),\s+(\d+:\d+)$#',
            [$this, 'replace_month'],
            $str
        );

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

    private function replace_month($m)
    {
        $in = ["mrt"];
        $out = ["March"];

        if (isset($m['month'])) {
            if ($month = $this->dateStringToEnglish($m['month'])) {
                $m[0] = str_replace($m['month'], $month, $m[0]);
            } else {
                $m[0] = str_replace($in, $out, $m[0]);
            }
        }

        return $m[0];
    }
}
