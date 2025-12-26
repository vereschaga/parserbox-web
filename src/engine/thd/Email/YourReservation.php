<?php

namespace AwardWallet\Engine\thd\Email;

use AwardWallet\Engine\MonthTranslate;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "thd/it-10056162.eml, thd/it-10140109.eml, thd/it-10199287.eml, thd/it-10204101.eml";
    public $reFrom = "confirmation@travelerhelpdesk.com";

    public static $subjects = [
        'smartfares'     => ['Smartfares', '- Your Reservation'],
        'cheapflightnow' => ['Cheapflightnow', '- Your Reservation'],
        'cheapfaresnow'  => ['CheapFaresNow', '- Your Reservation'],
        'lcairlines'     => ['Lowcostairlines', '- Your Reservation'],
    ];
    public $subjectDefault = 'Itinerary';

    public $reBody = 'travelerhelpdesk.com';
    public $reBody2 = [
        "en"=> "Itinerary:",
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
        preg_match_all("#([^:]+)\s*:\s*([A-Z\d]{5,7})\b#", $this->nextText("Airline Confirmation Code(s):"), $ms, PREG_SET_ORDER);
        $rls = [];

        foreach ($ms as $m) {
            $rls[trim($m[1])] = $m[2];
        }

        $tripNumber = $this->http->FindSingleNode("//text()[" . $this->contains("Reservation Code:") . "]/following::text()[normalize-space(.)][1]", null, true, "#^([A-Z\d]{5,7})$#");

        $xpath = "//text()[" . $this->eq("DEPART") . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $airline = $this->http->FindSingleNode("./tr[2]/td[1]", $root, true, "#(.*?)\s+\##");

            if (isset($airline) && isset($rls[$airline])) {
                $airs[$rls[$airline]][] = $root;

                continue;
            } elseif (isset($tripNumber)) {
                $airs[$tripNumber][] = $root;
            } else {
                $this->logger->info("RL not found");
            }
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $tripNumber;

            // Passengers
            $it['Passengers'] = $this->http->FindNodes("//text()[contains(normalize-space(), 'Traveler(s):')]/ancestor::td[1]/following-sibling::td[1]//text()");

            // TicketNumbers
            $it['TicketNumbers'] = $this->http->FindNodes("//text()[contains(normalize-space(), 'Ticket Number(s):')]/ancestor::td[1]/following-sibling::td[1]//text()", null, "#(\d[\d\-]{5,})#");

            // AccountNumbers
            // Cancelled
            // TotalCharge
            // BaseFare
            // Currency
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            $it['Status'] = $this->http->FindSingleNode("//text()[" . $this->starts("Your reservation is") . "]", null, true, "#Your reservation is\s+(\w+)#");

            // ReservationDate
            // NoItineraries
            // TripCategory

            foreach ($roots as $root) {
                if (count($names = explode(" to ", $this->http->FindSingleNode("./tr[1]/td[1]", $root))) != 2) {
                    $this->logger->info("split names failed");
                    $itineraries = [];

                    return;
                }

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[2]/td[1]", $root, true, "#\#\s*(\d+)#");

                // DepCode
                $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", $names[0]);

                // DepName
                $itsegment['DepName'] = $this->re("#(.*?)\s*\([A-Z]{3}\)#", $names[0]);

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextText("DEPART", $root)));

                // ArrCode
                $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})\)#", $names[1]);

                // ArrName
                $itsegment['ArrName'] = $this->re("#(.*?)\s*\([A-Z]{3}\)#", $names[1]);

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->nextText("ARRIVE", $root)));

                // AirlineName
                $itsegment['AirlineName'] = array_search($rl, $rls);

                if (empty($itsegment['AirlineName'])) {
                    $itsegment['AirlineName'] = trim($this->http->FindSingleNode("./tr[2]/td[1]", $root, true, "#(.+)\#\s*\d+#"));
                }

                // Operator
                $itsegment['Operator'] = $this->http->FindSingleNode(".//text()[" . $this->starts("Operated By") . "]", $root, true, "#Operated By\s+(.+)#");

                // Aircraft
                // TraveledMiles
                // AwardMiles
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
            "#^(\d+)/(\d+)/(\d{4}) (\d+:\d+ [AP]M)$#", //04/03/2017 08:25 AM
        ];
        $out = [
            "$2.$1.$3, $4",
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

    private function nextText($field, $root = null, $regexp = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root, true, $regexp);
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
