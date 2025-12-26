<?php

namespace AwardWallet\Engine\jetsetter\Email;

class It1674050 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?@jetsetter#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#Your Jetsetter Reservation#i', 'us', ''],
    ];
    public $reFrom = [
        ['#@jetsetter#i', 'us', ''],
    ];
    public $reProvider = [
        ['#@jetsetter#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "27.01.2015, 17:06";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "jetsetter/it-1654041.eml, jetsetter/it-1674050.eml, jetsetter/it-1802010.eml, jetsetter/it-1802013.eml";
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
                        return re("#Your supplier confirmation number is\s*\#([\w-]+)#i");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Your Reservation')]/ancestor-or-self::tr[1]/following-sibling::tr[2]//td[2]";

                        $res = [];
                        $info = text(xpath($xpath));
                        $info = re('#(.+)(?:[ ]*\n[ ]*\n|$)#s', $info); // remove duplicate if present

                        $name = re('#(.+?)\n#', $info);
                        $addr = re('#\n(.+?)http#s', $info);
                        $addr = preg_replace('#\s+#', ' ', $addr);
                        $res['HotelName'] = trim($name);
                        $res['Address'] = trim($addr);

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Check In/Check Out')]/ancestor-or-self::tr[1]/following-sibling::tr[2]//td[3]";
                        $dates = nodes($xpath)[0];

                        if (preg_match('#(.+)/(.+)#', $dates, $ms)) {
                            return [
                                'CheckInDate'  => totime(uberDateTime($ms[1])),
                                'CheckOutDate' => totime(uberDateTime($ms[2])),
                            ];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $names = nodes("//*[contains(text(), 'Traveler(s)')]/ancestor-or-self::tr[1]/following-sibling::tr[2]/td[2]");

                        if ($names) {
                            return preg_split('#\s*(and|&|,)\s*#i', $names[0]);
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node("(//*[contains(text(), 'cancellations')]/ancestor-or-self::td[1])[1]");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Room Type')]/ancestor-or-self::tr[1]/following-sibling::tr[2]//td[3]";
                        $info = text(xpath($xpath));

                        $type = re('#(.+?)(?:\s{2,}|$)#', $info);

                        if (preg_match('#\s*(.+)\s*-\s*(.+)\s*#', $type, $ms)) {
                            return [
                                'RoomType'            => $ms[1],
                                'RoomTypeDescription' => $ms[2],
                            ];
                        } else {
                            return trim($type);
                        } // no desc. found
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
