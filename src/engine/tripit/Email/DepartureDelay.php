<?php

namespace AwardWallet\Engine\tripit\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class DepartureDelay extends \TAccountChecker
{
    public $mailFiles = "tripit/it-12310726.eml, tripit/it-125780456.eml, tripit/it-12624552.eml, tripit/it-128383113.eml, tripit/it-128427098.eml, tripit/it-128985520.eml, tripit/it-129031943.eml, tripit/it-55252902.eml, tripit/it-55972882.eml, tripit/it-56216410.eml, tripit/it-57530879.eml, tripit/it-57853615.eml";

    public $lang = "en";

    private $reFrom = "@tripit.com";
    private $reSubject = [
        "en" => ["alert: Departure dela", "alert: Check-in reminder for your flight", "Departure summary for your flight to", 'alert: Departure change for', 'Pull-in for ', 'alert: Gate update for'],
    ];
    private $reBody = 'TripIt Pro';
    private $reBody2 = [
        "en" => ["Departure delay for", "Check-in reminder for your flight", "Departure summary for your flight to", 'The scheduled departure time for', 'has PULLED IN, now departing', 'The departure gate for '],
    ];

    private static $dictionary = [
        "en" => [
            'TripIt Pro alert:' => ['TripIt Pro alert:', 'Pull-in for '],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        if (strpos($from, $this->reFrom) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'TripIt Pro') === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            foreach ($re as $v) {
                if (stripos($headers["subject"], $v) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            foreach ($re as $v) {
                if (strpos($body, $v) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->reBody2 as $lang => $re) {
            foreach ($re as $v) {
                if (strpos($this->http->Response["body"], $v) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $this->parseHtml($email, $parser);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email, PlancakeEmailParser $parser): void
    {
        $patterns['time'] = '\d{1,2}[:]+\d{1,2}(?:\s*[AaPp][Mm])?';
        $patterns['timezone'] = '(?:[A-Z]{3,5}|[-+][ ]*\d{1,2})'; // GMT    |    AEST    |    +08    |    -03
        $patterns['statusVariants'] = 'DELAYED|ON TIME|CANCELLED|PULLED IN';

        $dateFromTitleStr = $this->http->FindSingleNode("//h1[{$this->starts($this->t("TripIt Pro alert:"))}]", null, true, "/ on (\d+\/\d+\/\d+)$/");
        $dateFromWeatherStr = $this->http->FindSingleNode("//h1[{$this->contains("Arrival:")}]", null, true, "/:\s*(\d+ [A-z]{3,}|[A-z]{3,} \d+)/");

        $r = $email->add()->flight();

        $segmentsTexts = $this->http->FindNodes("//h1[{$this->starts($this->t("TripIt Pro alert:"))}]/ancestor::tr[1]/descendant::text()[normalize-space()]");
        $segmentsText = implode("\n", $segmentsTexts);

        $segmentsPatterns = [
            //UA 4678 (sold as AC 3995) (BOI to SFO) is currently ON TIME, departing gate B21 at 6:39am MDT.
            0 => "#(?:^|Your connection )(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<FlightNumber>\d+?) (?:\((?:operated by|sold\s*as) (?<OperAirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<OperFlightNumber>\d+?)\) )?\((?<DepCode>[A-Z]{3}) to (?<ArrCode>[A-Z]{3})\) (?:is currently|has) (?<Status>{$patterns['statusVariants']}), (?:now )?departing(?: from)?(?: terminal (?<DepartureTerminal>[-\w\s]+?))?\W.*?at (?<DepTime>{$patterns['time']}) {$patterns['timezone']}\.#im",

            // AS 1792 (SEA to LAX) is currently ON TIME, departing at 5:45pm PDT.
            1 => "#^(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z]) (?<FlightNumber>\d+) \((?<DepCode>[A-Z]{3}) to (?<ArrCode>[A-Z]{3})\)"
                . " is currently (?<Status>{$patterns['statusVariants']}), departing at (?<DepTime>{$patterns['time']}) {$patterns['timezone']}(?: \(from original {$patterns['time']} {$patterns['timezone']}\))?\."
                . "\s*(?:Arrival time at [A-Z]{3} is(?: now)? (?<ArrTime>{$patterns['time']}) {$patterns['timezone']}\.|New arrival time is not yet confirmed|)#im",

            // Your flight to OSL departs in 24 hours. QR 947 departs SIN on 13/3/2020 at 8:25pm +08. You may be able to check in now.
            // Your flight to LAX departs in 24 hours. AS departs SJC on 26/5/2020 at 14:30 PDT. You may be able to check in now.
            // Your flight to AMS departs in 24 hours. Flight (sold as KL 1276) departs EDI on 9/14/2019 at 6:00am BST. You may be able to check in now.
            // Your flight to departs in 24 hours. OA 333 (sold as A3 7333) departs CHQ on 1/6/2020 at 09:20 EEST. You may be able to check in now.
            // Your flight to IAD departs in 24 hours. UA 7200 (operated by ET 500) departs ADD on 8/20/2019 at 10:45pm EAT. You may be able to check in now.
            2 => "#^Your flight to (?:(?<ArrCode>[A-Z]{3}) )?departs in 24 hours\. (?:Flight \(sold as )?(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<FlightNumber>\d+)?\)?(?: \(operated by (?<OperAirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<OperFlightNumber>\d+?)\))?.*? departs (?<DepCode>[A-Z]{3}) on (?<day>\d{1,2})\/(?<month>\d{1,2})\/(?<year>\d{4}) at (?<DepTime>{$patterns['time']}).+?\. You may be able to check in now\.#im",

            // Your flight to ROC departs in 24 hours. AA 5250 departs DCA on 2020/3/8 at 15:31 EDT. You may be able to check in now.
            // Your flight to ROC departs in 24 hours. LH 177 (sold as UA 9214) departs TXL on 2020/1/26 at 08:45 CET. You may be able to check in now.
            // Your flight to SIN departs in 24 hours. LH departs CDG on 2021/12/4 at 18:35 CET. You may be able to check in now.
            3 => "#^Your flight to (?:(?<ArrCode>[A-Z]{3}) )?departs in 24 hours\. (?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<FlightNumber>\d{1,5}\b)?.*? departs (?<DepCode>[A-Z]{3}) on (?<year>\d{4})\/(?<month>\d{1,2})\/(?<day>\d{1,2}) at (?<DepTime>{$patterns['time']}).+?\. You may be able to check in now\.#im",

            // Your connection AA 198 (JFK to MXP) is currently ON TIME, departing from terminal 8, gate 3 at 6:10pm EST.
            // Your connection B6 169 (BOS to FLL) is currently ON TIME, departing from terminal C at 09:35 +08.
            // Your connection AA 072 (CLT to CLE) is currently ON TIME, departing from gate E20 at 11:40am EDT.
            // Your connection UA 7200 (operated by ET 500) (DUB to IAD) is currently ON TIME, departing from terminal 1 at 5:45am IST. Your connection may be at risk.
            4 => "#^Your connection (?<OperAirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<OperFlightNumber>\d+?) (?:\((?:operated by|sold\s*as) (?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<FlightNumber>\d+?)\) )\((?<DepCode>[A-Z]{3}) to (?<ArrCode>[A-Z]{3})\) is currently (?<Status>DELAYED|ON TIME|CANCELLED), departing(?: from)?(?: terminal (?<DepartureTerminal>[-\w\s]+?))?\W.*?at (?<DepTime>\d{1,2}[:]+\d{1,2}(?:\s*[AaPp][Mm])?) (?:[A-Z]{3,5}|[-+][ ]*\d{1,2})\.#im",

            // UA 901 (SFO to LHR) is currently DELAYED, departing terminal INTL, gate 102 at 12:40pm PDT (from original 12:20pm PDT). Arrival time at LHR is now 7:03am BST.
            // UA 1798 (SFO to SAN) is currently DELAYED, departing terminal 3, gate 72 at 7:00pm PDT (from original 5:04pm PDT). Arrival time at SAN is now 8:23pm PDT.
            // BA 402 (LHR to BRU) is currently DELAYED, departing terminal 5 at 14:15 GMT (from original 13:45 GMT). New arrival time is not yet confirmed; contact British Airways for more information.
            // Flight (sold as UA 1260) (LAS to DEN) is currently DELAYED, departing terminal 3, gate D53 at 1:14pm PDT (from original 11:40am PDT). Arrival time at DEN is now 4:06pm MDT.
            5 => "#(?:^|Flight \(sold as )(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<FlightNumber>\d+)?(?:\)| \(.+as.+?\))?\s*\((?<DepCode>[A-Z]{3}) to (?<ArrCode>[A-Z]{3})\)"
                . " is currently (?<Status>{$patterns['statusVariants']}), departing(?: from)?(?: terminal (?<DepartureTerminal>[-\w\s]+?))?\W.*?at (?<DepTime>{$patterns['time']}) {$patterns['timezone']}(?: \(from original {$patterns['time']} {$patterns['timezone']}\))?\."
                . "\s*(?:Arrival time at [A-Z]{3} is(?: now)? (?<ArrTime>{$patterns['time']}) (?:{$patterns['timezone']}\.|)|New arrival time is not yet confirmed|)#im",

            // The scheduled departure time for AS 3486 (SJC to LAX) departing 5/12/2021 has been changed to 07:20 PST (from original 07:45 PST). This flight is now scheduled to arrive at 08:43 PST.
            6 => "#The scheduled departure time for (?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<FlightNumber>\d+)?(?: *\(sold as (?<SoldAsAirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<SoldAsFlightNumber>\d{1,5})\))?\s*\((?<DepCode>[A-Z]{3}) to (?<ArrCode>[A-Z]{3})\)"
                . " departing (?<day>\d{1,2})\/(?<month>\d{1,2})\/(?<year>\d{4}) has been changed to (?<DepTime>{$patterns['time']}) {$patterns['timezone']}(?: \(from original {$patterns['time']} {$patterns['timezone']}\))?\."
                . "\s*This flight is now scheduled to arrive at (?<ArrTime>{$patterns['time']}) {$patterns['timezone']}\.#im",

            // The departure gate for AA 1117 (SJD to DFW) is now terminal 2, gate 3B.
            // The departure gate for WE 609 (sold as TG 2609) (HKG to HKT) is now terminal 1, gate 40.
            7 => "#The departure gate for (?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<FlightNumber>\d{0,5})(?: *\(sold as (?<SoldAsAirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<SoldAsFlightNumber>\d{1,5})\))? \((?<DepCode>[A-Z]{3}) to (?<ArrCode>[A-Z]{3})\) is now(?: terminal (?<DepartureTerminal>[-\w\s]+?),)? gate \w+\.#m",

        ];
        $segNumber = 1;

//        $this->logger->debug('$segmentsTexts = '.print_r( $segmentsTexts ?? '',true));
        foreach ($segmentsTexts as $sText) {
            foreach ($segmentsPatterns as $key => $sPattern) {
                if (preg_match($sPattern, $sText, $m)) {

                    if ($segNumber == 2 && !isset($m['day'])) {
                        //it-57530879.eml
                        //it-57853615.eml
                        $email->removeItinerary($r);
                        $email->setIsJunk(true);
                    }

                    $dateFormat = null;
                    $segNumber++;

                    $s = $r->addSegment();

                    if (!empty($m['Status'])) {
                        $s->extra()->status($m['Status']);
                    }

                    $depDate = null;
                    $arrDate = null;

                    if (isset($m['day'], $m['month'], $m['year'])) {
                        if (!empty($dateFromWeatherStr)) {
                            if (preg_match("/(?<month>[[:alpha:]]+)/u", $dateFromWeatherStr, $day)) {
                                $monthNum = MonthTranslate::$MonthNames[$this->lang][mb_strtolower($day['month'])] + 1;

                                if ($monthNum == $m['month']) {
                                    $depDate = $this->normalizeDate($m['month'] . "/" . $m['day'] . "/" . $m['year']);
                                } elseif ($monthNum == $m['day']) {
                                    $depDate = $this->normalizeDate($m['day'] . "/" . $m['month'] . "/" . $m['year']);
                                } elseif ($m['month'] > 12) {
                                    $depDate = $this->normalizeDate($m['month'] . "/" . $m['day'] . "/" . $m['year']);
                                } elseif ($m['day'] > 12) {
                                    $depDate = $this->normalizeDate($m['day'] . "/" . $m['month'] . "/" . $m['year']);
                                } else {
                                    $this->logger->alert('Wrong day in format 2');
                                }
                                if (!empty($date)) {
                                    $arrDate = $this->normalizeDate($dateFromWeatherStr, $date);
                                }
                            }
                        } else {
                            if ($m['month'] > 12) {
                                $depDate = $this->normalizeDate($m['month'] . "." . $m['day'] . "." . $m['year']);
                            } elseif ($m['day'] > 12) {
                                $depDate = $this->normalizeDate($m['day'] . "." . $m['month'] . "." . $m['year']);
                            }
                        }
                    } elseif (!empty($dateFromWeatherStr) && stripos($parser->getCleanFrom(), 'tripit.') !== false) {
                        if (preg_match("/(?<month>[[:alpha:]]+)/u", $dateFromWeatherStr, $day)) {
                            $arrDate = $this->normalizeDate($dateFromWeatherStr, strtotime("-1 day", strtotime($parser->getDate())));
                        }
                    }

                    if (empty($depDate) && !empty($dateFromTitleStr)) {
                        if (preg_match("/(\d+)\/(\d+)\/(\d+)/", $dateFromTitleStr, $mat)) {
                            if ($mat[1] > 12) {
                                $depDate = strtotime($mat[1].'.'.$mat[2].'.'.$mat[3]);
                            } elseif ($mat[2] > 12) {
                                $depDate = strtotime($mat[2].'.'.$mat[1].'.'.$mat[3]);
                            } elseif (stripos($parser->getCleanFrom(), 'tripit.') !== false) {
                                $d1 = strtotime($mat[1].'.'.$mat[2].'.'.$mat[3]);
                                $d2 = strtotime($mat[2].'.'.$mat[1].'.'.$mat[3]);
                                $ed = strtotime("-12 day", strtotime($parser->getDate()));
                                if ($d1 - $ed > 0 && $d2 - $ed > 0){
                                    if ($d1 - $ed <= $d2 - $ed) {
                                        $depDate = $d1;
                                    } elseif ($d1 - $ed > $d2 - $ed) {
                                        $depDate = $d2;
                                    }
                                } elseif ($d1 - $ed > 0) {
                                    $depDate = $d1;
                                } elseif ($d2 - $ed > 0) {
                                    $depDate = $d2;
                                }
                            }
                        }
                    }

//                    $this->logger->debug('$depDate = '.print_r( $depDate ?? '',true));
//                    $this->logger->debug('$arrDate = '.print_r( $arrDate ?? '',true));

                    if (!empty($m['SoldAsAirlineName']) && !empty($m['SoldAsFlightNumber'])) {
                        $s->airline()
                            ->carrierName($m['AirlineName'])
                            ->carrierNumber($m['FlightNumber']);
                        $m['AirlineName'] = $m['SoldAsAirlineName'];
                        $m['FlightNumber'] = $m['SoldAsFlightNumber'];
                    }

                    $s->airline()
                        ->name($m['AirlineName']);

                    if (!empty($m['FlightNumber'])) {
                        $s->airline()->number($m['FlightNumber']);
                    } else {
                        $s->airline()->noNumber();
                    }

                    if (!empty($m['OperAirlineName'])) {
                        $s->airline()
                            ->carrierName($m['OperAirlineName']);
                    }

                    if (!empty($m['OperFlightNumber'])) {
                        $s->airline()
                            ->carrierNumber($m['OperFlightNumber']);
                    }

                    if ($key === 7 && !empty($depDate)) {
                        //Gate update
                        $s->departure()
                            ->noDate()
                            ->day($depDate);
                    } elseif (!empty($depDate) && !empty($m['DepTime'])) {
                        $s->departure()
                            ->date($this->normalizeDate($m['DepTime'], $depDate));
                    }
                    $s->departure()
                        ->code($m['DepCode']);

                    if (!empty($m["DepartureTerminal"])) {
                        $s->departure()->terminal($m["DepartureTerminal"]);
                    }

                    if (empty($m['ArrTime'])
                        && preg_match("/^Arrival time at {$m['ArrCode']} is(?: now)? (?<ArrTime>{$patterns['time']}) {$patterns['timezone']}\./im", $segmentsText, $arr)
                    ) {
                        // Arrival time at JFK is now 6:55pm EST.
                        $m['ArrTime'] = $arr['ArrTime'];
                    }


                    if (!empty($m['ArrTime'])) {
                        $s->arrival()->date($this->normalizeDate($m['ArrTime'], $arrDate ?? $depDate));
                        if (empty($s->getDepDate())) {
                            $s->departure()
                                ->noDate();
                        }
                    } else {
                        $s->arrival()->noDate();
                    }
                    if (!empty($m['ArrCode'])) {
                        $s->arrival()->code($m['ArrCode']);
                    } else {
                        $s->arrival()->noCode();
                    }

                    $this->logger->debug('Found segment by pattern-' . $key);

                    break;
                }
            }
        }

        $confNo = $this->http->FindSingleNode("descendant::text()[{$this->starts("Confirmation #:")}][1]");

        if (preg_match("/^(Confirmation #:)\s*(.+?)(?:\([^\)]*\))?(?:\s*{$patterns['statusVariants']})?$/", $confNo, $m)) {
            // Confirmation #: N9JMUD CANCELLED
            $confs = array_map('trim', preg_split('/(?:, | and )/', $m[2]));

            foreach ($confs as $conf) {
                if (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*:\s*(?<conf>[A-Z\d]{5,7})\s*$/", $conf, $cm)
                || preg_match("/^\s*(?<conf>[A-Z\d]{5,7})\s*\(\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\)\s*$/", $conf, $cm)
                ) {
                    $r->general()->confirmation($cm['conf'], $cm['al'] . ' ' . rtrim($m[1], ': '));
                } else {
                    $r->general()->confirmation($conf, rtrim($m[1], ': '));
                }
            }
        } else {
            $r->general()->noConfirmation();
        }

        $travellers = $tickets = $accounts = [];
        $items = $this->http->FindNodes("//text()[" . $this->starts("Passenger Information:") . "]");

        foreach ($items as $item) {
            // Passenger Information: Marlin Jay Yoder; Frequent Flyer # 474548410; Ticket # 5262194339530
            // Passenger Information: Victoria Jean Yoder; Ticket # 5262194339532
            // Passenger Information: Marlin Yoder
            if (preg_match('/Passenger Information: ([A-z\s.]{3,40})/', $item, $m)) {
                $travellers[] = $m[1];
                $travellers = array_filter(preg_replace("/\s*Frequent Flyer.*/", '', $travellers));
            }

            if (preg_match('/Ticket # ([\w\-]+)/', $item, $m)) {
                $tickets[] = $m[1];
            }

            if (preg_match('/Frequent Flyer # ([\w\- ]+)/', $item, $m)) {
                $accounts[] = $m[1];
            }
        }

        if (!empty($travellers)) {
            $r->general()->travellers(array_unique($travellers), true);
        }

        if (!empty($tickets)) {
            $r->issued()->tickets(array_unique($tickets), true);
        }

        if (!empty($accounts)) {
            $r->program()->accounts(array_unique($accounts), true);
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
//        $this->logger->debug('$instr = '.print_r( $instr,true));
        $in = [
            "#^(\d+)/(\d+)/(\d{4})$#", //4/20/2018
        ];
        $out = [
            "$3-$1-$2",
        ];
        $str = preg_replace($in, $out, $instr);

        if ($relDate !== false) {
            return strtotime($str, $relDate);
        } else {
            return strtotime($str);
        }
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
