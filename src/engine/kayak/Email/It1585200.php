<?php

namespace AwardWallet\Engine\kayak\Email;

class It1585200 extends \TAccountCheckerExtended
{
    public $reFrom = "#@kayak.com#i";
    public $reProvider = "#[.@]kayak.com#i";
    public $rePlain = "#\n[>\s]*From\s*:[^\n]*?kayak\.com#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#flight.*?\d+\s*from\s*\w+\s*on\s*time#i";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "kayak/it-11.eml, kayak/it-12.eml, kayak/it-13.eml, kayak/it-1559193.eml, kayak/it-1559580.eml, kayak/it-1562726.eml, kayak/it-1568490.eml, kayak/it-1568491.eml, kayak/it-1585200.eml, kayak/it-1585587.eml, kayak/it-6.eml, kayak/it-7.eml, kayak/it-9.eml";

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
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\s*(.*?)\s+\#(\d+)\s+#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepCode' => re("#\n\s*Departs\s+([A-Z]{3})\s+([^\n]+\s+\d+:\d+(?:\s*[apm]{2})*)#"),
                                'DepDate' => strtotime(en(uberDateTime(re(2)))),
                            ];
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return [
                                'ArrCode' => re("#\n\s*Arrives\s+([A-Z]{3})\s+([^\n]+\s+\d+:\d+(?:\s*[apm]{2})*)#"),
                                'ArrDate' => strtotime(en(uberDateTime(re(2)))),
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
