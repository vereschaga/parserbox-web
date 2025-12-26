<?php

namespace AwardWallet\Engine\logitravel\Email;

class It2265828 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Muchas\s+Gracias[,.]?\s+Logitravel#i', 'blank', '2000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]logitravel[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]logitravel[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "es";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "26.01.2015, 10:36";
    public $crDate = "26.01.2015, 10:22";
    public $xPath = "";
    public $mailFiles = "logitravel/it-2265828.eml, logitravel/it-2269095.eml";
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
                        return rew('Localizador Compañía aérea:  (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $q = white('^ (\w.+?) \( Adulto \)');

                        if (preg_match_all("/$q/imu", $text, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Precio Final:  (.+?) \n');

                        return total($x);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Salida:')]/ancestor::table[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = rew('Aerolínea: .+?, (\w+\d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return reni('Salida:  \d+:\d+ (.+?) \n');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = en(uberDate(1));
                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $date = totime($date);
                            $dt1 = strtotime($time1, $date);
                            $dt2 = date_carry($time2, $dt1);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return reni('Llegada:  \d+:\d+ (.+?) \n');
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
        return ["es"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
