<?php

namespace AwardWallet\Engine\swissair\Email;

class It3963633 extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['SWISS Flugplanänderun', 'blank', ''],
    ];
    public $reFrom = [
        ['#schedule-change@swiss.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#@swiss.com#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "24.06.2016, 13:20";
    public $crDate = "24.06.2016, 13:06";
    public $xPath = "";
    public $mailFiles = "";
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
                        return re('#Buchungsnummer:\s+(\w+)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $s = cell('Flugnummer', +2);

                            if (preg_match('#(\w{2})(\d+)#', $s, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $s = re('#Ihr SWISS-Flug von .* nach .*\.#');

                            if (preg_match('#von\s+.*\s+\((\w{3})\)\s+nach\s+.*\s+\((\w{3})\)#i', $s, $m)) {
                                return [
                                    'DepCode' => $m[1],
                                    'ArrCode' => $m[2],
                                ];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $d = cell('Datum Abflug:', +2);
                            $t1 = cell('Abflugzeit:', +2);
                            $t2 = cell('Ankunftszeit:', +2);

                            if ($d and $t1 and $t2) {
                                $depDate = strtotime($d . ', ' . $t1);
                                $arrDate = strtotime($d . ', ' . $t2);

                                return [
                                    'DepDate' => $depDate,
                                    'ArrDate' => $arrDate,
                                ];
                            }
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
