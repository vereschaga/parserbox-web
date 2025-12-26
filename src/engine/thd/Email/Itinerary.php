<?php

namespace AwardWallet\Engine\thd\Email;

use AwardWallet\Engine\MonthTranslate;

// TODO: merge with parser thd/ItineraryFor (in favor of thd/ItineraryFor)

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "thd/it-10140112.eml, thd/it-10143999.eml";
    public $reFrom = "confirmation@travelerhelpdesk.com";

    public static $subjects = [
        'smartfares'     => ['Smartfares', 'Itinerary'],
        'cheapflightnow' => ['Cheapflightnow', 'Itinerary'],
        'cheapfaresnow'  => ['CheapFaresNow', 'Itinerary'],
        'lcairlines'     => ['Lowcostairlines', 'Itinerary'],
    ];
    public $subjectDefault = 'Itinerary';

    public $reBody = 'travelerhelpdesk.com';
    public $reBody2 = [
        "en"=> "Flight Reservation Code:",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public $codeProvider;
    private $bodies = [
        'smartfares' => [
            '//a[contains(@href, "smartfares.com")]',
            '//text()[contains(normalize-space(), "Thank you for choosing Smartfares")]',
            'Thank you for choosing Smartfares',
        ],
        'cheapflightnow' => [
            '//a[contains(@href, "cheapflightnow.com")]',
            '//text()[contains(normalize-space(), "Thank you for choosing Cheapflightnow")]',
            'Thank you for choosing Cheapflightnow',
        ],
        'cheapfaresnow' => [
            '//a[contains(@href, "cheapfaresnow.com")]',
            '//text()[contains(normalize-space(), "Thank you for choosing CheapFaresNow")]',
            'Thank you for choosing CheapFaresNow',
        ],
        'lcairlines' => [
            '//a[contains(@href, "lowcostairlines.com")]',
            '//text()[contains(normalize-space(), "Thank you for choosing Lowcostairlines")]',
            'Thank you for choosing Lowcostairlines',
        ],
    ];

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText("Flight Reservation Code:");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//img[contains(@src, '/images/airlines35/')]/ancestor::table[1]/preceding::p[not(contains(normalize-space(), 'Please Note'))][1]//descendant::text()[normalize-space(.)]");

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText("Total Trip Cost:"));

        // BaseFare
        $BaseFare = $this->http->FindNodes("//text()[" . $this->eq("Ticket Price") . "]/ancestor::tr[1]/following-sibling::tr/td[3]");
        $it['BaseFare'] = 0.0;

        foreach ($BaseFare as $fare) {
            $it['BaseFare'] += $this->amount($fare);
        }

        // Currency
        $it['Currency'] = $this->currency($this->nextText("Total Trip Cost:"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//img[contains(@src, '/images/airlines35/')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[not(" . $this->contains("Depart:") . ")][1]/descendant::text()[normalize-space(.)][1]", $root)));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[" . $this->starts("Flight:") . "]", $root, true, "#Flight:\s+(\d+)#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->re("#\d+:\d+\s+[AP]M\s+(.+)#", $this->nextText("Depart:", $root));

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->re("#(\d+:\d+\s+[AP]M)#", $this->nextText("Depart:", $root)), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->re("#\d+:\d+\s+[AP]M\s+(.+)#", $this->nextText("Arrive:", $root));

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->re("#(\d+:\d+\s+[AP]M)#", $this->nextText("Arrive:", $root)), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root);

            // Operator
            $itsegment['Operator'] = $this->http->FindSingleNode("./td[3]/descendant::text()[contains(normalize-space(), 'Operated By')]", $root, true, "#:\s*(.+)#");

            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[3]/descendant::text()[contains(normalize-space(), 'Flight')]/following::text()[normalize-space()][1]", $root);

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

    public function getProvider(\PlancakeEmailParser $parser)
    {
        if (isset($this->codeProvider)) {
            return $this->codeProvider;
        }

        foreach ($this->bodies as $code => $criteria) {
            foreach ($criteria as $search) {
                if (!(stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                    && !(stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)) {
                    continue 2;
                }
            }

            return $code;
        }

        return null;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!empty($headers['subject'])) {
            foreach (self::$subjects as $code => $arr) {
                if (stripos($headers['subject'], $arr[0]) !== false && stripos($headers['subject'], $arr[1]) !== false) {
                    $this->codeProvider = $code;

                    return true;
                }
            }
        }

        if (strpos($headers["from"], $this->reFrom) !== false && stripos($headers["subject"], $this->subjectDefault) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        $findProvider = false;

        foreach ($this->bodies as $code => $criteria) {
            foreach ($criteria as $search) {
                if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                    || (stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)) {
                    $findProvider = true;

                    break 2;
                }
            }
        }

        if ($findProvider == false && strpos($body, $this->reBody) === false) {
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
        $this->http->FilterHTML = true;
        $itineraries = [];

        //		foreach($this->reBody2 as $lang=>$re){
        //			if(strpos($this->http->Response["body"], $re) !== false){
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if ($code = $this->getProvider($parser)) {
            $result['providerCode'] = $code;
        }

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

    public static function getEmailProviders()
    {
        return array_keys(self::$subjects);
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $in = [
            "#^[^\s\d]+, ([^\s\d]+) (\d+), (\d{4})$#", //Tuesday, February 21, 2017
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
