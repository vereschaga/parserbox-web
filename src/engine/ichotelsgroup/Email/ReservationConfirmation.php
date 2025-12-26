<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = "ichotelsgroup/it-1663432.eml, ichotelsgroup/it-1664740.eml, ichotelsgroup/it-1665426.eml, ichotelsgroup/it-1665694.eml, ichotelsgroup/it-1675247.eml, ichotelsgroup/it-1732916.eml, ichotelsgroup/it-1904095.eml, ichotelsgroup/it-3.eml, ichotelsgroup/it-3387948.eml, ichotelsgroup/it-4.eml";

    private $detectBody = [
        'Reservation and Hotel Details',
        'Reservering en hoteldetails voor',
        '2017 InterContinental Hotels Group',
    ];

    private $provider = 'ihg';

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'ihg.com') !== false
                && isset($headers['subject'])
                && stripos($headers['subject'], 'Your Reservation Confirmation #') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detectBody as $detect) {
            if (stripos($body, $detect) !== false && stripos($body, $this->provider) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'ihg.com') !== false;
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
                        return re('#(?:Your\s+Confirmation\s+Number\s+is|Uw bevestigingsnummer is|Confirmation)\s*\#?\s*([\w\-]+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $hotelName = node("//u[contains(normalize-space(text()), 'Greetings from')]", null, true, '/Greetings from\s+(.+)/iu');

                        if (empty($hotelName)) {
                            $hotelName = node("(//text()[contains(normalize-space(.), 'Your Hotel')])[2]", null, true, '/Your Hotel\s*\S*\s*(.+)/');
                        }
                        $regex = white('#
							(?:Check-Out|Uitchecken) : .+?
							([A-Z] .+?) (?: \s{2,} | \n)
							(\w.+?) \n
							(?: Front Desk : (.+?) \n)?
							(?: View Map and | Reserve a Car | Receptie)
						#su');

                        if (preg_match($regex, $text, $m)) {
                            if (rew('\d+:\d+', $m[1])) {
                                return null;
                            }

                            return [
                                'HotelName' => !empty($hotelName) ? $hotelName : nice($m[1]),
                                'Address'   => nice($m[2], ','),
                                'Phone'     => $m[3] ?? re('#(?:Front\s+Desk|Receptie):\s+(.+?)\n#i'),
                            ];
                        } else {
                            return [
                                'HotelName' => $hotelName,
                                'Address'   => node("//a[contains(., 'Map')]/ancestor::tr[1]/preceding-sibling::tr[1]"),
                                'Rooms'     => node("//span[contains(normalize-space(.), 'Number of rooms')]/following-sibling::text()[1]"),
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = [];

                        foreach (['CheckIn' => 'Check-In|Inchecken|Check In', 'CheckOut' => 'Check-Out|Uitchecken|Check Out'] as $key => $value) {
                            $subj = re('#(?:' . $value . '):\s+(.+?)\n#');
                            $res[$key . 'Date'] = $subj;
                        }

                        foreach ($res as &$date) {
                            if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $date, $m)) {
                                $date = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $date));
                            }
                        }

                        if ($this->identifyDateFormat($res['CheckInDate'], $res['CheckOutDate']) === 1) {
                            $format = "$2.$1.$3";
                        } else {
                            $format = "$1.$2.$3";
                        }

                        foreach ($res as &$date) {
                            $date = strtotime(preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", $format, $date));
                        }

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $names = nodes("(//td[contains(., 'Name') and not(descendant::td)]/following-sibling::td[1])[1]");

                        if (empty($names) && ($lName = node("//a[contains(@href, 'userid') and img and contains(@href, 'account')]/img/@src", null, true, '/lastname\=(\w+)/'))) {
                            $names[] = node("//text()[contains(., 'Dear')]", null, true, '/Dear\s+(.+),/') . ' ' . $lName;
                        }

                        if (empty($names)) {
                            $names = [re('#(?:Name|Naam):\s+(.+)#i')];
                        }

                        return ['GuestNames' => $names, 'Guests' => count($names)];
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $res = re('#Membership\s*\#:\s+([\w\-]+)#i');

                        if (empty($res) && ($acc = node("//a[contains(@href, 'userid') and img and contains(@href, 'account')]/@href", null, true, '/userid\%3D(\d+)/'))) {
                            $res = $acc;
                        }

                        return [$res];
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailLanguages()
    {
        return ["en", "nl"];
    }

    private function identifyDateFormat($date1, $date2)
    {
//    define("DATE_MONTH_FIRST", "1");
//    define("DATE_DAY_FIRST", "0");
//    define("DATE_UNKNOWN_FORMAT", "-1");
        if (preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date1, $m) && preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date2, $m2)) {
            if (intval($m[1]) > 12 || intval($m2[1]) > 12) {
                return 0;
            } elseif (intval($m[2]) > 12 || intval($m2[2]) > 12) {
                return 1;
            } else {
                //try to guess format
                $diff = [];

                foreach (['$3-$2-$1', '$3-$1-$2'] as $i => $format) {
                    $tempdate1 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date1);
                    $tempdate2 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date2);

                    if (($tstd1 = strtotime($tempdate1)) !== false && ($tstd2 = strtotime($tempdate2)) !== false && ($tstd2 - $tstd1 > 0)) {
                        $diff[$i] = $tstd2 - $tstd1;
                    }
                }
                $min = min($diff);

                return array_flip($diff)[$min];
            }
        }

        return -1;
    }
}
