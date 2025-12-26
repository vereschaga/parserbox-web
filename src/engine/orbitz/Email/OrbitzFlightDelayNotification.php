<?php

namespace AwardWallet\Engine\orbitz\Email;

class OrbitzFlightDelayNotification extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-1878570.eml, orbitz/it-1878611.eml";

    public $rePlain = "#See\s+the\s+latest\s+travel\s+conditions\s+-\s+on\s+your\s+mobile\s+device\s+at\s+http://m\.orbitz\.com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Orbitz\s+Flight\s+Delay\s+Notification#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#orbitz#i";
    public $reProvider = "#orbitz#i";
    public $xPath = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+Orbitz\s+record\s+locator\s+for\s+this\s+trip\s+is\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re('#Primary\s+traveler\s+name:\s*([^\.]+)#i')];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#flight\s+\d+\s+is\s+(delayed)#i');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(.*)\s+flight\s+(\d+)#i', $text, $m)) {
                                return [
                                    'AirlineName'  => trim($m[1]),
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Departure\s+from\s+(.*?)\s+\((\w+)#si', $text, $m)) {
                                return [
                                    'DepName' => nice($m[1]),
                                    'DepCode' => $m[2],
                                ];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Estimated\s+arrival\s+at\s+(.*?)\s+\((\w+)#si', $text, $m)) {
                                return [
                                    'ArrName' => nice($m[1]),
                                    'ArrCode' => $m[2],
                                ];
                            }
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
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
