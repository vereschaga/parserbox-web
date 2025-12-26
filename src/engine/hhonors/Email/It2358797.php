<?php

namespace AwardWallet\Engine\hhonors\Email;

class It2358797 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\d{4} Hilton Hospitality#i', 'us', '-100'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#hhonors#i', 'us', ''],
    ];
    public $reProvider = [
        ['#hhonors#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "14.01.2015, 13:37";
    public $crDate = "14.01.2015, 13:04";
    public $xPath = "";
    public $mailFiles = "hhonors/it-2358797.eml";
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
                        return re("#\n\s*Confirmation Number\s*:\s*([A-Z\d\-]+)#ix");
                    },

                    "ConfirmationNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#HHonors Number:\s*([A-Z\d\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("(//*[contains(text(), 'Check-In')]/ancestor::table[2])[1]"));

                        return [
                            "HotelName" => re("#^([^\n]+)\s+(.*?)\s+Phone\s*:\s*([\d\-\(\)+. ]{4,})\s+Fax\s*:\s*([\d\-\(\)+. ]{4,})#is", $info),
                            "Address"   => nice(re(2)),
                            "Phone"     => trim(re(3)),
                            "Fax"       => trim(re(4)),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check\-In date\s*:\s*([^\n]+)#ix") . ',' . re("#\n\s*Check\-In time\s*:\s*([^\n]+)#ix"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check\-Out date\s*:\s*([^\n]+)#ix") . ',' . re("#\n\s*Check\-Out time\s*:\s*([^\n]+)#ix"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#Reservation Confirmation for (.+)#ix");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+(\d+)\s+Adult#i");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(\d+) Rooms#ix");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'need to cancel')]/ancestor-or-self::td[1]");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room\s+Type\s*:\s*([^\n]+)#");
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Total Rate for Stay\s*\(in\s+points\)\s*:\s*([^\n]+)#");
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
