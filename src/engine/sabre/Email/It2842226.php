<?php

namespace AwardWallet\Engine\sabre\Email;

class It2842226 extends \TAccountCheckerExtended
{
    public $mailFiles = "sabre/it-2842226.eml";

    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?@pop3.amadeus.net#i', 'blank', ''],
    ];
    public $reHtml = [
        ['#AMADEUS\.COM#i', 'blank', ''],
    ];
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#@pop3.amadeus.net#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#@pop3.amadeus.net#i', 'blank', ''],
    ];
    private $date = 0;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("text/plain");
                    // echo $text;
                    // die();

                    $parts = explode("\r\n\r\n\r\n\r\n\r\n", $text);

                    $its = [];
                    $airs = [];

                    foreach ($parts as $k=>$v) {
                        if (strpos($v, 'RESERVATION NUMBER(S)') !== false) {
                            unset($parts[$k]);
                        } elseif (strpos($v, 'This document is automatically generated.') !== false) {
                            unset($parts[$k]);
                        } elseif (strpos($v, 'AIR')) {
                            $airs[] = trim($v);
                        } elseif (strpos($v, 'HOTEL')) {
                            $its[] = trim($v);
                        }
                    }

                    $flights = [];

                    foreach ($airs as $air) {
                        $airline = re("#\n([A-Z]{2})\s+\d+#", $air);
                        $flights[$airline][] = $air;
                    }

                    foreach ($flights as $f) {
                        $its[] = implode('__SPLIT__', $f);
                    }

                    return $its;
                },

                "#AIR#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $airline = re("#\n([A-Z]{2})\s+\d+#");

                        return re("#RESERVATION NUMBER\(S\)[^\n]+{$airline}/(\S+)#", text($this->http->Response['body']));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return explode("__SPLIT__", $text);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                "AirlineName"  => re("#\n([A-Z]{2})\s+(\d+)#"),
                                "FlightNumber" => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                "DepCode" => TRIP_CODE_UNKNOWN,
                                "DepName" => trim(substr($text, 28, 15)),
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#(\d+)(\w+)#", substr($text, 22, 5)) . ' ' . en(re(2));
                            $time = re("#(\d+)(\d{2})([AP]+)#", substr($text, 58, 5)) . ':' . re(2) . ' ' . re(3) . 'M';
                            $dt = strtotime($date . ', ' . $time, $this->date);

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                "ArrCode" => TRIP_CODE_UNKNOWN,
                                "ArrName" => trim(substr($text, 43, 15)),
                            ];
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#(\d+)(\w+)#", substr($text, 22, 5)) . ' ' . en(re(2));
                            $time = re("#^.{63}\s+(\d+)(\d{2})([AP]+)#", $text) . ':' . re(2) . ' ' . re(3) . 'M';
                            $dt = strtotime($date . ', ' . $time, $this->date);

                            return $dt;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#AIRCRAFT:\s+([^\n]+)#"));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#SEAT\s+(\S+)#");
                        },
                    ],
                ],

                "#HOTEL#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#CONFIRMATION:\s+(\S+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return [
                            'HotelName' => re("#^.{28}([^\n]+)[\r\n]+.{28}([^\n]+[\r\n]+[^\n]+)#"),
                            'Address'   => nice(re(2)),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = [];
                        $fields = [
                            'CheckInDate' => 'CHECK-IN',
                            'CheckOutDate'=> 'CHECK-OUT',
                        ];

                        foreach ($fields as $field=>$re) {
                            $res[$field] = strtotime(re("#{$re}\s*:\s*(\d+)(\w+)#") . ' ' . en(re(2)), $this->date);
                        }

                        return $res;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#TELEPHONE\s*:\s*(\S+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#CANCELLATION POLICY:([^\n]+)#"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return [
                            "Total"   => re("#TOTAL[^\n].*?([\d\.]+)\s+(\S+)#"),
                            "Currency"=> re(2),
                        ];
                    },
                ],
            ],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $result = parent::ParsePlanEmail($parser);

        return $result;
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
