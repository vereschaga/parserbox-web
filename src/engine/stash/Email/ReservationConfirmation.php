<?php

namespace AwardWallet\Engine\stash\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?stash#i', 'us', ''],
    ];
    public $reHtml = [
        ['#STASH-logo#i', 'blank', '/1'],
    ];
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]stash#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]stash#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "28.04.2015, 12:46";
    public $crDate = "28.04.2015, 12:02";
    public $xPath = "";
    public $mailFiles = "stash/it-2668148.eml, stash/it-2668149.eml";
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
                        return cell('Reservation Number:', +1);
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re('#Thank\s+you\s+for\s+making\s+a\s+reservation\s+with\s+(.*?)\.#');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $d = cell('Arrival Date:', +1);
                        $t = re('#Check-In\s*.*\s*(\d+:\d+[ap]m)\s*/#i');

                        if ($d and $t) {
                            return strtotime($d . ', ' . $t);
                        }
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $d = cell('Departure Date:', +1);
                        $t = re('#Check-Out\s*.*/\s*(\d+:\d+[ap]m)#i');

                        if ($d and $t) {
                            return strtotime($d . ', ' . $t);
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return node('//center/table[2]//tr[not(.//tr)][1]');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return cell('Guest Name:', +1);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell('Number of Rooms:', +1);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return cell('Room Rate*:', +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Cancellation\s+Policy\s*-\s*(.*)#');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return cell('Room Type:', +1);
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
