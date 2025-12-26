<?php

namespace AwardWallet\Engine\orbitz\Email;

class It1568818 extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-1540781.eml, orbitz/it-1568818.eml";

    public $reFrom = "#orbitz#i";
    public $reProvider = "#orbitz#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?orbitz#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
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
                        return re("#\n\s*Hotel Confirmation Number\s+([\w\d\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $r = glue(filter(nodes("//text()[contains(., 'Phone number')]/ancestor-or-self::td[1]//text()")), "\n");
                        re("#^([^\n]+)\s+(.*?)\s+Phone\s+number:\s*([^\n]+)#ms", $r);

                        return [
                            'HotelName' => re(1),
                            'Address'   => nice(re(2)),
                            'Phone'     => re(3),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(cell("Check-In Date", +1, 0));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(cell("Check-Out Date", +1, 0));
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Total Number of Rooms\s*([^\n]+)#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Total Number of Guests\s*([^\n]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room Reservation Name:\s*([^\n]+)#");
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        $r = ['RoomTypeDescription' => [], 'RoomType' => []];
                        re("#\n\s*Room Description\s*([^\-:]+)\s*[:\-]\s*([^\n]+)#", function ($m) use (&$r) {
                            $r['RoomTypeDescription'][] = $m[1];
                            $r['RoomType'][] = $m[2];
                        }, $text);

                        $r['RoomTypeDescription'] = implode('|', $r['RoomTypeDescription']);
                        $r['RoomType'] = implode('|', $r['RoomType']);

                        return $r;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Amount Charged to Your Card\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Amount Charged to Your Card\s*([^\n]+)#"));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $r = filter(nodes("//text()[contains(., 'Cancellation Policy')]/ancestor-or-self::td[1]//text()"));

                        return nice(glue(array_slice($r, 1), "\n"));
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
