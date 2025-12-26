<?php

namespace AwardWallet\Engine\lufthansa\Email;

class BookingDetails extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?lufthansa#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]lufthansa#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]lufthansa#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "01.06.2015, 07:31";
    public $crDate = "28.05.2015, 15:19";
    public $xPath = "";
    public $mailFiles = "lufthansa/it-2762861.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";
    public $confNo;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->confNo = re('#Lufthansa\s+booking\s+code:\s+([\w\-]+)#i');

                    $text = re('#Your\s+itinerary\s+(.*?)\s+Total\s+Price\s+of\s+your\s+ticket#si');
                    $reservations = splitter('#(\w{3}\.\s+\d+\s+\w+\s+\d{4}\s*:.*)#i');
                    // print_r($reservations);

                    $total = re('#Total\s+Price\s+for\s+all\s+Passengers\s+(.*)#i');

                    if ($total) {
                        $total = total($total, 'Amount');
                        $this->parsedValue('TotalCharge', $total);
                    }

                    return $reservations;

                //$r = '#\w{3}\.\s+\d+\s+\w+\s+\d{4}\s*:(?:(?s).*?)Status:.*#i';
                    //if (preg_match_all($r, $text, $m)) {
                    //	print_r($m);
                    //}
                },

                "#\s+TRAIN\s+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return $this->confNo;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Status\s*:\s+(.*)#');
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = null;
                            $dateStr = re('#\w{3}\.\s+(\d+\s+\w+\s+\d{4})#i');
                            $r = '#([\d\s]+:[\d\s]+)h\s*([\-+]\d)?\s+(.*?)\n#i';

                            if (preg_match_all($r, $text, $matches, PREG_SET_ORDER)) {
                                foreach (['Dep' => 0, 'Arr' => 1] as $key => $value) {
                                    if (!isset($matches[$value])) {
                                        continue;
                                    }
                                    $m = $matches[$value];
                                    $t = preg_replace('#\s#', '', $m[1]);
                                    $res[$key . 'Date'] = ($dateStr) ? strtotime($dateStr . ', ' . $t) : null;

                                    if ($res[$key . 'Date'] and $m[2]) {
                                        $res[$key . 'Date'] = strtotime($m[2], $res[$key . 'Date']);
                                    }
                                    $res[$key . 'Name'] = $m[3];
                                }
                            }

                            return $res;
                        //foreach ()
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#\n(\w{2})(\d+)\n#i', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },
                    ],
                ],

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return $this->confNo;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Status\s*:\s+(.*)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#\n(\w{2})(\d+)\n#i', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;
                            $dateStr = re('#\w{3}\.\s+(\d+\s+\w+\s+\d{4})#i');
                            $r = '#\n([\d\s]+:[\d\s]+)h\s*(.*?\d)?\s+(.*?)\s+\((\w{3})\)#i';

                            if (preg_match_all($r, $text, $matches, PREG_SET_ORDER)) {
                                foreach (['Dep' => 0, 'Arr' => 1] as $key => $value) {
                                    if (!isset($matches[$value])) {
                                        continue;
                                    }
                                    $m = $matches[$value];
                                    $t = preg_replace('#\s#', '', $m[1]);
                                    $res[$key . 'Date'] = ($dateStr) ? strtotime($dateStr . ', ' . $t) : null;

                                    if ($res[$key . 'Date'] and $m[2]) {
                                        $res[$key . 'Date'] = strtotime($m[2] . ' day', $res[$key . 'Date']);
                                    }
                                    $res[$key . 'Name'] = $m[3];
                                    $res[$key . 'Code'] = $m[4];
                                }
                            }

                            return $res;
                        //foreach ()
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Class\s*:\s+(.*?)\s+\((\w)\)\n#', $text, $m)) {
                                return [
                                    'Cabin'        => $m[1],
                                    'BookingClass' => $m[2],
                                ];
                            }
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re('#Seat:\s+(\d+\w)\s*\n#');
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
