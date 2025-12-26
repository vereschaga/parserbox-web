<?php

namespace AwardWallet\Engine\kayak\Email;

class It1585301 extends \TAccountCheckerExtended
{
    public $reFrom = "#@kayak\.com#i";
    public $reProvider = "#[.@]kayak\.com#i";
    public $rePlain = "#\n[>\s]*From\s*:[^\n]*?kayak\.#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#24 hour check-in alert for#i";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "kayak/it-10.eml, kayak/it-15.eml, kayak/it-1553299.eml, kayak/it-1560301.eml, kayak/it-1583342.eml, kayak/it-1585301.eml, kayak/it-5.eml, kayak/it-8.eml";

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
                        return re("#\s+Confirmation:\s*(?:[A-Z\d]{2}/)?([\w\d\-]{5,7})\b#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\s*(.*?)\s+\#(\d+),#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepDate' => strtotime(re("#\n\s*Departs\s+([^\n]+\s+\d+:\d+(?:\s*[apm]{2})*)\s+[^\n]*?\n\s*\b([A-Z]{3})\b#ms")),
                                'DepCode' => re(2),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'ArrDate' => strtotime(re("#\n\s*Arrives\s+([^\n]+\s+\d+:\d+(?:\s*[apm]{2})*)\s+[^\n]*?\n\s*\b([A-Z]{3})\b#ms")),
                                'ArrCode' => re(2),
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
