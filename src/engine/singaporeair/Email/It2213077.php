<?php

namespace AwardWallet\Engine\singaporeair\Email;

class It2213077 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?singaporeair#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#singaporeair#i";
    public $reProvider = "#singaporeair#i";
    public $caseReference = "6915";
    public $isAggregator = "0";
    public $fnDateFormat = "";
    public $xPath = "";
    public $mailFiles = "singaporeair/it-2213077.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text = $this->setDocument("plain")];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#Booking Reference Number\s*:\s*([A-Z\d-]+)#ix");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Passenger Name[.:\s]+([^\n]+)#ix");
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#KrisFlyer Membership Number\s*:\s*([A-Z\d-]+)#ix");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*([A-Z\d]{2}\s*\d+\s+\d+\s+\w+\s+\d{4}\s+.*?\s+to\s+[^\n]+)#i");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#^([A-Z\d]{2})\s*(\d+)\s+(\d+\s+\w+\s+\d{4})\s+(.*?)\s+to\s+([^\n]+)\s+(\d+)(\d{2})\s+(\d+)(\d{2})(\+\d+)?\s+(.*?)\s+Confirmed#"),
                                'FlightNumber' => re(2),
                                'DepName'      => re(4),
                                'ArrName'      => re(5),
                                'DepDate'      => totime(re(3) . ',' . re(6) . ':' . re(7)),
                                'ArrDate'      => re(10) ? strtotime('+' . re(10) . ' day', totime(re(3) . ',' . re(8) . ':' . re(9))) : totime(re(3) . ',' . re(8) . ':' . re(9)),
                                'Cabin'        => re(11),
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
