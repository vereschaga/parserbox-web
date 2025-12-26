<?php

namespace AwardWallet\Engine\lner\Email;

class It3 extends \TAccountCheckerExtended
{
    public $reFrom = "#@eastcoast\.#i";
    public $reProvider = "#@eastcoast\.#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@eastcoast#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "lner/it-1603257.eml, lner/it-2.eml, lner/it-3.eml, lner/it-4.eml, lner/it-5.eml, lner/it-6.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#Your confirmation number is:\s*([\w-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = re("#Dear (.+?),#");

                        return [$name];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $n = re("#has been charged\s*([^\s]+)#");

                        return total($n);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re("#Your confirmation number is#i")) {
                            return 'confirmed';
                        }
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Departs:')]/ancestor-or-self::tr[1]");
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re('#(.+)\s+at#', node('.//td[2]'));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node("ancestor::table[1]/preceding::u[1]/following::text()[normalize-space()][1]");
                            $date = re('#\s+on\s+(.+)#', $date);

                            $time1 = re('#\s+at\s+(.+)#', node('.//td[2]'));
                            $time2 = re('#\s+at\s+(.+)#', node('./following-sibling::tr[2]/td[2]'));

                            $dt1 = "$date $time1";
                            $dt2 = "$date $time2";
                            $dt1 = totime(uberDateTime($dt1));
                            $dt2 = totime(uberDateTime($dt2));

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re('#(.+)\s+at#', node('./following-sibling::tr[2]/td[2]'));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $type = node('./following-sibling::tr[contains(., "Ticket Type:")]/td[2]');

                            return re("#(.+)\s+(?:anytime|off(?:\s*|-)?peak)#i", $type);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seats = node('./following-sibling::tr[3]');

                            if (re('#seats\s*reserved#i', $seats) && preg_match_all("#\b(\d+\w+)\b#", $seats, $ms)) {
                                return implode(',', $ms[1]);
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
}
