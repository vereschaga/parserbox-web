<?php

namespace AwardWallet\Engine\hoggrob\Email;

class It2585430 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n\s*HRG\s+\w+#', 'blank', ''],
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
    public $upDate = "29.03.2015, 01:23";
    public $crDate = "29.03.2015, 01:12";
    public $xPath = "";
    public $mailFiles = "";
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
                        return re("#\n\s*Amadeus-Code\s*:\s*([A-Z\d-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Itinerary for\s*:\s*([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*AIR FARE\s*:\s*([A-Z]+\s+[\d.,]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Date\s*:\s*([^\n]+)#")));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*Flight\s+Date\s+from\s+to\s+Dep[.\s]+Arrival\s+([A-Z\d]{2}\s*\d+)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $anchor = uberDateTime(re("#\n\s*Date\s*:\s*([^\n]+)#"));

                            return [
                                'AirlineName'  => re("#^([A-Z\d]{2})\s*(\d+)\s+(\d+[A-Z]+)\s+(.*?)\s{2,}(.*?)\s+(\d+:\d+\s*[APM]*)\s+(\d+:\d+\s*[APM]*)#"),
                                'FlightNumber' => re(2),
                                'DepDate'      => correctDate(re(3) . ',' . re(6), $anchor),
                                'ArrDate'      => correctDate(re(3) . ',' . re(7), $anchor),
                                'DepName'      => re(4),
                                'ArrName'      => re(5),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#Aircraft type\s*:\s*([^\n]+)#");
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Booking Class\s*:\s*([A-Z])#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*seat\s*:\s*([A-Z\d]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Duration\s*:\s*([^\n]+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return clear("#[:;]#", re("#\n\s*Meals\s*:\s*([^\n]+)#"), ',');
                        },

                        "Smoking" => function ($text = '', $node = null, $it = null) {
                            return re("#Non\s*smoke#i") ? false : null;
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Stops\s*:\s*(\d+)#");
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Airline Code\s*:\s*([A-Z\d-]+)#");
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
