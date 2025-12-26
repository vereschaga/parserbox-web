<?php

namespace AwardWallet\Engine\swissotel\Email;

class SwissotelHotelsReservationDetails extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?swissotel#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]swissotel#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]swissotel#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "08.04.2015, 21:27";
    public $crDate = "08.04.2015, 21:09";
    public $xPath = "";
    public $mailFiles = "swissotel/it-2596666.eml";
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
                        return re('#Your\s+reservation\s+number\s+is\s*:\s+(\d+)#i');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#(.*)\s+(.*\s+.*)\s+Tel:\s*\+#i', $text, $m)) {
                            return [
                                'HotelName' => nice($m[1]),
                                'Address'   => nice($m[2]),
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#ARRIVING\s+on\s+(\d+-\w+-\d+)#i'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#DEPARTING\s+on\s+(\d+-\w+-\d+)#i'));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re('#Tel:\s*(.*)#');
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re('#Fax:\s*(.*)#');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re('#DEAR\s+(.*?)\s+Thank\s+you\s+for\s+booking#i');
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#GUESTS\s*:\s+(\d+)\s+Adult,\s+(\d+)\s+Children#i', $text, $m)) {
                            return [
                                'Guests' => $m[1],
                                'Kids'   => $m[2],
                            ];
                        }
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re('#ROOM\s+RATE\s*:\s+(.*)#i');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#CANCEL\s+POLICY\s*:\s+(.*)#i');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#ROOM\s+TYPE\s*:\s+(.*)#i');
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
