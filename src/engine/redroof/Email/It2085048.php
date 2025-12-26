<?php

namespace AwardWallet\Engine\redroof\Email;

class It2085048 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Red\s+Roof\s+reservation\s+is\s+confirmed#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#Confirmation\s+Report#i', 'blank', ''],
    ];
    public $reFrom = [
        ['#redroof#i', 'us', ''],
    ];
    public $reProvider = [
        ['#redroof#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "30.06.2015, 11:30";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "redroof/it-2085048.eml, redroof/it-2839485.eml";
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
                        return re("#Confirmation No.\s*([A-Z\d-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#Reservation\s+Details\s+([^\n]+?)(?>\s+\(\s*more\s+info[^\n]+)?\s*\n#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDatetime(cell("Check-in", +1) . " 00:00"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $day = uberDatetime(cell("Check-out", +1));
                        $time = uberTime(re("#Check Out Time:\s*(\d+:\d+)#"));

                        return totime(uberDatetime($day . " " . $time));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $address = re("#Reservation\s+Details\s+[^\n]+?information\s*\)\s+([^\n]+?)(?:\s*\|\s*([0-9\-\(\)]+))?\s*?\n#");
                        $address = preg_replace("#\s*\|\s*#", ", ", $address);
                        $phone = re(2);

                        return [
                            "Address" => $address,
                            "Phone"   => $phone,
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [beautifulName(re("#Guest\s+Details\s+.+?\n\s*Address\s*:\s+(.+?)\s*?\n#s"))];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return intval(cell("# of Adults", +1));
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return intval(cell("# of Children", +1));
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return intval(cell("# of Rooms", +1));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#Cancellation\s+Policy\s+([^\n]+?)\s*?\n#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Room:", +1);

                        return $node;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#total of\s*([^\n]+)#"), "Total");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Your\s+Red\s+Roof\s+reservation\s+is\s+([^\n]+)\.#i");
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
