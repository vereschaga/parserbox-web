<?php

namespace AwardWallet\Engine\kuwait\Email;

use AwardWallet\Engine\MonthTranslate;

class BookingConfirmation2014 extends \TAccountChecker
{
    public $mailFiles = "kuwait/it-7392597.eml, kuwait/it-7407299.eml, kuwait/it-7536702.eml";
    public $reFrom = "e-booking@kuwaitairways.com";
    public $reSubject = [
        "en"=> "Booking Confirmation",
    ];
    public $reBody = 'www.kuwaitairways.com';
    public $reBody2 = [
        "en"=> "Flight Details",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        // echo $this->text;
        $terminals = [];
        $tickets = [];
        $pdfs = $this->parser->searchAttachmentByName("KuwaitAirwaysETicket\d+.PDF");

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($this->parser->getAttachmentBody($pdf));
            $tickets[] = $this->re("#E-TICKET NUMBER\s+(.+)#", $text);

            $flights = substr($text, $s = strpos($text, "FLIGHT DETAILS"), strpos($text, "FARE AND PAYMENT DETAILS") - $s);

            foreach ($this->split("#(FLIGHT  )#", $flights) as $stext) {
                $rows = array_merge([], array_filter(array_map('trim', explode("\n", $stext))));

                if (isset($rows[1])) {
                    $fl = $this->re("#^\w{2}\s+(\d+)#", $rows[1]);

                    if (isset($rows[2]) && $term = $this->re("#\d{4}\s+(.*?)\s{2,}#", $rows[2])) {
                        $terminals[$fl]['Dep'] = $term;
                    }

                    if (isset($rows[5]) && $term = $this->re("#\d{4}\s+(.*?)$#", $rows[5])) {
                        $terminals[$fl]['Arr'] = $term;
                    }
                }
            }
        }
        // die();
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText("BOOKING REFERENCE");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq("Passenger") . "]/ancestor::tr[1]/following-sibling::tr/td[1]", null, "#\d+\.\s+(.+)#");

        // TicketNumbers
        $it['TicketNumbers'] = array_filter($tickets);

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq("Total price for all passengers:") . "]/ancestor::td[1]/following-sibling::td[2]/descendant::text()[normalize-space(.)][1]"));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->http->FindSingleNode("//text()[" . $this->eq("Total price for all passengers:") . "]/ancestor::td[1]/following-sibling::td[2]/descendant::text()[normalize-space(.)][1]"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        $it['Status'] = $this->nextText("Status");

        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->eq("Departure") . "]/ancestor::tr[1]/following-sibling::tr[./td[6]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]", $root, true, "#^\w{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#\(([A-Z]{3})\)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

            // DepartureTerminal
            if (isset($terminals[$itsegment['FlightNumber']]['Dep'])) {
                $itsegment['DepartureTerminal'] = $terminals[$itsegment['FlightNumber']]['Dep'];
            }

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate(implode("", $this->http->FindNodes("./td[2]/descendant::text()[normalize-space(.)][position()>1]", $root))));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#\(([A-Z]{3})\)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

            // ArrivalTerminal
            if (isset($terminals[$itsegment['FlightNumber']]['Arr'])) {
                $itsegment['ArrivalTerminal'] = $terminals[$itsegment['FlightNumber']]['Arr'];
            }

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate(implode("", $this->http->FindNodes("./td[3]/descendant::text()[normalize-space(.)][position()>1]", $root))));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#^(\w{2})\d+$#");

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $this->http->FindSingleNode("./td[5]", $root);

            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[6]", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops
            $itsegment['Stops'] = $this->http->FindSingleNode("./td[4]", $root);

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
            if (stripos($headers["subject"], $re) !== false) {
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
        $this->parser = $parser;

        $this->http->FilterHTML = true;
        $this->http->setBody($parser->getHTMLBody());
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $a = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($a) . ucfirst($this->lang),
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
        // $this->http->log($word);
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
            "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
        ];
        $out = [
            "$2 $3 $4, $1",
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
