<?php

namespace AwardWallet\Engine\gha\Email;

class It2211717 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#making\s+your\s+reservation\s+with\s+the\s+Global\s+Hotel\s+Alliance#i', 'blank', '-1000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#@gha\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#@gha\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "18.06.2015, 16:40";
    public $crDate = "10.06.2015, 17:07";
    public $xPath = "";
    public $mailFiles = "gha/it-2211717.eml";
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
                        return node("//div[./span[contains(text(), 'Confirmation Number')]]/span[2]/text()");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node("//td[./span[1][contains(text(), 'Confirmed at:')]]/p[1]/text()");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return timestamp_from_format(re("/Arrival\s+Date\s*\:\s+(\d+(\s+\/\s+\d+){2})\s/ui") . "T00:00", "d / m / Y\\TH:i");
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return timestamp_from_format(re("/Departure\s+Date\s*\:\s+(\d+(\s+\/\s+\d+){2})\s/ui") . "T00:00", "d / m / Y\\TH:i");
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return node("//td[./span[1][contains(text(), 'Confirmed at:')]]/span[2]/text()");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return node("//td[./span[1][contains(text(), 'Confirmed at:')]]/span[3]/a/text()");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re("/Room\s+\d+\s*:\s*?\n?[^\n]+\s+Guest\s+Information\s+([^\n]+)\s*\n/ui")];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return [
                            'Guests' => intval(re("/Number\s+of\s+Room.+?\s+Room\s+\d+\s*:\s*Adult[\w\(\)]*\s*:\s*(\d+)\s+\/\s*Children\s*:\s*(\d+)\s/uis")),
                            'Kids'   => intval(re(2)),
                        ];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("/Number\s+of\s+Room\s*\:\s+(\d+)\s/ui");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return node("//tr[./td[1]/*[normalize-space(text()) = '1 Night']]/td[3]/*/text()");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("/\sCancellations\s*:\s+(.+?)\s*\n/ui");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return [
                            'RoomType'            => re("/Billing\s+Information.+?\s+ROOM\s+\d+\s*:\s*(.+?)\s+\-\s+(.+?)\s+Room\s+Information/uis"),
                            'RoomTypeDescription' => re(2),
                        ];
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//tr[./td[1]/*[normalize-space(text()) = 'Subtotal:']]/td[2]/*/text()"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//tr[./td[1]/*[normalize-space(text()) = 'Tax and Fees:']]/td[2]/*/text()"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $totalStr = node("//tr[./td[1]/*[normalize-space(text()) = 'Total:']]/td[2]/*/text()");

                        return [
                            'Total'    => cost(re("/\s(\d+(\.\d+)?)\s*$/ui", $totalStr)),
                            'Currency' => re("/^\s*(\w+)\s+\d+/ui", $totalStr),
                        ];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re("/Your\s+Reservation\s+is\s+Confirmed/ui")) {
                            return "Confirmed";
                        }
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
