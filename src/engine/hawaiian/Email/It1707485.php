<?php

namespace AwardWallet\Engine\hawaiian\Email;

class It1707485 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?hawaiian#i', 'blank', ''],
        ['#Hawaiian\s+Airlines#i', 'blank', '-1000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#hawaiian#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#hawaiian#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "10.02.2015, 20:19";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "hawaiian/it-1707482.eml, hawaiian/it-1707484.eml, hawaiian/it-1707485.eml, hawaiian/it-2429079.eml, hawaiian/it-2455175.eml, hawaiian/it-2455228.eml";
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

        if ($this->http->XPath->query("//*[".$this->contains(['Passenger Info and Cost Breakdown'])."]")->length > 0) {
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
                        return re("#\n\s*Confirmation Code:\s*([A-Z\d\-]+)#", $this->text());
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//*[contains(text(), 'HawaiianMiles #')]/preceding::tr[2]");

                        return nice($ppl);
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $account = array_filter(nodes("//text()[starts-with(normalize-space(), 'HawaiianMiles #')]/following::text()[normalize-space()][1]", null, "#^[\d\s]{5,}$#"));

                        return $account;
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        $tickets = array_filter(nodes("//text()[normalize-space() = 'ETicket Number']/ancestor::tr[1][contains(normalize-space(), 'Name')]/following-sibling::tr/td[normalize-space()][2]", null, "#^[\d\s]{5,}$#"));

                        return $tickets;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total Air Travel[^\n]*?\s*\=\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(0));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'TRIP ')]/ancestor::tr[1][contains(., 'FLIGHT')]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("td[4]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepCode' => re("#\(([A-Z]{3})\).*?\(([A-Z]{3})\)#ms", node("td[3]")),
                                'ArrCode' => re(2),
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $info = text(xpath("td[3]"));
                            $date = uberDate($info);

                            $dep = $date . "," . uberTime(1);
                            $arr = $date . "," . uberTime(2);

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#CLASS\s+(.*?)$#", node("td[5]"));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seats = array_values(array_filter(nodes("//*[contains(text(), 'Taxes and Fees')]/ancestor::table[1]/preceding-sibling::table[1]/tbody/tr[./td[2][starts-with(normalize-space(.),'{$it['DepCode']}') and contains(.,'{$it['ArrCode']}')]]/td[3]", null, "#^\s*(\d+\w)\s*$#")));

                            if (empty($seats)) {
                                $seats = array_values(array_filter(nodes("//*[contains(text(), 'Taxes and Fees')]/ancestor::table[1]/preceding-sibling::table[1]/tr[./td[2][starts-with(normalize-space(.),'{$it['DepCode']}') and contains(.,'{$it['ArrCode']}')]]/td[3]", null, "#^\s*(\d+\w)\s*$#")));
                            }

                            return $seats;
                        /*
                        $mark = $it['AirlineName'].' '.$it['FlightNumber']."\s+".$it['DepCode'].'-'.$it['ArrCode'];
                        return re("#\s+$mark\s+(\d+[A-Z]+)#", $this->text());*/
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#Nonstop#") ? 0 : null;
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
