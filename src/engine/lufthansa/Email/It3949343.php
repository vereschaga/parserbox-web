<?php

namespace AwardWallet\Engine\lufthansa\Email;

class It3949343 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?online@booking-lufthansa.com#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#online@booking-lufthansa.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]lufthansa#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "nl";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "20.06.2016, 14:24";
    public $crDate = "20.06.2016, 13:24";
    public $xPath = "";
    public $mailFiles = "";
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
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Boekingscode:\s+(\w+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [
                            'Passengers'    => re('#Reisdata voor:\s+(.*)#'),
                            'TicketNumbers' => re('#Ticketnummer:\s+([\d\-]+)#'),
                        ];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[contains(., "Datum") and contains(., "Vertrek")]/following-sibling::tr[contains(., "Zitplaats:")]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $s = node('./td[1]');

                            if (preg_match('#(\w{2})\s+(\d+)#i', $s, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[3]');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $s = str_replace('​', '', node('./td[2]'));

                            if (preg_match('#(\d+)\.\s+(\w+)#', $s, $m)) {
                                $d = $m[1] . ' ' . en($m[2]);

                                foreach (['Dep' => 5, 'Arr' => 6] as $key => $value) {
                                    $t = re('#\d+:\d+#', str_replace('​', '', node('./td[' . $value . ']')));
                                    $res[$key . 'Date'] = strtotime($d . ', ' . $t);
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[4]');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $s = node('./td[7]');

                            if (preg_match('#(.*)\s+\((\w)\).*Zitplaats:\s+(.*)#i', $s, $m)) {
                                return [
                                    'Cabin'        => $m[1],
                                    'BookingClass' => $m[2],
                                    'Seats'        => $m[3],
                                ];
                            }
                        },
                    ],
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
        return ["nl"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
