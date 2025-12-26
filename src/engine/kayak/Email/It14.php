<?php

namespace AwardWallet\Engine\kayak\Email;

class It14 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#booking on KAYAK#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#@KAYAK.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]KAYAK.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "28.08.2015, 15:10";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "kayak/it-14.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "";

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
                        return re("#\n\s*Fare code\s*:\s*([\w\d\-]+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*(.*?)\s+Flight\s+(\d+)#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\b([A-Z]{3})\b:#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $base = re("#Departure\s+\w+,\s*(\w+\s*\d+)#");

                            if ($base) {
                                $base .= ', ' . $this->getEmailYear();
                            }

                            $dep = totime($base . ', ' . re("#Take\-off\s*:\s*(\d+:\d+\w)#") . 'm');
                            $arr = totime($base . ', ' . re("#Landing\s*:\s*(\d+:\d+\w)#") . 'm');

                            if ($arr < $dep) {
                                $arr += 24 * 3600;
                            }

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#Landing.*?\b([A-Z]{3})\b:#ms");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Fare\s+code\s*:\s*[\w\d\-]+\s*\|\s*([^\n]+)#");
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
