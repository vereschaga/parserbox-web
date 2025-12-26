<?php

namespace AwardWallet\Engine\saudisrabianairlin\Email;

use AwardWallet\Engine\MonthTranslate;

class BrownTables extends \TAccountChecker
{
    public $mailFiles = "saudisrabianairlin/it-1.eml, saudisrabianairlin/it-2.eml, saudisrabianairlin/it-6610258.eml, saudisrabianairlin/it-7287567.eml";

    public $reSubject = [
        'en' => 'Saudi Confirmation Email',
    ];
    public $reBody = [
        'en' => ['Departure'],
    ];

    public static $dictionary = [
        'en' => [],
    ];

    public $lang = 'en';

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking reference")) . "]/following::text()[string-length(normalize-space(.))>3][1]", null, true, '/([A-Z\d]{5,})\s*-/');

        $passengers = [];
        $accountNumbers = [];
        $seatsByPassengers = [];
        $ticketNumbers = [];
        $passengerRows = $this->http->XPath->query("//text()[" . $this->eq($this->t("Passenger")) . "]/ancestor::tr[1]/following-sibling::tr");

        foreach ($passengerRows as $passengerRow) {
            if ($this->http->FindSingleNode("./descendant::text()[normalize-space(.)][position()=1 and " . $this->starts($this->t("Contact")) . "]", $passengerRow)) {
                break;
            }

            if ($passenger = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][position()=1 and ./ancestor::*[name()='strong' or name()='b']]", $passengerRow)) {
                $passengers[] = $passenger;
            }

            if ($accountNumber = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Alfursan number")) . "]/ancestor::font[1]/following::text()[normalize-space(.)][1]", $passengerRow, true, '/^([A-Z\d\s]{4,})$/')) {
                $accountNumbers[] = $accountNumber;
            }

            if ($seatsByPassenger = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Seat")) . "]/ancestor::font[1]/following::text()[normalize-space(.)][1]", $passengerRow, true, '/^([,A-Z\d\s]{2,})$/')) {
                $seatsByPassengers[] = explode(',', $seatsByPassenger);
            }

            if ($ticketNumber = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("E-ticket document")) . "]/ancestor::font[1]/following::text()[normalize-space(.)][1]", $passengerRow, true, '/^([-\d\s]+)$/')) {
                $ticketNumbers[] = $ticketNumber;
            }
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = array_unique($passengers);
        }

        if (!empty($accountNumbers[0])) {
            $it['AccountNumbers'] = $accountNumbers;
        }

        if (!empty($ticketNumbers[0])) {
            $it['TicketNumbers'] = $ticketNumbers;
        }

        $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::tr[1]/following-sibling::tr";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        $seatsResult = [];
        $countTripSegments = $segments->length;

        for ($i = 0; $i < $countTripSegments; $i++) {
            $seatsBySegment = [];

            foreach ($seatsByPassengers as $seats) {
                $seatsBySegment[] = trim($seats[$i]);
            }

            if (count($seatsBySegment)) {
                $seatsResult[] = $seatsBySegment;
            }
        }

        if ($this->http->XPath->query("./preceding-sibling::tr[ ./descendant::text()[" . $this->eq($this->t("Departure")) . "] ][1][ ./descendant::text()[" . $this->eq($this->t("Arrival")) . "] ]", $segments->item(0))->length > 0) {
            $offset = 1;
        } // with Arrival field
        else {
            $offset = 0;
        } // without Arrival field

        foreach ($segments as $key => $root) {
            $itsegment = [];

            $date = $this->normalizeDate($this->http->FindSingleNode("./td[1]", $root));

            $itsegment['DepDate'] = strtotime($date . ', ' . $this->http->FindSingleNode("./td[2]", $root));

            $itsegment['DepName'] = implode(', ', $this->http->FindNodes("./td[3]/descendant::text()[normalize-space(.)]", $root));

            if ($offset === 0) {
                $itsegment['ArrDate'] = MISSING_DATE;
            } else {
                $timeArr = $this->http->FindSingleNode("./td[4]", $root);

                if (preg_match("#^(\d+:\d+.*?)\s*([\+\-]\s*\d+)?$#", $timeArr, $m)) {
                    $itsegment['ArrDate'] = strtotime($date . ', ' . $m[1]);

                    if (isset($m[2]) && !empty($m[2])) {
                        $itsegment['ArrDate'] = strtotime($m[2] . ' days', $itsegment['ArrDate']);
                    }
                }
            }

            $itsegment['ArrName'] = implode(', ', $this->http->FindNodes("./td[{$offset}+4]/descendant::text()[normalize-space(.)]", $root));

            $flight = $this->http->FindSingleNode("./td[{$offset}+5]", $root);

            if (preg_match('/^([A-Z]{2})\s*(\d+)$/', $flight, $matches)) {
                $itsegment['AirlineName'] = $matches[1];
                $itsegment['FlightNumber'] = $matches[2];
            }

            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[{$offset}+6]", $root, true, '/^([A-Z\d])$/');

            $itsegment['Stops'] = $this->http->FindSingleNode("./td[{$offset}+7]", $root, true, '/^(\d{1,2})\s*stop/i');

            $itsegment['Duration'] = $this->http->FindSingleNode("./td[{$offset}+8]", $root, true, '/^(\d{1,3}\s*[hrs]+[,\s]+\d{1,2}\s*[min]+)$/');

            $cabinTexts = $this->http->FindNodes("./td[{$offset}+10]/descendant::text()[normalize-space(.)]", $root);
            $cabinValues = array_values(array_filter(array_map('trim', $cabinTexts)));

            if (!empty($cabinValues[0])) {
                $itsegment['Cabin'] = substr(implode(' ', $cabinValues), 0, 50);
            }

            $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            if (isset($seatsResult[$key]) && ($seats = $seatsResult[$key])) {
                $itsegment['Seats'] = $seats;
            }

            $it['TripSegments'][] = $itsegment;
        }

        $payment = $this->nextText($this->t("Total amount:"));

        if (preg_match('/([A-Z]{3})([,.\d\s]+)/', $payment, $matches)) {
            $it['Currency'] = $matches[1];
            $it['TotalCharge'] = $this->normalizePrice($matches[2]);
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@saudiairlines.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'ibesupport@saudiairlines.com') !== false) {
            return true;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Saudi Arabian Airlines") or contains(.,"@saudiairlines.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.saudiairlines.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        foreach ($this->reBody as $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $lang => $re) {
            foreach ($re as $item) {
                if (stripos($body, $item) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parseHtml($itineraries);

        $classPart = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($classPart) . ucfirst($this->lang),
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
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
            "#(\d{1,2})\s+([^\d\s]{3,})$#", // Saturday 19 July
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
