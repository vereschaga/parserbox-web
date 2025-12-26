<?php

namespace AwardWallet\Engine\hotelclub\Email;

class HotelConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#HotelClub\s+booking\s+number#i', 'us', '2000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#confirmation@hotelclub\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#hotelclub\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "30.10.2015, 09:41";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "hotelclub/it-2114146.eml, hotelclub/it-2260113.eml, hotelclub/it-2265116.eml, hotelclub/it-2269233.eml, hotelclub/it-3151820.eml, hotelclub/it-3155925.eml";
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
                        return re_white('Hotel confirmation number:  (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $r = '#Hotel confirmation number:\s*(?:\*?\s*\w+\s*\*?)\s*HOTEL\s*(.+?)\s*\n\s*(.*?)\s*(?:Phone:|Hotel\s+confirmation\s+number)#si';

                        if (preg_match($r, $text, $m) and $m[2]) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => $m[2],
                            ];
                        }
                        $name = re_white('
							Hotel confirmation number:
							(?: \w+)
							HOTEL
							(.+?)
							Hotel confirmation number:
						');
                        $name = nice($name);

                        $url = node("//*[contains(normalize-space(text()), 'hotel details')]/@href");
                        $s = file_get_contents($url); // Don't know why, but GetURL does not work
                        $a = strip_tags(re('#<span itemprop="streetAddress">(.*)</span>#i', $s));

                        if ($a) {
                            $addr = nice($a);
                        } else {
                            $addr = $name;
                        }

                        return [
                            'HotelName' => $name,
                            'Address'   => str_replace("View map of ", "", $addr),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Check-in:\s+\w+,\s*(\d+\s+\w+\s+\d+),?\s*(\d{2})?(\d{2})?#i', $text, $m)) {
                            $d = $m[1];
                            $t = (isset($m[2]) and isset($m[3])) ? $m[2] . ':' . $m[3] : null;

                            return strtotime($d . ' ' . $t);
                        }
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Check-out:\s+\w+,\s*(\d+\s+\w+\s+\d+),?\s*(\d{2})?(\d{2})?#i', $text, $m)) {
                            $d = $m[1];
                            $t = (isset($m[2]) and isset($m[3])) ? $m[2] . ':' . $m[3] : null;

                            return strtotime($d . ' ' . $t);
                        }
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Phone:\s+([\d\s\(\)\-]+)#'));
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Fax:\s+(\d[\d\s\(\)\-]+)#'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [between('Hotel reservations under:', 'Nice job!')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white('(\d+) guests');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re_white('(\d+) Room');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re_white('(.\d+[.]\d+) avg\/night');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(str_replace('Hotel policies', '', between('Cancellation:', '**Please')));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $info = between('Room description:', 'Check-in:');
                        $q = white('(.+?) - (.+)');

                        if (preg_match("/$q/isu", $info, $ms)) {
                            return [
                                'RoomType'            => nice($ms[1]),
                                'RoomTypeDescription' => nice($ms[2]),
                            ];
                        }

                        if ($info) {
                            return $info;
                        } else {
                            return cell('Room description:', +1);
                        }
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('(.[\d.,]+)  Taxes and fees');

                        return cost($x);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Taxes and fees  ([A-Z$]+[\d.,]+)');

                        return cost($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Total trip cost ([A-Z$]+[\d,.]+)');

                        return total($x, 'Total');
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return trim(cell('Member Rewards applied', +1), '-');
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            between("You'll earn", 'in Member Rewards'),
                            between("You earned", 'in Member Rewards')
                        );
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('Thank you for booking your trip')) {
                            return 'confirmed';
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('This reservation was made on (\w+, \d+ \w+ \d+)');

                        return strtotime($date);
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
