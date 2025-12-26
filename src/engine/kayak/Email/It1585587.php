<?php

namespace AwardWallet\Engine\kayak\Email;

class It1585587 extends \TAccountCheckerExtended
{
    public $reFrom = "#@kayak.com#i";
    public $reProvider = "#[.@]kayak.com#i";
    public $rePlain = "#\n[>\s]*From\s*:[^\n]*?kayak.com#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "";

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
                        $a = [
                            "COOK\"OOO" => function ($a) {
                                //werfwert , ),}
                                // \'
                            },
                        ];

                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*([^\n]*?)\s+\#(\d+)\n#", "\n" . $text),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepCode' => re("#\n\s*Departs\s+([A-Z]{3})\s+(\w+\s+\w+\s+\d+\s+\d{4}\s+\d+:\d+\s*[apm]{2})#ms"),
                                'DepDate' => strtotime(re(2)),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'ArrCode' => re("#\n\s*Arrives\s+([A-Z]{3})\s+(\w+\s+\w+\s+\d+\s+\d{4}\s+\d+:\d+\s*[apm]{2})#ms"),
                                'ArrDate' => strtotime(re(2)),
                            ];
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
}
