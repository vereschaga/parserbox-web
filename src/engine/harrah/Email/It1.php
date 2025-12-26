<?php

namespace AwardWallet\Engine\harrah\Email;

class It1 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*(?:From|Von)\s*:[^\n]*?(?:harrah|pkghlrss)#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]harrah#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]harrah#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "10.03.2015, 14:21";
    public $crDate = "09.03.2015, 06:06";
    public $xPath = "";
    public $mailFiles = "harrah/it-1.eml, harrah/it-2536026.eml";
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
                        return re("#\n\s*Confirmation Number\s*:\s*([A-Z\d-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//*[contains(text(), 'Hotel Information')]/ancestor-or-self::td[1]"));

                        return [
                            "HotelName" => detach("#^[^\n]+\s+([^\n]+)#", $info),
                            "Phone"     => detach("#\n\s*([\d\-A-Z\(\)+]+)\s*$#", $info),
                            "Address"   => nice($info),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check-In\s+Date\s*:\s*([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check-Out\s+Date\s*:\s*([^\n]+)#"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Guest Name\s*:\s*([^\n]+)#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of Adults\s*:\s*(\d+)#");
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of Children\s*:\s*(\d+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#CANCELLATION POLICY\s+(.*?)\s+DEPOSITS AND CREDIT CARDS#is");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#Room Selection & Preferences\s+([^\n]+)#");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re([
                            "#Total Trip Cost[*\s]+([^\n]+)#",
                            "#\n\s*(\d+[\d,.]+)\s*\*\s+Room\s+rates#i",
                        ]), 'Total');
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
