<?php

namespace AwardWallet\Engine\singaporeair\Email;

class It1596805 extends \TAccountCheckerExtended
{
    public $reFrom = "#singaporeair#i";
    public $reProvider = "#singaporeair#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?singaporeair#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "singaporeair/it-1596805.eml";
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
                        return re("#\n\s*Booking Reference\s*:\s*([^\n]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $r = re("#\n\s*Passenger Name\s*\(s\)\s*:\s*(.*?)\s+Booking#");
                        $names = [];
                        re("#\b\d+\.\s*([A-Z,./ ]+)#", function ($m) use (&$names) {
                            $names[] = trim($m[1]);
                        }, $r);

                        return $names;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\n\s*[A-Z\d]{2}\s+\d+\s+\d+\s+\w+\s+\d{4})#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            re("#\n\s*([A-Z\d]{2})\s+(\d+)\s+(\d+\s+\w+\s+\d{4})\s+(.*?)\s+to\s+(.*?)\s+(\d+)\-(\d+)(?:\*\d+ \w+)*\s+(\w+)#");

                            $dep = strtotime(re(3) . ',' . re(6));
                            $arr = strtotime(re(3) . ',' . re(7));

                            if ($dep > $arr) {
                                $arr = strtotime('+1 day', $arr);
                            }

                            return [
                                'AirlineName'  => re(1),
                                'FlightNumber' => re(2),
                                'DepDate'      => $dep,
                                'ArrDate'      => $arr,
                                'DepName'      => re(4),
                                'ArrName'      => re(5),
                                'Cabin'        => re(8),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
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
