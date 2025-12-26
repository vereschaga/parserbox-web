<?php

namespace AwardWallet\Engine\travelocity\Email;

class It14 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s]*From\s*:\s*Travelocity Hotel#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Travelocity Hotel#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@travelocity\.#i";
    public $reProvider = "#[@.]travelocity\.#i";
    public $xPath = "";
    public $mailFiles = "travelocity/it-14.eml, travelocity/it-1852243.eml";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#reservation number:\s*(\d+)#"),
                            re("#Itinerary number\s*:\s*([A-Z\d\-]+)#")
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node("(//h2)[1]");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        re("#(\w{3})/(\d+)/(\d+)\s*\-\s*\w{3}+\s*(\w{3})/(\d+)/(\d+)#");

                        return [
                            'CheckInDate'  => strtotime(implode('-', [re(2), re(1), re(3)])),
                            'CheckOutDate' => strtotime(implode('-', [re(5), re(4), re(6)])),
                        ];
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return node("(//h2)[1]/following-sibling::*[1]");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\s*Reserved for\s+([^\n]+)#"));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s*adult#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Room')]/ancestor-or-self::td[1]/following-sibling::td[last()]//*[self::strong or self::b]");
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Room')]/ancestor-or-self::td[1]/following-sibling::td[last()]//*[not(self::strong or self::b)]");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#room has been\s*(\w+)#");
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#been\s*cancelled#") ? true : false;
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
