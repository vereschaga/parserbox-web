<?php

namespace AwardWallet\Engine\icelandair\Email;

use AwardWallet\Engine\MonthTranslate;

// TODO: merge with parsers icelandair/YourIcelandairTicket (in favor of icelandair/YourIcelandairTicket)

class YourFlightTicket extends \TAccountChecker
{
    public $mailFiles = "icelandair/it-4477913.eml, icelandair/it-6513845.eml, icelandair/it-8930801.eml, icelandair/it-8936041.eml";

    public $reFrom = "@icelandair.is";
    public $reSubject = [
        "en"=> "Your flight ticket:",
        "is"=> "Flugmiðinn þinn:",
    ];
    public $reBody = 'Icelandair';
    public $reBody2 = [
        "en"=> "Booking reference:",
    ];
    public $date;
    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private $parser = null;

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts("Booking reference:") . "]", null, true, "#Booking reference:\s+([A-Z\d]+)#");

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter([$this->http->FindSingleNode("//text()[" . $this->starts("Name:") . "]", null, true, "#Name:\s+(.+)#")]);

        if (count($it['Passengers']) == 0) {
            $it['Passengers'] = array_values(array_filter(array_unique($this->http->FindNodes("//text()[normalize-space(.)='Passengers']/following::table[1]/descendant::text()[normalize-space(.)='Date']/ancestor::table[1]/preceding-sibling::table[1]", null, "#(.+?)\s*(?:\(|$)#"))));
        }

        // TicketNumbers
        $it['TicketNumbers'] = array_filter([$this->http->FindSingleNode("//text()[" . $this->starts("Ticket Number:") . "]", null, true, "#Ticket Number:\s+(.+)#")]);

        if (count($it['TicketNumbers']) == 0) {
            $it['TicketNumbers'] = array_values(array_filter(array_unique($this->http->FindNodes("//text()[normalize-space(.)='Passengers']/following::table[1]/descendant::text()[normalize-space(.)='Date']/ancestor::table[1]//td[3]"))));
        }

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Air fare')]/ancestor::td[position()=1 and count(./preceding-sibling::td[normalize-space(.)!=''])=0]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space(.)!=''][1]"));

        if (!empty($tot['Total'])) {
            $it['BaseFare'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        } else {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Air fare')]", null, true, "#Air fare[\s:]+(.+)#"));

            if (!empty($tot['Total'])) {
                $it['BaseFare'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Taxes and fees')]/ancestor::td[position()=1 and count(./preceding-sibling::td[normalize-space(.)!=''])=2]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space(.)!=''][3]"));

        if (!empty($tot['Total'])) {
            $it['Tax'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Total airfare')]/ancestor::td[position()=1 and count(./preceding-sibling::td[normalize-space(.)!=''])=3]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space(.)!=''][4]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        } else {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Total airfare')]", null, true, "#Total airfare[\s:]+(.+)#"));

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
        }

        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts("Date of issue:") . "]", null, true, "#Date of issue:\s+(.+?\d{4})#")));

        if ($it['ReservationDate'] !== false) {
            $this->date = $it['ReservationDate'];
        }

        // NoItineraries
        // TripCategory

        $xpath = "//text()[" . $this->eq("Dep Time") . "]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[4]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]", $root, true, "#^\w{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[2]", $root);

            // DepName
            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[6]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[7]", $root, true, "#\d+:\d+#"), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]", $root);

            // ArrName
            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[8]", $root, true, "#\d+:\d+#"), $date);

            if ($this->http->FindSingleNode("./td[8]", $root, true, "#\+1#")) {
                $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#^(\w{2})\d+$#");

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[5]", $root);

            // PendingUpgradeTo
            // Seats
            if (!empty($itsegment['DepCode']) && !empty($itsegment['ArrCode'])) {
                $itsegment['Seats'] = array_filter($this->http->FindNodes("//text()[normalize-space(.)='Passengers']/following::table[1]/descendant::text()[normalize-space(.)='Date']/ancestor::table[1][contains(.,'Seat')]//td[2][translate(normalize-space(.),' ','')='" . $itsegment['DepCode'] . "-" . $itsegment['ArrCode'] . "']/following-sibling::td[2]", null, "#^\s*(\d+[A-Z])\s*$#i"));
            }

            if (!isset($itsegment['Seats']) || count($itsegment['Seats']) == 0) {
                $itsegment['Seats'] = $this->http->FindSingleNode("./td[9]", $root);
            }

            if (isset($itsegment['DepCode'], $itsegment['ArrCode'], $itsegment['DepDate'])) {//format 2
                $dc = $itsegment['DepCode'];
                $ac = $itsegment['ArrCode'];
                $td = $this->http->FindSingleNode("./td[7]", $root, true, "#\d+:\d+#");
                $info = $this->http->XPath->query("//text()[contains(.,'{$dc}') and contains(.,'{$ac}')]/ancestor::td[1][contains(.,'{$td}')]");

                if ($info->length == 1) {
                    $node = $info->item(0);
                    $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode(".//text()[contains(.,'Terminal')]", $node, true, "#Terminal[\s:]+(.+)#");
                    $itsegment['Duration'] = $this->http->FindSingleNode("./following-sibling::td[1]//text()[1][contains(.,'h')]", $node);
                    $itsegment['Cabin'] = $this->http->FindSingleNode("./following-sibling::td[1]//text()[contains(.,'Class')]", $node);

                    if (!empty($itsegment['FlightNumber'])) {
                        $itsegment['Aircraft'] = $this->http->FindSingleNode("./descendant::text()[contains(.,'{$itsegment['FlightNumber']}')]/following::text()[normalize-space(.)!=''][1]", $node);
                    }
                }
            }
            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'YourFlightTicket' . ucfirst($this->lang),
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\d\s]+)$#", //26OCT
        ];
        $out = [
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);			// 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);	// 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);	// 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
