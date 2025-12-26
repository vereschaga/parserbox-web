<?php

namespace AwardWallet\Engine\spg\Email;

class It2846646 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['SPG Program#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]starwoodhotels#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]starwoodhotels#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "26.06.2015, 13:41";
    public $crDate = "26.06.2015, 13:15";
    public $xPath = "";
    public $mailFiles = "spg/it-2846646.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("//*[contains(text(), 'YOUR VACATION PACKAGE:')]/ancestor::table[2]");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('Resort confirmation : (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node(".//*[contains(text(), 'YOUR VACATION PACKAGE:')]/following::strong[1]");

                        return nice($name);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = cell('Arrival day:', +1);
                        $time = cell('Check-in time:', +1);

                        $dt = strtotime($date);
                        $dt = strtotime($time, $dt);

                        return $dt;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = cell('Departure day:', +1);
                        $time = cell('Check-out time:', +1);

                        $dt = strtotime($date);
                        $dt = strtotime($time, $dt);

                        return $dt;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = node(".//*[contains(text(), 'YOUR VACATION PACKAGE:')]/following::em[1]");

                        return nice($addr);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = cell('Guest name:', +1);

                        return [nice($name)];
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return nice(cell('Resort fees', +1));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return reni('(Cancellations .+?) Certificate');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return nice(cell('Accommodations', +1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('You are now confirmed')) {
                            return 'confirmed';
                        }
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
