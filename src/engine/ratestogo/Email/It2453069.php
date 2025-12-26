<?php

namespace AwardWallet\Engine\ratestogo\Email;

class It2453069 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]ratestogo[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]ratestogo[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]ratestogo[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "13.02.2015, 10:37";
    public $crDate = "13.02.2015, 10:18";
    public $xPath = "";
    public $mailFiles = "ratestogo/it-2453069.eml";
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
                        return reni('Hotel confirmation number: (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[normalize-space(text()) = 'Hotel']/following::td[2]"));
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('Check-in: (.+? \d{4})');

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('Check-out: (.+? \d{4})');

                        return totime($date);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[normalize-space(text()) = 'Hotel']/following::a[1]"));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return reni('Phone: ([\d-\s]+)');
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return reni('Fax: ([\d-\s]+)');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [reni('Hotel reservations under: (.+?) \n')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return reni('(\d+) guests');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return reni('(\d+) Room \(');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return reni('(. [\d.,]+) avg / night');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[normalize-space(text()) = 'Cancellation:']/following::ul[1]"));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return reni('Room description: (.+?) -');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Total trip cost  (.+?) \n');

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Hotel confirmation number')) {
                            return 'confirmed';
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('This reservation was made on (.+? \d{4})');

                        return totime($date);
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
