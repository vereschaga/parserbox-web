<?php

namespace AwardWallet\Engine\mirage\Email;

class It3209786 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['MGM Resorts International#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]mgmresorts[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]mgmresorts[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "06.11.2015, 12:24";
    public $crDate = "06.11.2015, 12:12";
    public $xPath = "";
    public $mailFiles = "mirage/it-1.eml, mirage/it-3209786.eml";
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
                        return reni('Confirmation Number : (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return reni('Hotel : (.+?) \n');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('Arrival date : (.+? \d{4})');

                        return strtotime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('Departure date : (.+? \d{4})');

                        return strtotime($date);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[contains(text(), 'Privacy Policy')]/ancestor::tr[1]");

                        return reni('(.+?) Privacy Policy', $info);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = reni('Dear (.+?) ,');

                        return [$name];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return reni('Number of Guests : (\d+)');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return reni('Room Type : (.+?) \n');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Your reservation is confirmed')) {
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
