<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

class It1542181 extends \TAccountCheckerExtended
{
    public $rePlain = "#@holidayinnclub\.com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "";
    public $reProvider = "#@holidayinnclub\.com#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "ichotelsgroup/it-1542181.eml, ichotelsgroup/it-2214807.eml";
    public $pdfRequired = "";

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
                        return re('#Reservation Number:\s*([^\s]*)#ims');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Reservation Number:\s*[^\s]*\s*At\s*(.*?)\n#ims'),
                            re('#Where\s+you\s+are\s+staying\s*:\s+(.*)#i')
                        );
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-In:\s*(.*?)\s*Check#ims') . ' ' . re("#Arrival Date:.*?,\s*(.*?)\n#ims"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-Out:\s*(.*?)\n#ims') . ' ' . re("#Departure Date:.*?,\s*(.*?)\n#ims"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Reservation Number:\s*[^\s]*\s*At\s*.*?\n(.*?)\s*Resort#ims'),
                            re('#Where\s+you\s+are\s+staying\s*:\s+.*\s+(.*)#i')
                        );
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#Name:\s*(.*?)\n#ims");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+Accomm?odations:\s*(.*)#i');
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#VIP Guest Number:\s*(.*?)\n#");
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
