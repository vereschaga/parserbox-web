<?php

namespace AwardWallet\Engine\woodfield\Email;

class It1938538 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#^\s*La\s+Quinta\s+Inn\s.+?Number\s+of\s+Rooms\s*:#msi', 'blank', '5000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Booking cancellation', 'blank', ''],
        ['La Quinta Hotel Reservation for', 'blank', ''],
    ];
    public $reFrom = [
        ['#laquinta#i', 'us', ''],
    ];
    public $reProvider = [
        ['#laquinta#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "8276";
    public $upDate = "24.08.2015, 10:15";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "woodfield/it-1938538.eml, woodfield/it-1939081.eml, woodfield/it-2254775.eml, woodfield/it-3009750.eml";
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
                        return orval(
                            re("#\n\s*Confirmation Number(?:\(s\))?\s*:\s*([\d\-A-Z]+)#ix"),
                            re("#\n\s*Your confirmation \#(?:\(s\))?\s*:\s*([\d\-A-Z]+)#ix")
                        );
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $text = orval(
                            text(xpath("//a[contains(., 'La Quinta')]/ancestor::td[1]")),
                            re("#\n\s*(La Quinta(?>[^\n]+\s+){2,5}?)Guest\s+Name\s*:#si")
                        );

                        return [
                            'HotelName' => re("#^(La Quinta Inn[^\n]+)\s+(.*?)\n\s*([\(\)\d \-+]{4,})\s*\n#is", $text),
                            'Address'   => nice(re(2)),
                            'Phone'     => trim(re(3)),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Arrival Date\s*:\s*([^\n]+)#") . ',' . re("#Check-In Time\s*:\s*([^\n]+)#i"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Departure Date\s*:\s*([^\n]+)#") . ',' . re("#Check-Out Time\s*:\s*([^\n]+)#i"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        if (re("#Guest\s+Name\s*:\s*([^\n:]+)#i")) {
                            return [re(1)];
                        }
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of (?:Adults|Guests)\s*:\s*(\d+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of Rooms\s*:\s*(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#Rate\s*:\s*([^\n]+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Cancel Policy\s*:\s*([^\n]+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room Type\s*:\s*([^\n]+)#");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total Amount\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            currency(re(1)),
                            currency(re("#Rate\s*:\s*([^\n]+)#"))
                        );
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re("#\n\s*This cancellation notice has been sent by electronic mail\.#")) {
                            return "cancelled";
                        }
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        if (re("#\n\s*This cancellation notice has been sent by electronic mail\.#")) {
                            return true;
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
