<?php

namespace AwardWallet\Engine\omnihotels\Email;

class It3 extends \TAccountCheckerExtended
{
    public $reFrom = "#@omnihotels.#i";
    public $reProvider = "#[@.]omnihotels.#i";
    public $rePlain = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Stay at the Omni|Stay at Omni#i";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "omnihotels/it-3.eml, omnihotels/it-68137525.eml";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null) {
                        return re("#\n\s*Confirmation\s*\#\s*:?\s*([\d\w\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null) {
                        if (re("#\n\s*Your Reservation\s*\n\s*([^\n]+)\s+(.*?)\s+((?:\d{2,}\-)+\d{2,}(?:\s*or\s*.*?)*)\s+View your#ms")) {
                            $result = [
                                'HotelName' => re(1),
                                'Address'   => nice(re(2)),
                                'Phone'     => re(3),
                            ];
                        }

                        if (!isset($return)) {
                            $name = implode("\n", nodes("//text()[normalize-space(.)='Get Directions']/ancestor::td[1]//text()"));

                            if (preg_match("#\s*(.+)\s+(.+)\s*/\s*Get\s*Directions\s+Phone:\s*([\d\-]+)#", $name, $m)) {
                                $result = [
                                    'HotelName' => $m[1],
                                    'Address'   => $m[2],
                                    'Phone'     => $m[3],
                                ];
                            }
                        }

                        return $result;
                    },

                    "CheckInDate" => function ($text = '', $node = null) {
                        return strtotime(re("#\n\s*Arriving\s*:?\s*([^\n]+)#i"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null) {
                        return strtotime(re("#\n\s*Departing\s*:?\s*([^\n]+)#i"));
                    },

                    "Guests" => function ($text = '', $node = null) {
                        return re("#\n\s*Guests\s*:?\s*(\d+)#i");
                    },

                    "GuestNames" => function ($text = '', $node = null) {
                        return re("#\n\s*(?:Welcome|Hello)\s+([^,]+),#");
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
}
