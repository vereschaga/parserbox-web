<?php

namespace AwardWallet\Engine\gha\Email;

class It3174107 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Total Rooms.+Global Hotel Alliance#is', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]gha[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]gha[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "02.11.2015, 15:33";
    public $crDate = "02.11.2015, 15:23";
    public $xPath = "";
    public $mailFiles = "gha/it-3174107.eml";
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
                        return reni('Confirmation Number  (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(text(), 'Confirmation Number')]/preceding::span[1]");
                        $q = white("
							Your Reservation Details
							$name
							(?P<Address> .+?)
							T: (?P<Phone> .+?)
							Confirmation Number
						");
                        $res = re2dict($q, $text);
                        $res['HotelName'] = $name;

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('Arrival Date : (.+? \d{4})');

                        return strtotime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('Departure Date : (.+? \d{4})');

                        return strtotime($date);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = reni('
							Guest Information
							(.+?)
							Phone :
						');

                        return [$name];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return rew('Adults (\d+)');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return rew('Children (\d+)');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return rew('Total Rooms : (\d+)');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return reni('Room \d+ : (.+?) \s+ - \s+');
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Subtotal:', +1);

                        return cost($x);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Taxes and Fees:', +1);

                        return cost($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Grand Total:', +1);

                        return total($x, 'Total');
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
