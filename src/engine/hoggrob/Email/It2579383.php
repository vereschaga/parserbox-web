<?php

namespace AwardWallet\Engine\hoggrob\Email;

class It2579383 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?hoggrob#i', 'blank', ''],
        ['#Hogg Robinson Group plc#i', 'blank', '-2000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]hoggrob#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]hoggrob#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "22.03.2015, 10:48";
    public $crDate = "22.03.2015, 10:33";
    public $xPath = "";
    public $mailFiles = "hoggrob/it-2579383.eml, hoggrob/it-2579384.eml";
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
                        return re("#\n[>\s]*Amadeus-Code\s*:\s*([A-Z\d-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n[>\s]*Reiseplan\s+für\s*:\s*([^\n]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Status:\s*([^\s]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n[\s>]*Datum[:\s]*([^\s]+)#") . ',' . re("#\s+Zeit:\s*([^\s]+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\n[\s>]*Flug\s+Datum\s+von)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $anchor = strtotime($this->parser->getHeader('date'));

                            return [
                                'AirlineName'  => re("#\n[>\s]*([A-Z\d]{2})\s*(\d+)\s+(\d+[A-Z]{3})\s+(.*?)\s{2,}(.*?)\s+(\d+:\d+)\s+(\d+:\d+)#"),
                                'FlightNumber' => re(2),
                                'DepName'      => re(4),
                                'ArrName'      => re(5),
                                'DepDate'      => correctDate(re(3) . ',' . re(6), $anchor),
                                'ArrDate'      => correctDate(re(3) . ',' . re(7), $anchor),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#Flugzeugtyp:\s*([^\n]+)#");
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re("#Buchungsklasse:\s*([A-Z])#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#Flugdauer:\s*([^\n]+)#");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#Stops:\s*(\d+)#");
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return re("#Airline Code:\s*([A-Z\d-]+)#");
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
