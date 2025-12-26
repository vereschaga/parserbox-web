<?php

namespace AwardWallet\Engine\joyoflife\Email;

class It2218284 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.](?:jdvhotels|communehotels)[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]jdvhotels[.]com#i";
    public $reProvider = "#[@.]jdvhotels[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "joyoflife/it-1593581.eml, joyoflife/it-1780887.eml, joyoflife/it-2218284.eml, joyoflife/it-3.eml";
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
                        $conf = cell('Confirmation Number', +1);

                        return nice($conf);
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = nice(re_white('Thank you for choosing (.+?),'));

                        return [
                            'HotelName' => $name,
                            'Address'   => $name,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = cell('Arrival Date', +1);

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = cell('Departure Date', +1);

                        return totime($date);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = cell('Guest Name', +1);

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white('Number of Adults  (\d+)');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re_white('Number of Children  (\d+)');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re_white('Number of Rooms  (\d+)');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Cancellation Policy', +1);

                        return nice($x);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Room Type', +1);

                        return nice($x);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Local Taxes', +1);

                        return cost($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Total Charges', +1);

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('Reservation Confirmation')) {
                            return 'confirmed';
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
