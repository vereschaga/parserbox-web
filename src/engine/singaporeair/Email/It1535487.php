<?php

namespace AwardWallet\Engine\singaporeair\Email;

class It1535487 extends \TAccountCheckerExtended
{
    public $reFrom = "#singaporeair#i";
    public $reProvider = "#singaporeair#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?singaporeair#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "singaporeair/it-1535487.eml";
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
                        return re("#\n\s*REF:\s*([A-Z\d\-]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*TL:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*TL:\s*([^\n]+)#"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*FR:\s*([A-Z]{3}\s*[\d.,]+)#"));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $total = 0;
                        re("#\s+TX:\s*[A-Z]+\s+([\d.,]+)#", function ($m) use (&$total) {
                            $total += cost($m[1]);
                        }, $text);

                        return $total;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*NAME:\s*([A-Z/ .\d,]+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        re("#CP\s+CR\s+FLT\s+CL\s+DATE\s+BRD\s+OFF\s+TIME\s+ST\s+FARE\s+BASIS\s+BGA(\s+.*?)\s+FARE#ms");

                        return splitter("#(\n\s*\d+\.\s+[A-Z\d]{2}\s+\d+\s+[A-Z]\s+)#", re(1));
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            re("#\n\s*\d+\.\s+([A-Z\d]{2})\s+(\d+)\s+([A-Z])\s+(\d+\w+\d+)\s+([A-Z]{3})\s+([A-Z]{3})\s+(\d+)\s+(\w+)#");

                            $arr = $dep = strtotime(re(4) . ', ' . re(7));

                            return [
                                'AirlineName'  => re(1),
                                'FlightNumber' => re(2),
                                'DepCode'      => re(5),
                                'ArrCode'      => re(6),
                                'DepDate'      => $dep,
                                'ArrDate'      => $arr,
                                'BookingClass' => re(3),
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
