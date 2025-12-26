<?php

namespace AwardWallet\Engine\hawaiian\Email;

class It2503204 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]hawaiianairlines[.]#i', 'blank', ''],
    ];
    public $reHtml = 'Your Hawaiian Airlines Reservation Confirmation';
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]hawaiianairlines[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]hawaiianairlines[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "24.02.2015, 10:07";
    public $crDate = "24.02.2015, 09:55";
    public $xPath = "";
    public $mailFiles = "hawaiian/it-13075036.eml, hawaiian/it-2503204.eml, hawaiian/it-3988809.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public $detectSubject = [
        'Reservation Confirmation',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], '.hawaiianairlines.com') === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers["subject"], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.hawaiianair.com') or contains(@href, '.hawaiianairlines.com')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//*[".$this->contains(['Passenger Information and Cost Breakdown'])."]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'hawaiianairlines.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = parent::ParsePlanEmail($parser);
        return $result;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('Confirmation code:  (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//*[contains(text(), 'eTicket #:')]/preceding::td[1]");

                        return nice($ppl);
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $account = array_filter(nodes("//text()[starts-with(normalize-space(), 'HawaiianMiles #')]/following::text()[normalize-space()][1]", null, "#^[\d\s]{5,}$#"));

                        return $account;
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        $tickets = array_filter(nodes("//text()[normalize-space() = 'eTicket #:']/following::text()[normalize-space()][1]", null, "#^[\d\s]{5,}$#"));

                        return $tickets;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Total Air Travel Cost \( \w+ \)  (.[\d.,]+) \n');

                        return total($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('RESERVATION CONFIRMATION')) {
                            return 'confirmed';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Air Itinerary')]
						/ancestor::table[1]/following-sibling::table");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = rew('\s+ ([A-Z]{2} \d+)');
                            $res = uberAir($fl);

                            $q = white("$fl  \w{3}-\w{3}  (\d+\w) \s+");

                            if (preg_match_all("/$q/isu", $this->text(), $m)) {
                                $res['Seats'] = implode(', ', $m[1]);
                            }

                            return $res;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w{3}) \)');

                            return ure("/$q/isu", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time1 = $this->correctTimeString(uberTime(1));
                            $time2 = $this->correctTimeString(uberTime(2));

                            $date = totime($date);

                            $dt1 = strtotime($time1, $date);
                            $dt2 = date_carry($time2, $dt1);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\s+to\s+.*?\( (\w{3}) \)');

                            return ure("/$q/isu", 1);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $text = reni('Class\s*(.*)$');

                            return $text !== 'Class' ? $text : null;
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            if (rew('Nonstop|Non-stop')) {
                                return 0;
                            }
                        },
                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if (!empty($it['DepCode']) && !empty($it['ArrCode'])) {
                                $seats = array_values(array_filter(nodes("//text()[starts-with(normalize-space(.),'{$it['DepCode']}') and contains(.,'{$it['ArrCode']}')]/ancestor::td[1]/following::td[1]", null, "#^\s*(\d{1,3}[A-Z])\s*$#")));
                            }

                            return $seats;
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function correctTimeString($time)
    {
        if (preg_match("#(\d+):(\d+)\s*([ap]m)#i", $time, $m)) {
            if (($m[1] == 0 && stripos($m[3], 'am') !== false) || $m[1] > 12) {
                return $m[1] . ":" . $m[2];
            }
        }

        return $time;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
                return "starts-with(normalize-space(.), \"{$s}\")";
            }, $field)).')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
                return "contains(normalize-space(.), \"{$s}\")";
            }, $field)).')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
                return 'normalize-space(.)="' . $s . '"';
            }, $field)).')';
    }

}
