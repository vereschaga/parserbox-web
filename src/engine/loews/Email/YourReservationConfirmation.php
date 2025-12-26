<?php

namespace AwardWallet\Engine\loews\Email;

class YourReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?loews#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#loews#i";
    public $reProvider = "#loews#i";
    public $xPath = "";
    public $mailFiles = "loews/it-1930912.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Reservation\s+Number\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $res['HotelName'] = re('#is\s+waiting\s+for\s+you\s+at\s+(.*?)\.#i');
                        $res['Address'] = $res['HotelName'];

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(cell('Arrival Date', +1) . ', ' . cell('Check-in time', +1));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(cell('Departure Date', +1) . ', ' . cell('Check-out time', +1));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [cell('Guest Name', +1)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell('Number of Guests', +1);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return cell('Room Rate Per Night', +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return cell('Cancellation', +1);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return cell('Room Type', +1);
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
