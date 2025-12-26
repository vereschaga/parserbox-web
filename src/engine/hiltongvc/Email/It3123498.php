<?php

namespace AwardWallet\Engine\hiltongvc\Email;

class It3123498 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]hgvc[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]hgvc[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]hgvc[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "02.10.2015, 13:16";
    public $crDate = "02.10.2015, 13:06";
    public $xPath = "";
    public $mailFiles = "hiltongvc/it-3123498.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'complex');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('Confirmation \#  (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							YOUR DESTINATION
							(?P<HotelName> \w.+?) \n
							(?P<Address> .+?) \n
							(?P<Phone> [\d-]{8,})
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('Arrival Date : (.+? \d{4})');
                        $time = rew('Check In Time : (\w.+?) \n');

                        $dt = strtotime($date);
                        $dt = strtotime($time, $dt);

                        return $dt;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('Departure Date : (.+? \d{4})');
                        $time = rew('Check Out Time : (\w.+?) \n');

                        $dt = strtotime($date);
                        $dt = strtotime($time, $dt);

                        return $dt;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $info = rew('YOUR INFORMATION (.+?) Confirmation \#');
                        $guests = explode('&', $info);

                        return nice($guests);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return reni('Room Type : (\w.+?) \n');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Total Price : (\S.+?) \n');

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
