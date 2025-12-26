<?php

namespace AwardWallet\Engine\jetblue\Email;

class It2022132 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?jetblue#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#jetblue#i";
    public $reProvider = "#jetblue#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // Parser toggled off as it is duplicate of TAccountCheckerJetblueEmailitineraryforyourupcomingtrip
                    return null;

                    return [$text = clear("#<[^>]+>#", $this->setDocument('plain'))];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Your\s+confirmation\s+number\s+is\s*([A-Z\d\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = [];
                        re("#\n\s*([A-Za-z ]*?)\s+(?:N/A\s+)?([A-Z\d]{2}|\d+[A-Z])\n#", function ($m) use (&$names) {
                            $names[$m[1]] = 1;
                        }, $text);

                        return array_keys($names);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\w{3},\s*\w{3}\s+\d{2}\s+\d+:\d+\s*[amp.]+\s+\d+:\d+\s*[amp.]+)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            re("#\w{3},\s*(\w{3}\s+\d{2})\s+(\d+:\d+\s*[amp.]+)\s+(\d+:\d+\s*[amp.]+)\s+(.*?)\s*\(([A-Z]{3})\)\s+to\s+(.*?)\s+\(([A-Z]{3})\)\s+(\d+)\s+.*?\s+([A-Z\d]{2})\s+#");

                            $dep = re(1) . ',' . re(2);
                            $arr = re(1) . ',' . re(3);

                            correctDates($dep, $arr);

                            return [
                                'DepDate'      => $dep,
                                'ArrDate'      => $arr,
                                'DepName'      => re(4),
                                'DepCode'      => re(5),
                                'ArrName'      => re(6),
                                'ArrCode'      => re(7),
                                'FlightNumber' => re(8),
                                'AirlineName'  => re(9),
                            ];
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seats = [];
                            re("#\n\s*(\d+[A-Z])\n#", function ($m) use (&$seats) {
                                $seats[] = $m[1];
                            }, $text);

                            return implode(',', $seats);
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
