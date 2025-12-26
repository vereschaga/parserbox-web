<?php

namespace AwardWallet\Engine\spg\Email;

class It1911152 extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#SheratonGreensboroAtFourSeasons@Starwoodhotels#i";
    public $reProvider = "#Starwoodhotels#i";
    public $xPath = "";
    public $mailFiles = "spg/it-1911152.eml";
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
                        return re("#\n\s*Reservation\s*\#\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#Sheraton Greensboro Hotel#i"));
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#For your arrival\s+(\d+\-\w+\-\d+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#and departure\s+(\d+\-\w+\-\d+)#"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return [
                            'Address' => re("#\n\s*(Sheraton Greensboro.*?)\s+([\d\-\(\) +]+)\s+for guests#"),
                            'Phone'   => re(2),
                        ];
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#nightly\s+rate\s+for\s+this\s+room\s+type\s+is\s*(.*?)\.\n#ims"));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\n[\s*]*(Failure to cancel reservations.*?)\.#ims");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+following accommodations\s*:\s*([^\n.]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#([^\n]+)\n\s*Reservation\s*\#\s*([A-Z\d\-]+)#"));
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
