<?php

namespace AwardWallet\Engine\jetstar\Email;

class It2158324 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@jetstar[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@jetstar[.]com#i";
    public $reProvider = "#[@.]jetstar[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "";
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
                        return re_white('Booking Reference  (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//*[normalize-space(text()) = 'Passenger']/ancestor::table[1]"));
                        $q = white('
							(?: MISS | MR | MRS) (.+?) \n
						');

                        if (preg_match_all("/$q/iu", $info, $ms)) {
                            return nice($ms[1]);
                        }
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//*[normalize-space(text()) = 'Passenger']/ancestor::table[1]"));
                        $q = white('
							number (\w+) \n
						');

                        if (preg_match_all("/$q/iu", $info, $ms)) {
                            return implode(',', $ms[1]);
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Booking Date: (\d+ \w+ \d+)');

                        return strtotime($date);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[normalize-space(text()) = 'Starter']/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $info = node('./preceding::tr[1]');
                            $fl = re_white('Change Flight (\w+)', $info);

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $name = node('./td[2]//strong[1]');

                            return nice($name);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $info = node('./td[2]');
                            $date = uberDate($info);
                            $time = uberTime($info);

                            $dt = strtotime($date);
                            $dt = strtotime($time, $dt);

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $name = node('./td[3]//strong[1]');

                            return nice($name);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $info = node('./td[3]');
                            $date = uberDate($info);
                            $time = uberTime($info);

                            $dt = strtotime($date);
                            $dt = strtotime($time, $dt);

                            return $dt;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $pl = re_white('^ (.+?) Starter');

                            return nice($pl);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $x = re_white('Flight Duration: (\d+hr \d+min)');

                            return nice($x);
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
