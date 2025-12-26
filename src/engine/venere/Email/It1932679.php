<?php

namespace AwardWallet\Engine\venere\Email;

class It1932679 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?venere#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#venere#i";
    public $reProvider = "#venere#i";
    public $xPath = "";
    public $mailFiles = "venere/it-1932679.eml";
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
                        return re("#\sreservation number\s+([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#([^\n]+)\s+Attn:#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check in\s*:\s*([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check out\s*:\s*([^\n]+)#"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*Name\s*:\s*([^\n]+)#")];
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return [
                            'RoomType' => re("#\n\s*Rooms requested\s*:\s*([^\n]*?)\s+([.\d,]+\s*[A-Z]{3})#"),
                            'Cost'     => cost(re(2)),
                        ];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#number\s+[A-Z\d\-]+\s+has\s+been\s+(\w+)#");
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#has\s+been\s+cancelled#i") ? true : false;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Reservation was made on\s*:\s*([^\n]+)#")));
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
