<?php

namespace AwardWallet\Engine\kayak\Email;

class It1570717 extends \TAccountCheckerExtended
{
    public $reFrom = "#@kayak.com#i";
    public $reProvider = "#[.@]kayak.com#i";
    public $rePlain = "#\n[>\s]*From\s*:[^\n]*?kayak#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Your KAYAK booking#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "kayak/it-1570717.eml";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        return re("#\n\s*Confirmation number\s*:\s*([\w\d\-]+)#i");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        re("#\d+\s\d+\n\s*([^\n]+)\s+([^\n]+)\s+(.[\d.]+)\s*(?:\-|—|&mdash;)*\s*\w+\s+(\w+\s*\d+\s+\d+)\s*\-\s*\w+\s+(\w+\s*\d+\s+\d+)#msi");

                        return [
                            'HotelName'    => trim(re(1)),
                            'Address'      => trim(re(2)),
                            'CheckInDate'  => strtotime(re(4)),
                            'CheckOutDate' => strtotime(re(5)),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#Phone\s+\s+Email\s+([^\n]*?)\s+\d{5,}#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s*adult#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s*room#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Avg Nightly')]/ancestor::tr[1]/following-sibling::tr[1]/td[3]");
                    },

                    "RateType" => function ($text = '', $node = null, $it = null) {
                        $info = explode(",", re("#\n\s*Room\s+([^\n]+)#"), 2);

                        return [
                            'RateType' => trim(reset($info)),
                            'RoomType' => trim(end($info)),
                        ];
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(re("#\n\s*Cancellation\s+(.*?)\s+Terms & Conditions#ms")));
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return glue([
                            re("#\n\s*(Bed\s*type:\s*[^\n]+)#"),
                            re("#\n\s*(Includes:\s*[^\n]+)#"),
                        ], '. ');
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//*[contains(text(), 'Avg Nightly')]/ancestor::tr[1]/following-sibling::tr[1]/td[4]"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Tax Recovery Charge & Service Fees\s+([^\n]+)#"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total Cost\s+([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Total Cost\s+([^\n]+)#"));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
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
