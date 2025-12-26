<?php

namespace AwardWallet\Engine\thalys\Email;

class It1836448 extends \TAccountCheckerExtended
{
    public $reFrom = "#thalys#i";
    public $reProvider = "#thalys#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?thalys#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "#Thalys tickets through#i";
    public $reHtmlRange = "5000";
    public $xPath = "";
    public $mailFiles = "thalys/it-1836448.eml";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        return re("#\s+reservation code\s*\(PNR\):\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*Dear ([^,]+),\s+Thank you#"));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*TOTAL AMOUNT\s*:\s*([^\n]+)#"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total amount train tickets\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*TOTAL AMOUNT\s*:\s*([^\n]+)#"));
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            re("#From\s+(.*?)\s+to\s+(.*?)\s+on\s+(.*?)\s+with\s+Thalys\s+Train number\s+(.*?)\s+departure at\s+(\d+:\d+)\s+and\s+arrival\s+at\s+(\d+:\d+)#");

                            $dep = re(3) . "," . re(5);
                            $arr = re(3) . "," . re(6);

                            correctDates($dep, $arr);

                            return [
                                'DepName' => re(1),
                                'ArrName' => re(2),
                                'Type'    => re(4),
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Cabin' => re("#\n\s*(\w+\s+\d+),\s*seat\s+([\d,\s]+)#ims"),
                                'Seats' => nice(re(2)),
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
