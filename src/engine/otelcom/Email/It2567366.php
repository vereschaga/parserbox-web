<?php

namespace AwardWallet\Engine\otelcom\Email;

class It2567366 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?otel\.com#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]otelcom#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]otelcom#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "19.03.2015, 15:31";
    public $crDate = "17.03.2015, 13:27";
    public $xPath = "";
    public $mailFiles = "otelcom/it-2086673.eml, otelcom/it-2567366.eml, otelcom/it-2567367.eml";
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
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*TRACKING\s+NUMBER\s*:\s*([A-Z\d\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*HOTEL\s*:\s*([^\n]+)#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*CHECK-IN DATE\s*:\s*([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*CHECK-OUT DATE\s*:\s*([^\n]+)#"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*DESTINATION\s*:\s*([^\n]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re([
                            "#\n\s*Dear\s+(.*?)\s+Thank\s+you#i",
                            "#\n\s*LEAD NAME\s*:\s*([^\n]+)#",
                        ]);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*ROOM TYPE\s*:\s*([^\n]+)#");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*(?:GRAND TOTAL PRICE|Total Price)\s*:\s*([^\n]+)#i"), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re([
                            "#\n\s*Your booking is (\w+)#ix",
                            "#\n\s*RESERVATION STATUS\s*:\s*([^\n]+)#",
                        ]);
                    },
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
