<?php

namespace AwardWallet\Engine\wynnlv\Email;

use PlancakeEmailParser;

class It2457232 extends \TAccountCheckerExtended
{
    public $mailFiles = "wynnlv/it-2457232.eml";

    private $detects = [
        'We thank you for choosing ',
    ];

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
                        return re("#\n\s*Confirmation[\#\s]+([A-Z\d\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#We thank you for choosing (.*?) and look#ix");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Arrival Date\s*:\s*([^\n]+)#") . ',' . uberTime(re("#Check-in time is(.+)#")));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Departure Date\s*:\s*([^\n]+)#") . ',' . uberTime(re("#Check-out time is(.+)#i")));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return [
                            'Address' => re("#\n\s*([^\n]+)\s+Phone\s*:\s*([+\d\(\)\-A-Z ]{5,})\s+Fax\s*:\s*([+\d\(\)\-A-Z ]{5,})#"),
                            'Phone'   => re(2),
                            'Fax'     => re(3),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reservation Name\s*:\s*([^\n]+)#ix");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'cancellation')]/ancestor-or-self::p[1]");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room Type\s*:\s*([^\n]+)#ix");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#We are pleased to (\w+)#ix");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Date\s*:\s*(\d+\-\w+\-\d+)#"));
                    },
                ],
            ],
        ];
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() . $parser->getPlainBody();

        if (false === stripos($body, 'Wynn Las Vegas')) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }
}
