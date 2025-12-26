<?php

namespace AwardWallet\Engine\ufly\Email;

class PreAssigned extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Sun Country Reservation#im', 'us', ''],
    ];
    public $reHtml = '#Sun Country Reservation#im';
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#@suncountry\.#i', 'us', ''],
    ];
    public $reProvider = [
        ['#@suncountry\.#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "ufly/it-4429504.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

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
                        return node('//*[contains(normalize-space(text()), "Confirmation Code:")]/following-sibling::td');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return implode(",", array_unique(filter(nodes("//tr[contains(.,'Departing')][1]/preceding-sibling::tr[position()>4 and not(contains(.,'Traveler'))]"))));
                    },
                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        if (preg_match("#(\D+)\s*(.+)#", reni('Amount:\s+(.+)', node("//tr[contains(.,'Amount:')]")), $m)) {
                            $r = [
                                'TotalCharge' => trim($m[2]),
                                'Currency'    => currency(trim($m[1])),
                            ];

                            return $r;
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $cntFly = xpath("//tr[contains(.,'Departing')]")->length;

                        for ($i = 1; $i <= $cntFly; $i++) {
                            $segments[] = xpath("//tr[contains(.,'Departing')][" . $i . "]/following-sibling::tr[1]//preceding-sibling::tr[position()<5]");
                        }

                        return $segments;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return reni('Flight.*? (\d+)');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            if (preg_match("#(?<DepName>.+?),.+?\s+?to\s+(?<ArrName>.+?),.+?#", text($text), $m)) {
                                $r = [
                                    'DepCode' => TRIP_CODE_UNKNOWN,
                                    'ArrCode' => TRIP_CODE_UNKNOWN,
                                    'DepName' => $m['DepName'],
                                    'ArrName' => $m['ArrName'],
                                ];

                                if (preg_match("#.+?\sto\s.+?(Terminal)\s*(?<Term>\S+).+?#i", text($text), $m)) {
                                    $r['ArrivalTerminal'] = $m['Term'];
                                }

                                if (preg_match("#.+?(Terminal)\s*(?<Term>\S+).+?\sto\s#i", text($text), $m)) {
                                    $r['DepartureTerminal'] = $m['Term'];
                                }

                                return $r;
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dt1 = strtotime(uberDateTime(text($text)));

                            $date2 = uberDate(text($text));
                            $time2 = uberTime(text($text), 2);
                            $dt2 = strtotime($date2 . ' ' . $time2);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return reni('Flight \b(\S{2})\b');
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
