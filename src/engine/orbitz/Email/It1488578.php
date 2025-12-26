<?php

namespace AwardWallet\Engine\orbitz\Email;

class It1488578 extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-1488578.eml, orbitz/it-1565780.eml, orbitz/it-1646597.eml";

    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?orbitz|Orbitz record locator#i', 'us', '8000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#orbitz#i', 'us', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "17.04.2015, 11:17";
    public $crDate = "";
    public $xPath = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (rew('Pick-up:') || $this->http->XPath->query("//tr[ *[normalize-space()][1][normalize-space()='Depart'] and *[4] ]")->length > 0) {
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('Orbitz record locator: (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $q = white('Traveler \d+ [*]? (.+?) (?: [*] | \n)');

                        if (preg_match_all("/$q/isu", $text, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Total due at booking
							(. [\d.,]+)
						');

                        return total($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('This reservation was made on (.+? \d{4})');
                        $date = totime($date);
                        $this->anchor = $date;

                        return $date;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $q = white('(?:
							(?: Leave | Return) [-]* \w+ , |
							Change of airlines
						)');

                        return splitter("/($q)/isu");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $q = white('(?: \n | \] | \* )
								(?P<AirlineName> [a-z\s]+)
								(?P<FlightNumber> \d+)
								(?P<Cabin>Economy|Business) \|
								(?P<Aircraft> .+?) \n
							');
                            $res = re2dict($q, $text);

                            $air = arrayVal($res, 'AirlineName');

                            if (!$air) {
                                return;
                            }
                            $res['FlightLocator'] = orval(
                                reni("$air record locator: (\w+)", $this->text()),
                                CONFNO_UNKNOWN
                            );

                            return $res;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $q = white(', (\w+ \d+)');
                            $date = ure("/$q/isu", 1);
                            $date = orval(
                                date_carry($date, $this->anchor),
                                $this->anchor
                            );

                            if (!$this->anchor) {
                                return;
                            }

                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $dt1 = strtotime($time1, $date);
                            $dt2 = date_carry($time2, $dt1);
                            $this->anchor = $dt2;

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", 2);
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return re("#([\d,.]+\s+mi\b)#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seat = reni('Seats: (.+?) \|');

                            return empty($seat) ? null : [preg_replace('/[-\s]+/', '', $seat)];
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return reni('\| (\d+ hr \d+ min)');
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
}
