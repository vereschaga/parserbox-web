<?php

namespace AwardWallet\Engine\kayak\Email;

class It1540734 extends \TAccountCheckerExtended
{
    public $reFrom = "#@kayak.com#i";
    public $reProvider = "#\[@\.\]kayak.com#i";
    public $rePlain = "#\n[>\s]*From\s*:[^\n]*?@kayak.com#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#KAYAK booking#i";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "kayak/it-1540734.eml";

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
                        return re("#\s*confirmation number\s*:\s*([\d\w\-]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*Booking Created\s*:\s*([^\n]+)#"));
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $td = glue(filter(explode("\n", cell('See details', 0, 0, "//text()"))), "\n");
                        re("#^([^\n]+)\s+(.*?)\s+([+\t \d\-\(\)]{4,})\s+See\s*details#ms", $td);

                        return [
                            "HotelName" => re(1),
                            "Address"   => nice(re(2)),
                            "Phone"     => re(3),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*Check\-in:\s*([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*Check\-out:\s*([^\n]+)#"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total Charged\s+([^\n]+)#"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Tax Recovery Charge & Service Fees\s+([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Total Charged\s+([^\n]+)#"));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\s*(\d+)\s+adult#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\s*(\d+)\s+room#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#(.[.\d]+\s+avg\.*\s*per\s*night)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Cancellation')]/ancestor-or-self::div[1]/div[1]");
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return trim(clear("#&\#\d+;#", re("#avg\.*\s*per\s*night\)*\s+(.*?)\s+.[\d.]{2,}#ms")));
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
}
