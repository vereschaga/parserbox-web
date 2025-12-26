<?php

namespace AwardWallet\Engine\swissotel\Email;

class It2356551 extends \TAccountCheckerExtended
{
    public $mailFiles = "swissotel/it-2356551.eml";
    public $reFrom = "@swissotel.com";
    public $reSubject = [
        "en"=> "Swissôtel Hotels Reservation Details",
    ];
    public $reBody = 'Swissotel';
    public $reBody2 = [
        "en"=> "Your reservation number is:",
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
                        return reni('Your reservation number is:  (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(text(), 'QUICK LINKS') or contains(text(), 'Quick Links')]/preceding::p[1]/span[1]");
                        $addr = reni("
							$name
							(.+?)
							Tel:
						");

                        return [
                            'HotelName' => $name,
                            'Address'   => $addr,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('ARRIVING on (.+? \d{4})');

                        if (!$time = timestamp_from_format($date, 'd / m / Y|')) {
                            $time = timestamp_from_format($date, 'd-M-Y|');
                        }

                        return $time;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('DEPARTING on (.+? \d{4})');

                        if (!$time = timestamp_from_format($date, 'd / m / Y|')) {
                            $time = timestamp_from_format($date, 'd-M-Y|');
                        }

                        return $time;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return reni('Tel: ([+\s\d()]+)');
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return reni('Fax: ([+\s\d()]+)');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re("#DEAR (.+)#i", node("//text()[starts-with(normalize-space(.), 'DEAR') or starts-with(normalize-space(.), 'Dear')]"))];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return reni('(\d+) adult');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return reni('(\d+) Children');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return reni('ROOM RATE: (.+?) \n');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return reni('CANCEL POLICY: (.+?) \n');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return reni('ROOM TYPE: (.+?) \n');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = reni('TOTAL RATE: (.+?) \n');

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (reni('your reservation was completed successfully')) {
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
