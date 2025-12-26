<?php

namespace AwardWallet\Engine\westjet\Email;

class It3951152 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?westjet#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['My WestJet trip details', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]westjet#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]westjet#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "20.06.2016, 15:47";
    public $crDate = "20.06.2016, 15:34";
    public $xPath = "";
    public $mailFiles = "westjet/it-3951152.eml";
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
                        return CONFNO_UNKNOWN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter('#(Flight\s+\w{2}\s+\d+)#i');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Flight\s+(\w{2})\s+(\d+)#i', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 'Departs', 'Arr' => 'Arrives'] as $key => $value) {
                                $r = '#' . $value . ':\s+.*?\s+\((\w{3})\)\s+on\s+\w+,\s+(\w+\s+\d+,\s+\d+.*)#i';

                                if (preg_match($r, $text, $m)) {
                                    $res[$key . 'Code'] = $m[1];
                                    $res[$key . 'Date'] = strtotime($m[2]);
                                }
                            }

                            return $res;
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re('#Duration:\s+(.*)#');
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re('#Stops:\s+(\d+)#');
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
