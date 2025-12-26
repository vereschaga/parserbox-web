<?php

namespace AwardWallet\Engine\woodfield\Email;

class LaQuintaHotelReservation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*? reservations@laquinta.com#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['La Quinta Hotel Reservation', 'blank', ''],
    ];
    public $reFrom = [
        ['#reservations@laquinta.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#laquinta.com#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "23.06.2016, 06:44";
    public $crDate = "23.06.2016, 06:25";
    public $xPath = "";
    public $mailFiles = "woodfield/it-3957517.eml";
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
                        return re('#Your Reservation Confirmation \#:\s+(\d+)#');
                    },

                    "ConfirmationNumbers" => function ($text = '', $node = null, $it = null) {
                        $s = re('#Your Reservation Confirmation \#:\s+([\d\s]+)#');

                        return array_filter(explode(' ', $s));
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node('//*[normalize-space(.) = "Your Reservation Confirmation #:"]/following-sibling::a');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $d = re('#Check-In Date:\s+(\d+/\d+/\d+)#');
                        $t = re('#Check-In Time:\s+(\d+:\d+)#');

                        return strtotime($d . ' ' . $t);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $d = re('#Check-Out Date:\s+(\d+/\d+/\d+)#');
                        $t = re('#Check-Out Time:\s+(\d+:\d+)#');

                        return strtotime($d . ' ' . $t);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $s = implode(', ', nodes('//*[normalize-space(.) = "Your Reservation Confirmation #:"]/following-sibling::a/following-sibling::text()[position() <= 2]'));

                        return $s;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return node('//*[normalize-space(.) = "Your Reservation Confirmation #:"]/following-sibling::a/following-sibling::text()[position() = 3]');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re('#Your Name:\s+(.*?)\s+Check-In#');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#Number of Rooms:\s+(\d+)#');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#IF YOU HAVE TO CANCEL\s+(.*?)\s+Cancel\s+this#');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Room Type:\s+(.*)\s+\[Nightly#');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Estimated Total w/Tax:\s+([\d.]+\s+\w{3})\s+#'), 'Total');
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
