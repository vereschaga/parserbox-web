<?php

namespace AwardWallet\Engine\singaporeair\Email;

class It3661254 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Thank you for flying with Singapore Airlines#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]singaporeair[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]singaporeair[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "01.04.2016, 13:20";
    public $crDate = "01.04.2016, 12:50";
    public $xPath = "";
    public $mailFiles = "singaporeair/it-3661254.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $date = rew('Flight 1  .+?  (\w+ \d+ , \d{4})');
                    $date = strtotime($date);
                    $this->anchor = $date;

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('We are pleased to confirm')) {
                            return 'confirmed';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = rew('Your itinerary  (.+?)  Additional information');
                        $q = white('[A-Z]{2} \d+  Operated by');

                        return splitter("/($q)/su", $info);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = rew('([A-Z]{2} \d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $q = white('\d+:\d+ (?:AM|PM)  (\w.+?) \n');

                            return ure("/$q/isu", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $dt1 = strtotime($time1, $this->anchor);
                            $dt2 = strtotime($time2, $this->anchor);

                            if ($dt2 < $dt1) {
                                $dt2 = strtotime('+1 day', $dt2);
                            }

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $q = white('\d+:\d+ (?:AM|PM)  (\w.+?) \n');

                            return ure("/$q/isu", 2);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            $q = white('(?P<Cabin> \w+) Class \( (?P<BookingClass> \w) \)');
                            $res = re2dict($q, $text);

                            return $res;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $q = white('- (\d+\w)');

                            if (preg_match_all("/$q/su", $text, $m)) {
                                return nice($m[1]);
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
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
