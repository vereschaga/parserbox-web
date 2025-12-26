<?php

namespace AwardWallet\Engine\klm\Email;

class It1746573 extends \TAccountCheckerExtended
{
    public $reFrom = "#[^\w\d]klm[^\w\d]#i";
    public $reProvider = "#[^\w\d]klm[^\w\d]#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[^\w\d]klm[^\w\d]#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "klm/it-1746573.eml";
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
                        return re("#\n\s*Booking code\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#\n\s*Flight number\s*:\s*([^\n]+)#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#(?:^|\()([A-Z]{3})(?:\)|$)#", re("#\n\s*Origin\s*:\s*([^\n]+)#"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(re("#\n\s*Departure date\s*:\s*([^\n]+)#") . ',' . re("#\n\s*Departure time\s*:\s*([^\n]+)#"));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#(?:^|\()([A-Z]{3})(?:\)|$)#", re("#\n\s*Destination\s*:\s*([^\n]+)#"));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Cabin\s*:\s*([^\n]+)#");
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
