<?php

namespace AwardWallet\Engine\woodfield\Email;

class It1849388 extends \TAccountCheckerExtended
{
    public $mailFiles = "woodfield/it-11216415.eml, woodfield/it-1587009.eml, woodfield/it-1626003.eml, woodfield/it-1632626.eml, woodfield/it-1655377.eml, woodfield/it-1849388.eml, woodfield/it-1939386.eml, woodfield/it-1967624.eml, woodfield/it-1967658.eml, woodfield/it-1967681.eml, woodfield/it-3664040.eml, woodfield/it-3664041.eml";
    public $reFrom = "@laquinta.com";
    public $reSubject = [
        "en"=> "La Quinta Hotel Reservation for",
    ];
    public $reBody = 'La Quinta';
    public $reBody2 = [
        "en"=> "Check-In Date:",
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!empty($headers['from']) && strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (!empty($headers['subject']) && stripos($headers["subject"], $re) !== false) {
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
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#Your\s*Reservation\s*Confirmation\s*(?:No)?[:\#\s]+([\w-]+)#is");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $subj = implode("\n", (nodes("//*[contains(text(), 'Your Reservation Confirmation')]/ancestor::span[1]//text()[normalize-space(.)!='']")));

                        if (node("//*[contains(text(), 'Your Reservation Confirmation')]/ancestor::span[1]//a[contains(@href, 'propertyProfile') or contains(@href, 'hotel-details')]")) {
                            $hotel = white(text(xpath("//*[contains(text(), 'Your Reservation Confirmation')]/ancestor::span[1]//a[contains(@href, 'propertyProfile') or contains(@href, 'hotel-details')]")));
                        } else {
                            $hotel = "[^\n]+";
                        }
                        $this->http->Log($subj);

                        return [
                            'HotelName' => nice(re("#Your\s+Reservation\s+Confirmation\s+(?:No|\#)\s*:\s*[\d\s]+\s+({$hotel})\s+(.*?)\n\s*([\d\s-]+)$#ms", $subj)),
                            'Address'   => nice(re(2)),
                            'Phone'     => preg_replace("#\s+#", "", re(3)),
                        ];
                    // }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = $this->getField("Check-In Date:");
                        $time = $this->getField("Check-In Time:");

                        return strtotime(uberDateTime("$date $time"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = $this->getField("Check-Out Date:");
                        $time = $this->getField("Check-Out Time:");

                        return strtotime(uberDateTime("$date $time"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = re('/Your\s*Name:(.+)Check-In\s*Date:/is');

                        return [nice($name)];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return $this->getField("Number of Rooms:");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $rate = re("#Nightly\s*Rate:\s*(.?\d+[.]\d+\s*(?:[A-Z]+)?)#i");
                        $cost = cost($rate);
                        $currency = currency($rate);

                        return "$cost $currency";
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#(.*?)(?:Cancel\s+this\s+reservation|$)#ms", implode(' ', nodes("(//*[normalize-space(text()) = 'IF YOU HAVE TO CANCEL']/ancestor::div[1]//text()[normalize-space(.)])[position()>1]"))));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return nice($this->getFIeld("Room Type:"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $cost = $this->getField("Estimated Total w/Tax:");

                        return [
                            'Total'    => cost($cost),
                            'Currency' => currency($cost),
                        ];
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

    private function getField($s)
    {
        return node("//*[normalize-space(.)='{$s}']/following::text()[normalize-space(.)][1]");
    }
}
