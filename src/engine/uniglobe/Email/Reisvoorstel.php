<?php

namespace AwardWallet\Engine\uniglobe\Email;

class Reisvoorstel extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?uniglobe|http://www\.travel-case\.com/logos/uniglobe#i', 'us', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#info@uniglobe-ap-travel\.nl#i', 'us', ''],
    ];
    // var $reProvider = [
    // ['#uniglobe-ap-travel\.nl#i','us',''],
    // ];
    public $fnLanguage = "";
    public $langSupported = "nl";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "8012";
    public $upDate = "26.01.2015, 11:16";
    public $crDate = "30.12.2014, 22:10";
    public $xPath = "";
    public $mailFiles = "uniglobe/it-2315652.eml, uniglobe/it-2315674.eml, uniglobe/it-2315683.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (stripos($text, "AMADEUS.COM") !== false) {
                        return null;
                    }
                    $this->passengers = nodes('//table[./following-sibling::table[contains(., "Ticket 1:")]]//tr | //table[normalize-space(.) = "Passagiers"]/following-sibling::table[1]//tr');
                    $this->year = re('#\d{4}#i', $this->parser->getHeader('date'));

                    if (!$this->year) {
                        return null;
                    }

                    return xpath('//table/following-sibling::table[1 and .//tr[contains(., "Datum") and contains(., "Vluchtnr")]]');
                },

                "#.*#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        if (!node('.//tr[contains(., "Trein")]')) {
                            $s = re('#:\s+(.*)#i', node('./following-sibling::table[1]//tr[contains(., "Prijs inclusief tax")]'));

                            return total($s);
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.//tr[contains(., "Datum") and contains(., "Vluchtnr")]/following-sibling::tr');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w{2})\s+(\d+)#i', node('./td[2]'), $m)) {
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
                            $res = null;
                            $dateStr = re('#^\d+[A-Z]+$#i', node('./td[1]')) . $this->year;

                            foreach (['Dep' => 5, 'Arr' => 6] as $key => $value) {
                                if (preg_match('#(\d+:\d+)(\+\d)?#i', node('./td[' . $value . ']'), $m)) {
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[1]);

                                    if (isset($m[2])) {
                                        $res[$key . 'Date'] = strtotime($m[2] . ' day', $res[$key . 'Date']);
                                    }
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
                            return node('./td[last()]');
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $trainReservations = [];

                    foreach ($it as &$res) {
                        $trainIndices = [];
                        $i = 0;

                        foreach ($res['TripSegments'] as $ts) {
                            if ($ts['Cabin'] == 'Trein') {
                                $trainIndices[] = $i;
                            }
                            $i++;
                        }

                        if ($trainIndices) {
                            $trainReservation = [
                                'Kind'          => 'T',
                                'RecordLocator' => CONFNO_UNKNOWN,
                                'Passengers'    => $this->passengers,
                                'TripCategory'  => TRIP_CATEGORY_TRAIN,
                            ];

                            foreach ($trainIndices as $trainI) {
                                $ts = $res['TripSegments'][$trainI];
                                $ts['Type'] = $ts['AirlineName'] . ' ' . $ts['FlightNumber'];
                                unset($ts['Cabin']);
                                unset($ts['AirlineName']);
                                unset($ts['FlightNumber']);
                                $trainReservation['TripSegments'][] = $ts;
                                unset($res['TripSegments'][$trainI]);
                            }
                            $trainReservations[] = $trainReservation;
                        }
                    }

                    foreach ($trainReservations as $tr) {
                        $it[] = $tr;
                    }

                    return $it;
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
        return ["nl"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
