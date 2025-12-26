<?php

namespace AwardWallet\Engine\marriott\Email;

class It2253960 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]marriott[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#Marriott\s+Reservations#i";
    public $reProvider = "#[@.]marriott[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "26.12.2014, 08:26";
    public $crDate = "24.12.2014, 07:36";
    public $xPath = "";
    public $mailFiles = "marriott/it-2253960.eml";
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
                        return reni('(?:
								Confirmation |
								Cancellation
							) \#? :  (\w+)
						');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = reni('
							for a stay with
							(.+?)
							by Marriott
						');
                        $addr = reni("
							Length of Stay
							$name .*?
							(\d+ .+?)
							Phone:
						");

                        return [
                            'HotelName' => $name,
                            'Address'   => $addr,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('Check-in: (.+? \d{4})');

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('Check-out: (.+? \d{4})');

                        return totime($date);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return reni('Phone: ([\d- ]+)');
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return reni('Fax: ([\d- ]+)');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//*[contains(text(), 'Guest ')]/following::text()[1]");

                        return nice($ppl);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return reni('Guests in room: (\d+)');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return reni('Number of rooms: (\d+)');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re_white('([\d.,]+ per night)');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $rooms = nodes("//*[contains(text(), 'Room type:')]/following::text()[1]");

                        return implode('|', nice($rooms));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (reni('we have canceled your reservation')) {
                            return 'cancelled';
                        }
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        if (reni('we have canceled your reservation')) {
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
