<?php

namespace AwardWallet\Engine\wowair\Email;

class It2391191 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#(?:\n[>\s*]*From\s*:[^\n]*?wow\.is|Booking\s+confirmation\s+and\s+itinerary)#i', 'blank', ''],
    ];
    public $reHtml = '#(?:\n[>\s*]*From\s*:[^\n]*?wow\.is|Booking\s+confirmation\s+and\s+itinerary)#i';
    public $rePDF = "";
    public $reSubject = [
        ['Confirmation from WOW air', 'blank', ''],
    ];
    public $reFrom = [
        ['#wowair#i', 'us', ''],
    ];
    public $reProvider = [
        ['#(?:wowair|wow\.is)#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "2";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "09.07.2015, 15:13";
    public $crDate = "20.01.2015, 19:13";
    public $xPath = "";
    public $mailFiles = "wowair/it-2140912.eml, wowair/it-2251737.eml, wowair/it-2391191.eml, wowair/it-2861672.eml, wowair/it-5048303.eml, wowair/it-5060768.eml";
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
                        return re("#\n\s*Reservation number\s*:\s*([A-Z\d-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = orval(
                            nodes("//*[contains(text(), 'Going Out')]/following::tr[string-length(normalize-space(.))>1][1]/following::tr[contains(., 'AIR-') and contains(., '/')]"),
                            nodes("//*[contains(text(), 'Going Out')]/following::tr[string-length(normalize-space(.))>1][1]/.//tr[not(.//tr) and contains(., 'AIR-') and contains(., '/')]")
                        );
                        $all = [];

                        foreach ($names as &$name) {
                            $all[beautifulName(re("#^(.*?)\s+AIR\-#", $name))] = 1;
                        }

                        return array_keys($all);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Total\s+paid\s+([^\n]+)#i"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total\s+fare\s+([^\n]+)#ix"));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes,\s*fees\s*&\s*charges\s+([^\n]+)#ix"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $itineraries = xpath("//*[contains(text(), 'Going Out')]/following::tr[string-length(normalize-space(.))>1][1]/td");

                        if ($itineraries->length == 0
                           || !re("#\n\s*Flight\s+number\s+(?:\([^\)]+\))?:?\s*([A-Z\d]{2}\s*\d+)#", $itineraries->item(0))
                        ) {
                            $itineraries = xpath("//td[not(.//td) and contains(., 'Going Out')]/../td");
                        }

                        return $itineraries;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#\n\s*Flight\s+number\s+(?:\([^\)]+\))?:?\s*([A-Z\d]{2}\s*\d+)#"));
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepName' => re("#(?:^|\n)\s*From:\s*(.*?)\s+\(([A-Z]{3})\)#"),
                                'DepCode' => re(2),
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            if (
                                preg_match("#\n\s*\w+,\s*(\d+\w*?\s+\w+,\s*\d+).*?\n\s*Departure:\s*([^\n]+)\s+([^\n]+)#", text($text), $m)
                                || preg_match("#\n\s*\w+,\s*(\d+\w*?\s+\w+,\s*\d+)\s*\-\s*(\d+:\d+)#", text($text), $m)
                            );
                            $date = clear("#,#", $m[1]);
                            $dep = totime($date . ',' . uberTime($m[2]));

                            if (isset($m[3])) {
                                $arrTime = $m[3];
                            } else {
                                $date = clear("#,#", re("#\n\s*To:.+?\n\s*\w+,\s*(\d+\w*?\s+\w+,\s*\d+)\s*\-\s*(\d+:\d+)#s"));
                                $arrTime = re(2);
                            }
                            $arr = totime($date . ',' . uberTime($arrTime));

                            if (re("#\(([+-]\d+)\)#")) {
                                $arr = strtotime(re(1) . ' days', $arr);
                            }

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'ArrName' => re("#(?:^|\n)\s*To:\s*(.*?)\s+\(([A-Z]{3})\)#"),
                                'ArrCode' => re(2),
                            ];
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
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
