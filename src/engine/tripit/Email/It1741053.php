<?php

namespace AwardWallet\Engine\tripit\Email;

// TODO: may be removed
class It1741053 extends \TAccountCheckerExtended
{
    /*public function detectEmailByHeaders(array $headers) {
        return true;
    }*/

    public $mailFiles = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return null;

                    return xpath("//img[contains(@src, 'trip_item')]/ancestor::tr[1]");
                },

                ".//img[contains(@alt, 'Flight')]" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re('##');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            foreach (['.//p[3]', './/p[4]'] as $path) {
                                if (preg_match('#(.+?)\s+(\d+)[,]?#', node("$path"), $ms)) {
                                    return [
                                        'FlightNumber' => $ms[2],
                                        'AirlineName'  => $ms[1],
                                    ];
                                }
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = [];

                            $days = explode(' ', 'Mon Tue Wed Thu Fri Sat Sun');
                            $pred = array_map(function ($d) {
                                return "contains(text(), '$d')";
                            }, $days);
                            $pred = implode(' or ', $pred);
                            $date = node("preceding::p[$pred][1]");

                            $time1 = node('.//p[1]');
                            $time2 = node('(./following-sibling::tr[contains(., "Arrive")])[1]//p[1]');

                            if (preg_match('#(\w{3})\s*to\s*(\w{3})#', node('.//p[2]'), $ms)) {
                                $res['DepCode'] = $ms[1];
                                $res['ArrCode'] = $ms[2];
                            }

                            $res['DepDate'] = totime(uberDateTime(sprintf('%s %s', $date, $time1)));
                            $res['ArrDate'] = totime(uberDateTime(sprintf('%s %s', $date, $time2)));

                            if ($res['ArrDate'] < $res['DepDate']) {
                                $res['ArrDate'] = strtotime('+1 day', $res['ArrDate']);
                            }

                            return $res;
                        },
                    ],
                ],

                ".//img[contains(@alt, 'Car')]" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = [];

                        $days = explode(' ', 'Mon Tue Wed Thu Fri Sat Sun');
                        $pred = array_map(function ($d) {
                            return "contains(text(), '$d')";
                        }, $days);
                        $pred = implode(' or ', $pred);
                        $date = node("preceding::p[$pred][1]");

                        $time = node('.//p[1]');
                        $loc = node('.//p[3]//a');

                        if (re('#pick[-]?up#i')) {
                            $res['PickupDatetime'] = totime(uberDateTime(sprintf('%s %s', $date, $time)));
                            $res['PickupLocation'] = $loc;
                        } elseif (re('#drop[-]?off#i', node('.'))) {
                            $res['DropoffDatetime'] = totime(uberDateTime(sprintf('%s %s', $date, $time)));
                            $res['DropoffLocation'] = $loc;
                        }

                        return $res;
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        $brackets = '(?:[(]\d+[)])?\s*';

                        return re("#($brackets\d+[-]\d+[-]\d+)#", node('.//p[4]'));
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+:\d+\s*\w{2}\s*-\s*\d+:\d+\s*\w{2})#", node('.//p[4]'));
                    },
                ],

                ".//img[contains(@alt, 'Lodging')]" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:arrive|depart)\s*(.+)#i', node('.//p[2]'));
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = [];

                        $days = explode(' ', 'Mon Tue Wed Thu Fri Sat Sun');
                        $pred = array_map(function ($d) {
                            return "contains(text(), '$d')";
                        }, $days);
                        $pred = implode(' or ', $pred);
                        $date = node("preceding::p[$pred][1]");

                        $time = node('.//p[1]');

                        if (re('#arrive#i')) {
                            $res['CheckInDate'] = totime(uberDateTime(sprintf('%s %s', $date, $time)));
                        } elseif (re('#depart#i', node('.'))) {
                            $res['CheckOutDate'] = totime(uberDateTime(sprintf('%s %s', $date, $time)));
                        }

                        return $res;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#(.+?)\s*phone#is", node('.//pre[1]')),
                            node('.//p[3]//a')
                        );
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#phone[:]\s*(.+)fax#is", node('.//pre[1]')),
                            re('#([+]\d+\s+\d+\s+\d+\s+\d+)#')
                        );
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re("#fax[:]\s*(.+)reserv#is", node('.//pre[1]'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re('##');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#(.+?)[:,](.+)#', node('.//pre[1]'), $ms)) {
                            return [
                                'RoomType'            => preg_match('#bed|room|double|standard#i', $ms[1], $_) ? $ms[1] : '',
                                'RoomTypeDescription' => $ms[2],
                            ];
                        }
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    //return $it;
                    $it2 = [];
                    $n = sizeof($it);

                    foreach ($it as $i => $v) {
                        if (!isset($v['Kind'])) {
                            continue;
                        }

                        if ($v['Kind'] !== 'R' && $v['Kind'] !== 'L') {
                            $it2[] = $v;

                            continue;
                        }

                        // skip supplementary items
                        if ($v['Kind'] === 'L' && !isset($v['PickupDatetime'])) {
                            continue;
                        }

                        if ($v['Kind'] === 'R' && !isset($v['CheckInDate'])) {
                            continue;
                        }

                        // add missing DropoffDatetime from supplementary
                        // yes, layout is crazy
                        if ($v['Kind'] === 'L') {
                            for ($j = $i + 1; $j < $n; $j++) {
                                if (isset($it[$j]['Kind']) && $it[$j]['Kind'] == 'L'
                                        && isset($it[$j]['DropoffDatetime'])) {
                                    break;
                                }
                            }

                            if ($j !== $n) {
                                $v2 = $v;
                                $v2['DropoffDatetime'] = $it[$j]['DropoffDatetime'];
                                $v2['DropoffLocation'] = $it[$j]['DropoffLocation'];
                                $it2[] = $v2;
                            }
                        }

                        // add missing CheckOutDate from supplementary
                        if ($v['Kind'] === 'R') {
                            for ($j = $i + 1; $j < $n; $j++) {
                                if (isset($it[$j]['Kind']) && $it[$j]['Kind'] == 'R'
                                        && isset($it[$j]['CheckOutDate'])) {
                                    break;
                                }
                            }

                            if ($j !== $n) {
                                $v2 = $v;
                                $v2['CheckOutDate'] = $it[$j]['CheckOutDate'];
                                $it2[] = $v2;
                            }
                        }
                    }

                    $names = [trim(re('#(.+)\s*is\s*going#i', node('//h1[1]')))];

                    foreach ($it2 as $i => $_) {
                        if ($it2[$i]['Kind'] === 'T') {
                            $it2[$i]['Passengers'] = $names;
                        } elseif ($it2[$i]['Kind'] === 'R') {
                            $it2[$i]['GuestNames'] = $names;
                        }
                    }

                    return $it2;
                },
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
