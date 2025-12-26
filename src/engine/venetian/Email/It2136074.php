<?php

namespace AwardWallet\Engine\venetian\Email;

class It2136074 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\sGrazie\s.+?Departure\s+Date\s+[^\n]+\s+Suite\s+Type#is', 'blank', '5000'],
        ['#Venetian#is', 'blank', '5000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#Your\s+[^\n]+?\s+Reservation\s+Confirmation#', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]venetian#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]venetian#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "27.06.2016, 15:20";
    public $crDate = "25.08.2015, 09:40";
    public $xPath = "";
    public $mailFiles = "venetian/it-2136074.eml, venetian/it-3003299.eml, venetian/it-3967182.eml";
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
                        return re("#\n\s*Confirmation\s+Number\s*:?\s*([\w-]+)#i");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = nice(re("#Thank you for choosing to stay at\s+(.+?)\s*\.#si"));

                        if (empty($name)) {
                            $name = nice(re("#Thank you for choosing\s+(.+?)\s*\.#si"));
                        }

                        return $name;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = totime(re("#Arrival\s+Date\s+\w+,\s*(\w+\s+\d+)\w*,\s*(\d+)#i") . " " . re(2) . ", " . re("#Check-in\s+Time:?\s+(\d+:\d+\s*\w*)#i"));

                        if (empty($date)) {
                            $date = totime(re("#Check\s+in\s+\w+,\s*(\w+\s+\d+)\w*,\s*(\d+)#i") . " " . re(2));
                        }

                        return $date;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = totime(re("#Departure\s+Date:?\s+\w+,\s*(\w+\s+\d+)\w*,\s*(\d+)#i") . " " . re(2) . ", " . re("#Check-?out\s+Time:?\s+(\d+:\d+\s*\w*)#i"));

                        if (empty($date)) {
                            $date = totime(re("#Check\s+out\s+\w+,\s*(\w+\s+\d+)\w*,\s*(\d+)#i") . " " . re(2));
                        }

                        return $date;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return $it['HotelName'];
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#contact (?:us|Resort\s+Services) at:?\s+([\d\(\+][(\d) \-\.]+)#i");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Guests\s+(\d+)#i");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        if (re("#\n\s*Dear\s+([^\n]+?),#")) {
                            return [re(1)];
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*Cancellation\s+Policy\s+([^\n]+)#i"));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Suite\s+Type:?\s+([^\n]+)#i");
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*(?:Total\s+Room\s+Tax|Tax\s+total)\s+([^\n]+)#i"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Grand\s+Total:?\s+([^\n]+)#i"), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re("#\n\s*Thank you for choosing to stay at#")) {
                            return "confirmed";
                        }
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
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
