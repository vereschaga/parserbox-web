<?php

namespace AwardWallet\Engine\leadinghotels\Email;

class MarbellaClubHotel extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Your\s+reservation\s+is\s+confirmed\s+as\s+follows#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#LW\d+-#i', 'blank', ''],
    ];
    public $reFrom = [
        ['#lhw.in@xmr3.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]xmr3.com#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "28.05.2015, 08:28";
    public $crDate = "27.05.2015, 12:29";
    public $xPath = "";
    public $mailFiles = "leadinghotels/it-1670429.eml, leadinghotels/it-2758585.eml, leadinghotels/it-3961043.eml";
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
                        return re('#\d+#i', cell("Confirmation #:", +1));
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return cell("Hotel:", +1);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(str_replace('Check-in time is', '', cell("Arrival Date:", +1)));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(str_replace('Check-out time is', '', cell("Departure Date:", +1)));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $node = node("//p[contains(., 'Phone:') and contains(., 'Fax:')]");

                        return orval(
                            re("#^(.+)\s+Phone:#i", $node),
                            $it["HotelName"]
                        );
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $node = node("//p[contains(., 'Phone:') and contains(., 'Fax:')]");

                        return re("#Phone:(.+)\s+Fax#i", $node);
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        $node = node("//p[contains(., 'Phone:') and contains(., 'Fax:')]");

                        return re("#Fax:(.+)$#i", $node);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell("Number Adults:", +1);
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return cell("Number Children:", +1);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\/#i", cell('Number Rooms', +1));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'CANCELLATION POLICY:')]", null, true, "#^CANCELLATION\s+POLICY:\s+(.+)$#i");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#^([^\.|^\/]+)[\.|\/]+#i", cell("Accommodations", +2));
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return re("#^[^\.|^\/]+[\.|\/]+(.*)$#i", cell("Accommodations", +2));

                        return re("##");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(cell("Total Cost", +1), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Your\s+reservation\s+is\s+([^\s]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(cell('Booking Date', +1));
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
