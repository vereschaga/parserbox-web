<?php

namespace AwardWallet\Engine\worldhotels\Email;

class It2566588 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Visit\s+Worldhotels#i', 'blank', '-500'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]worldhotels#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]worldhotels#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "17.03.2015, 15:49";
    public $crDate = "17.03.2015, 15:38";
    public $xPath = "";
    public $mailFiles = "worldhotels/it-2566588.eml";
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
                        return re("#\n\s*Reservation Number\s*:\s*([A-Z\d-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("(//*[contains(text(), 'Welcome to')]/ancestor::table[1]/following::table[1]//tr[1])[1]"));

                        return [
                            'HotelName' => re("#^(.*?)\s{2,}(.*?)\s{2,}([+\d\(\) \-]{5,})\s{2,}#is", $info),
                            'Address'   => nice(re(2)),
                            'Phone'     => re(3),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell("Arrival Date:", +1, 0) . ',' . uberTime(cell('Check-in Time:', +1)));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell("Departure Date:", +1, 0) . ',' . uberTime(cell('Check-out Time:', +1)));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return cell("Guest Name:", +1, 0);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s+Adult#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return cell("Rate per night:", +1, 0) . '/per night';
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return cell("Cancellation policy", +1, 0);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room Description\s+([^\n/]+)#");
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room Description\s+[^\n/]+/\s*(.+)#");
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Subtotal:", +1, 0));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $total = 0;
                        re("#\s+Tax:\s*([^\n]+)#", function ($m) use (&$total) {
                            $total += cost($m[1]);
                        }, $text);

                        return $total;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(cell("Total (incl Tax):", +1, 0), 'Total');
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
