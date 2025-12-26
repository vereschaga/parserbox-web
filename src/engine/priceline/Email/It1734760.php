<?php

namespace AwardWallet\Engine\priceline\Email;

class It1734760 extends \TAccountCheckerExtended
{
    public $mailFiles = "";

    private $detectBody = [
        'This is a transactional email from',
    ];

    private $provider = 'Priceline.com';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation Number\s+([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node("//img[contains(@src, '/htlimg/')]/ancestor::table[2]/preceding-sibling::table[1]");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Check-In\s+([^\n]+)#")));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Check-Out\s+([^\n]+)#")));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return [
                            'Address' => nice(glue(re("#(\d+\D+)\s*([\d\-+\(\)\s]+)\s*#ms", xpath("//img[contains(@src, '/htlimg/')]/ancestor::table[1]/preceding-sibling::table[1]/descendant::tr[1]")))),
                            'Phone'   => re(2),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $names = [];

                        re("#\n\s*Reservation Name\s+([^\t\d]+)Confirmation Number#", function ($m) use (&$names) {
                            $names[nice($m[1])] = 1;
                        }, $text);

                        return array_keys($names);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $max = null;
                        re("#\n\s*Room\s+(\d+)\s+#", function ($m) use (&$max) {
                            $max = $m[1];
                        }, $text);

                        return $max;
                    },
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->provider) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detectBody as $dt) {
            if (stripos($body, $dt) !== false && stripos($body, $this->provider) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], $this->provider) !== false;
    }
}
