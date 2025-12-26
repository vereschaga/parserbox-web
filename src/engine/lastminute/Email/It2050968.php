<?php

namespace AwardWallet\Engine\lastminute\Email;

class It2050968 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?@lastminute[.]com#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#@lastminute[.]com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]lastminute[.]com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "23.03.2015, 19:28";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "lastminute/it-2050968.eml, lastminute/it-2572699.eml";
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
                        return re_white("Hotel's reference: (\w+)");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("(//a[contains(text(), 'View Map')]/ancestor-or-self::td[1]/strong[1])[1]");
                        $name = orval(
                            re_white('(.+?) -', $name),
                            $name
                        );

                        return nice($name);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $dt = node("//*[contains(text(), 'Check in:')]/following::td[1]");

                        return strtotime($dt);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $dt = node("//*[contains(text(), 'Check out:')]/following::td[1]");

                        return strtotime($dt);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = nodes("//a[contains(text(), 'View Map')]/ancestor-or-self::td[1]/text()[position() <= 4]");
                        $addr = implode(',', $addr);
                        $addr = nice($addr);

                        return $addr;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Tel: ([\d\s]+)');

                        return nice($x);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $q = white('Room \d+: (.+?) .\d+[.]\d+');

                        if (preg_match_all("/$q/isu", $text, $ms)) {
                            return $ms[1];
                        }
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white('Adults (\d+)');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re_white('Children (\d+)');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return sizeof(nodes("//*[contains(text(), '| Children')]"));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $pol = re_white('
							Hotel Cancellation Policy
							( .+? hotel check-in[.] )
						');

                        return nice($pol);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[contains(text(), '| Children')]");
                        $type = re_white('Children \d+ (.+)', $info);
                        $type = orval(
                            re_white('(.+?) with Free', $type),
                            $type
                        );

                        return [
                            'RoomType'            => re("#^(.*?)\.(.+)#s", nice($type)),
                            'RoomTypeDescription' => re("#\-\s*(.+)#s", re(2)),
                        ];
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Total charged (.\d+[.]\d+)');

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+is\s+(cancel+ed|confirmed)#i");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#Your hotel booking for the (.*?) is#xi"));
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
