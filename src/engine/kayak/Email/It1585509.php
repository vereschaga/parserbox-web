<?php

namespace AwardWallet\Engine\kayak\Email;

class It1585509 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s]*From\s*:[^\n]*?kayak#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@kayak.com#i";
    public $reProvider = "#[.@]kayak.com#i";
    public $caseReference = "7293";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "kayak/it-1585509.eml, kayak/it-2066920.eml";
    public $pdfRequired = "";

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
                        return re("#\n\s*Your confirmation number\s*:\s*([\d\w\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return [
                            'HotelName' => re("#\n\s*Reservation Details\s+([^\n]+)\s+(.*?)\s+Check\-in#ims"),
                            'Address'   => nice(glue(clear("#\|\s*Map\s*#", re(2)))),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*Check\-in[:\s]+([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*Check\-out[:\s]+([^\n]+)#"));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Phone\s*([^\n]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Guest\s*\n\s*([^\n]+)#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of Guests\s*(\d+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of Rooms\s*(\d+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return reset(explode(',', re("#\n\s*Room Type\s*([^\n]+)#")));
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(array_slice(explode(',', re("#\n\s*Room Type\s*([^\n]+)#")), 1)));
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\(1 night\)\s*([^\n]*?)\s+Tax Recovery Charge and Service Fees#"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Tax Recovery Charge and Service Fees\s*([^\n]+)#"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total Cost\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Total Cost\s*([^\n]+)#"));
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
