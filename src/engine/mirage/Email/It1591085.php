<?php

namespace AwardWallet\Engine\mirage\Email;

// parsers with similar formats: gcampaigns/HResConfirmation (object), marriott/ReservationConfirmation (object), marriott/It2506177, triprewards/It3520762, woodfield/It2220680, goldpassport/WelcomeTo

class It1591085 extends \TAccountCheckerExtended
{
    public $mailFiles = "mirage/it-2816888.eml, mirage/it-2816900.eml";
    public $reFrom = "groupcampaigns@pkghlrss.com";
    public $reSubject = [
        "en"=> "Confirmation",
    ];
    public $reBody = '/manage.passkey.com/';
    public $reBody2 = [
        "en"=> "Reservation Name:",
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!empty($headers['from']) || !empty($headers['subject'])) {
            return false;
        }

        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

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
                        return node('//*[contains(text(), "Online Confirmation:") or contains(text(), "Acknowledgement Number")]/ancestor-or-self::td[1]/following-sibling::td[1]');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $hn = re("#Thank you for choosing (.*?) as your#is");

                        if (empty($hn)) {
                            $hn = re("#Thank you for making your hotel reservation. The staff of\s+(.+)\s+is looking#");
                        }

                        return $hn;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(node('//*[contains(text(), "Arrival Date:")]/ancestor-or-self::td[1]/following-sibling::td[1]'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(node('//*[contains(text(), "Departure Date:")]/ancestor-or-self::td[1]/following-sibling::td[1]'));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = re("# as your (.*?) destination#is");

                        if (empty($addr)) {
                            $addr = $it['HotelName'];
                        }

                        return $addr;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return node('//*[contains(text(), "Reservation Name:")]/ancestor-or-self::td[1]/following-sibling::td[1]');
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $guests = node('//*[contains(text(), "Number of Guests:")]/ancestor-or-self::td[1]/following-sibling::td[1]');

                        if (!empty($guests)) {
                            return $guests;
                        } else {
                            $t = node("//*[contains(text(), 'Date')]/ancestor-or-self::*[1][contains(.,'Guest(s)') and contains(.,'Status') and contains(.,'Rate')]/following::text()[1]");

                            return [
                                "Guests" => re("#^\w+\s+\d+,\s+\d{4}\s+(\d+)#", $t),
                                "Rate"   => re("#^\w+\s+\d+,\s+\d{4}\s+\d+\s+\w+\s+([\d\.]+)\s*$#", $t),
                                "Status" => re("#^\w+\s+\d+,\s+\d{4}\s+\d+\s+(\w+)#", $t),
                            ];
                        }
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return node('//*[contains(text(), "Number of Rooms:")]/ancestor-or-self::td[1]/following-sibling::td[1]');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node('//*[contains(text(), "Cancel Policy:")]/ancestor-or-self::td[1]/following-sibling::td[1]');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return node('//*[contains(text(), "Room Type:")]/ancestor-or-self::td[1]/following-sibling::td[1]');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return node('//*[contains(text(), "Total Charge:")]/ancestor-or-self::td[1]/following-sibling::td[1]');
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(node('//*[contains(text(), "Date Booked:")]/ancestor-or-self::td[1]/following-sibling::td[1]'));
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
