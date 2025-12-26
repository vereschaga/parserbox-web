<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class IsWaitingForYou extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-10294357.eml, tapportugal/it-10512999.eml, tapportugal/it-12265038.eml, tapportugal/it-15355770.eml, tapportugal/it-6830291.eml";

    public $lang = "en";
    private $reFrom = "no-reply@info.flytap.com";
    private $reSubject = [
        "pt"=> "está à sua espera",
        "en"=> "is waiting for you",
    ];
    private $reBody = 'TAP Portugal';
    private $reBody2 = [
        "pt"  => "está à sua espera",
        "en"  => "is waiting for you",
        'en2' => 'We hope you have a good journey on TAP Air Portugal',
    ];

    private static $dictionary = [
        "pt" => [
            "Booking Code:"       => ["Código de Reserva:", 'Código de Reserva'],
            "Hi "                 => "Olá ",
            "Booking Date / Time:"=> "Data / Hora da reserva:",
            "DEPARTURE"           => "PARTIDA",
            "ARRIVAL"             => "CHEGADA",
            "Flight"              => "Voo:",
            "Airport"             => "Aeroporto",
            "Terminal"            => "Terminal",
            "Date"                => "Data",
            "Duration"            => "Duração:",
        ],
        "en" => [
            "DEPARTURE"     => ["DEPARTURE", "Departure"],
            'Booking Code:' => ['Booking Code:', 'Booking reference'],
        ],
    ];
    private $date = null;

    private $year = null;

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

        if (false === stripos($body, $this->reBody) && false === stripos($body, 'TAP Air Portugal')) {
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
        $this->date = EmailDateHelper::calculateOriginalDate($this, $parser);
        //		$this->logger->info('Relative date: '.date('r', $this->date));
        $this->year = date('Y', strtotime($parser->getDate()));

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseHtml(),
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

    private function parseHtml()
    {
        $itineraries = [];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Booking Code:"));

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->starts($this->t("Hi ")) . "]", null, "#" . $this->t("Hi ") . "(.*?)[.,]#");

        // TicketNumbers
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

        if ($date = $this->normalizeDate($this->nextText($this->t("Booking Date / Time:")))) {
            $this->date = $it['ReservationDate'] = $date;
        }
        $xpath = "//text()[" . $this->eq($this->t("DEPARTURE")) . "]/ancestor::td[3]";
        $type = 1;
        $nodes = $this->http->XPath->query($xpath);

        if (
            (0 === $this->http->XPath->query("{$xpath}/descendant::node()[contains(normalize-space(.), '{$this->t('Airport')}') and contains(normalize-space(.), '{$this->t('Terminal')}')]")->length)
            && 0 < ($nodes = $this->http->XPath->query("//td[contains(normalize-space(.), '{$this->t('Duration')}') and not(.//td)]/ancestor::tr[count(descendant::img)=2][1]"))->length
        ) {
            $type = 2;
        }

        if (1 === $type) {
            foreach ($nodes as $root) {
                $itsegment = [];
                // FlightNumber
                if (!$itsegment['FlightNumber'] = $this->re("#^\w{2}[ ]*(\d+)$#", $this->nextText($this->t("Flight"), $root))) {
                    $itsegment['FlightNumber'] = $this->re("#^\w{2}[ ]*(\d+)$#", $this->http->FindSingleNode("./*[1]/descendant::td[1]/*[3]/descendant::text()[normalize-space()][5]", $root));
                }

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("DEPARTURE")) . "]/ancestor::table[1]//td[" . $this->eq($this->t("Airport")) . "]/following-sibling::td");

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("DEPARTURE")) . "]/ancestor::table[1]//td[" . $this->eq($this->t("Terminal")) . "]/following-sibling::td");

                if ($itsegment['DepartureTerminal'] == 'N/A') {
                    unset($itsegment['DepartureTerminal']);
                }
                // DepDate
                $itsegment['DepDate'] = $this->normalizeDate(
                    $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("DEPARTURE")) . "]/ancestor::table[1]//td[" . $this->eq($this->t("Date")) . "]/following-sibling::td", $root) . ', ' .
                    $this->http->FindSingleNode("./*[1]/descendant::td[1]/*[1]/descendant::text()[normalize-space()][1]", $root)
                );

                $itsegment['DepCode'] = $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("ARRIVAL")) . "]/ancestor::table[1]//td[" . $this->eq($this->t("Airport")) . "]/following-sibling::td");

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("ARRIVAL")) . "]/ancestor::table[1]//td[" . $this->eq($this->t("Terminal")) . "]/following-sibling::td");

                if ($itsegment['ArrivalTerminal'] == 'N/A') {
                    unset($itsegment['ArrivalTerminal']);
                }

                // ArrDate
                $itsegment['ArrDate'] = $this->normalizeDate(
                    $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("ARRIVAL")) . "]/ancestor::table[1]//td[" . $this->eq($this->t("Date")) . "]/following-sibling::td", $root) . ', ' .
                    $this->http->FindSingleNode("./*[1]/descendant::td[1]/*[3]/descendant::text()[normalize-space()][1]", $root));

                // AirlineName
                if (!$itsegment['AirlineName'] = $this->re("#^(\w{2})[ ]*\d+$#", $this->nextText($this->t("Flight"), $root))) {
                    $itsegment['AirlineName'] = $this->re("#^(\w{2})[ ]*\d+$#", $this->http->FindSingleNode("./*[1]/descendant::td[1]/*[3]/descendant::text()[normalize-space()][5]", $root));
                }

                if (empty($itsegment['FlightNumber']) && empty($itsegment['AirlineName']) && empty($this->http->FindSingleNode("(.//text()[" . $this->contains($this->t('Flight')) . "])[1]", $root))) {
                    $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
                    $itsegment['AirlineName'] = AIRLINE_UNKNOWN;
                }

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                if (!$itsegment['Duration'] = $this->nextText($this->t("Duration"))) {
                    $itsegment['Duration'] = $this->http->FindSingleNode("./*[1]/descendant::td[1]/*[3]/descendant::text()[normalize-space()][4]", $root);
                }

                // Meal
                // Smoking
                // Stops

                $it['TripSegments'][] = $itsegment;
            }
        } elseif (2 === $type) {
            foreach ($nodes as $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];
                $re = "/(\d{1,2}:\d{2})\s*([A-Z]{3})\s*\((.+)\)\s*{$this->t('Date')}\s*(\d{1,2} \w+)/";

                $node = $this->http->FindSingleNode('descendant::td[descendant::img and not(.//td)][1]/following-sibling::td[1]', $root);

                if (preg_match($re, $node, $m)) {
                    $seg['DepCode'] = $m[2];

                    if ($this->year) {
                        $seg['DepDate'] = $this->normalizeDate($m[4] . ' ' . $this->year . ', ' . $m[1]);
                    }
                    $seg['DepName'] = $m[3];
                }

                $node = $this->http->FindSingleNode("descendant::td[contains(., '{$this->t('Duration')}') and not(.//td)][1]/preceding-sibling::td[1]", $root);

                if (preg_match($re, $node, $m)) {
                    $seg['ArrCode'] = $m[2];

                    if ($this->year) {
                        $seg['ArrDate'] = $this->normalizeDate($m[4] . ' ' . $this->year . ', ' . $m[1]);
                    }
                    $seg['ArrName'] = $m[3];
                }

                $node = $this->http->FindSingleNode("descendant::td[contains(., '{$this->t('Duration')}') and not(.//td)][1]", $root);

                if (preg_match("/{$this->t('Duration')}\s*(.+)\s*{$this->t('Flight')}\s*([A-Z\d]{2})\s*(\d+)/", $node, $m)) {
                    $seg['Duration'] = $m[1];
                    $seg['AirlineName'] = $m[2];
                    $seg['FlightNumber'] = $m[3];
                }

                if ($seat = $this->http->FindSingleNode("following-sibling::tr[1]/descendant::td[normalize-space(.)='Seat reservation' and not(.//td)]/following-sibling::td[1]", $root)) {
                    $seg['Seats'][] = $seat;
                }

                $it['TripSegments'][] = $seg;
            }
        }

        $itineraries[] = $it;

        return $itineraries;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        // $this->http->log($instr);
        $in = [
            "#\b00:(\d+[ap]m)#i",
            "#^(\d+) of ([^\s\d]+) (\d{4}), (\d+:\d+)([AP]M)$#", //10 of July 2017, 00:44PM
            "#^(\d+) of ([^\s\d]+) (\d{4}), (\d+:\d+ GMT)$#", //5 of October 2017, 15:53 GMT
            "#^(\d+) ([^\s\d]+), (\d+:\d+)([AP]M)?$#", //20 Dec, 00:35AM
            '/(\d{1,2} \w+ \d{2,4}, \d+:\d+)/',
        ];
        $out = [
            "12:$1",
            "$1 $2 $3, $4 $5",
            "$1 $2 $3, $4 $5",
            "$1 $2 %Y%, $3 $4",
            '$1',
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // $this->http->log($str);
        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(' ', $relDate, true, $str);
        }

        return strtotime($str, $relDate);
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
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
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
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
