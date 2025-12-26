<?php

namespace AwardWallet\Engine\tzell\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

// parser similar zoetic/ItineraryPlain
class ItineraryRows extends \TAccountChecker
{
    public $mailFiles = "tzell/it-12382508.eml, tzell/it-12403091.eml, tzell/it-12617199.eml";

    public $lang = "en";
    private $reFrom = "@Tzell.com";
    private $reSubject = [
        "en" => " Trip",
        "en2"=> "Flights for",
    ];
    private $reBody = 'TZELLINFO.COM';
    private $reBody2 = [
        "en"=> "ITINERARY",
    ];

    private static $dictionary = [
        "en" => [],
    ];
    private $date = null;
    private $debugmode = false;

    public function parseHtml()
    {
        $itineraries = [];
        $airs = [];
        $filling = [];
        $current = null;

        // (//text()[contains(normalize-space(.), 'FOR:')]/ancestor::p[1]/following-sibling::p[./following-sibling::p[1][contains(normalize-space(.), ' -')]])[1]
        if (($row = $this->http->XPath->query("(//text()[" . $this->contains("FOR:") . "]/ancestor::p[1]/following-sibling::p[./following-sibling::p[1][" . $this->contains(' -') . "]])[1]")->item(0)) === null) {
            $this->debug("start row not found");

            return;
        }
        $date = null;
        $counter = 0;

        while (($row = $this->http->XPath->query("./following-sibling::p[1]", $row)->item(0)) !== null) {
            $rowstr = $this->http->FindSingleNode(".", $row);
            // 22 APR 18  -  SUNDAY
            if (strlen(trim($rowstr)) == null) {
                $counter++;

                if ($counter >= 5) {
                    $this->debug("end by counter");

                    break;
                }

                continue;
            } elseif ($datestr = $this->re("#^(\d+ [^\s\d]+ \d{2}) - [^\s\d]+$#", $rowstr)) {
                $date = $this->normalizeDate($datestr);

            //###############
            //##   AIRS   ###
            //###############

            // AIR   EMIRATES             FLT:215    BUSINESS       MEALS
            // AIR UNITED AIRLINES FLT:3538 FIRST
            } elseif (strpos($rowstr, 'AIR') !== false && preg_match("#^AIR\s+(?<AirlineName>.*?)\s+FLT:(?<FlightNumber>\d+)\s+(?<Cabin>.*?)(?:\s|$)#", $rowstr, $m)) {
                $this->debug("air1");
                $filling['AIRSEGMENT'] = [];
                $current = 'AIRSEGMENT';
                $filling['AIRSEGMENT']['FlightNumber'] = $m['FlightNumber'];
                $filling['AIRSEGMENT']['AirlineName'] = $m['AirlineName'];
                $filling['AIRSEGMENT']['Cabin'] = $m['Cabin'];
            // OPERATED BY /REPUBLIC AIRLINES DBA UNITED EXPRESS
            } elseif ($current == 'AIRSEGMENT' && preg_match("#^OPERATED BY /(.+)#", $rowstr, $m)) {
                $this->debug("air1-1");
                $filling['AIRSEGMENT']['Operator'] = $m[1];
            // LV NEW YORK JFK                 240P           EQP: AIRBUS A380-800 J
            } elseif ($current == 'AIRSEGMENT' && preg_match("#^(?<DepName>.*?)\s+(?<DepTime>\d{1,2}\d{2}[AP])\s+EQP:\s+(?<Aircraft>.+)#", $rowstr, $m)) {
                $this->debug("air2");
                $filling['AIRSEGMENT']['DepCode'] = TRIP_CODE_UNKNOWN;
                $filling['AIRSEGMENT']['DepName'] = $m['DepName'];
                $filling['AIRSEGMENT']['DepDate'] = $this->normalizeDate($m['DepTime'], $date);
                $filling['AIRSEGMENT']['Aircraft'] = $m['Aircraft'];
            // DEPART: TERMINAL 4                             13HR 10MIN
            } elseif ($current == 'AIRSEGMENT' && preg_match("#^DEPART:\s+(?:TERMINAL\s+)?(?<DepartureTerminal>.*?)\s+(?<Duration>\d+HR.*)#", $rowstr, $m)) {
                $this->debug("air3");
                $filling['AIRSEGMENT']['DepartureTerminal'] = $m['DepartureTerminal'];
                $filling['AIRSEGMENT']['Duration'] = $m['Duration'];
            // AR ABU DHABI INTL               1150A          NON-STOP
            } elseif ($current == 'AIRSEGMENT' && preg_match("#^(?<ArrName>.*?)\s+(?<ArrTime>\d{1,2}\d{2}[AP])\s+(?<Stops>NON-STOP)#", $rowstr, $m)) {
                $this->debug("air4");
                $filling['AIRSEGMENT']['ArrCode'] = TRIP_CODE_UNKNOWN;
                $filling['AIRSEGMENT']['ArrName'] = $m['ArrName'];
                $filling['AIRSEGMENT']['ArrDate'] = $this->normalizeDate($m['ArrTime'], $date);
                $filling['AIRSEGMENT']['Stops'] = $m['Stops'];
            // ARRIVE: TERMINAL 1                             REF: PAEXQU
            } elseif ($current == 'AIRSEGMENT' && preg_match("#^ARRIVE:\s+(?:TERMINAL\s+)?(?<ArrivalTerminal>.*?)\s+REF:\s*(?<RecordLocator>.+)#", $rowstr, $m)) {
                $this->debug("air5");
                $filling['AIRSEGMENT']['ArrivalTerminal'] = $m['ArrivalTerminal'];

                if ($seat = $this->http->FindSingleNode("./following-sibling::p[1]", $row, true, "#SEAT-(\d+[A-Z])(?:\s|$)#")) {
                    $filling['AIRSEGMENT']['Seats'][] = $seat;
                    $row = $this->http->XPath->query("./following-sibling::p[1]", $row)->item(0);
                }
                $airs[$m['RecordLocator']][] = $filling['AIRSEGMENT'];
                unset($filling['AIRSEGMENT']);
                $current = null;

            //#################
            //##   HOTELS   ###
            //#################

            // HOTEL ABU DHABI INTL OUT-26APR
            } elseif (strpos($rowstr, 'HOTEL') !== false && preg_match("#^HOTEL\s+(?<HotelName>.*?)\s+OUT-(?<CheckOutDate>\d+[^\s\d]+)$#", $rowstr, $m)) {
                $this->debug("hotel1");
                $filling['HOTEL'] = [];
                $current = 'HOTEL';
                $filling['HOTEL']['CheckInDate'] = $date;
                $filling['HOTEL']['CheckOutDate'] = $this->normalizeDate($m['CheckOutDate'], $filling['HOTEL']['CheckInDate']);

            // ROSEWOOD HOTELS                 3 NIGHTS
            } elseif ($current == 'HOTEL' && preg_match("#^.*?\s+\d+ NIGHTS#", $rowstr, $m)) {
                $this->debug("hotel2");

            // ROSEWOOD ABU DHABI              1 ROOM     DELUXE ROOM - KING BED
            } elseif ($current == 'HOTEL' && preg_match("#^(?<HotelName>.*?)\s+(?<Rooms>\d+) ROOM\s+(?<RoomType>.+)#", $rowstr, $m)) {
                $this->debug("hotel3");
                $filling['HOTEL']['HotelName'] = $filling['HOTEL']['Address'] = $m['HotelName'];
                $filling['HOTEL']['Rooms'] = $m['Rooms'];
                $filling['HOTEL']['RoomType'] = $m['RoomType'];
                $row = $this->http->XPath->query("./following-sibling::p[1]", $row)->item(0);

            // ABU DHABI AE 00000              RATE-1040AED PER NIGHT          FONE 971-2-8135550              CANCEL BY 06P DAY OF ARRIVAL
            } elseif ($current == 'HOTEL' && preg_match("#^.*?\s+RATE-(?<Rate>\d+\s*[A-Z]{3} PER NIGHT)\s+FONE\s+(?<Phone>[\d-]+)\s+(?<CancellationPolicy>.+)#", $rowstr, $m)) {
                $this->debug("hotel4");
                $filling['HOTEL']['Rate'] = $m['Rate'];
                $filling['HOTEL']['Phone'] = $m['Phone'];
                $filling['HOTEL']['CancellationPolicy'] = $m['CancellationPolicy'];

            // FAX  971-2-8135551
            } elseif ($current == 'HOTEL' && preg_match("#FAX\s+(?<Fax>[\d-]+)#", $rowstr, $m)) {
                $this->debug("hotel5");
                $filling['HOTEL']['Fax'] = $m['Fax'];

            // GUARANTEED LATE ARRIVAL
            } elseif ($current == 'HOTEL' && preg_match("#GUARANTEED LATE ARRIVAL#", $rowstr, $m)) {
                $this->debug("hotel6");

                continue;
            // CONFIRMATION 57071SB067979
            } elseif ($current == 'HOTEL' && preg_match("#^CONFIRMATION\s+(?<ConfirmationNumber>\w+)$#", $rowstr, $m)) {
                $this->debug("hotel7");
                $filling['HOTEL']['ConfirmationNumber'] = $m['ConfirmationNumber'];

            // VIP RQSTS KING BED NON SMK ROOM
            } elseif ($current == 'HOTEL' && preg_match("#RQSTS#", $rowstr, $m)) {
                $this->debug("hotel8");

                continue;
            // 3183 AED APPROXIMATE TOTAL PRICE
            } elseif ($current == 'HOTEL' && preg_match("#^(?<Total>[\d\.\,]+) (?<Currency>[A-Z]{3}) APPROXIMATE TOTAL PRICE$#", $rowstr, $m)) {
                $this->debug("hotel9");
                $filling['HOTEL']['Total'] = $this->amount($m['Total']);
                $filling['HOTEL']['Currency'] = $m['Currency'];

                if ($this->http->FindSingleNode("./following-sibling::p[1]", $row, true, "#INCLUDES#")) {
                    $row = $this->http->XPath->query("./following-sibling::p[1]", $row)->item(0);
                }
                $filling['HOTEL']['Kind'] = 'R';
                $filling["HOTEL"]['GuestNames'][] = $this->http->FindSingleNode("(//text()[" . $this->starts("FOR:") . "])[1]", null, true, "#FOR:\s+(.+)#");
                $itineraries[] = $filling['HOTEL'];
                unset($filling['HOTEL']);
                $current = null;

            // OTHER HOUSTON GEO BUSH
            // THANK YOU FOR TRAVELING WITH US
            } elseif (strpos($rowstr, 'OTHER') !== false && $this->http->FindSingleNode("./following-sibling::p[1]", $row, true, "#THANK YOU#")) {
                break;
            } else {
                $this->debug("unknown row " . $rowstr . " - current: " . $current);

                return;
            }

            if ($this->http->XPath->query("./following-sibling::*[1][name()!='p']", $row)->item(0) !== null) {
                $this->debug("end by: " . $this->http->FindSingleNode("./following-sibling::*[1]", $row));

                break;
            }
            $counter = 0;
        }

        // check filling
        if (count($filling) != 0) {
            $this->debug("filling not empty");

            return;
        }

        foreach ($airs as $rl=>$segments) {
            $it = ['Kind'=>'T', 'RecordLocator'=>$rl, 'TripSegments'=>$segments];
            $it["Passengers"][] = $this->http->FindSingleNode("(//text()[" . $this->starts("FOR:") . "])[1]", null, true, "#FOR:\s+(.+)#");
            $itineraries[] = $it;
        }
        // print_r($itineraries);
        // die();

        return $itineraries;
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
        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

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
            "#^(\d+) ([^\s\d]+) (\d{2})$#", //22 APR 18
            "#^(\d{1,2})(\d{2})([AP])$#", //1150A
        ];
        $out = [
            "$1 $2 20$3",
            "$1:$2 $3M",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
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

    private function debug($str)
    {
        if ($this->debugmode) {
            $this->logger->info($str);
        }
    }
}
