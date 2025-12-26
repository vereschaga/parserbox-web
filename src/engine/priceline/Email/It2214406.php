<?php

namespace AwardWallet\Engine\priceline\Email;

class It2214406 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?priceline#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#priceline#i";
    public $reProvider = "#priceline#i";
    public $caseReference = "6701";
    public $isAggregator = "0";
    public $fnDateFormat = "";
    public $xPath = "";
    public $mailFiles = "priceline/it-2214406.eml";
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
                        return re("#\n\s*Record Locator\s*:\s*([A-Z\d-]+)#ix");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Passenger Name(s)')]/ancestor::tr[1]/following-sibling::tr[contains(., 'Tkt')][1]");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Total\s*:\s*([A-Z]{3}\s+[^\n]+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        re("#Routing\s+Date\s+Flight\s+Class\s+Status\s+(.+?)\s+Unit\s+Price#is");
                        $flights = re(1);

                        return splitter("#(.*?\s+\d+\-[A-Z]{3}\-\d+\s+[A-Z\d]{2}\s*\d+\s+[A-Z]\s+Confirmed)#is", $flights);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            re("#^(.*?)\s+(\d+\-[A-Z]{3}\-\d+)\s+([A-Z\d]{2})\s*(\d+)\s+([A-Z])\s+Confirmed#is");
                            [$dep, $arr] = explode('/', re(1));

                            return [
                                'DepName'      => nice($dep),
                                'ArrName'      => nice($arr),
                                'DepDate'      => totime(re(2)),
                                'ArrDate'      => MISSING_DATE,
                                'AirlineName'  => re(3),
                                'FlightNumber' => re(4),
                                'BookingClass' => re(5),
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
